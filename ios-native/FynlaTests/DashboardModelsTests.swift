import Foundation
import Testing
@testable import Fynla

@Suite("Dashboard contract")
struct DashboardModelsTests {
    @Test
    func decodesThePopulatedMobileDashboardWithoutRecomputingServerValues() throws {
        let dashboard = try decode("populated")

        #expect(dashboard.netWorth.total == Decimal(347_250.50))
        #expect(dashboard.netWorth.breakdown.totalAssets == Decimal(512_000.50))
        #expect(dashboard.modules.savings.status == .active)
        #expect(dashboard.modules.savings.totalSavings == Decimal(42_500.25))
        #expect(dashboard.modules.retirement.potValue == Decimal(185_000))
        #expect(dashboard.level.level == 3)
        #expect(dashboard.level.actionsCompleted == 2)
        #expect(dashboard.level.actionsTotal == 4)
        #expect(dashboard.percentile == 68)
        #expect(dashboard.focusAreas.first?.actions.first?.action.kind == .navigate)
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
