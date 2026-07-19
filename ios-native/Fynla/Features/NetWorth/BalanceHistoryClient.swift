import Foundation

protocol BalanceHistoryClient: Sendable {
    func load() async throws -> BalanceHistory
    func downloadAdviserExport() async throws -> Data
}

struct LiveBalanceHistoryClient: BalanceHistoryClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> BalanceHistory {
        try await apiClient.send(
            APIRequest<BalanceHistoryResponse>(
                path: "api/balance-history",
                method: .get,
                headers: ["Cache-Control": "no-cache"],
                responseDecoding: .raw
            )
        ).data
    }

    func downloadAdviserExport() async throws -> Data {
        try await apiClient.sendData(
            APIRequest<BalanceHistoryDownloadMarker>(
                path: "api/plans/adviser-export-pack",
                method: .get,
                headers: ["Accept": "application/pdf"],
                responseDecoding: .raw
            )
        )
    }
}

private struct BalanceHistoryDownloadMarker: Decodable, Sendable {}
