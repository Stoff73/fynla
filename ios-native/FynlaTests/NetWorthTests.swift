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
        #expect(detailed.property.items.first?.outstandingMortgage == Decimal(180000))
        #expect(detailed.liabilities?.items.map(\.detailRoute) == [
            .mortgageDetail(id: 91),
            .liabilityDetail(id: 92),
        ])
    }

    @Test
    func decodesCanonicalPropertyMortgageAndLiabilityDetails() throws {
        let property = try decode(PropertyDetailResponse.self, "property-detail").property
        let mortgage = try decode(MortgageDetailResponse.self, "mortgage-detail").mortgage
        let liability = try decode(LiabilityDetailResponse.self, "liability-detail").liability

        #expect(property.outstandingMortgage == Decimal(180000))
        #expect(property.mortgages?.first?.detailRoute == .mortgageDetail(id: 91))
        #expect(mortgage.property?.detailRoute == .propertyDetail(id: 41))
        #expect(mortgage.remainingTermMonths == 240)
        #expect(liability.monthlyPayment == Decimal(350))
        #expect(liability.interestRate == Decimal(string: "5.9"))
    }

    @Test
    func snapshotUsesServerCategoryTotalsAndRejectsUnknownCategories() throws {
        let snapshot = try populatedSnapshot()

        #expect(snapshot.assetCategories.map(\.id) == [
            .property, .investments, .pensions, .cash, .business, .chattels,
        ])
        #expect(snapshot.assetCategories.last?.title == "Valuables")
        #expect(NetWorthCategory.chattels.rawValue == "chattels")
        #expect(NetWorthCategory.chattels.title == "Valuables")
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
            .response(status: 200, body: try fixture("property-detail")),
            .response(status: 200, body: try fixture("mortgage-detail")),
            .response(status: 200, body: try fixture("liability-detail")),
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

        let live = LiveNetWorthClient(apiClient: client)
        let snapshot = try await live.load()
        _ = try await live.loadProperty(id: 41)
        _ = try await live.loadMortgage(id: 91)
        _ = try await live.loadLiability(id: 92)

        #expect(snapshot.overview.totalAssets == Decimal(650000))
        let requests = await transport.requests()
        #expect(Set(requests.compactMap(\.url?.path)) == [
            "/fynla/api/net-worth/overview",
            "/fynla/api/net-worth/assets-summary-detailed",
            "/fynla/api/properties/41",
            "/fynla/api/mortgages/91",
            "/fynla/api/estate/liabilities/92",
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
