import Observation

@MainActor
@Observable
final class HolisticPlanModel {
    private(set) var state: HolisticPlanViewState = .idle
    private let client: any HolisticPlanClient
    private let clock: any HolisticPlanClock
    private let timeout: Duration
    private var lastPlan: HolisticPlan?
    private var generation = 0

    init(
        client: any HolisticPlanClient,
        clock: any HolisticPlanClock = ContinuousHolisticPlanClock(),
        timeout: Duration = .seconds(15)
    ) {
        self.client = client
        self.clock = clock
        self.timeout = timeout
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastPlan
        if previous == nil { state = .loading }

        do {
            let plan = try await loadBeforeDeadline()
            guard activeGeneration == generation, !Task.isCancelled else { return }
            lastPlan = plan
            state = .loaded(plan)
        } catch is CancellationError {
            guard activeGeneration == generation, let previous else { return }
            state = .loaded(previous)
        } catch let error as APIError {
            guard activeGeneration == generation, !Task.isCancelled else { return }
            map(error, previous: previous)
        } catch is HolisticPlanTimeoutError {
            guard activeGeneration == generation, !Task.isCancelled else { return }
            state = .timedOut
        } catch {
            guard activeGeneration == generation, !Task.isCancelled else { return }
            state = .failed(requestID: nil)
        }
    }

    private func loadBeforeDeadline() async throws -> HolisticPlan {
        let client = client
        let clock = clock
        let timeout = timeout

        return try await withThrowingTaskGroup(of: HolisticPlan.self) { group in
            group.addTask { try await client.load() }
            group.addTask {
                try await clock.sleep(for: timeout)
                throw HolisticPlanTimeoutError()
            }
            defer { group.cancelAll() }
            guard let result = try await group.next() else { throw CancellationError() }
            return result
        }
    }

    func refresh() async { await load() }

    func stop() {
        generation &+= 1
        lastPlan = nil
        state = .idle
    }

    private func map(_ error: APIError, previous: HolisticPlan?) {
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

private struct HolisticPlanTimeoutError: Error {}
