#if FYNLA_UI_TESTING
import Foundation

enum UITestMode: String, Sendable {
    case liveLaunch = "live-launch"
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
    case loginSuccess = "login-success"
    case loginVerification = "login-verification"
    case loginMultiFactor = "login-mfa"
    case loginRestoration = "login-restoration"
    case loginRestorationWithoutMFA = "login-restoration-no-mfa"
    case loginLockout = "login-lockout"
    case passwordReset = "password-reset"
    case passwordResetMultiFactor = "password-reset-mfa"

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
        case .liveLaunch:
            .launching
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
             .registrationLargeText,
             .loginSuccess,
             .loginVerification,
             .loginMultiFactor,
             .loginRestoration,
             .loginRestorationWithoutMFA,
             .loginLockout,
             .passwordReset,
             .passwordResetMultiFactor:
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
            .largeText
        case .liveLaunch,
             .signedOut,
             .unlocked,
             .designSystem,
             .loginSuccess,
             .loginVerification,
             .loginMultiFactor,
             .loginRestoration,
             .loginRestorationWithoutMFA,
             .loginLockout,
             .passwordReset,
             .passwordResetMultiFactor:
            nil
        }
    }

    var loginScenario: LoginUITestScenario? {
        switch self {
        case .loginSuccess:
            .success
        case .loginVerification:
            .verification
        case .loginMultiFactor:
            .multiFactor
        case .loginRestoration:
            .restoration
        case .loginRestorationWithoutMFA:
            .restorationWithoutMFA
        case .loginLockout:
            .lockout
        case .liveLaunch,
             .signedOut,
             .unlocked,
             .designSystem,
             .registrationSuccess,
             .registrationFieldErrors,
             .registrationDuplicateEmail,
             .registrationWrongCode,
             .registrationExpired,
             .registrationResendExhausted,
             .registrationLargeText,
             .passwordReset,
             .passwordResetMultiFactor:
            nil
        }
    }

    var passwordResetScenario: PasswordResetUITestScenario? {
        switch self {
        case .passwordReset:
            .withoutMultiFactor
        case .passwordResetMultiFactor:
            .withMultiFactor
        case .liveLaunch,
             .signedOut,
             .unlocked,
             .designSystem,
             .registrationSuccess,
             .registrationFieldErrors,
             .registrationDuplicateEmail,
             .registrationWrongCode,
             .registrationExpired,
             .registrationResendExhausted,
             .registrationLargeText,
             .loginSuccess,
             .loginVerification,
             .loginMultiFactor,
             .loginRestoration,
             .loginRestorationWithoutMFA,
             .loginLockout:
            nil
        }
    }
}

enum LoginUITestScenario: Sendable, Equatable {
    case success
    case verification
    case multiFactor
    case restoration
    case restorationWithoutMFA
    case lockout
}

