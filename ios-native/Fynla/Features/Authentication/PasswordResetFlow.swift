import Foundation
import Observation

enum PasswordResetStep: Sendable, Equatable {
    case email
    case emailVerification
    case multiFactor
    case newPassword
    case complete
}

struct PasswordResetActions: Sendable {
    let request: @MainActor @Sendable (String) async throws -> PasswordResetRequestResult
    let verifyEmail: @MainActor @Sendable (String, String) async throws -> PasswordResetEmailVerification
    let resend: @MainActor @Sendable (String) async throws -> PasswordResetResendResult
    let verifyMFA: @MainActor @Sendable (String, String) async throws -> PasswordResetAuthorization
    let useRecoveryCode: @MainActor @Sendable (String, String) async throws -> PasswordResetAuthorization
    let reset: @MainActor @Sendable (String, String, String) async throws -> String
    let cancel: @MainActor @Sendable () -> Void

    @MainActor
    static func client(
        _ client: any PasswordResetClient,
        cancel: @escaping @MainActor @Sendable () -> Void
    ) -> Self {
        Self(
            request: { try await client.requestPasswordReset(email: $0) },
            verifyEmail: {
                try await client.verifyPasswordResetEmail(token: $0, code: $1)
            },
            resend: { try await client.resendPasswordResetCode(token: $0) },
            verifyMFA: {
                try await client.verifyPasswordResetMFA(token: $0, code: $1)
            },
            useRecoveryCode: {
                try await client.usePasswordResetRecoveryCode(
                    token: $0,
                    recoveryCode: $1
                )
            },
            reset: {
                try await client.resetPassword(
                    token: $0,
                    password: $1,
                    confirmation: $2
                )
            },
            cancel: cancel
        )
    }
}

@MainActor
@Observable
final class PasswordResetModel {
    var email = ""

    private(set) var step: PasswordResetStep = .email
    private(set) var message: String?
    private(set) var messageIsError = false
    private(set) var isSubmitting = false
    private(set) var remainingResends = 2
    private(set) var passwordError: String?

    var canResend: Bool {
        step == .emailVerification && remainingResends > 0 && !isSubmitting
    }

    private let actions: PasswordResetActions
    private var resetToken: String?
    private var attemptGeneration = 0

    init(actions: PasswordResetActions) {
        self.actions = actions
    }

