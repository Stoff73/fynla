import Foundation
import Testing
@testable import Fynla

@Suite("Expenditure feature")
struct ExpenditureTests {
    @Test
    func decodesTheExistingMobileProfileExpenditureShape() throws {
        let profile = try decodedProfile("expenditure-populated")

        #expect(profile.expenditure.monthly == Decimal(string: "3250.50"))
        #expect(profile.expenditure.annual == Decimal(39006))
        #expect(profile.expenditure.categories?.nonZeroRows.map(\.title) == [
            "Food & groceries",
            "Transport & fuel",
            "Clothing & personal care",
            "Entertainment & dining",
            "Other",
        ])
    }

    @Test
    func missingCategoriesRepresentsTheCanonicalFreeResponse() throws {
        let expenditure = try decodedProfile("expenditure-free").expenditure

        #expect(expenditure.monthly == Decimal(1800))
        #expect(expenditure.annual == Decimal(21600))
        #expect(expenditure.categories == nil)
    }

    @Test
    func zeroExpenditureHasNoCategoryRows() throws {
        let expenditure = try decodedProfile("expenditure-empty").expenditure

        #expect(expenditure.monthly == 0)
        #expect(expenditure.annual == 0)
        #expect(expenditure.categories?.nonZeroRows.isEmpty == true)
    }

    @Test
    func clientUsesTheSharedAuthenticatedProfileEndpoint() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                body: try fixture("expenditure-populated")
            ),
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
            tokenProvider: ExpenditureTokenProvider(),
            requestID: { "expenditure-request" }
        )

        let profile = try await LiveExpenditureClient(apiClient: apiClient).load()

        #expect(profile.expenditure.monthly == Decimal(string: "3250.50"))
        let request = try #require(await transport.requests().first)
        #expect(request.httpMethod == "GET")
        #expect(request.url?.path == "/fynla/api/user/profile")
        #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer expenditure-token")
    }

    @Test @MainActor
    func modelLoadsRefreshesAndClearsTheServerSnapshot() async throws {
        let profile = try decodedProfile("expenditure-populated")
        let client = ExpenditureClientStub([
            .success(profile),
            .success(profile),
        ])
        let model = ExpenditureModel(client: client)

        await model.load()
        #expect(model.state == .loaded(profile.expenditure))
        await model.refresh()
        #expect(await client.loadCount() == 2)

        model.stop()
        #expect(model.state == .idle)
    }

    @Test @MainActor
    func modelMapsOfflineAuthenticationAndUpgradeStates() async {
        let offline = ExpenditureModel(
            client: ExpenditureClientStub([.failure(APIError.offline)])
        )
        await offline.load()
        #expect(offline.state == .offline(previous: nil))

        let auth = ExpenditureModel(
            client: ExpenditureClientStub([.failure(APIError.unauthenticated)])
        )
        await auth.load()
        #expect(auth.state == .unauthenticated)

        let upgrade = ExpenditureModel(
            client: ExpenditureClientStub([
                .failure(APIError.upgradeRequired(message: "Detailed expenditure is part of Premium.")),
            ])
        )
        await upgrade.load()
        #expect(
            upgrade.state
                == .upgradeRequired(message: "Detailed expenditure is part of Premium.")
        )
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/Expenditure/\(name).json")
        )
    }

    private func decodedProfile(_ name: String) throws -> ExpenditureProfile {
        try JSONDecoder().decode(
            APIEnvelope<ExpenditureProfile>.self,
            from: try fixture(name)
        ).data
    }
}

private struct ExpenditureTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "expenditure-token" }
}

private actor ExpenditureClientStub: ExpenditureClient {
    private var results: [Result<ExpenditureProfile, Error>]
    private var count = 0

    init(_ results: [Result<ExpenditureProfile, Error>]) {
        self.results = results
    }

    func load() async throws -> ExpenditureProfile {
        count += 1
        guard !results.isEmpty else {
            throw APIError.server(status: 500, requestID: nil)
        }
        return try results.removeFirst().get()
    }

    func loadCount() -> Int { count }
}
