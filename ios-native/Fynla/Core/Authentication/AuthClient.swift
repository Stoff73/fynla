import Foundation

protocol AuthClient: Sendable {
    func register(_ input: RegistrationInput) async throws -> RegistrationChallenge
    func verifyRegistration(_ input: RegistrationVerificationInput) async throws -> String
    func resendRegistration(pendingID: Int) async throws -> String
    func login(email: String, password: String) async throws -> LoginOutcome
    func verifyLogin(code: String, challengeToken: String) async throws -> String
    func verifyMFA(code: String, token: String) async throws -> String
    func useRecoveryCode(_ code: String, token: String) async throws -> String
    func exchange(bootstrapToken: String, deviceLabel: String) async throws -> NativeCredentials
}

protocol AuthCompletionClient: AuthClient {
    func loginCompletion(email: String, password: String) async throws -> LoginCompletion
    func verifyRegistrationCompletion(
        _ input: RegistrationVerificationInput
    ) async throws -> BootstrapAuthentication
    func verifyLoginCompletion(
        code: String,
        challengeToken: String
    ) async throws -> BootstrapAuthentication
    func verifyMFACompletion(code: String, token: String) async throws -> BootstrapAuthentication
    func useRecoveryCodeCompletion(
        _ code: String,
        token: String
    ) async throws -> BootstrapAuthentication
    func resendLogin(challengeToken: String) async throws -> LoginResendResult
    func checkRestoration(email: String, password: String) async throws -> RestorationSession
    func restoreAccount(
        token: String,
        mfaCode: String?
    ) async throws -> BootstrapAuthentication
}

extension AuthCompletionClient {
    func verifyRegistration(_ input: RegistrationVerificationInput) async throws -> String {
        try await verifyRegistrationCompletion(input).bootstrapAccessToken
    }

    func login(email: String, password: String) async throws -> LoginOutcome {
        try await loginCompletion(email: email, password: password).outcome
    }

    func verifyLogin(code: String, challengeToken: String) async throws -> String {
        try await verifyLoginCompletion(
            code: code,
            challengeToken: challengeToken
        ).bootstrapAccessToken
    }

    func verifyMFA(code: String, token: String) async throws -> String {
        try await verifyMFACompletion(code: code, token: token).bootstrapAccessToken
    }

    func useRecoveryCode(_ code: String, token: String) async throws -> String {
        try await useRecoveryCodeCompletion(code, token: token).bootstrapAccessToken
    }

}

protocol PasswordResetClient: Sendable {
    func requestPasswordReset(email: String) async throws -> PasswordResetRequestResult
    func verifyPasswordResetEmail(
        token: String,
        code: String
    ) async throws -> PasswordResetEmailVerification
    func resendPasswordResetCode(token: String) async throws -> PasswordResetResendResult
    func verifyPasswordResetMFA(
        token: String,
        code: String
    ) async throws -> PasswordResetAuthorization
    func usePasswordResetRecoveryCode(
        token: String,
        recoveryCode: String
    ) async throws -> PasswordResetAuthorization
    func resetPassword(
        token: String,
        password: String,
        confirmation: String
    ) async throws -> String
}

protocol CurrentUserClient: Sendable {
    func currentUser(accessToken: String) async throws -> AuthenticatedUser
}

