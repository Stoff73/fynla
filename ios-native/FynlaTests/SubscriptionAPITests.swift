import Foundation
import Testing
@testable import Fynla

@Suite("Subscription API")
struct SubscriptionAPITests {
    @Test
    func usesOnlyTheAuthenticatedNativeBillingEndpointsAndTypedPayloads() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"entitlement":{"tier":"free","provider":null,"status":"free","renews":false,"current_period_end":null,"capabilities":{"dashboard":"full"},"limits":{"savings_account":2},"billing_management":"none"}}}"#.utf8)
            ),
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"enabled":true}}"#.utf8)
            ),
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"app_account_token":"19659a36-8e55-4f95-98cb-3f5d61f489aa"}}"#.utf8)
            ),
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"acknowledged":true,"transaction_id":"42","entitlement":{"tier":"premium","provider":"apple","status":"active","renews":true,"current_period_end":"2026-08-18T20:00:00Z"}}}"#.utf8)
            ),
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"entitlement":{"tier":"premium","provider":"apple","status":"active","renews":true,"current_period_end":"2026-08-18T20:00:00Z"}}}"#.utf8)
            ),
        ])
        let client = APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "42",
            transport: transport,
            tokenProvider: SubscriptionTokenProvider(),
            requestID: { "subscription-request" }
        )
        let api = LiveSubscriptionAPI(apiClient: client)

        #expect(try await api.entitlement().tier == .free)
        #expect(try await api.authorizePurchase())
        #expect(
            try await api.appAccountToken()
                == UUID(uuidString: "19659A36-8E55-4F95-98CB-3F5D61F489AA")
        )
        let acknowledged = try await api.acknowledge(
            .testing(
                id: 42,
                originalID: 40,
                productID: StoreProductIdentifier.monthly,
                appAccountToken: UUID(
                    uuidString: "19659A36-8E55-4F95-98CB-3F5D61F489AA"
                )!,
                signedJWS: "header.payload.signature"
            )
        )
        #expect(acknowledged)
        try await api.reconcile(originalTransactionID: "40")

        let requests = await transport.requests()
        #expect(requests.map { $0.httpMethod } == ["GET", "GET", "GET", "POST", "POST"])
        #expect(requests.map { $0.url?.path } == [
            "/fynla/api/v1/native/entitlement",
            "/fynla/api/v1/native/storekit/purchase-authorization",
            "/fynla/api/v1/native/storekit/account-token",
            "/fynla/api/v1/native/storekit/transactions",
            "/fynla/api/v1/native/storekit/reconcile",
        ])
        #expect(
            requests[3].httpBody
                == Data(#"{"signed_transaction":"header.payload.signature"}"#.utf8)
        )
        #expect(
            requests[4].httpBody
                == Data(#"{"original_transaction_id":"40"}"#.utf8)
        )
        #expect(requests.allSatisfy {
            $0.value(forHTTPHeaderField: "Authorization") == "Bearer native-token"
        })
    }
}

private struct SubscriptionTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "native-token" }
}
