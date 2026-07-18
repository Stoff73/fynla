import Observation

@MainActor
@Observable
final class DashboardModel {
    private(set) var state: DashboardViewState = .idle
    private(set) var completingActionIDs: Set<String> = []
    private(set) var actionMessage: String?
    private let client: any DashboardClient
    private var lastSnapshot: DashboardSnapshot?
    private var generation = 0

    init(client: any DashboardClient) {
        self.client = client
    }

    var snapshot: DashboardSnapshot? {
        switch state {
        case let .loaded(snapshot): snapshot
        case let .offline(previous): previous
        case .idle, .loading, .unauthenticated, .failed: lastSnapshot
        }
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastSnapshot
        if previous == nil {
            state = .loading
        }

        do {
            let snapshot = try await client.load()
            guard activeGeneration == generation, !Task.isCancelled else { return }
            lastSnapshot = snapshot
            state = .loaded(snapshot)
        } catch is CancellationError {
            guard activeGeneration == generation, let previous else { return }
            state = .loaded(previous)
        } catch let error as APIError {
            guard activeGeneration == generation, !Task.isCancelled else { return }
            map(error, previous: previous)
        } catch {
            guard activeGeneration == generation, !Task.isCancelled else { return }
            state = .failed(requestID: nil)
        }
    }

    func refresh() async {
        await load()
    }

    func complete(_ action: DashboardAction) async {
        guard action.type == .recommendation,
              !completingActionIDs.contains(action.id)
        else { return }

        completingActionIDs.insert(action.id)
        actionMessage = nil
        defer { completingActionIDs.remove(action.id) }

        do {
            try await client.markRecommendationDone(action)
            await refresh()
        } catch is CancellationError {
            return
        } catch {
            actionMessage = "We could not mark that action complete. Please try again."
        }
    }

    func stop() {
        generation &+= 1
        lastSnapshot = nil
        state = .idle
        completingActionIDs = []
        actionMessage = nil
    }

    private func map(_ error: APIError, previous: DashboardSnapshot?) {
        switch error {
        case .offline:
            state = .offline(previous: previous)
        case .unauthenticated:
            state = .unauthenticated
        case let .server(_, requestID), let .decoding(requestID):
            state = .failed(requestID: requestID)
        case .validation,
             .forbidden,
             .upgradeRequired,
             .rateLimited,
             .conflict:
            state = .failed(requestID: nil)
        }
    }
}
