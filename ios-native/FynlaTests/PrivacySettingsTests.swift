import Foundation
import Testing
@testable import Fynla

@Suite("Privacy settings and data export")
struct PrivacySettingsTests {
    @Test
    func clientUsesCurrentConsentAndExportEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("consents")),
            .response(status: 200, body: try fixture("consent-history")),
            .response(status: 200, body: try fixture("consents-updated")),
            .response(status: 200, body: try fixture("export-processing")),
            .response(status: 200, body: try fixture("export-ready")),
            .response(status: 200, body: Data("{\"account\":{}}".utf8)),
        ])
        let client = LivePrivacyClient(apiClient: apiClient(transport))

        let consents = try await client.loadConsents()
        let history = try await client.loadConsentHistory()
        let updated = try await client.updateConsents(["marketing": true])
        let requested = try await client.requestExport(format: "json")
        let ready = try await client.exportStatus()
        let bytes = try await client.downloadExport(id: ready.exportID)

        #expect(consents.consents["ai_chat"]?.consented == true)
        #expect(history.history.first?.consented == false)
        #expect(updated.consents["marketing"]?.consented == true)
        #expect(requested.status == "processing")
        #expect(ready.isDownloadable == true)
        #expect(bytes == Data("{\"account\":{}}".utf8))
        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/auth/gdpr/consents",
            "/fynla/api/auth/gdpr/consents/history",
            "/fynla/api/auth/gdpr/consents",
            "/fynla/api/auth/gdpr/export",
            "/fynla/api/auth/gdpr/export/status",
            "/fynla/api/auth/gdpr/export/91/download",
        ])
    }

    @Test @MainActor
    func consentModelPublishesOnlyServerAcknowledgedState() async throws {
        let initial = try JSONDecoder().decode(
            ConsentEnvelope.self,
            from: try fixture("consents")
        ).data
        let updated = try JSONDecoder().decode(
            ConsentUpdateEnvelope.self,
            from: try fixture("consents-updated")
        ).data
        let client = PrivacyClientStub(consents: initial, updated: updated)
        let model = PrivacySettingsModel(client: client)

        await model.load()
        await model.setConsent(type: "marketing", consented: true)

        #expect(model.state == .loaded(updated))
        #expect(await client.updates() == [["marketing": true]])
    }

    @Test @MainActor
    func exportPollsBoundedlyThenSavesAndDeletesTemporaryFile() async throws {
        let root = FileManager.default.temporaryDirectory
            .appending(path: "fynla-export-test-\(UUID().uuidString)", directoryHint: .isDirectory)
        let store = TemporaryExportStore(directory: root)
        let client = ExportPrivacyClientStub()
        let model = DataExportModel(
            client: client,
            store: store,
            maximumPollAttempts: 3,
            wait: { _ in }
        )

        await model.requestExport()
        let url = try #require(model.shareURL)

        #expect(FileManager.default.fileExists(atPath: url.path))
        #expect(await client.statusChecks() == 1)
        await model.shareDismissed()
        #expect(!FileManager.default.fileExists(atPath: url.path))
    }

    private func apiClient(_ transport: TestHTTPTransport) -> APIClient {
        APIClient(
            environment: try! AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: PrivacyTokenProvider(),
            requestID: { "privacy-request" }
        )
    }

    private static func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Privacy/\(name).json")
        )
    }

    private func fixture(_ name: String) throws -> Data {
        try Self.fixture(name)
    }
}

private struct PrivacyTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "privacy-token" }
}

private actor PrivacyClientStub: PrivacyClient {
    let consents: ConsentSnapshot
    let updated: ConsentSnapshot
    private var consentUpdates: [[String: Bool]] = []

    init(consents: ConsentSnapshot, updated: ConsentSnapshot) {
        self.consents = consents
        self.updated = updated
    }

    func loadConsents() async throws -> ConsentSnapshot { consents }
    func loadConsentHistory() async throws -> ConsentHistory { ConsentHistory(history: []) }
    func updateConsents(_ values: [String: Bool]) async throws -> ConsentSnapshot {
        consentUpdates.append(values)
        return updated
    }
    func requestExport(format: String) async throws -> DataExportStatus { throw StubError.unused }
    func exportStatus() async throws -> DataExportStatus { throw StubError.unused }
    func downloadExport(id: Int) async throws -> Data { throw StubError.unused }
    func updates() -> [[String: Bool]] { consentUpdates }
}

private actor ExportPrivacyClientStub: PrivacyClient {
    private var checks = 0
    func loadConsents() async throws -> ConsentSnapshot { throw StubError.unused }
    func loadConsentHistory() async throws -> ConsentHistory { throw StubError.unused }
    func updateConsents(_ values: [String: Bool]) async throws -> ConsentSnapshot { throw StubError.unused }
    func requestExport(format: String) async throws -> DataExportStatus {
        DataExportStatus(exportID: 91, status: "processing", format: "json", expiresAt: nil, isDownloadable: nil)
    }
    func exportStatus() async throws -> DataExportStatus {
        checks += 1
        return DataExportStatus(exportID: 91, status: "completed", format: "json", expiresAt: "2026-07-25T12:00:01Z", isDownloadable: true)
    }
    func downloadExport(id: Int) async throws -> Data { Data("{\"account\":{}}".utf8) }
    func statusChecks() -> Int { checks }
}

private enum StubError: Error { case unused }
