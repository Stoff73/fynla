import Foundation

protocol AchievementsClient: Sendable {
    func loadAchievements() async throws -> AchievementsSnapshot
    func loadCompleted(page: Int) async throws -> AchievementsCompletedPage
    func loadActivity(before: Int?) async throws -> AchievementsActivityPage
    func loadStatus() async throws -> GamificationStatus
    func acknowledgeCelebration() async throws
}

struct LiveAchievementsClient: AchievementsClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func loadAchievements() async throws -> AchievementsSnapshot {
        try await apiClient.send(
            APIRequest<AchievementsSnapshot>(
                path: "api/v1/mobile/achievements",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }

    func loadCompleted(page: Int) async throws -> AchievementsCompletedPage {
        try await apiClient.send(
            APIRequest<AchievementsCompletedPage>(
                path: "api/v1/mobile/achievements/completed",
                method: .get,
                queryItems: [URLQueryItem(name: "page", value: String(page))]
            )
        )
    }

    func loadActivity(before: Int?) async throws -> AchievementsActivityPage {
        let query = before.map {
            [URLQueryItem(name: "before", value: String($0))]
        } ?? []
        return try await apiClient.send(
            APIRequest<AchievementsActivityPage>(
                path: "api/gamification/activity",
                method: .get,
                queryItems: query,
                responseDecoding: .raw
            )
        )
    }

    func loadStatus() async throws -> GamificationStatus {
        try await apiClient.send(
            APIRequest<GamificationStatus>(
                path: "api/gamification/status",
                method: .get,
                responseDecoding: .raw
            )
        )
    }

    func acknowledgeCelebration() async throws {
        let response = try await apiClient.send(
            APIRequest<CelebrationAcknowledgement>(
                path: "api/gamification/celebration/ack",
                method: .post,
                responseDecoding: .raw
            )
        )
        guard response.acknowledged else {
            throw APIError.decoding(requestID: nil)
        }
    }
}

private struct CelebrationAcknowledgement: Decodable, Sendable {
    let acknowledged: Bool
}
