import Foundation
import Testing
@testable import Fynla

@Suite("Authentication coordinator")
struct AuthenticationCoordinatorTests {
    @Test @MainActor
    func decodedMandatoryPasswordLoginRetainsNativeAuthButCannotUnlockUntilCompletion() async throws {
        let completion = try AuthResponseDecoder.login(from: fixture("auth-immediate-success"))
        let harness = makeHarness(loginCompletion: completion)

        try await harness.coordinator.login(
            email: "example@example.test",
            password: "Example1!",
            deviceLabel: "Example iPhone"
        )

        #expect(await harness.events.values() == [
            "login", "exchange:bootstrap-example-immediate", "user:native-access",
        ])
        #expect(harness.coordinator.state == .passwordChangeRequired)
        #expect(harness.coordinator.mustChangePassword == true)
        #expect(harness.coordinator.authenticatedUser?.id == 101)
        #expect(harness.coordinator.credentials?.refreshToken == "native-refresh")
        #expect(harness.session.state == .passwordChangeRequired)
        #expect(await harness.coordinator.accessToken() == "native-access")

        let router = AppRouter(session: harness.session)
        #expect(!router.navigate(to: .dashboard))
        #expect(!router.navigate(to: .settings))

        #expect(harness.coordinator.completeMandatoryPasswordChange())
        #expect(harness.coordinator.state == .authenticated(mustChangePassword: false))
        #expect(harness.coordinator.mustChangePassword == false)
        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.credentials?.accessToken == "native-access")
        #expect(!harness.coordinator.completeMandatoryPasswordChange())
    }

    @Test @MainActor
    func verificationMFAAndRestorationBranchesCannotUnlockOrExchange() async throws {
        for outcome in [
            LoginOutcome.verification(
                challengeToken: "challenge",
                maskedEmail: "e***@example.test"
            ),
            .multiFactor(token: "mfa", maskedEmail: "e***@example.test"),
            .restorable(
                RestorationChallenge(
                    token: "restore",
                    deletedAt: "2026-07-16T12:00:00Z",
                    deletionReason: "user_requested",
                    deletionSource: "settings",
                    firstName: "Example"
                )
            ),
        ] {
            let harness = makeHarness(login: outcome)

            try await harness.coordinator.login(
                email: "example@example.test",
                password: "Example1!",
                deviceLabel: "Example iPhone"
            )

            let expectedEvents = if case .restorable = outcome {
                ["login", "restore-check"]
            } else {
                ["login"]
            }
            #expect(await harness.events.values() == expectedEvents)
            #expect(harness.coordinator.credentials == nil)
            #expect(harness.coordinator.authenticatedUser == nil)
            #expect(harness.session.state != .authenticatedUnlocked)
            switch outcome {
            case .verification:
                #expect(harness.session.state == .verificationRequired)
            case .multiFactor:
                #expect(harness.session.state == .multiFactorRequired)
            case .restorable:
                #expect(harness.session.state == .restorationRequired)
                if case let .restoration(challenge) = harness.coordinator.state {
                    #expect(challenge.token == "checked-restoration")
                    #expect(challenge.requiresMFA)
                } else {
                    Issue.record("Expected checked restoration challenge")
                }
            case .authenticated:
                Issue.record("Unexpected authenticated case")
            }
        }
    }

    @Test @MainActor
    func registrationVerificationCompletesThroughExchangeAndUserFetch() async throws {
        let harness = makeHarness()
        let input = RegistrationInput(
            firstName: "Example",
            middleName: nil,
            surname: "User",
            email: "example@example.test",
            password: "Example1!",
            passwordConfirmation: "Example1!"
        )

        try await harness.coordinator.register(input)
        #expect(harness.session.state == .verificationRequired)
        #expect(harness.coordinator.state == .registrationVerification(
            RegistrationChallenge(pendingID: 321, maskedEmail: "e***@example.test")
        ))

        try await harness.coordinator.verifyRegistration(
            RegistrationVerificationInput(code: "123456", pendingID: 321),
            deviceLabel: "Example iPhone"
        )

        #expect(await harness.events.values() == [
            "register", "verify-registration", "exchange:bootstrap-registration", "user:native-access",
        ])
        #expect(harness.session.state == .authenticatedUnlocked)
    }

    @Test @MainActor
    func registrationResendUsesPendingChallengeWithoutBypassingCoordinatorState() async throws {
        let harness = makeHarness()
        let input = RegistrationInput(
            firstName: "Example",
            middleName: nil,
            surname: "User",
            email: "example@example.test",
            password: "Example1!",
            passwordConfirmation: "Example1!"
        )
        try await harness.coordinator.register(input)

        let message = try await harness.coordinator.resendRegistration()

        #expect(message == "Verification code sent")
        #expect(harness.coordinator.state == .registrationVerification(
            RegistrationChallenge(pendingID: 321, maskedEmail: "e***@example.test")
        ))
        #expect(harness.session.state == .verificationRequired)
        #expect(await harness.events.values() == ["register", "resend-registration:321"])
    }

    @Test @MainActor
    func loginVerificationMFAAndRecoverySuccessesAllUseTheFullAuthenticationPipeline() async throws {
        let cases: [(LoginOutcome, CompletionAction, String)] = [
            (
                .verification(challengeToken: "challenge", maskedEmail: "e***@example.test"),
                .verification,
                "bootstrap-verification"
            ),
            (
                .multiFactor(token: "mfa", maskedEmail: "e***@example.test"),
                .mfa,
                "bootstrap-mfa"
            ),
            (
                .multiFactor(token: "mfa", maskedEmail: "e***@example.test"),
                .recovery,
                "bootstrap-recovery"
            ),
        ]

        for (outcome, action, bootstrap) in cases {
            let harness = makeHarness(login: outcome)
            try await harness.coordinator.login(
                email: "example@example.test",
                password: "Example1!",
                deviceLabel: "Example iPhone"
            )

            switch action {
            case .verification:
                try await harness.coordinator.verifyLogin(code: "123456", deviceLabel: "Example iPhone")
            case .mfa:
                try await harness.coordinator.verifyMFA(code: "123456", deviceLabel: "Example iPhone")
            case .recovery:
                try await harness.coordinator.useRecoveryCode("recovery", deviceLabel: "Example iPhone")
            }

            let events = await harness.events.values()
            #expect(events.suffix(2) == ["exchange:\(bootstrap)", "user:native-access"])
            if action == .verification {
                #expect(harness.coordinator.state == .passwordChangeRequired)
                #expect(harness.session.state == .passwordChangeRequired)
                #expect(harness.coordinator.credentials?.accessToken == "native-access")
                #expect(harness.coordinator.completeMandatoryPasswordChange())
            }
            #expect(harness.session.state == .authenticatedUnlocked)
        }
    }

    @Test @MainActor
    func consumedMFAChallengeFailureRequiresFreshFullLogin() async throws {
        for action in [CompletionAction.mfa, .recovery] {
            let harness = makeHarness(
                login: .multiFactor(token: "one-time-mfa", maskedEmail: "e***@example.test"),
                mfaError: AuthError.unauthenticated(
                    message: "Invalid or expired verification request."
                )
            )
            try await harness.coordinator.login(
                email: "example@example.test",
                password: "Example1!",
                deviceLabel: "Example iPhone"
            )

            await expectCoordinatorError(.fullLoginRequired) {
                switch action {
                case .mfa:
                    try await harness.coordinator.verifyMFA(
                        code: "000000",
                        deviceLabel: "Example iPhone"
                    )
                case .recovery:
                    try await harness.coordinator.useRecoveryCode(
                        "invalid-recovery",
                        deviceLabel: "Example iPhone"
                    )
                case .verification:
                    Issue.record("Unexpected verification case")
                }
            }

            #expect(harness.coordinator.state == .fullLoginRequired)
            #expect(harness.session.state == .signedOut)
        }
    }

    @Test @MainActor
    func checkedRestorationCompletesThroughExchangeAndCurrentUserGate() async throws {
        let harness = makeHarness(login: .restorable(RestorationChallenge(
            token: "untrusted-login-token",
            deletedAt: "2026-07-16T12:00:00Z",
            deletionReason: "user_requested",
            deletionSource: "settings",
            firstName: "Example"
        )))
        try await harness.coordinator.login(
            email: "example@example.test",
            password: "Example1!",
            deviceLabel: "Example iPhone"
        )

        try await harness.coordinator.restoreAccount(
            mfaCode: "123456",
            deviceLabel: "Example iPhone"
        )

        #expect(await harness.events.values() == [
            "login", "restore-check", "restore:checked-restoration:123456",
            "exchange:bootstrap-restoration", "user:native-access",
        ])
        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.authenticatedUser?.id == 101)
    }

    @Test @MainActor
    func exchangeFailureClearsEverythingAndRequiresFullLogin() async {
        let harness = makeHarness(
            login: .authenticated(bootstrapAccessToken: "bootstrap-immediate"),
            exchangeError: TestAuthenticationFailure.expected
        )

        await expectCoordinatorError(.fullLoginRequired) {
            try await harness.coordinator.login(
                email: "example@example.test",
                password: "Example1!",
                deviceLabel: "Example iPhone"
            )
        }

        assertCleared(harness)
        #expect(await harness.events.values() == ["login", "exchange:bootstrap-immediate"])
    }

    @Test @MainActor
    func userFetchFailureClearsNativeCredentialsAndRequiresFullLogin() async {
        let harness = makeHarness(
            login: .authenticated(bootstrapAccessToken: "bootstrap-immediate"),
            userError: TestAuthenticationFailure.expected
        )

        await expectCoordinatorError(.fullLoginRequired) {
            try await harness.coordinator.login(
                email: "example@example.test",
                password: "Example1!",
                deviceLabel: "Example iPhone"
            )
        }

        assertCleared(harness)
        #expect(await harness.events.values() == [
            "login", "exchange:bootstrap-immediate", "user:native-access",
        ])
    }

    @Test @MainActor
    func freshCoordinatorHasNoCredentialAndDeclinedBiometricsRemainMemoryOnly() async throws {
        let fresh = makeHarness()
        #expect(fresh.coordinator.credentials == nil)
        #expect(fresh.coordinator.inMemoryRefreshCredential == nil)
        #expect(await fresh.coordinator.accessToken() == nil)

        let authenticated = makeHarness(
            login: .authenticated(bootstrapAccessToken: "bootstrap-immediate")
        )
        try await authenticated.coordinator.login(
            email: "example@example.test",
            password: "Example1!",
            deviceLabel: "Example iPhone"
        )
        authenticated.coordinator.declineBiometricPersistence()

        #expect(authenticated.coordinator.refreshPersistence == .memoryOnly)
        #expect(authenticated.coordinator.inMemoryRefreshCredential?.token == "native-refresh")

        let restarted = makeHarness()
        #expect(restarted.coordinator.inMemoryRefreshCredential == nil)
        #expect(restarted.session.state == .signedOut)
    }

    @Test @MainActor
    func duplicateSubmissionIsRejectedBeforeItCanCommitStaleCredentials() async throws {
        let gate = AsyncGate()
        let harness = makeHarness(
            login: .authenticated(bootstrapAccessToken: "bootstrap-immediate"),
            loginGate: gate
        )
        let first = Task { @MainActor in
            try await harness.coordinator.login(
                email: "first@example.test",
                password: "Example1!",
                deviceLabel: "Example iPhone"
            )
        }
        while harness.coordinator.state != .submitting {
            await Task.yield()
        }

        await expectCoordinatorError(.busy) {
            try await harness.coordinator.login(
                email: "second@example.test",
                password: "Example1!",
                deviceLabel: "Example iPhone"
            )
        }
        await gate.open()
        try await first.value

        #expect(await harness.events.values().filter { $0 == "login" }.count == 1)
        #expect(harness.coordinator.credentials?.accessToken == "native-access")
    }

    @Test @MainActor
    func cancelledSubmissionCannotCommitLateCredentials() async {
        let harness = makeHarness(
            login: .authenticated(bootstrapAccessToken: "bootstrap-immediate"),
            loginDelay: .seconds(30)
        )
        let task = Task { @MainActor in
            try await harness.coordinator.login(
                email: "example@example.test",
                password: "Example1!",
                deviceLabel: "Example iPhone"
            )
        }
        while harness.coordinator.state != .submitting {
            await Task.yield()
        }
        task.cancel()

        do {
            try await task.value
            Issue.record("Expected cancellation")
        } catch let error as AuthenticationCoordinatorError {
            #expect(error == .cancelled)
        } catch {
            Issue.record("Unexpected cancellation error")
        }

        #expect(harness.coordinator.credentials == nil)
        #expect(harness.coordinator.authenticatedUser == nil)
        #expect(harness.session.state == .signedOut)
    }

    @Test @MainActor
    func signOutInvalidatesOldAttemptWithoutClearingNewSuccessfulLogin() async throws {
        let gate = AsyncGate()
        let events = EventLog()
        let client = SupersedingAuthClient(gate: gate, events: events)
        let session = AppSession(state: .signedOut)
        let coordinator = AuthenticationCoordinator(
            appSession: session,
            authClient: client,
            currentUserClient: client
        )

        let first = Task { @MainActor in
            try await coordinator.login(
                email: "first@example.test",
                password: "Example1!",
                deviceLabel: "First iPhone"
            )
        }
        while coordinator.state != .submitting {
            await Task.yield()
        }

        coordinator.signOut()
        try await coordinator.login(
            email: "second@example.test",
            password: "Example1!",
            deviceLabel: "Second iPhone"
        )
        #expect(coordinator.credentials?.accessToken == "native-access-second")
        #expect(coordinator.authenticatedUser?.id == 202)
        #expect(session.state == .authenticatedUnlocked)

        await gate.open()
        await expectCoordinatorError(.cancelled) {
            try await first.value
        }

        #expect(coordinator.credentials?.accessToken == "native-access-second")
        #expect(coordinator.credentials?.refreshToken == "native-refresh-second")
        #expect(coordinator.authenticatedUser?.id == 202)
        #expect(coordinator.state == .authenticated(mustChangePassword: false))
        #expect(session.state == .authenticatedUnlocked)
        #expect(await events.values() == [
            "login:first@example.test",
            "login:second@example.test",
            "exchange:bootstrap-second",
            "user:native-access-second",
        ])
    }

    @Test
    func authenticationSourcesContainNoCredentialPersistenceOrDiagnostics() throws {
        let testDirectory = URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
        let repositorySourceRoot = testDirectory
            .deletingLastPathComponent()
            .appending(path: "Fynla/Core/Authentication")
        let hostSourceRoot = testDirectory
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .appending(path: "Sources/Fynla")
        let sourceRoot = FileManager.default.fileExists(
            atPath: repositorySourceRoot.path
        ) ? repositorySourceRoot : hostSourceRoot
        let names = ["AuthModels.swift", "AuthClient.swift", "AuthenticationCoordinator.swift"]
        for name in names {
            let source = try String(contentsOf: sourceRoot.appending(path: name), encoding: .utf8)
            #expect(!source.contains("UserDefaults"))
            #expect(!source.contains("print("))
            #expect(!source.contains("DiagnosticsClient"))
            #expect(!source.contains("OSLog"))
        }
    }

    @MainActor
    private func makeHarness(
        login: LoginOutcome = .verification(
            challengeToken: "challenge",
            maskedEmail: "e***@example.test"
        ),
        mustChange: Bool? = nil,
        exchangeError: (any Error)? = nil,
        userError: (any Error)? = nil,
        mfaError: (any Error)? = nil,
        loginGate: AsyncGate? = nil,
        loginDelay: Duration? = nil,
        loginCompletion: LoginCompletion? = nil
    ) -> CoordinatorHarness {
        let events = EventLog()
        let auth = ScriptedAuthClient(
            events: events,
            login: loginCompletion ?? LoginCompletion(
                outcome: login,
                mustChangePassword: mustChange
            ),
            exchangeError: exchangeError,
            mfaError: mfaError,
            loginGate: loginGate,
            loginDelay: loginDelay
        )
        let users = ScriptedCurrentUserClient(events: events, error: userError)
        let session = AppSession(state: .signedOut)
        let coordinator = AuthenticationCoordinator(
            appSession: session,
            authClient: auth,
            currentUserClient: users
        )
        return CoordinatorHarness(
            coordinator: coordinator,
            session: session,
            events: events
        )
    }

    @MainActor
    private func assertCleared(_ harness: CoordinatorHarness) {
        #expect(harness.coordinator.state == .fullLoginRequired)
        #expect(harness.coordinator.credentials == nil)
        #expect(harness.coordinator.inMemoryRefreshCredential == nil)
        #expect(harness.coordinator.authenticatedUser == nil)
        #expect(harness.session.state == .signedOut)
    }

    @MainActor
    private func expectCoordinatorError<Value>(
        _ expected: AuthenticationCoordinatorError,
        operation: () async throws -> Value
    ) async {
        do {
            _ = try await operation()
            Issue.record("Expected coordinator error")
        } catch let error as AuthenticationCoordinatorError {
            #expect(error == expected)
        } catch {
            Issue.record("Unexpected coordinator error type")
        }
    }

    private enum CompletionAction: Equatable {
        case verification
        case mfa
        case recovery
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(contentsOf: URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/API/\(name).json"))
    }
}

