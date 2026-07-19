import Observation

@MainActor
@Observable
final class RetirementModel {
    private(set) var state: RetirementViewState = .idle
    private(set) var dcProjection: RetirementPotProjection?
    private(set) var isLoadingDCProjection = false
    private let client: any RetirementClient
    private var lastSnapshot: RetirementSnapshot?
    private var generation = 0
    private var projectionGeneration = 0

    init(client: any RetirementClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastSnapshot
        if previous == nil { state = .loading }

        do {
            async let analysis = try? client.analyze()
            async let projections = try? client.loadProjections()
            let index = try await client.loadIndex()
            let snapshot = await RetirementSnapshot(
                index: index,
                analysis: analysis,
                projections: projections
            )
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

    func loadDCPensionProjection(id: Int) async {
        projectionGeneration &+= 1
        let activeGeneration = projectionGeneration
        dcProjection = nil
        isLoadingDCProjection = true
        defer {
            if activeGeneration == projectionGeneration {
                isLoadingDCProjection = false
            }
        }
        do {
            let projection = try await client.loadDCPensionProjection(id: id)
            guard activeGeneration == projectionGeneration, !Task.isCancelled else { return }
            dcProjection = projection
        } catch {
            return
        }
    }

    func dcPension(id: Int) -> DCPension? {
        lastSnapshot?.index.dcPensions.first { $0.id == id }
    }

    func dbPension(id: Int) -> DBPension? {
        lastSnapshot?.index.dbPensions.first { $0.id == id }
    }

    var statePension: StatePension? { lastSnapshot?.index.statePension }

    func stop() {
        generation &+= 1
        projectionGeneration &+= 1
        lastSnapshot = nil
        dcProjection = nil
        isLoadingDCProjection = false
        state = .idle
    }

    private func map(_ error: APIError, previous: RetirementSnapshot?) {
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
