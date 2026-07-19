import Foundation
import Observation

@MainActor
@Observable
final class DataExportModel {
    private(set) var state: DataExportState = .idle
    private(set) var shareURL: URL?
    private let client: any PrivacyClient
    private let store: TemporaryExportStore
    private let maximumPollAttempts: Int
    private let wait: @Sendable (Duration) async -> Void
    private var generation = 0

    init(
        client: any PrivacyClient,
        store: TemporaryExportStore,
        maximumPollAttempts: Int = 6,
        wait: @escaping @Sendable (Duration) async -> Void = {
            try? await Task.sleep(for: $0)
        }
    ) {
        self.client = client
        self.store = store
        self.maximumPollAttempts = maximumPollAttempts
        self.wait = wait
    }

    func requestExport() async {
        generation &+= 1
        let active = generation
        state = .requesting
        do {
            try? await store.cleanupExpired()
            var status = try await client.requestExport(format: "json")
            for attempt in 0..<maximumPollAttempts {
                try Task.checkCancellation()
                guard active == generation else { return }
                if status.status == "expired" {
                    state = .expired
                    return
                }
                if status.status == "completed", status.isDownloadable != false {
                    let data = try await client.downloadExport(id: status.exportID)
                    guard active == generation else { return }
                    let url = try await store.save(data, format: status.format)
                    shareURL = url
                    state = .ready(status)
                    return
                }
                state = .processing(status)
                guard attempt + 1 < maximumPollAttempts else { return }
                let seconds = min(8, 1 << min(attempt, 3))
                await wait(.seconds(seconds))
                status = try await client.exportStatus()
            }
        } catch is CancellationError {
            return
        } catch APIError.rateLimited {
            state = .rateLimited
        } catch {
            state = .failed
        }
    }

    func shareDismissed() async {
        guard let shareURL else { return }
        try? await store.delete(shareURL)
        self.shareURL = nil
        state = .idle
    }

    func stop() async {
        generation &+= 1
        await shareDismissed()
        state = .idle
    }
}
