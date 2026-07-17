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
            response("auth-registration-resend-success"),
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
        _ = try await client.resendRegistration(pendingID: 321)
        _ = try await client.login(email: "example@example.test", password: "Example1!")
        _ = try await client.verifyLogin(code: "234567", challengeToken: "challenge-example-login")
        _ = try await client.verifyMFA(code: "345678", token: "mfa-example-challenge")
        _ = try await client.useRecoveryCode("example-recovery-code", token: "mfa-example-challenge")

        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/auth/register",
            "/fynla/api/auth/verify-code",
            "/fynla/api/auth/resend-code",
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
            "pending_id": 321, "type": "registration",
        ])
        #expect(try jsonObject(requests[3]) == [
            "email": "example@example.test", "password": "Example1!",
        ])
        #expect(try jsonObject(requests[4]) == [
            "challenge_token": "challenge-example-login", "code": "234567", "type": "login",
        ])
        #expect(try jsonObject(requests[5]) == [
            "code": "345678", "mfa_token": "mfa-example-challenge",
        ])
        #expect(try jsonObject(requests[6]) == [
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
        await expectAuthError(.validation(
            message: "The given data was invalid.",
            errors: [
                "email": ["The email field is required."],
                "password": ["The password field is required."],
            ]
        )) {
            try await client.login(email: "example@example.test", password: "Example1!")
        }
        await expectAuthError(
            .resendExhausted(message: "Maximum resend limit reached. Please refresh and try again.")
        ) {
            try await client.login(email: "example@example.test", password: "Example1!")
        }
    }

    @Test
    func currentCodeAndChallengeFailuresKeepServerWording() async {
        let transport = TestHTTPTransport([
            response("auth-invalid-credentials", status: 401),
            response("auth-registration-code-invalid", status: 422),
            response("auth-registration-code-expired", status: 422),
            response("auth-login-session-expired", status: 422),
            response("auth-login-code-invalid", status: 422),
            response("auth-mfa-challenge-expired", status: 401),
            response("auth-mfa-code-invalid", status: 401),
            response("auth-recovery-code-invalid", status: 401),
        ])
        let client = makeClient(transport)

        await expectAuthError(.unauthenticated(message: "Invalid email or password.")) {
            try await client.login(email: "example@example.test", password: "wrong")
        }
        await expectAuthError(.validation(message: "Invalid verification code", errors: [:])) {
            try await client.verifyRegistration(
                RegistrationVerificationInput(code: "000000", pendingID: 321)
            )
        }
        await expectAuthError(.registrationUnavailable(
            message: "Verification code has expired. Please register again."
        )) {
            try await client.verifyRegistration(
                RegistrationVerificationInput(code: "000000", pendingID: 321)
            )
        }
        await expectAuthError(.validation(
            message: "Invalid or expired verification session",
            errors: [:]
        )) {
            try await client.verifyLogin(code: "000000", challengeToken: "expired-challenge")
        }
        await expectAuthError(.validation(
            message: "Invalid or expired verification code",
            errors: [:]
        )) {
            try await client.verifyLogin(code: "000000", challengeToken: "current-challenge")
        }
        await expectAuthError(.unauthenticated(
            message: "Invalid or expired verification request."
        )) {
            try await client.verifyMFA(code: "000000", token: "expired-mfa")
        }
        await expectAuthError(.unauthenticated(message: "Invalid verification code.")) {
            try await client.verifyMFA(code: "000000", token: "current-mfa")
        }
        await expectAuthError(.unauthenticated(message: "Invalid recovery code.")) {
            try await client.useRecoveryCode("wrong-recovery", token: "current-mfa")
        }
    }

    @Test
    func preservesCancellationAndNormalizesTransportFailures() async {
        let cancelled = makeClient(ThrowingAuthTransport(.cancelled))
        do {
            _ = try await cancelled.login(email: "example@example.test", password: "Example1!")
            Issue.record("Expected cancellation")
        } catch is CancellationError {
            // Cancellation remains cancellation for coordinator ownership.
        } catch {
            Issue.record("Unexpected cancellation error type")
        }

        let urlCancelled = makeClient(ThrowingAuthTransport(.urlError(.cancelled)))
        do {
            _ = try await urlCancelled.login(email: "example@example.test", password: "Example1!")
            Issue.record("Expected URL cancellation to normalize to CancellationError")
        } catch is CancellationError {
            // URLSession cancellation remains cancellation.
        } catch {
            Issue.record("Unexpected URL cancellation error type")
        }

        let offline = makeClient(ThrowingAuthTransport(.urlError(.networkConnectionLost)))
        await expectAuthError(.offline) {
            try await offline.login(email: "example@example.test", password: "Example1!")
        }

        let failed = makeClient(ThrowingAuthTransport(.other))
        await expectAuthError(.transport) {
            try await failed.login(email: "example@example.test", password: "Example1!")
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

private enum AuthTransportTestFailure: Error {
    case expected
}

private actor ThrowingAuthTransport: HTTPTransport {
    enum Failure: Sendable {
        case cancelled
        case urlError(URLError.Code)
        case other
    }

    private let failure: Failure

    init(_ failure: Failure) {
        self.failure = failure
    }

    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse) {
        switch failure {
        case .cancelled:
            throw CancellationError()
        case let .urlError(code):
            throw URLError(code)
        case .other:
            throw AuthTransportTestFailure.expected
        }
    }

    func byteStream(for request: URLRequest) async throws -> HTTPByteStream {
        throw AuthTransportTestFailure.expected
    }
}
