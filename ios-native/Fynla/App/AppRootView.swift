import SwiftUI

struct AppRootView: View {
    let session: AppSession
    let privacyLockController: PrivacyLockController?
    let subscriptionModel: SubscriptionModel
    let dashboardModel: DashboardModel
    let achievementsModel: AchievementsModel
    let incomeModel: IncomeModel
    let fynModel: FynConversationModel
    let bugReportModel: BugReportModel
    let appleSubscriptionManager: any AppleSubscriptionManaging
    @State private var registrationModel: RegistrationModel
    @State private var loginModel: LoginModel
    @State private var passwordResetModel: PasswordResetModel
    @State private var isPresentingPasswordReset: Bool

    init(
        session: AppSession,
        privacyLockController: PrivacyLockController? = nil,
        subscriptionModel: SubscriptionModel,
        dashboardModel: DashboardModel,
        achievementsModel: AchievementsModel,
        incomeModel: IncomeModel,
        fynModel: FynConversationModel,
        bugReportModel: BugReportModel,
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
        self.dashboardModel = dashboardModel
        self.achievementsModel = achievementsModel
        self.incomeModel = incomeModel
        self.fynModel = fynModel
        self.bugReportModel = bugReportModel
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
                    dashboardModel: dashboardModel,
                    achievementsModel: achievementsModel,
                    incomeModel: incomeModel,
                    fynModel: fynModel,
                    bugReportModel: bugReportModel,
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
                dashboardModel.stop()
                achievementsModel.stop()
                incomeModel.stop()
                fynModel.stopAndClear()
                bugReportModel.reset()
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
    let dashboardModel: DashboardModel
    let achievementsModel: AchievementsModel
    let incomeModel: IncomeModel
    let fynModel: FynConversationModel
    let bugReportModel: BugReportModel
    let appleSubscriptionManager: any AppleSubscriptionManaging
    @Environment(AppRouter.self) private var router
    @State private var isPresentingMenu = false
    @State private var isPresentingFyn = false

    var body: some View {
        NavigationStack(path: navigationPath) {
            DashboardView(
                model: dashboardModel,
                onRoute: { route in
                    navigate(to: route)
                },
                onOpenFyn: { prompt in
                    presentFyn(prompt: prompt)
                }
            )
            .navigationDestination(for: AppRoute.self) { route in
                NavigationDestinationFactory.destination(
                    for: route,
                    subscriptionModel: subscriptionModel,
                    achievementsModel: achievementsModel,
                    incomeModel: incomeModel,
                    bugReportModel: bugReportModel,
                    appleManager: appleSubscriptionManager,
                    privacyLockController: privacyLockController,
                    onOpenFyn: { prompt in
                        presentFyn(prompt: prompt)
                    },
                    onRoute: navigate
                )
            }
            .toolbar {
                ToolbarItem(placement: .topBarLeading) {
                    Button("Menu") {
                        isPresentingMenu = true
                    }
                    .font(FynlaTypography.button)
                    .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                    .accessibilityIdentifier("navigation.open")
                }
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Settings") {
                        navigate(to: .settings)
                    }
                    .font(FynlaTypography.button)
                    .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                    .accessibilityIdentifier("app.unlocked.settings")
                }
            }
            .accessibilityIdentifier("app.unlocked")
        }
        .sheet(isPresented: $isPresentingMenu) {
            NavigationMenuView(
                includeStagedDestinations: isDevelopmentBuild,
                onSelect: { route in
                    isPresentingMenu = false
                    navigate(to: route)
                },
                onDismiss: {
                    isPresentingMenu = false
                }
            )
        }
        .sheet(isPresented: $isPresentingFyn) {
            FynView(
                model: fynModel,
                onClose: { isPresentingFyn = false },
                onRoute: { route in
                    isPresentingFyn = false
                    navigate(to: route)
                },
                onRefreshCurrentScreen: {
                    switch router.path.last {
                    case nil, .dashboard:
                        Task { await dashboardModel.refresh() }
                    case .income:
                        Task { await incomeModel.refresh() }
                    default:
                        break
                    }
                }
            )
        }
        .safeAreaInset(edge: .bottom) {
            Button("Ask Fyn") { presentFyn() }
                .font(FynlaTypography.button)
                .foregroundStyle(.white)
                .frame(
                    maxWidth: .infinity,
                    minHeight: FynlaSpacing.minimumInteractiveTarget
                )
                .background(FynlaColor.Token.horizon500.color)
                .padding(.horizontal, FynlaSpacing.standard)
                .padding(.vertical, FynlaSpacing.small)
                .background(FynlaColor.pageBackground)
                .accessibilityIdentifier("fyn.open")
        }
    }

    private var navigationPath: Binding<[AppRoute]> {
        Binding(
            get: { router.path },
            set: { routes in
                _ = router.restore(routes)
            }
        )
    }

    private func navigate(to route: AppRoute) {
        if route == .bugReport {
            bugReportModel.updateContext(
                route: mobilePath(for: router.path.last ?? .dashboard),
                conversationID: fynModel.conversationID
            )
        }
        if route == .dashboard {
            router.reset()
        } else {
            _ = router.navigate(to: route)
        }
    }

    private func presentFyn(prompt: String? = nil) {
        fynModel.currentRoute = mobilePath(for: router.path.last ?? .dashboard)
        if let prompt,
           !prompt.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        {
            fynModel.draft = prompt
        }
        isPresentingFyn = true
    }

    private func mobilePath(for route: AppRoute) -> String {
        switch route {
        case .dashboard: "/dashboard"
        case .income: "/income"
        case .expenditure: "/expenditure"
        case .savings: "/savings"
        case .investment: "/investment"
        case .retirement: "/retirement"
        case .taxStrategy: "/tax-strategy"
        case .achievements: "/achievements"
        case .netWorth: "/net-worth"
        case .protection: "/protection"
        case .estate: "/estate"
        case .goals: "/goals"
        case .holisticPlan: "/holistic-plan"
        case .bugReport: "/report-a-problem"
        case .settings: "/settings"
        case .module: "/dashboard"
        }
    }

    private var isDevelopmentBuild: Bool {
#if DEBUG
        true
#else
        false
#endif
    }
}
