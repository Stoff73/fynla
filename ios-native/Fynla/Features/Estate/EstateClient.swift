import Foundation

protocol EstateClient: Sendable {
    func loadIndex() async throws -> EstateIndexResponse
    func loadNetWorth() async throws -> EstateNetWorthResponse
}

struct LiveEstateClient: EstateClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func loadIndex() async throws -> EstateIndexResponse {
        try await apiClient.send(
            APIRequest<EstateIndexResponse>(
                path: "api/estate",
                method: .get,
                headers: ["Cache-Control": "no-cache"],
                responseDecoding: .raw
            )
        )
    }

    func loadNetWorth() async throws -> EstateNetWorthResponse {
        try await apiClient.send(
            APIRequest<EstateNetWorthResponse>(
                path: "api/estate/net-worth",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }
}
