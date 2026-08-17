import Foundation
import Testing
@testable import Fynla

@Suite("Settings presentation")
struct SettingsModelTests {
    @Test @MainActor
    func exposesTheAuthenticatedAdminBeforeTheSettingsScreenRefreshes() {
        let model = SettingsModel(
            userProvider: {
                AuthenticatedUser(
                    id: 72,
                    firstName: "Admin",
                    surname: "User",
                    name: nil,
                    email: "admin@example.test",
                    isAdmin: true
                )
            },
            privacyLockController: nil,
            webBaseURL: URL(string: "https://fynla.org")!
        )

        #expect(model.account?.isAdmin == true)
        #expect(model.account?.name == "Admin User")
    }

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
        #expect(model.supportURL.absoluteString == "https://csjones.co/fynla/help")
        #expect(SettingsModel.isTrustedPublicURL(
            model.supportURL,
            relativeTo: URL(string: "https://csjones.co/fynla")!
        ))
        #expect(!SettingsModel.isTrustedPublicURL(
            URL(string: "https://attacker.example/help")!,
            relativeTo: URL(string: "https://csjones.co/fynla")!
        ))
        #expect(!SettingsModel.isTrustedPublicURL(
            URL(string: "http://csjones.co/fynla/help")!,
            relativeTo: URL(string: "https://csjones.co/fynla")!
        ))
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

    @Test @MainActor
    func signOutRunsDeviceCleanupBeforeClearingTheLocalSession() async {
        let cleanup = SettingsCleanupRecorder()
        let model = SettingsModel(
            userProvider: { Self.user },
            privacyLockController: nil,
            webBaseURL: URL(string: "https://fynla.org")!,
            beforeSignOut: { await cleanup.record() }
        )

        await model.signOut()

        #expect(await cleanup.count() == 1)
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

private actor SettingsCleanupRecorder {
    private var value = 0
    func record() { value += 1 }
    func count() -> Int { value }
}
