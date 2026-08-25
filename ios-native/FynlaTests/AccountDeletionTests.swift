import Foundation
import Testing
@testable import Fynla

@Suite("Account deletion")
struct AccountDeletionTests {
    @Test
    func clientUsesCurrentErasureEndpointsAndExactPayloads() async throws {
        let token = String(repeating: "s", count: 64)
        let transport = TestHTTPTransport([
            .response(status: 200, body: json("""
                {"success":true,"requires_2fa":false,"requires_email_verification":true,"session_token":"\(token)"}
                """)),
            .response(status: 200, body: json("""
                {"success":true,"message":"Identity verified successfully.","session_token":"\(token)","type":"account"}
                """)),
            .response(status: 200, body: json("""
                {"success":true,"message":"Verification code sent to your email."}
                """)),
            .response(status: 200, body: json("""
                {"success":true,"message":"Your account has been deleted.","type":"account","logout_required":true}
                """)),
            .response(status: 200, body: json("""
                {"success":true,"message":"Scheduled deletion cancelled."}
                """)),
        ])
        let client = LiveAccountDeletionClient(apiClient: apiClient(transport))

        let initiation = try await client.initiate()
        _ = try await client.verify(sessionToken: token, code: "123456")
        _ = try await client.resend(sessionToken: token)
        let result = try await client.execute(
            sessionToken: token,
            confirmation: AccountDeletionModel.confirmationPhrase
        )
        _ = try await client.cancelScheduled()

        #expect(initiation.requiresEmailVerification)
        #expect(result.logoutRequired)
        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/auth/gdpr/erasure/initiate",
            "/fynla/api/auth/gdpr/erasure/verify",
            "/fynla/api/auth/gdpr/erasure/resend-code",
            "/fynla/api/auth/gdpr/erasure/execute",
            "/fynla/api/auth/gdpr/erasure/cancel-scheduled",
        ])
        #expect(body(requests[0]) == ["type": "account"])
        #expect(body(requests[1]) == ["session_token": token, "code": "123456"])
        #expect(body(requests[2]) == ["session_token": token])
        #expect(body(requests[3]) == [
            "session_token": token,
            "confirmation": "Delete my Account",
        ])
        #expect(requests[4].httpBody == nil)
    }

    @Test @MainActor
    func applePremiumRequiresBillingWarningBeforeInitiation() async {
        let client = AccountDeletionClientStub()
        let model = AccountDeletionModel(
            client: client,
            loadBillingState: { .applePremium },
            cleanup: {}
        )

        await model.prepare()

        #expect(model.state == .appleBillingWarning)
        #expect(await client.initiationCount() == 0)
        model.continueAfterAppleWarning()
        #expect(model.state == .ready(.applePremium))
    }

    @Test @MainActor
    func freeDeletionSupportsWrongCodeAndResendWithoutLosingTheSession() async {
        let client = AccountDeletionClientStub(
            verificationResults: [
                .failure(AccountDeletionClientError.rejected(
                    message: "Invalid verification code. 2 attempt(s) remaining."
                )),
                .success(AccountDeletionVerification(
                    message: "Identity verified successfully.",
                    sessionToken: Self.sessionToken,
                    type: "account"
                )),
            ]
        )
        let model = AccountDeletionModel(
            client: client,
            loadBillingState: { .free },
            cleanup: {}
        )

        await model.prepare()
        await model.initiate()
        model.setVerificationCode("12a34567")
        #expect(model.verificationCode == "123456")
        await model.verify()
        #expect(model.state == .verificationRequired(usesAuthenticator: false))
        #expect(model.errorMessage == "Invalid verification code. 2 attempt(s) remaining.")

        await model.resend()
        #expect(model.message == "Verification code sent to your email.")
        await model.verify()
        #expect(model.state == .verified)
        #expect(await client.verifiedCodes() == ["123456", "123456"])
    }

    @Test @MainActor
    func expiredVerificationSessionReturnsToAStartableState() async {
        let client = AccountDeletionClientStub(
            verificationResults: [
                .failure(AccountDeletionClientError.rejected(
                    message: "Invalid or expired session. Please start again."
                )),
            ]
        )
        let model = AccountDeletionModel(
            client: client,
            loadBillingState: { .free },
            cleanup: {}
        )

        await model.prepare()
        await model.initiate()
        model.setVerificationCode("123456")
        await model.verify()

        #expect(model.state == .ready(.free))
        #expect(model.errorMessage == "Invalid or expired session. Please start again.")
        await model.initiate()
        #expect(model.state == .verificationRequired(usesAuthenticator: false))
    }

    @Test @MainActor
    func immediateDeletionCleansLocalStateOnlyAfterServerAcknowledgement() async {
        let cleanup = CleanupRecorder()
        let client = AccountDeletionClientStub(
            executionResult: AccountDeletionResult(
                message: "Your account has been deleted.",
                type: "account",
                logoutRequired: true,
                scheduledDeletionAt: nil
            )
        )
        let model = AccountDeletionModel(
            client: client,
            loadBillingState: { .webPremium },
            cleanup: { await cleanup.record() }
        )

        await model.prepare()
        await model.initiate()
        model.setVerificationCode("123456")
        await model.verify()
        model.setConfirmation("delete my account")
        #expect(!model.canExecute)
        #expect(await cleanup.count() == 0)

        model.setConfirmation(AccountDeletionModel.confirmationPhrase)
        await model.execute()

        #expect(model.state == .deleted(message: "Your account has been deleted."))
        #expect(await cleanup.count() == 1)
    }

    @Test @MainActor
    func scheduledDeletionKeepsTheSessionAndCanBeCancelled() async {
        let cleanup = CleanupRecorder()
        let client = AccountDeletionClientStub(
            executionResult: AccountDeletionResult(
                message: "Your account is scheduled for deletion on 31 July 2026.",
                type: "account_scheduled",
                logoutRequired: false,
                scheduledDeletionAt: "2026-07-31T23:59:59+00:00"
            )
        )
        let model = AccountDeletionModel(
            client: client,
            loadBillingState: { .webPremium },
            cleanup: { await cleanup.record() }
        )

        await model.prepare()
        await model.initiate()
        model.setVerificationCode("123456")
        await model.verify()
        model.setConfirmation(AccountDeletionModel.confirmationPhrase)
        await model.execute()

        #expect(model.state == .scheduled(
            message: "Your account is scheduled for deletion on 31 July 2026.",
            deletionAt: "2026-07-31T23:59:59+00:00"
        ))
        #expect(await cleanup.count() == 0)
        await model.cancelScheduled()
        #expect(model.state == .cancelled(message: "Scheduled deletion cancelled."))
        #expect(await client.cancellationCount() == 1)
    }

    private static let sessionToken = String(repeating: "s", count: 64)

    private func apiClient(_ transport: TestHTTPTransport) -> APIClient {
        APIClient(
            environment: try! AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: AccountDeletionTokenProvider(),
            requestID: { "deletion-request" }
        )
    }

    private func body(_ request: URLRequest) -> [String: String] {
        guard let data = request.httpBody,
              let object = try? JSONSerialization.jsonObject(with: data) as? [String: String]
        else { return [:] }
        return object
    }

    private func json(_ value: String) -> Data { Data(value.utf8) }
}

