import Foundation
import Observation

enum LoginField: String, Sendable {
    case email
    case password
}

enum LoginStep: Sendable, Equatable {
    case signIn
    case verification(maskedEmail: String)
    case multiFactor(maskedEmail: String)
    case restoration(RestorationChallenge)
    case authenticated
}

struct TransientSubmissionResult: Sendable, Equatable {
    let succeeded: Bool
    let clearInput: Bool

    static func success(clearInput: Bool = true) -> Self {
        Self(succeeded: true, clearInput: clearInput)
    }

    static func failure(clearInput: Bool) -> Self {
        Self(succeeded: false, clearInput: clearInput)
    }
}

struct LoginActions: Sendable {
    let login: @MainActor @Sendable (String, String) async throws -> LoginStep
    let verifyLogin: @MainActor @Sendable (String) async throws -> Void
    let resendLogin: @MainActor @Sendable () async throws -> LoginResendResult
    let verifyMFA: @MainActor @Sendable (String) async throws -> Void
    let useRecoveryCode: @MainActor @Sendable (String) async throws -> Void
    let restoreAccount: @MainActor @Sendable (String?) async throws -> Void
    let cancel: @MainActor @Sendable () -> Void

    @MainActor
    static func coordinator(
        _ coordinator: AuthenticationCoordinator,
        deviceLabel: String
    ) -> Self {
        Self(
            login: { email, password in
                try await coordinator.login(
                    email: email,
                    password: password,
                    deviceLabel: deviceLabel
                )
                switch coordinator.state {
                case let .loginVerification(_, maskedEmail):
                    return .verification(maskedEmail: maskedEmail)
                case let .multiFactor(_, maskedEmail):
                    return .multiFactor(maskedEmail: maskedEmail)
                case let .restoration(challenge):
                    return .restoration(challenge)
                case .authenticated, .passwordChangeRequired:
                    return .authenticated
                default:
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
            },
            verifyLogin: { code in
                try await coordinator.verifyLogin(
                    code: code,
                    deviceLabel: deviceLabel
                )
            },
            resendLogin: {
                try await coordinator.resendLogin()
            },
            verifyMFA: { code in
                try await coordinator.verifyMFA(
                    code: code,
                    deviceLabel: deviceLabel
                )
            },
            useRecoveryCode: { code in
                try await coordinator.useRecoveryCode(
                    code,
                    deviceLabel: deviceLabel
                )
            },
            restoreAccount: { code in
                try await coordinator.restoreAccount(
                    mfaCode: code,
                    deviceLabel: deviceLabel
                )
            },
            cancel: {
                coordinator.signOut()
            }
        )
    }
}

@MainActor
@Observable
final class LoginModel {
    var email = ""

    private(set) var step: LoginStep = .signIn
    private(set) var fieldErrors: [LoginField: String] = [:]
    private(set) var message: String?
    private(set) var messageIsError = false
    private(set) var isSubmitting = false
    private(set) var canResendVerification = true
    private(set) var lockoutRemainingSeconds = 0

    var isLocked: Bool {
        lockoutWithoutCountdown || lockoutRemainingSeconds > 0
    }

    private let actions: LoginActions
    private let now: @MainActor @Sendable () -> Date
    private var lockoutDeadline: Date?
    private var lockoutWithoutCountdown = false
    private var attemptGeneration = 0

    init(
        actions: LoginActions,
        now: @escaping @MainActor @Sendable () -> Date = { Date() }
    ) {
        self.actions = actions
        self.now = now
    }

    func submitLogin(password: String) async -> TransientSubmissionResult {
        tickLockout()
        guard case .signIn = step,
              !isLocked,
              !isSubmitting
        else {
            return .failure(clearInput: false)
        }
        fieldErrors = [:]
        message = nil
        messageIsError = false
        let submittedEmail = email.trimmingCharacters(in: .whitespacesAndNewlines)
        if !Self.isValidEmail(submittedEmail) {
            fieldErrors[.email] = "Enter a valid email address."
        }
        if password.isEmpty {
            fieldErrors[.password] = "Password is required."
        }
        guard fieldErrors.isEmpty else {
            return .failure(clearInput: false)
        }

        let attempt = beginAttempt()
        do {
            let next = try await actions.login(submittedEmail, password)
            guard isCurrent(attempt) else {
                return .failure(clearInput: true)
            }
            step = next
            finishAttempt()
            return .success()
        } catch {
            guard isCurrent(attempt) else {
                return .failure(clearInput: true)
            }
            finishAttempt()
            apply(error)
            return .failure(clearInput: true)
        }
    }

    func submitVerification(code: String) async -> TransientSubmissionResult {
        guard case .verification = step else {
            return .failure(clearInput: true)
        }
        guard Self.isSixDigitCode(code) else {
            message = "Enter the six-digit verification code."
            messageIsError = true
            return .failure(clearInput: true)
        }
        return await performTransient(successStep: .authenticated) {
            try await self.actions.verifyLogin(code)
        }
    }

    func resendVerification() async {
        guard case .verification = step,
              canResendVerification,
              !isSubmitting,
              !isLocked
        else {
            return
        }
        let attempt = beginAttempt()
        do {
            let result = try await actions.resendLogin()
            guard isCurrent(attempt) else { return }
            finishAttempt()
            canResendVerification = result.canResend
            message = result.message
            messageIsError = false
        } catch {
            guard isCurrent(attempt) else { return }
            finishAttempt()
            if case AuthError.resendExhausted = error {
                canResendVerification = false
            }
            apply(error)
        }
    }

