import Foundation
import Testing
@testable import Fynla

@Suite("Protection feature")
struct ProtectionTests {
    @Test
    func decodesPoliciesAndServerCalculatedGaps() throws {
        let index = try decode(ProtectionIndex.self, "protection-index")
        let analysis = try decode(ProtectionAnalysis.self, "protection-analysis")
        let snapshot = ProtectionSnapshot(index: index, analysis: analysis)

        #expect(snapshot.policies.count == 2)
        #expect(snapshot.policies.map(\.type) == [.life, .incomeProtection])
        #expect(snapshot.totalLumpSumCover == Decimal(250000))
        #expect(snapshot.annualIncomeCover == Decimal(30000))
        #expect(snapshot.openGaps.map(\.id) == ["human-capital", "final-expenses", "income-protection"])
        #expect(snapshot.policy(type: .life, id: 41)?.provider == "Example Life")
        #expect(snapshot.policy(type: .life, id: 999) == nil)
    }

    @Test
    func clientUsesTheExistingIndexAndAnalysisEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("protection-index")),
            .response(status: 200, body: try fixture("protection-analysis")),
        ])
        let client = LiveProtectionClient(apiClient: APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: ProtectionTokenProvider(),
            requestID: { "protection-request" }
        ))

        _ = try await client.loadIndex()
        _ = try await client.analyze()

        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/protection",
            "/fynla/api/protection/analyze",
        ])
    }

    @Test @MainActor
    func modelLoadsAndClears() async throws {
        let index = try decode(ProtectionIndex.self, "protection-index")
        let analysis = try decode(ProtectionAnalysis.self, "protection-analysis")
        let snapshot = ProtectionSnapshot(index: index, analysis: analysis)
        let model = ProtectionModel(client: ProtectionClientStub(index: index, analysis: analysis))

        await model.load()
        #expect(model.state == .loaded(snapshot))
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

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/Protection/\(name).json")
        )
    }
}

private struct ProtectionTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "protection-token" }
}

private struct ProtectionClientStub: ProtectionClient {
    let index: ProtectionIndex
    let analysis: ProtectionAnalysis
    func loadIndex() async throws -> ProtectionIndex { index }
    func analyze() async throws -> ProtectionAnalysis { analysis }
}