actor LoginUITestAuthClient: AuthCompletionClient, CurrentUserClient {
    private let scenario: LoginUITestScenario

    init(scenario: LoginUITestScenario) {
        self.scenario = scenario
    }

    func register(_ input: RegistrationInput) async throws -> RegistrationChallenge {
        throw TestDependencyError.unexpectedNetworkRequest
    }

    func verifyRegistrationCompletion(
        _ input: RegistrationVerificationInput
    ) async throws -> BootstrapAuthentication {
        throw TestDependencyError.unexpectedNetworkRequest
    }

    func resendRegistration(pendingID: Int) async throws -> String {
        throw TestDependencyError.unexpectedNetworkRequest
    }

    func loginCompletion(email: String, password: String) async throws -> LoginCompletion {
        switch scenario {
        case .success:
            LoginCompletion(
                outcome: .authenticated(bootstrapAccessToken: "ui-bootstrap"),
                mustChangePassword: false
            )
        case .verification:
            LoginCompletion(
                outcome: .verification(
                    challengeToken: "ui-login-challenge",
                    maskedEmail: "e***@example.test"
                ),
                mustChangePassword: nil
            )
        case .multiFactor:
            LoginCompletion(
                outcome: .multiFactor(
                    token: "ui-mfa-token",
                    maskedEmail: "e***@example.test"
                ),
                mustChangePassword: nil
            )
        case .restoration, .restorationWithoutMFA:
            LoginCompletion(
                outcome: .restorable(RestorationChallenge(
                    token: "untrusted-ui-restoration-token",
                    deletedAt: "2026-07-16T12:00:00Z",
                    deletionReason: "user_requested",
                    deletionSource: "settings",
                    firstName: "Example"
                )),
                mustChangePassword: nil
            )
        case .lockout:
            throw AuthError.locked(
                message: "Account temporarily locked. Try again in 1 minute(s).",
                remainingSeconds: 60
            )
        }
    }

    func verifyLoginCompletion(
        code: String,
        challengeToken: String
    ) async throws -> BootstrapAuthentication {
        guard scenario == .verification else {
            throw TestDependencyError.unexpectedNetworkRequest
        }
        return completedAuthentication()
    }

    func verifyMFACompletion(
        code: String,
        token: String
    ) async throws -> BootstrapAuthentication {
        guard scenario == .multiFactor else {
            throw TestDependencyError.unexpectedNetworkRequest
        }
        return completedAuthentication()
    }

    func useRecoveryCodeCompletion(
        _ code: String,
        token: String
    ) async throws -> BootstrapAuthentication {
        guard scenario == .multiFactor else {
            throw TestDependencyError.unexpectedNetworkRequest
        }
        return completedAuthentication()
    }

    func resendLogin(challengeToken: String) async throws -> LoginResendResult {
        guard scenario == .verification else {
            throw TestDependencyError.unexpectedNetworkRequest
        }
        return LoginResendResult(
            message: "Verification code sent",
            canResend: true,
            remainingResends: 1
        )
    }

    func checkRestoration(email: String, password: String) async throws -> RestorationSession {
        guard scenario == .restoration || scenario == .restorationWithoutMFA else {
            throw TestDependencyError.unexpectedNetworkRequest
        }
        return RestorationSession(
            token: "checked-ui-restoration-token",
            requiresMFA: scenario == .restoration
        )
    }

    func restoreAccount(
        token: String,
        mfaCode: String?
    ) async throws -> BootstrapAuthentication {
        guard token == "checked-ui-restoration-token",
              (scenario == .restoration && mfaCode == "123456")
                || (scenario == .restorationWithoutMFA && mfaCode == nil)
        else {
            throw TestDependencyError.unexpectedNetworkRequest
        }
        return completedAuthentication()
    }

    func exchange(bootstrapToken: String, deviceLabel: String) async throws -> NativeCredentials {
        NativeCredentials(
            accessToken: "ui-native-access",
            accessExpiresAt: "2026-07-17T12:15:00Z",
            refreshToken: "ui-native-refresh",
            refreshExpiresAt: "2026-08-16T12:00:00Z",
            absoluteExpiresAt: "2026-10-15T12:00:00Z",
            sessionID: "11111111-2222-3333-4444-555555555555"
        )
    }

    func currentUser(accessToken: String) async throws -> AuthenticatedUser {
        guard accessToken == "ui-native-access" else {
            throw TestDependencyError.unexpectedNetworkRequest
        }
        return AuthenticatedUser(
            id: 101,
            firstName: "Example",
            surname: "User",
            name: "Example User",
            email: "example@example.test"
        )
    }

    private func completedAuthentication() -> BootstrapAuthentication {
        BootstrapAuthentication(
            bootstrapAccessToken: "ui-bootstrap",
            mustChangePassword: false
        )
    }
}

enum PasswordResetUITestScenario: Sendable, Equatable {
    case withoutMultiFactor
    case withMultiFactor

    @MainActor
    func actions(session: AppSession) -> PasswordResetActions {
        PasswordResetActions(
            request: { _ in
                PasswordResetRequestResult(
                    message: "Verification code sent",
                    resetToken: "ui-reset-token"
                )
            },
            verifyEmail: { _, _ in
                PasswordResetEmailVerification(
                    requiresMFA: self == .withMultiFactor,
                    canResetPassword: self == .withoutMultiFactor
                )
            },
            resend: { _ in
                PasswordResetResendResult(
                    message: "Verification code sent",
                    remainingResends: 1
                )
            },
            verifyMFA: { _, _ in
                PasswordResetAuthorization(canResetPassword: true)
            },
            useRecoveryCode: { _, _ in
                PasswordResetAuthorization(canResetPassword: true)
            },
            reset: { _, _, _ in "Your password has been reset successfully." },
            cancel: { session.signOut() }
        )
    }
}

enum RegistrationUITestScenario: Sendable {
    case success
    case fieldErrors
    case duplicateEmail
    case wrongCode
    case expired
    case resendExhausted
    case largeText

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
                     .resendExhausted,
                     .largeText:
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
                    throw AuthError.registrationUnavailable(
                        message: "Verification code has expired. Please register again."
                    )
                case .largeText:
                    throw AuthError.registrationUnavailable(
                        message: "Registration is no longer available. Please register again."
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
