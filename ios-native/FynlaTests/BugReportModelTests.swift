import Foundation
import Testing
@testable import Fynla

@MainActor
@Suite("Native bug reporting")
struct BugReportModelTests {
    @Test
    func liveClientSendsOnlyReviewedAllowlistedMetadata() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                body: Data(#"{"success":true,"message":"Report logged","issue_url":null}"#.utf8)
            ),
        ])
        let api = APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "42",
            transport: transport,
            tokenProvider: BugReportTokenProvider()
        )
        let report = BugReportSubmission(
            description: "The native dashboard did not refresh.",
            category: "General",
            severity: "Medium",
            metadata: metadata,
            conversationID: "321"
        )

        try await LiveBugReportClient(apiClient: api).submit(report)

        let request = try #require(await transport.requests().first)
        let body = try #require(request.httpBody)
        let json = try #require(
            JSONSerialization.jsonObject(with: body) as? [String: Any]
        )
        #expect(request.url?.path == "/fynla/api/bug-report")
        #expect(json["description"] as? String == report.description)
        #expect(json["platform"] as? String == "ios")
        #expect(json["app_build"] as? String == "42")
        #expect(json["native_session_uuid"] as? String == metadata.nativeSessionUUID)
        #expect(json["conversation_id"] as? Int == 321)
        #expect(json["console_logs"] == nil)
        #expect(json["fyn_transcript"] == nil)
        #expect(json["financial_values"] == nil)
    }

    @Test
    func modelShowsExactlyWhatWillBeSentAndNeverClaimsOfflineSubmission() async {
        let client = OfflineBugReportClient()
        let model = BugReportModel(client: client, metadata: metadata)
        model.description = "A card is showing stale data."
        model.conversationID = "321"

        model.prepareReview()
        #expect(model.state == .reviewing)
        #expect(model.reviewRows.map(\.label) == [
            "App version", "Build", "Environment", "Route",
            "Request reference", "Native session", "Conversation reference",
        ])

        await model.submit()

        #expect(model.state == .offline)
        #expect(model.description == "A card is showing stale data.")
    }

    private var metadata: BugReportMetadata {
        BugReportMetadata(
            appVersion: "1.0.0",
            appBuild: "42",
            environment: "staging",
            route: "/dashboard",
            requestCorrelationID: "request-42",
            nativeSessionUUID: "9E7D314A-E607-4B93-B739-6864363CF913"
        )
    }
}

private struct BugReportTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "bug-report-token" }
}

private struct OfflineBugReportClient: BugReportClient {
    func submit(_ report: BugReportSubmission) async throws {
        throw APIError.offline
    }
}
