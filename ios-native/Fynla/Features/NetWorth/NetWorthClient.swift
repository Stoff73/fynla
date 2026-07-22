import Foundation

protocol NetWorthClient: Sendable {
    func load() async throws -> NetWorthSnapshot
}

struct LiveNetWorthClient: NetWorthClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> NetWorthSnapshot {
        async let overview = apiClient.send(
            APIRequest<NetWorthOverview>(
                path: "api/net-worth/overview",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
        async let detailed = apiClient.send(
            APIRequest<NetWorthDetailedAssets>(
                path: "api/net-worth/assets-summary-detailed",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )

        return try await NetWorthSnapshot(
            overview: overview,
            detailed: detailed
        )
    }
}
