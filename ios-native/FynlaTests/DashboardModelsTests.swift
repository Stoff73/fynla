import Foundation
import Testing
@testable import Fynla

@Suite("Dashboard contract")
struct DashboardModelsTests {
    // Unknown server strings must degrade, never kill a screen decode
    // (mirrors /m's tolerance of new payload values).
    @Test
    func unknownEnumValuesDecodeToSafeFallbacks() throws {
        let decoder = JSONDecoder()
        #expect(try decoder.decode(
            DashboardModuleStatus.self, from: Data("\"brand_new\"".utf8)
        ) == .unavailable)
        #expect(try decoder.decode(
            DashboardAlertSeverity.self, from: Data("\"urgent\"".utf8)
        ) == .info)
        #expect(try decoder.decode(
            DashboardActionType.self, from: Data("\"celebration\"".utf8)
        ) == .recommendation)
        #expect(try decoder.decode(
            DashboardActionKind.self, from: Data("\"open_modal\"".utf8)
        ) == .unknown)
        #expect(try decoder.decode(
            EstateMode.self, from: Data("\"premium\"".utf8)
        ) == .teaser)
    }

    @Test
    func decodesThePopulatedMobileDashboardWithoutRecomputingServerValues() throws {
        let dashboard = try decode("populated")

        #expect(dashboard.netWorth.total == Decimal(347_250.50))
        #expect(dashboard.netWorth.breakdown.totalAssets == Decimal(512_000.50))
        #expect(dashboard.modules.savings.status == .active)
        #expect(dashboard.modules.savings.totalSavings == Decimal(42_500.25))
        #expect(
            dashboard.modules.ordered.first(where: { $0.key == "savings" })?.title
                == "Bank Accounts"
        )
        #expect(
            FinancePanel.panels(from: dashboard).first(where: { $0.id == "savings" })?.label
                == "Bank Accounts"
        )
        #expect(dashboard.modules.retirement.potValue == Decimal(185_000))
        #expect(dashboard.level.level == 3)
        #expect(dashboard.level.actionsCompleted == 2)
        #expect(dashboard.level.actionsTotal == 4)
        #expect(dashboard.percentile == 68)
        #expect(dashboard.focusAreas.first?.actions.first?.action.kind == .navigate)
        #expect(
            dashboard.focusAreas.first?.actions.first?.title
                == "Review whether increasing your workplace pension contributions could improve your retirement outcome"
        )
        #expect(
            dashboard.focusAreas.first?.actions.first?.meta
                == "This explanation must remain readable across multiple lines on a narrow mobile screen."
        )
        #expect(
            SemanticDestinationResolver.route(
                for: dashboard.focusAreas.first?.actions.first?.action.destination,
                legacyPath: dashboard.focusAreas.first?.actions.first?.action.payload
            ) == .retirement(pensionType: nil, id: nil)
        )
        #expect(dashboard.alerts.first?.severity == .important)
        #expect(dashboard.newMilestones.first?.type == "net_worth")
        #expect(dashboard.nextMilestone?.route == "m-net-worth")
        #expect(dashboard.entitlement?.tier == .free)
    }

    @Test(arguments: ["new-user", "partially-configured", "module-unavailable", "free-gated"])
    func decodesEveryDashboardState(_ fixtureName: String) throws {
        _ = try decode(fixtureName)
    }

    @Test
    func keepsUnavailableAndMissingMoneyExplicitInsteadOfFabricatingZero() throws {
        let dashboard = try decode("module-unavailable")

        #expect(dashboard.modules.investment.status == .unavailable)
        #expect(dashboard.modules.investment.portfolioValue == nil)
        #expect(dashboard.modules.investment.message == "Unable to load module data at this time.")
    }

    @Test
    func preservesFreeCapabilityGatesFromTheCanonicalDashboardPayload() throws {
        let dashboard = try decode("free-gated")

        #expect(dashboard.entitlement?.tier == .free)
        #expect(dashboard.entitlement?.capabilities["holistic_plan"] == "preview")
        #expect(dashboard.entitlement?.limits["savings_account"] == 2)
    }

    @Test
    func semanticDestinationWinsOverAConflictingLegacyTaxPath() throws {
        let action = try JSONDecoder().decode(
            DashboardActionDestination.self,
            from: Data(#"""
            {
                "kind":"navigate",
                "payload":"/tax-strategy",
                "destination":{
                    "screen":"retirement",
                    "params":{"pension_id":8472,"pension_type":"dc"},
                    "fallback":"net_worth"
                }
            }
            """#.utf8)
        )

        #expect(action.destination?.screen == "retirement")
        #expect(action.destination?.params["pension_id"] == .int(8472))
        #expect(action.destination?.params["pension_type"] == .string("dc"))
        #expect(
            SemanticDestinationResolver.route(
                for: action.destination,
                legacyPath: action.payload
            ) == .retirement(pensionType: nil, id: nil)
        )
    }

    @Test
    func unknownSemanticScreenUsesExplicitFallbackAndReportsOnlyTheScreen() throws {
        let destination = try JSONDecoder().decode(
            SemanticDestination.self,
            from: Data(#"""
            {
                "screen":"future_screen",
                "params":{"account_id":8472,"current_value":"184500"},
                "fallback":"net_worth"
            }
            """#.utf8)
        )
        var unknownScreens: [String] = []

        let route = SemanticDestinationResolver.route(
            for: destination,
            legacyPath: "/tax-strategy",
            onUnknown: { unknownScreens.append($0) }
        )

        #expect(route == .netWorth(category: nil))
        #expect(unknownScreens == ["future_screen"])
    }

    @Test
    func legacyDashboardPathsRemainAllowlistedDuringRollout() {
        #expect(
            SemanticDestinationResolver.route(
                for: nil,
                legacyPath: "/investment"
            ) == .investment(accountID: nil)
        )
        #expect(
            SemanticDestinationResolver.route(
                for: nil,
                legacyPath: "/not-an-app-route"
            ) == .dashboard
        )
    }

    @Test(arguments: [
        ("personal_information", AppRoute.personalInformation),
        ("subscription", AppRoute.subscription),
        ("settings", AppRoute.settings),
    ])
    func resolvesEveryServerAdvertisedAccountDestination(
        _ screen: String,
        _ expected: AppRoute
    ) {
        let destination = SemanticDestination(
            screen: screen,
            params: [:],
            fallback: "dashboard"
        )

        #expect(
            SemanticDestinationResolver.route(
                for: destination,
                legacyPath: nil
            ) == expected
        )
    }

    private func decode(_ name: String) throws -> DashboardSnapshot {
        try JSONDecoder().decode(
            APIEnvelope<DashboardSnapshot>.self,
            from: fixture(name)
        ).data
    }

    private func fixture(_ name: String) throws -> Data {
        let fileURL = URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/Dashboard/\(name).json")
        return try Data(contentsOf: fileURL)
    }
}
