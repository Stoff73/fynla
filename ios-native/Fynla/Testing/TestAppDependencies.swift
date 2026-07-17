#if FYNLA_UI_TESTING
import Foundation

enum UITestMode: String, Sendable {
    case signedOut = "signed-out"
    case unlocked
    case designSystem = "design-system"
    case registrationSuccess = "registration-success"
    case registrationFieldErrors = "registration-field-errors"
    case registrationDuplicateEmail = "registration-duplicate-email"
    case registrationWrongCode = "registration-wrong-code"
    case registrationExpired = "registration-expired"
    case registrationResendExhausted = "registration-resend-exhausted"
    case registrationLargeText = "registration-large-text"

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
        case .registrationSuccess,
             .registrationFieldErrors,
             .registrationDuplicateEmail,
             .registrationWrongCode,
             .registrationExpired,
             .registrationResendExhausted,
             .registrationLargeText:
            .signedOut
        }
    }

    var showsDesignSystem: Bool {
        self == .designSystem
    }

    var registrationScenario: RegistrationUITestScenario? {
        switch self {
        case .registrationSuccess:
            .success
        case .registrationFieldErrors:
            .fieldErrors
        case .registrationDuplicateEmail:
            .duplicateEmail
        case .registrationWrongCode:
            .wrongCode
        case .registrationExpired:
            .expired
        case .registrationResendExhausted:
            .resendExhausted
        case .registrationLargeText:
            .success
        case .signedOut,
             .unlocked,
             .designSystem:
            nil
        }
    }
}

enum RegistrationUITestScenario: Sendable {
    case success
    case fieldErrors
    case duplicateEmail
    case wrongCode
    case expired
    case resendExhausted

    @MainActor
    func actions(session: AppSession) -> RegistrationActions {
        RegistrationActions(
            register: { _ in
                switch self {
                case .fieldErrors:
                    throw AuthError.validation(
                        message: "The given data was invalid.",
                        errors: [
                            "first_name": ["First name could not be accepted."],
                            "email": ["Enter a different email address."],
                        ]
                    )
                case .duplicateEmail:
                    throw AuthError.validation(
                        message: "An account with this email address already exists. Please sign in or reset your password.",
                        errors: [:]
                    )
                case .success,
                     .wrongCode,
                     .expired,
                     .resendExhausted:
                    guard session.beginAuthentication(), session.requireVerification() else {
                        throw AuthenticationCoordinatorError.fullLoginRequired
                    }
                    return RegistrationChallenge(
                        pendingID: 321,
                        maskedEmail: "e***@example.test"
                    )
                }
            },
            verify: { _ in
                switch self {
                case .wrongCode:
                    throw AuthError.validation(
                        message: "Invalid verification code",
                        errors: [:]
                    )
                case .expired:
                    throw AuthError.validation(
                        message: "Verification code has expired. Please register again.",
                        errors: [:]
                    )
                case .success,
                     .resendExhausted:
                    guard session.completeAuthentication(), session.unlock() else {
                        throw AuthenticationCoordinatorError.fullLoginRequired
                    }
                case .fieldErrors,
                     .duplicateEmail:
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
            },
            resend: { _ in
                if self == .resendExhausted {
                    throw AuthError.resendExhausted(
                        message: "Maximum resend limit reached. Please refresh and try again."
                    )
                }
                return "Verification code sent"
            },
            startOver: {
                session.signOut()
            }
        )
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
