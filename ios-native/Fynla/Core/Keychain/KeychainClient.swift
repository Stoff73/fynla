import Foundation
import Security

protocol KeychainClient: Sendable {
    func save(_ credential: NativeRefreshCredential) async throws
    func read() async throws -> NativeRefreshCredential
    func read(
        authentication: LocalAuthenticationAuthorization
    ) async throws -> NativeRefreshCredential
    func delete() async throws
}

enum KeychainStoreAccessibility: Sendable, Equatable {
    case whenUnlockedThisDeviceOnly
}

enum KeychainStoreAccessControl: Sendable, Equatable {
    case biometryCurrentSet
}

struct KeychainStoreProtection: Sendable, Equatable {
    let accessibility: KeychainStoreAccessibility
    let accessControl: KeychainStoreAccessControl
}

struct KeychainStoreDeleteQuery: Sendable, Equatable {
    let service: String
    let synchronizable: Bool
}

struct KeychainStoreItem: Sendable, Equatable {
    let service: String
    let account: String
    let synchronizable: Bool
    let protection: KeychainStoreProtection
    let data: Data
}

enum KeychainStoreReadAuthentication: Sendable, Equatable {
    case userPresence(localizedReason: String)
    case authenticated(LocalAuthenticationAuthorization)
}

struct KeychainStoreReadQuery: Sendable, Equatable {
    let service: String
    let synchronizable: Bool
    let authentication: KeychainStoreReadAuthentication
}

enum KeychainStoreFailure: Sendable, Equatable {
    case status(OSStatus)
}

enum KeychainStoreReadResult: Sendable, Equatable {
    case success(KeychainStoreItem)
    case failure(KeychainStoreFailure)
}

protocol KeychainStoreAdapter: Sendable {
    func delete(_ query: KeychainStoreDeleteQuery) async -> OSStatus
    func add(_ item: KeychainStoreItem) async -> OSStatus
    func read(_ query: KeychainStoreReadQuery) async -> KeychainStoreReadResult
}
