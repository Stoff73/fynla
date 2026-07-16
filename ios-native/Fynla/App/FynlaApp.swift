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
            AppRootView(session: session)
                .environment(session)
                .environment(router)
        }
    }
}
