import Foundation
import Testing
@testable import Fynla

@Suite("Savings feature")
struct SavingsTests {
    @Test
    func decodesAndGroupsTheExistingSavingsResponse() throws {
        let snapshot = try decode(SavingsSnapshot.self, "savings-populated")

        #expect(snapshot.accountCount == 2)
        #expect(snapshot.accountLimit == 2)
        #expect(snapshot.isAtAccountLimit)
        #expect(snapshot.bankAccounts.map(\.id) == [11])
        #expect(snapshot.cashISAs.map(\.id) == [12])
        #expect(snapshot.totalCash == Decimal(32500))
        #expect(snapshot.emergencyFundTarget.targetAmount == Decimal(18000))
        #expect(snapshot.isaAllowance?.remaining == Decimal(12000))
    }

    @Test
    func emptySavingsDoesNotInventAccountsOrAnAllowance() throws {
        let snapshot = try decode(SavingsSnapshot.self, "savings-empty")

        #expect(snapshot.accounts.isEmpty)
        #expect(snapshot.totalCash == 0)
        #expect(snapshot.accountLimit == nil)
        #expect(snapshot.isaAllowance == nil)
    }

    @Test
    func decodesExactJointAccountDetailFieldsReturnedByTheServer() throws {
        let account = try decode(SavingsAccount.self, "savings-account")

        #expect(account.id == 12)
        #expect(account.fullBalanceValue == Decimal(20000))
        #expect(account.userShare == Decimal(10000))
        #expect(account.ownershipPercentage == Decimal(50))
        #expect(account.maturityDate == "2027-07-18")
        #expect(account.isISA)
    }

    @Test
    func clientLoadsTheListAndExactAccountID() async throws {
        let transport = TestHTTPTransport([
            .response(status: 200, body: try fixture("savings-populated")),
            .response(status: 200, body: try fixture("savings-account")),
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
            tokenProvider: SavingsTokenProvider(),
            requestID: { "savings-request" }
        )
        let client = LiveSavingsClient(apiClient: apiClient)

        _ = try await client.load()
        let account = try await client.loadAccount(id: 12)

        #expect(account.id == 12)
        #expect((await transport.requests()).map(\.url?.path) == [
            "/fynla/api/savings",
            "/fynla/api/savings/accounts/12",
        ])
    }

    @Test @MainActor
    func modelLoadsSummaryAndExactDetailThenClears() async throws {
        let snapshot = try decode(SavingsSnapshot.self, "savings-populated")
        let account = try decode(SavingsAccount.self, "savings-account")
        let model = SavingsModel(
            client: SavingsClientStub(snapshot: snapshot, account: account)
        )

        await model.load()
        #expect(model.state == .loaded(snapshot))
        await model.loadAccount(id: 12)
        #expect(model.accountState == .loaded(account))

        model.stop()
        #expect(model.state == .idle)
        #expect(model.accountState == .idle)
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
                .appending(path: "Fixtures/Financial/Savings/\(name).json")
        )
    }
}

private struct SavingsTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "savings-token" }
}

private struct SavingsClientStub: SavingsClient {
    let snapshot: SavingsSnapshot
    let account: SavingsAccount

    func load() async throws -> SavingsSnapshot { snapshot }
    func loadAccount(id: Int) async throws -> SavingsAccount { account }
}
