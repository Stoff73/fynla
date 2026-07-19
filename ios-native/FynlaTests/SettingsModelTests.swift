import Foundation
import Testing
@testable import Fynla

@Suite("Settings presentation")
struct SettingsModelTests {
    @Test @MainActor
    func presentsAuthenticatedAccountAndCanonicalFreePlan() {
        let model = SettingsModel(
            userProvider: {
                AuthenticatedUser(
                    id: 73,
                    firstName: "Casey",
                    surname: "Jones",
                    name: nil,
                    email: "casey@example.test"
                )
            },
            privacyLockController: nil,
            webBaseURL: URL(string: "https://csjones.co/fynla")!
        )

        model.refresh(subscription: .free(
            products: [],
            selectedProductID: StoreProductIdentifier.monthly,
            isPending: false
        ))

        #expect(model.account?.name == "Casey Jones")
        #expect(model.account?.email == "casey@example.test")
        #expect(model.plan == .free)
        #expect(model.privacyURL.absoluteString == "https://csjones.co/fynla/privacy")
        #expect(model.termsURL.absoluteString == "https://csjones.co/fynla/terms")
        #expect(model.supportURL.absoluteString == "https://csjones.co/fynla/contact")
    }

    @Test @MainActor
    func preservesAppleAndWebBillingManagementDistinction() {
        let model = SettingsModel(
            userProvider: { Self.user },
            privacyLockController: nil,
            webBaseURL: URL(string: "https://fynla.org")!
        )

        model.refresh(subscription: .applePremium(Self.entitlement(.apple)))
        #expect(model.plan == .applePremium)

        model.refresh(subscription: .webPremium(Self.entitlement(.web)))
        #expect(model.plan == .webPremium)
    }

    @Test @MainActor
    func unavailableAndSignedOutStatesDoNotInventAccountOrPlanData() {
        let model = SettingsModel(
            userProvider: { nil },
            privacyLockController: nil,
            webBaseURL: URL(string: "https://fynla.org")!
        )

        model.refresh(subscription: .unavailable(message: "Offline"))

        #expect(model.account == nil)
        #expect(model.plan == .unavailable)
        #expect(!model.faceIDEnabled)
    }

    private static let user = AuthenticatedUser(
        id: 74,
        firstName: nil,
        surname: nil,
        name: "Alex Example",
        email: "alex@example.test"
    )

    private static func entitlement(
        _ billing: NativeBillingManagement
    ) -> NativeEntitlement {
        NativeEntitlement(
            tier: .premium,
            provider: billing == .apple ? "apple" : "stripe",
            status: "active",
            renews: true,
            currentPeriodEnd: "2026-08-18T12:00:00Z",
            capabilities: [:],
            limits: [:],
            billingManagement: billing
        )
    }
}
