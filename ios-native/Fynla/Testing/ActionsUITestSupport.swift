#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum ActionsUITestComposition {
    static func model() -> ActionsModel {
        ActionsModel(client: ActionsUITestClient())
    }
}

// Offline card for the dashboard fixture's `retirement-1` recommendation, so
// journeys that tap it open the card and continue through "Go to it".
private struct ActionsUITestClient: ActionsClient {
    func list() async throws -> ActionsList {
        ActionsList(open: [], completed: [])
    }

    func card(id: String) async throws -> ActionCard {
        try JSONDecoder().decode(
            ActionCardEnvelope.self,
            from: Data(Self.fixture.replacingOccurrences(of: "__ID__", with: id).utf8)
        ).data
    }

    func markDone(_ card: ActionCard) async throws {}

    func saveFunding(_ card: ActionCard, account: ActionCard.FundingAccount) async throws {}

    private static let fixture = #"""
    {
      "data":{
        "id":"__ID__",
        "type":"recommendation",
        "module":"retirement",
        "module_label":"Retirement",
        "topic":null,
        "deadline":null,
        "title":"Review whether increasing your workplace pension contributions could improve your retirement outcome",
        "description":"This explanation must remain readable across multiple lines on a narrow mobile screen.",
        "why":[],
        "what_this_changes":[],
        "key_figure":null,
        "how_to":[],
        "conflict_note":null,
        "disclaimer":null,
        "ask_fyn":{"kind":"prompt","prompt":"Tell me more about my workplace pension contributions"},
        "primary":{"kind":"mark_done","recommendation_id":"__ID__"},
        "go_to":{"destination":{"screen":"retirement","params":{},"fallback":"dashboard"},"payload":"/retirement"},
        "funding":null,
        "done":false,
        "completed_at":null
      }
    }
    """#
}
#endif
