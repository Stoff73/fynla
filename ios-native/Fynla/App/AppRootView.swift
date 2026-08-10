import SwiftUI

struct AppRootView: View {
    let session: AppSession
    let privacyLockController: PrivacyLockController?
    let legacyCapacitorCleanup: LegacyCapacitorCleanup
    let nativeVersionPolicyModel: NativeVersionPolicyModel
    let subscriptionModel: SubscriptionModel
    let dashboardModel: DashboardModel
    let achievementsModel: AchievementsModel
    let conversationHistoryModel: ConversationHistoryModel
    let personalInformationModel: PersonalInformationModel
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
    let webHandoffClient: any WebHandoffClient
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
        conversationHistoryModel: ConversationHistoryModel,
        personalInformationModel: PersonalInformationModel,
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
        webHandoffClient: any WebHandoffClient,
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
        self.conversationHistoryModel = conversationHistoryModel
        self.personalInformationModel = personalInformationModel
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
        self.webHandoffClient = webHandoffClient
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
                        conversationHistoryModel: conversationHistoryModel,
                        personalInformationModel: personalInformationModel,
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
                        webHandoffClient: webHandoffClient
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
            // Skip the legacy Capacitor cleanup under UI-test automation:
            // fresh test containers carry no legacy website data, and the
            // WKWebsiteDataStore touch launches WebKit's network process,
            // whose fault logging trips an XCTAutomationSupport NULL-strcmp
            // crash that kills the app under test at boot (six identical
            // simulator crash artifacts + the two registration UI-test
            // timeouts, 2026-07-22).
            #if !FYNLA_UI_TESTING
            _ = try? await legacyCapacitorCleanup.runIfNeeded()
            #endif
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
                personalInformationModel.stop()
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

private enum FynLaunchRequest {
    case generic
    case contextual(FynContextualAction)
    case conversation(String)
}

private struct UnlockedView: View {
    let privacyLockController: PrivacyLockController?
    let subscriptionModel: SubscriptionModel
    let dashboardModel: DashboardModel
    let achievementsModel: AchievementsModel
    let conversationHistoryModel: ConversationHistoryModel
    let personalInformationModel: PersonalInformationModel
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
    let webHandoffClient: any WebHandoffClient
    @Environment(AppRouter.self) private var router
    @State private var isPresentingMenu = false
    @State private var isPresentingFyn = false
    @State private var fynLaunchRequest: FynLaunchRequest = .generic
    // Route requested from inside the Fyn cover — applied only once the cover
    // has fully dismissed, so the push never races the dismissal transaction.
    @State private var pendingFynRoute: AppRoute?
    // /m nudge dismissals are session-scoped (Dashboard.vue data flags).
    @State private var nudgeDismissed = false
    @State private var unlockBubbleDismissed = false
    @State private var browserItem: SafariSheetItem?
    @State private var isOpeningAdmin = false
    @State private var adminError: String?

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
        .sheet(item: $browserItem) { item in
            SafariSheet(url: item.url)
                .ignoresSafeArea()
        }
        .safeAreaInset(edge: .bottom, spacing: 0) {
            fynDock
        }
        .overlay(alignment: .bottom) {
            // md-fyn-nudge — the onboarding "finish your tax plan" pill, else
            // the KYC unlock pill, floating just above the docked Fyn bar.
            fynNudges
        }
        .overlay {
            drawerOverlay
        }
        .overlay {
            // Level-up fireworks takeover (missed celebrations delivered by
            // the status fetch, as /m's dashboard fetchStatus does).
            if let celebration = achievementsModel.pendingCelebration {
                GamificationCelebrationView(
                    level: celebration.level,
                    levelName: celebration.levelName,
                    nextActions: celebration.nextActions ?? [],
                    onDismiss: {
                        Task { await achievementsModel.dismissCelebration() }
                    }
                )
            }
        }
        .task { await achievementsModel.refreshCelebration() }
    }

    // /m Dashboard.vue nudges: onboardingActive shows the horizon-500
    // "Finish your personalised tax plan with Fyn" pill ("Later" dismisses
    // for the session); otherwise a KYC-gated top unlock action offers
    // "<meta> — Fyn can help" ("Not now"), opening Fyn pre-seeded with the
    // module's capture prompt.
    @ViewBuilder
    private var fynNudges: some View {
        if settingsModel.onboardingActive, !nudgeDismissed {
            nudgePill(
                text: "Finish your personalised tax plan with Fyn",
                dismissLabel: "Later",
                identifier: "dashboard.fyn-nudge",
                onOpen: { presentFyn() },
                onDismiss: { nudgeDismissed = true }
            )
        } else if !settingsModel.onboardingActive,
                  !unlockBubbleDismissed,
                  let unlock = topUnlockAction
        {
            nudgePill(
                text: "\(unlock.meta) — Fyn can help",
                dismissLabel: "Not now",
                identifier: "dashboard.unlock-nudge",
                onOpen: { presentFyn(prompt: capturePrompt(for: unlock.module)) },
                onDismiss: { unlockBubbleDismissed = true }
            )
        }
    }

