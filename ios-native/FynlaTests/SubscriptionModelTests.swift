import Foundation
import Testing
@testable import Fynla

@Suite("Subscription model")
@MainActor
struct SubscriptionModelTests {
    @Test
    func loadStartsCanonicalEntitlementAndProductsTogetherThenShowsFreeProducts() async throws {
        let gate = LoadGate()
        let api = SubscriptionAPISpy(
            entitlements: [.free],
            loadGate: gate
        )
        let products = StoreProductsSpy(
            products: [.monthly, .annual],
            loadGate: gate
        )
        let model = SubscriptionModel(api: api, storeKit: products)

        let load = Task { await model.load() }
        await gate.waitUntilBothStarted()
        #expect(model.state == .loading)
        await gate.release()
        await load.value

        #expect(
            model.state == .free(
                products: [.monthly, .annual],
                selectedProductID: StoreProductIdentifier.monthly,
                isPending: false
            )
        )
    }

    @Test
    func canonicalPremiumProviderChoosesManagementAndSuppressesProducts() async {
        let appleModel = SubscriptionModel(
            api: SubscriptionAPISpy(entitlements: [.applePremium]),
            storeKit: StoreProductsSpy(products: [.monthly, .annual])
        )
        await appleModel.load()
        #expect(appleModel.state == .applePremium(.applePremium))

        let webModel = SubscriptionModel(
            api: SubscriptionAPISpy(entitlements: [.webPremium]),
            storeKit: StoreProductsSpy(products: [.monthly, .annual])
        )
        await webModel.load()
        #expect(webModel.state == .webPremium(.webPremium))
    }

    @Test
    func freeSelectionUsesStoreKitLocalizedPriceAndPeriod() async {
        let model = SubscriptionModel(
            api: SubscriptionAPISpy(entitlements: [.free]),
            storeKit: StoreProductsSpy(products: [.monthly, .annual])
        )
        await model.load()
        model.selectProduct(StoreProductIdentifier.annual)

        #expect(model.selectedProduct?.displayPrice == "£59.99")
        #expect(model.selectedProduct?.periodLabel == "per year")
    }

    @Test
    func verifiedPurchaseAcknowledgesBeforeFinishThenReloadsCanonicalEntitlement() async {
        let events = EventRecorder()
        let finish = FinishRecorder(events: events)
        let transaction = SignedStoreTransaction.testing(
            id: 42,
            originalID: 40,
            productID: StoreProductIdentifier.monthly,
            appAccountToken: .testAccount,
            signedJWS: "header.payload.signature",
            finish: { await finish.record() }
        )
        let api = SubscriptionAPISpy(
            entitlements: [.free, .applePremium],
            events: events
        )
        let store = StoreProductsSpy(
            products: [.monthly, .annual],
            purchaseOutcome: .verified(transaction),
            events: events
        )
        let model = SubscriptionModel(api: api, storeKit: store)
        await model.load()

        await model.purchase()

        #expect(model.state == .applePremium(.applePremium))
        #expect(await finish.count == 1)
        let purchaseEvents = await events.values().filter {
            ["purchase", "acknowledge", "finish", "entitlement"].contains($0)
        }
        #expect(Array(purchaseEvents.suffix(4)) == [
            "purchase", "acknowledge", "finish", "entitlement",
        ])
    }

    @Test
    func failedAcknowledgementLeavesTransactionUnfinishedAndNeverClaimsPremium() async {
        let finish = FinishRecorder()
        let transaction = SignedStoreTransaction.testing(
            id: 43,
            originalID: 40,
            productID: StoreProductIdentifier.monthly,
            appAccountToken: .testAccount,
            signedJWS: "header.payload.signature",
            finish: { await finish.record() }
        )
        let api = SubscriptionAPISpy(
            entitlements: [.free],
            acknowledgement: false
        )
        let model = SubscriptionModel(
            api: api,
            storeKit: StoreProductsSpy(
                products: [.monthly, .annual],
                purchaseOutcome: .verified(transaction)
            )
        )
        await model.load()

        await model.purchase()

        #expect(await finish.count == 0)
        #expect(model.state.isFree)
        #expect(model.message == "We couldn't activate Premium. Please try again later.")
    }

    @Test
    func pendingPurchaseDisablesFurtherPurchaseAttemptsWithoutRetrying() async {
        let store = StoreProductsSpy(
            products: [.monthly, .annual],
            purchaseOutcome: .pending
        )
        let model = SubscriptionModel(
            api: SubscriptionAPISpy(entitlements: [.free]),
            storeKit: store
        )
        await model.load()

        await model.purchase()
        await model.purchase()

        #expect(model.state.isPending)
        #expect(model.message == "Purchase pending approval. Premium will activate after the App Store confirms it.")
        #expect(await store.purchaseCount() == 1)
    }

    @Test
    func cancellationKeepsFreeStateWithoutAnError() async {
        let model = SubscriptionModel(
            api: SubscriptionAPISpy(entitlements: [.free]),
            storeKit: StoreProductsSpy(
                products: [.monthly, .annual],
                purchaseOutcome: .userCancelled
            )
        )
        await model.load()

        await model.purchase()

        #expect(model.state.isFree)
        #expect(model.message == nil)
    }

    @Test
    func transactionUpdateUsesTheSameAcknowledgementFinishAndReloadPath() async {
        let finish = FinishRecorder()
        let transaction = SignedStoreTransaction.testing(
            id: 44,
            originalID: 40,
            productID: StoreProductIdentifier.monthly,
            appAccountToken: .testAccount,
            signedJWS: "update.header.payload",
            finish: { await finish.record() }
        )
        let api = SubscriptionAPISpy(entitlements: [.free, .applePremium])
        let store = StoreProductsSpy(products: [.monthly, .annual])
        let model = SubscriptionModel(api: api, storeKit: store)
        await model.start()

        store.sendUpdate(transaction)
        await eventually { await finish.count == 1 }

        #expect(model.state == .applePremium(.applePremium))
        #expect(await api.acknowledgedJWS() == ["update.header.payload"])
    }

    @Test
    func restoreSyncsThenReconcilesServerHistoryThenReloadsCanonicalEntitlement() async {
        let events = EventRecorder()
        let restored = SignedStoreTransaction.testing(
            id: 45,
            originalID: 40,
            productID: StoreProductIdentifier.annual,
            appAccountToken: .testAccount,
            signedJWS: "restored.header.payload"
        )
        let api = SubscriptionAPISpy(
            entitlements: [.free, .applePremium],
            events: events
        )
        let store = StoreProductsSpy(
            products: [.monthly, .annual],
            currentEntitlements: [restored],
            events: events
        )
        let model = SubscriptionModel(api: api, storeKit: store)
        await model.load()

        await model.restore()

        #expect(model.state == .applePremium(.applePremium))
        #expect(await events.values().suffix(4) == [
            "sync", "currentEntitlements", "reconcile:40", "entitlement",
        ])
    }
}

