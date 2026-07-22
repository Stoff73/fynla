import Foundation

protocol ExpenditureClient: Sendable {
    func load() async throws -> ExpenditureProfile
}

struct LiveExpenditureClient: ExpenditureClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> ExpenditureProfile {
        try await apiClient.send(
            APIRequest<ExpenditureProfile>(
                path: "api/user/profile",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }
}
