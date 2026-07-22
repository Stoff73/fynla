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
}

private struct FynUITestClient: FynClient {
    func onboardingStatus() async throws -> FynOnboardingStatus {
        try decode(#"{"in_progress":true,"current_step":"path_choice","conversation_id":321}"#)
    }

    func listConversations() async throws -> [FynConversationListItem] { [] }

    func createConversation(currentRoute: String) async throws -> FynConversationRecord {
        try decode(#"{"id":321,"title":"Onboarding","message_count":1,"status":"active"}"#)
    }

    func loadConversation(id: String) async throws -> FynTranscript {
        try decode(
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
