import Foundation
import Testing
@testable import Fynla

@Suite("Face ID client")
struct BiometricClientTests {
    @Test
    func reportsOnlyTheBiometryExposedByTheInjectedContext() async {
        let faceID = LocalAuthenticationClient(
            context: StubLocalAuthenticationContext(type: .faceID)
        )
        let touchID = LocalAuthenticationClient(
            context: StubLocalAuthenticationContext(type: .touchID)
        )
        let unavailable = LocalAuthenticationClient(
            context: StubLocalAuthenticationContext(type: .none, canEvaluate: false)
        )
        let lockedOutFaceID = LocalAuthenticationClient(
            context: StubLocalAuthenticationContext(
                type: .faceID,
                canEvaluate: false,
                evaluationError: .biometryLockout
            )
        )

        #expect(await faceID.biometryType() == .faceID)
        #expect(await touchID.biometryType() == .touchID)
        #expect(await unavailable.biometryType() == .none)
        #expect(await lockedOutFaceID.biometryType() == .faceIDUnavailable)
        await #expect(throws: BiometricError.lockout) {
            try await lockedOutFaceID.authenticate(reason: "Unlock your Fynla session")
        }
    }

    @Test(arguments: [
        (LocalAuthenticationAdapterError.userCancelled, BiometricError.cancelled),
        (.authenticationFailed, .authenticationFailed),
        (.biometryLockout, .lockout),
        (.biometryUnavailable, .unavailable),
        // A device with no current enrolment is unavailable. There is no invented
        // "biometric set changed" status; Keychain item-not-found remains decisive.
        (.biometryNotEnrolled, .unavailable),
    ])
    func mapsContextFailuresWithoutLeakingFrameworkErrors(
        adapterError: LocalAuthenticationAdapterError,
        expected: BiometricError
    ) async {
        let client = LocalAuthenticationClient(
            context: StubLocalAuthenticationContext(
                type: .faceID,
                evaluationError: adapterError
            )
        )

        await #expect(throws: expected) {
            try await client.authenticate(reason: "Unlock your Fynla session")
        }
    }

    @Test
    func successfulFaceIDEvaluationUsesANonemptyReason() async throws {
        let context = StubLocalAuthenticationContext(type: .faceID)
        let client = LocalAuthenticationClient(context: context)

        _ = try await client.authenticate(reason: "Unlock your Fynla session")

        #expect(await context.reasons() == ["Unlock your Fynla session"])
    }

    @Test
    func authenticationFailsClosedWhenFaceIDIsNotAvailable() async {
        let context = StubLocalAuthenticationContext(type: .touchID)
        let client = LocalAuthenticationClient(context: context)

        await #expect(throws: BiometricError.unavailable) {
            try await client.authenticate(reason: "Unlock your Fynla session")
        }
        #expect(await context.reasons().isEmpty)
    }
}

private actor StubLocalAuthenticationContext: LocalAuthenticationContextAdapter {
    private let type: LocalAuthenticationBiometryType
    private let canEvaluate: Bool
    private let evaluationError: LocalAuthenticationAdapterError?
    private var evaluatedReasons: [String] = []

    init(
        type: LocalAuthenticationBiometryType,
        canEvaluate: Bool = true,
        evaluationError: LocalAuthenticationAdapterError? = nil
    ) {
        self.type = type
        self.canEvaluate = canEvaluate
        self.evaluationError = evaluationError
    }

    func availability() async -> LocalAuthenticationAvailability {
        LocalAuthenticationAvailability(
            canEvaluate: canEvaluate,
            biometryType: type
        )
    }

    func evaluate(reason: String) async throws -> LocalAuthenticationAuthorization {
        evaluatedReasons.append(reason)
        if let evaluationError {
            throw evaluationError
        }
        return .testing
    }

    func reasons() -> [String] {
        evaluatedReasons
    }
}
