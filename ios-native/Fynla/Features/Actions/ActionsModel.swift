import Observation

enum ActionsLoadState<Value: Sendable & Equatable>: Sendable, Equatable {
    case idle
    case loading
    case loaded(Value)
    case notFound
    case failed
}

/// The actions list ("See all actions") and each action's card — both from
/// the endpoints web and /m use; nothing is worked out on the device.
@MainActor
@Observable
final class ActionsModel {
    private(set) var list: ActionsLoadState<ActionsList> = .idle
    private(set) var cards: [String: ActionsLoadState<ActionCard>] = [:]
    private(set) var markingID: String?
    private(set) var actionFailed = false
    private let client: any ActionsClient

    init(client: any ActionsClient) {
        self.client = client
    }

    func loadList() async {
        if case .loaded = list {} else { list = .loading }
        do {
            list = .loaded(try await client.list())
        } catch {
            if case .loaded = list { return }
            list = .failed
        }
    }

    func card(_ id: String) -> ActionsLoadState<ActionCard> {
        cards[id] ?? .idle
    }

    func loadCard(_ id: String) async {
        if cards[id] == nil { cards[id] = .loading }
        do {
            cards[id] = .loaded(try await client.card(id: id))
        } catch APIError.server(let status, _) where status == 404 {
            cards[id] = .notFound
        } catch {
            if case .loaded = cards[id] { return }
            cards[id] = .failed
        }
    }

    func markDone(_ card: ActionCard) async {
        guard markingID == nil else { return }
        actionFailed = false
        markingID = card.id
        defer { markingID = nil }
        do {
            try await client.markDone(card)
            await loadCard(card.id)
            await loadList()
        } catch {
            actionFailed = true
        }
    }

    func selectFunding(_ card: ActionCard, account: ActionCard.FundingAccount) async {
        actionFailed = false
        do {
            try await client.saveFunding(card, account: account)
            guard case var .loaded(updated) = cards[card.id] else { return }
            updated.funding?.selectedID = account.accountID
            updated.funding?.selectedType = account.type
            cards[card.id] = .loaded(updated)
        } catch {
            actionFailed = true
        }
    }

    func stop() {
        list = .idle
        cards = [:]
        markingID = nil
        actionFailed = false
    }
}