actor APIAuthClient: AuthCompletionClient, CurrentUserClient, PasswordResetClient {
    private let environment: AppEnvironment
    private let version: String
    private let build: String
    private let transport: any HTTPTransport
    private let requestID: @Sendable () -> String

    init(
        environment: AppEnvironment,
        version: String,
        build: String,
        transport: any HTTPTransport,
        requestID: @escaping @Sendable () -> String = { UUID().uuidString }
    ) {
        self.environment = environment
        self.version = version
        self.build = build
        self.transport = transport
        self.requestID = requestID
    }

    func register(_ input: RegistrationInput) async throws -> RegistrationChallenge {
        let data = try await perform(
            path: "api/auth/register",
            method: .post,
            body: try encode(input)
        )
        return try AuthResponseDecoder.registrationChallenge(from: data)
    }

    func verifyRegistrationCompletion(
        _ input: RegistrationVerificationInput
    ) async throws -> BootstrapAuthentication {
        let data = try await perform(
            path: "api/auth/verify-code",
            method: .post,
            body: try encode(
                VerificationRequest(
                    code: input.code,
                    type: "registration",
                    challengeToken: nil,
                    pendingID: input.pendingID
                )
            )
        )
        return try AuthResponseDecoder.authentication(from: data)
    }

    func resendRegistration(pendingID: Int) async throws -> String {
        let data = try await perform(
            path: "api/auth/resend-code",
            method: .post,
            body: try encode(
                RegistrationResendRequest(
                    type: "registration",
                    pendingID: pendingID
                )
            )
        )
        return try AuthResponseDecoder.resendMessage(from: data)
    }

    func loginCompletion(email: String, password: String) async throws -> LoginCompletion {
        let data = try await perform(
            path: "api/auth/login",
            method: .post,
            body: try encode(LoginRequest(email: email, password: password))
        )
        return try AuthResponseDecoder.login(from: data)
    }

    func verifyLoginCompletion(
        code: String,
        challengeToken: String
    ) async throws -> BootstrapAuthentication {
        let data = try await perform(
            path: "api/auth/verify-code",
            method: .post,
            body: try encode(
                VerificationRequest(
                    code: code,
                    type: "login",
                    challengeToken: challengeToken,
                    pendingID: nil
                )
            )
        )
        return try AuthResponseDecoder.authentication(from: data)
    }

    func verifyMFACompletion(
        code: String,
        token: String
    ) async throws -> BootstrapAuthentication {
        let data = try await perform(
            path: "api/auth/mfa/verify",
            method: .post,
            body: try encode(MFAVerificationRequest(code: code, token: token))
        )
        return try AuthResponseDecoder.authentication(from: data)
    }

    func useRecoveryCodeCompletion(
        _ code: String,
        token: String
    ) async throws -> BootstrapAuthentication {
        let data = try await perform(
            path: "api/auth/mfa/recovery",
            method: .post,
            body: try encode(RecoveryCodeRequest(code: code, token: token))
        )
        return try AuthResponseDecoder.authentication(from: data)
    }

    func resendLogin(challengeToken: String) async throws -> LoginResendResult {
        let data = try await perform(
            path: "api/auth/resend-code",
            method: .post,
            body: try encode(
                LoginResendRequest(
                    type: "login",
                    challengeToken: challengeToken
                )
            )
        )
        return try AuthResponseDecoder.loginResend(from: data)
    }

    func requestPasswordReset(email: String) async throws -> PasswordResetRequestResult {
        let data = try await perform(
            path: "api/auth/password-reset/request",
            method: .post,
            body: try encode(EmailRequest(email: email))
        )
        return try AuthResponseDecoder.passwordResetRequest(from: data)
    }

    func verifyPasswordResetEmail(
        token: String,
        code: String
    ) async throws -> PasswordResetEmailVerification {
        let data = try await perform(
            path: "api/auth/password-reset/verify-email",
            method: .post,
            body: try encode(TokenCodeRequest(token: token, code: code))
        )
        return try AuthResponseDecoder.passwordResetEmailVerification(from: data)
    }

    func resendPasswordResetCode(token: String) async throws -> PasswordResetResendResult {
        let data = try await perform(
            path: "api/auth/password-reset/resend-code",
            method: .post,
            body: try encode(TokenRequest(token: token))
        )
        return try AuthResponseDecoder.passwordResetResend(from: data)
    }

    func verifyPasswordResetMFA(
        token: String,
        code: String
    ) async throws -> PasswordResetAuthorization {
        let data = try await perform(
            path: "api/auth/password-reset/verify-mfa",
            method: .post,
            body: try encode(TokenCodeRequest(token: token, code: code))
        )
        return try AuthResponseDecoder.passwordResetAuthorization(from: data)
    }

    func usePasswordResetRecoveryCode(
        token: String,
        recoveryCode: String
    ) async throws -> PasswordResetAuthorization {
        let data = try await perform(
            path: "api/auth/password-reset/mfa-recovery",
            method: .post,
            body: try encode(
                TokenRecoveryRequest(token: token, recoveryCode: recoveryCode)
            )
        )
        return try AuthResponseDecoder.passwordResetAuthorization(from: data)
    }

    func resetPassword(
        token: String,
        password: String,
        confirmation: String
    ) async throws -> String {
        let data = try await perform(
            path: "api/auth/password-reset/reset",
            method: .post,
            body: try encode(
                PasswordResetRequest(
                    token: token,
                    password: password,
                    passwordConfirmation: confirmation
                )
            )
        )
        return try AuthResponseDecoder.successMessage(from: data)
    }

    func checkRestoration(
        email: String,
        password: String
    ) async throws -> RestorationSession {
        let data = try await perform(
            path: "api/auth/restore/check",
            method: .post,
            body: try encode(LoginRequest(email: email, password: password))
        )
        return try AuthResponseDecoder.restorationSession(from: data)
    }

    func restoreAccount(
        token: String,
        mfaCode: String?
    ) async throws -> BootstrapAuthentication {
        let data = try await perform(
            path: "api/auth/restore",
            method: .post,
            body: try encode(
                RestoreAccountRequest(
                    restorationToken: token,
                    mfaCode: mfaCode
                )
            )
        )
        return try AuthResponseDecoder.restorationAuthentication(from: data)
    }

    func exchange(
        bootstrapToken: String,
        deviceLabel: String
    ) async throws -> NativeCredentials {
        let data = try await perform(
            path: "api/v1/native/auth/session/exchange",
            method: .post,
            body: try encode(DeviceLabelRequest(deviceLabel: deviceLabel)),
            bearer: bootstrapToken
        )
        return try AuthResponseDecoder.nativeCredentials(from: data)
    }

    func currentUser(accessToken: String) async throws -> AuthenticatedUser {
        let data = try await perform(
            path: "api/auth/user",
            method: .get,
            bearer: accessToken
        )
        return try AuthResponseDecoder.currentUser(from: data)
    }

    private func perform(
        path: String,
        method: HTTPMethod,
        body: Data? = nil,
        bearer: String? = nil
    ) async throws -> Data {
        let request = try APIRequest<DiscardedAuthResponse>(
            path: path,
            method: method,
            body: body
        ).urlRequest(
            baseURL: environment.apiBaseURL,
            clientName: environment.clientName,
            version: version,
            build: build,
            requestID: requestID(),
            accessToken: bearer
        )
        let data: Data
        let response: HTTPURLResponse
        do {
            (data, response) = try await transport.data(for: request)
        } catch is CancellationError {
            throw CancellationError()
        } catch let error as URLError where error.code == .cancelled {
            throw CancellationError()
        } catch let error as URLError where error.code.isAuthOffline {
            throw AuthError.offline
        } catch {
            throw AuthError.transport
        }

        guard (200..<300).contains(response.statusCode) else {
            throw AuthResponseDecoder.error(from: data, status: response.statusCode)
        }
        return data
    }

    private func encode<Value: Encodable>(_ value: Value) throws -> Data {
        try JSONEncoder().encode(value)
    }
}

