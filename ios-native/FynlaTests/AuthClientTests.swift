import Foundation
import Testing
@testable import Fynla

@Suite("Authentication client")
struct AuthClientTests {
    @Test
    func sendsExactPublicAuthenticationPathsAndJSONBodies() async throws {
        let transport = TestHTTPTransport([
            response("auth-registration-challenge", status: 201),
            response("auth-registration-verification-success"),
            response("auth-login-verification"),
            response("auth-login-verification-success"),
            response("auth-mfa-success"),
            response("auth-recovery-success"),
        ])
        let client = makeClient(transport)

        _ = try await client.register(
            RegistrationInput(
                firstName: "Example",
                middleName: nil,
                surname: "User",
                email: "example@example.test",
                password: "Example1!",
                passwordConfirmation: "Example1!"
            )
        )
        _ = try await client.verifyRegistration(
            RegistrationVerificationInput(code: "123456", pendingID: 321)
        )
        _ = try await client.login(email: "example@example.test", password: "Example1!")
        _ = try await client.verifyLogin(code: "234567", challengeToken: "challenge-example-login")
        _ = try await client.verifyMFA(code: "345678", token: "mfa-example-challenge")
        _ = try await client.useRecoveryCode("example-recovery-code", token: "mfa-example-challenge")

        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/auth/register",
            "/fynla/api/auth/verify-code",
            "/fynla/api/auth/login",
            "/fynla/api/auth/verify-code",
            "/fynla/api/auth/mfa/verify",
            "/fynla/api/auth/mfa/recovery",
        ])
        #expect(requests.allSatisfy { $0.url?.query == nil })
        #expect(try jsonObject(requests[0]) == [
            "email": "example@example.test",
            "first_name": "Example",
            "password": "Example1!",
            "password_confirmation": "Example1!",
            "surname": "User",
        ])
        #expect(try jsonObject(requests[1]) == [
            "code": "123456", "pending_id": 321, "type": "registration",
        ])
        #expect(try jsonObject(requests[2]) == [
            "email": "example@example.test", "password": "Example1!",
        ])
        #expect(try jsonObject(requests[3]) == [
            "challenge_token": "challenge-example-login", "code": "234567", "type": "login",
        ])
        #expect(try jsonObject(requests[4]) == [
            "code": "345678", "mfa_token": "mfa-example-challenge",
        ])
        #expect(try jsonObject(requests[5]) == [
            "mfa_token": "mfa-example-challenge", "recovery_code": "example-recovery-code",
        ])
    }

    @Test
    func exchangeUsesBootstrapBearerAndCurrentUserUsesOnlyNativeBearer() async throws {
        let transport = TestHTTPTransport([
            response("auth-native-credentials"),
            response("auth-user"),
        ])
        let client = makeClient(transport)

        let credentials = try await client.exchange(
            bootstrapToken: "bootstrap-example-immediate",
            deviceLabel: "Example iPhone"
        )
        _ = try await client.currentUser(accessToken: credentials.accessToken)

        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/v1/native/auth/session/exchange",
            "/fynla/api/auth/user",
        ])
        #expect(requests[0].value(forHTTPHeaderField: "Authorization") == "Bearer bootstrap-example-immediate")
        #expect(try jsonObject(requests[0]) == ["device_label": "Example iPhone"])
        #expect(requests[1].value(forHTTPHeaderField: "Authorization") == "Bearer native-access-example")
        #expect(requests[1].httpBody == nil)
        #expect(requests.allSatisfy { $0.url?.query == nil })
    }

    @Test
    func exposesTypedErrorsWithoutIncludingResponseBodies() async {
        let transport = TestHTTPTransport([
            response("auth-locked", status: 423),
            response("auth-validation", status: 422),
            response("auth-resend-exhausted", status: 429),
        ])
        let client = makeClient(transport)

        await expectAuthError(
            .locked(message: "Too many attempts. Please try again later.", remainingSeconds: 180)
        ) {
            try await client.login(email: "example@example.test", password: "Example1!")
        }
        await expectAuthError(.validation([
            "email": ["The email field is required."],
            "password": ["The password field is required."],
        ])) {
            try await client.login(email: "example@example.test", password: "Example1!")
        }
        await expectAuthError(
            .resendExhausted(message: "Maximum resend limit reached. Please refresh and try again.")
        ) {
            try await client.login(email: "example@example.test", password: "Example1!")
        }
    }

    private func makeClient(_ transport: TestHTTPTransport) -> APIAuthClient {
        APIAuthClient(
            environment: try! AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.2.3",
            build: "45",
            transport: transport,
            requestID: { "auth-request" }
        )
    }

    private func response(_ fixture: String, status: Int = 200) -> TestHTTPTransport.Stub {
        .response(status: status, body: try! fixtureData(fixture))
    }

    private func fixtureData(_ name: String) throws -> Data {
        try Data(contentsOf: URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/API/\(name).json"))
    }

    private func jsonObject(_ request: URLRequest) throws -> [String: AnyHashable] {
        let data = try #require(request.httpBody)
        return try #require(JSONSerialization.jsonObject(with: data) as? [String: AnyHashable])
    }

    private func expectAuthError<Value>(
        _ expected: AuthError,
        operation: () async throws -> Value
    ) async {
        do {
            _ = try await operation()
            Issue.record("Expected authentication error")
        } catch let error as AuthError {
            #expect(error == expected)
        } catch {
            Issue.record("Unexpected error type")
        }
    }
}
