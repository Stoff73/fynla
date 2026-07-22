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
}

protocol FynClient: Sendable {
    func onboardingStatus() async throws -> FynOnboardingStatus
    func listConversations() async throws -> [FynConversationListItem]
    func createConversation(currentRoute: String) async throws -> FynConversationRecord
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
    private let requestID: @Sendable () -> String

    init(
        apiClient: APIClient,
        environment: AppEnvironment,
        version: String,
        build: String,
        transport: any HTTPTransport,
        tokenProvider: any AccessTokenProviding,
        requestID: @escaping @Sendable () -> String
    ) {
        self.apiClient = apiClient
        self.environment = environment
        self.version = version
        self.build = build
        self.transport = transport
        self.tokenProvider = tokenProvider
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

    func loadConversation(id: String) async throws -> FynTranscript {
        try await apiClient.send(
            APIRequest<FynTranscript>(
                path: "api/ai-chat/conversations/\(id)",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
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

    private func open<Body: Encodable & Sendable>(
        path: String,
        body: Body,
        headers: [String: String] = [:]
    ) async throws -> FynStreamResult {
        var requestHeaders = headers
        requestHeaders["Accept"] = "text/event-stream"
        let correlationID = requestID()
        let request = try APIRequest<FynNoResponse>(
            path: path,
            method: .post,
            body: JSONEncoder().encode(body),
            headers: requestHeaders
        ).urlRequest(
            baseURL: environment.apiBaseURL,
            clientName: environment.clientName,
            version: version,
            build: build,
            requestID: correlationID,
            accessToken: await tokenProvider.accessToken()
        )

        do {
            switch try await SSEClient(transport: transport).stream(request) {
            case let .queued(queued):
                return .queued(
                    messageID: String(queued.messageID),
                    queuePosition: queued.queuePosition
                )
            case let .stream(stream):
                return .stream(decoded(stream))
            }
        } catch let error as SSEClientError {
            switch error {
            case .unexpectedStatus(409, _):
                throw FynClientError.busy
            case .unexpectedStatus(403, _):
                throw FynClientError.consentRequired
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
