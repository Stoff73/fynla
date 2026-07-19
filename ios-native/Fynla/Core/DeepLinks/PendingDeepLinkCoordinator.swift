import Foundation
import Observation

@MainActor
@Observable
final class PendingDeepLinkCoordinator {
    private(set) var pendingRoute: AppRoute?

    private let parser: DeepLinkParser
    private let session: AppSession
    private let router: AppRouter
    private let openExternal: @MainActor (URL) -> Void

    init(
        parser: DeepLinkParser,
        session: AppSession,
        router: AppRouter,
        openExternal: @escaping @MainActor (URL) -> Void
    ) {
        self.parser = parser
        self.session = session
        self.router = router
        self.openExternal = openExternal
    }

    func receive(_ url: URL) {
        switch parser.destination(for: url) {
        case let .native(route):
            pendingRoute = route
            deliverPendingRouteIfPossible()
        case let .external(url):
            pendingRoute = nil
            openExternal(url)
        case .ignored:
            break
        }
    }

    func sessionDidChange() {
        deliverPendingRouteIfPossible()
    }

    private func deliverPendingRouteIfPossible() {
        guard session.state == .authenticatedUnlocked,
              let pendingRoute,
              router.open(pendingRoute)
        else {
            return
        }
        self.pendingRoute = nil
    }
}