    func requestReset() async -> TransientSubmissionResult {
        let submittedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines)
        guard Self.isValidEmail(submittedEmail), !isSubmitting else {
            message = "Enter a valid email address."
            messageIsError = true
            return .failure(clearInput: false)
        }
        let attempt = beginAttempt()
        do {
            let result = try await actions.request(submittedEmail)
            guard isCurrent(attempt) else { return .failure(clearInput: false) }
            finishAttempt()
            message = result.message
            messageIsError = false
            if let token = result.resetToken, !token.isEmpty {
                resetToken = token
                remainingResends = 2
                step = .emailVerification
            } else {
                resetToken = nil
                step = .email
            }
            return .success(clearInput: false)
        } catch {
            guard isCurrent(attempt) else { return .failure(clearInput: false) }
            finishAttempt()
            apply(error)
            return .failure(clearInput: false)
        }
    }

    func verifyEmail(code: String) async -> TransientSubmissionResult {
        guard step == .emailVerification,
              let resetToken,
              Self.isSixDigitCode(code)
        else {
            message = "Enter the six-digit verification code."
            messageIsError = true
            return .failure(clearInput: true)
        }
        return await performTransient(
            operation: {
                let result = try await self.actions.verifyEmail(resetToken, code)
                guard result.requiresMFA || result.canResetPassword else {
                    throw AuthError.decoding
                }
                return result
            },
            onSuccess: { result in
                self.step = result.requiresMFA ? .multiFactor : .newPassword
            }
        )
    }

    func resendEmailCode() async {
        guard canResend, let resetToken else { return }
        let attempt = beginAttempt()
        do {
            let result = try await actions.resend(resetToken)
            guard isCurrent(attempt) else { return }
            finishAttempt()
            remainingResends = max(0, result.remainingResends)
            message = result.message
            messageIsError = false
        } catch {
            guard isCurrent(attempt) else { return }
            finishAttempt()
            apply(error)
        }
    }

    func verifyMFA(code: String) async -> TransientSubmissionResult {
        guard step == .multiFactor,
              let resetToken,
              Self.isSixDigitCode(code)
        else {
            message = "Enter the six-digit authenticator code."
            messageIsError = true
            return .failure(clearInput: true)
        }
        return await performTransient(
            operation: {
                let result = try await self.actions.verifyMFA(resetToken, code)
                guard result.canResetPassword else { throw AuthError.decoding }
                return result
            },
            onSuccess: { _ in
                self.step = .newPassword
            }
        )
    }

    func useRecoveryCode(_ code: String) async -> TransientSubmissionResult {
        let value = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard step == .multiFactor,
              let resetToken,
              !value.isEmpty
        else {
            message = "Enter a recovery code."
            messageIsError = true
            return .failure(clearInput: true)
        }
        return await performTransient(
            operation: {
                let result = try await self.actions.useRecoveryCode(resetToken, value)
                guard result.canResetPassword else { throw AuthError.decoding }
                return result
            },
            onSuccess: { _ in
                self.step = .newPassword
            }
        )
    }

    func resetPassword(
        password: String,
        confirmation: String
    ) async -> TransientSubmissionResult {
        passwordError = Self.passwordValidationMessage(
            password: password,
            confirmation: confirmation
        )
        guard step == .newPassword,
              let resetToken,
              passwordError == nil
        else {
            return .failure(clearInput: false)
        }
        return await performTransient(
            operation: {
                try await self.actions.reset(resetToken, password, confirmation)
            },
            onSuccess: { successMessage in
                self.message = successMessage
                self.messageIsError = false
                self.resetToken = nil
                self.step = .complete
            }
        )
    }

    func useDifferentEmail() {
        invalidateAttempt()
        resetToken = nil
        step = .email
        remainingResends = 2
        clearPresentation()
    }

    func cancel() {
        invalidateAttempt()
        resetToken = nil
        step = .email
        remainingResends = 2
        clearPresentation()
        actions.cancel()
    }

    private func performTransient<Value: Sendable>(
        operation: @escaping @MainActor @Sendable () async throws -> Value,
        onSuccess: @escaping @MainActor @Sendable (Value) -> Void
    ) async -> TransientSubmissionResult {
        guard !isSubmitting else { return .failure(clearInput: false) }
        let attempt = beginAttempt()
        do {
            let value = try await operation()
            guard isCurrent(attempt) else { return .failure(clearInput: true) }
            onSuccess(value)
            finishAttempt()
            return .success()
        } catch {
            guard isCurrent(attempt) else { return .failure(clearInput: true) }
            finishAttempt()
            apply(error)
            return .failure(clearInput: true)
        }
    }

    private func beginAttempt() -> Int {
        attemptGeneration += 1
        isSubmitting = true
        message = nil
        messageIsError = false
        passwordError = nil
        return attemptGeneration
    }

    private func finishAttempt() {
        isSubmitting = false
    }

    private func invalidateAttempt() {
        attemptGeneration += 1
        isSubmitting = false
    }

    private func isCurrent(_ attempt: Int) -> Bool {
        attempt == attemptGeneration
    }

    private func clearPresentation() {
        message = nil
        messageIsError = false
        passwordError = nil
    }

    private func apply(_ error: Error) {
        messageIsError = true
        switch error {
        case let AuthError.validation(serverMessage, errors):
            message = serverMessage ?? errors.values.first?.first ?? "Check the details and try again."
        case let AuthError.requestRejected(serverMessage, _):
            message = serverMessage
        case let AuthError.unauthenticated(serverMessage):
            message = serverMessage ?? "Verification could not be completed."
        case let AuthError.locked(serverMessage, _):
            message = serverMessage
        case let AuthError.rateLimited(serverMessage):
            message = serverMessage ?? "Too many attempts. Please try again later."
        case AuthError.offline:
            message = "You appear to be offline. Check your connection and try again."
        case AuthError.transport:
            message = "Fynla could not connect securely. Please try again."
        case AuthError.decoding, AuthError.server:
            message = "Fynla could not complete the password reset. Please try again."
        case let AuthError.notFound(serverMessage):
            message = serverMessage ?? "The reset session is no longer available."
        case let AuthError.resendExhausted(serverMessage):
            message = serverMessage
            remainingResends = 0
        case let AuthError.registrationUnavailable(serverMessage):
            message = serverMessage
        case AuthenticationCoordinatorError.cancelled, is CancellationError:
            message = nil
            messageIsError = false
        default:
            message = "Fynla could not complete the password reset. Please try again."
        }
    }

    private static func passwordValidationMessage(
        password: String,
        confirmation: String
    ) -> String? {
        guard password.count >= 8 else {
            return "Password must be at least 8 characters."
        }
        let values = password.unicodeScalars.map(\.value)
        let lower: (UInt32) -> Bool = { (97...122).contains($0) }
        let upper: (UInt32) -> Bool = { (65...90).contains($0) }
        let digit: (UInt32) -> Bool = { (48...57).contains($0) }
        guard values.contains(where: lower),
              values.contains(where: upper),
              values.contains(where: digit),
              values.contains(where: { !lower($0) && !upper($0) && !digit($0) })
        else {
            return "Password must include lowercase, uppercase, a number and a special character."
        }
        guard password == confirmation else {
            return "Passwords do not match."
        }
        return nil
    }

    private static func isSixDigitCode(_ value: String) -> Bool {
        value.utf8.count == 6 && value.utf8.allSatisfy { (48...57).contains($0) }
    }

    private static func isValidEmail(_ value: String) -> Bool {
        guard !value.isEmpty, !value.contains(where: \.isWhitespace) else { return false }
        let parts = value.split(separator: "@", omittingEmptySubsequences: false)
        guard parts.count == 2,
              !parts[0].isEmpty,
              let domain = parts.last,
              domain.contains("."),
              !domain.hasPrefix("."),
              !domain.hasSuffix(".")
        else {
            return false
        }
        return true
    }
}

