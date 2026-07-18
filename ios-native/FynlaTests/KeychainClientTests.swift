import Foundation
import Security
import Testing
@testable import Fynla

@Suite("Native refresh credential Keychain")
struct KeychainClientTests {
    @Test
    func saveDeletesOlderSessionsThenAddsExactProtectedCredential() async throws {
        let adapter = InMemoryKeychainStoreAdapter()
        let client = SystemKeychainClient(adapter: adapter)
        let first = credential(
            token: "refresh-first",
            sessionID: "11111111-2222-3333-4444-555555555555"
        )
        let replacement = credential(
            token: "refresh-replacement",
            sessionID: "66666666-7777-8888-9999-000000000000"
        )

        try await client.save(first)
        try await client.save(replacement)

        let operations = await adapter.recordedOperations()
        #expect(operations.count == 4)
        #expect(operations[0] == .delete(.init(
            service: "org.fynla.app.native-session",
            synchronizable: false
        )))
        #expect(operations[1].isAdd(account: first.sessionID))
        #expect(operations[2] == .delete(.init(
            service: "org.fynla.app.native-session",
            synchronizable: false
        )))

        guard case let .add(item) = operations[3] else {
            Issue.record("Expected replacement add after old-session deletion")
            return
        }
        #expect(item.service == "org.fynla.app.native-session")
        #expect(item.account == replacement.sessionID)
        #expect(item.synchronizable == false)
        #expect(item.protection == KeychainStoreProtection(
            accessibility: .whenUnlockedThisDeviceOnly,
            accessControl: .biometryCurrentSet
        ))

        let payload = try #require(
            JSONSerialization.jsonObject(with: item.data) as? [String: String]
        )
        #expect(payload == [
            "absolute_expires_at": replacement.absoluteExpiresAt,
            "expires_at": replacement.expiresAt,
            "session_id": replacement.sessionID,
            "token": replacement.token,
        ])
        #expect(payload["access_token"] == nil)
        #expect(payload["password"] == nil)
        #expect(payload["email"] == nil)
        #expect(payload["code"] == nil)
        #expect(payload["mfa_code"] == nil)
        #expect(payload["api_response"] == nil)
    }

    @Test
    func savedCredentialRoundTripsThroughAUserPresenceRead() async throws {
        let adapter = InMemoryKeychainStoreAdapter()
        let client = SystemKeychainClient(adapter: adapter)
        let expected = credential()

        try await client.save(expected)
        let actual = try await client.read()

        #expect(actual == expected)
        let operations = await adapter.recordedOperations()
        guard case let .read(query) = operations.last else {
            Issue.record("Expected a Keychain read")
            return
        }
        #expect(query.service == "org.fynla.app.native-session")
        #expect(query.synchronizable == false)
        guard case let .userPresence(localizedReason) = query.authentication else {
            Issue.record("Expected a user-presence protected read")
            return
        }
        #expect(!localizedReason.isEmpty)
    }

    @Test
    func authenticatedReadPassesTheExactEvaluatedCapabilityToTheStore() async throws {
        let adapter = InMemoryKeychainStoreAdapter()
        let client = SystemKeychainClient(adapter: adapter)
        let expected = credential()

        try await client.save(expected)
        let actual = try await client.read(authentication: .testing)

        #expect(actual == expected)
        let operations = await adapter.recordedOperations()
        guard case let .read(query) = operations.last,
              case let .authenticated(authorization) = query.authentication
        else {
            Issue.record("Expected a read using the evaluated authentication capability")
            return
        }
        #expect(authorization == .testing)
    }

    @Test
    func deleteRemovesTheOnlyNativeSessionCredential() async throws {
        let adapter = InMemoryKeychainStoreAdapter()
        let client = SystemKeychainClient(adapter: adapter)

        try await client.save(credential())
        try await client.delete()

        await #expect(throws: KeychainError.itemNotFound) {
            _ = try await client.read()
        }
    }

    @Test
    func missingItemRemainsDistinct() async {
        let client = SystemKeychainClient(adapter: InMemoryKeychainStoreAdapter())

        await #expect(throws: KeychainError.itemNotFound) {
            _ = try await client.read()
        }
        await #expect(throws: KeychainError.itemNotFound) {
            try await client.delete()
        }
    }

    @Test
    func cancelledInteractionRemainsDistinct() async {
        let adapter = InMemoryKeychainStoreAdapter(
            nextReadFailure: .status(errSecUserCanceled)
        )
        let client = SystemKeychainClient(adapter: adapter)

        await #expect(throws: KeychainError.interactionCancelled) {
            _ = try await client.read()
        }
    }

    @Test
    func authenticationFailureRemainsDistinct() async {
        let adapter = InMemoryKeychainStoreAdapter(
            nextReadFailure: .status(errSecAuthFailed)
        )
        let client = SystemKeychainClient(adapter: adapter)

        await #expect(throws: KeychainError.authenticationFailed) {
            _ = try await client.read()
        }
    }

    @Test
    func unavailableProtectedItemRequiresFullLoginWhetherMissingOrSystemInvalidated() async throws {
        let adapter = InMemoryKeychainStoreAdapter(
            nextReadFailure: .status(errSecItemNotFound)
        )
        let client = SystemKeychainClient(adapter: adapter)

        try await client.save(credential())

        await #expect(throws: KeychainError.itemNotFound) {
            _ = try await client.read()
        }
    }

    @Test
    func malformedStoredCredentialFailsClosed() async {
        let client = SystemKeychainClient(adapter: InMemoryKeychainStoreAdapter(
            initialItem: storedItem(data: Data("not-json".utf8))
        ))

        await #expect(throws: KeychainError.invalidStoredCredential) {
            _ = try await client.read()
        }
    }

    @Test
    func accountAndPayloadSessionMismatchFailsClosed() async throws {
        let payload = try JSONSerialization.data(withJSONObject: [
            "token": "refresh-example",
            "expires_at": "2026-08-16T12:00:00Z",
            "absolute_expires_at": "2026-10-15T12:00:00Z",
            "session_id": "payload-session",
        ])
        let client = SystemKeychainClient(adapter: InMemoryKeychainStoreAdapter(
            initialItem: storedItem(data: payload, account: "keychain-account")
        ))

        await #expect(throws: KeychainError.invalidStoredCredential) {
            _ = try await client.read()
        }
    }

    private func credential(
        token: String = "refresh-example",
        sessionID: String = "11111111-2222-3333-4444-555555555555"
    ) -> NativeRefreshCredential {
        NativeRefreshCredential(
            token: token,
            expiresAt: "2026-08-16T12:00:00Z",
            absoluteExpiresAt: "2026-10-15T12:00:00Z",
            sessionID: sessionID
        )
    }

    private func storedItem(
        data: Data,
        account: String = "11111111-2222-3333-4444-555555555555"
    ) -> KeychainStoreItem {
        KeychainStoreItem(
            service: "org.fynla.app.native-session",
            account: account,
            synchronizable: false,
            protection: KeychainStoreProtection(
                accessibility: .whenUnlockedThisDeviceOnly,
                accessControl: .biometryCurrentSet
            ),
            data: data
        )
    }
}

