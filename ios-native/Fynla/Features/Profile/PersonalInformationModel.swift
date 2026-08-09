import Observation

@MainActor
@Observable
final class PersonalInformationModel {
    nonisolated static let supportsEditing = false

    private(set) var state: PersonalInformationViewState = .idle
    private let client: any PersonalInformationClient
    private var lastProfile: PersonalInformationProfile?
    private var generation = 0

    init(client: any PersonalInformationClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastProfile
        if previous == nil {
            state = .loading
        }

        do {
            let profile = try await client.load()
            guard activeGeneration == generation, !Task.isCancelled else { return }
            lastProfile = profile
            state = .loaded(profile)
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
        lastProfile = nil
        state = .idle
    }

    private func map(
        _ error: APIError,
        previous: PersonalInformationProfile?
    ) {
        switch error {
        case .offline:
            state = .offline(previous: previous)
        case .unauthenticated:
            state = .unauthenticated
        case let .server(_, requestID), let .decoding(requestID):
            state = .failed(requestID: requestID)
        case .validation, .forbidden, .nativeUpdateRequired,
             .rateLimited, .conflict, .upgradeRequired:
            state = .failed(requestID: nil)
        }
    }
}
