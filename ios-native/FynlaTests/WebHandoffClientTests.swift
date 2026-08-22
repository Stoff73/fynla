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

    /// This list must equal the backing values of `app/Enums/WebHandoffDestination.php`,
    /// in declaration order. It is a hand-maintained mirror of a hand-maintained
    /// mirror, which is how `estate_will` went missing for as long as it did
    /// (W-0044): the test names the server allowlist but only ever checked a frozen
    /// copy of it. `WebHandoffDestinationTest` on the PHP side asserts the same list,
    /// so adding a case there fails a test that points here.
    @Test
    func exposesOnlyTheServerAllowlistedSemanticDestinations() {
        #expect(WebHandoffDestination.allCases.map(\.rawValue) == [
            "admin", "subscription", "settings", "privacy", "notifications",
            "estate_will",
        ])
    }

    /// The trap W-0044 sat on. Swift's implicit raw value is the case name, so
    /// `case estateWill` would put `"estateWill"` on the wire and the server would
    /// reject it with a 422 — while the Swift side looked entirely correct. Every
    /// multi-word destination needs an explicit snake_case raw value.
    @Test
    func multiWordDestinationsSendTheSnakeCaseValueTheServerValidates() {
        #expect(WebHandoffDestination.estateWill.rawValue == "estate_will")
        for destination in WebHandoffDestination.allCases {
            #expect(destination.rawValue == destination.rawValue.lowercased())
        }
    }

    @Test
    func issuesTheWillBuilderHandoffTheNativeAppHasNoScreenFor() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 201,
                body: Data(
                    """
                    {
                      "success": true,
                      "data": {
                        "url": "https://csjones.co/fynla/web-handoff/will-token",
                        "expires_at": "2026-08-22T16:02:00.000000Z"
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

        let url = try await client.issue(.estateWill)

        #expect(url.absoluteString == "https://csjones.co/fynla/web-handoff/will-token")
        let request = try #require(await transport.requests().first)
        let body = try #require(request.httpBody)
        let object = try #require(
            JSONSerialization.jsonObject(with: body) as? [String: String]
        )
        #expect(object == ["destination": "estate_will"])
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
