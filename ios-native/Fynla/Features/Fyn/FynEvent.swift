import Foundation

struct FynLevelUp: Sendable, Equatable {
    let level: Int
    let levelName: String
    let nextActions: [String]
}

enum FynEvent: Sendable, Equatable {
    case text(String)
    case conversationCreated(String)
    case resume(String)
    case onboardingAdvance
    case navigation(path: String, section: String?)
    case onboardingComplete
    case levelUp(FynLevelUp)
    case tokenLimit(String)
    case consentRequired
    case handoffError(String)
    case error(String)
    case entityCreated(name: String?)
    case captureComplete(summary: String?)
    case quickReplies(prompt: String?, replies: [FynReply], actionReplies: Bool)
    case skipLink(FynReply)
    case subscriptionOptions
    case done(messageID: String?)
    case unknown(String)
}

struct FynEventDecoder: Sendable {
    func decode(_ event: SSEEvent) throws -> FynEvent {
        let frame = try JSONDecoder().decode(FynEventFrame.self, from: Data(event.data.utf8))
        switch frame.type {
        case "content":
            return .text(frame.text ?? "")
        case "conversation_created":
            return frame.conversationID.map { .conversationCreated($0.value) }
                ?? .unknown(frame.type)
        case "resume":
            return frame.conversationID.map { .resume($0.value) }
                ?? .unknown(frame.type)
        case "onboarding_advance":
            return .onboardingAdvance
        case "navigation":
            return frame.routePath.map { .navigation(path: $0, section: frame.section) }
                ?? .unknown(frame.type)
        case "onboarding_complete":
            return .onboardingComplete
        case "level_up":
            guard let level = frame.level else { return .unknown(frame.type) }
            return .levelUp(
                FynLevelUp(
                    level: level,
                    levelName: frame.levelName ?? "Level \(level)",
                    nextActions: frame.nextActions ?? []
                )
            )
        case "token_limit":
            return .tokenLimit(frame.message ?? "You've reached your daily Fyn usage limit.")
        case "consent_required":
            return .consentRequired
        case "handoff_error":
            return .handoffError(frame.message ?? "Fyn could not pick up that request.")
        case "error":
            return .error(frame.message ?? "Fyn could not complete that request.")
        case "entity_created":
            return .entityCreated(name: frame.name)
        case "capture_complete":
            return .captureComplete(summary: frame.summary)
        case "quick_replies":
            let isAction = frame.actionBubbles == true
            var replies = (frame.bubbles ?? []).map {
                FynReply(
                    id: $0.id,
                    label: $0.label,
                    route: $0.route,
                    isAction: isAction
                )
            }
            if let skip = frame.skipLink,
               !replies.contains(where: { $0.id == "skip" })
            {
                replies.append(
                    FynReply(id: "skip", label: skip.label, route: nil, isAction: true)
                )
            }
            return .quickReplies(
                prompt: frame.promptText,
                replies: replies,
                actionReplies: isAction
            )
        case "skip_link":
            guard let skip = frame.skipLink else { return .unknown(frame.type) }
            return .skipLink(
                FynReply(id: "skip", label: skip.label, route: nil, isAction: true)
            )
        case "action" where frame.action == "subscription_options":
            return .subscriptionOptions
        case "done":
            return .done(messageID: frame.messageID?.value)
        default:
            return .unknown(frame.type)
        }
    }
}

private struct FynEventFrame: Decodable {
    let type: String
    let text: String?
    let message: String?
    let conversationID: FynServerID?
    let messageID: FynServerID?
    let routePath: String?
    let section: String?
    let level: Int?
    let levelName: String?
    let nextActions: [String]?
    let name: String?
    let summary: String?
    let promptText: String?
    let bubbles: [FynReplyPayload]?
    let actionBubbles: Bool?
    let skipLink: FynSkipLink?
    let action: String?

    private enum CodingKeys: String, CodingKey {
        case type, text, message, section, level, name, summary, bubbles, action
        case conversationID = "conversation_id"
        case messageID = "message_id"
        case routePath = "route_path"
        case levelName = "level_name"
        case nextActions = "next_actions"
        case promptText = "prompt_text"
        case actionBubbles = "action_bubbles"
        case skipLink = "skip_link"
    }
}
