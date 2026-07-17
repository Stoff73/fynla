import Foundation
import LocalAuthentication
import Security

actor SystemKeychainClient: KeychainClient {
    private static let service = "org.fynla.app.native-session"
    private static let unlockReason = "Unlock your Fynla session"
    private static let protection = KeychainStoreProtection(
        accessibility: .whenUnlockedThisDeviceOnly,
        accessControl: .biometryCurrentSet
    )

    private let adapter: any KeychainStoreAdapter

    init(adapter: any KeychainStoreAdapter = SystemKeychainStoreAdapter()) {
        self.adapter = adapter
    }

    func save(_ credential: NativeRefreshCredential) async throws {
        let payload: Data
        do {
            payload = try JSONEncoder().encode(StoredCredential(credential))
        } catch {
            throw KeychainError.invalidStoredCredential
        }

        let deleteStatus = await adapter.delete(Self.deleteQuery)
        guard deleteStatus == errSecSuccess || deleteStatus == errSecItemNotFound else {
            throw Self.error(for: deleteStatus)
        }

        let addStatus = await adapter.add(KeychainStoreItem(
            service: Self.service,
            account: credential.sessionID,
            synchronizable: false,
            protection: Self.protection,
            data: payload
        ))
        guard addStatus == errSecSuccess else {
            throw Self.error(for: addStatus)
        }
    }

    func read() async throws -> NativeRefreshCredential {
        let result = await adapter.read(KeychainStoreReadQuery(
            service: Self.service,
            synchronizable: false,
            authentication: .userPresence(localizedReason: Self.unlockReason)
        ))

        switch result {
        case let .success(item):
            do {
                let stored = try JSONDecoder().decode(StoredCredential.self, from: item.data)
                guard stored.sessionID == item.account else {
                    throw KeychainError.invalidStoredCredential
                }
                return stored.credential
            } catch let error as KeychainError {
                throw error
            } catch {
                throw KeychainError.invalidStoredCredential
            }
        case let .failure(.status(status)):
            throw Self.error(for: status)
        }
    }

    func delete() async throws {
        let status = await adapter.delete(Self.deleteQuery)
        guard status == errSecSuccess else {
            throw Self.error(for: status)
        }
    }

    private static var deleteQuery: KeychainStoreDeleteQuery {
        KeychainStoreDeleteQuery(service: service, synchronizable: false)
    }

    private static func error(for status: OSStatus) -> KeychainError {
        switch status {
        case errSecItemNotFound:
            .itemNotFound
        case errSecUserCanceled:
            .interactionCancelled
        case errSecAuthFailed:
            .authenticationFailed
        default:
            .unexpectedStatus(status)
        }
    }
}

private struct StoredCredential: Codable {
    let token: String
    let expiresAt: String
    let absoluteExpiresAt: String
    let sessionID: String

    init(_ credential: NativeRefreshCredential) {
        token = credential.token
        expiresAt = credential.expiresAt
        absoluteExpiresAt = credential.absoluteExpiresAt
        sessionID = credential.sessionID
    }

    var credential: NativeRefreshCredential {
        NativeRefreshCredential(
            token: token,
            expiresAt: expiresAt,
            absoluteExpiresAt: absoluteExpiresAt,
            sessionID: sessionID
        )
    }

    enum CodingKeys: String, CodingKey {
        case token
        case expiresAt = "expires_at"
        case absoluteExpiresAt = "absolute_expires_at"
        case sessionID = "session_id"
    }
}

struct SystemKeychainStoreAdapter: KeychainStoreAdapter {
    func delete(_ query: KeychainStoreDeleteQuery) async -> OSStatus {
        SecItemDelete([
            kSecClass: kSecClassGenericPassword,
            kSecAttrService: query.service,
            kSecAttrSynchronizable: query.synchronizable,
        ] as CFDictionary)
    }

    func add(_ item: KeychainStoreItem) async -> OSStatus {
        var creationError: Unmanaged<CFError>?
        guard let accessControl = SecAccessControlCreateWithFlags(
            kCFAllocatorDefault,
            kSecAttrAccessibleWhenUnlockedThisDeviceOnly,
            .biometryCurrentSet,
            &creationError
        ) else {
            return errSecParam
        }

        return SecItemAdd([
            kSecClass: kSecClassGenericPassword,
            kSecAttrService: item.service,
            kSecAttrAccount: item.account,
            kSecAttrSynchronizable: item.synchronizable,
            kSecAttrAccessControl: accessControl,
            kSecValueData: item.data,
        ] as CFDictionary, nil)
    }

    func read(_ query: KeychainStoreReadQuery) async -> KeychainStoreReadResult {
        let localizedReason: String
        switch query.authentication {
        case let .userPresence(reason):
            localizedReason = reason
        }
        guard !localizedReason.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
            return .failure(.status(errSecParam))
        }

        let context = LAContext()
        context.localizedReason = localizedReason

        var attributes: CFTypeRef?
        let status = SecItemCopyMatching([
            kSecClass: kSecClassGenericPassword,
            kSecAttrService: query.service,
            kSecAttrSynchronizable: query.synchronizable,
            kSecMatchLimit: kSecMatchLimitOne,
            kSecReturnAttributes: true,
            kSecReturnData: true,
            kSecUseAuthenticationContext: context,
        ] as CFDictionary, &attributes)

        guard status == errSecSuccess else {
            return .failure(.status(status))
        }
        guard let attributes = attributes as? [CFString: Any],
              let account = attributes[kSecAttrAccount] as? String,
              let data = attributes[kSecValueData] as? Data
        else {
            return .failure(.status(errSecDecode))
        }

        return .success(KeychainStoreItem(
            service: query.service,
            account: account,
            synchronizable: query.synchronizable,
            protection: KeychainStoreProtection(
                accessibility: .whenUnlockedThisDeviceOnly,
                accessControl: .biometryCurrentSet
            ),
            data: data
        ))
    }
}
