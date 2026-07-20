import SwiftUI

struct AppRootView: View {
    let session: AppSession
    let privacyLockController: PrivacyLockController?
    let legacyCapacitorCleanup: LegacyCapacitorCleanup
    let nativeVersionPolicyModel: NativeVersionPolicyModel
    let subscriptionModel: SubscriptionModel
    let dashboardModel: DashboardModel
    let achievementsModel: AchievementsModel
    let incomeModel: IncomeModel
    let expenditureModel: ExpenditureModel
    let netWorthModel: NetWorthModel
    let balanceHistoryModel: BalanceHistoryModel
    let savingsModel: SavingsModel
    let investmentModel: InvestmentModel
    let retirementModel: RetirementModel
    let protectionModel: ProtectionModel
    let estateModel: EstateModel
    let goalsModel: GoalsModel
    let taxStrategyModel: TaxStrategyModel
    let holisticPlanModel: HolisticPlanModel
    let settingsModel: SettingsModel
    let privacySettingsModel: PrivacySettingsModel
    let dataExportModel: DataExportModel
    let accountDeletionModel: AccountDeletionModel
    let pushCoordinator: PushRegistrationCoordinator
    let deepLinkCoordinator: PendingDeepLinkCoordinator
    let fynModel: FynConversationModel
    let bugReportModel: BugReportModel
    let appleSubscriptionManager: any AppleSubscriptionManaging
    let shareClient: any ShareContentClient
    private let webBaseURL: URL
    @State private var registrationModel: RegistrationModel
    @State private var loginModel: LoginModel
    @State private var passwordResetModel: PasswordResetModel
    @State private var isPresentingPasswordReset: Bool

