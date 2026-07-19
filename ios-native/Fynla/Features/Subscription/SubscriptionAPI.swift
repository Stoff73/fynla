import Foundation

struct LiveSubscriptionAPI: SubscriptionAPI {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func entitlement() async throws -> NativeEntitlement {
        let response = try await apiClient.send(
            APIRequest<EntitlementResponse>(
                path: "api/v1/native/entitlement",
                method: .get
            )
        )
        return response.entitlement
    }

    func authorizePurchase() async throws -> Bool {
        let response = try await apiClient.send(
            APIRequest<PurchaseAuthorizationResponse>(
                path: "api/v1/native/storekit/purchase-authorization",
                method: .get
            )
        )
        return response.enabled
    }

    func appAccountToken() async throws -> UUID {
        let response = try await apiClient.send(
            APIRequest<AppAccountTokenResponse>(
                path: "api/v1/native/storekit/account-token",
                method: .get
            )
        )
        guard let token = UUID(uuidString: response.appAccountToken) else {
            throw APIError.decoding(requestID: nil)
        }
        return token
    }

    func acknowledge(_ transaction: SignedStoreTransaction) async throws -> Bool {
        let body = try JSONEncoder().encode(
            TransactionSubmission(signedTransaction: transaction.signedJWS)
        )
        let response = try await apiClient.send(
            APIRequest<TransactionAcknowledgementResponse>(
                path: "api/v1/native/storekit/transactions",
                method: .post,
                body: body
            )
        )
        return response.acknowledged
    }

    func reconcile(originalTransactionID: String) async throws {
        let body = try JSONEncoder().encode(
            ReconciliationSubmission(
                originalTransactionID: originalTransactionID
            )
        )
        _ = try await apiClient.send(
            APIRequest<IgnoredSubscriptionResponse>(
                path: "api/v1/native/storekit/reconcile",
                method: .post,
                body: body
            )
        )
    }
}

private struct EntitlementResponse: Decodable, Sendable {
    let entitlement: NativeEntitlement
}

private struct PurchaseAuthorizationResponse: Decodable, Sendable {
    let enabled: Bool
}

private struct AppAccountTokenResponse: Decodable, Sendable {
    let appAccountToken: String

    private enum CodingKeys: String, CodingKey {
        case appAccountToken = "app_account_token"
    }
}

private struct TransactionSubmission: Encodable, Sendable {
    let signedTransaction: String

    private enum CodingKeys: String, CodingKey {
        case signedTransaction = "signed_transaction"
    }
}

private struct TransactionAcknowledgementResponse: Decodable, Sendable {
    let acknowledged: Bool
}

private struct ReconciliationSubmission: Encodable, Sendable {
    let originalTransactionID: String

    private enum CodingKeys: String, CodingKey {
        case originalTransactionID = "original_transaction_id"
    }
}

private struct IgnoredSubscriptionResponse: Decodable, Sendable {}
