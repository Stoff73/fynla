import Foundation
import Testing
@testable import Fynla

@Suite("Income feature")
struct IncomeTests {
    @Test
    func decodesTheExistingMobileProfileIncomeShape() throws {
        let envelope = try JSONDecoder().decode(
            APIEnvelope<IncomeProfile>.self,
            from: try fixture("income-populated")
        )

        #expect(envelope.data.incomeSummary.user.total == Decimal(string: "78300.75"))
        #expect(envelope.data.incomeSummary.user.employmentDetail == "Northstar Ltd · Product designer")
        #expect(envelope.data.incomeSummary.user.nonZeroSources.map(\.title) == [
            "Employment", "Self-employment", "Dividends", "Interest",
        ])
        #expect(envelope.data.incomeSummary.spouse?.total == Decimal(48000))
    }

    @Test
    func keepsTheBackendTotalAndDoesNotRecalculateItOnDevice() throws {
        let envelope = try JSONDecoder().decode(
            APIEnvelope<IncomeProfile>.self,
            from: try fixture("income-populated")
        )

        #expect(envelope.data.incomeSummary.user.sourceTotal == Decimal(string: "78300.75"))
        #expect(envelope.data.incomeSummary.user.total == Decimal(string: "78300.75"))
    }

    @Test
    func emptyIncomeHasNoRowsAndNoInventedSpouse() throws {
        let envelope = try JSONDecoder().decode(
            APIEnvelope<IncomeProfile>.self,
            from: try fixture("income-empty")
        )

        #expect(envelope.data.incomeSummary.user.nonZeroSources.isEmpty)
        #expect(envelope.data.incomeSummary.spouse == nil)
    }

    @Test
    func clientUsesTheSharedAuthenticatedProfileEndpoint() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("income-populated")),
        ])
        let apiClient = APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: IncomeTokenProvider(),
            requestID: { "income-request" }
        )

        let profile = try await LiveIncomeClient(apiClient: apiClient).load()

        #expect(profile.incomeSummary.user.total == Decimal(string: "78300.75"))
        let request = try #require(await transport.requests().first)
        #expect(request.httpMethod == "GET")
        #expect(request.url?.path == "/fynla/api/user/profile")
        #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer income-token")
    }

    @Test @MainActor
    func modelLoadsRefreshesAndClearsTheServerSnapshot() async throws {
        let profile = try decodedProfile("income-populated")
        let client = IncomeClientStub([.success(profile), .success(profile)])
        let model = IncomeModel(client: client)

        await model.load()
        #expect(model.state == .loaded(profile.incomeSummary))
        await model.refresh()
        #expect(await client.loadCount() == 2)

        model.stop()
        #expect(model.state == .idle)
    }

    @Test @MainActor
    func modelMapsOfflineAuthenticationAndUpgradeStates() async {
        let offline = IncomeModel(
            client: IncomeClientStub([.failure(APIError.offline)])
        )
        await offline.load()
        #expect(offline.state == .offline(previous: nil))

        let auth = IncomeModel(
            client: IncomeClientStub([.failure(APIError.unauthenticated)])
        )
        await auth.load()
        #expect(auth.state == .unauthenticated)

        let upgrade = IncomeModel(
            client: IncomeClientStub([
                .failure(APIError.upgradeRequired(message: "Premium income details")),
            ])
        )
        await upgrade.load()
        #expect(upgrade.state == .upgradeRequired(message: "Premium income details"))
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/Income/\(name).json")
        )
    }

    private func decodedProfile(_ name: String) throws -> IncomeProfile {
        try JSONDecoder().decode(
            APIEnvelope<IncomeProfile>.self,
            from: try fixture(name)
        ).data
    }
}

private struct IncomeTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "income-token" }
}

private actor IncomeClientStub: IncomeClient {
    private var results: [Result<IncomeProfile, Error>]
    private var count = 0

    init(_ results: [Result<IncomeProfile, Error>]) {
        self.results = results
    }

    func load() async throws -> IncomeProfile {
        count += 1
        guard !results.isEmpty else {
            throw APIError.server(status: 500, requestID: nil)
        }
        return try results.removeFirst().get()
    }

    func loadCount() -> Int { count }
}
