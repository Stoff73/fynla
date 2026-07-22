import SwiftUI

struct AppRootView: View {
    let session: AppSession
    let privacyLockController: PrivacyLockController?
    let subscriptionModel: SubscriptionModel
    let appleSubscriptionManager: any AppleSubscriptionManaging
    @State private var registrationModel: RegistrationModel
    @State private var loginModel: LoginModel
    @State private var passwordResetModel: PasswordResetModel
    @State private var isPresentingPasswordReset: Bool

    init(
        session: AppSession,
        privacyLockController: PrivacyLockController? = nil,
        subscriptionModel: SubscriptionModel,
        appleSubscriptionManager: any AppleSubscriptionManaging,
        registrationActions: RegistrationActions,
        loginActions: LoginActions,
        passwordResetActions: PasswordResetActions,
        webBaseURL: URL,
        initiallyPresentsRegistration: Bool = false,
        initiallyPresentsPasswordReset: Bool = false
    ) {
        self.session = session
        self.privacyLockController = privacyLockController
        self.subscriptionModel = subscriptionModel
        self.appleSubscriptionManager = appleSubscriptionManager
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
                if let privacyLockController {
                    LockedView(
                        message: "Unlock to view your financial plan.",
                        isUnlocking: privacyLockController.isUnlocking,
                        failure: privacyLockController.lastUnlockFailure,
                        unlock: privacyLockController.canUnlockWithFaceID
                            ? { @MainActor @Sendable in
                                Task { @MainActor in
                                    await privacyLockController.unlock()
                                }
                            }
                            : nil,
                        signInAnotherWay: {
                            Task { @MainActor in
                                await privacyLockController.signInAnotherWay()
                            }
                        }
                    )
                } else {
                    LockedView(message: "Unlock to view your financial plan.")
                }
            case .authenticatedUnlocked:
                UnlockedView(
                    privacyLockController: privacyLockController,
                    subscriptionModel: subscriptionModel,
                    appleSubscriptionManager: appleSubscriptionManager
                )
            case .deletingAccount:
                LockedView(message: "Updating your account securely…")
            }
        }
        .preferredColorScheme(.light)
        .task {
            if let privacyLockController {
                await privacyLockController.completeLaunch()
                await privacyLockController.refreshFaceIDOffer()
            } else {
                session.completeLaunch(hasAuthenticatedSession: false)
            }
        }
        .onChange(of: session.state) { _, _ in
            guard let privacyLockController else { return }
            Task { @MainActor in
                await privacyLockController.refreshFaceIDOffer()
            }
        }
        .task(id: session.state) {
            if session.state == .authenticatedUnlocked {
                await subscriptionModel.start()
            } else if session.state == .signedOut {
                subscriptionModel.stop()
            }
        }
        .overlay {
            if let privacyLockController,
               privacyLockController.shouldOfferFaceID,
               session.state == .authenticatedUnlocked,
               !privacyLockController.isPrivacyCovered
            {
                ZStack {
                    FynlaColor.Token.horizon500.color.opacity(0.45)
                        .ignoresSafeArea()
                    FaceIDOptInView(controller: privacyLockController)
                        .padding(FynlaSpacing.standard)
                }
                .accessibilityIdentifier("face-id.opt-in.cover")
            }
        }
        .overlay {
            if privacyLockController?.isPrivacyCovered == true {
                FynlaColor.Token.horizon500.color
                    .ignoresSafeArea()
                    .accessibilityLabel("Fynla content hidden")
                    .accessibilityIdentifier("privacy-lock.cover")
            }
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

private struct UnlockedView: View {
    let privacyLockController: PrivacyLockController?
    let subscriptionModel: SubscriptionModel
    let appleSubscriptionManager: any AppleSubscriptionManaging

    var body: some View {
        NavigationStack {
            VStack(spacing: FynlaSpacing.large) {
                Text("Fynla")
                    .font(FynlaTypography.pageTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Text("Your secure workspace is ready.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)

                NavigationLink {
                    SettingsView(
                        subscriptionModel: subscriptionModel,
                        appleManager: appleSubscriptionManager,
                        privacyLockController: privacyLockController
                    )
                } label: {
                    Label("Settings", systemImage: "gearshape")
                        .font(FynlaTypography.button)
                        .frame(maxWidth: .infinity, minHeight: 44)
                }
                .buttonStyle(.bordered)
                .tint(FynlaColor.primaryText)
                .accessibilityIdentifier("app.unlocked.settings")

                if let privacyLockController {
                    FynlaButton(
                        "Sign out",
                        variant: .secondary,
                        accessibilityLabel: "Sign out of Fynla"
                    ) {
                        Task { @MainActor in
                            await privacyLockController.signOut()
                        }
                    }
                    .accessibilityIdentifier("app.unlocked.sign-out")
                }
            }
            .padding(FynlaSpacing.large)
            .accessibilityElement(children: .contain)
            .accessibilityIdentifier("app.unlocked")
            .toolbar {
                if let privacyLockController {
                    ToolbarItem(placement: .topBarTrailing) {
                        Button("Lock", systemImage: "lock") {
                            privacyLockController.lock()
                        }
                        .accessibilityIdentifier("app.unlocked.lock")
                    }
                }
            }
        }
    }
}
