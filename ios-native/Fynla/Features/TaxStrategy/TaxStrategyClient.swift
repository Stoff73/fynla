import Foundation

protocol TaxStrategyClient: Sendable {
    func load() async throws -> TaxStrategyDashboard
    func markDone(_ recommendation: TaxRecommendation) async throws
}

struct LiveTaxStrategyClient: TaxStrategyClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> TaxStrategyDashboard {
        try await apiClient.send(
            APIRequest<TaxStrategyEnvelope>(
                path: "api/tax-strategy",
                method: .get,
                headers: ["Cache-Control": "no-cache"],
                responseDecoding: .raw
            )
        ).data
    }

    func markDone(_ recommendation: TaxRecommendation) async throws {
        guard let recommendationID = recommendation.recommendationID else { return }
        let body = try JSONEncoder().encode(
            TaxRecommendationCompletion(
                module: "tax",
                recommendationText: recommendation.title
            )
        )
        _ = try await apiClient.send(
            APIRequest<TaxCompletionResponse>(
                path: "api/recommendations/\(recommendationID)/mark-done",
                method: .post,
                body: body
            )
        )
    }
}

private struct TaxRecommendationCompletion: Encodable, Sendable {
    let module: String
    let recommendationText: String

    private enum CodingKeys: String, CodingKey {
        case module
        case recommendationText = "recommendation_text"
    }
}

private struct TaxCompletionResponse: Decodable, Sendable {}
