import SwiftUI

@main
struct FynlaApp: App {
    @State private var session: AppSession
    @State private var router: AppRouter
    private let dependencies: AppDependencies

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
        _session = State(initialValue: session)
        _router = State(initialValue: AppRouter(session: session))
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
        AppRootView(session: session)
            .environment(session)
            .environment(router)
    }

    private static func makeLiveDependencies() -> AppDependencies {
        do {
            return try AppDependencies.live()
        } catch {
            fatalError("Invalid application configuration: \(error)")
        }
    }
}
