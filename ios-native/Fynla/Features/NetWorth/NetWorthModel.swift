import Observation

@MainActor
@Observable
final class NetWorthModel {
    private(set) var state: NetWorthViewState = .idle
    private let client: any NetWorthClient
    private var lastSnapshot: NetWorthSnapshot?
    private var generation = 0

    init(client: any NetWorthClient) {
        self.client = client
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

    func stop() {
        generation &+= 1
        lastSnapshot = nil
        state = .idle
    }

    private func map(_ error: APIError, previous: NetWorthSnapshot?) {
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
