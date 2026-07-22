struct NativeUpdateRequirement: Sendable, Equatable {
    let message: String
    let minimumVersion: String
    let minimumBuild: Int
    let appStoreURL: String
}

enum APIError: Error, Sendable, Equatable {
    case validation([String: [String]])
    case unauthenticated
    case forbidden(message: String?)
    case upgradeRequired(message: String)
    case nativeUpdateRequired(NativeUpdateRequirement)
    case rateLimited(retryAfter: Duration?)
    case conflict(message: String?)
    case offline
    case server(status: Int, requestID: String?)
    case decoding(requestID: String?)
}
