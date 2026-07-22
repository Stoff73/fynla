import Foundation
import Security
import WebKit

protocol LegacyWebsiteDataCleaning: Sendable {
    func clearLegacyWebsiteData() async throws
}

protocol LegacyInternetPasswordDeleting: Sendable {
    func deleteInternetPassword(server: String) async -> OSStatus
}

protocol LegacyCleanupCompletionTracking: Sendable {
    func isComplete() async -> Bool
    func markComplete() async
}

enum LegacyCapacitorCleanupError: Error, Sendable, Equatable {
    case keychainStatus(OSStatus)
}

actor LegacyCapacitorCleanup {
    private static let legacyServers = ["fynla.org", "csjones.co"]

    private let websiteData: any LegacyWebsiteDataCleaning
    private let internetPasswords: any LegacyInternetPasswordDeleting
    private let marker: any LegacyCleanupCompletionTracking

    init(
        websiteData: any LegacyWebsiteDataCleaning,
        internetPasswords: any LegacyInternetPasswordDeleting,
        marker: any LegacyCleanupCompletionTracking
    ) {
        self.websiteData = websiteData
        self.internetPasswords = internetPasswords
        self.marker = marker
    }

    static func live() -> LegacyCapacitorCleanup {
        LegacyCapacitorCleanup(
            websiteData: SystemLegacyWebsiteDataCleaner(),
            internetPasswords: SystemLegacyInternetPasswordDeleter(),
            marker: UserDefaultsLegacyCleanupMarker()
        )
    }

    func runIfNeeded() async throws {
        guard !(await marker.isComplete()) else { return }

        try await websiteData.clearLegacyWebsiteData()
        for server in Self.legacyServers {
            let status = await internetPasswords.deleteInternetPassword(server: server)
            guard status == errSecSuccess || status == errSecItemNotFound else {
                throw LegacyCapacitorCleanupError.keychainStatus(status)
            }
        }

        await marker.markComplete()
    }
}

@MainActor
final class SystemLegacyWebsiteDataCleaner: LegacyWebsiteDataCleaning {
    func clearLegacyWebsiteData() async throws {
        let dataStore = WKWebsiteDataStore.default()
        let types: Set<String> = [
            WKWebsiteDataTypeCookies,
            WKWebsiteDataTypeLocalStorage,
            WKWebsiteDataTypeSessionStorage,
            WKWebsiteDataTypeIndexedDBDatabases,
            WKWebsiteDataTypeDiskCache,
            WKWebsiteDataTypeMemoryCache,
        ]
        let records = await dataStore.dataRecords(ofTypes: types)
        await dataStore.removeData(ofTypes: types, for: records)
    }
}

struct SystemLegacyInternetPasswordDeleter: LegacyInternetPasswordDeleting {
    func deleteInternetPassword(server: String) async -> OSStatus {
        SecItemDelete([
            kSecClass: kSecClassInternetPassword,
            kSecAttrServer: server,
        ] as CFDictionary)
    }
}

actor UserDefaultsLegacyCleanupMarker: LegacyCleanupCompletionTracking {
    private static let key = "native-v1.legacy-capacitor-cleanup.complete"
    private let defaults: UserDefaults

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
    }

    func isComplete() -> Bool {
        defaults.bool(forKey: Self.key)
    }

    func markComplete() {
        defaults.set(true, forKey: Self.key)
    }
}
