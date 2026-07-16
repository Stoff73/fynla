import Testing
@testable import Fynla

@Suite("App session")
struct AppSessionTests {
    @Test @MainActor
    func followsTheAuthenticationAndPrivacyLifecycle() {
        let session = AppSession()

        #expect(session.state == .launching)
        #expect(session.completeLaunch(hasAuthenticatedSession: false))
        #expect(session.state == .signedOut)
        #expect(session.beginAuthentication())
        #expect(session.state == .authenticating)
        #expect(session.requireVerification())
        #expect(session.state == .verificationRequired)
        #expect(session.requireMultiFactor())
        #expect(session.state == .multiFactorRequired)
        #expect(session.completeAuthentication())
        #expect(session.state == .authenticatedLocked)
        #expect(session.unlock())
        #expect(session.state == .authenticatedUnlocked)
        #expect(session.beginAccountDeletion())
        #expect(session.state == .deletingAccount)
        #expect(session.cancelAccountDeletion())
        #expect(session.state == .authenticatedUnlocked)
        session.signOut()
        #expect(session.state == .signedOut)
    }

    @Test @MainActor
    func restoresAnExistingSessionLocked() {
        let session = AppSession()

        #expect(session.completeLaunch(hasAuthenticatedSession: true))
        #expect(session.state == .authenticatedLocked)
    }

    @Test @MainActor
    func rejectsTransitionsThatWouldBypassAuthentication() {
        let session = AppSession()

        #expect(!session.unlock())
        #expect(!session.completeAuthentication())
        #expect(!session.beginAccountDeletion())
        #expect(session.state == .launching)

        #expect(session.completeLaunch(hasAuthenticatedSession: false))
        #expect(!session.requireVerification())
        #expect(!session.requireMultiFactor())
        #expect(session.state == .signedOut)
    }

    @Test @MainActor
    func locksAnUnlockedSession() {
        let session = AppSession(state: .authenticatedUnlocked)

        #expect(session.lock())
        #expect(session.state == .authenticatedLocked)
        #expect(!session.lock())
    }
}
