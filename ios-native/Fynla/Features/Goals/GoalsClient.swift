import Foundation

protocol GoalsClient: Sendable {
    func loadGoals() async throws -> GoalListResponse
    func loadOverview() async throws -> GoalsOverview
}

struct LiveGoalsClient: GoalsClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func loadGoals() async throws -> GoalListResponse {
        try await apiClient.send(request(path: "api/goals"))
    }

    func loadOverview() async throws -> GoalsOverview {
        try await apiClient.send(request(path: "api/goals/dashboard-overview"))
    }

    private func request<Value: Decodable & Sendable>(
        path: String
    ) -> APIRequest<Value> {
        APIRequest(
            path: path,
            method: .get,
            headers: ["Cache-Control": "no-cache"]
        )
    }
}
