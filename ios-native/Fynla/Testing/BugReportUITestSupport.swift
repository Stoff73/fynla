#if FYNLA_UI_TESTING
@MainActor
enum BugReportUITestComposition {
    static func model(metadata: BugReportMetadata) -> BugReportModel {
        BugReportModel(client: BugReportUITestClient(), metadata: metadata)
    }
}

private struct BugReportUITestClient: BugReportClient {
    func submit(_ report: BugReportSubmission) async throws {}
}
#endif
