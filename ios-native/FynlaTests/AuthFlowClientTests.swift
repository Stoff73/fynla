import Foundation
import Testing
@testable import Fynla

@Suite("Login continuation, reset and restoration client")
struct AuthFlowClientTests {
    @Test
    func sendsExactLoginResendPasswordResetAndRestorationRequests() async throws {
        let transport = TestHTTPTransport([
            fixture("auth-login-resend-success"),
            fixture("auth-password-reset-request"),
            fixture("auth-password-reset-email-mfa"),
            fixture("auth-password-reset-resend-success"),
            fixture("auth-password-reset-mfa-ready"),
            fixture("auth-password-reset-recovery-ready"),
            fixture("auth-password-reset-complete"),
            fixture("auth-restoration-check-mfa"),
            fixture("auth-restoration-success"),
        ])
        let client = makeClient(transport)
        let resetToken = String(repeating: "r", count: 64)

        let resend = try await client.resendLogin(challengeToken: "login-challenge")
        let requested = try await client.requestPasswordReset(email: "example@example.test")
        let email = try await client.verifyPasswordResetEmail(
            token: resetToken,
            code: "123456"
        )
        _ = try await client.resendPasswordResetCode(token: resetToken)
        _ = try await client.verifyPasswordResetMFA(token: resetToken, code: "234567")
        _ = try await client.usePasswordResetRecoveryCode(
            token: resetToken,
            recoveryCode: "RECOVERY-EXAMPLE"
        )
        _ = try await client.resetPassword(
            token: resetToken,
            password: "Example1!",
            confirmation: "Example1!"
        )
        let restoration = try await client.checkRestoration(
            email: "example@example.test",
            password: "Example1!"
        )
        let restored = try await client.restoreAccount(
            token: restoration.token,
            mfaCode: "RECOVERY-RESTORE"
        )

        #expect(resend == LoginResendResult(
            message: "Verification code sent",
            canResend: true,
            remainingResends: 1
        ))
        #expect(requested.resetToken != nil)
        #expect(email.requiresMFA)
        #expect(!email.canResetPassword)
        #expect(restoration.requiresMFA)
        #expect(restored.bootstrapAccessToken == "bootstrap-restored-example")

        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/auth/resend-code",
            "/fynla/api/auth/password-reset/request",
            "/fynla/api/auth/password-reset/verify-email",
            "/fynla/api/auth/password-reset/resend-code",
            "/fynla/api/auth/password-reset/verify-mfa",
            "/fynla/api/auth/password-reset/mfa-recovery",
            "/fynla/api/auth/password-reset/reset",
            "/fynla/api/auth/restore/check",
            "/fynla/api/auth/restore",
        ])
        #expect(requests.allSatisfy { $0.url?.query == nil })
        #expect(try jsonObject(requests[0]) == [
            "challenge_token": "login-challenge", "type": "login",
        ])
        #expect(try jsonObject(requests[1]) == ["email": "example@example.test"])
        #expect(try jsonObject(requests[2]) == ["code": "123456", "token": resetToken])
        #expect(try jsonObject(requests[3]) == ["token": resetToken])
        #expect(try jsonObject(requests[4]) == ["code": "234567", "token": resetToken])
        #expect(try jsonObject(requests[5]) == [
            "recovery_code": "RECOVERY-EXAMPLE", "token": resetToken,
        ])
        #expect(try jsonObject(requests[6]) == [
            "password": "Example1!",
            "password_confirmation": "Example1!",
            "token": resetToken,
        ])
        #expect(try jsonObject(requests[7]) == [
            "email": "example@example.test", "password": "Example1!",
        ])
        #expect(try jsonObject(requests[8]) == [
            "mfa_code": "RECOVERY-RESTORE",
            "restoration_token": "restoration-example-checked-token",
        ])
    }

    @Test
    func genericResetRequestDoesNotInventAResetSession() async throws {
        let client = makeClient(TestHTTPTransport([
            fixture("auth-password-reset-request-generic"),
        ]))

        let result = try await client.requestPasswordReset(email: "absent@example.test")

        #expect(result.resetToken == nil)
        #expect(
            result.message
                == "If an account exists with this email, you will receive a verification code."
        )
    }

    @Test
    func restorationWithoutMultiFactorOmitsMFAFromTheRestoreRequest() async throws {
        let transport = TestHTTPTransport([
            fixture("auth-restoration-check"),
            fixture("auth-restoration-success"),
        ])
        let client = makeClient(transport)

        let restoration = try await client.checkRestoration(
            email: "example@example.test",
            password: "Example1!"
        )
        _ = try await client.restoreAccount(token: restoration.token, mfaCode: nil)

        #expect(!restoration.requiresMFA)
        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/auth/restore/check",
            "/fynla/api/auth/restore",
        ])
        #expect(try jsonObject(requests[1]) == [
            "restoration_token": "restoration-example-checked-token",
        ])
    }

    @Test
    func messageBearingBadRequestRemainsTypedAndSafe() async {
        let body = Data(#"{"success":false,"message":"Invalid verification code."}"#.utf8)
        let client = makeClient(TestHTTPTransport([
            .response(status: 400, body: body),
        ]))

        do {
            _ = try await client.verifyPasswordResetEmail(
                token: String(repeating: "r", count: 64),
                code: "000000"
            )
            Issue.record("Expected request rejection")
        } catch let error as AuthError {
            #expect(error == .requestRejected(
                message: "Invalid verification code.",
                status: 400
            ))
        } catch {
            Issue.record("Unexpected error type")
        }
    }

    private func makeClient(_ transport: any HTTPTransport) -> APIAuthClient {
        APIAuthClient(
            environment: try! AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.2.3",
            build: "45",
            transport: transport,
            requestID: { "auth-flow-request" }
        )
    }

    private func fixture(_ name: String) -> TestHTTPTransport.Stub {
        .response(status: 200, body: try! Data(contentsOf: fixtureURL(name)))
    }

    private func fixtureURL(_ name: String) -> URL {
        URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/API/\(name).json")
    }

    private func jsonObject(_ request: URLRequest) throws -> [String: AnyHashable] {
        let data = try #require(request.httpBody)
        return try #require(JSONSerialization.jsonObject(with: data) as? [String: AnyHashable])
    }
}
