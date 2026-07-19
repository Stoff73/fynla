import Observation

@MainActor
@Observable
final class GoalsModel {
    private(set) var state: GoalsViewState = .idle
    private let client: any GoalsClient
    private var lastSnapshot: GoalsSnapshot?
    private var generation = 0

    init(client: any GoalsClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastSnapshot
        if previous == nil { state = .loading }

        do {
            async let overview = try? client.loadOverview()
            let list = try await client.loadGoals()
            let snapshot = await GoalsSnapshot(list: list, overview: overview)
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

    func refresh() async { await load() }

    func stop() {
        generation &+= 1
        lastSnapshot = nil
        state = .idle
    }

    private func map(_ error: APIError, previous: GoalsSnapshot?) {
        switch error {
        case .offline:
            state = .offline(previous: previous)
        case .unauthenticated:
            state = .unauthenticated
        case let .upgradeRequired(message):
            state = .upgradeRequired(message: message)
        case let .server(_, requestID), let .decoding(requestID):
            state = .failed(requestID: requestID)
        case .validation, .forbidden, .rateLimited, .conflict:
            state = .failed(requestID: nil)
        }
    }
}
