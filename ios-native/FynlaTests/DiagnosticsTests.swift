import Testing
@testable import Fynla

@Suite("Redacted diagnostics")
struct DiagnosticsTests {
    @Test
    func preservesOnlyAllowlistedScalarValues() async {
        let sink = TestDiagnosticsSink()
        let diagnostics = RedactingDiagnosticsClient(sink: sink)
        let event = DiagnosticEvent(
            category: .network,
            operation: "api.response",
            statusCode: 200,
            requestID: "request-123",
            durationMilliseconds: 42
        )

        await diagnostics.record(event)

        #expect(await sink.events() == [event])
    }

    @Test
    func sensitiveHeaderAndCredentialNamesNeverReachTheSink() async {
        let sensitiveValues = [
            "Authorization",
            "Cookie",
            "verification_code",
            "verification_token",
            "password",
            "password_confirmation",
            "signedTransaction",
            "signedPayload",
        ]
        let sink = TestDiagnosticsSink()
        let diagnostics = RedactingDiagnosticsClient(sink: sink)

        for value in sensitiveValues {
            await diagnostics.record(DiagnosticEvent(
                category: .authentication,
                operation: value,
                statusCode: 401,
                requestID: value,
                durationMilliseconds: 5
            ))
        }

        let recorded = await sink.events()
        #expect(recorded.allSatisfy { $0.operation == "redacted" })
        #expect(recorded.allSatisfy { $0.requestID == nil })

        let rendered = recorded.map(String.init(describing:)).joined(separator: " ")
        for value in sensitiveValues {
            #expect(!rendered.localizedCaseInsensitiveContains(value))
        }
    }

    @Test
    func arbitraryFinancialJSONNeverReachesTheSink() async {
        let payload = #"{"income":75000,"net_worth":225000,"accounts":[17]}"#
        let sink = TestDiagnosticsSink()
        let diagnostics = RedactingDiagnosticsClient(sink: sink)

        await diagnostics.record(DiagnosticEvent(
            category: .network,
            operation: payload,
            statusCode: 200,
            requestID: payload,
            durationMilliseconds: 8
        ))

        let event = await sink.events().first
        #expect(event?.operation == "redacted")
        #expect(event?.requestID == nil)
        #expect(!String(describing: event).contains("75000"))
        #expect(!String(describing: event).contains("225000"))
    }

    @Test
    func rejectsUnknownOperationsAndInvalidMeasurements() async {
        let sink = TestDiagnosticsSink()
        let diagnostics = RedactingDiagnosticsClient(sink: sink)

        await diagnostics.record(DiagnosticEvent(
            category: .application,
            operation: "free form log message",
            statusCode: 99,
            requestID: "request with spaces",
            durationMilliseconds: -1
        ))

        let event = await sink.events().first
        #expect(event?.operation == "redacted")
        #expect(event?.statusCode == nil)
        #expect(event?.requestID == nil)
        #expect(event?.durationMilliseconds == nil)
    }

    @Test
    func diagnosticEventContainsOnlyTheApprovedFields() {
        let event = DiagnosticEvent(
            category: .streaming,
            operation: "sse.connect",
            statusCode: nil,
            requestID: nil,
            durationMilliseconds: nil
        )

        let labels = Set(Mirror(reflecting: event).children.compactMap(\.label))
        #expect(labels == [
            "category",
            "operation",
            "statusCode",
            "requestID",
            "durationMilliseconds",
        ])
    }
}

private actor TestDiagnosticsSink: DiagnosticsClient {
    private var recorded: [DiagnosticEvent] = []

    func record(_ event: DiagnosticEvent) async {
        recorded.append(event)
    }

    func events() -> [DiagnosticEvent] {
        recorded
    }
}
