import Observation

@MainActor
@Observable
final class NetWorthModel {
    private(set) var state: NetWorthViewState = .idle
    private(set) var detailState: NetWorthDetailViewState = .idle
    private let client: any NetWorthClient
    private var lastSnapshot: NetWorthSnapshot?
    private var generation = 0
    private var detailGeneration = 0
    private var lastDetail: NetWorthCanonicalDetail?

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

    func loadProperty(id: Int) async {
        await loadDetail { .property(try await self.client.loadProperty(id: id).property) }
    }

    func loadMortgage(id: Int) async {
        await loadDetail { .mortgage(try await self.client.loadMortgage(id: id).mortgage) }
    }

    func loadLiability(id: Int) async {
        await loadDetail { .liability(try await self.client.loadLiability(id: id).liability) }
    }

    func stop() {
        generation &+= 1
        detailGeneration &+= 1
        lastSnapshot = nil
        lastDetail = nil
        state = .idle
        detailState = .idle
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
        case .validation, .forbidden, .nativeUpdateRequired, .rateLimited, .conflict:
            state = .failed(requestID: nil)
        }
    }

    private func loadDetail(
        operation: () async throws -> NetWorthCanonicalDetail
    ) async {
        detailGeneration &+= 1
        let activeGeneration = detailGeneration
        let previous = lastDetail
        detailState = .loading

        do {
            let detail = try await operation()
            guard activeGeneration == detailGeneration, !Task.isCancelled else { return }
            lastDetail = detail
            detailState = .loaded(detail)
        } catch is CancellationError {
            guard activeGeneration == detailGeneration, let previous else { return }
            detailState = .loaded(previous)
        } catch let error as APIError {
            guard activeGeneration == detailGeneration, !Task.isCancelled else { return }
            mapDetail(error, previous: previous)
        } catch {
            guard activeGeneration == detailGeneration, !Task.isCancelled else { return }
            detailState = .failed(requestID: nil)
        }
    }

    private func mapDetail(_ error: APIError, previous: NetWorthCanonicalDetail?) {
        switch error {
        case .offline:
            detailState = .offline(previous: previous)
        case .unauthenticated:
            detailState = .unauthenticated
        case let .upgradeRequired(message):
            detailState = .upgradeRequired(message: message)
        case let .server(_, requestID), let .decoding(requestID):
            detailState = .failed(requestID: requestID)
        case .validation, .forbidden, .nativeUpdateRequired, .rateLimited, .conflict:
            detailState = .failed(requestID: nil)
        }
    }
}
