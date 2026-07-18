import Foundation

struct FynNavigation: Sendable, Equatable {
    let route: AppRoute
    let section: String?
}

enum FynNotice: Sendable, Equatable {
    case tokenLimit(String)
    case consentRequired
    case failure(String)
}

struct FynReductionState: Sendable, Equatable {
    var conversationID: String?
    var messages: [FynMessage] = []
    var pendingNavigation: FynNavigation?
    var levelUp: FynLevelUp?
    var notice: FynNotice?
    var onboardingComplete = false
    var unknownEventNames: [String] = []
}

struct FynEventReducer: Sendable {
    private let makeID: @Sendable () -> String

    init(makeID: @escaping @Sendable () -> String = { UUID().uuidString }) {
        self.makeID = makeID
    }

    mutating func reduce(_ event: FynEvent, into state: inout FynReductionState) {
        switch event {
        case let .text(text):
            guard !text.isEmpty else { return }
            let index = assistantIndex(in: &state)
            state.messages[index].text += text
        case let .conversationCreated(id), let .resume(id):
            state.conversationID = id
        case .onboardingAdvance:
            if let index = currentAssistantIndex(in: state),
               !state.messages[index].text.isEmpty || !state.messages[index].replies.isEmpty
            {
                state.messages[index].delivery = .persisted
                state.messages.append(newAssistant())
            }
        case let .navigation(path, section):
            guard let route = Self.allowedRoute(path) else { return }
            state.pendingNavigation = FynNavigation(route: route, section: section)
        case .onboardingComplete:
            state.onboardingComplete = true
        case let .levelUp(levelUp):
            state.levelUp = levelUp
        case let .tokenLimit(message):
            state.notice = .tokenLimit(message)
            replaceAssistantText(message, delivery: .persisted, in: &state)
        case .consentRequired:
            let message = "Fyn chat consent is required before you can continue."
            state.notice = .consentRequired
            replaceAssistantText(message, delivery: .failed, in: &state)
        case let .handoffError(message), let .error(message):
            state.notice = .failure(message)
            replaceAssistantText(message, delivery: .failed, in: &state)
        case let .entityCreated(name):
            let index = assistantIndex(in: &state)
            var names: [String] = []
            if case let .pending(existing)? = state.messages[index].capture {
                names = existing
            }
            if let name, !names.contains(name) {
                names.append(name)
            }
            state.messages[index].capture = .pending(names: names)
        case let .captureComplete(summary):
            let text = summary ?? "Your information was saved."
            let index = assistantIndex(in: &state)
            state.messages[index].capture = .confirmed(summary: text)
            state.messages[index].text = text
        case let .quickReplies(prompt, replies, actionReplies):
            let index: Int
            if let current = currentAssistantIndex(in: state),
               !state.messages[current].text.isEmpty
            {
                state.messages[current].delivery = .persisted
                state.messages.append(newAssistant())
                index = state.messages.index(before: state.messages.endIndex)
            } else {
                index = assistantIndex(in: &state)
            }
            if let prompt {
                state.messages[index].text = prompt
            }
            state.messages[index].replies = replies.map {
                FynReply(id: $0.id, label: $0.label, route: $0.route, isAction: actionReplies)
            }
        case let .skipLink(reply):
            let index = assistantIndex(in: &state)
            if !state.messages[index].replies.contains(where: { $0.id == reply.id }) {
                state.messages[index].replies.append(reply)
            }
        case .subscriptionOptions:
            let index = assistantIndex(in: &state)
            state.messages[index].replies = [
                FynReply(
                    id: "subscription_options",
                    label: "Compare plans",
                    route: nil,
                    isAction: true
                ),
            ]
        case let .done(messageID):
            guard let index = currentAssistantIndex(in: state) else { return }
            let completed = messageID.map { state.messages[index].replacingID($0) }
                ?? state.messages[index]
            state.messages[index] = completed
            state.messages[index].delivery = .persisted
        case let .unknown(name):
            state.unknownEventNames.append(name)
        }
    }

    private func assistantIndex(in state: inout FynReductionState) -> Int {
        if let index = currentAssistantIndex(in: state) {
            return index
        }
        state.messages.append(newAssistant())
        return state.messages.index(before: state.messages.endIndex)
    }

    private func currentAssistantIndex(in state: FynReductionState) -> Int? {
        guard let index = state.messages.indices.last,
              state.messages[index].role == .fyn,
              state.messages[index].delivery == .streaming
        else {
            return nil
        }
        return index
    }

    private func newAssistant() -> FynMessage {
        FynMessage(
            id: makeID(),
            role: .fyn,
            text: "",
            replies: [],
            delivery: .streaming,
            capture: nil
        )
    }

    private func replaceAssistantText(
        _ text: String,
        delivery: FynMessage.Delivery,
        in state: inout FynReductionState
    ) {
        let index = assistantIndex(in: &state)
        state.messages[index].text = text
        state.messages[index].delivery = delivery
    }

    private static func allowedRoute(_ path: String) -> AppRoute? {
        switch path {
        case "/tax-strategy": .taxStrategy
        case "/income": .income
        case "/expenditure": .expenditure
        case "/savings": .savings(accountID: nil)
        case "/investment": .investment(accountID: nil)
        case "/retirement": .retirement(pensionType: nil, id: nil)
        default: nil
        }
    }
}
