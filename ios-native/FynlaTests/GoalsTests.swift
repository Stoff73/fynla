import Foundation
import Testing
@testable import Fynla

@Suite("Goals feature")
struct GoalsTests {
    @Test
    func decodesServerGoalProgressAndOverview() throws {
        let list = try decode(GoalListResponse.self, "goals-list")
        let overview = try decode(GoalsOverview.self, "goals-overview")
        let snapshot = GoalsSnapshot(list: list, overview: overview)

        #expect(snapshot.goals.count == 2)
        #expect(snapshot.overview?.overallProgress == Decimal(string: "66.7"))
        #expect(snapshot.goals[0].progressPercentage == Decimal(60))
        #expect(snapshot.goals[0].isOnTrack)
        #expect(snapshot.goals[1].status == "completed")
    }

    @Test
    func clientUsesBothCurrentGoalEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("goals-list")),
            .response(status: 200, body: try fixture("goals-overview")),
        ])
        let client = LiveGoalsClient(apiClient: APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: GoalsTokenProvider(),
            requestID: { "goals-request" }
        ))

        _ = try await client.loadGoals()
        _ = try await client.loadOverview()

        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/goals",
            "/fynla/api/goals/dashboard-overview",
        ])
    }

    @Test @MainActor
    func modelLoadsAndClears() async throws {
        let list = try decode(GoalListResponse.self, "goals-list")
        let overview = try decode(GoalsOverview.self, "goals-overview")
        let snapshot = GoalsSnapshot(list: list, overview: overview)
        let model = GoalsModel(client: GoalsClientStub(list: list, overview: overview))

        await model.load()
        #expect(model.state == .loaded(snapshot))
        model.stop()
        #expect(model.state == .idle)
    }

    private func decode<Value: Decodable & Sendable>(
        _ type: Value.Type,
        _ name: String
    ) throws -> Value {
        try JSONDecoder().decode(
            APIEnvelope<Value>.self,
            from: try fixture(name)
        ).data
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/Goals/\(name).json")
        )
    }
}

private struct GoalsTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "goals-token" }
}

private struct GoalsClientStub: GoalsClient {
    let list: GoalListResponse
    let overview: GoalsOverview
    func loadGoals() async throws -> GoalListResponse { list }
    func loadOverview() async throws -> GoalsOverview { overview }
}
