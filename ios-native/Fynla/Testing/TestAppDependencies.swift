#if DEBUG
import Foundation

enum UITestMode: String, Sendable {
    case signedOut = "signed-out"
    case unlocked
    case designSystem = "design-system"

    init?(arguments: [String]) {
        guard let flagIndex = arguments.firstIndex(of: "-fynla-ui-test-mode"),
              arguments.filter({ $0 == "-fynla-ui-test-mode" }).count == 1,
              arguments.indices.contains(flagIndex + 1)
        else {
            return nil
        }

        self.init(rawValue: arguments[flagIndex + 1])
    }

    var initialSessionState: AppSession.State {
        switch self {
        case .signedOut:
            .signedOut
        case .unlocked:
            .authenticatedUnlocked
        case .designSystem:
            .launching
        }
    }

    var showsDesignSystem: Bool {
        self == .designSystem
    }
}

enum TestDependencyError: Error, Equatable, Sendable {
    case unexpectedNetworkRequest
}

enum TestAppDependencies {
    static func make() -> AppDependencies {
        AppDependencies(
            environment: try! AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            appVersion: "1.0.0",
            appBuild: "1",
            httpTransport: FailingTestHTTPTransport(),
            diagnostics: SilentTestDiagnosticsClient(),
            accessTokenProvider: EmptyTestAccessTokenProvider(),
            clock: { Date(timeIntervalSince1970: 1_700_000_000) },
            requestID: { "ui-test-request" },
            featureClients: .foundation
        )
    }
}

private actor FailingTestHTTPTransport: HTTPTransport {
    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        throw TestDependencyError.unexpectedNetworkRequest
    }

    func byteStream(for request: URLRequest) async throws -> HTTPByteStream {
        throw TestDependencyError.unexpectedNetworkRequest
    }
}

private struct SilentTestDiagnosticsClient: DiagnosticsClient {
    func record(_ event: DiagnosticEvent) async {}
}

private struct EmptyTestAccessTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? {
        nil
    }
}
#endif
