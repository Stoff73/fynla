import Foundation
import Testing
@testable import Fynla

@MainActor
@Suite("Fyn conversation model")
struct FynConversationModelTests {
    @Test
    func queuedTurnRetriesBusyStreamAndKeepsReadingAfterDone() async throws {
        let client = ScriptedFynClient(
            transcripts: [try transcript(messages: [])],
            sendOutcomes: [.queued(messageID: "700", queuePosition: 1)],
            queuedOutcomes: [
                .busy,
                .busy,
                .events([
                    .text("Your answer."),
                    .done(messageID: "701"),
                    .levelUp(FynLevelUp(level: 4, levelName: "Navigator", nextActions: [])),
                ]),
            ]
        )
        let clock = RecordingFynClock()
        let model = FynConversationModel(
            client: client,
            clock: clock,
            currentRoute: "/dashboard",
            makeID: { "local-message" }
        )

        await model.open(conversationID: "321")
        await model.send("What should I do next?")

        #expect(await client.sendCount() == 1)
        #expect(await client.queuedCount() == 3)
        #expect(await clock.sleeps() == [.milliseconds(1_500), .milliseconds(1_500)])
        #expect(model.messages.last?.text == "Your answer.")
        #expect(model.messages.last?.delivery == .persisted)
        #expect(model.levelUp?.level == 4)
        #expect(model.phase == .idle)
    }

    @Test
    func uncertainAcceptanceReloadsBeforeRetryAndDoesNotPostTwiceWhenAccepted() async throws {
        let accepted = try transcript(messages: [
            (id: 81, role: "user", content: "Add my Cash ISA"),
            (id: 82, role: "assistant", content: "Your Cash ISA is saved."),
        ])
        let client = ScriptedFynClient(
            transcripts: [try transcript(messages: []), accepted],
            sendOutcomes: [.acceptanceUncertain]
        )
        let model = FynConversationModel(
            client: client,
            currentRoute: "/savings",
            makeID: { "gesture-1" }
        )

        await model.open(conversationID: "321")
        await model.send("Add my Cash ISA")
        #expect(model.phase == .acceptanceUncertain)

        await model.retryLastMessage()

        #expect(await client.sendCount() == 1)
        #expect(model.phase == .idle)
        #expect(model.messages.map(\.text) == ["Add my Cash ISA", "Your Cash ISA is saved."])
    }

    @Test
    func sameDestinationNavigationRequestsCloseAndRefreshInsteadOfPushing() async throws {
        let client = ScriptedFynClient(
            transcripts: [try transcript(messages: [])],
            sendOutcomes: [
                .events([
                    .text("I updated those savings figures."),
                    .navigation(path: "/savings", section: "cash"),
                    .done(messageID: "91"),
                ]),
            ]
        )
        let model = FynConversationModel(
            client: client,
            currentRoute: "/savings",
            makeID: { "gesture-2" }
        )

        await model.open(conversationID: "321")
        await model.send("Use the corrected balance")

        #expect(model.shouldCloseAndRefresh)
        #expect(model.takeNavigation() == nil)
    }

    private func transcript(
        messages: [(id: Int, role: String, content: String)]
    ) throws -> FynTranscript {
        let rows = messages.map {
            """
            {"id":\($0.id),"role":"\($0.role)","content":"\($0.content)","metadata":null,"created_at":"2026-07-18T18:00:00Z"}
            """
        }.joined(separator: ",")
        let data = Data(
            """
            {"conversation":{"id":321,"title":"Fyn","message_count":\(messages.count),"status":"active"},"messages":[\(rows)]}
            """.utf8
        )
        return try JSONDecoder().decode(FynTranscript.self, from: data)
    }
}

private actor RecordingFynClock: FynClock {
    private var recorded: [Duration] = []

    func sleep(for duration: Duration) async throws {
        recorded.append(duration)
    }

    func sleeps() -> [Duration] { recorded }
}

private actor ScriptedFynClient: FynClient {
    enum SendOutcome: Sendable {
        case events([FynEvent])
        case queued(messageID: String, queuePosition: Int?)
        case acceptanceUncertain
    }

    enum QueuedOutcome: Sendable {
        case busy
        case events([FynEvent])
    }

    private var transcripts: [FynTranscript]
    private var sendOutcomes: [SendOutcome]
    private var queuedOutcomes: [QueuedOutcome]
    private var sends = 0
    private var queuedStreams = 0

    init(
        transcripts: [FynTranscript],
        sendOutcomes: [SendOutcome] = [],
        queuedOutcomes: [QueuedOutcome] = []
    ) {
        self.transcripts = transcripts
        self.sendOutcomes = sendOutcomes
        self.queuedOutcomes = queuedOutcomes
    }

    func onboardingStatus() async throws -> FynOnboardingStatus {
        try JSONDecoder().decode(
            FynOnboardingStatus.self,
            from: Data(#"{"in_progress":false}"#.utf8)
        )
    }

    func listConversations() async throws -> [FynConversationListItem] { [] }

    func createConversation(currentRoute: String) async throws -> FynConversationRecord {
        try JSONDecoder().decode(
            FynConversationRecord.self,
            from: Data(#"{"id":321,"title":"Fyn","message_count":0,"status":"active"}"#.utf8)
        )
    }

    func loadConversation(id: String) async throws -> FynTranscript {
        guard !transcripts.isEmpty else { throw URLError(.badServerResponse) }
        return transcripts.removeFirst()
    }

    func startOnboarding(from: String?) async throws -> AsyncThrowingStream<FynEvent, Error> {
        stream([])
    }

    func sendMessage(
        conversationID: String,
        text: String,
        currentRoute: String,
        idempotencyKey: String
    ) async throws -> FynStreamResult {
        sends += 1
        guard !sendOutcomes.isEmpty else { throw URLError(.badServerResponse) }
        switch sendOutcomes.removeFirst() {
        case let .events(events):
            return FynStreamResult.stream(stream(events))
        case let .queued(messageID, position):
            return FynStreamResult.queued(
                messageID: messageID,
                queuePosition: position
            )
        case .acceptanceUncertain:
            throw FynClientError.acceptanceUncertain
        }
    }

    func streamQueuedMessage(
        conversationID: String,
        messageID: String,
        currentRoute: String
    ) async throws -> AsyncThrowingStream<FynEvent, Error> {
        queuedStreams += 1
        guard !queuedOutcomes.isEmpty else { throw URLError(.badServerResponse) }
        switch queuedOutcomes.removeFirst() {
        case .busy: throw FynClientError.busy
        case let .events(events): return stream(events)
        }
    }

    func performAction(
        conversationID: String,
        action: String
    ) async throws -> AsyncThrowingStream<FynEvent, Error> {
        stream([])
    }

    func sendCount() -> Int { sends }
    func queuedCount() -> Int { queuedStreams }

    private nonisolated func stream(
        _ events: [FynEvent]
    ) -> AsyncThrowingStream<FynEvent, Error> {
        AsyncThrowingStream { continuation in
            events.forEach { continuation.yield($0) }
            continuation.finish()
        }
    }
}
