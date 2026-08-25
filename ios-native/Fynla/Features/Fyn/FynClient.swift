import Foundation

enum FynStreamResult: Sendable {
    case stream(AsyncThrowingStream<FynEvent, Error>)
    case queued(messageID: String, queuePosition: Int?)
}

enum FynClientError: Error, Sendable, Equatable {
    case busy
    case consentRequired
    case rateLimited(retryAfter: Duration?)
    case acceptanceUncertain
    case unexpectedStatus(Int, requestID: String?)
    case invalidEvent
    case contextualResourceUnavailable(SemanticDestination)
    /// F1: the SSE open received a 401 and the one-shot refresh-and-replay
    /// (see `LiveFynClient.open(path:body:headers:)`) could not recover —
    /// either no refresher was configured, the refresh itself failed, or
    /// the replay was rejected too. Distinct from `unexpectedStatus` so
    /// `FynConversationModel` can show "session expired" instead of a
    /// generic failure (F2).
    case authExpired
}

protocol FynClient: Sendable {
    func onboardingStatus() async throws -> FynOnboardingStatus
    func listConversations() async throws -> [FynConversationListItem]
    func createConversation(currentRoute: String) async throws -> FynConversationRecord
    func createContextualConversation(
        _ request: FynContextualConversationRequest
    ) async throws -> FynContextualConversationResponse
    func loadConversation(id: String) async throws -> FynTranscript
    func startOnboarding(from: String?) async throws -> AsyncThrowingStream<FynEvent, Error>
    func sendMessage(
        conversationID: String,
        text: String,
        currentRoute: String,
        idempotencyKey: String
    ) async throws -> FynStreamResult
    func streamQueuedMessage(
        conversationID: String,
        messageID: String,
        currentRoute: String
    ) async throws -> AsyncThrowingStream<FynEvent, Error>
    func performAction(
        conversationID: String,
        action: String
    ) async throws -> AsyncThrowingStream<FynEvent, Error>
}

struct LiveFynClient: FynClient {
    private let apiClient: APIClient
    private let environment: AppEnvironment
    private let version: String
    private let build: String
    private let transport: any HTTPTransport
    private let tokenProvider: any AccessTokenProviding
    private let tokenRefresher: (any AccessTokenRefreshing)?
    private let requestID: @Sendable () -> String

    init(
        apiClient: APIClient,
        environment: AppEnvironment,
        version: String,
        build: String,
        transport: any HTTPTransport,
        tokenProvider: any AccessTokenProviding,
        tokenRefresher: (any AccessTokenRefreshing)? = nil,
        requestID: @escaping @Sendable () -> String
    ) {
        self.apiClient = apiClient
        self.environment = environment
        self.version = version
        self.build = build
        self.transport = transport
        self.tokenProvider = tokenProvider
        self.tokenRefresher = tokenRefresher
        self.requestID = requestID
    }

