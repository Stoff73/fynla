import Foundation

protocol StoreKitClient: Sendable {
    func products() async throws -> [StoreProduct]
    func purchase(_ productID: String, appAccountToken: UUID) async throws -> PurchaseOutcome
    func updates() -> AsyncStream<SignedStoreTransaction>
    func unfinishedTransactions() async -> [SignedStoreTransaction]
    func sync() async throws
    func currentEntitlements() async -> [SignedStoreTransaction]
}

extension StoreKitClient {
    func unfinishedTransactions() async -> [SignedStoreTransaction] {
        []
    }

    func currentEntitlements() async -> [SignedStoreTransaction] {
        []
    }
}
