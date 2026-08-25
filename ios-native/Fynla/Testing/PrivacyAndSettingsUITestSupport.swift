#if FYNLA_UI_TESTING
import Foundation

enum PrivacyAndSettingsUITestComposition {
    static func privacyClient() -> any PrivacyClient {
        PrivacyUITestClient()
    }

    static func accountDeletionClient() -> any AccountDeletionClient {
        AccountDeletionUITestClient()
    }

    @MainActor
    static func systemPushClient() -> any SystemPushClient {
        SystemPushUITestClient()
    }

    static func pushClient() -> any PushClient {
        PushUITestClient()
    }
}

private actor PrivacyUITestClient: PrivacyClient {
    private var marketing = false

    func loadConsents() async throws -> ConsentSnapshot {
        snapshot
    }

    func loadConsentHistory() async throws -> ConsentHistory {
        ConsentHistory(history: [
            ConsentHistoryEntry(
                type: "marketing",
                version: "1.0",
                consented: false,
                consentedAt: nil,
                withdrawnAt: "2026-08-01T12:00:00Z"
            ),
        ])
    }

    func updateConsents(_ values: [String: Bool]) async throws -> ConsentSnapshot {
        guard values["marketing"] == true else {
            throw PrivacyUITestError.expectedMarketingOptIn
        }
        marketing = true
        return snapshot
    }

    func requestExport(format: String) async throws -> DataExportStatus {
        DataExportStatus(
            exportID: 812,
            status: "completed",
            format: format,
            expiresAt: "2026-08-13T12:00:00Z",
            isDownloadable: true
        )
    }

    func exportStatus() async throws -> DataExportStatus {
        try await requestExport(format: "json")
    }

    func downloadExport(id: Int) async throws -> Data {
        Data("{\"account\":{\"id\":101}}".utf8)
    }

    private var snapshot: ConsentSnapshot {
        ConsentSnapshot(
            consents: [
                "terms": record(true),
                "privacy": record(true),
                "data_processing": record(true),
                "ai_chat": record(true),
                "marketing": record(marketing),
            ],
            needsReconsent: []
        )
    }

    private func record(_ consented: Bool) -> ConsentRecord {
        ConsentRecord(
            consented: consented,
            version: "1.0",
            consentedAt: consented ? "2026-08-01T12:00:00Z" : nil
        )
    }
}

private enum PrivacyUITestError: Error {
    case expectedMarketingOptIn
}

private actor AccountDeletionUITestClient: AccountDeletionClient {
    private let sessionToken = String(repeating: "s", count: 64)

    func initiate() async throws -> AccountDeletionInitiation {
        AccountDeletionInitiation(
            requiresTwoFactor: false,
            requiresEmailVerification: true,
            sessionToken: sessionToken
        )
    }

    func verify(
        sessionToken: String,
        code: String
    ) async throws -> AccountDeletionVerification {
        AccountDeletionVerification(
            message: "Identity verified successfully.",
            sessionToken: self.sessionToken,
            type: "account"
        )
    }

    func resend(sessionToken: String) async throws -> AccountDeletionMessage {
        AccountDeletionMessage(message: "Verification code sent to your email.")
    }

    func execute(
        sessionToken: String,
        confirmation: String
    ) async throws -> AccountDeletionResult {
        AccountDeletionResult(
            message: "Your account is scheduled for deletion on 31 August 2026.",
            type: "account_scheduled",
            logoutRequired: false,
            scheduledDeletionAt: "2026-08-31T23:59:59Z"
        )
    }

    func cancelScheduled() async throws -> AccountDeletionMessage {
        AccountDeletionMessage(message: "Scheduled deletion cancelled.")
    }
}

@MainActor
private final class SystemPushUITestClient: SystemPushClient {
    func authorizationStatus() async -> PushAuthorizationStatus { .authorized }
    func requestAuthorization() async throws -> PushAuthorizationStatus { .authorized }
    func registerForRemoteNotifications() {}
}

private actor PushUITestClient: PushClient {
    private var preferences = PushPreferences.defaults

    func register(_ registration: PushDeviceRegistration) async throws {}
    func unregister(deviceID: String) async throws {}
    func loadPreferences() async throws -> PushPreferences { preferences }

    func updatePreferences(_ values: [PushPreferenceKey: Bool]) async throws {
        for (key, value) in values { preferences[key] = value }
    }
}
#endif
