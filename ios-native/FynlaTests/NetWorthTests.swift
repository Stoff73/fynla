import Foundation
import Testing
@testable import Fynla

@Suite("Net worth feature")
struct NetWorthTests {
    @Test
    func decodesTheExistingOverviewAndDetailedShapes() throws {
        let overview = try decode(
            NetWorthOverview.self,
            "net-worth-overview-populated"
        )
        let detailed = try decode(
            NetWorthDetailedAssets.self,
            "net-worth-detailed-populated"
        )

        #expect(overview.netWorth == Decimal(440000))
        #expect(overview.hasDBPensions)
        #expect(overview.liabilitiesBreakdown.creditCards == Decimal(3000))
        #expect(detailed.property.items.first?.ownershipType == "joint")
        #expect(detailed.business.items.first?.ownershipPercentage == Decimal(50))
        #expect(detailed.pensions.items.last?.annualPension == Decimal(20000))
    }

    @Test
    func snapshotUsesServerCategoryTotalsAndRejectsUnknownCategories() throws {
        let snapshot = try populatedSnapshot()

        #expect(snapshot.assetCategories.map(\.id) == [
            .property, .investments, .pensions, .cash, .business, .chattels,
        ])
        #expect(snapshot.section(for: .business)?.totalValue == Decimal(60000))
        #expect(snapshot.liabilityRows.map(\.title) == [
            "Mortgages", "Loans", "Credit cards",
        ])
        #expect(NetWorthCategory(rawValue: "unknown") == nil)
    }

    @Test
    func emptySnapshotKeepsZeroServerTotalsAndNoInventedRows() throws {
        let snapshot = NetWorthSnapshot(
            overview: try decode(
                NetWorthOverview.self,
                "net-worth-overview-empty"
            ),
            detailed: try decode(
                NetWorthDetailedAssets.self,
                "net-worth-detailed-empty"
            )
        )

        #expect(snapshot.overview.netWorth == 0)
        #expect(snapshot.assetCategories.isEmpty)
        #expect(snapshot.liabilityRows.isEmpty)
    }

    @Test
    func clientLoadsBothExistingAuthenticatedEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                body: try fixture("net-worth-overview-populated")
            ),
            .response(
                status: 200,
                body: try fixture("net-worth-detailed-populated")
            ),
        ])
        let client = APIClient(
            environment: try AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: NetWorthTokenProvider(),
            requestID: { "net-worth-request" }
        )

        let snapshot = try await LiveNetWorthClient(apiClient: client).load()

        #expect(snapshot.overview.totalAssets == Decimal(650000))
        let requests = await transport.requests()
        #expect(Set(requests.compactMap(\.url?.path)) == [
            "/fynla/api/net-worth/overview",
            "/fynla/api/net-worth/assets-summary-detailed",
        ])
        #expect(requests.allSatisfy {
            $0.value(forHTTPHeaderField: "Authorization") == "Bearer net-worth-token"
        })
    }

    @Test @MainActor
    func modelLoadsRefreshesAndMapsOfflineState() async throws {
        let snapshot = try populatedSnapshot()
        let client = NetWorthClientStub([
            .success(snapshot),
            .failure(APIError.offline),
        ])
        let model = NetWorthModel(client: client)

        await model.load()
        #expect(model.state == .loaded(snapshot))
        await model.refresh()
        #expect(model.state == .offline(previous: snapshot))

        model.stop()
        #expect(model.state == .idle)
    }

    private func populatedSnapshot() throws -> NetWorthSnapshot {
        NetWorthSnapshot(
            overview: try decode(
                NetWorthOverview.self,
                "net-worth-overview-populated"
            ),
            detailed: try decode(
                NetWorthDetailedAssets.self,
                "net-worth-detailed-populated"
            )
        )
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
                .appending(path: "Fixtures/Financial/NetWorth/\(name).json")
        )
    }
}

private struct NetWorthTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "net-worth-token" }
}

private actor NetWorthClientStub: NetWorthClient {
    private var results: [Result<NetWorthSnapshot, Error>]

    init(_ results: [Result<NetWorthSnapshot, Error>]) {
        self.results = results
    }

    func load() async throws -> NetWorthSnapshot {
        guard !results.isEmpty else {
            throw APIError.server(status: 500, requestID: nil)
        }
        return try results.removeFirst().get()
    }
}
