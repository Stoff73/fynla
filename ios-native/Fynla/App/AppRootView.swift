import SwiftUI

struct AppRootView: View {
    let session: AppSession
    @State private var registrationModel: RegistrationModel

    init(
        session: AppSession,
        registrationActions: RegistrationActions,
        webBaseURL: URL,
        initiallyPresentsRegistration: Bool = false
    ) {
        self.session = session
        _registrationModel = State(
            initialValue: RegistrationModel(
                actions: registrationActions,
                webBaseURL: webBaseURL,
                isPresentingRegistration: initiallyPresentsRegistration
            )
        )
    }

    var body: some View {
        Group {
            switch session.state {
            case .launching:
                LaunchingView()
            case .signedOut:
                if registrationModel.isPresentingRegistration {
                    RegistrationView(model: registrationModel)
                } else {
                    SignedOutView {
                        registrationModel.presentRegistration()
                    }
                }
            case .authenticating:
                if registrationModel.isPresentingRegistration {
                    RegistrationView(model: registrationModel)
                } else {
                    LoadingView(message: "Signing in securely")
                }
            case .verificationRequired:
                if registrationModel.challenge != nil {
                    VerificationCodeView(model: registrationModel)
                } else {
                    LoadingView(message: "Preparing email verification")
                }
            case .multiFactorRequired:
                SignedOutPendingView(message: "Confirm your identity to continue.")
            case .restorationRequired:
                SignedOutPendingView(message: "Restore your account to continue.")
            case .passwordChangeRequired:
                LockedView(message: "Change your password to continue.")
            case .authenticatedLocked:
                LockedView(message: "Unlock to view your financial plan.")
            case .authenticatedUnlocked:
                UnlockedView()
            case .deletingAccount:
                LockedView(message: "Updating your account securely…")
            }
        }
        .preferredColorScheme(.light)
    }
}

private struct LaunchingView: View {
    var body: some View {
        LoadingView(message: "Fynla is starting")
            .accessibilityIdentifier("app.launching")
    }
}

private struct SignedOutView: View {
    let createAccount: @MainActor () -> Void

    var body: some View {
        VStack(spacing: FynlaSpacing.large) {
            VStack(spacing: FynlaSpacing.small) {
                Text("Fynla")
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Text("Sign in to continue.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .multilineTextAlignment(.center)
            }
            .accessibilityElement(children: .combine)
            .accessibilityIdentifier("auth.signedOut")

            FynlaButton("Create account", action: createAccount)
                .frame(maxWidth: 320)
                .accessibilityIdentifier("registration.createAccount")

            Text("Native sign-in options will appear here in the next authentication step.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .padding(FynlaSpacing.large)
        .background(FynlaColor.pageBackground.ignoresSafeArea())
    }
}

private struct SignedOutPendingView: View {
    let message: String

    var body: some View {
        VStack(spacing: FynlaSpacing.small) {
            Text("Fynla")
                .font(FynlaTypography.sectionTitle)
            Text(message)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.center)
        }
        .padding(FynlaSpacing.large)
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("auth.signedOut")
    }
}

private struct LockedView: View {
    let message: String

    var body: some View {
        VStack(spacing: 12) {
            Text("Fynla is locked")
                .font(.title.bold())
            Text(message)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
        }
        .padding()
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("app.locked")
    }
}

private struct UnlockedView: View {
    var body: some View {
        VStack(spacing: 12) {
            Text("Fynla")
                .font(.title.bold())
            Text("Your secure workspace is ready.")
                .foregroundStyle(.secondary)
        }
        .padding()
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("app.unlocked")
    }
}
