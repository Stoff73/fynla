import Foundation
import Observation

enum NativeSubscriptionTier: String, Decodable, Sendable, Equatable {
    case free
    case premium
}

enum NativeBillingManagement: String, Decodable, Sendable, Equatable {
    case apple
    case web
    case none
}

struct NativeEntitlement: Decodable, Sendable, Equatable {
    let tier: NativeSubscriptionTier
    let provider: String?
    let status: String
    let renews: Bool
    let currentPeriodEnd: String?
    let capabilities: [String: String]
    let limits: [String: Int?]
    let billingManagement: NativeBillingManagement

    private enum CodingKeys: String, CodingKey {
        case tier
        case provider
        case status
        case renews
        case currentPeriodEnd = "current_period_end"
        case capabilities
        case limits
        case billingManagement = "billing_management"
    }
}

protocol SubscriptionAPI: Sendable {
    func entitlement() async throws -> NativeEntitlement
    func appAccountToken() async throws -> UUID
    func acknowledge(_ transaction: SignedStoreTransaction) async throws -> Bool
    func reconcile(originalTransactionID: String) async throws
}

enum SubscriptionUIState: Sendable, Equatable {
    case loading
    case free(
        products: [StoreProduct],
        selectedProductID: String,
        isPending: Bool
    )
    case applePremium(NativeEntitlement)
    case webPremium(NativeEntitlement)
    case unavailable(message: String)
}

@MainActor
@Observable
final class SubscriptionModel {
    private(set) var state: SubscriptionUIState = .loading
    private(set) var message: String?
    private(set) var isPurchasing = false
    private(set) var isRestoring = false

    private let api: any SubscriptionAPI
    private let storeKit: any StoreKitClient
    private var availableProducts: [StoreProduct] = []
    private var processingTransactionIDs: Set<UInt64> = []
    private var updateTask: Task<Void, Never>?

    init(api: any SubscriptionAPI, storeKit: any StoreKitClient) {
        self.api = api
        self.storeKit = storeKit
    }

    var selectedProduct: StoreProduct? {
        guard case let .free(_, selectedProductID, _) = state else {
            return nil
        }
        return availableProducts.first { $0.id == selectedProductID }
    }

    var canPurchase: Bool {
        guard case let .free(_, _, isPending) = state else { return false }
        return !isPending && !isPurchasing && !isRestoring
    }

    func start() async {
        if updateTask == nil {
            let storeKit = self.storeKit
            updateTask = Task { [weak self] in
                for await transaction in storeKit.updates() {
                    guard !Task.isCancelled else { return }
                    await self?.process(transaction)
                }
            }
        }
        await load()
    }

    func stop() {
        updateTask?.cancel()
        updateTask = nil
        state = .loading
        message = nil
        isPurchasing = false
        isRestoring = false
        availableProducts = []
        processingTransactionIDs = []
    }

    func load() async {
        state = .loading
        message = nil
        async let entitlementResult = loadEntitlement()
        async let productsResult = loadProducts()
        let (entitlement, products) = await (entitlementResult, productsResult)

        guard case let .success(resolvedEntitlement) = entitlement else {
            state = .unavailable(
                message: "Subscription details are unavailable. Please try again."
            )
            return
        }

        switch products {
        case let .success(products):
            availableProducts = products
        case .failure:
            availableProducts = []
        }

        present(
            entitlement: resolvedEntitlement,
            products: availableProducts
        )
    }

    func selectProduct(_ productID: String) {
        guard StoreProductIdentifier.all.contains(productID),
              availableProducts.contains(where: { $0.id == productID }),
              case let .free(products, _, isPending) = state,
              !isPending,
              !isPurchasing
        else {
            return
        }
        state = .free(
            products: products,
            selectedProductID: productID,
            isPending: false
        )
    }

