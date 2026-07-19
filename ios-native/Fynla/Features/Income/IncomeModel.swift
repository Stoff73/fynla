import Observation

@MainActor
@Observable
final class IncomeModel {
    private(set) var state: IncomeViewState = .idle
    private let client: any IncomeClient
    private var lastSummary: IncomeSummary?
    private var generation = 0

    init(client: any IncomeClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastSummary
        if previous == nil {
            state = .loading
        }

        do {
            let profile = try await client.load()
            guard activeGeneration == generation, !Task.isCancelled else { return }
            lastSummary = profile.incomeSummary
            state = .loaded(profile.incomeSummary)
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
        lastSummary = nil
        state = .idle
    }

    private func map(_ error: APIError, previous: IncomeSummary?) {
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