    func onboardingStatus() async throws -> FynOnboardingStatus {
        try await apiClient.send(
            APIRequest<FynOnboardingStatus>(
                path: "api/ai-chat/onboarding/status",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }

    func listConversations() async throws -> [FynConversationListItem] {
        try await apiClient.send(
            APIRequest<[FynConversationListItem]>(
                path: "api/ai-chat/conversations",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }

    func createConversation(currentRoute: String) async throws -> FynConversationRecord {
        try await apiClient.send(
            APIRequest<FynConversationRecord>(
                path: "api/ai-chat/conversations",
                method: .post,
                body: try JSONEncoder().encode(FynRouteBody(currentRoute: currentRoute))
            )
        )
    }

    func createContextualConversation(
        _ request: FynContextualConversationRequest
    ) async throws -> FynContextualConversationResponse {
        try await apiClient.send(
            APIRequest<FynContextualConversationResponse>(
                path: "api/ai-chat/contextual-conversations",
                method: .post,
                body: try JSONEncoder().encode(request)
            )
        )
    }

    func loadConversation(id: String) async throws -> FynTranscript {
        let response = try await apiClient.sendRawResponse(
            APIRequest<FynTranscript>(
                path: "api/ai-chat/conversations/\(id)",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )

        if (200..<300).contains(response.statusCode) {
            do {
                let envelope = try JSONDecoder().decode(
                    APIEnvelope<FynTranscript>.self,
                    from: response.data
                )
                guard envelope.success else {
                    throw FynClientError.invalidEvent
                }
                return envelope.data
            } catch let error as FynClientError {
                throw error
            } catch {
                throw FynClientError.invalidEvent
            }
        }

        if response.statusCode == 410,
           let unavailable = try? JSONDecoder().decode(
               FynContextualUnavailableEnvelope.self,
               from: response.data
           ),
           unavailable.error == "contextual_resource_unavailable"
        {
            throw FynClientError.contextualResourceUnavailable(
                unavailable.data.fallbackDestination
            )
        }

        if response.statusCode == 401 {
            throw FynClientError.authExpired
        }

        throw FynClientError.unexpectedStatus(
            response.statusCode,
            requestID: response.requestID
        )
    }

    func startOnboarding(from: String?) async throws -> AsyncThrowingStream<FynEvent, Error> {
        let result = try await open(
            path: "api/ai-chat/onboarding/start",
            body: FynOnboardingStartBody(from: from)
        )
        guard case let .stream(stream) = result else {
            throw FynClientError.invalidEvent
        }
        return stream
    }

    func sendMessage(
        conversationID: String,
        text: String,
        currentRoute: String,
        idempotencyKey: String
    ) async throws -> FynStreamResult {
        do {
            return try await open(
                path: "api/ai-chat/conversations/\(conversationID)/messages",
                body: FynMessageBody(message: text, currentRoute: currentRoute),
                headers: ["Idempotency-Key": idempotencyKey]
            )
        } catch let error as FynClientError {
            throw error
        } catch is CancellationError {
            throw CancellationError()
        } catch {
            throw FynClientError.acceptanceUncertain
        }
    }

    func streamQueuedMessage(
        conversationID: String,
        messageID: String,
        currentRoute: String
    ) async throws -> AsyncThrowingStream<FynEvent, Error> {
        let result = try await open(
            path: "api/ai-chat/conversations/\(conversationID)/messages/\(messageID)/stream",
            body: FynRouteBody(currentRoute: currentRoute)
        )
        guard case let .stream(stream) = result else {
            throw FynClientError.invalidEvent
        }
        return stream
    }

    func performAction(
        conversationID: String,
        action: String
    ) async throws -> AsyncThrowingStream<FynEvent, Error> {
        let result = try await open(
            path: "api/ai-chat/conversations/\(conversationID)/action",
            body: FynActionBody(action: action)
        )
        guard case let .stream(stream) = result else {
            throw FynClientError.invalidEvent
        }
        return stream
    }

    /// F1: unlike `APIClient.send`, the SSE open used to build its own
    /// request with a single `tokenProvider.accessToken()` snapshot and had
    /// no path back to the reactive 401 refresh `APIClient` already does
    /// (`APIClient.swift:56-76`). A 401 here now gets the same one-shot
    /// treatment: refresh via the shared `tokenRefresher` (the same
    /// `PrivacyLockController` instance `APIClient` uses — see
    /// `AppDependencies.makeFynClient()`), then replay the SSE open exactly
    /// once with the fresh token. The replay is safe because
    /// `SSEClientError.unexpectedStatus` can only be thrown by
    /// `SSEClient.stream(_:)` before it starts yielding events (the status
    /// check happens before the parsing `AsyncThrowingStream` body runs),
    /// so catching it here always means "no event has been consumed yet" —
    /// never a mid-stream retry. If the refresh or the replay fails, that
    /// failure surfaces as `FynClientError.authExpired` (F2) instead of the
    /// generic `unexpectedStatus`.
    private func open<Body: Encodable & Sendable>(
        path: String,
        body: Body,
        headers: [String: String] = [:]
    ) async throws -> FynStreamResult {
        var requestHeaders = headers
        requestHeaders["Accept"] = "text/event-stream"
        let correlationID = requestID()
        let bodyData = try JSONEncoder().encode(body)

        do {
            let request = try streamRequest(
                path: path,
                bodyData: bodyData,
                headers: requestHeaders,
                correlationID: correlationID,
                accessToken: await tokenProvider.accessToken()
            )
            return try await performStream(request)
        } catch let error as SSEClientError {
            switch error {
            case .unexpectedStatus(409, _):
                throw FynClientError.busy
            case .unexpectedStatus(403, _):
                throw FynClientError.consentRequired
            case .unexpectedStatus(401, _):
                return try await replayAfterRefresh(
                    path: path,
                    bodyData: bodyData,
                    headers: requestHeaders,
                    correlationID: correlationID
                )
            case let .rateLimited(seconds, _):
                throw FynClientError.rateLimited(
                    retryAfter: seconds.map(Duration.seconds)
                )
            case let .unexpectedStatus(status, requestID):
                throw FynClientError.unexpectedStatus(status, requestID: requestID)
            default:
                throw error
            }
        }
    }

    private func replayAfterRefresh(
        path: String,
        bodyData: Data,
        headers: [String: String],
        correlationID: String
    ) async throws -> FynStreamResult {
        guard let tokenRefresher else {
            throw FynClientError.authExpired
        }

        let refreshedToken: String?
        do {
            refreshedToken = try await tokenRefresher.refreshAccessToken()
        } catch {
            throw FynClientError.authExpired
        }
        guard let refreshedToken else {
            throw FynClientError.authExpired
        }

        do {
            let request = try streamRequest(
                path: path,
                bodyData: bodyData,
                headers: headers,
                correlationID: correlationID,
                accessToken: refreshedToken
            )
            return try await performStream(request)
        } catch {
            throw FynClientError.authExpired
        }
    }

    private func streamRequest(
        path: String,
        bodyData: Data,
        headers: [String: String],
        correlationID: String,
        accessToken: String?
    ) throws -> URLRequest {
        try APIRequest<FynNoResponse>(
            path: path,
            method: .post,
            body: bodyData,
            headers: headers
        ).urlRequest(
            baseURL: environment.apiBaseURL,
            clientName: environment.clientName,
            version: version,
            build: build,
            requestID: correlationID,
            accessToken: accessToken
        )
    }

    private func performStream(_ request: URLRequest) async throws -> FynStreamResult {
        switch try await SSEClient(transport: transport).stream(request) {
        case let .queued(queued):
            return .queued(
                messageID: String(queued.messageID),
                queuePosition: queued.queuePosition
            )
        case let .stream(stream):
            return .stream(decoded(stream))
        }
    }

    private func decoded(
        _ source: AsyncThrowingStream<SSEEvent, Error>
    ) -> AsyncThrowingStream<FynEvent, Error> {
        AsyncThrowingStream { continuation in
            let task = Task {
                do {
                    let decoder = FynEventDecoder()
                    for try await event in source {
                        try Task.checkCancellation()
                        continuation.yield(try decoder.decode(event))
                    }
                    continuation.finish()
                } catch is CancellationError {
                    continuation.finish()
                } catch {
                    continuation.finish(throwing: error)
                }
            }
            continuation.onTermination = { _ in task.cancel() }
        }
    }
}

private struct FynContextualUnavailableEnvelope: Decodable {
    struct Payload: Decodable {
        let fallbackDestination: SemanticDestination

        private enum CodingKeys: String, CodingKey {
            case fallbackDestination = "fallback_destination"
        }
    }

    let error: String
    let data: Payload
}

private struct FynRouteBody: Encodable, Sendable {
    let currentRoute: String

    private enum CodingKeys: String, CodingKey {
        case currentRoute = "current_route"
    }
}

private struct FynOnboardingStartBody: Encodable, Sendable {
    let from: String?
}

private struct FynMessageBody: Encodable, Sendable {
    let message: String
    let currentRoute: String

    private enum CodingKeys: String, CodingKey {
        case message
        case currentRoute = "current_route"
    }
}

private struct FynActionBody: Encodable, Sendable {
    let action: String
}

private struct FynNoResponse: Decodable, Sendable {}
