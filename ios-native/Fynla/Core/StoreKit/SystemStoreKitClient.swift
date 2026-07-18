import Foundation
import StoreKit

final class StoreKitTransactionUpdateBroadcaster: @unchecked Sendable {
    private typealias Continuation =
        AsyncStream<SignedStoreTransaction>.Continuation

    private let lock = NSLock()
    private var subscribers: [UUID: Continuation] = [:]

    func stream() -> AsyncStream<SignedStoreTransaction> {
        let subscriberID = UUID()
        let (stream, continuation) = AsyncStream.makeStream(
            of: SignedStoreTransaction.self
        )
        continuation.onTermination = { [weak self] _ in
            self?.removeSubscriber(subscriberID)
        }
        lock.withLock {
            subscribers[subscriberID] = continuation
        }
        return stream
    }

    func broadcast(_ transaction: SignedStoreTransaction) {
        let continuations = lock.withLock { Array(subscribers.values) }
        continuations.forEach { $0.yield(transaction) }
    }

    private func removeSubscriber(_ subscriberID: UUID) {
        _ = lock.withLock {
            subscribers.removeValue(forKey: subscriberID)
        }
    }
}

final class SystemStoreKitClient: StoreKitClient, Sendable {
    private let productIDs = StoreProductIdentifier.all
    private static let updateBroadcaster = StoreKitTransactionUpdateBroadcaster()
    private static let transactionUpdateProducer: Task<Void, Never> = {
        let broadcaster = updateBroadcaster
        return Task.detached(priority: .utility) {
            for await verification in Transaction.updates {
                guard let transaction = try? SystemStoreKitClient.signedTransaction(
                          verification,
                          allowedProductIDs: StoreProductIdentifier.all,
                          expectedProductID: nil,
                          expectedAppAccountToken: nil
                      )
                else {
                    continue
                }
                broadcaster.broadcast(transaction)
            }
        }
    }()

    init() {
        _ = Self.transactionUpdateProducer
    }

    func products() async throws -> [StoreProduct] {
        let products = try await Product.products(for: productIDs)
        guard products.allSatisfy({ productIDs.contains($0.id) }) else {
            throw StoreKitClientError.unexpectedProduct
        }
        guard Set(products.map(\.id)) == productIDs else {
            throw StoreKitClientError.productUnavailable
        }

        return try products
            .map(Self.storeProduct)
            .sorted { Self.productRank($0.id) < Self.productRank($1.id) }
    }

    func purchase(
        _ productID: String,
        appAccountToken: UUID
    ) async throws -> PurchaseOutcome {
        guard productIDs.contains(productID) else {
            throw StoreKitClientError.unexpectedProduct
        }

        let products = try await Product.products(for: [productID])
        guard products.allSatisfy({ productIDs.contains($0.id) }) else {
            throw StoreKitClientError.unexpectedProduct
        }
        guard products.count == 1, let product = products.first,
              product.id == productID
        else {
            throw StoreKitClientError.productUnavailable
        }

        let result: Product.PurchaseResult
        do {
            result = try await product.purchase(options: [
                .appAccountToken(appAccountToken),
            ])
        } catch StoreKitError.userCancelled {
            return .userCancelled
        }

        switch result {
        case let .success(verification):
            return .verified(
                try Self.signedTransaction(
                    verification,
                    allowedProductIDs: productIDs,
                    expectedProductID: productID,
                    expectedAppAccountToken: appAccountToken
                )
            )
        case .pending:
            return .pending
        case .userCancelled:
            return .userCancelled
        @unknown default:
            throw StoreKitClientError.unsupportedPurchaseResult
        }
    }

    func updates() -> AsyncStream<SignedStoreTransaction> {
        Self.updateBroadcaster.stream()
    }

    func sync() async throws {
        try await AppStore.sync()
    }

    func currentEntitlements() async -> [SignedStoreTransaction] {
        var transactions: [SignedStoreTransaction] = []
        for await verification in Transaction.currentEntitlements {
            guard let transaction = try? Self.signedTransaction(
                verification,
                allowedProductIDs: productIDs,
                expectedProductID: nil,
                expectedAppAccountToken: nil
            ) else {
                continue
            }
            transactions.append(transaction)
        }
        return transactions
    }

    private static func storeProduct(_ product: Product) throws -> StoreProduct {
        guard let period = product.subscription?.subscriptionPeriod else {
            throw StoreKitClientError.missingSubscriptionMetadata
        }

        return StoreProduct(
            id: product.id,
            displayName: product.displayName,
            description: product.description,
            displayPrice: product.displayPrice,
            subscriptionPeriod: StoreSubscriptionPeriod(
                value: period.value,
                unit: try storeUnit(period.unit)
            )
        )
    }

    private static func storeUnit(
        _ unit: Product.SubscriptionPeriod.Unit
    ) throws -> StoreSubscriptionPeriod.Unit {
        switch unit {
        case .day:
            .day
        case .week:
            .week
        case .month:
            .month
        case .year:
            .year
        @unknown default:
            throw StoreKitClientError.missingSubscriptionMetadata
        }
    }

    private static func signedTransaction(
        _ verification: VerificationResult<Transaction>,
        allowedProductIDs: Set<String>,
        expectedProductID: String?,
        expectedAppAccountToken: UUID?
    ) throws -> SignedStoreTransaction {
        guard case let .verified(transaction) = verification else {
            throw StoreKitClientError.unverifiedTransaction
        }
        guard allowedProductIDs.contains(transaction.productID),
              expectedProductID == nil || transaction.productID == expectedProductID
        else {
            throw StoreKitClientError.unexpectedProduct
        }
        guard let appAccountToken = transaction.appAccountToken,
              expectedAppAccountToken == nil || appAccountToken == expectedAppAccountToken
        else {
            throw StoreKitClientError.appAccountTokenMismatch
        }

        return SignedStoreTransaction(
            id: transaction.id,
            originalID: transaction.originalID,
            productID: transaction.productID,
            appAccountToken: appAccountToken,
            purchaseDate: transaction.purchaseDate,
            expirationDate: transaction.expirationDate,
            revocationDate: transaction.revocationDate,
            signedJWS: verification.jwsRepresentation,
            finish: { await transaction.finish() }
        )
    }

    private static func productRank(_ productID: String) -> Int {
        switch productID {
        case StoreProductIdentifier.monthly:
            0
        case StoreProductIdentifier.annual:
            1
        default:
            2
        }
    }
}
