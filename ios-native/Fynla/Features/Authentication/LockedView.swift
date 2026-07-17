import SwiftUI

struct LockedView: View {
    let message: String
    let isUnlocking: Bool
    let failure: PrivacyUnlockFailure?
    let unlock: (@MainActor () -> Void)?
    let signInAnotherWay: (@MainActor () -> Void)?

    init(
        message: String,
        isUnlocking: Bool = false,
        failure: PrivacyUnlockFailure? = nil,
        unlock: (@MainActor () -> Void)? = nil,
        signInAnotherWay: (@MainActor () -> Void)? = nil
    ) {
        self.message = message
        self.isUnlocking = isUnlocking
        self.failure = failure
        self.unlock = unlock
        self.signInAnotherWay = signInAnotherWay
    }

    var body: some View {
        VStack(spacing: FynlaSpacing.standard) {
            Text("Fynla is locked")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text(message)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.center)

            if let failureMessage {
                Text(failureMessage)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.primaryAction)
                    .multilineTextAlignment(.center)
                    .accessibilityIdentifier("app.locked.message")
            }

            if let unlock {
                FynlaButton(
                    "Unlock with Face ID",
                    accessibilityLabel: "Unlock Fynla with Face ID",
                    isLoading: isUnlocking,
                    isDisabled: isUnlocking,
                    action: unlock
                )
                .accessibilityIdentifier("app.locked.unlock")
            }

            if let signInAnotherWay {
                FynlaButton(
                    "Sign in another way",
                    variant: .secondary,
                    accessibilityLabel: "Sign in with your account details",
                    isDisabled: isUnlocking,
                    action: signInAnotherWay
                )
                .accessibilityIdentifier("app.locked.sign-in-another-way")
            }
        }
        .padding(FynlaSpacing.large)
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("app.locked")
    }

    private var failureMessage: String? {
        switch failure {
        case .cancelled:
            "Face ID was cancelled. Try again or sign in another way."
        case .authenticationFailed:
            "Face ID did not recognise you. Try again or sign in another way."
        case .retryRequired:
            "Fynla could not finish unlocking. Try again or sign in another way."
        case .fullLoginRequired:
            "Your protected session is no longer available. Sign in again."
        case nil:
            nil
        }
    }
}
