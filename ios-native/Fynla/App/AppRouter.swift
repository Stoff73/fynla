import Observation

enum AppRoute: Hashable, Sendable {
    case dashboard
    case achievements
    case module(String)
    case income
    case expenditure
    case netWorth(category: String?)
    case protection(policyType: String?, id: Int?)
    case savings(accountID: Int?)
    case investment(accountID: Int?)
    case retirement(pensionType: String?, id: Int?)
    case estate
    case goals
    case taxStrategy
    case holisticPlan
    case settings

    var requiresUnlockedSession: Bool {
        self != .settings
    }
}

@MainActor
@Observable
final class AppRouter {
    private let session: AppSession
    private(set) var path: [AppRoute] = []

    init(session: AppSession) {
        self.session = session
    }

    @discardableResult
    func navigate(to route: AppRoute) -> Bool {
        guard canNavigate(to: route) else { return false }
        path.append(route)
        return true
    }

    @discardableResult
    func removeLast() -> Bool {
        guard !path.isEmpty else { return false }
        path.removeLast()
        return true
    }

    func reset() {
        path.removeAll(keepingCapacity: false)
    }

    private func canNavigate(to route: AppRoute) -> Bool {
        switch session.state {
        case .authenticatedUnlocked:
            true
        case .authenticatedLocked:
            !route.requiresUnlockedSession
        case .launching,
             .signedOut,
             .authenticating,
             .verificationRequired,
             .multiFactorRequired,
             .deletingAccount:
            false
        }
    }
}