private actor InMemoryKeychainStoreAdapter: KeychainStoreAdapter {
    private var items: [String: KeychainStoreItem] = [:]
    private var operations: [RecordedOperation] = []
    private var nextReadFailure: KeychainStoreFailure?

    init(
        nextReadFailure: KeychainStoreFailure? = nil,
        initialItem: KeychainStoreItem? = nil
    ) {
        self.nextReadFailure = nextReadFailure
        if let initialItem {
            items[initialItem.account] = initialItem
        }
    }

    func delete(_ query: KeychainStoreDeleteQuery) -> OSStatus {
        operations.append(.delete(query))
        let matchingAccounts = items.compactMap { account, item in
            item.service == query.service && item.synchronizable == query.synchronizable
                ? account
                : nil
        }
        for account in matchingAccounts {
            items.removeValue(forKey: account)
        }
        return matchingAccounts.isEmpty ? errSecItemNotFound : errSecSuccess
    }

    func add(_ item: KeychainStoreItem) -> OSStatus {
        operations.append(.add(item))
        guard items[item.account] == nil else {
            return errSecDuplicateItem
        }
        items[item.account] = item
        return errSecSuccess
    }

    func read(_ query: KeychainStoreReadQuery) -> KeychainStoreReadResult {
        operations.append(.read(query))
        if let nextReadFailure {
            self.nextReadFailure = nil
            return .failure(nextReadFailure)
        }
        guard let item = items.values.first(where: {
            $0.service == query.service && $0.synchronizable == query.synchronizable
        }) else {
            return .failure(.status(errSecItemNotFound))
        }
        return .success(item)
    }

    func recordedOperations() -> [RecordedOperation] {
        operations
    }
}

private enum RecordedOperation: Sendable, Equatable {
    case delete(KeychainStoreDeleteQuery)
    case add(KeychainStoreItem)
    case read(KeychainStoreReadQuery)

    func isAdd(account: String) -> Bool {
        guard case let .add(item) = self else {
            return false
        }
        return item.account == account
    }
}
