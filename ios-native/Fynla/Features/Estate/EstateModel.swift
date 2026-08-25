import Observation

@MainActor
@Observable
final class EstateModel {
    private(set) var state: EstateViewState = .idle
    private let client: any EstateClient
    private var lastSnapshot: EstateSnapshot?
    private var generation = 0

    init(client: any EstateClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastSnapshot
        if previous == nil { state = .loading }

        do {
            let index = try await client.loadIndex()
            let netWorth = index.mode == .full
                ? (try? await client.loadNetWorth())?.netWorth
                : nil
            let snapshot = EstateSnapshot(index: index, netWorth: netWorth)
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

    private func map(_ error: APIError, previous: EstateSnapshot?) {
        switch error {
        case .offline:
            state = .offline(previous: previous)
        case .unauthenticated:
            state = .unauthenticated
        case let .upgradeRequired(message):
            state = .upgradeRequired(message: message)
        case let .server(_, requestID), let .decoding(requestID):
            state = .failed(requestID: requestID)
        case .validation, .forbidden, .nativeUpdateRequired, .rateLimited, .conflict:
            state = .failed(requestID: nil)
        }
    }
}
