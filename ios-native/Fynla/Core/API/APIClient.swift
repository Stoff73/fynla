import Foundation

protocol AccessTokenProviding: Sendable {
    func accessToken() async -> String?
}

protocol AccessTokenRefreshing: Sendable {
    func refreshAccessToken() async throws -> String?
}

private struct EmptyAccessTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? {
        nil
    }
}

actor APIClient {
    private let environment: AppEnvironment
    private let version: String
    private let build: String
    private let transport: any HTTPTransport
    private let tokenProvider: any AccessTokenProviding
    private let tokenRefresher: (any AccessTokenRefreshing)?
    private let requestID: @Sendable () -> String
    private let now: @Sendable () -> Date

    init(
        environment: AppEnvironment,
        version: String,
        build: String,
        transport: (any HTTPTransport)? = nil,
        tokenProvider: any AccessTokenProviding = EmptyAccessTokenProvider(),
        tokenRefresher: (any AccessTokenRefreshing)? = nil,
        requestID: @escaping @Sendable () -> String = { UUID().uuidString },
        now: @escaping @Sendable () -> Date = { Date() }
    ) {
        self.environment = environment
        self.version = version
        self.build = build
        self.transport = transport ?? URLSessionHTTPTransport.ephemeral()
        self.tokenProvider = tokenProvider
        self.tokenRefresher = tokenRefresher
        self.requestID = requestID
        self.now = now
    }

    func send<Value>(_ request: APIRequest<Value>) async throws -> Value {
        let correlationID = requestID()
        let accessToken = await tokenProvider.accessToken()
        var result = try await perform(
            request,
            accessToken: accessToken,
            correlationID: correlationID
        )

        if result.response.statusCode == 401,
           request.method.permitsAuthenticationReplay,
           accessToken != nil,
           let tokenRefresher,
           let refreshedToken = try await tokenRefresher.refreshAccessToken()
        {
            result = try await perform(
                request,
                accessToken: refreshedToken,
                correlationID: correlationID
            )
        }

        return try decode(
            Value.self,
            data: result.data,
            response: result.response,
            correlationID: correlationID
        )
    }

    private func perform<Value>(
        _ request: APIRequest<Value>,
        accessToken: String?,
        correlationID: String
    ) async throws -> (data: Data, response: HTTPURLResponse) {
        let urlRequest = try request.urlRequest(
            baseURL: environment.apiBaseURL,
            clientName: environment.clientName,
            version: version,
            build: build,
            requestID: correlationID,
            accessToken: accessToken
        )

        do {
            return try await transport.data(for: urlRequest)
        } catch let error as URLError where error.code.isOffline {
            throw APIError.offline
        }
    }

    private func decode<Value: Decodable & Sendable>(
        _ type: Value.Type,
        data: Data,
        response: HTTPURLResponse,
        correlationID: String
    ) throws -> Value {
        let responseRequestID = response.value(forHTTPHeaderField: "X-Request-ID")
            ?? correlationID

        guard (200..<300).contains(response.statusCode) else {
            throw mapError(
                data: data,
                response: response,
                requestID: responseRequestID
            )
        }

        do {
            let envelope = try JSONDecoder().decode(APIEnvelope<Value>.self, from: data)
            guard envelope.success else {
                throw APIError.decoding(requestID: responseRequestID)
            }
            return envelope.data
        } catch let error as APIError {
            throw error
        } catch {
            throw APIError.decoding(requestID: responseRequestID)
        }
    }

    private func mapError(
        data: Data,
        response: HTTPURLResponse,
        requestID: String?
    ) -> APIError {
        let body = try? JSONDecoder().decode(ErrorEnvelope.self, from: data)

        switch response.statusCode {
        case 401:
            return .unauthenticated
        case 403 where body?.error == "upgrade_required":
            return .upgradeRequired(message: body?.message ?? "Upgrade required.")
        case 403:
            return .forbidden(message: body?.message)
        case 409:
            return .conflict(message: body?.message)
        case 422:
            return .validation(body?.errors ?? [:])
        case 426:
            return .upgradeRequired(message: body?.message ?? "Upgrade required.")
        case 429:
            return .rateLimited(retryAfter: retryAfter(from: response))
        default:
            return .server(status: response.statusCode, requestID: requestID)
        }
    }

    private func retryAfter(from response: HTTPURLResponse) -> Duration? {
        guard let value = response.value(forHTTPHeaderField: "Retry-After") else {
            return nil
        }

        if let seconds = Double(value), seconds >= 0 {
            return .seconds(seconds)
        }

        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = TimeZone(secondsFromGMT: 0)
        formatter.dateFormat = "EEE',' dd MMM yyyy HH':'mm':'ss z"

        guard let date = formatter.date(from: value) else {
            return nil
        }

        return .seconds(max(0, date.timeIntervalSince(now())))
    }
}

private struct ErrorEnvelope: Decodable, Sendable {
    let error: String?
    let message: String?
    let errors: [String: [String]]?
}

private extension URLError.Code {
    var isOffline: Bool {
        switch self {
        case .notConnectedToInternet,
             .networkConnectionLost,
             .cannotConnectToHost,
             .cannotFindHost,
             .dnsLookupFailed,
             .timedOut:
            true
        default:
            false
        }
    }
}
