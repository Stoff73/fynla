import Foundation
import Testing
@testable import Fynla

/// Every action opens its own card (design C, CSJ 2026-09-26). Fixtures are real
/// payloads captured from csjones (user 427), not hand-written shapes.
@Suite("Action card feature")
struct ActionCardTests {
    @Test
    func decodesARealTaxCardWithFundingAndFigures() throws {
        let card = try decode(ActionCardEnvelope.self, "action-card").data

        #expect(card.id == "tax_pension_tax_relief")
        #expect(card.deadline?.label == "Closes 5 April")
        #expect(card.why.isEmpty == false)
        #expect(card.keyFigure?.label == "Saves about")
        #expect(card.funding?.accounts.first?.name == "Nationwide")
        #expect(card.primary == .markDone(recommendationID: "tax_pension_tax_relief"))
        #expect(card.done == false)
        #expect(card.goTo != nil)
    }

    @Test
    func decodesARealUnlockCard() throws {
        let card = try decode(ActionCardEnvelope.self, "action-card-unlock").data

        #expect(card.why.isEmpty)
        #expect(card.whatThisChanges.isEmpty == false)
        #expect(card.primary == .capture(prompt: "Help me add my investment details"))
        #expect(card.funding == nil)
    }

    @Test
    func decodesTheRealActionsList() throws {
        let list = try decode(ActionsListEnvelope.self, "actions-list").data

        #expect(list.open.isEmpty == false)
        #expect(list.open.first?.id == "tax_pension_tax_relief")
    }

    @Test
    func clientRequestsTheCardByItsListId() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("action-card-unlock")),
            .response(status: 200, body: try fixture("actions-list")),
        ])
        let client = LiveActionsClient(apiClient: APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: ActionCardTokenProvider(),
            requestID: { "action-card-request" }
        ))

        _ = try await client.card(id: "strategy_unlock:bed_and_isa")
        _ = try await client.list()

        // APIClient encodes each path segment; the id arrives whole.
        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/recommendations/actions/strategy_unlock:bed_and_isa",
            "/fynla/api/recommendations/actions",
        ])
    }

    @Test
    func saveFundingAcceptsTheSuccessReplyWithNoDataKey() async throws {
        // Review C2: the funding-source reply is {success, message}; decoding
        // it through the envelope threw on a save that had succeeded.
        let card = try decode(ActionCardEnvelope.self, "action-card").data
        let transport = TestHTTPTransport([
            .response(status: 200, body: Data(#"{"success":true,"message":"Funding source updated"}"#.utf8)),
        ])
        let client = LiveActionsClient(apiClient: APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: ActionCardTokenProvider(),
            requestID: { "action-card-request" }
        ))

        try await client.saveFunding(card, account: try #require(card.funding?.accounts.first))

        #expect((await transport.requests()).map(\.url?.path) == ["/fynla/api/plans/tax/funding-source"])
    }

    @Test
    func doneCardShowsItsCompletionDate() throws {
        var json = try JSONSerialization.jsonObject(with: try fixture("action-card")) as! [String: Any]
        var data = json["data"] as! [String: Any]
        data["done"] = true
        data["completed_at"] = "2026-09-26T10:00:00+00:00"
        json["data"] = data
        let card = try JSONDecoder().decode(ActionCardEnvelope.self, from: try JSONSerialization.data(withJSONObject: json)).data

        #expect(card.doneLabel == "Done · 26/09/2026")
    }

    @Test
    func seeAllActionsHasItsOwnRoute() {
        #expect(AppRoute.actions.mobilePath == "/actions")
        #expect(AppRoute.actionCard(id: "tax_pension_tax_relief").mobilePath == "/actions/tax_pension_tax_relief")
    }

    private func decode<Value: Decodable>(_ type: Value.Type, _ name: String) throws -> Value {
        try JSONDecoder().decode(type, from: try fixture(name))
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Actions/\(name).json")
        )
    }
}

private struct ActionCardTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "action-card-token" }
}
