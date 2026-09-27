import Foundation

/// One action's detail card (design C) — `GET api/recommendations/actions/{id}`,
/// built once on the server by ActionCardService. The app renders it as sent.
struct ActionCardEnvelope: Decodable, Sendable, Equatable {
    let data: ActionCard
}

struct ActionCard: Decodable, Sendable, Equatable, Identifiable {
    struct Deadline: Decodable, Sendable, Equatable {
        let label: String
    }

    struct KeyFigure: Decodable, Sendable, Equatable {
        let label: String
        let value: String
        let sub: String?
    }

    struct FundingAccount: Decodable, Sendable, Equatable, Identifiable {
        let accountID: Int
        let type: String
        let name: String
        let balance: Decimal
        let warning: String?

        var id: String { "\(type):\(accountID)" }

        private enum CodingKeys: String, CodingKey {
            case accountID = "id"
            case type, name, balance, warning
        }
    }

    struct Funding: Decodable, Sendable, Equatable {
        let accounts: [FundingAccount]
        var selectedID: Int?
        var selectedType: String?

        private enum CodingKeys: String, CodingKey {
            case accounts
            case selectedID = "selected_id"
            case selectedType = "selected_type"
        }
    }

    enum Primary: Sendable, Equatable {
        case markDone(recommendationID: String)
        case capture(prompt: String)
        case navigate(destination: SemanticDestination?, payload: String?)
    }

    /// Where a recommendation is actioned — "Go to it" beside Mark as done.
    struct GoTo: Decodable, Sendable, Equatable {
        let destination: SemanticDestination?
        let payload: String?
    }

    enum AskFyn: Sendable, Equatable {
        case prompt(String)
        case contextual(FynContextualConversationRequest)
    }

    let id: String
    let type: String
    let module: String
    let moduleLabel: String
    let topic: String?
    let deadline: Deadline?
    let title: String
    let description: String
    let why: [String]
    let whatThisChanges: [String]
    let keyFigure: KeyFigure?
    let howTo: [String]
    let conflictNote: String?
    let disclaimer: String?
    let askFyn: AskFyn
    let primary: Primary?
    var funding: Funding?
    let goTo: GoTo?
    let done: Bool
    let completedAt: String?

    var eyebrow: String { [moduleLabel, topic].compactMap { $0 }.joined(separator: " · ") }

    /// "Done · 26/09/2026", as web and /m show it (review I2).
    var doneLabel: String {
        guard let completedAt, let date = ISO8601DateFormatter().date(from: completedAt) else { return "Done" }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_GB")
        formatter.dateFormat = "dd/MM/yyyy"
        return "Done · \(formatter.string(from: date))"
    }

    private enum CodingKeys: String, CodingKey {
        case id, type, module, topic, deadline, title, description, why, done, funding, primary
        case goTo = "go_to"
        case moduleLabel = "module_label"
        case whatThisChanges = "what_this_changes"
        case keyFigure = "key_figure"
        case howTo = "how_to"
        case conflictNote = "conflict_note"
        case disclaimer
        case askFyn = "ask_fyn"
        case completedAt = "completed_at"
    }

    private struct RawPrimary: Decodable {
        let kind: String
        let recommendationID: String?
        let prompt: String?
        let destination: SemanticDestination?
        let payload: String?

        private enum CodingKeys: String, CodingKey {
            case kind, prompt, destination, payload
            case recommendationID = "recommendation_id"
        }
    }

    private struct RawAskFyn: Decodable {
        let kind: String
        let prompt: String?
        let request: FynContextualConversationRequest?
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        id = try c.decode(String.self, forKey: .id)
        type = try c.decode(String.self, forKey: .type)
        module = try c.decode(String.self, forKey: .module)
        moduleLabel = try c.decodeIfPresent(String.self, forKey: .moduleLabel) ?? ""
        topic = try c.decodeIfPresent(String.self, forKey: .topic)
        deadline = try? c.decodeIfPresent(Deadline.self, forKey: .deadline)
        title = try c.decode(String.self, forKey: .title)
        description = try c.decodeIfPresent(String.self, forKey: .description) ?? ""
        why = try c.decodeIfPresent([String].self, forKey: .why) ?? []
        whatThisChanges = try c.decodeIfPresent([String].self, forKey: .whatThisChanges) ?? []
        keyFigure = try? c.decodeIfPresent(KeyFigure.self, forKey: .keyFigure)
        howTo = try c.decodeIfPresent([String].self, forKey: .howTo) ?? []
        conflictNote = try c.decodeIfPresent(String.self, forKey: .conflictNote)
        disclaimer = try c.decodeIfPresent(String.self, forKey: .disclaimer)
        // A malformed optional block must not take the whole card down.
        funding = try? c.decodeIfPresent(Funding.self, forKey: .funding)
        goTo = try? c.decodeIfPresent(GoTo.self, forKey: .goTo)
        done = try c.decodeIfPresent(Bool.self, forKey: .done) ?? false
        completedAt = try c.decodeIfPresent(String.self, forKey: .completedAt)

        if let raw = try? c.decodeIfPresent(RawAskFyn.self, forKey: .askFyn),
           raw.kind == "contextual", let request = raw.request
        {
            askFyn = .contextual(request)
        } else {
            let raw = try? c.decodeIfPresent(RawAskFyn.self, forKey: .askFyn)
            askFyn = .prompt(raw?.prompt ?? "Tell me more about: \(title)")
        }

        switch try? c.decodeIfPresent(RawPrimary.self, forKey: .primary) {
        case let raw? where raw.kind == "mark_done":
            primary = .markDone(recommendationID: raw.recommendationID ?? id)
        case let raw? where raw.kind == "capture":
            primary = .capture(prompt: raw.prompt ?? "")
        case let raw? where raw.kind == "navigate":
            primary = .navigate(destination: raw.destination, payload: raw.payload)
        default:
            primary = nil
        }
    }
}

/// `GET api/recommendations/actions` — the same list /m and web show.
struct ActionsListEnvelope: Decodable, Sendable, Equatable {
    let data: ActionsList
}

struct ActionsList: Decodable, Sendable, Equatable {
    let open: [ActionsListItem]
    let completed: [ActionsCompletedItem]

    private enum CodingKeys: String, CodingKey { case open, completed }

    init(open: [ActionsListItem], completed: [ActionsCompletedItem]) {
        self.open = open
        self.completed = completed
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        open = try c.decodeIfPresent([ActionsListItem].self, forKey: .open) ?? []
        completed = (try? c.decodeIfPresent([ActionsCompletedItem].self, forKey: .completed)) ?? []
    }
}

struct ActionsListItem: Decodable, Sendable, Equatable, Identifiable {
    let id: String
    let type: String
    let title: String
    let meta: String?
    let moduleLabel: String?

    private enum CodingKeys: String, CodingKey {
        case id, type, title, meta
        case moduleLabel = "module_label"
    }
}

struct ActionsCompletedItem: Decodable, Sendable, Equatable, Identifiable {
    let id: String
    let text: String
    let moduleLabel: String?
    let completedAt: String?

    private enum CodingKeys: String, CodingKey {
        case id = "recommendation_id"
        case text = "recommendation_text"
        case moduleLabel = "module_label"
        case completedAt = "completed_at"
    }
}
