import Foundation

enum AccountDeletionBillingState: Sendable, Equatable {
    case free
    case applePremium
    case webPremium
    case unavailable
}

enum AccountDeletionState: Sendable, Equatable {
    case idle
    case loadingEntitlement
    case appleBillingWarning
    case ready(AccountDeletionBillingState)
    case requestingVerification
    case verificationRequired(usesAuthenticator: Bool)
    case verifying
    case verified
    case executing
    case scheduled(message: String, deletionAt: String?)
    case deleted(message: String)
    case cancelled(message: String)
    case failed(message: String)
}

struct AccountDeletionInitiation: Decodable, Sendable, Equatable {
    let requiresTwoFactor: Bool
    let requiresEmailVerification: Bool
    let sessionToken: String

    init(
        requiresTwoFactor: Bool,
        requiresEmailVerification: Bool,
        sessionToken: String
    ) {
        self.requiresTwoFactor = requiresTwoFactor
        self.requiresEmailVerification = requiresEmailVerification
        self.sessionToken = sessionToken
    }

    private enum CodingKeys: String, CodingKey {
        case requiresTwoFactor = "requires_2fa"
        case requiresEmailVerification = "requires_email_verification"
        case sessionToken = "session_token"
    }
}

struct AccountDeletionVerification: Decodable, Sendable, Equatable {
    let message: String
    let sessionToken: String
    let type: String

    private enum CodingKeys: String, CodingKey {
        case message
        case sessionToken = "session_token"
        case type
    }
}

struct AccountDeletionMessage: Decodable, Sendable, Equatable {
    let message: String
}

struct AccountDeletionResult: Decodable, Sendable, Equatable {
    let message: String
    let type: String
    let logoutRequired: Bool
    let scheduledDeletionAt: String?

    init(
        message: String,
        type: String,
        logoutRequired: Bool,
        scheduledDeletionAt: String?
    ) {
        self.message = message
        self.type = type
        self.logoutRequired = logoutRequired
        self.scheduledDeletionAt = scheduledDeletionAt
    }

    private enum CodingKeys: String, CodingKey {
        case message
        case type
        case logoutRequired = "logout_required"
        case scheduledDeletionAt = "scheduled_deletion_at"
    }
}

enum AccountDeletionClientError: Error, Sendable, Equatable {
    case rejected(message: String)
    case invalidResponse
}
