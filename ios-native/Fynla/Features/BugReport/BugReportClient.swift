import Foundation

protocol BugReportClient: Sendable {
    func submit(_ report: BugReportSubmission) async throws
}

struct LiveBugReportClient: BugReportClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func submit(_ report: BugReportSubmission) async throws {
        let response = try await apiClient.send(
            APIRequest<BugReportResponse>(
                path: "api/bug-report",
                method: .post,
                body: try JSONEncoder().encode(report),
                responseDecoding: .raw
            )
        )
        guard response.success else {
            throw APIError.server(status: 500, requestID: nil)
        }
    }
}

struct BugReportMetadata: Sendable, Equatable {
    let appVersion: String
    let appBuild: String
    let environment: String
    var route: String
    var requestCorrelationID: String?
    let nativeSessionUUID: String
}

struct BugReportSubmission: Encodable, Sendable, Equatable {
    let description: String
    let category: String
    let severity: String
    let metadata: BugReportMetadata
    let conversationID: String?

    private enum CodingKeys: String, CodingKey {
        case description, category, severity, platform, route, environment
        case appVersion = "app_version"
        case appBuild = "app_build"
        case requestCorrelationID = "request_correlation_id"
        case nativeSessionUUID = "native_session_uuid"
        case conversationID = "conversation_id"
    }

    func encode(to encoder: Encoder) throws {
        var values = encoder.container(keyedBy: CodingKeys.self)
        try values.encode(description, forKey: .description)
        try values.encode(category, forKey: .category)
        try values.encode(severity, forKey: .severity)
        try values.encode("ios", forKey: .platform)
        try values.encode(metadata.route, forKey: .route)
        try values.encode(metadata.environment, forKey: .environment)
        try values.encode(metadata.appVersion, forKey: .appVersion)
        try values.encode(metadata.appBuild, forKey: .appBuild)
        try values.encodeIfPresent(
            metadata.requestCorrelationID,
            forKey: .requestCorrelationID
        )
        try values.encode(metadata.nativeSessionUUID, forKey: .nativeSessionUUID)
        if let conversationID, let numericID = Int(conversationID) {
            try values.encode(numericID, forKey: .conversationID)
        }
    }
}

private struct BugReportResponse: Decodable, Sendable {
    let success: Bool
    let message: String?
    let issueURL: String?

    private enum CodingKeys: String, CodingKey {
        case success, message
        case issueURL = "issue_url"
    }
}
