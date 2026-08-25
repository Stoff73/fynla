import Observation

@MainActor
@Observable
final class GoalsModel {
    private(set) var state: GoalsViewState = .idle
    private(set) var detailState: GoalDetailViewState = .idle
    private let client: any GoalsClient
    private var lastSnapshot: GoalsSnapshot?
    private var generation = 0
    private var detailGeneration = 0
    private var lastDetail: GoalDetailResponse?

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

    func loadGoal(id: Int) async {
        detailGeneration &+= 1
        let activeGeneration = detailGeneration
        let previous = lastDetail?.goal.id == id ? lastDetail : nil
        if previous == nil { detailState = .loading }

        do {
            let detail = try await client.loadGoal(id: id)
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

    func stop() {
        generation &+= 1
        detailGeneration &+= 1
        lastSnapshot = nil
        lastDetail = nil
        state = .idle
        detailState = .idle
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
        case .validation, .forbidden, .nativeUpdateRequired, .rateLimited, .conflict:
            state = .failed(requestID: nil)
        }
    }

    private func mapDetail(_ error: APIError, previous: GoalDetailResponse?) {
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
