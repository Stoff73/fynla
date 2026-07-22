#if FYNLA_UI_TESTING
import Foundation

enum SubscriptionUITestComposition {
    @MainActor
    static func model(for scenario: SubscriptionUITestScenario) -> SubscriptionModel {
        SubscriptionModel(
            api: SubscriptionUITestAPI(scenario: scenario),
            storeKit: SubscriptionUITestStoreKit(scenario: scenario)
        )
    }
}

struct SubscriptionUITestAppleManager: AppleSubscriptionManaging {
    @MainActor
    func showManageSubscriptions() async throws {}
}

private actor SubscriptionUITestAPI: SubscriptionAPI {
    private let scenario: SubscriptionUITestScenario
    private var upgraded = false

    init(scenario: SubscriptionUITestScenario) {
        self.scenario = scenario
    }

    func entitlement() async throws -> NativeEntitlement {
        if upgraded && (scenario == .purchaseSuccess || scenario == .restoreSuccess) {
            return .uiTestApplePremium
        }

        switch scenario {
        case .unavailable:
            throw SubscriptionUITestError.unavailable
        case .applePremium:
            return .uiTestApplePremium
        case .webPremium:
            return .uiTestWebPremium
        case .free,
             .purchaseSuccess,
             .purchasePending,
             .purchaseCancelled,
             .restoreSuccess:
            return .uiTestFree
        }
    }

    func authorizePurchase() async throws -> Bool {
        true
    }

    func appAccountToken() async throws -> UUID {
        .uiTestAccount
    }

    func acknowledge(_ transaction: SignedStoreTransaction) async throws -> Bool {
        if scenario == .purchaseSuccess {
            upgraded = true
        }
        return true
    }

    func reconcile(originalTransactionID: String) async throws {
        guard scenario == .restoreSuccess, originalTransactionID == "40" else {
            throw SubscriptionUITestError.unavailable
        }
        upgraded = true
    }
}

private final class SubscriptionUITestStoreKit: StoreKitClient, @unchecked Sendable {
    private let scenario: SubscriptionUITestScenario

    init(scenario: SubscriptionUITestScenario) {
        self.scenario = scenario
    }

    func products() async throws -> [StoreProduct] {
        [.uiTestMonthly, .uiTestAnnual]
    }

    func purchase(
        _ productID: String,
        appAccountToken: UUID
    ) async throws -> PurchaseOutcome {
        switch scenario {
        case .purchaseSuccess:
            return .verified(.uiTestTransaction)
        case .purchasePending:
            return .pending
        case .purchaseCancelled:
            return .userCancelled
        case .free,
             .applePremium,
             .webPremium,
             .unavailable,
             .restoreSuccess:
            return .userCancelled
        }
    }

    func updates() -> AsyncStream<SignedStoreTransaction> {
        AsyncStream { $0.finish() }
    }

    func sync() async throws {}

    func currentEntitlements() async -> [SignedStoreTransaction] {
        scenario == .restoreSuccess ? [.uiTestTransaction] : []
    }
}

private enum SubscriptionUITestError: Error {
    case unavailable
}

private extension NativeEntitlement {
    static let uiTestFree = Self(
        tier: .free,
        provider: nil,
        status: "free",
        renews: false,
        currentPeriodEnd: nil,
        capabilities: ["dashboard": "full"],
        limits: ["savings_account": 2],
        billingManagement: .none
    )

    static let uiTestApplePremium = Self(
        tier: .premium,
        provider: "apple",
        status: "active",
        renews: true,
        currentPeriodEnd: "2026-08-18T20:00:00Z",
        capabilities: ["dashboard": "full"],
        limits: ["savings_account": nil],
        billingManagement: .apple
    )

    static let uiTestWebPremium = Self(
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
    static let uiTestMonthly = Self(
        id: StoreProductIdentifier.monthly,
        displayName: "Fynla Premium Monthly",
        description: "Fynla Premium",
        displayPrice: "£6.99",
        subscriptionPeriod: .init(value: 1, unit: .month)
    )

    static let uiTestAnnual = Self(
        id: StoreProductIdentifier.annual,
        displayName: "Fynla Premium Annual",
        description: "Fynla Premium",
        displayPrice: "£59.99",
        subscriptionPeriod: .init(value: 1, unit: .year)
    )
}

private extension SignedStoreTransaction {
    static let uiTestTransaction = Self.testing(
        id: 42,
        originalID: 40,
        productID: StoreProductIdentifier.monthly,
        appAccountToken: .uiTestAccount,
        signedJWS: "ui.header.payload"
    )
}

private extension UUID {
    static let uiTestAccount = UUID(
        uuidString: "19659A36-8E55-4F95-98CB-3F5D61F489AA"
    )!
}
#endif
