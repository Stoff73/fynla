import SwiftUI

struct AppRootView: View {
    let session: AppSession
    @State private var registrationModel: RegistrationModel
    @State private var loginModel: LoginModel
    @State private var passwordResetModel: PasswordResetModel
    @State private var isPresentingPasswordReset: Bool

    init(
        session: AppSession,
        registrationActions: RegistrationActions,
        loginActions: LoginActions,
        passwordResetActions: PasswordResetActions,
        webBaseURL: URL,
        initiallyPresentsRegistration: Bool = false,
        initiallyPresentsPasswordReset: Bool = false
    ) {
        self.session = session
        _registrationModel = State(
            initialValue: RegistrationModel(
                actions: registrationActions,
                webBaseURL: webBaseURL,
                isPresentingRegistration: initiallyPresentsRegistration
            )
        )
        _loginModel = State(initialValue: LoginModel(actions: loginActions))
        _passwordResetModel = State(
            initialValue: PasswordResetModel(actions: passwordResetActions)
        )
        _isPresentingPasswordReset = State(
            initialValue: initiallyPresentsPasswordReset
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
                } else if isPresentingPasswordReset {
                    passwordResetView
                } else {
                    loginView
                }
            case .authenticating:
                if registrationModel.isPresentingRegistration {
                    RegistrationView(model: registrationModel)
                } else {
                    loginView
                }
            case .verificationRequired:
                if registrationModel.challenge != nil {
                    VerificationCodeView(model: registrationModel)
                } else {
                    loginView
                }
            case .multiFactorRequired:
                MultiFactorView(model: loginModel)
            case .restorationRequired:
                RestoreAccountFlow(model: loginModel)
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
        .task {
            session.completeLaunch(hasAuthenticatedSession: false)
        }
    }

    private var loginView: some View {
        LoginView(
            model: loginModel,
            createAccount: {
                isPresentingPasswordReset = false
                registrationModel.presentRegistration()
            },
            forgotPassword: {
                isPresentingPasswordReset = true
            }
        )
    }

    private var passwordResetView: some View {
        PasswordResetFlow(
            model: passwordResetModel,
            onDismiss: {
                isPresentingPasswordReset = false
            }
        )
    }
}

private struct LaunchingView: View {
    var body: some View {
        LoadingView(message: "Fynla is starting")
            .accessibilityIdentifier("app.launching")
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
