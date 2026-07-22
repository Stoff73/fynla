import Foundation
import Testing
@testable import Fynla

@Suite("Retirement feature")
struct RetirementTests {
    @Test
    func decodesTheCurrentRetirementResponses() throws {
        let index = try decode(RetirementIndex.self, "retirement-index")
        let analysis = try decode(RetirementAnalysis.self, "retirement-analysis")
        let projections = try decode(RetirementProjections.self, "retirement-projections")
        let snapshot = RetirementSnapshot(
            index: index,
            analysis: analysis,
            projections: projections
        )

        #expect(snapshot.pensions.count == 3)
        #expect(snapshot.totalDCPensionWealth == Decimal(180000))
        #expect(snapshot.projectedIncome == Decimal(38000))
        #expect(snapshot.targetIncome == Decimal(42000))
        #expect(snapshot.incomeGap == Decimal(4000))
        #expect(snapshot.isAtAccountLimit)
        #expect(index.dcPensions[0].monthlyContribution == Decimal(500))
    }

    @Test
    func clientUsesAllExistingRetirementEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("retirement-index")),
            .response(status: 200, body: try fixture("retirement-analysis")),
            .response(status: 200, body: try fixture("retirement-projections")),
            .response(status: 200, body: try fixture("retirement-dc-projection")),
        ])
        let client = LiveRetirementClient(apiClient: APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: RetirementTokenProvider(),
            requestID: { "retirement-request" }
        ))

        _ = try await client.loadIndex()
        _ = try await client.analyze()
        _ = try await client.loadProjections()
        _ = try await client.loadDCPensionProjection(id: 31)

        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/retirement",
            "/fynla/api/retirement/analyze",
            "/fynla/api/retirement/projections",
            "/fynla/api/retirement/dc-pensions/31/projections",
        ])
    }

    @Test @MainActor
    func modelLoadsTheModuleAndExactDCPensionProjection() async throws {
        let index = try decode(RetirementIndex.self, "retirement-index")
        let analysis = try decode(RetirementAnalysis.self, "retirement-analysis")
        let projections = try decode(RetirementProjections.self, "retirement-projections")
        let dcProjection = try decode(RetirementPotProjection.self, "retirement-dc-projection")
        let model = RetirementModel(client: RetirementClientStub(
            index: index,
            analysis: analysis,
            projections: projections,
            dcProjection: dcProjection
        ))

        await model.load()
        #expect(model.state == .loaded(RetirementSnapshot(
            index: index,
            analysis: analysis,
            projections: projections
        )))
        await model.loadDCPensionProjection(id: 31)
        #expect(model.dcProjection == dcProjection)
        model.stop()
        #expect(model.state == .idle)
        #expect(model.dcProjection == nil)
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

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/Retirement/\(name).json")
        )
    }
}

private struct RetirementTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "retirement-token" }
}

private struct RetirementClientStub: RetirementClient {
    let index: RetirementIndex
    let analysis: RetirementAnalysis
    let projections: RetirementProjections
    let dcProjection: RetirementPotProjection

    func loadIndex() async throws -> RetirementIndex { index }
    func analyze() async throws -> RetirementAnalysis { analysis }
    func loadProjections() async throws -> RetirementProjections { projections }
    func loadDCPensionProjection(id: Int) async throws -> RetirementPotProjection { dcProjection }
}