private actor LoadGate {
    private var arrivals = 0
    private var bothStartedContinuation: CheckedContinuation<Void, Never>?
    private var releaseContinuations: [CheckedContinuation<Void, Never>] = []

    func arriveAndWait() async {
        arrivals += 1
        if arrivals == 2 {
            bothStartedContinuation?.resume()
            bothStartedContinuation = nil
        }
        await withCheckedContinuation { continuation in
            releaseContinuations.append(continuation)
        }
    }

    func waitUntilBothStarted() async {
        guard arrivals < 2 else { return }
        await withCheckedContinuation { continuation in
            bothStartedContinuation = continuation
        }
    }

    func release() {
        let continuations = releaseContinuations
        releaseContinuations.removeAll()
        continuations.forEach { $0.resume() }
    }
}

private actor SubscriptionAPISpy: SubscriptionAPI {
    private var entitlements: [NativeEntitlement]
    private let loadGate: LoadGate?
    private let acknowledgement: Bool
    private let events: EventRecorder?
    private var submittedJWS: [String] = []

    init(
        entitlements: [NativeEntitlement],
        loadGate: LoadGate? = nil,
        acknowledgement: Bool = true,
        events: EventRecorder? = nil
    ) {
        self.entitlements = entitlements
        self.loadGate = loadGate
        self.acknowledgement = acknowledgement
        self.events = events
    }

    func entitlement() async throws -> NativeEntitlement {
        if let loadGate { await loadGate.arriveAndWait() }
        await events?.append("entitlement")
        return entitlements.removeFirst()
    }

    func appAccountToken() async throws -> UUID {
        .testAccount
    }

    func acknowledge(_ transaction: SignedStoreTransaction) async throws -> Bool {
        submittedJWS.append(transaction.signedJWS)
        await events?.append("acknowledge")
        return acknowledgement
    }

    func reconcile(originalTransactionID: String) async throws {
        await events?.append("reconcile:\(originalTransactionID)")
    }

    func acknowledgedJWS() -> [String] { submittedJWS }
}

