import SwiftUI

struct RestoreAccountFlow: View {
    @Bindable var model: LoginModel

    @State private var mfaCode = ""
    @State private var submissionTask: Task<Void, Never>?
    @State private var countdownTask: Task<Void, Never>?

    var body: some View {
        ScrollView {
            VStack(spacing: FynlaSpacing.large) {
                VStack(spacing: FynlaSpacing.small) {
                    Text("Restore your Fynla account")
                        .font(FynlaTypography.sectionTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)
                    Text(summary)
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .multilineTextAlignment(.center)
                }

                if challenge?.requiresMFA == true {
                    VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                        Text("Authenticator or recovery code")
                            .font(FynlaTypography.bodySmall.weight(.semibold))
                            .foregroundStyle(FynlaColor.primaryText)
                        TextField("Authenticator or recovery code", text: $mfaCode)
                            .fynlaRecoveryCodeInput()
                            .fynlaAuthField(
                                identifier: "login.restore.mfaCode",
                                isFocused: false,
                                isError: model.messageIsError
                            )
                            .disabled(model.isSubmitting || model.isLocked)
                    }
                }

                if let message = model.message {
                    Text(message)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.Token.raspberry700.color)
                        .multilineTextAlignment(.center)
                        .accessibilityLabel("Error: \(message)")
                        .accessibilityIdentifier("login.restore.message")
                }

                if model.lockoutRemainingSeconds > 0 {
                    Text("Try again in \(model.lockoutRemainingSeconds) seconds.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.Token.raspberry700.color)
                        .accessibilityIdentifier("login.restore.lockoutCountdown")
                }

                VStack(spacing: FynlaSpacing.medium) {
                    FynlaButton(
                        "Restore my account",
                        isLoading: model.isSubmitting,
                        isDisabled: model.isSubmitting
                            || model.isLocked
                            || (challenge?.requiresMFA == true && mfaCode.isEmpty)
                    ) { submit() }
                    .accessibilityIdentifier("login.restore.submit")

                    FynlaButton("Cancel", variant: .secondary) { cancel() }
                        .accessibilityIdentifier("login.restore.cancel")
                }
            }
            .fynlaAuthPagePadding()
        }
        .background(FynlaColor.pageBackground.ignoresSafeArea())
        .onDisappear(perform: cancelWorkAndClearSecrets)
    }

    private var challenge: RestorationChallenge? {
        guard case let .restoration(challenge) = model.step else { return nil }
        return challenge
    }

    private var summary: String {
        guard let challenge else {
            return "Confirm that you want to restore your retained account data."
        }
        let name = challenge.firstName.map { "\($0), we" } ?? "We"
        return "\(name) found your retained account after your credentials were verified. Restore it to continue where you left off."
    }

    private func submit() {
        let code = mfaCode
        submissionTask?.cancel()
        submissionTask = Task { @MainActor in
            defer { submissionTask = nil }
            let result = await model.submitRestoration(code: code)
            guard !Task.isCancelled else { return }
            if result.clearInput { mfaCode = "" }
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

    private func cancelWorkAndClearSecrets() {
        submissionTask?.cancel()
        submissionTask = nil
        countdownTask?.cancel()
        countdownTask = nil
        mfaCode = ""
    }
}
