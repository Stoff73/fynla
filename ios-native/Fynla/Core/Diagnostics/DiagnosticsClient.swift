import Foundation
import OSLog

protocol DiagnosticsClient: Sendable {
    func record(_ event: DiagnosticEvent) async
}

private actor LoggerDiagnosticsClient: DiagnosticsClient {
    private let logger: Logger

    init(
        subsystem: String = Bundle.main.bundleIdentifier ?? "org.fynla.app",
        category: String = "diagnostics"
    ) {
        logger = Logger(subsystem: subsystem, category: category)
    }

    func record(_ event: DiagnosticEvent) async {
        let statusCode = event.statusCode ?? -1
        let requestID = event.requestID ?? "-"
        let durationMilliseconds = event.durationMilliseconds ?? -1

        logger.info(
            "category=\(event.category.rawValue, privacy: .public) operation=\(event.operation, privacy: .public) status=\(statusCode, privacy: .public) request_id=\(requestID, privacy: .private(mask: .hash)) duration_ms=\(durationMilliseconds, privacy: .public)"
        )
    }
}

extension RedactingDiagnosticsClient {
    static func live(
        subsystem: String = Bundle.main.bundleIdentifier ?? "org.fynla.app",
        category: String = "diagnostics"
    ) -> RedactingDiagnosticsClient {
        RedactingDiagnosticsClient(
            sink: LoggerDiagnosticsClient(subsystem: subsystem, category: category)
        )
    }
}
