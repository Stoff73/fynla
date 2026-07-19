import SwiftUI
import UIKit

@main
struct FynlaApp: App {
    @UIApplicationDelegateAdaptor(FynlaAppDelegate.self) private var appDelegate
    @Environment(\.scenePhase) private var scenePhase
    @State private var session: AppSession
    @State private var router: AppRouter
    @State private var authenticationCoordinator: AuthenticationCoordinator
    @State private var privacyLockController: PrivacyLockController
    @State private var subscriptionModel: SubscriptionModel
    @State private var dashboardModel: DashboardModel
    @State private var achievementsModel: AchievementsModel
    @State private var incomeModel: IncomeModel
    @State private var expenditureModel: ExpenditureModel
    @State private var netWorthModel: NetWorthModel
    @State private var balanceHistoryModel: BalanceHistoryModel
    @State private var savingsModel: SavingsModel
    @State private var investmentModel: InvestmentModel
    @State private var retirementModel: RetirementModel
    @State private var protectionModel: ProtectionModel
    @State private var estateModel: EstateModel
    @State private var goalsModel: GoalsModel
    @State private var taxStrategyModel: TaxStrategyModel
    @State private var holisticPlanModel: HolisticPlanModel
    @State private var settingsModel: SettingsModel
    @State private var privacySettingsModel: PrivacySettingsModel
    @State private var dataExportModel: DataExportModel
    @State private var accountDeletionModel: AccountDeletionModel
    @State private var pushCoordinator: PushRegistrationCoordinator
    @State private var deepLinkCoordinator: PendingDeepLinkCoordinator
    @State private var fynModel: FynConversationModel
    @State private var bugReportModel: BugReportModel
    private let dependencies: AppDependencies
    private let authenticationClient: APIAuthClient
    private let appleSubscriptionManager: any AppleSubscriptionManaging
    private let legacyCapacitorCleanup: LegacyCapacitorCleanup

    #if FYNLA_UI_TESTING
    private let uiTestMode: UITestMode?
    #endif

