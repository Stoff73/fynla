import Foundation

enum FynContextualActionKind: String, Codable, Sendable, Equatable {
    case add
    case edit
}

struct FynContextualOrigin: Codable, Sendable, Equatable {
    enum Kind: String, Codable, Sendable, Equatable {
        case surfaceAction = "surface_action"
        case recommendation
    }

    let kind: Kind
    let recommendationID: Int?

    init(kind: Kind, recommendationID: Int? = nil) {
        self.kind = kind
        self.recommendationID = recommendationID
    }

    private enum CodingKeys: String, CodingKey {
        case kind
        case recommendationID = "recommendation_id"
    }
}

struct FynContextualConversationRequest: Codable, Sendable, Equatable {
    let action: FynContextualActionKind
    let resourceType: String
    let resourceID: Int?
    let currentDestination: SemanticDestination
    let origin: FynContextualOrigin

    private enum CodingKeys: String, CodingKey {
        case action, origin
        case resourceType = "resource_type"
        case resourceID = "resource_id"
        case currentDestination = "current_destination"
    }
}

struct FynContextualAction: Sendable, Equatable {
    let request: FynContextualConversationRequest

    init(
        action: FynContextualActionKind,
        resourceType: String,
        resourceID: Int? = nil,
        currentDestination: SemanticDestination,
        origin: FynContextualOrigin
    ) {
        request = FynContextualConversationRequest(
            action: action,
            resourceType: resourceType,
            resourceID: resourceID,
            currentDestination: currentDestination,
            origin: origin
        )
    }
}

struct FynContextualConversationResponse: Decodable, Sendable, Equatable {
    let conversation: FynConversationRecord
    let openingMessage: FynTranscriptMessage

    private enum CodingKeys: String, CodingKey {
        case conversation
        case openingMessage = "opening_message"
    }
}

struct FynConversationRecord: Decodable, Sendable, Equatable {
    let id: String
    let title: String?
    let messageCount: Int?
    let status: String?

    private enum CodingKeys: String, CodingKey {
        case id, title, status
        case messageCount = "message_count"
    }

    init(from decoder: Decoder) throws {
        let values = try decoder.container(keyedBy: CodingKeys.self)
        id = try values.decode(FynServerID.self, forKey: .id).value
        title = try values.decodeIfPresent(String.self, forKey: .title)
        messageCount = try values.decodeIfPresent(Int.self, forKey: .messageCount)
        status = try values.decodeIfPresent(String.self, forKey: .status)
    }
}

struct FynTranscript: Decodable, Sendable, Equatable {
    let conversation: FynConversationRecord
    let messages: [FynTranscriptMessage]
}

struct FynTranscriptMessage: Decodable, Sendable, Equatable {
    let id: String
    let role: String
    let content: String
    let metadata: FynMessageMetadata?
    let createdAt: String?

    private enum CodingKeys: String, CodingKey {
        case id, role, content, metadata
        case createdAt = "created_at"
    }

    init(from decoder: Decoder) throws {
        let values = try decoder.container(keyedBy: CodingKeys.self)
        id = try values.decode(FynServerID.self, forKey: .id).value
        role = try values.decode(String.self, forKey: .role)
        content = try values.decodeIfPresent(String.self, forKey: .content) ?? ""
        metadata = try values.decodeIfPresent(FynMessageMetadata.self, forKey: .metadata)
        createdAt = try values.decodeIfPresent(String.self, forKey: .createdAt)
    }

    var fynMessage: FynMessage {
        let actionReplies = metadata?.actionBubbles == true
        var replies = (metadata?.bubbles ?? []).map { reply in
            FynReply(
                id: reply.id,
                label: reply.label,
                route: reply.route,
                isAction: actionReplies
            )
        }
        if let skip = metadata?.skipLink,
           !replies.contains(where: { $0.id == "skip" })
        {
            replies.append(
                FynReply(id: "skip", label: skip.label, route: nil, isAction: true)
            )
        }
        return FynMessage(
            id: id,
            role: role == "user" ? .user : .fyn,
            text: content,
            replies: replies,
            delivery: .persisted,
            capture: nil
        )
    }
}

struct FynMessageMetadata: Decodable, Sendable, Equatable {
    let bubbles: [FynReplyPayload]?
    let actionBubbles: Bool?
    let skipLink: FynSkipLink?

    private enum CodingKeys: String, CodingKey {
        case bubbles
        case actionBubbles = "action_bubbles"
        case skipLink = "skip_link"
    }
}

struct FynReplyPayload: Decodable, Sendable, Equatable {
    let id: String
    let label: String
    let route: String?
}

struct FynSkipLink: Decodable, Sendable, Equatable {
    let label: String
}

