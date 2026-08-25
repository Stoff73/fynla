import Observation

enum BugReportState: Sendable, Equatable {
    case editing
    case reviewing
    case submitting
    case submitted
    case offline
    case rateLimited
    case failed
}

struct BugReportReviewRow: Identifiable, Sendable, Equatable {
    let label: String
    let value: String

    var id: String { label }
}

@MainActor
@Observable
final class BugReportModel {
    private let client: any BugReportClient
    private var metadata: BugReportMetadata

    var description = ""
    var category = "General"
    var severity = "Medium"
    var conversationID: String?
    private(set) var state: BugReportState = .editing

    let categories = [
        "Protection", "Savings", "Investment", "Retirement",
        "Estate", "Goals", "Coordination", "General",
    ]
    let severities = ["Low", "Medium", "High", "Critical"]

    init(client: any BugReportClient, metadata: BugReportMetadata) {
        self.client = client
        self.metadata = metadata
    }

    var canReview: Bool {
        !description.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }

    var reviewRows: [BugReportReviewRow] {
        [
            BugReportReviewRow(label: "App version", value: metadata.appVersion),
            BugReportReviewRow(label: "Build", value: metadata.appBuild),
            BugReportReviewRow(label: "Environment", value: metadata.environment),
            BugReportReviewRow(label: "Route", value: metadata.route),
            BugReportReviewRow(
                label: "Request reference",
                value: metadata.requestCorrelationID ?? "Not available"
            ),
            BugReportReviewRow(label: "Native session", value: metadata.nativeSessionUUID),
            BugReportReviewRow(
                label: "Conversation reference",
                value: conversationID ?? "Not attached"
            ),
        ]
    }

    func updateContext(
        route: String,
        conversationID: String?,
        requestCorrelationID: String? = nil
    ) {
        metadata.route = route
        metadata.requestCorrelationID = requestCorrelationID
        self.conversationID = conversationID
    }

    func prepareReview() {
        guard canReview, state != .submitting else { return }
        description = description.trimmingCharacters(in: .whitespacesAndNewlines)
        state = .reviewing
    }

    func edit() {
        guard state != .submitting else { return }
        state = .editing
    }

    func submit() async {
        guard state == .reviewing else { return }
        state = .submitting
        do {
            try await client.submit(
                BugReportSubmission(
                    description: description,
                    category: category,
                    severity: severity,
                    metadata: metadata,
                    conversationID: conversationID
                )
            )
            state = .submitted
        } catch APIError.offline {
            state = .offline
        } catch APIError.rateLimited {
            state = .rateLimited
        } catch is CancellationError {
            state = .reviewing
        } catch {
            state = .failed
        }
    }

    func reset() {
        description = ""
        category = "General"
        severity = "Medium"
        conversationID = nil
        state = .editing
    }
}
