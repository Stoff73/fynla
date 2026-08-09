import Foundation
import Testing
@testable import Fynla

@Suite("Personal information feature")
struct PersonalInformationTests {
    @Test
    func decodesCanonicalProfileWithoutReconstructingFinancialFacts() throws {
        let profile = try decodedProfile()

        #expect(profile.personalInfo.name == "Alex Morgan")
        #expect(profile.personalInfo.nationalInsuranceNumber == "***3456")
        #expect(profile.personalInfo.address.formatted == "10 Savannah Way, London, Greater London, SW1A 1AA")
        #expect(profile.household?.name == "Morgan household")
        #expect(profile.spouse?.name == "Sam Morgan")
        #expect(profile.domicileInfo?.display == "Domiciled in the United Kingdom")
        #expect(profile.incomeOccupation?.totalAnnualIncome == Decimal(86_000))
        #expect(profile.expenditure?.monthlyExpenditure == Decimal(3_250))
        #expect(profile.assetsSummary?.total == Decimal(512_500))
        #expect(profile.liabilitiesSummary?.total == Decimal(187_250))
        #expect(profile.netWorth == Decimal(325_250))
    }

    @Test
    func presentationIsExplicitlyReadOnlyAndKeepsServerMaskedNationalInsurance() throws {
        let profile = try decodedProfile()

        #expect(PersonalInformationModel.supportsEditing == false)
        #expect(profile.personalInfo.nationalInsuranceNumber == "***3456")
    }

    @Test
    func clientUsesTheSharedAuthenticatedProfileEndpoint() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture()),
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
            tokenProvider: PersonalInformationTokenProvider(),
            requestID: { "personal-information-request" }
        )

        let profile = try await LivePersonalInformationClient(apiClient: apiClient).load()

        #expect(profile.personalInfo.name == "Alex Morgan")
        let request = try #require(await transport.requests().first)
        #expect(request.httpMethod == "GET")
        #expect(request.url?.path == "/fynla/api/user/profile")
        #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer personal-information-token")
    }

    @Test @MainActor
    func modelLoadsRetriesAndClearsTheCanonicalSnapshot() async throws {
        let profile = try decodedProfile()
        let client = PersonalInformationClientStub([
            .failure(APIError.server(status: 500, requestID: "profile-1")),
            .success(profile),
        ])
        let model = PersonalInformationModel(client: client)

        await model.load()
        #expect(model.state == .failed(requestID: "profile-1"))

        await model.load()
        #expect(model.state == .loaded(profile))
        #expect(await client.loadCount() == 2)

        model.stop()
        #expect(model.state == .idle)
    }

    @Test @MainActor
    func modelMapsOfflineAndAuthenticationStates() async {
        let offline = PersonalInformationModel(
            client: PersonalInformationClientStub([.failure(APIError.offline)])
        )
        await offline.load()
        #expect(offline.state == .offline(previous: nil))

        let auth = PersonalInformationModel(
            client: PersonalInformationClientStub([.failure(APIError.unauthenticated)])
        )
        await auth.load()
        #expect(auth.state == .unauthenticated)
    }

    private func fixture() throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/PersonalInformation/profile-populated.json")
        )
    }

    private func decodedProfile() throws -> PersonalInformationProfile {
        try JSONDecoder().decode(
            APIEnvelope<PersonalInformationProfile>.self,
            from: try fixture()
        ).data
    }
}

private struct PersonalInformationTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "personal-information-token" }
}

private actor PersonalInformationClientStub: PersonalInformationClient {
    private var results: [Result<PersonalInformationProfile, Error>]
    private var count = 0

    init(_ results: [Result<PersonalInformationProfile, Error>]) {
        self.results = results
    }

    func load() async throws -> PersonalInformationProfile {
        count += 1
        guard !results.isEmpty else {
            throw APIError.server(status: 500, requestID: nil)
        }
        return try results.removeFirst().get()
    }

    func loadCount() -> Int { count }
}
