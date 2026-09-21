import Foundation

protocol ThresholdClient: Sendable {
    func load() async throws -> ThresholdPosition
}

/// The same `/api/thresholds` payload web and /m read; native shows the end
/// figures only (CSJ 2026-09-21).
struct LiveThresholdClient: ThresholdClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> ThresholdPosition {
        try await apiClient.send(
            APIRequest<ThresholdPosition>(path: "api/thresholds", method: .get)
        )
    }
}
