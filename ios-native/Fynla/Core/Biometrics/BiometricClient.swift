enum BiometryType: Sendable, Equatable {
    case none
    case touchID
    case faceID
    case faceIDUnavailable
}

enum BiometricError: Error, Sendable, Equatable {
    case unavailable
    case cancelled
    case authenticationFailed
    case lockout
}

protocol BiometricClient: Sendable {
    func biometryType() async -> BiometryType
    func authenticate(reason: String) async throws -> LocalAuthenticationAuthorization
}