@MainActor
private struct CoordinatorHarness {
    let coordinator: AuthenticationCoordinator
    let session: AppSession
    let events: EventLog
}

private enum TestAuthenticationFailure: Error {
    case expected
}

private actor EventLog {
    private var events: [String] = []

    func append(_ event: String) {
        events.append(event)
    }

    func values() -> [String] {
        events
    }
}

private actor AsyncGate {
    private var isOpen = false
    private var continuations: [CheckedContinuation<Void, Never>] = []

    func wait() async {
        if isOpen { return }
        await withCheckedContinuation { continuation in
            continuations.append(continuation)
        }
    }

    func open() {
        isOpen = true
        let waiting = continuations
        continuations.removeAll()
        for continuation in waiting {
            continuation.resume()
        }
    }
}

private actor ScriptedAuthClient: AuthCompletionClient {
    private let events: EventLog
    private let loginValue: LoginCompletion
    private let exchangeError: (any Error)?
    private let mfaError: (any Error)?
    private let loginGate: AsyncGate?
    private let loginDelay: Duration?

    init(
        events: EventLog,
        login: LoginCompletion,
        exchangeError: (any Error)?,
        mfaError: (any Error)?,
        loginGate: AsyncGate?,
        loginDelay: Duration?
    ) {
        self.events = events
        self.loginValue = login
        self.exchangeError = exchangeError
        self.mfaError = mfaError
        self.loginGate = loginGate
        self.loginDelay = loginDelay
    }

    func register(_ input: RegistrationInput) async throws -> RegistrationChallenge {
        await events.append("register")
        return RegistrationChallenge(pendingID: 321, maskedEmail: "e***@example.test")
    }

    func loginCompletion(email: String, password: String) async throws -> LoginCompletion {
        await events.append("login")
        if let loginGate { await loginGate.wait() }
        if let loginDelay { try await Task.sleep(for: loginDelay) }
        return loginValue
    }

    func verifyRegistrationCompletion(
        _ input: RegistrationVerificationInput
    ) async throws -> BootstrapAuthentication {
        await events.append("verify-registration")
        return BootstrapAuthentication(
            bootstrapAccessToken: "bootstrap-registration",
            mustChangePassword: false
        )
    }

    func resendRegistration(pendingID: Int) async throws -> String {
        await events.append("resend-registration:\(pendingID)")
        return "Verification code sent"
    }

    func verifyLoginCompletion(
        code: String,
        challengeToken: String
    ) async throws -> BootstrapAuthentication {
        await events.append("verify-login")
        return BootstrapAuthentication(
            bootstrapAccessToken: "bootstrap-verification",
            mustChangePassword: true
        )
    }

    func verifyMFACompletion(code: String, token: String) async throws -> BootstrapAuthentication {
        await events.append("verify-mfa")
        if let mfaError { throw mfaError }
        return BootstrapAuthentication(
            bootstrapAccessToken: "bootstrap-mfa",
            mustChangePassword: nil
        )
    }

    func useRecoveryCodeCompletion(
        _ code: String,
        token: String
    ) async throws -> BootstrapAuthentication {
        await events.append("recovery")
        if let mfaError { throw mfaError }
        return BootstrapAuthentication(
            bootstrapAccessToken: "bootstrap-recovery",
            mustChangePassword: nil
        )
    }

    func resendLogin(challengeToken: String) async throws -> LoginResendResult {
        await events.append("resend-login:\(challengeToken)")
        return LoginResendResult(
            message: "Verification code sent",
            canResend: true,
            remainingResends: 1
        )
    }

    func checkRestoration(email: String, password: String) async throws -> RestorationSession {
        await events.append("restore-check")
        return RestorationSession(token: "checked-restoration", requiresMFA: true)
    }

    func restoreAccount(
        token: String,
        mfaCode: String?
    ) async throws -> BootstrapAuthentication {
        await events.append("restore:\(token):\(mfaCode ?? "none")")
        return BootstrapAuthentication(
            bootstrapAccessToken: "bootstrap-restoration",
            mustChangePassword: false
        )
    }

    func exchange(bootstrapToken: String, deviceLabel: String) async throws -> NativeCredentials {
        await events.append("exchange:\(bootstrapToken)")
        if let exchangeError { throw exchangeError }
        return NativeCredentials(
            accessToken: "native-access",
            accessExpiresAt: "2026-07-17T12:15:00Z",
            refreshToken: "native-refresh",
            refreshExpiresAt: "2026-08-16T12:00:00Z",
            absoluteExpiresAt: "2026-10-15T12:00:00Z",
            sessionID: "11111111-2222-3333-4444-555555555555"
        )
    }
}

