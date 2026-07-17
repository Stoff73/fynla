import Observation

enum AuthenticationCoordinatorError: Error, Sendable, Equatable {
    case busy
    case cancelled
    case fullLoginRequired
}

enum RefreshCredentialPersistence: Sendable, Equatable {
    case memoryOnly
}

@MainActor
@Observable
final class AuthenticationCoordinator: AccessTokenProviding {
    enum State: Sendable, Equatable {
        case signedOut
        case submitting
        case registrationVerification(RegistrationChallenge)
        case loginVerification(challengeToken: String, maskedEmail: String)
        case multiFactor(token: String, maskedEmail: String)
        case restoration(RestorationChallenge)
        case passwordChangeRequired
        case authenticated(mustChangePassword: Bool?)
        case fullLoginRequired
    }

    private(set) var state: State = .signedOut
    private(set) var credentials: NativeCredentials?
    private(set) var authenticatedUser: AuthenticatedUser?
    private(set) var mustChangePassword: Bool?
    private(set) var refreshPersistence: RefreshCredentialPersistence?

    var inMemoryRefreshCredential: NativeRefreshCredential? {
        credentials?.refreshCredential
    }

    private let appSession: AppSession
    private let authClient: any AuthCompletionClient
    private let currentUserClient: any CurrentUserClient
    private var isSubmitting = false
    private var attemptGeneration = 0

    init(
        appSession: AppSession,
        authClient: any AuthCompletionClient,
        currentUserClient: any CurrentUserClient
    ) {
        self.appSession = appSession
        self.authClient = authClient
        self.currentUserClient = currentUserClient
    }

    func register(_ input: RegistrationInput) async throws {
        let attempt = try beginInitialAttempt()

        do {
            let challenge = try await authClient.register(input)
            try requireCurrentAttempt(attempt)
            guard appSession.requireVerification() else {
                throw AuthenticationCoordinatorError.fullLoginRequired
            }
            state = .registrationVerification(challenge)
            isSubmitting = false
        } catch let error as AuthenticationCoordinatorError {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            clearToSignedOut(ifCurrent: attempt)
            throw error
        } catch is CancellationError {
            clearToSignedOut(ifCurrent: attempt)
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            clearToSignedOut(ifCurrent: attempt)
            throw error
        }
    }

