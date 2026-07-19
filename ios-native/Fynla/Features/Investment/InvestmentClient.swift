import Foundation

protocol InvestmentClient: Sendable {
    func load() async throws -> InvestmentSnapshot
}

struct LiveInvestmentClient: InvestmentClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> InvestmentSnapshot {
        try await apiClient.send(
            APIRequest<InvestmentSnapshot>(
                path: "api/investment",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }
}
