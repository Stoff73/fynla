import Foundation
import Observation

protocol FynClock: Sendable {
    func sleep(for duration: Duration) async throws
}

struct ContinuousFynClock: FynClock {
    func sleep(for duration: Duration) async throws {
        try await Task.sleep(for: duration)
    }
}

enum FynConversationPhase: Sendable, Equatable {
    case idle
    case loading
    case accepting
    case streaming
    case queued(position: Int?)
    case stillAnswering
    case rateLimited(retryAfter: Duration?)
    case offline
    case acceptanceUncertain
    case consentRequired
    case tokenLimited(String)
    case failed(String)

    var isBusy: Bool {
        switch self {
        case .loading, .accepting, .streaming, .queued:
            true
        default:
            false
        }
    }
}

@MainActor
@Observable
final class FynConversationModel {
    private let client: any FynClient
    private let clock: any FynClock
    private let makeID: @Sendable () -> String
    private var reducer: FynEventReducer
    private var reduction = FynReductionState()
    private var retryText: String?
    private var retryID: String?
    @ObservationIgnored private var streamTask: Task<Void, Error>?

    var draft = ""
    var currentRoute: String
    private(set) var phase: FynConversationPhase = .idle
    private(set) var shouldCloseAndRefresh = false

    init(
        client: any FynClient,
        clock: any FynClock = ContinuousFynClock(),
        currentRoute: String = "/dashboard",
        makeID: @escaping @Sendable () -> String = { UUID().uuidString }
    ) {
        self.client = client
        self.clock = clock
        self.currentRoute = currentRoute
        self.makeID = makeID
        reducer = FynEventReducer(makeID: makeID)
    }

    var conversationID: String? { reduction.conversationID }
    var messages: [FynMessage] { reduction.messages }
    var levelUp: FynLevelUp? { reduction.levelUp }
    var onboardingComplete: Bool { reduction.onboardingComplete }
    var unknownEventNames: [String] { reduction.unknownEventNames }

    func open(conversationID preferredID: String? = nil) async {
        guard phase == .idle else { return }
        phase = .loading
        shouldCloseAndRefresh = false

        do {
            if let preferredID {
                try await load(preferredID)
                phase = .idle
                return
            }

            let status = try await client.onboardingStatus()
            if status.inProgress, let id = status.conversationID {
                try await load(id)
                phase = .idle
                return
            }

            if let existing = try await client.listConversations().first {
                try await load(existing.id)
                phase = .idle
                return
            }

            do {
                try await consume(try await client.startOnboarding(from: nil))
                if let id = reduction.conversationID, reduction.messages.isEmpty {
                    try await load(id)
                }
            } catch FynClientError.busy {
                let conversation = try await client.createConversation(
                    currentRoute: currentRoute
                )
                reduction.conversationID = conversation.id
            }
            phase = phaseAfterStream()
        } catch is CancellationError {
            phase = .idle
        } catch {
            handle(error)
        }
    }

    func refresh() async {
        guard let id = conversationID, !phase.isBusy else { return }
        phase = .loading
        do {
            try await load(id)
            phase = .idle
        } catch is CancellationError {
            phase = .idle
        } catch {
            handle(error)
        }
    }

    func sendDraft() async {
        let text = draft.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty else { return }
        await send(text)
        switch phase {
        case .offline, .acceptanceUncertain:
            draft = text
        default:
            draft = ""
        }
    }

    func send(_ submittedText: String) async {
        let text = submittedText.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !text.isEmpty, phase == .idle else { return }
        let gestureID = makeID()
        await performSend(text, idempotencyKey: gestureID, appendUser: true)
    }

    func retryLastMessage() async {
        guard phase == .acceptanceUncertain,
              let text = retryText,
              let idempotencyKey = retryID,
              let conversationID
        else {
            return
        }

        phase = .loading
        do {
            let server = try await client.loadConversation(id: conversationID)
            if server.messages.contains(where: {
                $0.role == "user" && $0.content == text
            }) {
                apply(server)
                retryText = nil
                retryID = nil
                phase = .idle
                return
            }
            phase = .idle
            await performSend(
                text,
                idempotencyKey: idempotencyKey,
                appendUser: false
            )
        } catch is CancellationError {
            phase = .acceptanceUncertain
        } catch {
            handle(error)
        }
    }

    func chooseReply(_ reply: FynReply) async {
        guard phase == .idle, let conversationID else { return }
        consumeReplies()

        if let route = reply.route,
           let navigation = navigation(for: route)
        {
            reduction.pendingNavigation = navigation
            settleNavigation()
            return
        }

        if reply.isAction {
            phase = .accepting
            do {
                phase = .streaming
                try await consume(
                    try await client.performAction(
                        conversationID: conversationID,
                        action: reply.id
                    )
                )
                settleNavigation()
                phase = phaseAfterStream()
            } catch is CancellationError {
                phase = .idle
            } catch {
                handle(error)
            }
            return
        }

        await send(reply.label)
    }

    func stop() {
        streamTask?.cancel()
        streamTask = nil
        if phase == .streaming || phase.isBusy {
            phase = .idle
        }
    }

    func takeNavigation() -> FynNavigation? {
        defer { reduction.pendingNavigation = nil }
        return reduction.pendingNavigation
    }

    func clearCloseAndRefresh() {
        shouldCloseAndRefresh = false
    }

    func stopAndClear() {
        stop()
        reduction = FynReductionState()
        retryText = nil
        retryID = nil
        draft = ""
        shouldCloseAndRefresh = false
    }

