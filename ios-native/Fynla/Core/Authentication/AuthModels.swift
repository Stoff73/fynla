import Foundation

struct RegistrationInput: Encodable, Sendable, Equatable {
    let firstName: String
    let middleName: String?
    let surname: String
    let email: String
    let password: String
    let passwordConfirmation: String

    enum CodingKeys: String, CodingKey {
        case firstName = "first_name"
        case middleName = "middle_name"
        case surname
        case email
        case password
        case passwordConfirmation = "password_confirmation"
    }
}

struct RegistrationVerificationInput: Encodable, Sendable, Equatable {
    let code: String
    let pendingID: Int

    enum CodingKeys: String, CodingKey {
        case code
        case pendingID = "pending_id"
    }
}

struct RegistrationChallenge: Sendable, Equatable {
    let pendingID: Int
    let maskedEmail: String
}

struct RestorationChallenge: Sendable, Equatable {
    let token: String
    let deletedAt: String
    let deletionReason: String?
    let deletionSource: String?
    let firstName: String?
}

enum LoginOutcome: Sendable, Equatable {
    case verification(challengeToken: String, maskedEmail: String)
    case multiFactor(token: String, maskedEmail: String)
    case restorable(RestorationChallenge)
    case authenticated(bootstrapAccessToken: String)
}

struct LoginCompletion: Sendable, Equatable {
    let outcome: LoginOutcome
    let mustChangePassword: Bool?
}

struct BootstrapAuthentication: Sendable, Equatable {
    let bootstrapAccessToken: String
    let mustChangePassword: Bool?
}

struct NativeCredentials: Sendable, Equatable {
    let accessToken: String
    let accessExpiresAt: String
    let refreshToken: String
    let refreshExpiresAt: String
    let absoluteExpiresAt: String
    let sessionID: String

    var refreshCredential: NativeRefreshCredential {
        NativeRefreshCredential(
            token: refreshToken,
            expiresAt: refreshExpiresAt,
            absoluteExpiresAt: absoluteExpiresAt,
            sessionID: sessionID
        )
    }
}

struct NativeRefreshCredential: Sendable, Equatable {
    let token: String
    let expiresAt: String
    let absoluteExpiresAt: String
    let sessionID: String
}

struct AuthenticatedUser: Decodable, Sendable, Equatable {
    let id: Int
    let firstName: String?
    let surname: String?
    let name: String?
    let email: String

    enum CodingKeys: String, CodingKey {
        case id
        case firstName = "first_name"
        case surname
        case name
        case email
    }
}

enum AuthError: Error, Sendable, Equatable {
    case decoding
    case locked(message: String, remainingSeconds: Int?)
    case validation(message: String?, errors: [String: [String]])
    case resendExhausted(message: String)
    case rateLimited(message: String?)
    case unauthenticated(message: String?)
    case notFound(message: String?)
    case offline
    case transport
    case server(status: Int)
}

enum AuthResponseDecoder {
    static func registrationChallenge(from data: Data) throws -> RegistrationChallenge {
        let response = try decode(AuthResponse.self, from: data)
        guard response.success == true,
              response.requiresVerification == true,
              response.requiresMFA != true,
              let pendingID = response.data?.pendingID,
              let email = nonempty(response.data?.email)
        else {
            throw AuthError.decoding
        }

        return RegistrationChallenge(pendingID: pendingID, maskedEmail: email)
    }

    static func login(from data: Data) throws -> LoginCompletion {
        let response = try decode(AuthResponse.self, from: data)
        let branchCount = [
            response.requiresVerification == true,
            response.requiresMFA == true,
            response.accountDeletedRestorable == true,
            nonempty(response.data?.accessToken) != nil,
        ].filter { $0 }.count

        guard branchCount == 1 else {
            throw AuthError.decoding
        }

        if response.accountDeletedRestorable == true,
           let token = nonempty(response.restorationToken),
           let deletedAt = nonempty(response.deletedAt)
        {
            return LoginCompletion(
                outcome: .restorable(
                    RestorationChallenge(
                        token: token,
                        deletedAt: deletedAt,
                        deletionReason: response.deletionReason,
                        deletionSource: response.deletionSource,
                        firstName: response.firstName
                    )
                ),
                mustChangePassword: nil
            )
        }

        guard response.success == true else {
            throw AuthError.decoding
        }

        if response.requiresVerification == true,
           let token = nonempty(response.data?.challengeToken),
           let email = nonempty(response.data?.email)
        {
            return LoginCompletion(
                outcome: .verification(challengeToken: token, maskedEmail: email),
                mustChangePassword: nil
            )
        }

        if response.requiresMFA == true,
           let token = nonempty(response.data?.mfaToken),
           let email = nonempty(response.data?.email)
        {
            return LoginCompletion(
                outcome: .multiFactor(token: token, maskedEmail: email),
                mustChangePassword: nil
            )
        }

        if let token = nonempty(response.data?.accessToken) {
            return LoginCompletion(
                outcome: .authenticated(bootstrapAccessToken: token),
                mustChangePassword: response.data?.mustChangePassword
            )
        }

        throw AuthError.decoding
    }

    static func authentication(from data: Data) throws -> BootstrapAuthentication {
        let response = try decode(AuthResponse.self, from: data)
        guard response.success == true,
              response.requiresVerification != true,
              response.requiresMFA != true,
              response.accountDeletedRestorable != true,
              let token = nonempty(response.data?.accessToken)
        else {
            throw AuthError.decoding
        }

        return BootstrapAuthentication(
            bootstrapAccessToken: token,
            mustChangePassword: response.data?.mustChangePassword
        )
    }

