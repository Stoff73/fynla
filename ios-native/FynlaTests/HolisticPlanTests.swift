import Foundation
import Testing
@testable import Fynla

@Suite("Holistic Plan feature")
struct HolisticPlanTests {
    @Test
    func decodesBackendRankAndAffordabilityWithoutClientRecalculation() throws {
        let response = try JSONDecoder().decode(
            HolisticPlanEnvelope.self,
            from: try fixture()
        )
        let plan = response.data

        #expect(plan.items.map(\.type) == [
            "emergency_fund",
            "increase_pension_contribution",
            "bed_and_isa",
        ])
        #expect(plan.items.map(\.affordability) == [
            "fits",
            "partially_fits",
            "beyond_current_surplus",
        ])
        #expect(plan.effectiveSurplus == Decimal(450))
        #expect(plan.locked.map(\.strategyType) == ["salary_sacrifice_ni"])
    }

    @Test
    func clientUsesExistingCompositePlanEndpoint() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture()),
        ])
        let client = LiveHolisticPlanClient(apiClient: APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: HolisticPlanTokenProvider(),
            requestID: { "holistic-plan-request" }
        ))

        let plan = try await client.load()

        #expect(plan.items.count == 3)
        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/holistic/composite-plan",
        ])
    }

    @Test @MainActor
    func modelPublishesTheServerPlanUnchanged() async throws {
        let plan = try JSONDecoder().decode(
            HolisticPlanEnvelope.self,
            from: try fixture()
        ).data
        let model = HolisticPlanModel(client: HolisticPlanClientStub(plan: plan))

        await model.load()

        #expect(model.state == .loaded(plan))
    }

    private func fixture() throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/HolisticPlan/holistic-plan.json")
        )
    }
}

private struct HolisticPlanTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "holistic-plan-token" }
}

private actor HolisticPlanClientStub: HolisticPlanClient {
    let plan: HolisticPlan

    init(plan: HolisticPlan) { self.plan = plan }

    func load() async throws -> HolisticPlan { plan }
}
