import Foundation
import Testing

@Suite("StoreKit configuration contract")
struct StoreKitConfigurationTests {
    @Test
    func configurationDisablesOffersAndFamilySharing() throws {
        let configurationURL = try #require(
            Bundle(for: StoreKitConfigurationBundleToken.self).url(
                forResource: "Fynla",
                withExtension: "storekit"
            )
        )
        let configuration = try JSONDecoder().decode(
            StoreKitTestConfiguration.self,
            from: Data(contentsOf: configurationURL)
        )
        let group = try #require(configuration.subscriptionGroups.only)

        #expect(group.subscriptions.count == 2)
        #expect(group.subscriptions.allSatisfy { !$0.familyShareable })
        #expect(group.subscriptions.allSatisfy { $0.introductoryOffer == nil })
        #expect(group.subscriptions.allSatisfy { $0.adHocOffers.isEmpty })
        #expect(group.subscriptions.allSatisfy { $0.codeOffers.isEmpty })
    }
}

private final class StoreKitConfigurationBundleToken {}

private struct StoreKitTestConfiguration: Decodable {
    let subscriptionGroups: [SubscriptionGroup]

    struct SubscriptionGroup: Decodable {
        let subscriptions: [Subscription]
    }

    struct Subscription: Decodable {
        let adHocOffers: [Offer]
        let codeOffers: [Offer]
        let familyShareable: Bool
        let introductoryOffer: Offer?
    }

    struct Offer: Decodable {}
}

private extension Collection {
    var only: Element? {
        count == 1 ? first : nil
    }
}
