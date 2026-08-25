import Foundation

protocol NetWorthClient: Sendable {
    func load() async throws -> NetWorthSnapshot
    func loadProperty(id: Int) async throws -> PropertyDetailResponse
    func loadMortgage(id: Int) async throws -> MortgageDetailResponse
    func loadLiability(id: Int) async throws -> LiabilityDetailResponse
}

extension NetWorthClient {
    func loadProperty(id: Int) async throws -> PropertyDetailResponse {
        throw APIError.server(status: 501, requestID: nil)
    }

    func loadMortgage(id: Int) async throws -> MortgageDetailResponse {
        throw APIError.server(status: 501, requestID: nil)
    }

    func loadLiability(id: Int) async throws -> LiabilityDetailResponse {
        throw APIError.server(status: 501, requestID: nil)
    }
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

    func loadProperty(id: Int) async throws -> PropertyDetailResponse {
        try await detail(path: "api/properties/\(id)")
    }

    func loadMortgage(id: Int) async throws -> MortgageDetailResponse {
        try await detail(path: "api/mortgages/\(id)")
    }

    func loadLiability(id: Int) async throws -> LiabilityDetailResponse {
        try await detail(path: "api/estate/liabilities/\(id)")
    }

    private func detail<Value: Decodable & Sendable>(path: String) async throws -> Value {
        try await apiClient.send(APIRequest(
            path: path,
            method: .get,
            headers: ["Cache-Control": "no-cache"]
        ))
    }
}
