import Foundation
import Observation

@MainActor
@Observable
final class AccountDeletionModel {
    static let confirmationPhrase = "Delete my Account"

    private(set) var state: AccountDeletionState = .idle
    private(set) var verificationCode = ""
    private(set) var confirmation = ""
    private(set) var message: String?
    private(set) var errorMessage: String?

    private let client: any AccountDeletionClient
    private let loadBillingState: @MainActor @Sendable () async -> AccountDeletionBillingState
    private let cleanup: @MainActor @Sendable () async -> Void
    private var billingState: AccountDeletionBillingState = .unavailable
    private var sessionToken: String?

    init(
        client: any AccountDeletionClient,
        loadBillingState: @escaping @MainActor @Sendable () async -> AccountDeletionBillingState,
        cleanup: @escaping @MainActor @Sendable () async -> Void
    ) {
        self.client = client
        self.loadBillingState = loadBillingState
        self.cleanup = cleanup
    }

    var canVerify: Bool {
        verificationCode.count == 6
    }

    var canExecute: Bool {
        state == .verified && confirmation == Self.confirmationPhrase
    }

    func prepare() async {
        guard state == .idle || isTerminalState else { return }
        clearMessages()
        state = .loadingEntitlement
        billingState = await loadBillingState()
        switch billingState {
        case .applePremium:
            state = .appleBillingWarning
        case .free, .webPremium:
            state = .ready(billingState)
        case .unavailable:
            state = .failed(
                message: "Your subscription details are unavailable. Please try again."
            )
        }
    }

    func continueAfterAppleWarning() {
        guard state == .appleBillingWarning else { return }
        state = .ready(.applePremium)
    }

    func initiate() async {
        guard case .ready = state else { return }
        clearMessages()
        state = .requestingVerification
        do {
            let response = try await client.initiate()
            sessionToken = response.sessionToken
            verificationCode = ""
            state = .verificationRequired(
                usesAuthenticator: response.requiresTwoFactor
            )
            message = response.requiresTwoFactor
                ? "Enter the six-digit code from your authenticator app."
                : "Enter the six-digit verification code sent to your email."
        } catch {
            fail(error)
        }
    }

    func setVerificationCode(_ value: String) {
        verificationCode = String(
            value.filter(\.isNumber).prefix(6)
        )
        errorMessage = nil
    }

    func verify() async {
        guard canVerify,
              let sessionToken,
              case let .verificationRequired(usesAuthenticator) = state
        else { return }
        clearMessages()
        state = .verifying
        do {
            let response = try await client.verify(
                sessionToken: sessionToken,
                code: verificationCode
            )
            self.sessionToken = response.sessionToken
            message = response.message
            state = .verified
        } catch {
            let text = safeMessage(for: error)
            if requiresRestart(text) {
                self.sessionToken = nil
                verificationCode = ""
                state = .ready(billingState)
            } else {
                state = .verificationRequired(usesAuthenticator: usesAuthenticator)
            }
            errorMessage = text
        }
    }

    func resend() async {
        guard let sessionToken,
              case let .verificationRequired(usesAuthenticator) = state,
              !usesAuthenticator
        else { return }
        clearMessages()
        do {
            message = try await client.resend(sessionToken: sessionToken).message
        } catch {
            let text = safeMessage(for: error)
            if requiresRestart(text) {
                self.sessionToken = nil
                verificationCode = ""
                state = .ready(billingState)
            }
            errorMessage = text
        }
    }

    func setConfirmation(_ value: String) {
        confirmation = value
        errorMessage = nil
    }

    func execute() async {
        guard canExecute, let sessionToken else { return }
        clearMessages()
        state = .executing
        do {
            let response = try await client.execute(
                sessionToken: sessionToken,
                confirmation: confirmation
            )
            self.sessionToken = nil
            verificationCode = ""
            confirmation = ""
            if response.logoutRequired {
                state = .deleted(message: response.message)
                await cleanup()
            } else {
                state = .scheduled(
                    message: response.message,
                    deletionAt: response.scheduledDeletionAt
                )
            }
        } catch {
            state = .verified
            errorMessage = safeMessage(for: error)
        }
    }

    func cancelScheduled() async {
        guard case .scheduled = state else { return }
        clearMessages()
        do {
            let response = try await client.cancelScheduled()
            state = .cancelled(message: response.message)
        } catch {
            errorMessage = safeMessage(for: error)
        }
    }

    func reset() {
        state = .idle
        sessionToken = nil
        verificationCode = ""
        confirmation = ""
        clearMessages()
    }

    private var isTerminalState: Bool {
        switch state {
        case .cancelled, .failed:
            true
        default:
            false
        }
    }

    private func fail(_ error: any Error) {
        let text = safeMessage(for: error)
        state = .failed(message: text)
        errorMessage = text
    }

    private func clearMessages() {
        message = nil
        errorMessage = nil
    }

    private func safeMessage(for error: any Error) -> String {
        if case let AccountDeletionClientError.rejected(message) = error {
            return message
        }
        if case APIError.offline = error {
            return "You're offline. Reconnect and try again."
        }
        if case APIError.rateLimited = error {
            return "Too many attempts. Please wait and try again."
        }
        return "Account deletion could not be completed. Please try again."
    }

    private func requiresRestart(_ message: String) -> Bool {
        let lowercased = message.lowercased()
        return lowercased.contains("expired session")
            || lowercased.contains("too many failed attempts")
    }
}
