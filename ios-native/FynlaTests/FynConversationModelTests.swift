import Foundation
import Testing
@testable import Fynla

@MainActor
@Suite("Fyn conversation model")
struct FynConversationModelTests {
    @Test
    func contextualStartAlwaysCreatesFreshAndLoadsPersistedTranscript() async throws {
        let old = try transcript(messages: [
            (id: 1, role: "assistant", content: "Old conversation."),
        ])
        let first = try transcript(id: 501, messages: [
            (id: 2, role: "assistant", content: "First trusted opening."),
        ])
        let second = try transcript(id: 502, messages: [
            (id: 3, role: "assistant", content: "Second trusted opening."),
        ])
        let client = ScriptedFynClient(
            transcripts: [old, first, second],
            contextualConversationIDs: [501, 502]
        )
        let model = FynConversationModel(client: client, currentRoute: "/savings")
        let action = contextualSavingsEdit()

        await model.start(preferredID: "321")
        await model.startContextual(action)
        #expect(model.conversationID == "501")
        #expect(model.messages.map(\.text) == ["First trusted opening."])

        await model.startContextual(action)
        #expect(model.conversationID == "502")
        #expect(model.messages.map(\.text) == ["Second trusted opening."])
        #expect(await client.contextualCreateCount() == 2)
        #expect(await client.contextualRequestsRecorded() == [action.request, action.request])
    }

    @Test
    func contextualFailureClearsStaleStateAndCanRetryWithoutLosingRouteContext() async throws {
        let old = try transcript(messages: [
            (id: 1, role: "assistant", content: "Old conversation."),
        ])
        let recovered = try transcript(id: 601, messages: [
            (id: 2, role: "assistant", content: "Recovered trusted opening."),
        ])
        let client = ScriptedFynClient(
            transcripts: [old, recovered],
            contextualConversationIDs: [601],
            createContextualFailure: .unexpectedStatus(503, requestID: "ctx-503")
        )
        let model = FynConversationModel(client: client, currentRoute: "/savings")
        let action = contextualSavingsEdit()

        await model.start(preferredID: "321")
        model.draft = "stale draft"
        await model.startContextual(action)

        #expect(model.conversationID == nil)
        #expect(model.messages.isEmpty)
        #expect(model.draft.isEmpty)
        #expect(model.currentRoute == "/savings")
        #expect(model.phase == .failed("Fyn could not respond. Request ctx-503."))

        await model.startContextual(action)
        #expect(model.conversationID == "601")
        #expect(model.messages.map(\.text) == ["Recovered trusted opening."])
        #expect(model.currentRoute == "/savings")
    }

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

    @Test
    func midFlowOpenFiresResumeOncePerSessionThenLoadsTranscript() async throws {
        // /m parity: session entry fires the director's resume action (fresh
        // pruned welcome-back), dock reopens are transcript-only, and a
        // sign-out (stopAndClear) re-arms it for the next session.
        let greeting = try transcript(messages: [
            (id: 900, role: "assistant", content: "Welcome back, Chris."),
        ])
        let client = ScriptedFynClient(
            transcripts: [greeting, greeting, greeting],
            inProgressConversationID: "63"
        )
        let model = FynConversationModel(
            client: client,
            currentRoute: "/dashboard",
            makeID: { "gesture-3" }
        )

        await model.open()
        #expect(await client.actionsRecorded() == ["resume"])
        #expect(model.messages.map(\.text) == ["Welcome back, Chris."])
        #expect(model.phase == .idle)

        await model.open()
        #expect(await client.actionsRecorded() == ["resume"])

        model.stopAndClear()
        await model.open()
        #expect(await client.actionsRecorded() == ["resume", "resume"])
    }

    // MARK: - F2: `.failed` phase no longer freezes the composer

    @Test
    func authExpiredDuringSendSurfacesADistinctSessionExpiredPhase() async throws {
        // F1/F2: `LiveFynClient` surfaces `FynClientError.authExpired` when
        // its one-shot 401 refresh-and-replay can't recover the session.
        // `FynConversationModel` must map that to `.sessionExpired`, not the
        // generic `.failed`, so the composer can show "session expired".
        let client = ScriptedFynClient(
            transcripts: [try transcript(messages: [])],
            sendOutcomes: [.authExpired]
        )
        let model = FynConversationModel(
            client: client,
            currentRoute: "/dashboard",
            makeID: { "gesture-auth" }
        )

        await model.open(conversationID: "321")
        await model.send("Add my Cash ISA")

        #expect(model.phase == .sessionExpired)
    }

