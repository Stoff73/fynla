import Foundation
import Testing
@testable import Fynla

@Suite("API client")
struct APIClientTests {
    @Test
    func decodesASuccessEnvelopeAndAddsNativeHeaders() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: json(#"{"success":true,"data":{"value":"ready"}}"#)),
        ])
        let tokens = TestTokenStore(token: "access-token")
        let client = makeClient(transport: transport, tokens: tokens)

        let value = try await client.send(
            APIRequest<TestValue>(path: "api/v1/mobile/dashboard", method: .get)
        )

        #expect(value == TestValue(value: "ready"))
        let request = try #require(await transport.requests().first)
        #expect(request.url?.absoluteString == "https://csjones.co/fynla/api/v1/mobile/dashboard")
        #expect(request.value(forHTTPHeaderField: "Accept") == "application/json")
        #expect(request.value(forHTTPHeaderField: "X-Fynla-Client") == "ios")
        #expect(request.value(forHTTPHeaderField: "X-Fynla-Version") == "1.2.3")
        #expect(request.value(forHTTPHeaderField: "X-Fynla-Build") == "45")
        #expect(request.value(forHTTPHeaderField: "X-Request-ID") == "request-123")
        #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer access-token")
    }

    @Test
    func omitsAuthorizationWhenTheProviderHasNoToken() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: json(#"{"success":true,"data":{"value":"public"}}"#)),
        ])
        let tokens = TestTokenStore(token: nil)
        let client = makeClient(transport: transport, tokens: tokens)

        _ = try await client.send(APIRequest<TestValue>(path: "api/v1/health", method: .get))

        let request = try #require(await transport.requests().first)
        #expect(request.value(forHTTPHeaderField: "Authorization") == nil)
    }

    @Test
    func preservesLaravelFieldValidationErrors() async {
        let transport = TestHTTPTransport([
            .response(
                status: 422,
                body: json(#"{"success":false,"message":"The given data was invalid.","errors":{"email":["The email has already been taken."],"amount":["The amount must be positive."]}}"#)
            ),
        ])
        let client = makeClient(transport: transport)

        await expectError(
            .validation([
                "email": ["The email has already been taken."],
                "amount": ["The amount must be positive."],
            ]),
            from: client,
            request: APIRequest<TestValue>(path: "api/example", method: .post)
        )
    }

    @Test
    func mapsAuthenticationForbiddenUpgradeConflictAndServerErrors() async {
        let cases: [(Int, String, APIError)] = [
            (401, #"{"success":false,"message":"Unauthenticated"}"#, .unauthenticated),
            (403, #"{"success":false,"message":"Access denied"}"#, .forbidden(message: "Access denied")),
            (403, #"{"error":"upgrade_required","message":"Premium is required."}"#, .upgradeRequired(message: "Premium is required.")),
            (409, #"{"success":false,"message":"The record changed."}"#, .conflict(message: "The record changed.")),
            (500, #"{"success":false,"message":"Internal server error"}"#, .server(status: 500, requestID: "server-request")),
        ]

        for (status, body, expected) in cases {
            let transport = TestHTTPTransport([
                .response(
                    status: status,
                    headers: ["X-Request-ID": "server-request"],
                    body: json(body)
                ),
            ])
            let client = makeClient(transport: transport)
            await expectError(
                expected,
                from: client,
                request: APIRequest<TestValue>(path: "api/example", method: .post)
            )
        }
    }

    @Test
    func mapsRetryAfterSeconds() async {
        let transport = TestHTTPTransport([
            .response(
                status: 429,
                headers: ["Retry-After": "120"],
                body: json(#"{"success":false,"message":"Slow down"}"#)
            ),
        ])
        let client = makeClient(transport: transport)

        await expectError(
            .rateLimited(retryAfter: .seconds(120)),
            from: client,
            request: APIRequest<TestValue>(path: "api/example", method: .get)
        )
    }

    @Test
    func mapsRetryAfterHTTPDateUsingTheInjectedClock() async {
        let transport = TestHTTPTransport([
            .response(
                status: 429,
                headers: ["Retry-After": "Tue, 14 Nov 2023 22:15:20 GMT"],
                body: json(#"{"success":false,"message":"Slow down"}"#)
            ),
        ])
        let client = makeClient(transport: transport)

        await expectError(
            .rateLimited(retryAfter: .seconds(120)),
            from: client,
            request: APIRequest<TestValue>(path: "api/example", method: .get)
        )
    }

    @Test
    func exposesInvalidJSONAsADecodingError() async {
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                headers: ["X-Request-ID": "decode-request"],
                body: Data("not-json".utf8)
            ),
        ])
        let client = makeClient(transport: transport)

        await expectError(
            .decoding(requestID: "decode-request"),
            from: client,
            request: APIRequest<TestValue>(path: "api/example", method: .get)
        )
    }

    @Test
    func mapsConnectivityFailuresToOffline() async {
        let transport = TestHTTPTransport([.offline])
        let client = makeClient(transport: transport)

        await expectError(
            .offline,
            from: client,
            request: APIRequest<TestValue>(path: "api/example", method: .get)
        )
    }

    @Test
    func refreshesAndReplaysAGetExactlyOnceAfter401() async throws {
        let transport = TestHTTPTransport([
            .response(status: 401, body: json(#"{"success":false,"message":"Unauthenticated"}"#)),
            .response(status: 200, body: json(#"{"success":true,"data":{"value":"refreshed"}}"#)),
        ])
        let tokens = TestTokenStore(token: "expired", refreshedToken: "fresh")
        let client = makeClient(transport: transport, tokens: tokens)

        let value = try await client.send(
            APIRequest<TestValue>(path: "api/example", method: .get)
        )

        #expect(value.value == "refreshed")
        #expect(await tokens.refreshCount() == 1)
        let requests = await transport.requests()
        #expect(requests.count == 2)
        #expect(requests[0].value(forHTTPHeaderField: "Authorization") == "Bearer expired")
        #expect(requests[1].value(forHTTPHeaderField: "Authorization") == "Bearer fresh")
        #expect(requests[0].value(forHTTPHeaderField: "X-Request-ID") == "request-123")
        #expect(requests[1].value(forHTTPHeaderField: "X-Request-ID") == "request-123")
    }

    @Test
    func stopsAfterOneRefreshWhenTheReplayedGetIsAlsoUnauthorized() async {
        let transport = TestHTTPTransport([
            .response(status: 401, body: json(#"{"success":false,"message":"Unauthenticated"}"#)),
            .response(status: 401, body: json(#"{"success":false,"message":"Unauthenticated"}"#)),
        ])
        let tokens = TestTokenStore(token: "expired", refreshedToken: "still-expired")
        let client = makeClient(transport: transport, tokens: tokens)

        await expectError(
            .unauthenticated,
            from: client,
            request: APIRequest<TestValue>(path: "api/example", method: .get)
        )

        #expect(await tokens.refreshCount() == 1)
        #expect(await transport.requests().count == 2)
    }

    @Test
    func neverRefreshesOrReplaysWritesAfter401() async {
        for method in [HTTPMethod.post, .patch, .put, .delete] {
            let transport = TestHTTPTransport([
                .response(status: 401, body: json(#"{"success":false,"message":"Unauthenticated"}"#)),
            ])
            let tokens = TestTokenStore(token: "expired", refreshedToken: "fresh")
            let client = makeClient(transport: transport, tokens: tokens)

            await expectError(
                .unauthenticated,
                from: client,
                request: APIRequest<TestValue>(
                    path: "api/example",
                    method: method,
                    body: json(#"{"value":"write"}"#)
                )
            )

            #expect(await tokens.refreshCount() == 0)
            #expect(await transport.requests().count == 1)
        }
    }

    private struct TestValue: Codable, Sendable, Equatable {
        let value: String
    }

    private func makeClient(
        transport: TestHTTPTransport,
        tokens: TestTokenStore = TestTokenStore(token: nil)
    ) -> APIClient {
        APIClient(
            environment: try! AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.2.3",
            build: "45",
            transport: transport,
            tokenProvider: tokens,
            tokenRefresher: tokens,
            requestID: { "request-123" },
            now: { Date(timeIntervalSince1970: 1_700_000_000) }
        )
    }

    private func expectError<Value: Decodable & Sendable>(
        _ expected: APIError,
        from client: APIClient,
        request: APIRequest<Value>
    ) async {
        do {
            _ = try await client.send(request)
            Issue.record("Expected API error: \(expected)")
        } catch let error as APIError {
            #expect(error == expected)
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    private func json(_ value: String) -> Data {
        Data(value.utf8)
    }
}
