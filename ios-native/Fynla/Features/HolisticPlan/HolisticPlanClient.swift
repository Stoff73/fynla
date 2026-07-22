import Foundation

protocol HolisticPlanClient: Sendable {
    func load() async throws -> HolisticPlan
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
