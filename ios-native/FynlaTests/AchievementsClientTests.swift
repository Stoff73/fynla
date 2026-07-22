import Foundation
import Testing
@testable import Fynla

@Suite("Achievements client")
struct AchievementsClientTests {
    @Test
    func usesExistingAuthenticatedEndpointsAndPreservesPaginationValues() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("summary")),
            .response(status: 200, body: try fixture("completed-page-2")),
            .response(status: 200, body: try fixture("activity-page-1")),
            .response(status: 200, body: try fixture("status")),
            .response(status: 200, body: Data(#"{"acknowledged":true}"#.utf8)),
        ])
        let client = LiveAchievementsClient(
            apiClient: APIClient(
                environment: try AppEnvironment.values([
                    "FYNLA_ENVIRONMENT": "staging",
                    "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                    "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
                ]),
                version: "1.0.0",
                build: "12",
                transport: transport,
                tokenProvider: AchievementsTokenProvider(),
                requestID: { "achievements-request" }
            )
        )

        _ = try await client.loadAchievements()
        _ = try await client.loadCompleted(page: 2)
        _ = try await client.loadActivity(before: 101)
        let status = try await client.loadStatus()
        try await client.acknowledgeCelebration()

        #expect(status.pendingCelebration?.level == 3)
        let requests = await transport.requests()
        #expect(requests.map { $0.url?.path } == [
            "/fynla/api/v1/mobile/achievements",
            "/fynla/api/v1/mobile/achievements/completed",
            "/fynla/api/gamification/activity",
            "/fynla/api/gamification/status",
            "/fynla/api/gamification/celebration/ack",
        ])
        #expect(requests[1].url?.query == "page=2")
        #expect(requests[2].url?.query == "before=101")
        #expect(requests.last?.httpMethod == "POST")
        #expect(
            requests.allSatisfy {
                $0.value(forHTTPHeaderField: "Authorization")
                    == "Bearer achievements-token"
            }
        )
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Achievements/\(name).json")
        )
    }
}

private struct AchievementsTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "achievements-token" }
}
