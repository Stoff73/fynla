import Security

enum KeychainError: Error, Sendable, Equatable {
    case itemNotFound
    case interactionCancelled
    case authenticationFailed
    case invalidStoredCredential
    case unexpectedStatus(OSStatus)
}
