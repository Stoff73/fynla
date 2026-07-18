import Foundation

protocol StoreKitClient: Sendable {
    func products() async throws -> [StoreProduct]
    func purchase(_ productID: String, appAccountToken: UUID) async throws -> PurchaseOutcome
    func updates() -> AsyncStream<SignedStoreTransaction>
    func sync() async throws
}
