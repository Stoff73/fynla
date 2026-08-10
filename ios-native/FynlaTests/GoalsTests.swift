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
        #expect(snapshot.goals[0].detailRoute == .goalDetail(id: 61))
    }

    @Test
    func decodesCanonicalGoalDetailWithoutRecalculatingServerValues() throws {
        let detail = try decode(GoalDetailResponse.self, "goal-detail")

        #expect(detail.goal.description == "Buy a family home near our support network.")
        #expect(detail.goal.targetAmount == Decimal(50_000))
        #expect(detail.goal.currentAmount == Decimal(30_000))
        #expect(detail.goal.monthlyContribution == Decimal(750))
        #expect(detail.goal.createdAt == "2025-02-03T10:00:00Z")
        #expect(detail.milestones.count == 2)
        #expect(FynContextualActions.goal(id: 61).request.currentDestination.screen == "goal_detail")
    }

    @Test
    func clientUsesBothCurrentGoalEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("goals-list")),
            .response(status: 200, body: try fixture("goals-overview")),
            .response(status: 200, body: try fixture("goal-detail")),
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
        _ = try await client.loadGoal(id: 61)

        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/goals",
            "/fynla/api/goals/dashboard-overview",
            "/fynla/api/goals/61",
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
    func loadGoal(id: Int) async throws -> GoalDetailResponse {
        throw APIError.server(status: 404, requestID: nil)
    }
}
