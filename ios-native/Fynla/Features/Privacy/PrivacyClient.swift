import Foundation

protocol PrivacyClient: Sendable {
    func loadConsents() async throws -> ConsentSnapshot
    func loadConsentHistory() async throws -> ConsentHistory
    func updateConsents(_ values: [String: Bool]) async throws -> ConsentSnapshot
    func requestExport(format: String) async throws -> DataExportStatus
    func exportStatus() async throws -> DataExportStatus
    func downloadExport(id: Int) async throws -> Data
}

struct LivePrivacyClient: PrivacyClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func loadConsents() async throws -> ConsentSnapshot {
        try await apiClient.send(request(path: "api/auth/gdpr/consents"))
    }

    func loadConsentHistory() async throws -> ConsentHistory {
        try await apiClient.send(request(path: "api/auth/gdpr/consents/history"))
    }

    func updateConsents(_ values: [String: Bool]) async throws -> ConsentSnapshot {
        let body = try JSONEncoder().encode(ConsentUpdateBody(consents: values))
        return try await apiClient.send(APIRequest<ConsentSnapshot>(
            path: "api/auth/gdpr/consents",
            method: .put,
            body: body
        ))
    }

    func requestExport(format: String) async throws -> DataExportStatus {
        let body = try JSONEncoder().encode(ExportRequestBody(format: format))
        return try await apiClient.send(APIRequest<DataExportStatus>(
            path: "api/auth/gdpr/export",
            method: .post,
            body: body
        ))
    }

    func exportStatus() async throws -> DataExportStatus {
        try await apiClient.send(request(path: "api/auth/gdpr/export/status"))
    }

    func downloadExport(id: Int) async throws -> Data {
        try await apiClient.sendData(APIRequest<EmptyPrivacyResponse>(
            path: "api/auth/gdpr/export/\(id)/download",
            method: .get,
            headers: ["Accept": "application/octet-stream"]
        ))
    }

    private func request<Value: Decodable & Sendable>(
        path: String
    ) -> APIRequest<Value> {
        APIRequest(
            path: path,
            method: .get,
            headers: ["Cache-Control": "no-cache"]
        )
    }
}

private struct ConsentUpdateBody: Encodable, Sendable {
    let consents: [String: Bool]
}

private struct ExportRequestBody: Encodable, Sendable {
    let format: String
}

private struct EmptyPrivacyResponse: Decodable, Sendable {}
