import Foundation
import Testing
@testable import Fynla

@Suite("Secure native web handoff")
struct WebHandoffClientTests {
    @Test
    func issuesAnAllowlistedDestinationThroughTheAuthenticatedAPI() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 201,
                body: Data(
                    """
                    {
                      "success": true,
                      "data": {
                        "url": "https://csjones.co/fynla/web-handoff/one-time-token",
                        "expires_at": "2026-08-09T16:02:00.000000Z"
                      }
                    }
                    """.utf8
                )
            ),
        ])
        let apiClient = APIClient(
            environment: try Self.environment(),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: WebHandoffTokenProvider(),
            requestID: { "handoff-request" }
        )
        let client = LiveWebHandoffClient(
            apiClient: apiClient,
            trustedWebBaseURL: URL(string: "https://csjones.co/fynla")!
        )

        let url = try await client.issue(.admin)

        #expect(url.absoluteString == "https://csjones.co/fynla/web-handoff/one-time-token")
        let request = try #require(await transport.requests().first)
        #expect(request.httpMethod == "POST")
        #expect(request.url?.path == "/fynla/api/v1/mobile/web-handoffs")
        #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer handoff-token")
        let body = try #require(request.httpBody)
        let object = try #require(
            JSONSerialization.jsonObject(with: body) as? [String: String]
        )
        #expect(object == ["destination": "admin"])
    }

    @Test
    func rejectsAHandoffURLOutsideTheConfiguredWebOrigin() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 201,
                body: Data(
                    """
                    {
                      "success": true,
                      "data": {
                        "url": "https://attacker.example/collect",
                        "expires_at": "2026-08-09T16:02:00.000000Z"
                      }
                    }
                    """.utf8
                )
            ),
        ])
        let client = LiveWebHandoffClient(
            apiClient: APIClient(
                environment: try Self.environment(),
                version: "1.0.0",
                build: "12",
                transport: transport,
                tokenProvider: WebHandoffTokenProvider()
            ),
            trustedWebBaseURL: URL(string: "https://csjones.co/fynla")!
        )

        await #expect(throws: WebHandoffError.untrustedURL) {
            try await client.issue(.subscription)
        }
    }

    @Test
    func exposesOnlyTheServerAllowlistedSemanticDestinations() {
        #expect(WebHandoffDestination.allCases.map(\.rawValue) == [
            "admin", "subscription", "settings", "privacy", "notifications",
        ])
    }

    private static func environment() throws -> AppEnvironment {
        try AppEnvironment.values([
            "FYNLA_ENVIRONMENT": "staging",
            "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
            "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
        ])
    }
}

private struct WebHandoffTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "handoff-token" }
}
