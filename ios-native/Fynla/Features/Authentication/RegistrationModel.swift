import Foundation
import Observation

enum RegistrationField: String, CaseIterable, Sendable {
    case firstName
    case middleName
    case surname
    case email
    case password
    case passwordConfirmation
}

struct RegistrationDraft: Sendable, Equatable {
    let firstName: String
    let middleName: String
    let surname: String
    let email: String
}

struct RegistrationValidation: Sendable, Equatable {
    let input: RegistrationInput?
    let fieldErrors: [RegistrationField: String]
}

enum RegistrationValidator {
    static func validate(
        draft: RegistrationDraft,
        password: String,
        confirmation: String
    ) -> RegistrationValidation {
        let firstName = trimmed(draft.firstName)
        let middleName = trimmed(draft.middleName)
        let surname = trimmed(draft.surname)
        let email = trimmed(draft.email)
        var errors: [RegistrationField: String] = [:]

        if firstName.isEmpty {
            errors[.firstName] = "First name is required."
        } else if firstName.count > 255 {
            errors[.firstName] = "First name must be 255 characters or fewer."
        }
        if middleName.count > 255 {
            errors[.middleName] = "Middle name must be 255 characters or fewer."
        }
        if surname.isEmpty {
            errors[.surname] = "Surname is required."
        } else if surname.count > 255 {
            errors[.surname] = "Surname must be 255 characters or fewer."
        }
        if !isValidEmail(email) {
            errors[.email] = "Enter a valid email address."
        } else if email.count > 255 {
            errors[.email] = "Email must be 255 characters or fewer."
        }

        if password.count < 8 {
            errors[.password] = "Password must be at least 8 characters."
        } else if !passwordContainsEveryRequiredCharacterClass(password) {
            errors[.password] = "Password must include lowercase, uppercase, a number and a special character."
        }
        if confirmation != password {
            errors[.passwordConfirmation] = "Passwords do not match."
        }

        guard errors.isEmpty else {
            return RegistrationValidation(input: nil, fieldErrors: errors)
        }
        return RegistrationValidation(
            input: RegistrationInput(
                firstName: firstName,
                middleName: middleName.isEmpty ? nil : middleName,
                surname: surname,
                email: email,
                password: password,
                passwordConfirmation: confirmation
            ),
            fieldErrors: [:]
        )
    }

    static func isValidVerificationCode(_ value: String) -> Bool {
        value.utf8.count == 6 && value.utf8.allSatisfy { (48...57).contains($0) }
    }

    static func constrainedVerificationCode(_ value: String) -> String {
        String(
            decoding: value.utf8
                .filter { (48...57).contains($0) }
                .prefix(6),
            as: UTF8.self
        )
    }

    private static func trimmed(_ value: String) -> String {
        value.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    private static func isValidEmail(_ value: String) -> Bool {
        guard !value.isEmpty, !value.contains(where: { $0.isWhitespace }) else {
            return false
        }
        let parts = value.split(separator: "@", omittingEmptySubsequences: false)
        guard parts.count == 2,
              !parts[0].isEmpty,
              let domain = parts.last,
              domain.contains("."),
              !domain.hasPrefix("."),
              !domain.hasSuffix(".")
        else {
            return false
        }
        return true
    }

    private static func passwordContainsEveryRequiredCharacterClass(
        _ value: String
    ) -> Bool {
        let values = value.unicodeScalars.map(\.value)
        let isLowercase: (UInt32) -> Bool = { (97...122).contains($0) }
        let isUppercase: (UInt32) -> Bool = { (65...90).contains($0) }
        let isDigit: (UInt32) -> Bool = { (48...57).contains($0) }
        return values.contains(where: isLowercase)
            && values.contains(where: isUppercase)
            && values.contains(where: isDigit)
            && values.contains {
                !isLowercase($0) && !isUppercase($0) && !isDigit($0)
            }
    }
}

struct RegistrationActions: Sendable {
    let register: @MainActor @Sendable (RegistrationInput) async throws -> RegistrationChallenge
    let verify: @MainActor @Sendable (RegistrationVerificationInput) async throws -> Void
    let resend: @MainActor @Sendable (Int) async throws -> String
    let startOver: @MainActor @Sendable () -> Void

