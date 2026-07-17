import Foundation
import LocalAuthentication

enum LocalAuthenticationBiometryType: Sendable, Equatable {
    case none
    case touchID
    case faceID
}

struct LocalAuthenticationAvailability: Sendable, Equatable {
    let canEvaluate: Bool
    let biometryType: LocalAuthenticationBiometryType
}

enum LocalAuthenticationAdapterError: Error, Sendable, Equatable {
    case userCancelled
    case authenticationFailed
    case biometryLockout
    case biometryUnavailable
    case biometryNotEnrolled
}

struct LocalAuthenticationAuthorization: @unchecked Sendable, Equatable {
    let context: LAContext?
    private let identifier = UUID()

    static let testing = Self(context: nil)

    static func == (
        lhs: LocalAuthenticationAuthorization,
        rhs: LocalAuthenticationAuthorization
    ) -> Bool {
        lhs.identifier == rhs.identifier
    }
}

protocol LocalAuthenticationContextAdapter: Sendable {
    func availability() async -> LocalAuthenticationAvailability
    func evaluate(reason: String) async throws -> LocalAuthenticationAuthorization
}

actor LocalAuthenticationClient: BiometricClient {
    private let context: any LocalAuthenticationContextAdapter

    init(context: any LocalAuthenticationContextAdapter = SystemLocalAuthenticationContext()) {
        self.context = context
    }

    func biometryType() async -> BiometryType {
        let availability = await context.availability()
        return switch availability.biometryType {
        case .none:
            .none
        case .touchID:
            availability.canEvaluate ? .touchID : .none
        case .faceID:
            availability.canEvaluate ? .faceID : .faceIDUnavailable
        }
    }

    func authenticate(reason: String) async throws -> LocalAuthenticationAuthorization {
        let type = await biometryType()
        guard type == .faceID || type == .faceIDUnavailable,
              !reason.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        else {
            throw BiometricError.unavailable
        }

        do {
            return try await context.evaluate(reason: reason)
        } catch let error as LocalAuthenticationAdapterError {
            throw switch error {
            case .userCancelled:
                BiometricError.cancelled
            case .authenticationFailed:
                BiometricError.authenticationFailed
            case .biometryLockout:
                BiometricError.lockout
            case .biometryUnavailable, .biometryNotEnrolled:
                BiometricError.unavailable
            }
        } catch {
            throw BiometricError.authenticationFailed
        }
    }
}

actor SystemLocalAuthenticationContext: LocalAuthenticationContextAdapter {
    func availability() -> LocalAuthenticationAvailability {
        let context = LAContext()
        var error: NSError?
        let canEvaluate = context.canEvaluatePolicy(
            .deviceOwnerAuthenticationWithBiometrics,
            error: &error
        )
        return LocalAuthenticationAvailability(
            canEvaluate: canEvaluate,
            biometryType: Self.biometryType(context.biometryType)
        )
    }

    func evaluate(reason: String) async throws -> LocalAuthenticationAuthorization {
        let context = LAContext()
        var availabilityError: NSError?
        guard context.canEvaluatePolicy(
            .deviceOwnerAuthenticationWithBiometrics,
            error: &availabilityError
        ) else {
            throw Self.error(from: availabilityError)
        }

        do {
            try await context.evaluatePolicy(
                .deviceOwnerAuthenticationWithBiometrics,
                localizedReason: reason
            )
            return LocalAuthenticationAuthorization(context: context)
        } catch {
            throw Self.error(from: error as NSError)
        }
    }

    private static func biometryType(_ type: LABiometryType) -> LocalAuthenticationBiometryType {
        switch type {
        case .touchID:
            .touchID
        case .faceID:
            .faceID
        case .none, .opticID:
            .none
        @unknown default:
            .none
        }
    }

    private static func error(from error: NSError?) -> LocalAuthenticationAdapterError {
        guard let error,
              error.domain == LAError.errorDomain,
              let code = LAError.Code(rawValue: error.code)
        else {
            return .authenticationFailed
        }

        return switch code {
        case .userCancel, .appCancel, .systemCancel:
            .userCancelled
        case .authenticationFailed:
            .authenticationFailed
        case .biometryLockout:
            .biometryLockout
        case .biometryNotEnrolled:
            .biometryNotEnrolled
        case .biometryNotAvailable, .passcodeNotSet:
            .biometryUnavailable
        default:
            .authenticationFailed
        }
    }
}