    @Test
    func reopeningAfterAFailedPhaseSelfHeals() async throws {
        // F2(a): `open()` must accept `.failed` as a re-entrant start state
        // — otherwise the dock's `.task { open() }` can never recover once a
        // transient failure lands the model in `.failed`.
        let client = ScriptedFynClient(
            transcripts: [try transcript(messages: [])],
            loadConversationFailure: .unexpectedStatus(500, requestID: "req-500")
        )
        let model = FynConversationModel(
            client: client,
            currentRoute: "/dashboard",
            makeID: { "gesture-reopen" }
        )

        await model.open(conversationID: "321")
        #expect(model.phase == .failed("Fyn could not respond. Request req-500."))

        await model.open(conversationID: "321")
        #expect(model.phase == .idle)
        #expect(model.messages.isEmpty)
    }

    @Test
    func sendingFromAFailedPhaseFoldsBackToIdleInsteadOfNoOpingForever() async throws {
        // F2(b): the smallest coherent fix — `send()` folds a stuck
        // `.failed` back to `.idle` right before the send attempt, so the
        // tap that looked dead is the one that recovers.
        let client = ScriptedFynClient(
            transcripts: [try transcript(messages: [])],
            sendOutcomes: [.events([.text("Saved."), .done(messageID: "900")])],
            loadConversationFailure: .unexpectedStatus(500, requestID: "req-500")
        )
        let model = FynConversationModel(
            client: client,
            currentRoute: "/dashboard",
            makeID: { "gesture-fold" }
        )

        await model.open(conversationID: "321")
        #expect(model.phase == .failed("Fyn could not respond. Request req-500."))

        await model.send("Try again")

        #expect(await client.sendCount() == 1)
        #expect(model.phase == .idle)
        #expect(model.messages.last?.text == "Saved.")
    }

    // MARK: - F3: transcript bubble parity with /m's drop-last rule

