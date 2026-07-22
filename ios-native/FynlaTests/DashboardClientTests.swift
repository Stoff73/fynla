import Foundation
import Testing
@testable import Fynla

@Suite("Dashboard client")
struct DashboardClientTests {
    @Test
    func loadsTheSharedAuthenticatedMobileDashboardWithoutUsingETagCaching() async throws {
        let body = try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Dashboard/populated.json")
        )
        let transport = TestHTTPTransport([
            .response(status: 200, body: body),
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"id":88}}"#.utf8)
            ),
        ])
        let client = APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: DashboardTokenProvider(),
            requestID: { "dashboard-request" }
        )

        let dashboard = try await LiveDashboardClient(apiClient: client).load()

        #expect(dashboard.level.level == 3)
        let request = try #require(await transport.requests().first)
        #expect(request.httpMethod == "GET")
        #expect(request.url?.path == "/fynla/api/v1/mobile/dashboard")
        #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer dashboard-token")
        #expect(request.value(forHTTPHeaderField: "Cache-Control") == "no-cache")
        #expect(request.value(forHTTPHeaderField: "If-None-Match") == nil)

        let action = try #require(dashboard.nextActions.first)
        try await LiveDashboardClient(apiClient: client)
            .markRecommendationDone(action)
        let completion = try #require((await transport.requests()).last)
        #expect(completion.httpMethod == "POST")
        #expect(
            completion.url?.path
                == "/fynla/api/recommendations/saving-1/mark-done"
        )
        #expect(
            completion.httpBody
                == Data(#"{"module":"savings","recommendation_text":"Build your emergency fund"}"#.utf8)
        )
    }
}

private struct DashboardTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "dashboard-token" }
}