    func login(
        email: String,
        password: String,
        deviceLabel: String
    ) async throws {
        let attempt = try beginInitialAttempt()

        do {
            let completion = try await authClient.loginCompletion(
                email: email,
                password: password
            )
            try requireCurrentAttempt(attempt)

            switch completion.outcome {
            case let .verification(challengeToken, maskedEmail):
                guard appSession.requireVerification() else {
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
                state = .loginVerification(
                    challengeToken: challengeToken,
                    maskedEmail: maskedEmail
                )
                isSubmitting = false
            case let .multiFactor(token, maskedEmail):
                guard appSession.requireMultiFactor() else {
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
                state = .multiFactor(token: token, maskedEmail: maskedEmail)
                isSubmitting = false
            case let .restorable(challenge):
                guard appSession.requireRestoration() else {
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
                state = .restoration(challenge)
                isSubmitting = false
            case let .authenticated(bootstrapAccessToken):
                try await completeFullAuthentication(
                    BootstrapAuthentication(
                        bootstrapAccessToken: bootstrapAccessToken,
                        mustChangePassword: completion.mustChangePassword
                    ),
                    deviceLabel: deviceLabel,
                    attempt: attempt
                )
            }
        } catch let error as AuthenticationCoordinatorError {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            if error == .fullLoginRequired {
                clearForFullLoginRetry(ifCurrent: attempt)
            } else if error == .cancelled {
                clearToSignedOut(ifCurrent: attempt)
            }
            throw error
        } catch is CancellationError {
            clearToSignedOut(ifCurrent: attempt)
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            clearToSignedOut(ifCurrent: attempt)
            throw error
        }
    }

    func verifyRegistration(
        _ input: RegistrationVerificationInput,
        deviceLabel: String
    ) async throws {
        guard case .registrationVerification = state else {
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
        let pendingState = state
        let attempt = try beginPendingAttempt()

        do {
            let completion = try await authClient.verifyRegistrationCompletion(input)
            try requireCurrentAttempt(attempt)
            try await completeFullAuthentication(
                completion,
                deviceLabel: deviceLabel,
                attempt: attempt
            )
        } catch let error as AuthenticationCoordinatorError {
            if error == .cancelled {
                clearToSignedOut(ifCurrent: attempt)
            }
            throw error
        } catch is CancellationError {
            clearToSignedOut(ifCurrent: attempt)
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            restorePending(pendingState, ifCurrent: attempt)
            throw error
        }
    }

    func resendRegistration() async throws -> String {
        guard case let .registrationVerification(challenge) = state else {
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
        let pendingState = state
        let attempt = try beginPendingAttempt()

        do {
            let message = try await authClient.resendRegistration(
                pendingID: challenge.pendingID
            )
            try requireCurrentAttempt(attempt)
            restorePending(pendingState, ifCurrent: attempt)
            return message
        } catch let error as AuthenticationCoordinatorError {
            if error == .cancelled {
                clearToSignedOut(ifCurrent: attempt)
            }
            throw error
        } catch is CancellationError {
            clearToSignedOut(ifCurrent: attempt)
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            restorePending(pendingState, ifCurrent: attempt)
            throw error
        }
    }

    func verifyLogin(code: String, deviceLabel: String) async throws {
        guard case let .loginVerification(challengeToken, _) = state else {
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
        let pendingState = state
        let attempt = try beginPendingAttempt()

        do {
            let completion = try await authClient.verifyLoginCompletion(
                code: code,
                challengeToken: challengeToken
            )
            try requireCurrentAttempt(attempt)
            try await completeFullAuthentication(
                completion,
                deviceLabel: deviceLabel,
                attempt: attempt
            )
        } catch let error as AuthenticationCoordinatorError {
            if error == .cancelled {
                clearToSignedOut(ifCurrent: attempt)
            }
            throw error
        } catch is CancellationError {
            clearToSignedOut(ifCurrent: attempt)
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            restorePending(pendingState, ifCurrent: attempt)
            throw error
        }
    }

    func verifyMFA(code: String, deviceLabel: String) async throws {
        guard case let .multiFactor(token, _) = state else {
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
        let pendingState = state
        let attempt = try beginPendingAttempt()

        do {
            let completion = try await authClient.verifyMFACompletion(
                code: code,
                token: token
            )
            try requireCurrentAttempt(attempt)
            try await completeFullAuthentication(
                completion,
                deviceLabel: deviceLabel,
                attempt: attempt
            )
        } catch let error as AuthenticationCoordinatorError {
            if error == .cancelled {
                clearToSignedOut(ifCurrent: attempt)
            }
            throw error
        } catch is CancellationError {
            clearToSignedOut(ifCurrent: attempt)
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            restorePending(pendingState, ifCurrent: attempt)
            throw error
        }
    }

    func useRecoveryCode(_ code: String, deviceLabel: String) async throws {
        guard case let .multiFactor(token, _) = state else {
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
        let pendingState = state
        let attempt = try beginPendingAttempt()

        do {
            let completion = try await authClient.useRecoveryCodeCompletion(
                code,
                token: token
            )
            try requireCurrentAttempt(attempt)
            try await completeFullAuthentication(
                completion,
                deviceLabel: deviceLabel,
                attempt: attempt
            )
        } catch let error as AuthenticationCoordinatorError {
            if error == .cancelled {
                clearToSignedOut(ifCurrent: attempt)
            }
            throw error
        } catch is CancellationError {
            clearToSignedOut(ifCurrent: attempt)
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            restorePending(pendingState, ifCurrent: attempt)
            throw error
        }
    }

    func accessToken() async -> String? {
        credentials?.accessToken
    }

    func declineBiometricPersistence() {
        guard credentials != nil else { return }
        refreshPersistence = .memoryOnly
    }

    @discardableResult
    func completeMandatoryPasswordChange() -> Bool {
        guard state == .passwordChangeRequired,
              credentials != nil,
              authenticatedUser != nil,
              appSession.completeMandatoryPasswordChange()
        else {
            return false
        }

        mustChangePassword = false
        state = .authenticated(mustChangePassword: false)
        return true
    }

    func signOut() {
        attemptGeneration += 1
        clearToSignedOut()
    }

    private func beginInitialAttempt() throws -> Int {
        guard !isSubmitting, appSession.state == .signedOut,
              appSession.beginAuthentication()
        else {
            throw AuthenticationCoordinatorError.busy
        }
        attemptGeneration += 1
        isSubmitting = true
        state = .submitting
        return attemptGeneration
    }

    private func beginPendingAttempt() throws -> Int {
        guard !isSubmitting else {
            throw AuthenticationCoordinatorError.busy
        }
        attemptGeneration += 1
        isSubmitting = true
        state = .submitting
        return attemptGeneration
    }

    private func completeFullAuthentication(
        _ completion: BootstrapAuthentication,
        deviceLabel: String,
        attempt: Int
    ) async throws {
        do {
            try requireCurrentAttempt(attempt)
            let exchanged = try await authClient.exchange(
                bootstrapToken: completion.bootstrapAccessToken,
                deviceLabel: deviceLabel
            )
            try requireCurrentAttempt(attempt)
            let user = try await currentUserClient.currentUser(
                accessToken: exchanged.accessToken
            )
            try requireCurrentAttempt(attempt)

            if completion.mustChangePassword == true {
                guard appSession.requirePasswordChange() else {
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
            } else {
                guard appSession.completeAuthentication(), appSession.unlock() else {
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
            }

            credentials = exchanged
            authenticatedUser = user
            mustChangePassword = completion.mustChangePassword
            refreshPersistence = .memoryOnly
            state = completion.mustChangePassword == true
                ? .passwordChangeRequired
                : .authenticated(mustChangePassword: completion.mustChangePassword)
            isSubmitting = false
        } catch {
            guard isCurrentAttempt(attempt) else {
                throw AuthenticationCoordinatorError.cancelled
            }
            if error is CancellationError
                || error as? AuthenticationCoordinatorError == .cancelled
            {
                clearToSignedOut(ifCurrent: attempt)
                throw AuthenticationCoordinatorError.cancelled
            }
            clearForFullLoginRetry(ifCurrent: attempt)
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
    }

    private func requireCurrentAttempt(_ attempt: Int) throws {
        try Task.checkCancellation()
        guard isCurrentAttempt(attempt) else {
            throw AuthenticationCoordinatorError.cancelled
        }
    }

    private func isCurrentAttempt(_ attempt: Int) -> Bool {
        attempt == attemptGeneration
    }

    private func restorePending(_ pendingState: State, ifCurrent attempt: Int) {
        guard isCurrentAttempt(attempt) else { return }
        credentials = nil
        authenticatedUser = nil
        mustChangePassword = nil
        refreshPersistence = nil
        state = pendingState
        isSubmitting = false
    }

    private func clearForFullLoginRetry(ifCurrent attempt: Int) {
        guard isCurrentAttempt(attempt) else { return }
        credentials = nil
        authenticatedUser = nil
        mustChangePassword = nil
        refreshPersistence = nil
        state = .fullLoginRequired
        isSubmitting = false
        appSession.signOut()
    }

    private func clearToSignedOut() {
        credentials = nil
        authenticatedUser = nil
        mustChangePassword = nil
        refreshPersistence = nil
        state = .signedOut
        isSubmitting = false
        appSession.signOut()
    }

    private func clearToSignedOut(ifCurrent attempt: Int) {
        guard isCurrentAttempt(attempt) else { return }
        clearToSignedOut()
    }
}
