import Foundation
import Testing
@testable import Fynla

@MainActor
@Suite("Native Conversation History")
struct ConversationHistoryTests {
    @Test
    func groupsServerProjectedModesWithoutReconstructingContext() async throws {
        let conversations = try decodeHistory()
        let model = ConversationHistoryModel(loadConversations: { conversations })

        await model.load()

        #expect(model.state == .loaded)
        #expect(model.sections.map(\.mode) == [.onboarding, .contextual, .general])
        #expect(model.sections.map { $0.items.map(\.id) } == [["11"], ["22", "23"], ["33"]])
        #expect(model.sections[1].items[0].relatedEntity?.label == "Everyday Saver")
        #expect(model.sections[1].items[0].lastMessageSummary == "Tell me what has changed.")
    }

    @Test
    func unavailableEntityUsesTheServerExplanationAndSemanticFallback() async throws {
        let conversations = try decodeHistory()
        let model = ConversationHistoryModel(loadConversations: { conversations })
        await model.load()

        let unavailable = try #require(model.sections[1].items.last)

        #expect(unavailable.relatedEntity?.available == false)
        #expect(unavailable.relatedEntity?.explanation == "This account is no longer available.")
        #expect(model.canOpen(unavailable) == false)
        #expect(model.fallbackRoute(for: unavailable) == .savings(accountID: nil))
    }

    @Test
    func failuresExposeRetryableStateWithoutStaleRows() async {
        let model = ConversationHistoryModel(loadConversations: {
            throw APIError.server(status: 503, requestID: "req-history")
        })

        await model.load()

        #expect(model.state == .failed(requestID: "req-history"))
        #expect(model.sections.allSatisfy { $0.items.isEmpty })
    }

    @Test
    func contextualActionsEncodeOnlyIdentifiersForOverviewAndDetails() throws {
        let overview = FynContextualActions.savingsOverview(hasAccounts: true)
        let detail = FynContextualActions.savingsAccount(id: 8472)
        let pension = FynContextualActions.pension(type: "dc", id: 912)
        let policy = FynContextualActions.protectionPolicy(type: "life", id: 77)
        let goal = FynContextualActions.goal(id: 54)

        #expect(overview.request.action == .edit)
        #expect(overview.request.resourceType == "savings")
        #expect(overview.request.resourceID == nil)
        #expect(detail.request.resourceType == "savings_account")
        #expect(detail.request.resourceID == 8472)
        #expect(detail.request.currentDestination.params == ["account_id": .int(8472)])
        #expect(pension.request.resourceType == "dc_pension")
        #expect(pension.request.currentDestination.params["pension_type"] == .string("dc"))
        #expect(policy.request.resourceType == "life_insurance_policy")
        #expect(policy.request.currentDestination.params["policy_id"] == .int(77))
        #expect(goal.request.resourceType == "goal")
        #expect(goal.request.currentDestination.params == ["goal_id": .int(54)])
        #expect(goal.request.currentDestination.fallback == "goals")

        let encoded = String(
            decoding: try JSONEncoder().encode(detail.request),
            as: UTF8.self
        ).lowercased()
        for forbidden in ["balance", "value", "name", "label", "prompt", "provider"] {
            #expect(!encoded.contains(forbidden))
        }
    }

    private func decodeHistory() throws -> [FynConversationListItem] {
        try JSONDecoder().decode(
            [FynConversationListItem].self,
            from: Data(
                #"""
                [
                  {"id":11,"title":"Getting started","message_count":2,"mode":"onboarding","purpose":"Set up your plan","related_entity":null,"status":"active","created_at":"2026-08-09T08:00:00Z","updated_at":"2026-08-09T08:05:00Z","last_message_at":"2026-08-09T08:05:00Z","last_message_summary":"Choose a path.","fallback_destination":{"screen":"dashboard","params":{},"fallback":"dashboard"}},
                  {"id":22,"title":"Edit Bank Account","message_count":3,"mode":"contextual","purpose":"Edit Bank Account","related_entity":{"type":"savings_account","id":8472,"label":"Everyday Saver","available":true,"explanation":null},"status":"active","created_at":"2026-08-09T09:00:00Z","updated_at":"2026-08-09T09:05:00Z","last_message_at":"2026-08-09T09:05:00Z","last_message_summary":"Tell me what has changed.","fallback_destination":{"screen":"savings","params":{},"fallback":"dashboard"}},
                  {"id":23,"title":"Edit Bank Account","message_count":4,"mode":"contextual","purpose":"Edit Bank Account","related_entity":{"type":"savings_account","id":999,"label":null,"available":false,"explanation":"This account is no longer available."},"status":"paused","created_at":"2026-08-09T09:10:00Z","updated_at":"2026-08-09T09:15:00Z","last_message_at":"2026-08-09T09:15:00Z","last_message_summary":"Previous conversation.","fallback_destination":{"screen":"savings","params":{},"fallback":"dashboard"}},
                  {"id":33,"title":"Fyn","message_count":1,"mode":"general","purpose":"General Fyn conversation","related_entity":null,"status":"active","created_at":"2026-08-09T10:00:00Z","updated_at":"2026-08-09T10:00:00Z","last_message_at":"2026-08-09T10:00:00Z","last_message_summary":"How can I help?","fallback_destination":{"screen":"dashboard","params":{},"fallback":"dashboard"}}
                ]
                """#.utf8
            )
        )
    }
}