    @MainActor
    static func coordinator(
        _ coordinator: AuthenticationCoordinator,
        deviceLabel: String
    ) -> Self {
        Self(
            register: { input in
                try await coordinator.register(input)
                guard case let .registrationVerification(challenge) = coordinator.state else {
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
                return challenge
            },
            verify: { input in
                try await coordinator.verifyRegistration(
                    input,
                    deviceLabel: deviceLabel
                )
            },
            resend: { pendingID in
                guard case let .registrationVerification(challenge) = coordinator.state,
                      challenge.pendingID == pendingID
                else {
                    throw AuthenticationCoordinatorError.fullLoginRequired
                }
                return try await coordinator.resendRegistration()
            },
            startOver: {
                coordinator.signOut()
            }
        )
    }
}

enum RegistrationSubmissionResult: Sendable, Equatable {
    case advanced(clearSecrets: Bool)
    case failed(clearSecrets: Bool)
}

enum VerificationSubmissionResult: Sendable, Equatable {
    case succeeded
    case failed(clearCode: Bool)
}

@MainActor
@Observable
final class RegistrationModel {
    var firstName = ""
    var middleName = ""
    var surname = ""
    var email = ""
    var isPresentingRegistration: Bool

    private(set) var challenge: RegistrationChallenge?
    private(set) var fieldErrors: [RegistrationField: String] = [:]
    private(set) var message: String?
    private(set) var messageIsError = false
    private(set) var isSubmitting = false
    private(set) var isResendAvailable = true
    private(set) var requiresStartOver = false

    let termsURL: URL
    let privacyURL: URL

    private let actions: RegistrationActions
    private var attemptGeneration = 0

    init(
        actions: RegistrationActions,
        webBaseURL: URL,
        isPresentingRegistration: Bool = false
    ) {
        self.actions = actions
        self.termsURL = webBaseURL.appendingPathComponent("terms")
        self.privacyURL = webBaseURL.appendingPathComponent("privacy")
        self.isPresentingRegistration = isPresentingRegistration
    }

    func presentRegistration() {
        isPresentingRegistration = true
        clearPresentationErrors()
    }

    func beginVerification(_ challenge: RegistrationChallenge) {
        self.challenge = challenge
        isPresentingRegistration = true
        isResendAvailable = true
        requiresStartOver = false
        clearPresentationErrors()
    }

    func submitRegistration(
        password: String,
        confirmation: String
    ) async -> RegistrationSubmissionResult {
        let validation = RegistrationValidator.validate(
            draft: RegistrationDraft(
                firstName: firstName,
                middleName: middleName,
                surname: surname,
                email: email
            ),
            password: password,
            confirmation: confirmation
        )
        fieldErrors = validation.fieldErrors
        message = nil
        messageIsError = false
        requiresStartOver = false
        guard let input = validation.input else {
            return .failed(clearSecrets: false)
        }
        guard !isSubmitting else {
            message = "Please wait for the current request to finish."
            messageIsError = true
            return .failed(clearSecrets: false)
        }

        let attempt = beginAttempt()
        do {
            let newChallenge = try await actions.register(input)
            guard isCurrent(attempt) else {
                return .failed(clearSecrets: false)
            }
            challenge = newChallenge
            isSubmitting = false
            isResendAvailable = true
            fieldErrors = [:]
            message = nil
            return .advanced(clearSecrets: true)
        } catch {
            guard isCurrent(attempt) else {
                return .failed(clearSecrets: false)
            }
            isSubmitting = false
            apply(error)
            return .failed(clearSecrets: false)
        }
    }