    func submitMFA(code: String) async -> TransientSubmissionResult {
        guard case .multiFactor = step else {
            return .failure(clearInput: true)
        }
        guard Self.isSixDigitCode(code) else {
            message = "Enter the six-digit authenticator code."
            messageIsError = true
            return .failure(clearInput: true)
        }
        return await performTransient(successStep: .authenticated) {
            try await self.actions.verifyMFA(code)
        }
    }

    func submitRecovery(code: String) async -> TransientSubmissionResult {
        guard case .multiFactor = step else {
            return .failure(clearInput: true)
        }
        let value = code.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !value.isEmpty else {
            message = "Enter a recovery code."
            messageIsError = true
            return .failure(clearInput: true)
        }
        return await performTransient(successStep: .authenticated) {
            try await self.actions.useRecoveryCode(value)
        }
    }

    func submitRestoration(code: String) async -> TransientSubmissionResult {
        guard case let .restoration(challenge) = step else {
            return .failure(clearInput: true)
        }
        let value = code.trimmingCharacters(in: .whitespacesAndNewlines)
        if challenge.requiresMFA, value.isEmpty {
            message = "Enter your authenticator or recovery code."
            messageIsError = true
            return .failure(clearInput: true)
        }
        return await performTransient(successStep: .authenticated) {
            try await self.actions.restoreAccount(challenge.requiresMFA ? value : nil)
        }
    }

    func beginMultiFactor(maskedEmail: String) {
        invalidateAttempt()
        step = .multiFactor(maskedEmail: maskedEmail)
        clearPresentation()
    }

    func beginRestoration(_ challenge: RestorationChallenge) {
        invalidateAttempt()
        step = .restoration(challenge)
        clearPresentation()
    }

    func cancel() {
        invalidateAttempt()
        step = .signIn
        canResendVerification = true
        lockoutDeadline = nil
        lockoutWithoutCountdown = false
        lockoutRemainingSeconds = 0
        clearPresentation()
        actions.cancel()
    }

    func tickLockout() {
        guard let lockoutDeadline else {
            lockoutRemainingSeconds = 0
            return
        }
        lockoutRemainingSeconds = max(
            0,
            Int(ceil(lockoutDeadline.timeIntervalSince(now())))
        )
        if lockoutRemainingSeconds == 0 {
            self.lockoutDeadline = nil
        }
    }

    private func performTransient(
        successStep: LoginStep,
        operation: @escaping @MainActor @Sendable () async throws -> Void
    ) async -> TransientSubmissionResult {
        tickLockout()
        guard !isSubmitting, !isLocked else {
            return .failure(clearInput: false)
        }
        let attempt = beginAttempt()
        do {
            try await operation()
            guard isCurrent(attempt) else {
                return .failure(clearInput: true)
            }
            step = successStep
            finishAttempt()
            return .success()
        } catch {
            guard isCurrent(attempt) else {
                return .failure(clearInput: true)
            }
            finishAttempt()
            apply(error)
            return .failure(clearInput: true)
        }
    }

    private func beginAttempt() -> Int {
        attemptGeneration += 1
        isSubmitting = true
        fieldErrors = [:]
        message = nil
        messageIsError = false
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
        fieldErrors = [:]
        message = nil
        messageIsError = false
    }

    private func apply(_ error: Error) {
        messageIsError = true
        switch error {
        case let AuthError.locked(serverMessage, remainingSeconds):
            message = serverMessage
            if let remainingSeconds, remainingSeconds > 0 {
                lockoutWithoutCountdown = false
                lockoutDeadline = now().addingTimeInterval(TimeInterval(remainingSeconds))
                lockoutRemainingSeconds = remainingSeconds
            } else {
                lockoutDeadline = nil
                lockoutWithoutCountdown = true
                lockoutRemainingSeconds = 0
            }
        case let AuthError.validation(serverMessage, errors):
            message = serverMessage ?? errors.values.first?.first ?? "Check the details and try again."
        case let AuthError.unauthenticated(serverMessage):
            message = serverMessage ?? "Authentication could not be completed."
        case let AuthError.resendExhausted(serverMessage):
            message = serverMessage
        case let AuthError.rateLimited(serverMessage):
            message = serverMessage ?? "Too many attempts. Please try again later."
        case let AuthError.notFound(serverMessage):
            message = serverMessage ?? "The authentication session is no longer available."
        case let AuthError.requestRejected(serverMessage, _):
            message = serverMessage
        case AuthError.offline:
            message = "You appear to be offline. Check your connection and try again."
        case AuthError.transport:
            message = "Fynla could not connect securely. Please try again."
        case AuthError.decoding, AuthError.server:
            message = "Fynla could not complete authentication. Please try again."
        case let AuthError.registrationUnavailable(serverMessage):
            message = serverMessage
        case AuthenticationCoordinatorError.cancelled, is CancellationError:
            step = .signIn
            lockoutDeadline = nil
            lockoutWithoutCountdown = false
            lockoutRemainingSeconds = 0
            message = nil
            messageIsError = false
        case AuthenticationCoordinatorError.fullLoginRequired:
            step = .signIn
            message = "Please sign in again to continue."
        case AuthenticationCoordinatorError.busy:
            message = "Authentication is already in progress."
        default:
            message = "Fynla could not complete authentication. Please try again."
        }
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