private struct DiscardedAuthResponse: Decodable, Sendable {}

private struct LoginRequest: Encodable {
    let email: String
    let password: String
}

private struct VerificationRequest: Encodable {
    let code: String
    let type: String
    let challengeToken: String?
    let pendingID: Int?

    enum CodingKeys: String, CodingKey {
        case code
        case type
        case challengeToken = "challenge_token"
        case pendingID = "pending_id"
    }
}

private struct RegistrationResendRequest: Encodable {
    let type: String
    let pendingID: Int

    enum CodingKeys: String, CodingKey {
        case type
        case pendingID = "pending_id"
    }
}

private struct LoginResendRequest: Encodable {
    let type: String
    let challengeToken: String

    enum CodingKeys: String, CodingKey {
        case type
        case challengeToken = "challenge_token"
    }
}

private struct EmailRequest: Encodable {
    let email: String
}

private struct TokenRequest: Encodable {
    let token: String
}

private struct TokenCodeRequest: Encodable {
    let token: String
    let code: String
}

private struct TokenRecoveryRequest: Encodable {
    let token: String
    let recoveryCode: String

    enum CodingKeys: String, CodingKey {
        case token
        case recoveryCode = "recovery_code"
    }
}

private struct PasswordResetRequest: Encodable {
    let token: String
    let password: String
    let passwordConfirmation: String

    enum CodingKeys: String, CodingKey {
        case token
        case password
        case passwordConfirmation = "password_confirmation"
    }
}

private struct RestoreAccountRequest: Encodable {
    let restorationToken: String
    let mfaCode: String?

    enum CodingKeys: String, CodingKey {
        case restorationToken = "restoration_token"
        case mfaCode = "mfa_code"
    }
}

private struct MFAVerificationRequest: Encodable {
    let code: String
    let token: String

    enum CodingKeys: String, CodingKey {
        case code
        case token = "mfa_token"
    }
}

private struct RecoveryCodeRequest: Encodable {
    let code: String
    let token: String

    enum CodingKeys: String, CodingKey {
        case code = "recovery_code"
        case token = "mfa_token"
    }
}

private struct DeviceLabelRequest: Encodable {
    let deviceLabel: String

    enum CodingKeys: String, CodingKey {
        case deviceLabel = "device_label"
    }
}

private extension URLError.Code {
    var isAuthOffline: Bool {
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
