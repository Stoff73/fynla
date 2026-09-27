import Foundation

protocol ActionsClient: Sendable {
    func list() async throws -> ActionsList
    func card(id: String) async throws -> ActionCard
    func markDone(_ card: ActionCard) async throws
    func saveFunding(_ card: ActionCard, account: ActionCard.FundingAccount) async throws
}

/// The same endpoints web and /m call — no native-only shape.
struct LiveActionsClient: ActionsClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func list() async throws -> ActionsList {
        try await apiClient.send(
            APIRequest<ActionsListEnvelope>(
                path: "api/recommendations/actions",
                method: .get,
                headers: ["Cache-Control": "no-cache"],
                responseDecoding: .raw
            )
        ).data
    }

    func card(id: String) async throws -> ActionCard {
        try await apiClient.send(
            APIRequest<ActionCardEnvelope>(
                path: "api/recommendations/actions/\(id)",
                method: .get,
                headers: ["Cache-Control": "no-cache"],
                responseDecoding: .raw
            )
        ).data
    }

    func markDone(_ card: ActionCard) async throws {
        let body = try JSONEncoder().encode(ActionCompletion(module: card.module, recommendationText: card.title))
        _ = try await apiClient.send(
            APIRequest<ActionEmptyResponse>(
                path: "api/recommendations/\(card.id)/mark-done",
                method: .post,
                body: body
            )
        )
    }

    func saveFunding(_ card: ActionCard, account: ActionCard.FundingAccount) async throws {
        let body = try JSONEncoder().encode(FundingSelection(
            actionCategory: String(card.id.dropFirst(card.id.hasPrefix("tax_") ? 4 : 0)),
            targetAccountID: 0,
            fundingSourceType: account.type,
            fundingSourceID: account.accountID
        ))
        _ = try await apiClient.send(
            // The reply is {success, message} with no `data`, so it is read raw;
            // the envelope decoder would throw on a save that succeeded (review C2).
            APIRequest<ActionEmptyResponse>(
                path: "api/plans/tax/funding-source",
                method: .put,
                body: body,
                responseDecoding: .raw
            )
        )
    }
}

private struct ActionCompletion: Encodable, Sendable {
    let module: String
    let recommendationText: String

    private enum CodingKeys: String, CodingKey {
        case module
        case recommendationText = "recommendation_text"
    }
}

private struct FundingSelection: Encodable, Sendable {
    let actionCategory: String
    let targetAccountID: Int
    let fundingSourceType: String
    let fundingSourceID: Int

    private enum CodingKeys: String, CodingKey {
        case actionCategory = "action_category"
        case targetAccountID = "target_account_id"
        case fundingSourceType = "funding_source_type"
        case fundingSourceID = "funding_source_id"
    }
}

private struct ActionEmptyResponse: Decodable, Sendable {}