    init(
        session: AppSession,
        privacyLockController: PrivacyLockController? = nil,
        legacyCapacitorCleanup: LegacyCapacitorCleanup,
        nativeVersionPolicyModel: NativeVersionPolicyModel,
        subscriptionModel: SubscriptionModel,
        dashboardModel: DashboardModel,
        achievementsModel: AchievementsModel,
        incomeModel: IncomeModel,
        expenditureModel: ExpenditureModel,
        netWorthModel: NetWorthModel,
        balanceHistoryModel: BalanceHistoryModel,
        savingsModel: SavingsModel,
        investmentModel: InvestmentModel,
        retirementModel: RetirementModel,
        protectionModel: ProtectionModel,
        estateModel: EstateModel,
        goalsModel: GoalsModel,
        taxStrategyModel: TaxStrategyModel,
        holisticPlanModel: HolisticPlanModel,
        settingsModel: SettingsModel,
        privacySettingsModel: PrivacySettingsModel,
        dataExportModel: DataExportModel,
        accountDeletionModel: AccountDeletionModel,
        pushCoordinator: PushRegistrationCoordinator,
        deepLinkCoordinator: PendingDeepLinkCoordinator,
        fynModel: FynConversationModel,
        bugReportModel: BugReportModel,
        appleSubscriptionManager: any AppleSubscriptionManaging,
        shareClient: any ShareContentClient,
        registrationActions: RegistrationActions,
        loginActions: LoginActions,
        passwordResetActions: PasswordResetActions,
        webBaseURL: URL,
        initiallyPresentsRegistration: Bool = false,
        initiallyPresentsPasswordReset: Bool = false
    ) {
        self.session = session
        self.privacyLockController = privacyLockController
        self.legacyCapacitorCleanup = legacyCapacitorCleanup
        self.nativeVersionPolicyModel = nativeVersionPolicyModel
        self.subscriptionModel = subscriptionModel
        self.dashboardModel = dashboardModel
        self.achievementsModel = achievementsModel
        self.incomeModel = incomeModel
        self.expenditureModel = expenditureModel
        self.netWorthModel = netWorthModel
        self.balanceHistoryModel = balanceHistoryModel
        self.savingsModel = savingsModel
        self.investmentModel = investmentModel
        self.retirementModel = retirementModel
        self.protectionModel = protectionModel
        self.estateModel = estateModel
        self.goalsModel = goalsModel
        self.taxStrategyModel = taxStrategyModel
        self.holisticPlanModel = holisticPlanModel
        self.settingsModel = settingsModel
        self.privacySettingsModel = privacySettingsModel
        self.dataExportModel = dataExportModel
        self.accountDeletionModel = accountDeletionModel
        self.pushCoordinator = pushCoordinator
        self.deepLinkCoordinator = deepLinkCoordinator
        self.fynModel = fynModel
        self.bugReportModel = bugReportModel
        self.appleSubscriptionManager = appleSubscriptionManager
        self.shareClient = shareClient
        self.webBaseURL = webBaseURL
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
                switch nativeVersionPolicyModel.state {
                case .checking:
                    LoadingView(message: "Checking this version of Fynla…")
                case .supported:
                    UnlockedView(
                        privacyLockController: privacyLockController,
                        subscriptionModel: subscriptionModel,
                        dashboardModel: dashboardModel,
                        achievementsModel: achievementsModel,
                        incomeModel: incomeModel,
                        expenditureModel: expenditureModel,
                        netWorthModel: netWorthModel,
                        balanceHistoryModel: balanceHistoryModel,
                        savingsModel: savingsModel,
                        investmentModel: investmentModel,
                        retirementModel: retirementModel,
                        protectionModel: protectionModel,
                        estateModel: estateModel,
                        goalsModel: goalsModel,
                        taxStrategyModel: taxStrategyModel,
                        holisticPlanModel: holisticPlanModel,
                        settingsModel: settingsModel,
                        privacySettingsModel: privacySettingsModel,
                        dataExportModel: dataExportModel,
                        accountDeletionModel: accountDeletionModel,
                        pushCoordinator: pushCoordinator,
                        fynModel: fynModel,
                        bugReportModel: bugReportModel,
                        appleSubscriptionManager: appleSubscriptionManager,
                        shareClient: shareClient,
                        webBaseURL: webBaseURL
                    )
                case let .updateRequired(requirement, appStoreURL):
                    NativeUpdateRequiredView(
                        requirement: requirement,
                        appStoreURL: appStoreURL
                    )
                case .unavailable:
                    ErrorView(
                        message: "Fynla couldn't verify this app version. Check your connection and try again."
                    ) {
                        Task { await nativeVersionPolicyModel.validate() }
                    }
                }
            case .deletingAccount:
                LockedView(message: "Updating your account securely…")
            }
        }
        .preferredColorScheme(.light)
        .task {
            _ = try? await legacyCapacitorCleanup.runIfNeeded()
            if let privacyLockController {
                await privacyLockController.completeLaunch()
                await privacyLockController.refreshFaceIDOffer()
            } else {
                session.completeLaunch(hasAuthenticatedSession: false)
            }
        }
        .onChange(of: session.state) { _, _ in
            pushCoordinator.sessionDidChange()
            deepLinkCoordinator.sessionDidChange()
            guard let privacyLockController else { return }
            Task { @MainActor in
                await privacyLockController.refreshFaceIDOffer()
            }
        }
        .task(id: session.state) {
            if session.state == .authenticatedUnlocked {
                guard await nativeVersionPolicyModel.validate() else { return }
                await pushCoordinator.start()
                await subscriptionModel.start()
            } else if session.state == .signedOut {
                nativeVersionPolicyModel.reset()
                subscriptionModel.stop()
                dashboardModel.stop()
                achievementsModel.stop()
                incomeModel.stop()
                expenditureModel.stop()
                netWorthModel.stop()
                balanceHistoryModel.stop()
                savingsModel.stop()
                investmentModel.stop()
                retirementModel.stop()
                protectionModel.stop()
                estateModel.stop()
                goalsModel.stop()
                taxStrategyModel.stop()
                holisticPlanModel.stop()
                privacySettingsModel.stop()
                await dataExportModel.stop()
                accountDeletionModel.reset()
                fynModel.stopAndClear()
                bugReportModel.reset()
            }
        }
        .overlay {
            if let privacyLockController,
               privacyLockController.shouldOfferFaceID,
               session.state == .authenticatedUnlocked,
               nativeVersionPolicyModel.state == .supported,
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
                    .accessibilityElement(children: .contain)
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
    let expenditureModel: ExpenditureModel
    let netWorthModel: NetWorthModel
    let balanceHistoryModel: BalanceHistoryModel
    let savingsModel: SavingsModel
    let investmentModel: InvestmentModel
    let retirementModel: RetirementModel
    let protectionModel: ProtectionModel
    let estateModel: EstateModel
    let goalsModel: GoalsModel
    let taxStrategyModel: TaxStrategyModel
    let holisticPlanModel: HolisticPlanModel
    let settingsModel: SettingsModel
    let privacySettingsModel: PrivacySettingsModel
    let dataExportModel: DataExportModel
    let accountDeletionModel: AccountDeletionModel
    let pushCoordinator: PushRegistrationCoordinator
    let fynModel: FynConversationModel
    let bugReportModel: BugReportModel
    let appleSubscriptionManager: any AppleSubscriptionManaging
    let shareClient: any ShareContentClient
    let webBaseURL: URL
    @Environment(AppRouter.self) private var router
    @State private var isPresentingMenu = false
    @State private var isPresentingFyn = false
    // Route requested from inside the Fyn cover — applied only once the cover
    // has fully dismissed, so the push never races the dismissal transaction.
    @State private var pendingFynRoute: AppRoute?

    var body: some View {
        VStack(spacing: 0) {
            shellHeader
            navigationContent
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("app.unlocked")
        .fullScreenCover(isPresented: $isPresentingFyn) {
            fynCover
        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            fynDock
        }
        .overlay {
            drawerOverlay
        }
    }

    // Fixed gradient app bar (md-header): shared by every screen — the
    // hamburger and greeting travel with the shell, exactly as /m's
    // MobileChrome renders them on every page. The background paints the top
    // slice of the shared gradient, extended up under the status bar and
    // clipped exactly at the header's bottom edge.
    private var shellHeader: some View {
        HStack(spacing: 12) {
            Button {
                withAnimation(.easeInOut(duration: 0.28)) {
                    isPresentingMenu = true
                }
            } label: {
                Image(systemName: "line.3.horizontal")
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundStyle(.white)
                    .frame(width: 44, height: 44)
                    .background(Color.white.opacity(0.18))
                    .clipShape(Circle())
            }
            .accessibilityLabel("Open menu")
            .accessibilityIdentifier("navigation.open")

            Text(greeting)
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(.white)
                .lineLimit(1)
                .frame(maxWidth: .infinity, alignment: .leading)
                .accessibilityAddTraits(.isHeader)
                .accessibilityIdentifier("dashboard.greeting")

            Color.clear.frame(width: 44, height: 44)
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 14)
        .background(alignment: .top) {
            GeometryReader { geo in
                let safeTop = geo.frame(in: .global).minY
                Rectangle()
                    .fill(MobileChromeMetrics.sharedGradient)
                    .frame(height: MobileChromeMetrics.gradientSpan)
                    .frame(height: safeTop + geo.size.height, alignment: .top)
                    .clipped()
                    .offset(y: -safeTop)
            }
            .allowsHitTesting(false)
        }
    }

    // Time-of-day greeting, mirroring /m's computed greeting.
    private var greeting: String {
        let hour = Calendar.current.component(.hour, from: Date())
        let part = hour < 12 ? "morning" : (hour < 18 ? "afternoon" : "evening")
        if let name = settingsModel.greetingFirstName, !name.isEmpty {
            return "Good \(part), \(name)"
        }
        return "Good \(part), there"
    }

    private var navigationContent: some View {
        NavigationStack(path: navigationPath) {
            DashboardView(
                model: dashboardModel,
                shareClient: shareClient,
                onRoute: { route in
                    navigate(to: route)
                },
                onOpenFyn: { prompt in
                    presentFyn(prompt: prompt)
                }
            )
            .toolbar(.hidden, for: .navigationBar)
            .navigationDestination(for: AppRoute.self) { route in
                NavigationDestinationFactory.destination(
                    for: route,
                    subscriptionModel: subscriptionModel,
                    achievementsModel: achievementsModel,
                    incomeModel: incomeModel,
                    expenditureModel: expenditureModel,
                    netWorthModel: netWorthModel,
                    balanceHistoryModel: balanceHistoryModel,
                    savingsModel: savingsModel,
                    investmentModel: investmentModel,
                    retirementModel: retirementModel,
                    protectionModel: protectionModel,
                    estateModel: estateModel,
                    goalsModel: goalsModel,
                    taxStrategyModel: taxStrategyModel,
                    holisticPlanModel: holisticPlanModel,
                    settingsModel: settingsModel,
                    privacySettingsModel: privacySettingsModel,
                    dataExportModel: dataExportModel,
                    accountDeletionModel: accountDeletionModel,
                    pushCoordinator: pushCoordinator,
                    bugReportModel: bugReportModel,
                    appleManager: appleSubscriptionManager,
                    onOpenFyn: { prompt in
                        presentFyn(prompt: prompt)
                    },
                    onRoute: navigate
                )
                // /m has no system navigation bar anywhere — the shell header
                // (hamburger + greeting) and per-page hero replace it.
                .toolbar(.hidden, for: .navigationBar)
            }
        }
    }

    private var fynCover: some View {
        FynView(
            model: fynModel,
            onClose: { isPresentingFyn = false },
            onRoute: { route in
                pendingFynRoute = route
                isPresentingFyn = false
            },
            onRefreshCurrentScreen: {
                switch router.path.last {
                case nil, .dashboard:
                    Task { await dashboardModel.refresh() }
                case .income:
                    Task { await incomeModel.refresh() }
                case .expenditure:
                    Task { await expenditureModel.refresh() }
                case .netWorth:
                    Task { await netWorthModel.refresh() }
                case .balanceHistory:
                    Task { await balanceHistoryModel.refresh() }
                case .savings:
                    Task { await savingsModel.refresh() }
                case .investment:
                    Task { await investmentModel.refresh() }
                case .retirement:
                    Task { await retirementModel.refresh() }
                case .protection:
                    Task { await protectionModel.refresh() }
                case .estate:
                    Task { await estateModel.refresh() }
                case .goals:
                    Task { await goalsModel.refresh() }
                case .taxStrategy:
                    Task { await taxStrategyModel.refresh() }
                case .holisticPlan:
                    Task { await holisticPlanModel.refresh() }
                default:
                    break
                }
            },
            onReportProblem: {
                pendingFynRoute = .bugReport
                isPresentingFyn = false
            }
        )
        .onDisappear {
            if let route = pendingFynRoute {
                pendingFynRoute = nil
                navigate(to: route)
            }
        }
    }

    // Docked Fyn bar transcribing /m's md-fyn-dock: full-width white bar flush
    // to the bottom edge, Fyn avatar, name + status, circled up-chevron.
    private var fynDock: some View {
        Button {
            presentFyn()
        } label: {
            HStack(spacing: 10) {
                Image("FynAvatar")
                    .resizable()
                    .scaledToFill()
                    .frame(width: 36, height: 36)
                    .clipShape(Circle())
                    .accessibilityHidden(true)

                VStack(alignment: .leading, spacing: 2) {
                    Text("Fyn")
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon600.color)
                    Text("Your financial companion")
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.horizon400.color)
                }
                .frame(maxWidth: .infinity, alignment: .leading)

                Image(systemName: "chevron.up")
                    .font(.system(size: 16, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon600.color)
                    .frame(width: 36, height: 36)
                    .background(FynlaColor.Token.horizon100.color)
                    .clipShape(Circle())
                    .accessibilityHidden(true)
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
            .background(Color.white)
            .overlay(alignment: .top) {
                FynlaColor.Token.lightGray.color.frame(height: 1)
            }
            .shadow(color: .black.opacity(0.08), radius: 8, y: -4)
        }
        .buttonStyle(.plain)
        .accessibilityLabel("Chat with Fyn")
        .accessibilityIdentifier("fyn.open")
    }

    // Left slide-in drawer + dimmed backdrop transcribing /m's md-drawer
    // (86% width capped at 320, white panel, backdrop tap closes).
    @ViewBuilder
    private var drawerOverlay: some View {
        ZStack(alignment: .leading) {
            if isPresentingMenu {
                Color(red: 15 / 255, green: 23 / 255, blue: 42 / 255)
                    .opacity(0.55)
                    .ignoresSafeArea()
                    .onTapGesture { closeMenu() }
                    .transition(.opacity)
                    .accessibilityIdentifier("navigation.backdrop")

                GeometryReader { geo in
                    NavigationMenuView(
                        account: settingsModel.account,
                        activeRoute: router.path.last ?? .dashboard,
                        adminURL: webBaseURL.appendingPathComponent("admin"),
                        shareClient: shareClient,
                        onSelect: { route in
                            closeMenu()
                            navigate(to: route)
                        },
                        onLock: {
                            closeMenu()
                            settingsModel.lock()
                        },
                        onSignOut: {
                            closeMenu()
                            Task { await settingsModel.signOut() }
                        },
                        onDismiss: { closeMenu() }
                    )
                    .frame(width: min(320, geo.size.width * 0.86))
                    .frame(maxHeight: .infinity)
                    .shadow(color: .black.opacity(0.2), radius: 14, x: 8)
                }
                .transition(.move(edge: .leading))
            }
        }
        .animation(.easeInOut(duration: 0.28), value: isPresentingMenu)
    }

    private func closeMenu() {
        withAnimation(.easeInOut(duration: 0.28)) {
            isPresentingMenu = false
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
        case .balanceHistory: "/net-worth/history"
        case .protection: "/protection"
        case .estate: "/estate"
        case .goals: "/goals"
        case .holisticPlan: "/holistic-plan"
        case .bugReport: "/report-a-problem"
        case .settings: "/settings"
        case .module: "/dashboard"
        }
    }
}