    func submitVerification(code: String) async -> VerificationSubmissionResult {
        guard RegistrationValidator.isValidVerificationCode(code) else {
            message = "Enter the six-digit verification code."
            messageIsError = true
            return .failed(clearCode: true)
        }
        guard let challenge else {
            requiresStartOver = true
            message = "Registration is no longer available. Please start over."
            messageIsError = true
            return .failed(clearCode: true)
        }
        guard !isSubmitting else {
            return .failed(clearCode: false)
        }

        let attempt = beginAttempt()
        do {
            try await actions.verify(
                RegistrationVerificationInput(
                    code: code,
                    pendingID: challenge.pendingID
                )
            )
            guard isCurrent(attempt) else {
                return .failed(clearCode: true)
            }
            isSubmitting = false
            message = nil
            return .succeeded
        } catch {
            guard isCurrent(attempt) else {
                return .failed(clearCode: true)
            }
            isSubmitting = false
            apply(error)
            return .failed(clearCode: true)
        }
    }

    func resendCode() async {
        guard let challenge, isResendAvailable, !isSubmitting else { return }
        let attempt = beginAttempt()
        do {
            let successMessage = try await actions.resend(challenge.pendingID)
            guard isCurrent(attempt) else { return }
            isSubmitting = false
            message = successMessage
            messageIsError = false
        } catch {
            guard isCurrent(attempt) else { return }
            isSubmitting = false
            apply(error)
        }
    }

    func startOver() {
        invalidateAttempt()
        challenge = nil
        isPresentingRegistration = true
        isResendAvailable = true
        requiresStartOver = false
        clearPresentationErrors()
        actions.startOver()
    }

    func cancelFlow() {
        invalidateAttempt()
        challenge = nil
        isPresentingRegistration = false
        isResendAvailable = true
        requiresStartOver = false
        clearPresentationErrors()
        actions.startOver()
    }

    private func beginAttempt() -> Int {
        attemptGeneration += 1
        isSubmitting = true
        fieldErrors = [:]
        message = nil
        messageIsError = false
        return attemptGeneration
    }

    private func invalidateAttempt() {
        attemptGeneration += 1
        isSubmitting = false
    }

    private func isCurrent(_ attempt: Int) -> Bool {
        attempt == attemptGeneration
    }

    private func clearPresentationErrors() {
        fieldErrors = [:]
        message = nil
    }

    private func apply(_ error: any Error) {
        messageIsError = true
        guard let authError = error as? AuthError else {
            if error as? AuthenticationCoordinatorError == .cancelled
                || error is CancellationError
            {
                return
            }
            message = "Something went wrong. Please try again."
            return
        }

        switch authError {
        case let .validation(serverMessage, errors):
            fieldErrors = Self.visibleFieldErrors(errors)
            message = serverMessage ?? fieldErrors.values.first
        case let .registrationUnavailable(serverMessage):
            message = serverMessage
            requiresStartOver = true
        case let .notFound(serverMessage):
            message = serverMessage ?? "Registration not found. Please start over."
            requiresStartOver = true
        case let .resendExhausted(serverMessage):
            message = serverMessage
            isResendAvailable = false
        case let .locked(serverMessage, _):
            message = serverMessage
        case let .rateLimited(serverMessage):
            message = serverMessage ?? "Too many attempts. Please try again later."
        case let .unauthenticated(serverMessage):
            message = serverMessage ?? "Authentication failed. Please try again."
        case let .requestRejected(serverMessage, _):
            message = serverMessage
        case .offline:
            message = "You appear to be offline. Check your connection and try again."
        case .transport:
            message = "The service could not be reached. Please try again."
        case .decoding,
             .server:
            message = "Something went wrong. Please try again."
        }
    }

    private static func visibleFieldErrors(
        _ errors: [String: [String]]
    ) -> [RegistrationField: String] {
        let mapping: [String: RegistrationField] = [
            "first_name": .firstName,
            "middle_name": .middleName,
            "surname": .surname,
            "email": .email,
            "password": .password,
            "password_confirmation": .passwordConfirmation,
        ]
        return errors.reduce(into: [:]) { result, entry in
            guard let field = mapping[entry.key], let first = entry.value.first else {
                return
            }
            result[field] = first
        }
    }
}
