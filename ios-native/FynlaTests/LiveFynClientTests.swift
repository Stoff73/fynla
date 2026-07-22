import Foundation
import Testing
@testable import Fynla

// F1: the SSE open used to build its own request with a single
// `tokenProvider.accessToken()` snapshot and had no path back to the
// reactive 401 refresh `APIClient` already does. These tests exercise the
// real HTTP-level retry in `LiveFynClient.open(path:body:headers:)` — a
// `ScriptedFynClient` (used elsewhere in `FynConversationModelTests`)
// bypasses `LiveFynClient` entirely, so it cannot cover the actual
// refresh-and-replay wiring.
@Suite("Live Fyn client")
struct LiveFynClientTests {
    @Test
    func refreshesAndReplaysTheSSEOpenExactlyOnceAfter401() async throws {
        let transport = SequencedSSETransport([
            .init(status: 401, body: Data()),
            .init(
                status: 200,
                body: Data(
                    """
                    event: fyn
                    data: {"type":"content","text":"Hello again."}

                    event: fyn
                    data: {"type":"done","message_id":"701"}

                    """.utf8
                )
            ),
        ])
        let tokens = TestTokenStore(token: "expired", refreshedToken: "fresh")
        let client = makeClient(transport: transport, tokens: tokens)

        let result = try await client.sendMessage(
            conversationID: "321",
            text: "Hello",
            currentRoute: "/dashboard",
            idempotencyKey: "gesture-1"
        )

        guard case let .stream(stream) = result else {
            Issue.record("Expected a stream result")
            return
        }
        var events: [FynEvent] = []
        for try await event in stream {
            events.append(event)
        }

        #expect(events == [.text("Hello again."), .done(messageID: "701")])
        #expect(await tokens.refreshCount() == 1)
        let requests = await transport.requests()
        #expect(requests.count == 2)
        #expect(requests[0].value(forHTTPHeaderField: "Authorization") == "Bearer expired")
        #expect(requests[1].value(forHTTPHeaderField: "Authorization") == "Bearer fresh")
    }

    @Test
    func surfacesAuthExpiredWhenTheRefreshItselfFailsAfter401() async {
        let transport = SequencedSSETransport([
            .init(status: 401, body: Data()),
        ])
        let tokens = FailingTokenRefresher(token: "expired")
        let client = makeClient(transport: transport, tokens: tokens)

        await expectAuthExpired(from: client)

        #expect(await transport.requests().count == 1)
    }

    @Test
    func surfacesAuthExpiredWhenNoRefresherIsConfigured() async {
        let transport = SequencedSSETransport([
            .init(status: 401, body: Data()),
        ])
        let tokens = TestTokenStore(token: "expired")
        let client = LiveFynClient(
            apiClient: makeAPIClient(tokens: tokens),
            environment: environment(),
            version: "1.2.3",
            build: "45",
            transport: transport,
            tokenProvider: tokens,
            tokenRefresher: nil,
            requestID: { "corr-1" }
        )

        await expectAuthExpired(from: client)

        #expect(await transport.requests().count == 1)
    }

    @Test
    func surfacesAuthExpiredWhenTheReplayIsAlsoUnauthorized() async {
        let transport = SequencedSSETransport([
            .init(status: 401, body: Data()),
            .init(status: 401, body: Data()),
        ])
        let tokens = TestTokenStore(token: "expired", refreshedToken: "still-expired")
        let client = makeClient(transport: transport, tokens: tokens)

        await expectAuthExpired(from: client)

        #expect(await tokens.refreshCount() == 1)
        #expect(await transport.requests().count == 2)
    }

    private func expectAuthExpired(from client: LiveFynClient) async {
        do {
            _ = try await client.sendMessage(
                conversationID: "321",
                text: "Hello",
                currentRoute: "/dashboard",
                idempotencyKey: "gesture-2"
            )
            Issue.record("Expected FynClientError.authExpired")
        } catch let error as FynClientError {
            #expect(error == .authExpired)
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }

    private func environment() -> AppEnvironment {
        try! AppEnvironment.values([
            "FYNLA_ENVIRONMENT": "staging",
            "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
            "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
        ])
    }

    private func makeAPIClient(
        tokens: any (AccessTokenProviding & AccessTokenRefreshing)
    ) -> APIClient {
        APIClient(
            environment: environment(),
            version: "1.2.3",
            build: "45",
            transport: TestHTTPTransport([]),
            tokenProvider: tokens,
            tokenRefresher: tokens,
            requestID: { "corr-1" }
        )
    }

    private func makeClient(
        transport: SequencedSSETransport,
        tokens: any (AccessTokenProviding & AccessTokenRefreshing)
    ) -> LiveFynClient {
        LiveFynClient(
            apiClient: makeAPIClient(tokens: tokens),
            environment: environment(),
            version: "1.2.3",
            build: "45",
            transport: transport,
            tokenProvider: tokens,
            tokenRefresher: tokens,
            requestID: { "corr-1" }
        )
    }
}

/// A queue of whole-response byte-stream stubs, one per `byteStream(for:)`
/// call — unlike `TestSSETransport` (`SSEClientTests.swift`), which only
/// ever serves a single fixed response, this lets a test script a 401
/// followed by a 200 to exercise `LiveFynClient`'s replay-after-refresh.
private actor SequencedSSETransport: HTTPTransport {
    struct Stub: Sendable {
        let status: Int
        let headers: [String: String]
        let body: Data

        init(status: Int, headers: [String: String] = [:], body: Data) {
            self.status = status
            self.headers = headers
            self.body = body
        }
    }

    private var stubs: [Stub]
    private var capturedRequests: [URLRequest] = []

    init(_ stubs: [Stub]) {
        self.stubs = stubs
    }

    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        throw URLError(.unsupportedURL)
    }

    func byteStream(for request: URLRequest) async throws -> HTTPByteStream {
        capturedRequests.append(request)
        guard !stubs.isEmpty else { throw URLError(.badServerResponse) }
        let stub = stubs.removeFirst()
        let response = HTTPURLResponse(
            url: request.url ?? URL(string: "https://example.test")!,
            statusCode: stub.status,
            httpVersion: "HTTP/1.1",
            headerFields: stub.headers
        )!
        let body = stub.body
        let bytes = AsyncThrowingStream<UInt8, Error> { continuation in
            for byte in body {
                continuation.yield(byte)
            }
            continuation.finish()
        }
        return HTTPByteStream(response: response, bytes: bytes)
    }

    func requests() -> [URLRequest] {
        capturedRequests
    }
}

private actor FailingTokenRefresher: AccessTokenProviding, AccessTokenRefreshing {
    private let token: String?

    init(token: String?) {
        self.token = token
    }

    func accessToken() async -> String? {
        token
    }

    func refreshAccessToken() async throws -> String? {
        throw PrivacyLockControllerError.fullLoginRequired
    }
}
