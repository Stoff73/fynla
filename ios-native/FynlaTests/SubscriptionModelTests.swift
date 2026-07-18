import Foundation
import Testing
@testable import Fynla

@Suite("Subscription model")
@MainActor
struct SubscriptionModelTests {
    @Test
    func subscriptionDateAcceptsLaravelFractionalAndSecondOnlyISO8601Values() {
        let locale = Locale(identifier: "en_GB")
        let utc = TimeZone(secondsFromGMT: 0)!

        #expect(
            SubscriptionDateFormatter.string(
                from: "2026-08-18T20:00:00.123456Z",
                locale: locale,
                timeZone: utc
            ) == "18 August 2026"
        )
        #expect(
            SubscriptionDateFormatter.string(
                from: "2026-08-18T20:00:00Z",
                locale: locale,
                timeZone: utc
            ) == "18 August 2026"
        )
    }

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
        #expect(
            model.selectedProduct?.periodLabel(
                locale: Locale(identifier: "en_GB")
            ) == "1 year"
        )
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
    func pendingPurchaseSurvivesLockUnlockReloadAndStillBlocksRetry() async {
        let store = StoreProductsSpy(
            products: [.monthly, .annual],
            purchaseOutcome: .pending
        )
        let model = SubscriptionModel(
            api: SubscriptionAPISpy(entitlements: [.free, .free]),
            storeKit: store
        )
        await model.start()
        await model.purchase()

        await model.start()
        await model.purchase()

        #expect(model.state.isPending)
        #expect(!model.canPurchase)
        #expect(await store.purchaseCount() == 1)
    }

    @Test
    func verifiedUpdateWhilePrivacyLockedResolvesPendingToPremium() async {
        let finish = FinishRecorder()
        let transaction = SignedStoreTransaction.testing(
            id: 47,
            originalID: 40,
            productID: StoreProductIdentifier.monthly,
            appAccountToken: .testAccount,
            signedJWS: "locked.header.payload",
            finish: { await finish.record() }
        )
        let api = SubscriptionAPISpy(entitlements: [.free, .applePremium])
        let store = StoreProductsSpy(
            products: [.monthly, .annual],
            purchaseOutcome: .pending
        )
        let model = SubscriptionModel(api: api, storeKit: store)
        await model.start()
        await model.purchase()
        #expect(model.state.isPending)

        store.sendUpdate(transaction)
        await eventually { await finish.count == 1 }

        #expect(model.state == .applePremium(.applePremium))
        #expect(await store.purchaseCount() == 1)
    }

    @Test
    func signOutResetClearsPendingForNextAuthenticatedUser() async {
        let store = StoreProductsSpy(
            products: [.monthly, .annual],
            purchaseOutcome: .pending
        )
        let model = SubscriptionModel(
            api: SubscriptionAPISpy(entitlements: [.free, .free]),
            storeKit: store
        )
        await model.start()
        await model.purchase()
        #expect(model.state.isPending)

        model.stop()
        await model.start()

        #expect(model.state.isFree)
        #expect(!model.state.isPending)
        #expect(model.canPurchase)
    }

    @Test
    func stoppedModelDoesNotPreventNewModelReceivingLaterUpdate() async {
        let finish = FinishRecorder()
        let transaction = SignedStoreTransaction.testing(
            id: 48,
            originalID: 40,
            productID: StoreProductIdentifier.monthly,
            appAccountToken: .testAccount,
            signedJWS: "resubscribed.header.payload",
            finish: { await finish.record() }
        )
        let store = StoreProductsSpy(products: [.monthly, .annual])
        let oldModel = SubscriptionModel(
            api: SubscriptionAPISpy(entitlements: [.free]),
            storeKit: store
        )
        await oldModel.start()
        oldModel.stop()

        let newAPI = SubscriptionAPISpy(entitlements: [.free, .applePremium])
        let newModel = SubscriptionModel(api: newAPI, storeKit: store)
        await newModel.start()
        store.sendUpdate(transaction)
        await eventually { await finish.count == 1 }

        #expect(newModel.state == .applePremium(.applePremium))
        #expect(await newAPI.acknowledgedJWS() == ["resubscribed.header.payload"])
    }

    @Test
    func relaunchRecoversAnUnfinishedTransactionEmittedBeforeSubscription() async {
        let events = EventRecorder()
        let finish = FinishRecorder(events: events)
        let transaction = SignedStoreTransaction.testing(
            id: 49,
            originalID: 40,
            productID: StoreProductIdentifier.monthly,
            appAccountToken: .testAccount,
            signedJWS: "unfinished.header.payload",
            finish: { await finish.record() }
        )
        let store = StoreProductsSpy(
            products: [.monthly, .annual],
            unfinishedTransactions: [transaction],
            events: events
        )
        store.sendUpdate(transaction)

        let api = SubscriptionAPISpy(
            entitlements: [.applePremium, .applePremium],
            events: events
        )
        let relaunchedModel = SubscriptionModel(api: api, storeKit: store)
        await relaunchedModel.start()

        #expect(await finish.count == 1)
        #expect(relaunchedModel.state == .applePremium(.applePremium))
        #expect(await api.acknowledgedJWS() == ["unfinished.header.payload"])
        let recoveryEvents = await events.values().filter {
            ["unfinishedTransactions", "acknowledge", "finish"].contains($0)
        }
        #expect(recoveryEvents == [
            "unfinishedTransactions", "acknowledge", "finish",
        ])
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
    func staleFreeLoadCannotOverwritePremiumFromAcknowledgedUpdate() async {
        let api = SuspendedFirstEntitlementAPI(
            responses: [.free, .applePremium]
        )
        let finish = FinishRecorder()
        let transaction = SignedStoreTransaction.testing(
            id: 46,
            originalID: 40,
            productID: StoreProductIdentifier.monthly,
            appAccountToken: .testAccount,
            signedJWS: "race.header.payload",
            finish: { await finish.record() }
        )
        let store = StoreProductsSpy(products: [.monthly, .annual])
        let model = SubscriptionModel(api: api, storeKit: store)

        let start = Task { await model.start() }
        await api.waitUntilFirstRequestIsSuspended()
        store.sendUpdate(transaction)
        await api.waitForRequestCount(2)
        await eventually { await finish.count == 1 }
        #expect(model.state == .applePremium(.applePremium))

        await api.releaseFirstRequest()
        await start.value

        #expect(model.state == .applePremium(.applePremium))
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

private actor SuspendedFirstEntitlementAPI: SubscriptionAPI {
    private var responses: [NativeEntitlement]
    private var requestCount = 0
    private var firstRequestStarted: CheckedContinuation<Void, Never>?
    private var firstRequestRelease: CheckedContinuation<Void, Never>?
    private var requestCountWaiters: [(Int, CheckedContinuation<Void, Never>)] = []

    init(responses: [NativeEntitlement]) {
        self.responses = responses
    }

    func entitlement() async throws -> NativeEntitlement {
        let response = responses.removeFirst()
        requestCount += 1
        resumeSatisfiedRequestCountWaiters()
        if requestCount == 1 {
            firstRequestStarted?.resume()
            firstRequestStarted = nil
            await withCheckedContinuation { continuation in
                firstRequestRelease = continuation
            }
        }
        return response
    }

    func appAccountToken() async throws -> UUID { .testAccount }

    func acknowledge(_ transaction: SignedStoreTransaction) async throws -> Bool {
        true
    }

    func reconcile(originalTransactionID: String) async throws {}

    func waitUntilFirstRequestIsSuspended() async {
        guard requestCount == 0 else { return }
        await withCheckedContinuation { continuation in
            firstRequestStarted = continuation
        }
    }

    func waitForRequestCount(_ expectedCount: Int) async {
        guard requestCount < expectedCount else { return }
        await withCheckedContinuation { continuation in
            requestCountWaiters.append((expectedCount, continuation))
        }
    }

    func releaseFirstRequest() {
        firstRequestRelease?.resume()
        firstRequestRelease = nil
    }

    private func resumeSatisfiedRequestCountWaiters() {
        let satisfied = requestCountWaiters.filter { $0.0 <= requestCount }
        requestCountWaiters.removeAll { $0.0 <= requestCount }
        satisfied.forEach { $0.1.resume() }
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
    private let unfinished: [SignedStoreTransaction]
    private let events: EventRecorder?
    private var purchases = 0
    nonisolated private let updateBroadcaster =
        StoreKitTransactionUpdateBroadcaster()

    init(
        products: [StoreProduct],
        loadGate: LoadGate? = nil,
        purchaseOutcome: PurchaseOutcome = .userCancelled,
        currentEntitlements: [SignedStoreTransaction] = [],
        unfinishedTransactions: [SignedStoreTransaction] = [],
        events: EventRecorder? = nil
    ) {
        availableProducts = products
        self.loadGate = loadGate
        self.purchaseOutcome = purchaseOutcome
        entitlementTransactions = currentEntitlements
        unfinished = unfinishedTransactions
        self.events = events
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
        updateBroadcaster.stream()
    }

    func sync() async throws {
        await events?.append("sync")
    }

    func currentEntitlements() async -> [SignedStoreTransaction] {
        await events?.append("currentEntitlements")
        return entitlementTransactions
    }

    func unfinishedTransactions() async -> [SignedStoreTransaction] {
        await events?.append("unfinishedTransactions")
        return unfinished
    }

    nonisolated func sendUpdate(_ transaction: SignedStoreTransaction) {
        updateBroadcaster.broadcast(transaction)
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
