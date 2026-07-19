import Foundation

protocol ProtectionClient: Sendable {
    func loadIndex() async throws -> ProtectionIndex
    func analyze() async throws -> ProtectionAnalysis
}

struct LiveProtectionClient: ProtectionClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func loadIndex() async throws -> ProtectionIndex {
        try await apiClient.send(
            APIRequest<ProtectionIndex>(
                path: "api/protection",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }

    func analyze() async throws -> ProtectionAnalysis {
        try await apiClient.send(
            APIRequest<ProtectionAnalysis>(
                path: "api/protection/analyze",
                method: .post,
                body: Data("{}".utf8),
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }
}
