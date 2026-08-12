#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum FynUITestComposition {
    static func model() -> FynConversationModel {
        FynConversationModel(
            client: FynUITestClient(),
            currentRoute: "/dashboard",
            makeID: { "ui-test-message" }
        )
    }

    static func historyModel() -> ConversationHistoryModel {
        ConversationHistoryModel {
            try JSONDecoder().decode(
                [FynConversationListItem].self,
                from: Data(
                    #"""
                    [
                      {"id":321,"title":"Getting started","message_count":1,"mode":"onboarding","purpose":"Set up your plan","related_entity":null,"status":"active","created_at":"2026-08-09T08:00:00Z","updated_at":"2026-08-09T08:00:00Z","last_message_at":"2026-08-09T08:00:00Z","last_message_summary":"What would you like to focus on first?","fallback_destination":{"screen":"dashboard","params":{},"fallback":"dashboard"}},
                      {"id":401,"title":"Edit Bank Account","message_count":1,"mode":"contextual","purpose":"Edit Bank Account","related_entity":{"type":"savings_account","id":101,"label":"Everyday Saver","available":true,"explanation":null},"status":"active","created_at":"2026-08-09T09:00:00Z","updated_at":"2026-08-09T09:00:00Z","last_message_at":"2026-08-09T09:00:00Z","last_message_summary":"Tell me what has changed.","fallback_destination":{"screen":"savings","params":{},"fallback":"dashboard"}},
                      {"id":499,"title":"Edit Bank Account","message_count":1,"mode":"contextual","purpose":"Edit Bank Account","related_entity":{"type":"savings_account","id":999,"label":null,"available":false,"explanation":"This account is no longer available."},"status":"paused","created_at":"2026-08-09T10:00:00Z","updated_at":"2026-08-09T10:00:00Z","last_message_at":"2026-08-09T10:00:00Z","last_message_summary":"Previous conversation.","fallback_destination":{"screen":"savings","params":{},"fallback":"dashboard"}}
                    ]
                    """#.utf8
                )
            )
        }
    }
}

private actor FynUITestConversationState {
    private var nextContextualID = 401

    func takeContextualID() -> Int {
        defer { nextContextualID += 1 }
        return nextContextualID
    }
}

private struct FynUITestClient: FynClient {
    private let state = FynUITestConversationState()

    func onboardingStatus() async throws -> FynOnboardingStatus {
        try decode(#"{"in_progress":true,"current_step":"path_choice","conversation_id":321}"#)
    }

    func listConversations() async throws -> [FynConversationListItem] { [] }

    func createConversation(currentRoute: String) async throws -> FynConversationRecord {
        try decode(#"{"id":321,"title":"Onboarding","message_count":1,"status":"active"}"#)
    }

    func createContextualConversation(
        _ request: FynContextualConversationRequest
    ) async throws -> FynContextualConversationResponse {
        let id = await state.takeContextualID()
        return try decode(
            """
            {"conversation":{"id":\(id),"title":"Edit Bank Account","message_count":1,"status":"active"},"opening_message":{"id":\(id + 1000),"role":"assistant","content":"Trusted contextual opening \(id).","metadata":null,"created_at":"2026-08-10T09:00:00Z"}}
            """
        )
    }

    func loadConversation(id: String) async throws -> FynTranscript {
        if id != "321" {
            return try decode(
                """
                {"conversation":{"id":\(id),"title":"Edit Bank Account","message_count":1,"status":"active"},"messages":[{"id":\(id)1,"role":"assistant","content":"Trusted contextual opening \(id).","metadata":null,"created_at":"2026-08-10T09:00:00Z"}]}
                """
            )
        }
        return try decode(
            #"{"conversation":{"id":321,"title":"Onboarding","message_count":1,"status":"active"},"messages":[{"id":901,"role":"assistant","content":"What would you like to focus on first?","metadata":{"bubbles":[{"id":"savings","label":"Savings"},{"id":"retirement","label":"Retirement"}],"action_bubbles":false},"created_at":"2026-07-18T18:00:00Z"}]}"#
        )
    }

    func startOnboarding(from: String?) async throws -> AsyncThrowingStream<FynEvent, Error> {
        stream([
            .conversationCreated("321"),
            .quickReplies(
                prompt: "What would you like to focus on first?",
                replies: [
                    FynReply(id: "savings", label: "Savings", route: nil, isAction: false),
                    FynReply(id: "retirement", label: "Retirement", route: nil, isAction: false),
                ],
                actionReplies: false
            ),
            .done(messageID: "901"),
        ])
    }

    func sendMessage(
        conversationID: String,
        text: String,
        currentRoute: String,
        idempotencyKey: String
    ) async throws -> FynStreamResult {
        .stream(stream([
            .text("Let's work through \(text.lowercased())."),
            .done(messageID: "902"),
        ]))
    }

    func streamQueuedMessage(
        conversationID: String,
        messageID: String,
        currentRoute: String
    ) async throws -> AsyncThrowingStream<FynEvent, Error> {
        stream([])
    }

    func performAction(
        conversationID: String,
        action: String
    ) async throws -> AsyncThrowingStream<FynEvent, Error> {
        stream([.text("Continuing your plan."), .done(messageID: "903")])
    }

    private func decode<Value: Decodable>(_ json: String) throws -> Value {
        try JSONDecoder().decode(Value.self, from: Data(json.utf8))
    }

    private func stream(
        _ events: [FynEvent]
    ) -> AsyncThrowingStream<FynEvent, Error> {
        AsyncThrowingStream { continuation in
            events.forEach { continuation.yield($0) }
            continuation.finish()
        }
    }
}
#endif