struct FynReply: Identifiable, Sendable, Equatable {
    let id: String
    let label: String
    let route: String?
    let isAction: Bool
}

struct FynMessage: Identifiable, Sendable, Equatable {
    enum Role: Sendable, Equatable {
        case user
        case fyn
    }

    enum Delivery: Sendable, Equatable {
        case persisted
        case submitting
        case streaming
        case queued
        case failed
    }

    let id: String
    let role: Role
    var text: String
    var replies: [FynReply]
    var delivery: Delivery
    var capture: CaptureState?

    func replacingID(_ serverID: String) -> Self {
        Self(
            id: serverID,
            role: role,
            text: text,
            replies: replies,
            delivery: delivery,
            capture: capture
        )
    }
}

enum CaptureState: Sendable, Equatable {
    case pending(names: [String])
    case confirmed(summary: String)
}

struct FynOnboardingStatus: Decodable, Sendable, Equatable {
    let inProgress: Bool
    let currentStep: String?
    let path: String?
    let selection: String?
    let conversationID: String?

    private enum CodingKeys: String, CodingKey {
        case inProgress = "in_progress"
        case currentStep = "current_step"
        case path, selection
        case conversationID = "conversation_id"
    }

    init(from decoder: Decoder) throws {
        let values = try decoder.container(keyedBy: CodingKeys.self)
        inProgress = try values.decode(Bool.self, forKey: .inProgress)
        currentStep = try values.decodeIfPresent(String.self, forKey: .currentStep)
        path = try values.decodeIfPresent(String.self, forKey: .path)
        selection = try values.decodeIfPresent(String.self, forKey: .selection)
        conversationID = try values.decodeIfPresent(FynServerID.self, forKey: .conversationID)?.value
    }
}

struct FynConversationListItem: Decodable, Sendable, Equatable {
    let id: String
    let title: String?
    let messageCount: Int?
    let mode: String?
    let purpose: String?
    let relatedEntity: FynConversationRelatedEntity?
    let status: String?
    let createdAt: String?
    let updatedAt: String?
    let lastMessageAt: String?
    let lastMessageSummary: String?
    let fallbackDestination: SemanticDestination?

    private enum CodingKeys: String, CodingKey {
        case id, title, mode, purpose, status
        case messageCount = "message_count"
        case relatedEntity = "related_entity"
        case createdAt = "created_at"
        case updatedAt = "updated_at"
        case lastMessageAt = "last_message_at"
        case lastMessageSummary = "last_message_summary"
        case fallbackDestination = "fallback_destination"
    }

    init(from decoder: Decoder) throws {
        let values = try decoder.container(keyedBy: CodingKeys.self)
        id = try values.decode(FynServerID.self, forKey: .id).value
        title = try values.decodeIfPresent(String.self, forKey: .title)
        messageCount = try values.decodeIfPresent(Int.self, forKey: .messageCount)
        mode = try values.decodeIfPresent(String.self, forKey: .mode)
        purpose = try values.decodeIfPresent(String.self, forKey: .purpose)
        relatedEntity = try values.decodeIfPresent(
            FynConversationRelatedEntity.self,
            forKey: .relatedEntity
        )
        status = try values.decodeIfPresent(String.self, forKey: .status)
        createdAt = try values.decodeIfPresent(String.self, forKey: .createdAt)
        updatedAt = try values.decodeIfPresent(String.self, forKey: .updatedAt)
        lastMessageAt = try values.decodeIfPresent(String.self, forKey: .lastMessageAt)
        lastMessageSummary = try values.decodeIfPresent(
            String.self,
            forKey: .lastMessageSummary
        )
        fallbackDestination = try values.decodeIfPresent(
            SemanticDestination.self,
            forKey: .fallbackDestination
        )
    }
}

struct FynConversationRelatedEntity: Decodable, Sendable, Equatable {
    let type: String
    let id: String?
    let label: String?
    let available: Bool
    let explanation: String?

    private enum CodingKeys: String, CodingKey {
        case type, id, label, available, explanation
    }

    init(from decoder: Decoder) throws {
        let values = try decoder.container(keyedBy: CodingKeys.self)
        type = try values.decode(String.self, forKey: .type)
        id = try values.decodeIfPresent(FynServerID.self, forKey: .id)?.value
        label = try values.decodeIfPresent(String.self, forKey: .label)
        available = try values.decodeIfPresent(Bool.self, forKey: .available) ?? false
        explanation = try values.decodeIfPresent(String.self, forKey: .explanation)
    }
}

struct FynServerID: Decodable, Sendable, Equatable {
    let value: String

    init(from decoder: Decoder) throws {
        let value = try decoder.singleValueContainer()
        if let string = try? value.decode(String.self) {
            self.value = string
        } else {
            self.value = String(try value.decode(Int.self))
        }
    }
}
