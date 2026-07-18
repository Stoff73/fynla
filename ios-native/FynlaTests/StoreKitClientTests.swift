import Foundation
import Testing
@testable import Fynla

@Suite("StoreKit client contract")
struct StoreKitClientTests {
    @Test
    func injectedClientPreservesProductsAndPurchaseOutcomes() async throws {
        let token = UUID(uuidString: "19659A36-8E55-4F95-98CB-3F5D61F489AA")!
        let finishRecorder = FinishRecorder()
        let transaction = SignedStoreTransaction.testing(
            id: 42,
            originalID: 40,
            productID: StoreProductIdentifier.monthly,
            appAccountToken: token,
            signedJWS: "header.payload.signature",
            finish: { await finishRecorder.record() }
        )
        let product = StoreProduct(
            id: StoreProductIdentifier.monthly,
            displayName: "Fynla Premium Monthly",
            description: "Fynla Premium",
            displayPrice: "£6.99",
            subscriptionPeriod: .init(value: 1, unit: .month)
        )
        let client = InjectedStoreKitClient(
            products: [product],
            purchaseOutcome: .verified(transaction),
            updates: [transaction]
        )

        #expect(try await client.products() == [product])
        #expect(
            try await client.purchase(product.id, appAccountToken: token)
                == .verified(transaction)
        )

        var updateIterator = client.updates().makeAsyncIterator()
        #expect(await updateIterator.next() == transaction)
        try await client.sync()

        #expect(await finishRecorder.count == 0)
        await transaction.finish()
        #expect(await finishRecorder.count == 1)

        let calls = await client.recordedCalls()
        #expect(calls.purchaseProductID == product.id)
        #expect(calls.appAccountToken == token)
        #expect(calls.syncCount == 1)
    }

    @Test
    func purchaseErrorsAreTypedAndSafeToPresent() {
        #expect(StoreKitClientError.unexpectedProduct.safeMessage == "This subscription is unavailable.")
        #expect(
            StoreKitClientError.unverifiedTransaction.safeMessage
                == "We couldn't verify this purchase with the App Store. Please try again."
        )
    }
}

private actor FinishRecorder {
    private(set) var count = 0

    func record() {
        count += 1
    }
}

private actor InjectedStoreKitClient: StoreKitClient {
    struct Calls: Sendable, Equatable {
        var purchaseProductID: String?
        var appAccountToken: UUID?
        var syncCount = 0
    }

    private let availableProducts: [StoreProduct]
    private let purchaseOutcome: PurchaseOutcome
    nonisolated let updateStream: AsyncStream<SignedStoreTransaction>
    private var calls = Calls()

    init(
        products: [StoreProduct],
        purchaseOutcome: PurchaseOutcome,
        updates: [SignedStoreTransaction]
    ) {
        availableProducts = products
        self.purchaseOutcome = purchaseOutcome
        updateStream = AsyncStream(
            SignedStoreTransaction.self,
            bufferingPolicy: .unbounded
        ) { continuation in
            for update in updates {
                continuation.yield(update)
            }
            continuation.finish()
        }
    }

    func products() async throws -> [StoreProduct] {
        availableProducts
    }

    func purchase(
        _ productID: String,
        appAccountToken: UUID
    ) async throws -> PurchaseOutcome {
        calls.purchaseProductID = productID
        calls.appAccountToken = appAccountToken
        return purchaseOutcome
    }

    nonisolated func updates() -> AsyncStream<SignedStoreTransaction> {
        updateStream
    }

    func sync() async throws {
        calls.syncCount += 1
    }

    func recordedCalls() -> Calls {
        calls
    }
}
