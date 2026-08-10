import Foundation
import Testing
@testable import Fynla

@MainActor
@Suite("Financial presentation primitives")
struct FinancialPresentationTests {
    @Test
    func formatsUKMoneyWithoutConflatingZeroAndUnavailable() {
        #expect(MoneyFormatter.gbp(Decimal.zero) == "£0.00")
        #expect(MoneyFormatter.gbp(Decimal(string: "1234567.89")!) == "£1,234,567.89")
        #expect(MoneyFormatter.gbp(Decimal(string: "-42.5")!) == "-£42.50")

        #expect(FinancialValueView.money(nil).displayText == "Unavailable")
        #expect(FinancialValueView.money(.zero).displayText == "£0.00")
    }

    @Test
    func formatsServerSuppliedDatesAndPercentagesForTheUK() throws {
        let date = try #require(
            ISO8601DateFormatter().date(from: "2026-07-18T12:00:00Z")
        )

        #expect(MoneyFormatter.ukDate(date) == "18 Jul 2026")
        #expect(MoneyFormatter.percentage(Decimal(string: "12.5")!) == "12.5%")
    }

    @Test
    func contextualOverviewActionsAlwaysAddWithoutClientAuthoredFacts() {
        let actions = [
            FynContextualActions.savingsOverview(hasAccounts: true),
            FynContextualActions.investmentOverview(hasAccounts: true),
            FynContextualActions.retirementOverview(hasPensions: true),
            FynContextualActions.protectionOverview(hasPolicies: true),
            FynContextualActions.goalsOverview(hasGoals: true),
        ]

        #expect(actions.allSatisfy { $0.request.action == .add })
        #expect(actions.map(\.request.resourceType) == [
            "savings",
            "investment",
            "retirement",
            "protection",
            "goals",
        ])
        #expect(actions.allSatisfy { $0.request.resourceID == nil })
        #expect(actions.allSatisfy { $0.request.currentDestination.params.isEmpty })
    }

    @Test
    func contextualRequestEncodingContainsIdentifiersButNoFinancialFactsOrLabels() throws {
        let action = FynContextualAction(
            action: .edit,
            resourceType: "savings_account",
            resourceID: 42,
            currentDestination: SemanticDestination(
                screen: "savings_account_detail",
                params: ["account_id": .int(42)],
                fallback: "savings"
            ),
            origin: FynContextualOrigin(kind: .surfaceAction)
        )

        let encoded = try JSONEncoder().encode(action.request)
        let object = try #require(JSONSerialization.jsonObject(with: encoded) as? [String: Any])
        let text = try #require(String(data: encoded, encoding: .utf8))

        #expect(object["action"] as? String == "edit")
        #expect(object["resource_type"] as? String == "savings_account")
        #expect(object["resource_id"] as? Int == 42)
        #expect(text.contains("account_id"))
        #expect(!text.localizedCaseInsensitiveContains("balance"))
        #expect(!text.localizedCaseInsensitiveContains("value"))
        #expect(!text.localizedCaseInsensitiveContains("name"))
        #expect(!text.localizedCaseInsensitiveContains("prompt"))
    }

    @Test
    func conversationHistoryDecodingIsAdditiveAndBackwardCompatible() throws {
        let enriched = try JSONDecoder().decode(
            FynConversationListItem.self,
            from: Data(
                #"{"id":42,"title":"Edit account","message_count":2,"mode":"contextual","purpose":"Edit Bank Account","related_entity":{"type":"savings_account","id":7,"label":"Rainy Day","available":true,"explanation":null},"status":"active","created_at":"2026-08-10T08:00:00Z","updated_at":"2026-08-10T09:00:00Z","last_message_at":"2026-08-10T09:00:00Z","last_message_summary":"Tell me what changed.","fallback_destination":{"screen":"savings","params":{},"fallback":"dashboard"}}"#.utf8
            )
        )
        let legacy = try JSONDecoder().decode(
            FynConversationListItem.self,
            from: Data(#"{"id":41,"title":"Legacy"}"#.utf8)
        )

        #expect(enriched.mode == "contextual")
        #expect(enriched.relatedEntity?.label == "Rainy Day")
        #expect(enriched.fallbackDestination?.screen == "savings")
        #expect(legacy.mode == nil)
        #expect(legacy.relatedEntity == nil)
    }

    @Test
    func jointOwnedEntitiesDecodeAsReadOnlyForContextualEditSurfaces() throws {
        let investment = try JSONDecoder().decode(
            InvestmentAccount.self,
            from: Data(
                #"{"id":42,"current_value":"1000.00","holdings":[],"is_primary_owner":false}"#.utf8
            )
        )
        let goal = try JSONDecoder().decode(
            FinancialGoal.self,
            from: Data(
                #"{"id":43,"target_amount":"5000.00","current_amount":"1000.00","progress_percentage":"20.00","is_on_track":true,"is_primary_owner":false}"#.utf8
            )
        )

        #expect(investment.isPrimaryOwner == false)
        #expect(goal.isPrimaryOwner == false)
    }

    @Test
    func screenStateKeepsRetryAndUpgradeActionsExplicit() {
        #expect(ScreenStatePresentation.loading.canRetry == false)
        #expect(ScreenStatePresentation.offline.canRetry)
        #expect(ScreenStatePresentation.failed(requestID: "request-42").canRetry)
        #expect(ScreenStatePresentation.upgradeRequired(message: "Premium required").canUpgrade)
        #expect(ScreenStatePresentation.unauthenticated.canRetry == false)
    }
}
