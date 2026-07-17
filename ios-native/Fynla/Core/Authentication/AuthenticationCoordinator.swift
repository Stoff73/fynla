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
        try beginInitialAttempt()

        do {
            let challenge = try await authClient.register(input)
            try Task.checkCancellation()
            guard appSession.requireVerification() else {
                throw AuthenticationCoordinatorError.fullLoginRequired
            }
            state = .registrationVerification(challenge)
            isSubmitting = false
        } catch is CancellationError {
            clearToSignedOut()
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            clearToSignedOut()
            throw error
        }
    }

    func login(
        email: String,
        password: String,
        deviceLabel: String
    ) async throws {
        try beginInitialAttempt()

        do {
            let completion = try await authClient.loginCompletion(
                email: email,
                password: password
            )
            try Task.checkCancellation()

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
                    deviceLabel: deviceLabel
                )
            }
        } catch let error as AuthenticationCoordinatorError {
            if error == .fullLoginRequired {
                clearForFullLoginRetry()
            } else if error == .cancelled {
                clearToSignedOut()
            }
            throw error
        } catch is CancellationError {
            clearToSignedOut()
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            clearToSignedOut()
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
        try beginPendingAttempt()

        do {
            let completion = try await authClient.verifyRegistrationCompletion(input)
            try await completeFullAuthentication(completion, deviceLabel: deviceLabel)
        } catch let error as AuthenticationCoordinatorError {
            throw error
        } catch is CancellationError {
            clearToSignedOut()
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            restorePending(pendingState)
            throw error
        }
    }

    func verifyLogin(code: String, deviceLabel: String) async throws {
        guard case let .loginVerification(challengeToken, _) = state else {
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
        let pendingState = state
        try beginPendingAttempt()

        do {
            let completion = try await authClient.verifyLoginCompletion(
                code: code,
                challengeToken: challengeToken
            )
            try await completeFullAuthentication(completion, deviceLabel: deviceLabel)
        } catch let error as AuthenticationCoordinatorError {
            throw error
        } catch is CancellationError {
            clearToSignedOut()
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            restorePending(pendingState)
            throw error
        }
    }

    func verifyMFA(code: String, deviceLabel: String) async throws {
        guard case let .multiFactor(token, _) = state else {
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
        let pendingState = state
        try beginPendingAttempt()

        do {
            let completion = try await authClient.verifyMFACompletion(
                code: code,
                token: token
            )
            try await completeFullAuthentication(completion, deviceLabel: deviceLabel)
        } catch let error as AuthenticationCoordinatorError {
            throw error
        } catch is CancellationError {
            clearToSignedOut()
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            restorePending(pendingState)
            throw error
        }
    }

    func useRecoveryCode(_ code: String, deviceLabel: String) async throws {
        guard case let .multiFactor(token, _) = state else {
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
        let pendingState = state
        try beginPendingAttempt()

        do {
            let completion = try await authClient.useRecoveryCodeCompletion(
                code,
                token: token
            )
            try await completeFullAuthentication(completion, deviceLabel: deviceLabel)
        } catch let error as AuthenticationCoordinatorError {
            throw error
        } catch is CancellationError {
            clearToSignedOut()
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            restorePending(pendingState)
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

    func signOut() {
        clearToSignedOut()
    }

    private func beginInitialAttempt() throws {
        guard !isSubmitting, appSession.state == .signedOut,
              appSession.beginAuthentication()
        else {
            throw AuthenticationCoordinatorError.busy
        }
        isSubmitting = true
        state = .submitting
    }

    private func beginPendingAttempt() throws {
        guard !isSubmitting else {
            throw AuthenticationCoordinatorError.busy
        }
        isSubmitting = true
        state = .submitting
    }

    private func completeFullAuthentication(
        _ completion: BootstrapAuthentication,
        deviceLabel: String
    ) async throws {
        do {
            let exchanged = try await authClient.exchange(
                bootstrapToken: completion.bootstrapAccessToken,
                deviceLabel: deviceLabel
            )
            try Task.checkCancellation()
            let user = try await currentUserClient.currentUser(
                accessToken: exchanged.accessToken
            )
            try Task.checkCancellation()

            guard appSession.completeAuthentication(), appSession.unlock() else {
                throw AuthenticationCoordinatorError.fullLoginRequired
            }

            credentials = exchanged
            authenticatedUser = user
            mustChangePassword = completion.mustChangePassword
            refreshPersistence = .memoryOnly
            state = .authenticated(mustChangePassword: completion.mustChangePassword)
            isSubmitting = false
        } catch is CancellationError {
            clearToSignedOut()
            throw AuthenticationCoordinatorError.cancelled
        } catch {
            clearForFullLoginRetry()
            throw AuthenticationCoordinatorError.fullLoginRequired
        }
    }

    private func restorePending(_ pendingState: State) {
        credentials = nil
        authenticatedUser = nil
        mustChangePassword = nil
        refreshPersistence = nil
        state = pendingState
        isSubmitting = false
    }

    private func clearForFullLoginRetry() {
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
}