#if canImport(SwiftUI)
import SwiftUI

struct PasswordResetFlow: View {
    @Bindable var model: PasswordResetModel
    let onDismiss: @MainActor () -> Void

    @State private var code = ""
    @State private var mfaCode = ""
    @State private var recoveryCode = ""
    @State private var password = ""
    @State private var passwordConfirmation = ""
    @State private var showsRecovery = false
    @State private var submissionTask: Task<Void, Never>?
    @State private var resendTask: Task<Void, Never>?

    init(
        model: PasswordResetModel,
        onDismiss: @escaping @MainActor () -> Void = {}
    ) {
        self.model = model
        self.onDismiss = onDismiss
    }

    var body: some View {
        ScrollView {
            VStack(spacing: FynlaSpacing.large) {
                header
                stepContent
                status
                if model.step != .complete {
                    Button("Cancel") { cancel() }
                        .fynlaAuthTextAction()
                        .accessibilityIdentifier("passwordReset.cancel")
                }
            }
            .fynlaAuthPagePadding()
        }
        .background(FynlaColor.pageBackground.ignoresSafeArea())
        .onChange(of: model.step) { _, _ in clearUnusedSecrets() }
        .onDisappear(perform: cancelWorkAndClearSecrets)
    }

    private var header: some View {
        VStack(spacing: FynlaSpacing.small) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.center)
                .accessibilityAddTraits(.isHeader)
            Text(detail)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.center)
        }
    }

    @ViewBuilder
    private var stepContent: some View {
        switch model.step {
        case .email:
            emailStep
        case .emailVerification:
            verificationStep
        case .multiFactor:
            multiFactorStep
        case .newPassword:
            passwordStep
        case .complete:
            completeStep
        }
    }

    private var emailStep: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Email address")
                .font(FynlaTypography.bodySmall.weight(.semibold))
                .foregroundStyle(FynlaColor.primaryText)
            TextField("Email address", text: $model.email)
                .fynlaEmailInput()
                .fynlaAuthField(
                    identifier: "passwordReset.email",
                    isFocused: false,
                    isError: model.messageIsError
                )
                .disabled(model.isSubmitting)
            FynlaButton(
                "Send verification code",
                isLoading: model.isSubmitting,
                isDisabled: model.isSubmitting || model.email.isEmpty
            ) { requestReset() }
            .accessibilityIdentifier("passwordReset.request")
        }
    }

    private var verificationStep: some View {
        VStack(spacing: FynlaSpacing.medium) {
            TextField("Six-digit verification code", text: constrainedCode)
                .font(.system(.title2, design: .monospaced, weight: .bold))
                .multilineTextAlignment(.center)
                .fynlaVerificationKeyboard()
                .fynlaAuthField(
                    identifier: "passwordReset.verification.code",
                    isFocused: false,
                    isError: model.messageIsError
                )
                .disabled(model.isSubmitting)
            FynlaButton(
                "Verify email",
                isLoading: model.isSubmitting,
                isDisabled: model.isSubmitting || code.isEmpty
            ) { verifyEmail() }
            .accessibilityIdentifier("passwordReset.verification.submit")
            FynlaButton(
                "Send a new code",
                variant: .secondary,
                isDisabled: !model.canResend
            ) { resend() }
            .accessibilityIdentifier("passwordReset.verification.resend")
            Button("Use a different email") {
                cancelWorkAndClearSecrets()
                model.useDifferentEmail()
            }
            .fynlaAuthTextAction()
            .accessibilityIdentifier("passwordReset.verification.useDifferentEmail")
        }
    }

    @ViewBuilder
    private var multiFactorStep: some View {
        if showsRecovery {
            VStack(spacing: FynlaSpacing.medium) {
                TextField("Recovery code", text: $recoveryCode)
                    .fynlaRecoveryCodeInput()
                    .fynlaAuthField(
                        identifier: "passwordReset.mfa.recoveryCode",
                        isFocused: false,
                        isError: model.messageIsError
                    )
                    .disabled(model.isSubmitting)
                FynlaButton(
                    "Use recovery code",
                    isLoading: model.isSubmitting,
                    isDisabled: model.isSubmitting || recoveryCode.isEmpty
                ) { verifyRecovery() }
                .accessibilityIdentifier("passwordReset.mfa.recoverySubmit")
                Button("Back to authenticator code") {
                    recoveryCode = ""
                    showsRecovery = false
                }
                .fynlaAuthTextAction()
                .accessibilityIdentifier("passwordReset.mfa.useAuthenticator")
                .disabled(model.isSubmitting)
            }
        } else {
            VStack(spacing: FynlaSpacing.medium) {
                TextField("Six-digit authenticator code", text: constrainedMFACode)
                    .font(.system(.title2, design: .monospaced, weight: .bold))
                    .multilineTextAlignment(.center)
                    .fynlaVerificationKeyboard()
                    .fynlaAuthField(
                        identifier: "passwordReset.mfa.code",
                        isFocused: false,
                        isError: model.messageIsError
                    )
                    .disabled(model.isSubmitting)
                FynlaButton(
                    "Verify authenticator code",
                    isLoading: model.isSubmitting,
                    isDisabled: model.isSubmitting || mfaCode.isEmpty
                ) { verifyMFA() }
                .accessibilityIdentifier("passwordReset.mfa.submit")
                Button("Use a recovery code") {
                    mfaCode = ""
                    showsRecovery = true
                }
                .fynlaAuthTextAction()
                .accessibilityIdentifier("passwordReset.mfa.useRecovery")
                .disabled(model.isSubmitting)
            }
        }
    }

    private var passwordStep: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Use at least 8 characters with lowercase, uppercase, a number and a special character.")
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.secondaryText)
            SecureField("New password", text: $password)
                .fynlaNewPasswordInput()
                .fynlaAuthField(
                    identifier: "passwordReset.newPassword",
                    isFocused: false,
                    isError: model.passwordError != nil
                )
            SecureField("Confirm new password", text: $passwordConfirmation)
                .fynlaNewPasswordInput()
                .fynlaAuthField(
                    identifier: "passwordReset.passwordConfirmation",
                    isFocused: false,
                    isError: model.passwordError != nil
                )
            if let passwordError = model.passwordError {
                Text(passwordError)
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.Token.raspberry700.color)
                    .accessibilityIdentifier("passwordReset.passwordError")
            }
            FynlaButton(
                "Reset password",
                isLoading: model.isSubmitting,
                isDisabled: model.isSubmitting || password.isEmpty || passwordConfirmation.isEmpty
            ) { resetPassword() }
            .accessibilityIdentifier("passwordReset.reset")
        }
    }

    private var completeStep: some View {
        VStack(spacing: FynlaSpacing.medium) {
            Text("Your password has been reset. Sign in with the new password to continue.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.center)
            FynlaButton("Return to sign in") { cancel() }
                .accessibilityIdentifier("passwordReset.complete.signIn")
        }
    }

    @ViewBuilder
    private var status: some View {
        if let message = model.message {
            Text(message)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(
                    model.messageIsError
                        ? FynlaColor.Token.raspberry700.color
                        : FynlaColor.secondaryText
                )
                .multilineTextAlignment(.center)
                .accessibilityLabel(model.messageIsError ? "Error: \(message)" : message)
                .accessibilityIdentifier("passwordReset.message")
        }
    }

    private var title: String {
        switch model.step {
        case .email: "Reset your password"
        case .emailVerification: "Verify your email"
        case .multiFactor: "Two-factor authentication"
        case .newPassword: "Create a new password"
        case .complete: "Password reset complete"
        }
    }

    private var detail: String {
        switch model.step {
        case .email:
            "Enter your email address. For privacy, the response is the same whether or not an account exists."
        case .emailVerification:
            "Enter the code sent to your email address."
        case .multiFactor:
            "Confirm with your authenticator or a recovery code before changing your password."
        case .newPassword:
            "Choose a strong password that you do not use elsewhere."
        case .complete:
            "Your reset session is now closed."
        }
    }

    private var constrainedCode: Binding<String> {
        Binding(get: { code }, set: { code = Self.asciiDigits($0) })
    }

    private var constrainedMFACode: Binding<String> {
        Binding(get: { mfaCode }, set: { mfaCode = Self.asciiDigits($0) })
    }

    private func requestReset() {
        run { _ = await model.requestReset() }
    }

    private func verifyEmail() {
        let value = code
        run {
            let result = await model.verifyEmail(code: value)
            if result.clearInput { code = "" }
        }
    }

    private func resend() {
        resendTask?.cancel()
        resendTask = Task { @MainActor in
            defer { resendTask = nil }
            await model.resendEmailCode()
            guard !Task.isCancelled else { return }
            code = ""
        }
    }

    private func verifyMFA() {
        let value = mfaCode
        run {
            let result = await model.verifyMFA(code: value)
            if result.clearInput { mfaCode = "" }
        }
    }

    private func verifyRecovery() {
        let value = recoveryCode
        run {
            let result = await model.useRecoveryCode(value)
            if result.clearInput { recoveryCode = "" }
        }
    }

    private func resetPassword() {
        let submittedPassword = password
        let submittedConfirmation = passwordConfirmation
        run {
            let result = await model.resetPassword(
                password: submittedPassword,
                confirmation: submittedConfirmation
            )
            if result.clearInput {
                password = ""
                passwordConfirmation = ""
            }
        }
    }

    private func run(
        _ operation: @escaping @MainActor @Sendable () async -> Void
    ) {
        submissionTask?.cancel()
        submissionTask = Task { @MainActor in
            defer { submissionTask = nil }
            await operation()
        }
    }

    private func clearUnusedSecrets() {
        switch model.step {
        case .email:
            clearAllSecrets()
        case .emailVerification:
            mfaCode = ""
            recoveryCode = ""
            password = ""
            passwordConfirmation = ""
        case .multiFactor:
            code = ""
            password = ""
            passwordConfirmation = ""
        case .newPassword:
            code = ""
            mfaCode = ""
            recoveryCode = ""
        case .complete:
            clearAllSecrets()
        }
    }

    private func cancel() {
        cancelWorkAndClearSecrets()
        model.cancel()
        onDismiss()
    }

    private func cancelWorkAndClearSecrets() {
        submissionTask?.cancel()
        submissionTask = nil
        resendTask?.cancel()
        resendTask = nil
        clearAllSecrets()
    }

    private func clearAllSecrets() {
        code = ""
        mfaCode = ""
        recoveryCode = ""
        password = ""
        passwordConfirmation = ""
        showsRecovery = false
    }

    private static func asciiDigits(_ value: String) -> String {
        String(
            decoding: value.utf8.filter { (48...57).contains($0) }.prefix(6),
            as: UTF8.self
        )
    }
}
#endif