    func purchase() async {
        guard canPurchase, let selectedProduct else { return }
        isPurchasing = true
        message = nil
        defer { isPurchasing = false }

        do {
            let token = try await api.appAccountToken()
            let outcome = try await storeKit.purchase(
                selectedProduct.id,
                appAccountToken: token
            )
            switch outcome {
            case let .verified(transaction):
                await process(transaction)
            case .pending:
                markPending()
            case .userCancelled:
                break
            }
        } catch {
            message = safeMessage(for: error)
        }
    }

    func restore() async {
        guard !isRestoring, !isPurchasing else { return }
        isRestoring = true
        message = nil
        defer { isRestoring = false }

        do {
            let accountToken = try await api.appAccountToken()
            try await storeKit.sync()
            let transaction = await storeKit.currentEntitlements()
                .filter {
                    $0.appAccountToken == accountToken
                        && StoreProductIdentifier.all.contains($0.productID)
                }
                .max { $0.purchaseDate < $1.purchaseDate }
            guard let transaction else {
                message = "No App Store Premium subscription was found for this account."
                return
            }
            try await api.reconcile(
                originalTransactionID: String(transaction.originalID)
            )
            await refreshCanonicalEntitlement()
        } catch {
            message = safeMessage(for: error)
        }
    }

    private func present(
        entitlement: NativeEntitlement,
        products: [StoreProduct]
    ) {
        switch (entitlement.tier, entitlement.billingManagement) {
        case (.premium, .apple):
            state = .applePremium(entitlement)
        case (.premium, .web):
            state = .webPremium(entitlement)
        case (.premium, .none):
            state = .unavailable(
                message: "Subscription management is unavailable. Please try again."
            )
        case (.free, _):
            let sorted = products.sorted { lhs, rhs in
                productRank(lhs.id) < productRank(rhs.id)
            }
            guard Set(sorted.map(\.id)) == StoreProductIdentifier.all,
                  let selected = sorted.first?.id
            else {
                state = .unavailable(
                    message: "Premium subscriptions are unavailable. Please try again later."
                )
                return
            }
            state = .free(
                products: sorted,
                selectedProductID: selected,
                isPending: false
            )
        }
    }

    private func process(_ transaction: SignedStoreTransaction) async {
        guard StoreProductIdentifier.all.contains(transaction.productID),
              processingTransactionIDs.insert(transaction.id).inserted
        else {
            return
        }
        defer { processingTransactionIDs.remove(transaction.id) }

        do {
            guard try await api.acknowledge(transaction) else {
                message = "We couldn't activate Premium. Please try again later."
                return
            }
            await transaction.finish()
            await refreshCanonicalEntitlement()
        } catch {
            message = safeMessage(for: error)
        }
    }

    private func refreshCanonicalEntitlement() async {
        do {
            let entitlement = try await api.entitlement()
            present(entitlement: entitlement, products: availableProducts)
            message = nil
        } catch {
            message = "Premium was verified, but your subscription details couldn't be refreshed. Please try again."
        }
    }

    private func markPending() {
        guard case let .free(products, selectedProductID, _) = state else {
            return
        }
        state = .free(
            products: products,
            selectedProductID: selectedProductID,
            isPending: true
        )
        message = "Purchase pending approval. Premium will activate after the App Store confirms it."
    }

    private func loadEntitlement() async -> SubscriptionLoadResult<NativeEntitlement> {
        do {
            return .success(try await api.entitlement())
        } catch {
            return .failure
        }
    }

    private func loadProducts() async -> SubscriptionLoadResult<[StoreProduct]> {
        do {
            return .success(try await storeKit.products())
        } catch {
            return .failure
        }
    }

    private func safeMessage(for error: Error) -> String {
        if let storeKitError = error as? StoreKitClientError {
            return storeKitError.safeMessage
        }
        if let apiError = error as? APIError, apiError == .offline {
            return "You're offline. Check your connection and try again."
        }
        return "We couldn't update your subscription. Please try again later."
    }

    private func productRank(_ productID: String) -> Int {
        productID == StoreProductIdentifier.monthly ? 0 : 1
    }
}

private enum SubscriptionLoadResult<Value: Sendable>: Sendable {
    case success(Value)
    case failure
}