    static func nativeCredentials(from data: Data) throws -> NativeCredentials {
        let object = try JSONSerialization.jsonObject(with: data)
        guard let root = object as? [String: Any],
              root["success"] as? Bool == true,
              Set(root.keys) == ["success", "data"],
              let payload = root["data"] as? [String: Any],
              Set(payload.keys) == [
                  "access_token",
                  "access_expires_at",
                  "refresh_token",
                  "refresh_expires_at",
                  "absolute_expires_at",
                  "session_id",
              ]
        else {
            throw AuthError.decoding
        }

        let response = try decode(NativeCredentialsResponse.self, from: data)
        guard let accessToken = nonempty(response.data.accessToken),
              let accessExpiresAt = nonempty(response.data.accessExpiresAt),
              let refreshToken = nonempty(response.data.refreshToken),
              let refreshExpiresAt = nonempty(response.data.refreshExpiresAt),
              let absoluteExpiresAt = nonempty(response.data.absoluteExpiresAt),
              let sessionID = nonempty(response.data.sessionID)
        else {
            throw AuthError.decoding
        }

        return NativeCredentials(
            accessToken: accessToken,
            accessExpiresAt: accessExpiresAt,
            refreshToken: refreshToken,
            refreshExpiresAt: refreshExpiresAt,
            absoluteExpiresAt: absoluteExpiresAt,
            sessionID: sessionID
        )
    }

    static func currentUser(from data: Data) throws -> AuthenticatedUser {
        let response = try decode(CurrentUserResponse.self, from: data)
        guard response.success else { throw AuthError.decoding }
        return response.data.user
    }

    static func resendMessage(from data: Data) throws -> String {
        let response = try decode(AuthResponse.self, from: data)
        guard response.success == true,
              let message = nonempty(response.message)
        else {
            throw AuthError.decoding
        }
        return message
    }

    static func error(from data: Data, status: Int) -> AuthError {
        let response = try? JSONDecoder().decode(AuthErrorResponse.self, from: data)

        switch status {
        case 401:
            return .unauthenticated(message: response?.message)
        case 422:
            return .validation(
                message: response?.message,
                errors: response?.errors ?? [:]
            )
        case 423 where response?.locked == true:
            return .locked(
                message: response?.message ?? "Authentication is temporarily locked.",
                remainingSeconds: response?.remainingSeconds
            )
        case 429 where response?.canResend == false:
            return .resendExhausted(
                message: response?.message ?? "Maximum resend limit reached."
            )
        case 429:
            return .rateLimited(message: response?.message)
        case 404:
            return .notFound(message: response?.message)
        default:
            return .server(status: status)
        }
    }

    private static func decode<Value: Decodable>(
        _ type: Value.Type,
        from data: Data
    ) throws -> Value {
        do {
            return try JSONDecoder().decode(type, from: data)
        } catch {
            throw AuthError.decoding
        }
    }

    private static func nonempty(_ value: String?) -> String? {
        guard let value, !value.isEmpty else { return nil }
        return value
    }
}

private struct AuthResponse: Decodable {
    let success: Bool?
    let message: String?
    let requiresVerification: Bool?
    let requiresMFA: Bool?
    let accountDeletedRestorable: Bool?
    let deletedAt: String?
    let deletionReason: String?
    let deletionSource: String?
    let firstName: String?
    let restorationToken: String?
    let data: AuthResponseData?

    enum CodingKeys: String, CodingKey {
        case success
        case message
        case requiresVerification = "requires_verification"
        case requiresMFA = "requires_mfa"
        case accountDeletedRestorable = "account_deleted_restorable"
        case deletedAt = "deleted_at"
        case deletionReason = "deletion_reason"
        case deletionSource = "deletion_source"
        case firstName = "first_name"
        case restorationToken = "restoration_token"
        case data
    }
}

private struct AuthResponseData: Decodable {
    let pendingID: Int?
    let email: String?
    let challengeToken: String?
    let mfaToken: String?
    let accessToken: String?
    let mustChangePassword: Bool?

    enum CodingKeys: String, CodingKey {
        case pendingID = "pending_id"
        case email
        case challengeToken = "challenge_token"
        case mfaToken = "mfa_token"
        case accessToken = "access_token"
        case mustChangePassword = "must_change_password"
    }
}

private struct NativeCredentialsResponse: Decodable {
    struct Payload: Decodable {
        let accessToken: String?
        let accessExpiresAt: String?
        let refreshToken: String?
        let refreshExpiresAt: String?
        let absoluteExpiresAt: String?
        let sessionID: String?

        enum CodingKeys: String, CodingKey {
            case accessToken = "access_token"
            case accessExpiresAt = "access_expires_at"
            case refreshToken = "refresh_token"
            case refreshExpiresAt = "refresh_expires_at"
            case absoluteExpiresAt = "absolute_expires_at"
            case sessionID = "session_id"
        }
    }

    let data: Payload
}

private struct CurrentUserResponse: Decodable {
    struct Payload: Decodable {
        let user: AuthenticatedUser
    }

    let success: Bool
    let data: Payload
}

private struct AuthErrorResponse: Decodable {
    let message: String?
    let errors: [String: [String]]?
    let locked: Bool?
    let remainingSeconds: Int?
    let canResend: Bool?

    enum CodingKeys: String, CodingKey {
        case message
        case errors
        case locked
        case remainingSeconds = "remaining_seconds"
        case canResend = "can_resend"
    }
}
