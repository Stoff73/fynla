import Foundation

struct ConsentEnvelope: Decodable, Sendable, Equatable {
    let data: ConsentSnapshot
}

struct ConsentUpdateEnvelope: Decodable, Sendable, Equatable {
    let data: ConsentSnapshot
}

struct ConsentSnapshot: Decodable, Sendable, Equatable {
    let consents: [String: ConsentRecord]
    let needsReconsent: [String]?

    private enum CodingKeys: String, CodingKey {
        case consents
        case needsReconsent = "needs_reconsent"
    }
}

struct ConsentRecord: Decodable, Sendable, Equatable {
    let consented: Bool
    let version: String
    let consentedAt: String?

    private enum CodingKeys: String, CodingKey {
        case consented
        case version
        case consentedAt = "consented_at"
    }
}

struct ConsentHistory: Decodable, Sendable, Equatable {
    let history: [ConsentHistoryEntry]
}

struct ConsentHistoryEntry: Decodable, Sendable, Equatable, Identifiable {
    let type: String
    let version: String
    let consented: Bool
    let consentedAt: String?
    let withdrawnAt: String?

    var id: String { "\(type)_\(version)_\(consentedAt ?? withdrawnAt ?? "unknown")" }

    private enum CodingKeys: String, CodingKey {
        case type
        case version
        case consented
        case consentedAt = "consented_at"
        case withdrawnAt = "withdrawn_at"
    }
}

struct DataExportStatus: Decodable, Sendable, Equatable {
    let exportID: Int
    let status: String
    let format: String
    let expiresAt: String?
    let isDownloadable: Bool?

    private enum CodingKeys: String, CodingKey {
        case exportID = "export_id"
        case status
        case format
        case expiresAt = "expires_at"
        case isDownloadable = "is_downloadable"
    }
}

enum PrivacySettingsState: Sendable, Equatable {
    case idle
    case loading
    case loaded(ConsentSnapshot)
    case offline(previous: ConsentSnapshot?)
    case unauthenticated
    case failed(requestID: String?)
}

enum DataExportState: Sendable, Equatable {
    case idle
    case requesting
    case processing(DataExportStatus)
    case ready(DataExportStatus)
    case expired
    case rateLimited
    case failed
}