    private func performSend(
        _ text: String,
        idempotencyKey: String,
        appendUser: Bool
    ) async {
        phase = .accepting
        retryText = text
        retryID = idempotencyKey
        consumeReplies()

        let localID: String
        if appendUser {
            localID = idempotencyKey
            reduction.messages.append(
                FynMessage(
                    id: localID,
                    role: .user,
                    text: text,
                    replies: [],
                    delivery: .submitting,
                    capture: nil
                )
            )
        } else {
            localID = reduction.messages.last(where: {
                $0.role == .user && $0.text == text
            })?.id ?? idempotencyKey
            updateMessage(id: localID, delivery: .submitting)
        }

        do {
            let id = try await ensureConversation()
            switch try await client.sendMessage(
                conversationID: id,
                text: text,
                currentRoute: currentRoute,
                idempotencyKey: idempotencyKey
            ) {
            case let .stream(stream):
                updateMessage(id: localID, delivery: .persisted)
                phase = .streaming
                try await consume(stream)
            case let .queued(messageID, position):
                replaceMessageID(localID, with: messageID, delivery: .queued)
                phase = .queued(position: position)
                try await consumeQueued(
                    conversationID: id,
                    messageID: messageID
                )
            }
            retryText = nil
            retryID = nil
            settleNavigation()
            phase = phaseAfterStream()
        } catch is CancellationError {
            phase = .idle
        } catch FynClientError.acceptanceUncertain {
            updateMessage(id: localID, delivery: .failed)
            phase = .acceptanceUncertain
        } catch {
            updateMessage(id: localID, delivery: .failed)
            handle(error)
        }
    }

    private func consumeQueued(
        conversationID: String,
        messageID: String
    ) async throws {
        for attempt in 0..<8 {
            do {
                let stream = try await client.streamQueuedMessage(
                    conversationID: conversationID,
                    messageID: messageID,
                    currentRoute: currentRoute
                )
                phase = .streaming
                try await consume(stream)
                updateMessage(id: messageID, delivery: .persisted)
                return
            } catch FynClientError.busy {
                guard attempt < 7 else {
                    phase = .stillAnswering
                    return
                }
                try await clock.sleep(for: .milliseconds(1_500))
            }
        }
    }

    private func consume(
        _ stream: AsyncThrowingStream<FynEvent, Error>
    ) async throws {
        let task = Task { @MainActor in
            for try await event in stream {
                try Task.checkCancellation()
                reducer.reduce(event, into: &reduction)
            }
        }
        streamTask = task
        defer { streamTask = nil }
        try await task.value
    }

    private func ensureConversation() async throws -> String {
        if let conversationID {
            return conversationID
        }
        let conversation = try await client.createConversation(
            currentRoute: currentRoute
        )
        reduction.conversationID = conversation.id
        return conversation.id
    }

    private func load(_ id: String) async throws {
        apply(try await client.loadConversation(id: id))
    }

    private func apply(_ transcript: FynTranscript) {
        var messages = transcript.messages.map(\.fynMessage)
        if messages.count > 1 {
            for index in messages.indices.dropLast() {
                messages[index].replies = []
            }
        }
        reduction = FynReductionState(
            conversationID: transcript.conversation.id,
            messages: messages
        )
    }

    private func consumeReplies() {
        for index in reduction.messages.indices {
            reduction.messages[index].replies = []
        }
    }

    private func updateMessage(id: String, delivery: FynMessage.Delivery) {
        guard let index = reduction.messages.firstIndex(where: { $0.id == id }) else {
            return
        }
        reduction.messages[index].delivery = delivery
    }

    private func replaceMessageID(
        _ currentID: String,
        with serverID: String,
        delivery: FynMessage.Delivery
    ) {
        guard let index = reduction.messages.firstIndex(where: { $0.id == currentID }) else {
            return
        }
        reduction.messages[index] = reduction.messages[index].replacingID(serverID)
        reduction.messages[index].delivery = delivery
    }

    private func settleNavigation() {
        guard let navigation = reduction.pendingNavigation else { return }
        if path(for: navigation.route) == currentRoute {
            reduction.pendingNavigation = nil
            shouldCloseAndRefresh = true
        }
    }

    private func navigation(for path: String) -> FynNavigation? {
        var state = FynReductionState()
        var localReducer = FynEventReducer(makeID: makeID)
        localReducer.reduce(.navigation(path: path, section: nil), into: &state)
        return state.pendingNavigation
    }

    private func path(for route: AppRoute) -> String? {
        switch route {
        case .taxStrategy: "/tax-strategy"
        case .income: "/income"
        case .expenditure: "/expenditure"
        case .savings: "/savings"
        case .investment: "/investment"
        case .retirement: "/retirement"
        default: nil
        }
    }

    private func phaseAfterStream() -> FynConversationPhase {
        switch reduction.notice {
        case let .tokenLimit(message): .tokenLimited(message)
        case .consentRequired: .consentRequired
        case let .failure(message): .failed(message)
        case nil: phase == .stillAnswering ? .stillAnswering : .idle
        }
    }

    private func handle(_ error: Error) {
        switch error {
        case APIError.offline:
            phase = .offline
        case let FynClientError.rateLimited(retryAfter):
            phase = .rateLimited(retryAfter: retryAfter)
        case FynClientError.consentRequired:
            phase = .consentRequired
        case let FynClientError.unexpectedStatus(_, requestID):
            phase = .failed(requestID.map { "Fyn could not respond. Request \($0)." }
                ?? "Fyn could not respond. Please try again.")
        case is URLError:
            phase = .offline
        default:
            phase = .failed("Fyn could not respond. Please try again.")
        }
    }
}