    @Test
    func transcriptBubbleStrippingMatchesMobileWebsDropLastRule() async throws {
        // F3 audit: /m's `loadTranscript` (resources/mobile/mixins/
        // onboardingChat.js) does
        // `mapped.forEach((m, i) => { if (i < mapped.length - 1) m.bubbles = []; })`
        // — bubbles survive only on the LAST transcript row, regardless of
        // role. These four scenarios are the audit's own cases; all four
        // confirm native already matches /m byte-for-byte, including the
        // "offer answered by a user reply" case where NEITHER build leaves
        // anything tappable, because the offer (not last) gets stripped and
        // the user's reply never carried bubbles to begin with.
        let cases: [(label: String, transcript: FynTranscript, expectedReplyCounts: [Int])] = [
            (
                "resume greeting last",
                try bubbleTranscript(messages: [
                    (id: 1, role: "user", content: "Hi", bubbleIDs: []),
                    (
                        id: 2,
                        role: "assistant",
                        content: "Welcome back, Chris.",
                        bubbleIDs: ["continue", "something_else"]
                    ),
                ]),
                [0, 2]
            ),
            (
                "offer last",
                try bubbleTranscript(messages: [
                    (id: 3, role: "assistant", content: "Here's what I found.", bubbleIDs: []),
                    (
                        id: 4,
                        role: "assistant",
                        content: "Save it now or later?",
                        bubbleIDs: ["store_now", "store_later"]
                    ),
                ]),
                [0, 2]
            ),
            (
                "offer followed by user reply",
                try bubbleTranscript(messages: [
                    (
                        id: 5,
                        role: "assistant",
                        content: "Save it now or later?",
                        bubbleIDs: ["store_now", "store_later"]
                    ),
                    (id: 6, role: "user", content: "Yes, save it now", bubbleIDs: []),
                ]),
                [0, 0]
            ),
            (
                "deferred raise followed by celebration text",
                try bubbleTranscript(messages: [
                    (
                        id: 7,
                        role: "assistant",
                        content: "Increase your pension contribution?",
                        bubbleIDs: ["deferred_0"]
                    ),
                    (id: 8, role: "assistant", content: "Nice! Level up!", bubbleIDs: []),
                ]),
                [0, 0]
            ),
        ]

        for testCase in cases {
            let client = ScriptedFynClient(transcripts: [testCase.transcript])
            let model = FynConversationModel(
                client: client,
                currentRoute: "/dashboard",
                makeID: { "gesture-f3" }
            )

            await model.open(conversationID: "321")

            #expect(
                model.messages.map(\.replies.count) == testCase.expectedReplyCounts,
                "\(testCase.label) diverged from /m's drop-last bubble rule"
            )
        }
    }

    private func contextualSavingsEdit() -> FynContextualAction {
        FynContextualAction(
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
    }

    private func transcript(
        id: Int = 321,
        messages: [(id: Int, role: String, content: String)]
    ) throws -> FynTranscript {
        let rows = messages.map {
            """
            {"id":\($0.id),"role":"\($0.role)","content":"\($0.content)","metadata":null,"created_at":"2026-07-18T18:00:00Z"}
            """
        }.joined(separator: ",")
        let data = Data(
            """
            {"conversation":{"id":\(id),"title":"Fyn","message_count":\(messages.count),"status":"active"},"messages":[\(rows)]}
            """.utf8
        )
        return try JSONDecoder().decode(FynTranscript.self, from: data)
    }

    private func bubbleTranscript(
        messages: [(id: Int, role: String, content: String, bubbleIDs: [String])]
    ) throws -> FynTranscript {
        let rows = messages.map { message -> String in
            let metadata: String
            if message.bubbleIDs.isEmpty {
                metadata = "null"
            } else {
                let bubbles = message.bubbleIDs
                    .map { #"{"id":"\#($0)","label":"\#($0)"}"# }
                    .joined(separator: ",")
                metadata = #"{"bubbles":[\#(bubbles)]}"#
            }
            return """
            {"id":\(message.id),"role":"\(message.role)","content":"\(message.content)","metadata":\(metadata),"created_at":"2026-07-18T18:00:00Z"}
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
        /// F1/F2: simulates `LiveFynClient`'s 401 refresh-and-replay giving
        /// up — the model must land in `.sessionExpired`, not `.failed`.
        case authExpired
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
    private let inProgressConversationID: String?
    private var actions: [String] = []
    private var contextualConversationIDs: [Int]
    private var contextualRequests: [FynContextualConversationRequest] = []
    private var createContextualFailure: FynClientError?
    /// F2: thrown once by the next `loadConversation` call, then cleared —
    /// lets a test drive a single transient failure through `open()`
    /// without needing a dedicated scripted-client failure mode per case.
    private var loadConversationFailure: FynClientError?

    init(
        transcripts: [FynTranscript],
        sendOutcomes: [SendOutcome] = [],
        queuedOutcomes: [QueuedOutcome] = [],
        inProgressConversationID: String? = nil,
        loadConversationFailure: FynClientError? = nil,
        contextualConversationIDs: [Int] = [],
        createContextualFailure: FynClientError? = nil
    ) {
        self.transcripts = transcripts
        self.sendOutcomes = sendOutcomes
        self.queuedOutcomes = queuedOutcomes
        self.inProgressConversationID = inProgressConversationID
        self.loadConversationFailure = loadConversationFailure
        self.contextualConversationIDs = contextualConversationIDs
        self.createContextualFailure = createContextualFailure
    }

    func onboardingStatus() async throws -> FynOnboardingStatus {
        let json = inProgressConversationID.map {
            #"{"in_progress":true,"current_step":"path_choice","conversation_id":\#($0)}"#
        } ?? #"{"in_progress":false}"#
        return try JSONDecoder().decode(
            FynOnboardingStatus.self,
            from: Data(json.utf8)
        )
    }

    func listConversations() async throws -> [FynConversationListItem] { [] }

    func createConversation(currentRoute: String) async throws -> FynConversationRecord {
        try JSONDecoder().decode(
            FynConversationRecord.self,
            from: Data(#"{"id":321,"title":"Fyn","message_count":0,"status":"active"}"#.utf8)
        )
    }

    func createContextualConversation(
        _ request: FynContextualConversationRequest
    ) async throws -> FynContextualConversationResponse {
        contextualRequests.append(request)
        if let failure = createContextualFailure {
            createContextualFailure = nil
            throw failure
        }
        guard !contextualConversationIDs.isEmpty else {
            throw URLError(.badServerResponse)
        }
        let id = contextualConversationIDs.removeFirst()
        return try JSONDecoder().decode(
            FynContextualConversationResponse.self,
            from: Data(
                """
                {"conversation":{"id":\(id),"title":"Edit Bank Account","message_count":1,"status":"active"},"opening_message":{"id":\(id + 1000),"role":"assistant","content":"Trusted opening.","metadata":null,"created_at":"2026-08-10T09:00:00Z"}}
                """.utf8
            )
        )
    }

    func loadConversation(id: String) async throws -> FynTranscript {
        if let failure = loadConversationFailure {
            loadConversationFailure = nil
            throw failure
        }
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
        case .authExpired:
            throw FynClientError.authExpired
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
        actions.append(action)
        return stream([])
    }

    func sendCount() -> Int { sends }
    func queuedCount() -> Int { queuedStreams }
    func actionsRecorded() -> [String] { actions }
    func contextualCreateCount() -> Int { contextualRequests.count }
    func contextualRequestsRecorded() -> [FynContextualConversationRequest] { contextualRequests }

    private nonisolated func stream(
        _ events: [FynEvent]
    ) -> AsyncThrowingStream<FynEvent, Error> {
        AsyncThrowingStream { continuation in
            events.forEach { continuation.yield($0) }
            continuation.finish()
        }
    }
}
