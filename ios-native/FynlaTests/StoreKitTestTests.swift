import Foundation
import StoreKit
import StoreKitTest
import Testing
@testable import Fynla

@Suite("Local StoreKit configuration", .serialized)
struct StoreKitTestTests {
    @Test
    func loadsExactlyTheApprovedProductsAndUKPrices() async throws {
        _ = try makeSession()
        let client = SystemStoreKitClient()

        let products = try await client.products()

        #expect(products.map(\.id) == [
            StoreProductIdentifier.monthly,
            StoreProductIdentifier.annual,
        ])
        #expect(products.map(\.displayPrice) == ["£6.99", "£59.99"])
        #expect(products.map(\.subscriptionPeriod) == [
            .init(value: 1, unit: .month),
            .init(value: 1, unit: .year),
        ])
    }

    @Test
    func purchaseReturnsVerifiedJWSWithStableAccountToken() async throws {
        let session = try makeSession()
        let client = SystemStoreKitClient()
        let token = UUID(uuidString: "9E7D314A-E607-4B93-B739-6864363CF913")!

        let outcome = try await client.purchase(
            StoreProductIdentifier.monthly,
            appAccountToken: token
        )
        let signed = try #require(verifiedTransaction(outcome))

        #expect(signed.productID == StoreProductIdentifier.monthly)
        #expect(signed.appAccountToken == token)
        #expect(!signed.signedJWS.isEmpty)
        #expect(session.allTransactions().count == 1)
    }

    @Test
    func askToBuyAndCancellationMapToSafeOutcomes() async throws {
        let session = try makeSession()
        let client = SystemStoreKitClient()
        let token = UUID(uuidString: "7BCB13E1-E3F9-4704-94F6-E3CB1B90C619")!
        session.askToBuyEnabled = true

        let pending = try await client.purchase(
            StoreProductIdentifier.monthly,
            appAccountToken: token
        )
        #expect(pending == .pending)
        let pendingTransaction = try #require(session.allTransactions().first)
        #expect(pendingTransaction.pendingAskToBuyConfirmation)
        try session.declineAskToBuyTransaction(identifier: pendingTransaction.identifier)

        session.askToBuyEnabled = false
        try await session.setSimulatedError(
            .generic(.userCancelled),
            forAPI: .purchase
        )
        let cancelled = try await client.purchase(
            StoreProductIdentifier.annual,
            appAccountToken: token
        )
        #expect(cancelled == .userCancelled)
    }

    @Test
    func unverifiedPurchaseNeverBecomesASignedSubmission() async throws {
        let session = try makeSession()
        let client = SystemStoreKitClient()
        try await session.setSimulatedError(
            .verification(.invalidSignature),
            forAPI: .verification
        )

        await #expect(throws: StoreKitClientError.unverifiedTransaction) {
            _ = try await client.purchase(
                StoreProductIdentifier.monthly,
                appAccountToken: UUID()
            )
        }
    }

    @Test
    func verifiedUpdatesCarryJWSAndTokenWhileInvalidSignaturesAreFiltered() async throws {
        let session = try makeSession()
        let client = SystemStoreKitClient()
        let token = UUID(uuidString: "45FCF13B-7C77-4FCF-A86D-A5136AF49EC3")!

        let purchaseUpdate = Task {
            try await nextUpdate(from: client.updates()) {
                $0.appAccountToken == token
                    && $0.productID == StoreProductIdentifier.monthly
            }
        }
        await Task.yield()
        let original = try await session.buyProduct(
            identifier: StoreProductIdentifier.monthly,
            options: [.appAccountToken(token)]
        )
        let purchased = try await purchaseUpdate.value

        #expect(purchased.id == original.id)
        #expect(purchased.appAccountToken == token)
        #expect(!purchased.signedJWS.isEmpty)

        let renewalUpdate = Task {
            try await nextUpdate(from: client.updates()) {
                $0.appAccountToken == token && $0.id != purchased.id
            }
        }
        await Task.yield()
        try session.forceRenewalOfSubscription(
            productIdentifier: StoreProductIdentifier.monthly
        )
        let renewed = try await renewalUpdate.value

        #expect(renewed.originalID == purchased.originalID)
        #expect(renewed.appAccountToken == token)
        #expect(!renewed.signedJWS.isEmpty)

        try await session.setSimulatedError(
            .verification(.invalidSignature),
            forAPI: .verification
        )
        let invalidUpdate = Task {
            try await nextUpdate(
                from: client.updates(),
                timeout: .milliseconds(500)
            ) {
                $0.appAccountToken == token
                    && $0.id != purchased.id
                    && $0.id != renewed.id
            }
        }
        await Task.yield()
        try session.forceRenewalOfSubscription(
            productIdentifier: StoreProductIdentifier.monthly
        )

        await #expect(throws: StoreKitUpdateWaitError.self) {
            _ = try await invalidUpdate.value
        }
    }

    @Test
    func renewalExpiryAndRefundProduceDeterministicTransactions() async throws {
        let session = try makeSession()
        let token = UUID(uuidString: "90E0A67B-6629-43A9-BADC-8126E90E2115")!
        let original = try await session.buyProduct(
            identifier: StoreProductIdentifier.monthly,
            options: [.appAccountToken(token)]
        )

        try session.forceRenewalOfSubscription(
            productIdentifier: StoreProductIdentifier.monthly
        )
        let renewed = try #require(
            session.allTransactions().first(where: {
                $0.identifier != original.id
                    && $0.originalTransactionIdentifier == original.originalID
            })
        )
        #expect(renewed.productIdentifier == StoreProductIdentifier.monthly)

        try session.expireSubscription(
            productIdentifier: StoreProductIdentifier.monthly
        )
        let expired = try #require(
            session.allTransactions().first(where: { $0.identifier == renewed.identifier })
        )
        let expirationDate = try #require(expired.expirationDate)
        #expect(expirationDate <= Date())

        try session.refundTransaction(identifier: UInt(original.id))
        let refunded = try #require(
            session.allTransactions().first(where: { $0.identifier == original.id })
        )
        #expect(refunded.cancelDate != nil)
    }

    @Test
    func unexpectedProductIsRejectedBeforeStoreKit() async {
        let client = SystemStoreKitClient()

        await #expect(throws: StoreKitClientError.unexpectedProduct) {
            _ = try await client.purchase("org.fynla.unexpected", appAccountToken: UUID())
        }
    }

    private func makeSession() throws -> SKTestSession {
        let session = try SKTestSession(configurationFileNamed: "Fynla")
        session.disableDialogs = true
        session.clearTransactions()
        session.resetToDefaultState()
        session.disableDialogs = true
        session.storefront = "GBR"
        session.locale = Locale(identifier: "en_GB")
        return session
    }

    private func verifiedTransaction(
        _ outcome: PurchaseOutcome
    ) -> SignedStoreTransaction? {
        guard case let .verified(transaction) = outcome else { return nil }
        return transaction
    }

    private func nextUpdate(
        from stream: AsyncStream<SignedStoreTransaction>,
        timeout: Duration = .seconds(5),
        matching predicate: @escaping @Sendable (SignedStoreTransaction) -> Bool
    ) async throws -> SignedStoreTransaction {
        try await withThrowingTaskGroup(of: SignedStoreTransaction.self) { group in
            group.addTask {
                for await update in stream where predicate(update) {
                    return update
                }
                throw StoreKitUpdateWaitError.streamEnded
            }
            group.addTask {
                try await Task.sleep(for: timeout)
                throw StoreKitUpdateWaitError.timedOut
            }

            defer { group.cancelAll() }
            guard let update = try await group.next() else {
                throw StoreKitUpdateWaitError.streamEnded
            }
            return update
        }
    }
}

private enum StoreKitUpdateWaitError: Error {
    case streamEnded
    case timedOut
}
