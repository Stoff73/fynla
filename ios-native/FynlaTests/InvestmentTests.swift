import Foundation
import Testing
@testable import Fynla

@Suite("Investment feature")
struct InvestmentTests {
    @Test
    func decodesTheExistingInvestmentResponse() throws {
        let snapshot = try decode("investment-populated")

        #expect(snapshot.totalValue == Decimal(60000))
        #expect(snapshot.accountCount == 2)
        #expect(snapshot.isAtAccountLimit)
        #expect(snapshot.riskLabel == "Moderately Adventurous")
        #expect(snapshot.accounts[0].isISA)
        #expect(snapshot.accounts[0].ownerName == "Alex Example")
        #expect(snapshot.accounts[0].holdings.count == 2)
        #expect(snapshot.accounts[0].holdings[0].gainLoss == Decimal(6000))
        #expect(snapshot.accounts[0].portfolio?.contractVersion == "financial_portfolio_v1")
        #expect(snapshot.accounts[0].portfolio?.holdings[0].wrapperPercentage == Decimal(75))
        #expect(snapshot.accounts[0].portfolio?.holdings[0].wholeRelevantPortfolioPercentage == Decimal(60))
        #expect(snapshot.accounts[0].portfolio?.holdings[0].classifiedExposure.first?.holdingPercentage == Decimal(60))
        #expect(snapshot.accounts[0].portfolio?.holdings[1].fees.available == false)
        #expect(snapshot.accounts[0].portfolio?.holdings[0].performance.gainLoss == Decimal(6000))
        #expect(snapshot.accounts[0].portfolio?.analysis.coveragePercent == Decimal(90))
        #expect(snapshot.accounts[0].portfolio?.analysis.unclassifiedValue == Decimal(4800))
        #expect(snapshot.accounts[0].portfolio?.analysis.comparisons.entered?.driftPercentagePoints?["equities"] == Decimal(5))
        #expect(snapshot.accounts[0].portfolio?.analysis.comparisons.recommended?.driftPercentagePoints?["equities"] == Decimal(10))
        #expect(snapshot.accounts[0].portfolio?.performanceHistory.method == "recorded_value_snapshots")
    }

    @Test
    func emptyResponseDoesNotInventAccountsOrRisk() throws {
        let snapshot = try decode("investment-empty")

        #expect(snapshot.accounts.isEmpty)
        #expect(snapshot.totalValue == 0)
        #expect(snapshot.accountLimit == nil)
        #expect(snapshot.riskLabel == nil)
    }

    @Test
    func clientUsesTheCurrentInvestmentEndpoint() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("investment-populated")),
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
            tokenProvider: InvestmentTokenProvider(),
            requestID: { "investment-request" }
        )

        _ = try await LiveInvestmentClient(apiClient: apiClient).load()

        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/investment",
        ])
    }

    @Test @MainActor
    func modelLoadsAndFindsTheExactAccountID() async throws {
        let snapshot = try decode("investment-populated")
        let model = InvestmentModel(client: InvestmentClientStub(snapshot: snapshot))

        await model.load()

        #expect(model.state == .loaded(snapshot))
        #expect(model.account(id: 21)?.provider == "Example Investments")
        #expect(model.account(id: 999) == nil)
        model.stop()
        #expect(model.state == .idle)
    }

    private func decode(_ name: String) throws -> InvestmentSnapshot {
        try JSONDecoder().decode(
            APIEnvelope<InvestmentSnapshot>.self,
            from: try fixture(name)
        ).data
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/Investment/\(name).json")
        )
    }
}

private struct InvestmentTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "investment-token" }
}

private struct InvestmentClientStub: InvestmentClient {
    let snapshot: InvestmentSnapshot
    func load() async throws -> InvestmentSnapshot { snapshot }
}