    private func nudgePill(
        text: String,
        dismissLabel: String,
        identifier: String,
        onOpen: @escaping () -> Void,
        onDismiss: @escaping () -> Void
    ) -> some View {
        HStack(alignment: .center, spacing: 8) {
            Button(action: onOpen) {
                Text(text)
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(.white)
                    .multilineTextAlignment(.leading)
                    .frame(maxWidth: .infinity, alignment: .leading)
            }
            .accessibilityIdentifier(identifier)
            Button(action: onDismiss) {
                Text(dismissLabel)
                    .font(.system(size: 12))
                    .foregroundStyle(.white.opacity(0.7))
                    .padding(4)
            }
            .accessibilityIdentifier("\(identifier).dismiss")
        }
        .padding(.vertical, 10)
        .padding(.horizontal, 12)
        .background(FynlaColor.Token.horizon500.color)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .shadow(color: FynlaColor.Token.horizon500.color.opacity(0.25), radius: 10, y: 6)
        .padding(.horizontal, 12)
        .padding(.bottom, 8)
    }

    // /m topUnlock: the first locked area's unlock action, else the first
    // area's first unlock action.
    private var topUnlockAction: DashboardAction? {
        guard let areas = dashboardModel.snapshot?.focusAreas else { return nil }
        for area in areas where area.locked {
            if let first = area.actions.first, first.type == .unlock {
                return first
            }
        }
        return areas.first?.actions.first(where: { $0.type == .unlock })
    }

    // /m Dashboard.vue openFynForCapture prompts.
    private func capturePrompt(for module: String) -> String {
        switch module {
        case "protection": "Help me add my protection cover details"
        case "savings": "Help me add my savings details"
        case "investment": "Help me add my investment details"
        case "retirement": "Help me add my pension details"
        case "estate": "Help me add my estate planning details"
        case "goals": "Help me set a financial goal"
        case "tax": "Help me complete my tax strategy details"
        default: "Help me add my financial details"
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
                    conversationHistoryModel: conversationHistoryModel,
                    personalInformationModel: personalInformationModel,
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
                    onOpenContextualFyn: presentContextualFyn,
                    onOpenConversation: presentConversation,
                    onOpenRoute: openTopLevel,
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
            onStart: {
                switch fynLaunchRequest {
                case .generic:
                    await fynModel.open()
                case let .contextual(action):
                    await fynModel.startContextual(action)
                case let .conversation(id):
                    await fynModel.start(preferredID: id)
                }
            },
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
                case .personalInformation:
                    Task { await personalInformationModel.refresh() }
                case .subscription:
                    break
                case .conversationHistory:
                    Task { await conversationHistoryModel.load() }
                default:
                    break
                }
            },
            onReportProblem: {
                pendingFynRoute = .bugReport
                isPresentingFyn = false
            },
            onAckLevelUp: {
                Task { await achievementsModel.dismissCelebration() }
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
                        shareClient: shareClient,
                        isOpeningAdmin: isOpeningAdmin,
                        adminError: adminError,
                        onSelect: { route in
                            closeMenu()
                            navigate(to: route)
                        },
                        onOpenAdmin: openAdmin,
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

    private func openAdmin() {
        guard !isOpeningAdmin else { return }
        isOpeningAdmin = true
        adminError = nil
        Task { @MainActor in
            defer { isOpeningAdmin = false }
            do {
                let url = try await webHandoffClient.issue(.admin)
                closeMenu()
                browserItem = SafariSheetItem(url: url)
            } catch {
                adminError = "The Admin Panel could not be opened securely."
            }
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
                route: (router.path.last ?? .dashboard).mobilePath,
                conversationID: fynModel.conversationID
            )
        }
        if route == .dashboard {
            router.reset()
        } else {
            _ = router.navigate(to: route)
        }
    }

    private func openTopLevel(_ route: AppRoute) {
        _ = router.open(route)
    }

    private func presentFyn(prompt: String? = nil) {
        fynModel.currentRoute = (router.path.last ?? .dashboard).mobilePath
        fynLaunchRequest = .generic
        if let prompt,
           !prompt.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        {
            fynModel.draft = prompt
        }
        isPresentingFyn = true
    }

    private func presentContextualFyn(_ action: FynContextualAction) {
        fynModel.currentRoute = (router.path.last ?? .dashboard).mobilePath
        fynModel.draft = ""
        fynLaunchRequest = .contextual(action)
        isPresentingFyn = true
    }

    private func presentConversation(_ id: String) {
        fynModel.currentRoute = AppRoute.conversationHistory.mobilePath
        fynModel.draft = ""
        fynLaunchRequest = .conversation(id)
        isPresentingFyn = true
    }
}
