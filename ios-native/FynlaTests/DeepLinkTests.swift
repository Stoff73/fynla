import Foundation
import Testing
@testable import Fynla

@Suite("Safe native universal links")
struct DeepLinkTests {
    @Test
    func productionParserMapsEverySupportedMobileRouteShape() throws {
        let parser = DeepLinkParser(environment: environment(
            name: "production",
            baseURL: "https://fynla.org"
        ))

        let routes: [(String, AppRoute)] = [
            ("/dashboard", .dashboard),
            ("/achievements", .achievements),
            ("/income", .income),
            ("/expenditure", .expenditure),
            ("/net-worth", .netWorth(category: nil)),
            ("/net-worth/history", .balanceHistory),
            ("/net-worth/property", .netWorth(category: "property")),
            ("/protection", .protection(policyType: nil, id: nil)),
            ("/protection/policy/criticalIllness/41", .protection(policyType: "criticalIllness", id: 41)),
            ("/savings", .savings(accountID: nil)),
            ("/savings/account/42", .savings(accountID: 42)),
            ("/investment", .investment(accountID: nil)),
            ("/investment/account/43", .investment(accountID: 43)),
            ("/retirement", .retirement(pensionType: nil, id: nil)),
            ("/retirement/pension/dc/44", .retirement(pensionType: "dc", id: 44)),
            ("/retirement/pension/state/self", .retirement(pensionType: "state", id: nil)),
            ("/estate", .estate),
            ("/goals", .goals),
            ("/tax-strategy", .taxStrategy),
            ("/holistic-plan", .holisticPlan),
            ("/settings", .settings),
        ]

        for (path, expected) in routes {
            let destination = parser.destination(for: try #require(URL(
                string: "https://fynla.org\(path)"
            )))
            #expect(destination == .native(expected), "Failed route: \(path)")
        }
    }

    @Test
    func stagingParserRequiresTheEnvironmentHostAndBasePath() throws {
        let parser = DeepLinkParser(environment: environment(
            name: "staging",
            baseURL: "https://csjones.co/fynla"
        ))

        #expect(parser.destination(for: try #require(URL(
            string: "https://csjones.co/fynla/savings/account/7"
        ))) == .native(.savings(accountID: 7)))
        #expect(parser.destination(for: try #require(URL(
            string: "https://csjones.co/savings/account/7"
        ))) == .external(try #require(URL(
            string: "https://csjones.co/savings/account/7"
        ))))
        #expect(parser.destination(for: try #require(URL(
            string: "https://fynla.org/fynla/savings/account/7"
        ))) == .ignored)
    }

    @Test
    func parserRejectsAdminApiPaymentUnknownAndMalformedRoutesToWeb() throws {
        let parser = DeepLinkParser(environment: environment(
            name: "production",
            baseURL: "https://fynla.org"
        ))
        let externalPaths = [
            "/admin/users",
            "/api/v1/mobile/devices",
            "/.well-known/apple-app-site-association",
            "/payment/return",
            "/webhooks/revolut",
            "/net-worth/not-a-category",
            "/protection/policy/unknown/12",
            "/protection/policy/life/0",
            "/savings/account/01",
            "/investment/account/not-an-id",
            "/retirement/pension/unknown/4",
            "/settings/extra",
            "/unknown",
        ]

        for path in externalPaths {
            let url = try #require(URL(string: "https://fynla.org\(path)"))
            #expect(parser.destination(for: url) == .external(url), "Failed route: \(path)")
        }

        #expect(parser.destination(for: try #require(URL(
            string: "http://fynla.org/dashboard"
        ))) == .ignored)
        #expect(parser.destination(for: try #require(URL(
            string: "https://user@fynla.org/dashboard"
        ))) == .ignored)
        #expect(parser.destination(for: try #require(URL(
            string: "https://example.com/dashboard"
        ))) == .ignored)
        #expect(parser.destination(for: try #require(URL(
            string: "https://fynla.org/dashboard?account=41"
        ))) == .external(try #require(URL(
            string: "https://fynla.org/dashboard?account=41"
        ))))
    }

    @Test @MainActor
    func supportedLinkWaitsForAuthenticationAndFaceIDBeforeRouting() throws {
        let session = AppSession(state: .authenticatedLocked)
        let router = AppRouter(session: session)
        var openedURLs: [URL] = []
        let coordinator = PendingDeepLinkCoordinator(
            parser: DeepLinkParser(environment: environment(
                name: "production",
                baseURL: "https://fynla.org"
            )),
            session: session,
            router: router,
            openExternal: { openedURLs.append($0) }
        )

        coordinator.receive(try #require(URL(
            string: "https://fynla.org/net-worth/cash"
        )))
        #expect(coordinator.pendingRoute == .netWorth(category: "cash"))
        #expect(router.path.isEmpty)

        #expect(session.unlock())
        coordinator.sessionDidChange()
        #expect(coordinator.pendingRoute == nil)
        #expect(router.path == [.netWorth(category: "cash")])
        #expect(openedURLs.isEmpty)
    }

    @Test @MainActor
    func unsupportedActiveHostLinkOpensWebAndForeignHostIsIgnored() throws {
        let session = AppSession(state: .authenticatedUnlocked)
        let router = AppRouter(session: session)
        var openedURLs: [URL] = []
        let coordinator = PendingDeepLinkCoordinator(
            parser: DeepLinkParser(environment: environment(
                name: "production",
                baseURL: "https://fynla.org"
            )),
            session: session,
            router: router,
            openExternal: { openedURLs.append($0) }
        )
        let adminURL = try #require(URL(string: "https://fynla.org/admin/users"))

        coordinator.receive(adminURL)
        coordinator.receive(try #require(URL(string: "https://example.com/dashboard")))

        #expect(openedURLs == [adminURL])
        #expect(router.path.isEmpty)
        #expect(coordinator.pendingRoute == nil)
    }

    @Test @MainActor
    func dashboardLinkReturnsAWorkingAppToItsRoot() throws {
        let session = AppSession(state: .authenticatedUnlocked)
        let router = AppRouter(session: session)
        #expect(router.navigate(to: .income))
        let coordinator = PendingDeepLinkCoordinator(
            parser: DeepLinkParser(environment: environment(
                name: "production",
                baseURL: "https://fynla.org"
            )),
            session: session,
            router: router,
            openExternal: { _ in }
        )

        coordinator.receive(try #require(URL(
            string: "https://fynla.org/dashboard"
        )))

        #expect(router.path.isEmpty)
        #expect(coordinator.pendingRoute == nil)
    }

    private func environment(name: String, baseURL: String) -> AppEnvironment {
        try! AppEnvironment.values([
            "FYNLA_ENVIRONMENT": name,
            "FYNLA_API_BASE_URL": baseURL,
            "FYNLA_WEB_BASE_URL": baseURL,
        ])
    }
}
