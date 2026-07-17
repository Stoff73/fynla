struct RedactingDiagnosticsClient: DiagnosticsClient {
    private let sink: any DiagnosticsClient

    init(sink: any DiagnosticsClient) {
        self.sink = sink
    }

    func record(_ event: DiagnosticEvent) async {
        let redacted = DiagnosticEvent(
            category: event.category,
            operation: sanitizeOperation(event.operation),
            statusCode: sanitizeStatusCode(event.statusCode),
            requestID: sanitizeRequestID(event.requestID),
            durationMilliseconds: sanitizeDuration(event.durationMilliseconds)
        )
        await sink.record(redacted)
    }

    private func sanitizeOperation(_ operation: String) -> String {
        guard DiagnosticOperation.allowed.contains(operation),
              !containsSensitiveTerm(operation)
        else {
            return "redacted"
        }
        return operation
    }

    private func sanitizeStatusCode(_ statusCode: Int?) -> Int? {
        guard let statusCode, (100...599).contains(statusCode) else {
            return nil
        }
        return statusCode
    }

    private func sanitizeRequestID(_ requestID: String?) -> String? {
        guard let requestID,
              !requestID.isEmpty,
              requestID.utf8.count <= 128,
              requestID.allSatisfy({ $0.isLetter || $0.isNumber || $0 == "-" }),
              !containsSensitiveTerm(requestID)
        else {
            return nil
        }
        return requestID
    }

    private func sanitizeDuration(_ durationMilliseconds: Int?) -> Int? {
        guard let durationMilliseconds, durationMilliseconds >= 0 else {
            return nil
        }
        return durationMilliseconds
    }

    private func containsSensitiveTerm(_ value: String) -> Bool {
        let normalized = value.lowercased()
        return Self.sensitiveTerms.contains { normalized.contains($0) }
    }

    private static let sensitiveTerms = [
        "authorization",
        "cookie",
        "verification",
        "password",
        "signedtransaction",
        "signedpayload",
        "income",
        "networth",
        "net_worth",
        "account",
        "balance",
        "expenditure",
        "savings",
        "investment",
        "pension",
        "retirement",
        "portfolio",
        "amount",
        "assets",
        "liabilities",
        "estate",
        "tax",
    ]
}
