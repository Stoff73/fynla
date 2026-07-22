import Foundation

protocol DashboardClient: Sendable {
    func load() async throws -> DashboardSnapshot
    func markRecommendationDone(_ action: DashboardAction) async throws
}

struct LiveDashboardClient: DashboardClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> DashboardSnapshot {
        try await apiClient.send(
            APIRequest<DashboardSnapshot>(
                path: "api/v1/mobile/dashboard",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }

    func markRecommendationDone(_ action: DashboardAction) async throws {
        // Sorted keys keep the body byte-stable across Foundation versions.
        let encoder = JSONEncoder()
        encoder.outputFormatting = .sortedKeys
        let body = try encoder.encode(
            DashboardRecommendationCompletion(
                module: action.module,
                recommendationText: action.title
            )
        )
        _ = try await apiClient.send(
            APIRequest<DashboardCompletionResponse>(
                path: "api/recommendations/\(action.id)/mark-done",
                method: .post,
                body: body
            )
        )
    }
}

private struct DashboardRecommendationCompletion: Encodable, Sendable {
    let module: String
    let recommendationText: String

    private enum CodingKeys: String, CodingKey {
        case module
        case recommendationText = "recommendation_text"
    }
}

private struct DashboardCompletionResponse: Decodable, Sendable {}