private struct AccountDeletionTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "deletion-token" }
}

private actor AccountDeletionClientStub: AccountDeletionClient {
    private var initiations = 0
    private var codes: [String] = []
    private var cancellations = 0
    private var verificationResults: [Result<AccountDeletionVerification, Error>]
    private let executionResult: AccountDeletionResult

    init(
        verificationResults: [Result<AccountDeletionVerification, Error>] = [
            .success(AccountDeletionVerification(
                message: "Identity verified successfully.",
                sessionToken: String(repeating: "s", count: 64),
                type: "account"
            )),
        ],
        executionResult: AccountDeletionResult = AccountDeletionResult(
            message: "Your account has been deleted.",
            type: "account",
            logoutRequired: true,
            scheduledDeletionAt: nil
        )
    ) {
        self.verificationResults = verificationResults
        self.executionResult = executionResult
    }

    func initiate() async throws -> AccountDeletionInitiation {
        initiations += 1
        return AccountDeletionInitiation(
            requiresTwoFactor: false,
            requiresEmailVerification: true,
            sessionToken: String(repeating: "s", count: 64)
        )
    }

    func verify(sessionToken: String, code: String) async throws -> AccountDeletionVerification {
        codes.append(code)
        guard !verificationResults.isEmpty else { throw AccountDeletionClientError.invalidResponse }
        return try verificationResults.removeFirst().get()
    }

    func resend(sessionToken: String) async throws -> AccountDeletionMessage {
        AccountDeletionMessage(message: "Verification code sent to your email.")
    }

    func execute(sessionToken: String, confirmation: String) async throws -> AccountDeletionResult {
        executionResult
    }

    func cancelScheduled() async throws -> AccountDeletionMessage {
        cancellations += 1
        return AccountDeletionMessage(message: "Scheduled deletion cancelled.")
    }

    func initiationCount() -> Int { initiations }
    func verifiedCodes() -> [String] { codes }
    func cancellationCount() -> Int { cancellations }
}

private actor CleanupRecorder {
    private var value = 0
    func record() { value += 1 }
    func count() -> Int { value }
}
