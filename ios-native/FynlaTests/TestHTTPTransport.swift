import Foundation
@testable import Fynla

actor TestHTTPTransport: HTTPTransport {
    enum Stub: Sendable {
        case response(status: Int, headers: [String: String] = [:], body: Data)
        case offline
    }

    private var stubs: [Stub]
    private var capturedRequests: [URLRequest] = []

    init(_ stubs: [Stub]) {
        self.stubs = stubs
    }

    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        capturedRequests.append(request)

        guard !stubs.isEmpty else {
            throw URLError(.badServerResponse)
        }

        switch stubs.removeFirst() {
        case let .response(status, headers, body):
            let response = HTTPURLResponse(
                url: request.url ?? URL(string: "https://example.test")!,
                statusCode: status,
                httpVersion: "HTTP/1.1",
                headerFields: headers
            )!
            return (body, response)
        case .offline:
            throw URLError(.notConnectedToInternet)
        }
    }

    func byteStream(for request: URLRequest) async throws -> HTTPByteStream {
        throw URLError(.unsupportedURL)
    }

    func requests() -> [URLRequest] {
        capturedRequests
    }
}

actor TestTokenStore: AccessTokenProviding, AccessTokenRefreshing {
    private var token: String?
    private let refreshedToken: String?
    private var refreshes = 0

    init(token: String?, refreshedToken: String? = nil) {
        self.token = token
        self.refreshedToken = refreshedToken
    }

    func accessToken() async -> String? {
        token
    }

    func refreshAccessToken() async throws -> String? {
        refreshes += 1
        token = refreshedToken
        return refreshedToken
    }

    func refreshCount() -> Int {
        refreshes
    }
}