private actor ScriptedCurrentUserClient: CurrentUserClient {
    private let events: EventLog
    private let error: (any Error)?

    init(events: EventLog, error: (any Error)?) {
        self.events = events
        self.error = error
    }

    func currentUser(accessToken: String) async throws -> AuthenticatedUser {
        await events.append("user:\(accessToken)")
        if let error { throw error }
        return AuthenticatedUser(
            id: 101,
            firstName: "Example",
            surname: "User",
            name: "Example User",
            email: "example@example.test"
        )
    }
}

private actor SupersedingAuthClient: AuthCompletionClient, CurrentUserClient {
    private let gate: AsyncGate
    private let events: EventLog

    init(gate: AsyncGate, events: EventLog) {
        self.gate = gate
        self.events = events
    }

    func register(_ input: RegistrationInput) async throws -> RegistrationChallenge {
        throw TestAuthenticationFailure.expected
    }

    func loginCompletion(email: String, password: String) async throws -> LoginCompletion {
        await events.append("login:\(email)")
        if email == "first@example.test" {
            await gate.wait()
            return LoginCompletion(
                outcome: .authenticated(bootstrapAccessToken: "bootstrap-first"),
                mustChangePassword: false
            )
        }
        return LoginCompletion(
            outcome: .authenticated(bootstrapAccessToken: "bootstrap-second"),
            mustChangePassword: false
        )
    }

    func verifyRegistrationCompletion(
        _ input: RegistrationVerificationInput
    ) async throws -> BootstrapAuthentication {
        throw TestAuthenticationFailure.expected
    }

    func resendRegistration(pendingID: Int) async throws -> String {
        throw TestAuthenticationFailure.expected
    }

    func verifyLoginCompletion(
        code: String,
        challengeToken: String
    ) async throws -> BootstrapAuthentication {
        throw TestAuthenticationFailure.expected
    }

    func verifyMFACompletion(code: String, token: String) async throws -> BootstrapAuthentication {
        throw TestAuthenticationFailure.expected
    }

    func useRecoveryCodeCompletion(
        _ code: String,
        token: String
    ) async throws -> BootstrapAuthentication {
        throw TestAuthenticationFailure.expected
    }

    func resendLogin(challengeToken: String) async throws -> LoginResendResult {
        throw TestAuthenticationFailure.expected
    }

    func checkRestoration(email: String, password: String) async throws -> RestorationSession {
        throw TestAuthenticationFailure.expected
    }

    func restoreAccount(
        token: String,
        mfaCode: String?
    ) async throws -> BootstrapAuthentication {
        throw TestAuthenticationFailure.expected
    }

    func exchange(
        bootstrapToken: String,
        deviceLabel: String
    ) async throws -> NativeCredentials {
        let suffix = bootstrapToken == "bootstrap-second" ? "second" : "first"
        await events.append("exchange:\(bootstrapToken)")
        return NativeCredentials(
            accessToken: "native-access-\(suffix)",
            accessExpiresAt: "2026-07-17T12:15:00Z",
            refreshToken: "native-refresh-\(suffix)",
            refreshExpiresAt: "2026-08-16T12:00:00Z",
            absoluteExpiresAt: "2026-10-15T12:00:00Z",
            sessionID: suffix == "second"
                ? "22222222-2222-2222-2222-222222222222"
                : "11111111-1111-1111-1111-111111111111"
        )
    }

    func currentUser(accessToken: String) async throws -> AuthenticatedUser {
        await events.append("user:\(accessToken)")
        let isSecond = accessToken == "native-access-second"
        return AuthenticatedUser(
            id: isSecond ? 202 : 101,
            firstName: isSecond ? "Second" : "First",
            surname: "User",
            name: isSecond ? "Second User" : "First User",
            email: isSecond ? "second@example.test" : "first@example.test"
        )
    }
}
