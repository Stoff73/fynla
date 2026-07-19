import Observation

@MainActor
@Observable
final class SavingsModel {
    private(set) var state: SavingsViewState = .idle
    private(set) var accountState: SavingsAccountViewState = .idle
    private let client: any SavingsClient
    private var lastSnapshot: SavingsSnapshot?
    private var summaryGeneration = 0
    private var accountGeneration = 0

    init(client: any SavingsClient) {
        self.client = client
    }

    func load() async {
        summaryGeneration &+= 1
        let activeGeneration = summaryGeneration
        let previous = lastSnapshot
        if previous == nil { state = .loading }

        do {
            let snapshot = try await client.load()
            guard activeGeneration == summaryGeneration, !Task.isCancelled else { return }
            lastSnapshot = snapshot
            state = .loaded(snapshot)
        } catch is CancellationError {
            guard activeGeneration == summaryGeneration, let previous else { return }
            state = .loaded(previous)
        } catch let error as APIError {
            guard activeGeneration == summaryGeneration, !Task.isCancelled else { return }
            mapSummary(error, previous: previous)
        } catch {
            guard activeGeneration == summaryGeneration, !Task.isCancelled else { return }
            state = .failed(requestID: nil)
        }
    }

    func refresh() async { await load() }

    func loadAccount(id: Int) async {
        accountGeneration &+= 1
        let activeGeneration = accountGeneration
        accountState = .loading

        do {
            let account = try await client.loadAccount(id: id)
            guard activeGeneration == accountGeneration, !Task.isCancelled else { return }
            accountState = .loaded(account)
        } catch is CancellationError {
            return
        } catch let error as APIError {
            guard activeGeneration == accountGeneration, !Task.isCancelled else { return }
            mapAccount(error)
        } catch {
            guard activeGeneration == accountGeneration, !Task.isCancelled else { return }
            accountState = .failed(requestID: nil)
        }
    }

    func stop() {
        summaryGeneration &+= 1
        accountGeneration &+= 1
        lastSnapshot = nil
        state = .idle
        accountState = .idle
    }

    private func mapSummary(_ error: APIError, previous: SavingsSnapshot?) {
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

    private func mapAccount(_ error: APIError) {
        switch error {
        case .offline:
            accountState = .offline
        case .unauthenticated:
            accountState = .unauthenticated
        case .forbidden, .upgradeRequired:
            accountState = .forbidden
        case let .server(status, requestID):
            accountState = status == 404 ? .notFound : .failed(requestID: requestID)
        case let .decoding(requestID):
            accountState = .failed(requestID: requestID)
        case .validation, .rateLimited, .conflict:
            accountState = .failed(requestID: nil)
        }
    }
}
