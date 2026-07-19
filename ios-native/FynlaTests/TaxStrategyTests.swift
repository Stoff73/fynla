import Foundation
import Testing
@testable import Fynla

@Suite("Tax Strategy feature")
struct TaxStrategyTests {
    @Test
    func decodesServerAllowancesAndComposedPlan() throws {
        let dashboard = try decodeRaw(TaxStrategyEnvelope.self, "tax-strategy").data

        #expect(dashboard.taxYear == "2026/27")
        #expect(dashboard.isHousehold)
        #expect(dashboard.headroomCount == 1)
        #expect(dashboard.openRecommendations.map(\.type) == ["isa_topup_vs_psa"])
        #expect(dashboard.householdRecommendations.count == 1)
        #expect(dashboard.completedRecommendations.map(\.type) == ["bed_and_isa"])
        #expect(dashboard.composedPlan.combinedAnnualSaving == Decimal(2450))
    }

    @Test
    func clientLoadsAndCompletesThroughExistingEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("tax-strategy")),
            .response(status: 200, body: try fixture("recommendation-complete")),
        ])
        let client = LiveTaxStrategyClient(apiClient: APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: TaxStrategyTokenProvider(),
            requestID: { "tax-strategy-request" }
        ))

        let dashboard = try await client.load()
        try await client.markDone(dashboard.openRecommendations[0])

        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/tax-strategy",
            "/fynla/api/recommendations/tax_isa_topup_vs_psa/mark-done",
        ])
    }

    @Test @MainActor
    func modelReloadsOnlyAfterCompletionAcknowledgement() async throws {
        let dashboard = try decodeRaw(TaxStrategyEnvelope.self, "tax-strategy").data
        let client = TaxStrategyClientStub(dashboard: dashboard)
        let model = TaxStrategyModel(client: client)

        await model.load()
        await model.markDone(dashboard.openRecommendations[0])

        #expect(model.state == .loaded(dashboard))
        #expect(await client.completionCount() == 1)
        #expect(await client.loadCount() == 2)
    }

    private func decodeRaw<Value: Decodable>(
        _ type: Value.Type,
        _ name: String
    ) throws -> Value {
        try JSONDecoder().decode(type, from: try fixture(name))
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/TaxStrategy/\(name).json")
        )
    }
}

private struct TaxStrategyTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "tax-strategy-token" }
}

private actor TaxStrategyClientStub: TaxStrategyClient {
    let dashboard: TaxStrategyDashboard
    private var loads = 0
    private var completions = 0

    init(dashboard: TaxStrategyDashboard) { self.dashboard = dashboard }

    func load() async throws -> TaxStrategyDashboard {
        loads += 1
        return dashboard
    }

    func markDone(_ recommendation: TaxRecommendation) async throws {
        completions += 1
    }

    func loadCount() -> Int { loads }
    func completionCount() -> Int { completions }
}
