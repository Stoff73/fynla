import Foundation

protocol RetirementClient: Sendable {
    func loadIndex() async throws -> RetirementIndex
    func analyze() async throws -> RetirementAnalysis
    func loadProjections() async throws -> RetirementProjections
    func loadDCPensionProjection(id: Int) async throws -> RetirementPotProjection
}

struct LiveRetirementClient: RetirementClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func loadIndex() async throws -> RetirementIndex {
        try await apiClient.send(request(path: "api/retirement"))
    }

    func analyze() async throws -> RetirementAnalysis {
        try await apiClient.send(
            APIRequest<RetirementAnalysis>(
                path: "api/retirement/analyze",
                method: .post,
                body: Data("{}".utf8),
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }

    func loadProjections() async throws -> RetirementProjections {
        try await apiClient.send(request(path: "api/retirement/projections"))
    }

    func loadDCPensionProjection(id: Int) async throws -> RetirementPotProjection {
        try await apiClient.send(
            request(path: "api/retirement/dc-pensions/\(id)/projections")
        )
    }

    private func request<Value: Decodable & Sendable>(
        path: String
    ) -> APIRequest<Value> {
        APIRequest(
            path: path,
            method: .get,
            headers: ["Cache-Control": "no-cache"]
        )
    }
}
