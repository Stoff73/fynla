import Foundation
import Security
import Testing
@testable import Fynla

@Suite("Legacy Capacitor upgrade cleanup")
struct LegacyCapacitorCleanupTests {
    @Test
    func clearsLegacyWebDataAndOnlyKnownInternetPasswordServersOnce() async throws {
        let websiteData = WebsiteDataCleanerStub()
        let internetPasswords = InternetPasswordDeleterStub()
        let marker = CleanupMarkerStub()
        let cleanup = LegacyCapacitorCleanup(
            websiteData: websiteData,
            internetPasswords: internetPasswords,
            marker: marker
        )

        try await cleanup.runIfNeeded()
        try await cleanup.runIfNeeded()

        #expect(await websiteData.clearCount() == 1)
        #expect(await internetPasswords.deletedServers() == [
            "fynla.org",
            "csjones.co",
        ])
        #expect(await marker.isComplete())
        #expect(await marker.markCount() == 1)
    }

    @Test
    func partialFailureLeavesMarkerUnsetAndRetriesEveryOperation() async {
        let websiteData = WebsiteDataCleanerStub()
        let internetPasswords = InternetPasswordDeleterStub(statuses: [
            errSecSuccess,
            errSecInteractionNotAllowed,
            errSecItemNotFound,
            errSecSuccess,
        ])
        let marker = CleanupMarkerStub()
        let cleanup = LegacyCapacitorCleanup(
            websiteData: websiteData,
            internetPasswords: internetPasswords,
            marker: marker
        )

        await #expect(throws: LegacyCapacitorCleanupError.keychainStatus(
            errSecInteractionNotAllowed
        )) {
            try await cleanup.runIfNeeded()
        }
        #expect(await marker.isComplete() == false)

        await #expect(throws: Never.self) {
            try await cleanup.runIfNeeded()
        }
        #expect(await websiteData.clearCount() == 2)
        #expect(await internetPasswords.deletedServers() == [
            "fynla.org",
            "csjones.co",
            "fynla.org",
            "csjones.co",
        ])
        #expect(await marker.isComplete())
    }

    @Test
    func websiteDataFailureRetriesWithoutMarkingComplete() async {
        let websiteData = WebsiteDataCleanerStub(failuresRemaining: 1)
        let internetPasswords = InternetPasswordDeleterStub()
        let marker = CleanupMarkerStub()
        let cleanup = LegacyCapacitorCleanup(
            websiteData: websiteData,
            internetPasswords: internetPasswords,
            marker: marker
        )

        await #expect(throws: LegacyWebsiteDataCleanerStubError.failed) {
            try await cleanup.runIfNeeded()
        }
        #expect(await marker.isComplete() == false)
        #expect(await internetPasswords.deletedServers().isEmpty)

        await #expect(throws: Never.self) {
            try await cleanup.runIfNeeded()
        }
        #expect(await websiteData.clearCount() == 2)
        #expect(await marker.isComplete())
    }

    @Test
    func cleanupCannotDeleteTheNewNativeGenericPasswordService() async throws {
        let websiteData = WebsiteDataCleanerStub()
        let internetPasswords = InternetPasswordDeleterStub()
        let marker = CleanupMarkerStub()
        let protectedNativeService = "org.fynla.app.native-session"
        var protectedNativeItemExists = true
        let cleanup = LegacyCapacitorCleanup(
            websiteData: websiteData,
            internetPasswords: internetPasswords,
            marker: marker
        )

        try await cleanup.runIfNeeded()

        for server in await internetPasswords.deletedServers() {
            if server == protectedNativeService {
                protectedNativeItemExists = false
            }
        }
        #expect(protectedNativeItemExists)
        #expect(await internetPasswords.deletedServers().allSatisfy {
            $0 == "fynla.org" || $0 == "csjones.co"
        })
    }
}

private enum LegacyWebsiteDataCleanerStubError: Error {
    case failed
}

private actor WebsiteDataCleanerStub: LegacyWebsiteDataCleaning {
    private var count = 0
    private var failuresRemaining: Int

    init(failuresRemaining: Int = 0) {
        self.failuresRemaining = failuresRemaining
    }

    func clearLegacyWebsiteData() async throws {
        count += 1
        if failuresRemaining > 0 {
            failuresRemaining -= 1
            throw LegacyWebsiteDataCleanerStubError.failed
        }
    }

    func clearCount() -> Int { count }
}

private actor InternetPasswordDeleterStub: LegacyInternetPasswordDeleting {
    private var servers: [String] = []
    private var statuses: [OSStatus]

    init(statuses: [OSStatus] = []) {
        self.statuses = statuses
    }

    func deleteInternetPassword(server: String) async -> OSStatus {
        servers.append(server)
        return statuses.isEmpty ? errSecSuccess : statuses.removeFirst()
    }

    func deletedServers() -> [String] { servers }
}

private actor CleanupMarkerStub: LegacyCleanupCompletionTracking {
    private var complete = false
    private var marks = 0

    func isComplete() -> Bool { complete }

    func markComplete() {
        complete = true
        marks += 1
    }

    func markCount() -> Int { marks }
}