    init() {
        #if FYNLA_UI_TESTING
        let uiTestMode = UITestMode(arguments: ProcessInfo.processInfo.arguments)
        self.uiTestMode = uiTestMode
        let dependencies = uiTestMode == nil
            ? Self.makeLiveDependencies()
            : TestAppDependencies.make()
        let initialState = uiTestMode?.initialSessionState ?? .launching
        #else
        let dependencies = Self.makeLiveDependencies()
        let initialState = AppSession.State.launching
        #endif

        let session = AppSession(state: initialState)
        let router = AppRouter(session: session)
        let authClient = dependencies.makeAuthenticationClient()
        self.authenticationClient = authClient
        #if FYNLA_UI_TESTING
        let coordinator: AuthenticationCoordinator
        if let scenario = uiTestMode?.loginScenario {
            let uiTestAuthClient = LoginUITestAuthClient(scenario: scenario)
            coordinator = AuthenticationCoordinator(
                appSession: session,
                authClient: uiTestAuthClient,
                currentUserClient: uiTestAuthClient
            )
        } else {
            coordinator = AuthenticationCoordinator(
                appSession: session,
                authClient: authClient,
                currentUserClient: authClient
            )
        }
        #else
        let coordinator = AuthenticationCoordinator(
            appSession: session,
            authClient: authClient,
            currentUserClient: authClient
        )
        #endif
        #if FYNLA_UI_TESTING
        let faceIDTestClient = uiTestMode?.faceIDScenario.map {
            TestAppDependencies.makeFaceIDClient(scenario: $0)
        }
        let faceIDPreference: any FaceIDPreference = uiTestMode == nil
            ? UserDefaultsFaceIDPreference()
            : faceIDTestClient ?? TestAppDependencies.makeFaceIDPreference()
        let biometricClient: any BiometricClient = faceIDTestClient
            ?? LocalAuthenticationClient()
        let keychainClient: any KeychainClient = faceIDTestClient
            ?? SystemKeychainClient()
        let nativeSessionClient: any NativeSessionClient = faceIDTestClient
            ?? authClient
        let currentUserClient: any CurrentUserClient = faceIDTestClient
            ?? authClient
        #else
        let faceIDPreference: any FaceIDPreference = UserDefaultsFaceIDPreference()
        let biometricClient: any BiometricClient = LocalAuthenticationClient()
        let keychainClient: any KeychainClient = SystemKeychainClient()
        let nativeSessionClient: any NativeSessionClient = authClient
        let currentUserClient: any CurrentUserClient = authClient
        #endif
        let privacyLockController = PrivacyLockController(
            appSession: session,
            authenticationCoordinator: coordinator,
            biometricClient: biometricClient,
            keychainClient: keychainClient,
            nativeSessionClient: nativeSessionClient,
            currentUserClient: currentUserClient,
            preference: faceIDPreference,
            clock: ContinuousPrivacyClock(),
            deviceLabel: "Fynla iPhone"
        )
        let authenticatedDependencies = dependencies.authenticatedSession(
            accessTokenProvider: coordinator,
            tokenRefresher: privacyLockController
        )
        let pushCoordinator = PushRegistrationCoordinator(
            system: LiveSystemPushClient(),
            client: LivePushClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            ),
            session: session,
            router: router,
            nativeSessionID: { coordinator.credentials?.sessionID },
            metadata: dependencies.makePushDeviceMetadata(
                osVersion: "iOS \(UIDevice.current.systemVersion)"
            )
        )
        let deepLinkCoordinator = PendingDeepLinkCoordinator(
            parser: DeepLinkParser(environment: dependencies.environment),
            session: session,
            router: router,
            openExternal: { url in
                UIApplication.shared.open(url)
            }
        )
        let incomeModel = IncomeModel(
            client: LiveIncomeClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let expenditureModel = ExpenditureModel(
            client: LiveExpenditureClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let netWorthModel = NetWorthModel(
            client: LiveNetWorthClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let balanceHistoryModel = BalanceHistoryModel(
            client: LiveBalanceHistoryClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let savingsModel = SavingsModel(
            client: LiveSavingsClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let investmentModel = InvestmentModel(
            client: LiveInvestmentClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let retirementModel = RetirementModel(
            client: LiveRetirementClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let protectionModel = ProtectionModel(
            client: LiveProtectionClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let estateModel = EstateModel(
            client: LiveEstateClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let goalsModel = GoalsModel(
            client: LiveGoalsClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let taxStrategyModel = TaxStrategyModel(
            client: LiveTaxStrategyClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        let holisticPlanModel = HolisticPlanModel(
            client: LiveHolisticPlanClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        #if FYNLA_UI_TESTING
        let subscriptionModel: SubscriptionModel
        let appleSubscriptionManager: any AppleSubscriptionManaging
        if let scenario = uiTestMode?.subscriptionScenario {
            subscriptionModel = SubscriptionUITestComposition.model(for: scenario)
            appleSubscriptionManager = SubscriptionUITestAppleManager()
        } else {
            subscriptionModel = SubscriptionModel(
                api: LiveSubscriptionAPI(
                    apiClient: authenticatedDependencies.makeAPIClient()
                ),
                storeKit: SystemStoreKitClient()
            )
            appleSubscriptionManager = SystemAppleSubscriptionManager()
        }
        #else
        let subscriptionModel = SubscriptionModel(
            api: LiveSubscriptionAPI(
                apiClient: authenticatedDependencies.makeAPIClient()
            ),
            storeKit: SystemStoreKitClient()
        )
        let appleSubscriptionManager: any AppleSubscriptionManaging =
            SystemAppleSubscriptionManager()
        #endif
        #if FYNLA_UI_TESTING
        let settingsUserProvider: @MainActor () -> AuthenticatedUser? = {
            if uiTestMode == nil { return coordinator.authenticatedUser }
            return AuthenticatedUser(
                id: 101,
                firstName: "Example",
                surname: "User",
                name: "Example User",
                email: "example@example.test"
            )
        }
        #else
        let settingsUserProvider: @MainActor () -> AuthenticatedUser? = {
            coordinator.authenticatedUser
        }
        #endif
        let settingsModel = SettingsModel(
            userProvider: settingsUserProvider,
            privacyLockController: privacyLockController,
            webBaseURL: dependencies.environment.webBaseURL,
            beforeSignOut: { await pushCoordinator.unregister() }
        )
        let privacyClient = LivePrivacyClient(
            apiClient: authenticatedDependencies.makeAPIClient()
        )
        let privacySettingsModel = PrivacySettingsModel(client: privacyClient)
        let exportStore = TemporaryExportStore()
        let dataExportModel = DataExportModel(
            client: privacyClient,
            store: exportStore
        )
        let accountDeletionSubscriptionAPI = LiveSubscriptionAPI(
            apiClient: authenticatedDependencies.makeAPIClient()
        )
        let accountDeletionModel = AccountDeletionModel(
            client: LiveAccountDeletionClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            ),
            loadBillingState: {
                do {
                    let entitlement = try await accountDeletionSubscriptionAPI.entitlement()
                    return switch (entitlement.tier, entitlement.billingManagement) {
                    case (.premium, .apple): .applePremium
                    case (.premium, .web): .webPremium
                    case (.free, _): .free
                    case (.premium, .none): .unavailable
                    }
                } catch {
                    return .unavailable
                }
            },
            cleanup: {
                await pushCoordinator.unregister()
                await dataExportModel.stop()
                try? await exportStore.deleteAll()
                await privacyLockController.signOut()
            }
        )
        #if FYNLA_UI_TESTING
        let dashboardModel = uiTestMode == nil
            ? DashboardModel(
                client: LiveDashboardClient(
                    apiClient: authenticatedDependencies.makeAPIClient()
                )
            )
            : DashboardUITestComposition.model()
        #else
        let dashboardModel = DashboardModel(
            client: LiveDashboardClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        #endif
        #if FYNLA_UI_TESTING
        let achievementsModel = uiTestMode == nil
            ? AchievementsModel(
                client: LiveAchievementsClient(
                    apiClient: authenticatedDependencies.makeAPIClient()
                )
            )
            : AchievementsUITestComposition.model()
        #else
        let achievementsModel = AchievementsModel(
            client: LiveAchievementsClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            )
        )
        #endif
        #if FYNLA_UI_TESTING
        let fynModel = uiTestMode == nil
            ? FynConversationModel(client: authenticatedDependencies.makeFynClient())
            : FynUITestComposition.model()
        #else
        let fynModel = FynConversationModel(
            client: authenticatedDependencies.makeFynClient()
        )
        #endif
        #if FYNLA_UI_TESTING
        let bugReportModel = uiTestMode == nil
            ? BugReportModel(
                client: LiveBugReportClient(
                    apiClient: authenticatedDependencies.makeAPIClient()
                ),
                metadata: authenticatedDependencies.makeBugReportMetadata(
                    route: "/dashboard",
                    nativeSessionUUID: UUID().uuidString
                )
            )
            : BugReportUITestComposition.model(
                metadata: authenticatedDependencies.makeBugReportMetadata(
                    route: "/dashboard",
                    nativeSessionUUID: "9E7D314A-E607-4B93-B739-6864363CF913"
                )
            )
        #else
        let bugReportModel = BugReportModel(
            client: LiveBugReportClient(
                apiClient: authenticatedDependencies.makeAPIClient()
            ),
            metadata: authenticatedDependencies.makeBugReportMetadata(
                route: "/dashboard",
                nativeSessionUUID: UUID().uuidString
            )
        )
        #endif
        self.dependencies = authenticatedDependencies
        self.appleSubscriptionManager = appleSubscriptionManager
        self.legacyCapacitorCleanup = .live()
        _session = State(initialValue: session)
        _router = State(initialValue: router)
        _authenticationCoordinator = State(initialValue: coordinator)
        _privacyLockController = State(initialValue: privacyLockController)
        _subscriptionModel = State(initialValue: subscriptionModel)
        _dashboardModel = State(initialValue: dashboardModel)
        _achievementsModel = State(initialValue: achievementsModel)
        _incomeModel = State(initialValue: incomeModel)
        _expenditureModel = State(initialValue: expenditureModel)
        _netWorthModel = State(initialValue: netWorthModel)
        _balanceHistoryModel = State(initialValue: balanceHistoryModel)
        _savingsModel = State(initialValue: savingsModel)
        _investmentModel = State(initialValue: investmentModel)
        _retirementModel = State(initialValue: retirementModel)
        _protectionModel = State(initialValue: protectionModel)
        _estateModel = State(initialValue: estateModel)
        _goalsModel = State(initialValue: goalsModel)
        _taxStrategyModel = State(initialValue: taxStrategyModel)
        _holisticPlanModel = State(initialValue: holisticPlanModel)
        _settingsModel = State(initialValue: settingsModel)
        _privacySettingsModel = State(initialValue: privacySettingsModel)
        _dataExportModel = State(initialValue: dataExportModel)
        _accountDeletionModel = State(initialValue: accountDeletionModel)
        _pushCoordinator = State(initialValue: pushCoordinator)
        _deepLinkCoordinator = State(initialValue: deepLinkCoordinator)
        _fynModel = State(initialValue: fynModel)
        _bugReportModel = State(initialValue: bugReportModel)
    }

    var body: some Scene {
        WindowGroup {
            rootView
                .environment(\.appDependencies, dependencies)
        }
    }

    @ViewBuilder
    private var rootView: some View {
        #if FYNLA_UI_TESTING
        if uiTestMode?.showsDesignSystem == true {
            DesignSystemTestView()
        } else {
            appRootView
        }
        #else
        appRootView
        #endif
    }

    private var appRootView: some View {
        AppRootView(
            session: session,
            privacyLockController: privacyLockController,
            legacyCapacitorCleanup: legacyCapacitorCleanup,
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
            deepLinkCoordinator: deepLinkCoordinator,
            fynModel: fynModel,
            bugReportModel: bugReportModel,
            appleSubscriptionManager: appleSubscriptionManager,
            registrationActions: registrationActions,
            loginActions: loginActions,
            passwordResetActions: passwordResetActions,
            webBaseURL: dependencies.environment.webBaseURL,
            initiallyPresentsRegistration: initiallyPresentsRegistration,
            initiallyPresentsPasswordReset: initiallyPresentsPasswordReset
        )
            .environment(session)
            .environment(router)
            .task {
                PushAppDelegateBridge.shared.configure(pushCoordinator)
            }
            .onOpenURL { url in
                deepLinkCoordinator.receive(url)
            }
            .onContinueUserActivity(NSUserActivityTypeBrowsingWeb) { activity in
                guard let url = activity.webpageURL else { return }
                deepLinkCoordinator.receive(url)
            }
            .onChange(of: scenePhase) { _, phase in
                switch phase {
                case .background:
                    privacyLockController.didEnterBackground()
                case .active:
                    privacyLockController.didBecomeActive()
                case .inactive:
                    privacyLockController.willBecomeInactive()
                @unknown default:
                    break
                }
            }
    }

    @MainActor
    private var registrationActions: RegistrationActions {
        #if FYNLA_UI_TESTING
        if let scenario = uiTestMode?.registrationScenario {
            return scenario.actions(session: session)
        }
        #endif
        return .coordinator(
            authenticationCoordinator,
            deviceLabel: "Fynla iPhone"
        )
    }

    @MainActor
    private var loginActions: LoginActions {
        return .coordinator(
            authenticationCoordinator,
            deviceLabel: "Fynla iPhone"
        )
    }

    @MainActor
    private var passwordResetActions: PasswordResetActions {
        #if FYNLA_UI_TESTING
        if let scenario = uiTestMode?.passwordResetScenario {
            return scenario.actions(session: session)
        }
        #endif
        return .client(authenticationClient) {
            authenticationCoordinator.signOut()
        }
    }

    private var initiallyPresentsRegistration: Bool {
        #if FYNLA_UI_TESTING
        uiTestMode?.registrationScenario != nil
        #else
        false
        #endif
    }

    private var initiallyPresentsPasswordReset: Bool {
        #if FYNLA_UI_TESTING
        uiTestMode?.passwordResetScenario != nil
        #else
        false
        #endif
    }

    private static func makeLiveDependencies() -> AppDependencies {
        do {
            return try AppDependencies.live()
        } catch {
            fatalError("Invalid application configuration: \(error)")
        }
    }
}
