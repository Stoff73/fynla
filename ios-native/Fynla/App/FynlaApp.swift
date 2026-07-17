import SwiftUI

@main
struct FynlaApp: App {
    @State private var session: AppSession
    @State private var router: AppRouter
    @State private var authenticationCoordinator: AuthenticationCoordinator
    private let dependencies: AppDependencies
    private let authenticationClient: APIAuthClient

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

        self.dependencies = dependencies
        let session = AppSession(state: initialState)
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
        _session = State(initialValue: session)
        _router = State(initialValue: AppRouter(session: session))
        _authenticationCoordinator = State(initialValue: coordinator)
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
            registrationActions: registrationActions,
            loginActions: loginActions,
            passwordResetActions: passwordResetActions,
            webBaseURL: dependencies.environment.webBaseURL,
            initiallyPresentsRegistration: initiallyPresentsRegistration,
            initiallyPresentsPasswordReset: initiallyPresentsPasswordReset
        )
            .environment(session)
            .environment(router)
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