private actor StoreProductsSpy: StoreKitClient {
    private let availableProducts: [StoreProduct]
    private let loadGate: LoadGate?
    private let purchaseOutcome: PurchaseOutcome
    private let entitlementTransactions: [SignedStoreTransaction]
    private let events: EventRecorder?
    private var purchases = 0
    nonisolated private let updateStream: AsyncStream<SignedStoreTransaction>
    nonisolated private let updateContinuation: AsyncStream<SignedStoreTransaction>.Continuation

    init(
        products: [StoreProduct],
        loadGate: LoadGate? = nil,
        purchaseOutcome: PurchaseOutcome = .userCancelled,
        currentEntitlements: [SignedStoreTransaction] = [],
        events: EventRecorder? = nil
    ) {
        availableProducts = products
        self.loadGate = loadGate
        self.purchaseOutcome = purchaseOutcome
        entitlementTransactions = currentEntitlements
        self.events = events
        (updateStream, updateContinuation) = AsyncStream.makeStream(
            of: SignedStoreTransaction.self
        )
    }

    func products() async throws -> [StoreProduct] {
        if let loadGate { await loadGate.arriveAndWait() }
        return availableProducts
    }

    func purchase(
        _ productID: String,
        appAccountToken: UUID
    ) async throws -> PurchaseOutcome {
        purchases += 1
        await events?.append("purchase")
        return purchaseOutcome
    }

    nonisolated func updates() -> AsyncStream<SignedStoreTransaction> {
        updateStream
    }

    func sync() async throws {
        await events?.append("sync")
    }

    func currentEntitlements() async -> [SignedStoreTransaction] {
        await events?.append("currentEntitlements")
        return entitlementTransactions
    }

    nonisolated func sendUpdate(_ transaction: SignedStoreTransaction) {
        updateContinuation.yield(transaction)
    }

    func purchaseCount() -> Int { purchases }
}

private extension NativeEntitlement {
    static let free = Self(
        tier: .free,
        provider: nil,
        status: "free",
        renews: false,
        currentPeriodEnd: nil,
        capabilities: ["dashboard": "full"],
        limits: ["savings_account": 2],
        billingManagement: .none
    )

    static let applePremium = Self(
        tier: .premium,
        provider: "apple",
        status: "active",
        renews: true,
        currentPeriodEnd: "2026-08-18T20:00:00Z",
        capabilities: ["dashboard": "full"],
        limits: ["savings_account": nil],
        billingManagement: .apple
    )

    static let webPremium = Self(
        tier: .premium,
        provider: "revolut",
        status: "active",
        renews: true,
        currentPeriodEnd: "2026-08-18T20:00:00Z",
        capabilities: ["dashboard": "full"],
        limits: ["savings_account": nil],
        billingManagement: .web
    )
}

private extension StoreProduct {
    static let monthly = Self(
        id: StoreProductIdentifier.monthly,
        displayName: "Fynla Premium Monthly",
        description: "Fynla Premium",
        displayPrice: "£6.99",
        subscriptionPeriod: .init(value: 1, unit: .month)
    )

    static let annual = Self(
        id: StoreProductIdentifier.annual,
        displayName: "Fynla Premium Annual",
        description: "Fynla Premium",
        displayPrice: "£59.99",
        subscriptionPeriod: .init(value: 1, unit: .year)
    )
}

private actor FinishRecorder {
    private(set) var count = 0
    private let events: EventRecorder?

    init(events: EventRecorder? = nil) {
        self.events = events
    }

    func record() async {
        count += 1
        await events?.append("finish")
    }
}

private actor EventRecorder {
    private var recorded: [String] = []

    func append(_ event: String) {
        recorded.append(event)
    }

    func values() -> [String] { recorded }
}

private extension UUID {
    static let testAccount = UUID(
        uuidString: "19659A36-8E55-4F95-98CB-3F5D61F489AA"
    )!
}

private extension SubscriptionUIState {
    var isFree: Bool {
        if case .free = self { return true }
        return false
    }

    var isPending: Bool {
        if case let .free(_, _, isPending) = self { return isPending }
        return false
    }
}

private func eventually(
    _ condition: @escaping @Sendable () async -> Bool
) async {
    for _ in 0..<100 where !(await condition()) {
        await Task.yield()
    }
}
