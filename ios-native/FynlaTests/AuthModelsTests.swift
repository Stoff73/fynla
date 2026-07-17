import Foundation
import Testing
@testable import Fynla

@Suite("Authentication contracts")
struct AuthModelsTests {
    @Test
    func decodesRegistrationAndEveryLoginBranch() throws {
        #expect(
            try AuthResponseDecoder.registrationChallenge(
                from: fixture("auth-registration-challenge")
            ) == RegistrationChallenge(pendingID: 321, maskedEmail: "e*****e@example.test")
        )

        let immediate = try AuthResponseDecoder.login(
            from: fixture("auth-immediate-success")
        )
        #expect(immediate.outcome == .authenticated(bootstrapAccessToken: "bootstrap-example-immediate"))
        #expect(immediate.mustChangePassword == true)

        let verification = try AuthResponseDecoder.login(
            from: fixture("auth-login-verification")
        )
        #expect(
            verification.outcome == .verification(
                challengeToken: "challenge-example-login",
                maskedEmail: "e*****e@example.test"
            )
        )
        #expect(verification.mustChangePassword == nil)

        let mfa = try AuthResponseDecoder.login(from: fixture("auth-login-mfa"))
        #expect(
            mfa.outcome == .multiFactor(
                token: "mfa-example-challenge",
                maskedEmail: "e*****e@example.test"
            )
        )

        let restorable = try AuthResponseDecoder.login(
            from: fixture("auth-login-restorable")
        )
        #expect(
            restorable.outcome == .restorable(
                RestorationChallenge(
                    token: "restoration-example-challenge",
                    deletedAt: "2026-07-16T12:00:00Z",
                    deletionReason: "user_requested",
                    deletionSource: "settings",
                    firstName: "Example"
                )
            )
        )
    }

    @Test
    func decodesEveryFullAuthenticationSuccessWithoutInventingMetadata() throws {
        let registration = try AuthResponseDecoder.authentication(
            from: fixture("auth-registration-verification-success")
        )
        #expect(registration.bootstrapAccessToken == "bootstrap-example-registration")
        #expect(registration.mustChangePassword == false)

        let login = try AuthResponseDecoder.authentication(
            from: fixture("auth-login-verification-success")
        )
        #expect(login.bootstrapAccessToken == "bootstrap-example-verification")
        #expect(login.mustChangePassword == true)

        let mfa = try AuthResponseDecoder.authentication(from: fixture("auth-mfa-success"))
        #expect(mfa.bootstrapAccessToken == "bootstrap-example-mfa")
        #expect(mfa.mustChangePassword == nil)

        let recovery = try AuthResponseDecoder.authentication(
            from: fixture("auth-recovery-success")
        )
        #expect(recovery.bootstrapAccessToken == "bootstrap-example-recovery")
        #expect(recovery.mustChangePassword == nil)
    }

    @Test
    func decodesExactNativeCredentialsAndCurrentUserEnvelope() throws {
        let credentials = try AuthResponseDecoder.nativeCredentials(
            from: fixture("auth-native-credentials")
        )
        #expect(credentials.accessToken == "native-access-example")
        #expect(credentials.accessExpiresAt == "2026-07-17T12:15:00Z")
        #expect(credentials.refreshToken == "native-refresh-example")
        #expect(credentials.refreshExpiresAt == "2026-08-16T12:00:00Z")
        #expect(credentials.absoluteExpiresAt == "2026-10-15T12:00:00Z")
        #expect(credentials.sessionID == "11111111-2222-3333-4444-555555555555")

        let user = try AuthResponseDecoder.currentUser(from: fixture("auth-user"))
        #expect(user.id == 101)
        #expect(user.email == "user@example.test")
    }

    @Test
    func mapsLockValidationAndResendExhaustionDistinctly() throws {
        let locked = try fixture("auth-locked")
        let validation = try fixture("auth-validation")
        let exhausted = try fixture("auth-resend-exhausted")
        #expect(
            AuthResponseDecoder.error(from: locked, status: 423)
                == .locked(
                    message: "Too many attempts. Please try again later.",
                    remainingSeconds: 180
                )
        )
        #expect(
            AuthResponseDecoder.error(from: validation, status: 422)
                == .validation([
                    "email": ["The email field is required."],
                    "password": ["The password field is required."],
                ])
        )
        #expect(
            AuthResponseDecoder.error(from: exhausted, status: 429)
                == .resendExhausted(
                    message: "Maximum resend limit reached. Please refresh and try again."
                )
        )
        #expect(
            AuthResponseDecoder.error(
                from: Data(#"{"success":false,"message":"Slow down"}"#.utf8),
                status: 429
            ) == .rateLimited(message: "Slow down")
        )
    }

    @Test
    func unknownOrAmbiguousSuccessShapesFailClosed() {
        let unknown = Data(#"{"success":true,"data":{"unexpected":true}}"#.utf8)
        #expect(throws: AuthError.decoding) {
            try AuthResponseDecoder.login(from: unknown)
        }

        let ambiguous = Data(
            #"{"success":true,"requires_verification":true,"requires_mfa":true,"data":{"challenge_token":"challenge","mfa_token":"mfa","email":"e***@example.test"}}"#.utf8
        )
        #expect(throws: AuthError.decoding) {
            try AuthResponseDecoder.login(from: ambiguous)
        }
    }

    private func fixture(_ name: String) throws -> Data {
        let bundle = Bundle(for: AuthFixtureBundleToken.self)
        if let url = bundle.url(
            forResource: name,
            withExtension: "json",
            subdirectory: "Fixtures/API"
        ) ?? bundle.url(forResource: name, withExtension: "json") {
            return try Data(contentsOf: url)
        }

        return try Data(contentsOf: URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/API/\(name).json"))
    }
}

private final class AuthFixtureBundleToken {}
