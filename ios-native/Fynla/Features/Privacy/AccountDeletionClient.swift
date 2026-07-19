import Foundation

protocol AccountDeletionClient: Sendable {
    func initiate() async throws -> AccountDeletionInitiation
    func verify(
        sessionToken: String,
        code: String
    ) async throws -> AccountDeletionVerification
    func resend(sessionToken: String) async throws -> AccountDeletionMessage
    func execute(
        sessionToken: String,
        confirmation: String
    ) async throws -> AccountDeletionResult
    func cancelScheduled() async throws -> AccountDeletionMessage
}

struct LiveAccountDeletionClient: AccountDeletionClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func initiate() async throws -> AccountDeletionInitiation {
        try await send(
            AccountDeletionInitiation.self,
            path: "api/auth/gdpr/erasure/initiate",
            body: InitiationBody(type: "account")
        )
    }

    func verify(
        sessionToken: String,
        code: String
    ) async throws -> AccountDeletionVerification {
        try await send(
            AccountDeletionVerification.self,
            path: "api/auth/gdpr/erasure/verify",
            body: VerificationBody(sessionToken: sessionToken, code: code)
        )
    }

    func resend(sessionToken: String) async throws -> AccountDeletionMessage {
        try await send(
            AccountDeletionMessage.self,
            path: "api/auth/gdpr/erasure/resend-code",
            body: SessionBody(sessionToken: sessionToken)
        )
    }

    func execute(
        sessionToken: String,
        confirmation: String
    ) async throws -> AccountDeletionResult {
        try await send(
            AccountDeletionResult.self,
            path: "api/auth/gdpr/erasure/execute",
            body: ExecutionBody(
                sessionToken: sessionToken,
                confirmation: confirmation
            )
        )
    }

    func cancelScheduled() async throws -> AccountDeletionMessage {
        try await send(
            AccountDeletionMessage.self,
            request: APIRequest<EmptyAccountDeletionResponse>(
                path: "api/auth/gdpr/erasure/cancel-scheduled",
                method: .post,
                responseDecoding: .raw
            )
        )
    }

    private func send<Value, Body>(
        _ type: Value.Type,
        path: String,
        body: Body
    ) async throws -> Value
    where Value: Decodable & Sendable, Body: Encodable {
        let data = try JSONEncoder().encode(body)
        return try await send(
            type,
            request: APIRequest<EmptyAccountDeletionResponse>(
                path: path,
                method: .post,
                body: data,
                responseDecoding: .raw
            )
        )
    }

    private func send<Value>(
        _ type: Value.Type,
        request: APIRequest<EmptyAccountDeletionResponse>
    ) async throws -> Value
    where Value: Decodable & Sendable {
        let response = try await apiClient.sendRawResponse(request)
        let status = try? JSONDecoder().decode(
            AccountDeletionResponseStatus.self,
            from: response.data
        )
        guard (200..<300).contains(response.statusCode), status?.success == true else {
            if let message = status?.message, !message.isEmpty {
                throw AccountDeletionClientError.rejected(message: message)
            }
            throw AccountDeletionClientError.invalidResponse
        }
        do {
            return try JSONDecoder().decode(type, from: response.data)
        } catch {
            throw AccountDeletionClientError.invalidResponse
        }
    }
}

private struct AccountDeletionResponseStatus: Decodable {
    let success: Bool
    let message: String?
}

private struct InitiationBody: Encodable {
    let type: String
}

private struct VerificationBody: Encodable {
    let sessionToken: String
    let code: String

    private enum CodingKeys: String, CodingKey {
        case sessionToken = "session_token"
        case code
    }
}

private struct SessionBody: Encodable {
    let sessionToken: String

    private enum CodingKeys: String, CodingKey {
        case sessionToken = "session_token"
    }
}

private struct ExecutionBody: Encodable {
    let sessionToken: String
    let confirmation: String

    private enum CodingKeys: String, CodingKey {
        case sessionToken = "session_token"
        case confirmation
    }
}

private struct EmptyAccountDeletionResponse: Decodable, Sendable {}
