import Foundation

protocol SavingsClient: Sendable {
    func load() async throws -> SavingsSnapshot
    func loadAccount(id: Int) async throws -> SavingsAccount
}

struct LiveSavingsClient: SavingsClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> SavingsSnapshot {
        try await apiClient.send(
            APIRequest<SavingsSnapshot>(
                path: "api/savings",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }

    func loadAccount(id: Int) async throws -> SavingsAccount {
        try await apiClient.send(
            APIRequest<SavingsAccount>(
                path: "api/savings/accounts/\(id)",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }
}
