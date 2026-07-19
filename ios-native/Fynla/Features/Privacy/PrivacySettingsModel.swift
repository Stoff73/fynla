import Observation

@MainActor
@Observable
final class PrivacySettingsModel {
    private(set) var state: PrivacySettingsState = .idle
    private(set) var updatingType: String?
    private(set) var updateFailed = false
    private(set) var history: [ConsentHistoryEntry] = []
    private let client: any PrivacyClient
    private var lastSnapshot: ConsentSnapshot?
    private var generation = 0

    init(client: any PrivacyClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let active = generation
        let previous = lastSnapshot
        if previous == nil { state = .loading }
        do {
            async let historyResult = try? client.loadConsentHistory()
            let snapshot = try await client.loadConsents()
            let loadedHistory = await historyResult
            guard active == generation, !Task.isCancelled else { return }
            lastSnapshot = snapshot
            history = loadedHistory?.history ?? []
            state = .loaded(snapshot)
        } catch let error as APIError {
            guard active == generation, !Task.isCancelled else { return }
            switch error {
            case .offline: state = .offline(previous: previous)
            case .unauthenticated: state = .unauthenticated
            case let .server(_, requestID), let .decoding(requestID):
                state = .failed(requestID: requestID)
            default: state = .failed(requestID: nil)
            }
        } catch {
            guard active == generation, !Task.isCancelled else { return }
            state = .failed(requestID: nil)
        }
    }

    func setConsent(type: String, consented: Bool) async {
        guard updatingType == nil else { return }
        updatingType = type
        updateFailed = false
        history = []
        defer { updatingType = nil }
        do {
            let snapshot = try await client.updateConsents([type: consented])
            lastSnapshot = snapshot
            state = .loaded(snapshot)
        } catch {
            updateFailed = true
        }
    }

    func stop() {
        generation &+= 1
        lastSnapshot = nil
        state = .idle
        updatingType = nil
        updateFailed = false
    }
}
