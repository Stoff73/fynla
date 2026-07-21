import SwiftUI

struct MultiFactorView: View {
    @Bindable var model: LoginModel

    @State private var code = ""
    @State private var recoveryCode = ""
    @State private var showsRecovery = false
    @State private var submissionTask: Task<Void, Never>?
    @State private var countdownTask: Task<Void, Never>?

    var body: some View {
        ScrollView {
            VStack(spacing: FynlaSpacing.large) {
                VStack(spacing: FynlaSpacing.small) {
                    Text("Two-factor authentication")
                        .font(FynlaTypography.sectionTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)
                    Text(instructions)
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .multilineTextAlignment(.center)
                }

                if showsRecovery {
                    recoveryContent
                } else {
                    authenticatorContent
                }

                if let message = model.message {
                    Text(message)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.Token.raspberry700.color)
                        .multilineTextAlignment(.center)
                        .accessibilityLabel("Error: \(message)")
                        .accessibilityIdentifier("login.mfa.message")
                }

                if model.lockoutRemainingSeconds > 0 {
                    Text("Try again in \(model.lockoutRemainingSeconds) seconds.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.Token.raspberry700.color)
                        .accessibilityIdentifier("login.mfa.lockoutCountdown")
                }

                Button("Cancel and sign in again") { cancel() }
                    .fynlaAuthTextAction()
                    .accessibilityIdentifier("login.mfa.cancel")
            }
            .fynlaAuthPagePadding()
        }
        .background(FynlaColor.pageBackground.ignoresSafeArea())
        .onDisappear(perform: cancelWorkAndClearSecrets)
    }

    private var authenticatorContent: some View {
        VStack(spacing: FynlaSpacing.medium) {
            VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                Text("Six-digit authenticator code")
                    .font(FynlaTypography.bodySmall.weight(.semibold))
                    .foregroundStyle(FynlaColor.primaryText)
                TextField("Six-digit authenticator code", text: constrainedCode)
                    .font(.system(.title2, design: .monospaced, weight: .bold))
                    .multilineTextAlignment(.center)
                    .fynlaVerificationKeyboard()
                    .fynlaAuthField(
                        identifier: "login.mfa.code",
                        isFocused: false,
                        isError: model.messageIsError
                    )
                    .disabled(model.isSubmitting || model.isLocked)
            }

            FynlaButton(
                "Verify authenticator code",
                isLoading: model.isSubmitting,
                isDisabled: model.isSubmitting || model.isLocked || code.isEmpty
            ) { submitMFA() }
            .accessibilityIdentifier("login.mfa.submit")

            Button("Use a recovery code") {
                cancelSubmissionAndClearInputs()
                showsRecovery = true
            }
            .fynlaAuthTextAction()
            .disabled(model.isSubmitting || model.isLocked)
            .accessibilityIdentifier("login.mfa.useRecovery")
        }
    }

    private var recoveryContent: some View {
        VStack(spacing: FynlaSpacing.medium) {
            VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                Text("Recovery code")
                    .font(FynlaTypography.bodySmall.weight(.semibold))
                    .foregroundStyle(FynlaColor.primaryText)
                TextField("Recovery code", text: $recoveryCode)
                    .fynlaRecoveryCodeInput()
                    .fynlaAuthField(
                        identifier: "login.mfa.recoveryCode",
                        isFocused: false,
                        isError: model.messageIsError
                    )
                    .disabled(model.isSubmitting || model.isLocked)
            }

            FynlaButton(
                "Use recovery code",
                isLoading: model.isSubmitting,
                isDisabled: model.isSubmitting || model.isLocked || recoveryCode.isEmpty
            ) { submitRecovery() }
            .accessibilityIdentifier("login.mfa.recoverySubmit")

            Button("Back to authenticator code") {
                cancelSubmissionAndClearInputs()
                showsRecovery = false
            }
            .fynlaAuthTextAction()
            .disabled(model.isSubmitting || model.isLocked)
            .accessibilityIdentifier("login.mfa.useAuthenticator")
        }
    }

    private var instructions: String {
        if case let .multiFactor(maskedEmail) = model.step {
            return "Enter the code from your authenticator app for \(maskedEmail), or use one recovery code."
        }
        return "Enter the code from your authenticator app, or use one recovery code."
    }

    private var constrainedCode: Binding<String> {
        Binding(
            get: { code },
            set: {
                code = String(
                    decoding: $0.utf8.filter { (48...57).contains($0) }.prefix(6),
                    as: UTF8.self
                )
            }
        )
    }

    private func submitMFA() {
        let value = code
        submissionTask?.cancel()
        submissionTask = Task { @MainActor in
            defer { submissionTask = nil }
            let result = await model.submitMFA(code: value)
            guard !Task.isCancelled else { return }
            if result.clearInput { code = "" }
            startCountdownIfNeeded()
        }
    }

    private func submitRecovery() {
        let value = recoveryCode
        submissionTask?.cancel()
        submissionTask = Task { @MainActor in
            defer { submissionTask = nil }
            let result = await model.submitRecovery(code: value)
            guard !Task.isCancelled else { return }
            if result.clearInput { recoveryCode = "" }
            startCountdownIfNeeded()
        }
    }

    private func startCountdownIfNeeded() {
        countdownTask?.cancel()
        guard model.lockoutRemainingSeconds > 0 else { return }
        countdownTask = Task { @MainActor in
            while !Task.isCancelled, model.lockoutRemainingSeconds > 0 {
                try? await Task.sleep(for: .seconds(1))
                guard !Task.isCancelled else { return }
                model.tickLockout()
            }
        }
    }

    private func cancel() {
        cancelWorkAndClearSecrets()
        model.cancel()
    }

    private func cancelSubmissionAndClearInputs() {
        submissionTask?.cancel()
        submissionTask = nil
        code = ""
        recoveryCode = ""
    }

    private func cancelWorkAndClearSecrets() {
        cancelSubmissionAndClearInputs()
        countdownTask?.cancel()
        countdownTask = nil
    }
}

extension View {
    @ViewBuilder
    func fynlaRecoveryCodeInput() -> some View {
        #if os(iOS)
        self
            .textInputAutocapitalization(.characters)
            .autocorrectionDisabled()
        #else
        self
        #endif
    }
}
