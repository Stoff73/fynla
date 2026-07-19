import Foundation
import Observation

@MainActor
@Observable
final class BalanceHistoryModel {
    private(set) var state: BalanceHistoryViewState = .idle
    private(set) var isExporting = false
    private(set) var exportURL: URL?
    private(set) var exportError: String?
    private let client: any BalanceHistoryClient
    private var lastHistory: BalanceHistory?
    private var generation = 0

    init(client: any BalanceHistoryClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        let previous = lastHistory
        if previous == nil {
            state = .loading
        }

        do {
            let history = try await client.load()
            guard activeGeneration == generation, !Task.isCancelled else { return }
            lastHistory = history
            state = .loaded(history)
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

    func prepareAdviserExport() async {
        guard !isExporting else { return }
        isExporting = true
        exportError = nil
        defer { isExporting = false }

        do {
            let data = try await client.downloadAdviserExport()
            try Task.checkCancellation()
            let url = FileManager.default.temporaryDirectory
                .appending(path: "fynla-adviser-export.pdf")
            try data.write(to: url, options: .atomic)
            exportURL = url
        } catch is CancellationError {
            return
        } catch {
            exportError = "We could not prepare your adviser export pack. Please try again."
        }
    }

    func stop() {
        generation &+= 1
        lastHistory = nil
        state = .idle
        isExporting = false
        exportError = nil
        exportURL = nil
    }

    private func map(_ error: APIError, previous: BalanceHistory?) {
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
