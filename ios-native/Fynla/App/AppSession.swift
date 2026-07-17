import Observation

@MainActor
@Observable
final class AppSession {
    enum State: Sendable, Equatable {
        case launching
        case signedOut
        case authenticating
        case verificationRequired
        case multiFactorRequired
        case restorationRequired
        case authenticatedLocked
        case authenticatedUnlocked
        case deletingAccount
    }

    private(set) var state: State

    init(state: State = .launching) {
        self.state = state
    }

    @discardableResult
    func completeLaunch(hasAuthenticatedSession: Bool) -> Bool {
        guard state == .launching else { return false }
        state = hasAuthenticatedSession ? .authenticatedLocked : .signedOut
        return true
    }

    @discardableResult
    func beginAuthentication() -> Bool {
        transition(from: [.signedOut], to: .authenticating)
    }

    @discardableResult
    func requireVerification() -> Bool {
        transition(from: [.authenticating], to: .verificationRequired)
    }

    @discardableResult
    func requireMultiFactor() -> Bool {
        transition(
            from: [.authenticating, .verificationRequired],
            to: .multiFactorRequired
        )
    }

    @discardableResult
    func requireRestoration() -> Bool {
        transition(from: [.authenticating], to: .restorationRequired)
    }

    @discardableResult
    func completeAuthentication() -> Bool {
        transition(
            from: [.authenticating, .verificationRequired, .multiFactorRequired],
            to: .authenticatedLocked
        )
    }

    @discardableResult
    func unlock() -> Bool {
        transition(from: [.authenticatedLocked], to: .authenticatedUnlocked)
    }

    @discardableResult
    func lock() -> Bool {
        transition(from: [.authenticatedUnlocked], to: .authenticatedLocked)
    }

    @discardableResult
    func beginAccountDeletion() -> Bool {
        transition(from: [.authenticatedUnlocked], to: .deletingAccount)
    }

    @discardableResult
    func cancelAccountDeletion() -> Bool {
        transition(from: [.deletingAccount], to: .authenticatedUnlocked)
    }

    func signOut() {
        state = .signedOut
    }

    private func transition(from allowedStates: Set<State>, to newState: State) -> Bool {
        guard allowedStates.contains(state) else { return false }
        state = newState
        return true
    }
}
