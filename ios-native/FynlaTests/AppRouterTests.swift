import Testing
@testable import Fynla

@Suite("App router")
struct AppRouterTests {
    @Test @MainActor
    func rejectsFinancialRoutesUnlessTheSessionIsUnlocked() {
        let restrictedStates: [AppSession.State] = [
            .launching,
            .signedOut,
            .authenticating,
            .verificationRequired,
            .multiFactorRequired,
            .restorationRequired,
            .passwordChangeRequired,
            .authenticatedLocked,
            .deletingAccount,
        ]

        for state in restrictedStates {
            let session = AppSession(state: state)
            let router = AppRouter(session: session)

            for route in financialRoutes {
                #expect(!router.navigate(to: route))
            }

            #expect(router.path.isEmpty)
        }
    }

    @Test @MainActor
    func acceptsEveryFinancialRouteWhenTheSessionIsUnlocked() {
        let session = AppSession(state: .authenticatedUnlocked)
        let router = AppRouter(session: session)

        for route in financialRoutes {
            #expect(router.navigate(to: route))
        }

        #expect(router.path == financialRoutes)
    }

    @Test @MainActor
    func permitsSettingsForAnAuthenticatedLockedSession() {
        let session = AppSession(state: .authenticatedLocked)
        let router = AppRouter(session: session)

        #expect(router.navigate(to: .settings))
        #expect(router.path == [.settings])
    }

    @Test @MainActor
    func rejectsSettingsWithoutAnAuthenticatedSession() {
        let session = AppSession(state: .signedOut)
        let router = AppRouter(session: session)

        #expect(!router.navigate(to: .settings))
        #expect(router.path.isEmpty)
    }

    @Test @MainActor
    func passwordChangeGateRejectsEveryRouteIncludingSettings() {
        let session = AppSession(state: .passwordChangeRequired)
        let router = AppRouter(session: session)

        for route in financialRoutes + [.settings] {
            #expect(!router.navigate(to: route))
        }
        #expect(router.path.isEmpty)
    }

    @Test @MainActor
    func removesRoutesWithoutExposingMutableNavigationState() {
        let session = AppSession(state: .authenticatedUnlocked)
        let router = AppRouter(session: session)

        #expect(router.navigate(to: .dashboard))
        #expect(router.navigate(to: .income))
        #expect(router.removeLast())
        #expect(router.path == [.dashboard])
        router.reset()
        #expect(router.path.isEmpty)
        #expect(!router.removeLast())
    }

    @Test @MainActor
    func restoresAnUnlockedBackStackAndRejectsLockedFinancialState() {
        let unlocked = AppSession(state: .authenticatedUnlocked)
        let unlockedRouter = AppRouter(session: unlocked)
        let restored: [AppRoute] = [.income, .netWorth(category: "property")]

        #expect(unlockedRouter.restore(restored))
        #expect(unlockedRouter.path == restored)

        let locked = AppSession(state: .authenticatedLocked)
        let lockedRouter = AppRouter(session: locked)
        #expect(!lockedRouter.restore(restored))
        #expect(lockedRouter.path.isEmpty)
        #expect(lockedRouter.restore([.settings]))
        #expect(lockedRouter.path == [.settings])
    }

    private var financialRoutes: [AppRoute] {
        [
            .dashboard,
            .achievements,
            .module("savings"),
            .income,
            .expenditure,
            .netWorth(category: "property"),
            .protection(policyType: "life", id: 11),
            .savings(accountID: 12),
            .investment(accountID: 13),
            .retirement(pensionType: "workplace", id: 14),
            .estate,
            .goals,
            .taxStrategy,
            .holisticPlan,
        ]
    }
}
