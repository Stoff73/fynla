import Foundation

protocol IncomeClient: Sendable {
    func load() async throws -> IncomeProfile
}

struct LiveIncomeClient: IncomeClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> IncomeProfile {
        try await apiClient.send(
            APIRequest<IncomeProfile>(
                path: "api/user/profile",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }
}
