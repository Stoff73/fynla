import Observation

@MainActor
@Observable
final class TaxStrategyModel {
    private(set) var state: TaxStrategyViewState = .idle
    private(set) var markingRecommendationID: String?
    private(set) var completionFailed = false
    private let client: any TaxStrategyClient
    private var lastDashboard: TaxStrategyDashboard?
    private var generation = 0

    init(client: any TaxStrategyClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastDashboard
        if previous == nil { state = .loading }

        do {
            let dashboard = try await client.load()
            guard activeGeneration == generation, !Task.isCancelled else { return }
            lastDashboard = dashboard
            state = .loaded(dashboard)
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

    func markDone(_ recommendation: TaxRecommendation) async {
        guard recommendation.recommendationID != nil,
              markingRecommendationID == nil
        else {
            return
        }
        completionFailed = false
        markingRecommendationID = recommendation.recommendationID
        defer { markingRecommendationID = nil }
        do {
            try await client.markDone(recommendation)
            await load()
        } catch {
            completionFailed = true
        }
    }

    func stop() {
        generation &+= 1
        lastDashboard = nil
        markingRecommendationID = nil
        completionFailed = false
        state = .idle
    }

    private func map(_ error: APIError, previous: TaxStrategyDashboard?) {
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
