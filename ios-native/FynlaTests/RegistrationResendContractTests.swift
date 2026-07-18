import Foundation
import Testing
@testable import Fynla

@Suite("Registration resend contract")
struct RegistrationResendContractTests {
    @Test
    func expiredResendOffersTheTypedStartOverPath() async throws {
        let fixture = try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/API/auth-registration-resend-expired.json")
        )
        let transport = TestHTTPTransport([
            .response(status: 422, body: fixture),
        ])
        let client = APIAuthClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.2.3",
            build: "45",
            transport: transport,
            requestID: { "registration-resend-contract" }
        )

        do {
            _ = try await client.resendRegistration(pendingID: 321)
            Issue.record("Expected registration-unavailable error")
        } catch let error as AuthError {
            #expect(
                error == .registrationUnavailable(
                    message: "Registration has expired. Please register again."
                )
            )
        } catch {
            Issue.record("Unexpected error type")
        }
    }
}
