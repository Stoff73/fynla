import Foundation
import Testing
@testable import Fynla

@Suite("Balance history feature")
struct BalanceHistoryTests {
    @Test
    func decodesTheExistingRawHistoryResponse() throws {
        let response = try JSONDecoder().decode(
            BalanceHistoryResponse.self,
            from: try fixture("balance-history-populated")
        )

        #expect(response.data.visibility.label == "Full available history")
        #expect(response.data.latest?.netWorth == Decimal(440000))
        #expect(response.data.yearOnYear?.percentageChange == Decimal(string: "22.22"))
    }

    @Test
    func freeEmptyHistoryKeepsTheCanonicalNinetyDayVisibility() throws {
        let response = try JSONDecoder().decode(
            BalanceHistoryResponse.self,
            from: try fixture("balance-history-empty-free")
        )

        #expect(response.data.visibility.windowDays == 90)
        #expect(response.data.totals.isEmpty)
        #expect(response.data.yearOnYear == nil)
    }

    @Test
    func clientLoadsHistoryAndDownloadsTheExistingAdviserPDF() async throws {
        let pdf = Data("%PDF-1.7 test".utf8)
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                body: try fixture("balance-history-populated")
            ),
            .response(status: 200, body: pdf),
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
            tokenProvider: BalanceHistoryTokenProvider(),
            requestID: { "history-request" }
        )
        let client = LiveBalanceHistoryClient(apiClient: apiClient)

        let history = try await client.load()
        let downloaded = try await client.downloadAdviserExport()

        #expect(history.latest?.netWorth == Decimal(440000))
        #expect(downloaded == pdf)
        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/balance-history",
            "/fynla/api/plans/adviser-export-pack",
        ])
        #expect(requests.last?.value(forHTTPHeaderField: "Accept") == "application/pdf")
    }

    @Test @MainActor
    func modelLoadsAndClearsHistory() async throws {
        let history = try JSONDecoder().decode(
            BalanceHistoryResponse.self,
            from: try fixture("balance-history-populated")
        ).data
        let model = BalanceHistoryModel(
            client: BalanceHistoryClientStub(history: history)
        )

        await model.load()
        #expect(model.state == .loaded(history))
        model.stop()
        #expect(model.state == .idle)
    }

    private func fixture(_ name: String) throws -> Data {
        try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Financial/NetWorth/\(name).json")
        )
    }
}

private struct BalanceHistoryTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "history-token" }
}

private struct BalanceHistoryClientStub: BalanceHistoryClient {
    let history: BalanceHistory

    func load() async throws -> BalanceHistory { history }
    func downloadAdviserExport() async throws -> Data { Data() }
}
