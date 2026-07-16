import SwiftUI

@main
struct FynlaApp: App {
    @State private var session: AppSession
    @State private var router: AppRouter

    init() {
        let session = AppSession()
        _session = State(initialValue: session)
        _router = State(initialValue: AppRouter(session: session))
    }

    var body: some Scene {
        WindowGroup {
            rootView
        }
    }

    @ViewBuilder
    private var rootView: some View {
        #if DEBUG
        if ProcessInfo.processInfo.arguments.contains("-fynla-design-system-ui-test") {
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
}
