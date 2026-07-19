import Foundation
import Testing
@testable import Fynla

@Suite("Estate feature")
struct EstateTests {
    @Test
    func decodesServerOwnedTeaserAndFullModes() throws {
        let teaser = try decodeRaw(EstateIndexResponse.self, "estate-teaser")
        let full = try decodeRaw(EstateIndexResponse.self, "estate-full")
        let netWorth = try decode(EstateNetWorthResponse.self, "estate-net-worth")

        #expect(teaser.mode == .teaser)
        #expect(teaser.teaser?.estimatedLiability == Decimal(85000))
        #expect(full.mode == .full)
        #expect(full.data?.gifts.count == 1)
        #expect(full.data?.trusts.count == 1)
        #expect(full.data?.willInfo?.hasWill == true)
        #expect(netWorth.netWorth.netWorth == Decimal(650000))
        #expect(netWorth.netWorth.assetComposition.map(\.type) == ["property", "pension", "cash"])
    }

    @Test
    func clientOnlyLoadsNetWorthForTheFullResponse() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("estate-full")),
            .response(status: 200, body: try fixture("estate-net-worth")),
        ])
        let client = LiveEstateClient(apiClient: APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: EstateTokenProvider(),
            requestID: { "estate-request" }
        ))

        _ = try await client.loadIndex()
        _ = try await client.loadNetWorth()

        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/estate",
            "/fynla/api/estate/net-worth",
        ])
    }

    @Test @MainActor
    func modelLoadsAuthoritativeFullEstate() async throws {
        let index = try decodeRaw(EstateIndexResponse.self, "estate-full")
        let netWorth = try decode(EstateNetWorthResponse.self, "estate-net-worth")
        let model = EstateModel(client: EstateClientStub(index: index, netWorth: netWorth))

        await model.load()
        #expect(model.state == .loaded(EstateSnapshot(index: index, netWorth: netWorth.netWorth)))
        model.stop()
        #expect(model.state == .idle)
    }

    private func decode<Value: Decodable & Sendable>(
        _ type: Value.Type,
        _ name: String
    ) throws -> Value {
        try JSONDecoder().decode(
            APIEnvelope<Value>.self,
            from: try fixture(name)
        ).data
    }

    private func decodeRaw<Value: Decodable>(
        _ type: Value.Type,
        _ name: String
    ) throws -> Value {
        try JSONDecoder().decode(type, from: try fixture(name))
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/Estate/\(name).json")
        )
    }
}

private struct EstateTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "estate-token" }
}

private struct EstateClientStub: EstateClient {
    let index: EstateIndexResponse
    let netWorth: EstateNetWorthResponse
    func loadIndex() async throws -> EstateIndexResponse { index }
    func loadNetWorth() async throws -> EstateNetWorthResponse { netWorth }
}
