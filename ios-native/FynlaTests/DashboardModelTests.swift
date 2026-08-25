import Foundation
import Testing
@testable import Fynla

@Suite("Dashboard model")
struct DashboardModelTests {
    @Test @MainActor
    func loadsAndRefreshesTheCanonicalSnapshot() async throws {
        let snapshot = try fixture("populated")
        let client = DashboardClientStub([.success(snapshot), .success(snapshot)])
        let model = DashboardModel(client: client)

        await model.load()
        #expect(model.state == .loaded(snapshot))
        await model.refresh()
        #expect(model.state == .loaded(snapshot))
        #expect(await client.loadCount() == 2)
    }

    @Test @MainActor
    func offlineRefreshKeepsThePreviouslyLoadedDashboard() async throws {
        let snapshot = try fixture("populated")
        let client = DashboardClientStub([
            .success(snapshot),
            .failure(APIError.offline),
        ])
        let model = DashboardModel(client: client)

        await model.load()
        await model.refresh()

        #expect(model.state == DashboardViewState.offline(previous: snapshot))
        #expect(model.snapshot == snapshot)
    }

    @Test @MainActor
    func mapsAuthenticationAndRequestFailuresToExplicitStates() async {
        let authModel = DashboardModel(
            client: DashboardClientStub([
                .failure(APIError.unauthenticated),
            ])
        )
        await authModel.load()
        #expect(authModel.state == DashboardViewState.unauthenticated)

        let failedModel = DashboardModel(
            client: DashboardClientStub([
                .failure(APIError.server(status: 503, requestID: "request-503")),
            ])
        )
        await failedModel.load()
        #expect(
            failedModel.state
                == DashboardViewState.failed(requestID: "request-503")
        )
    }

    @Test @MainActor
    func cancellationDoesNotReplaceLoadedContentWithAnError() async throws {
        let snapshot = try fixture("populated")
        let client = DashboardClientStub([
            .success(snapshot),
            .failure(CancellationError()),
        ])
        let model = DashboardModel(client: client)

        await model.load()
        await model.refresh()

        #expect(model.state == .loaded(snapshot))
    }

    @Test @MainActor
    func completingAnActionAcknowledgesTheBackendThenReloadsTheDashboard() async throws {
        let snapshot = try fixture("populated")
        let client = DashboardClientStub([.success(snapshot), .success(snapshot)])
        let model = DashboardModel(client: client)
        let action = try #require(snapshot.nextActions.first)

        await model.load()
        await model.complete(action)

        #expect(await client.completedActionIDs() == [action.id])
        #expect(await client.loadCount() == 2)
        #expect(model.actionMessage == nil)
    }

    @Test @MainActor
    func signOutClearsThePreviouslyLoadedFinancialSnapshot() async throws {
        let snapshot = try fixture("populated")
        let model = DashboardModel(
            client: DashboardClientStub([.success(snapshot)])
        )

        await model.load()
        model.stop()

        #expect(model.state == .idle)
        #expect(model.snapshot == nil)
    }

    private func fixture(_ name: String) throws -> DashboardSnapshot {
        let data = try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Dashboard/\(name).json")
        )
        return try JSONDecoder().decode(APIEnvelope<DashboardSnapshot>.self, from: data).data
    }
}

private actor DashboardClientStub: DashboardClient {
    private var results: [Result<DashboardSnapshot, Error>]
    private var count = 0
    private var completed: [String] = []

    init(_ results: [Result<DashboardSnapshot, Error>]) {
        self.results = results
    }

    func load() async throws -> DashboardSnapshot {
        count += 1
        guard !results.isEmpty else { throw APIError.server(status: 500, requestID: nil) }
        return try results.removeFirst().get()
    }

    func loadCount() -> Int { count }

    func markRecommendationDone(_ action: DashboardAction) async throws {
        completed.append(action.id)
    }

    func completedActionIDs() -> [String] { completed }
}
