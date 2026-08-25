import Foundation

protocol HolisticPlanClient: Sendable {
    func load() async throws -> HolisticPlan
}

protocol HolisticPlanClock: Sendable {
    func sleep(for duration: Duration) async throws
}

struct ContinuousHolisticPlanClock: HolisticPlanClock {
    func sleep(for duration: Duration) async throws {
        try await Task.sleep(for: duration)
    }
}

struct LiveHolisticPlanClient: HolisticPlanClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> HolisticPlan {
        try await apiClient.send(
            APIRequest<HolisticPlan>(
                path: "api/holistic/composite-plan",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }
}
