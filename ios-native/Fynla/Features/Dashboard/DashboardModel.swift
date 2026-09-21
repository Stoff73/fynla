import Observation

@MainActor
@Observable
final class DashboardModel {
    private(set) var state: DashboardViewState = .idle
    private(set) var completingActionIDs: Set<String> = []
    private(set) var actionMessage: String?
    private(set) var thresholds: ThresholdPosition?
    private let client: any DashboardClient
    private let thresholdClient: any ThresholdClient
    private var lastSnapshot: DashboardSnapshot?
    private var generation = 0

    init(client: any DashboardClient, thresholdClient: any ThresholdClient) {
        self.client = client
        self.thresholdClient = thresholdClient
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
            // Best effort: the threshold strip is an addition to the dashboard,
            // never a reason to blank it, and a failed refresh keeps the strip
            // that is already on screen rather than making it disappear.
            if let position = try? await thresholdClient.load() {
                guard activeGeneration == generation, !Task.isCancelled else { return }
                thresholds = position
            }
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
        thresholds = nil
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
             .nativeUpdateRequired,
             .rateLimited,
             .conflict:
            state = .failed(requestID: nil)
        }
    }
}
