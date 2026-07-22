import Observation

@MainActor
@Observable
final class AchievementsModel {
    private(set) var state: AchievementsViewState = .idle
    private(set) var content: AchievementsContent?
    private(set) var isLoadingMoreCompleted = false
    private(set) var isLoadingMoreActivity = false
    private(set) var paginationMessage: String?
    // Shell-level fireworks takeover source (mirrors /m's
    // store.pendingCelebration): set by load()/refreshCelebration(), cleared
    // by dismissCelebration().
    private(set) var pendingCelebration: LevelCelebration?
    private let client: any AchievementsClient
    private var generation = 0

    init(client: any AchievementsClient) {
        self.client = client
    }

    func load() async {
        generation &+= 1
        let activeGeneration = generation
        if content == nil {
            state = .loading
        }

        async let activity = optionalActivity()
        async let status = optionalStatus()

        do {
            let summary = try await client.loadAchievements()
            let activityPage = await activity
            let gamificationStatus = await status
            guard activeGeneration == generation, !Task.isCancelled else { return }
            content = AchievementsContent(
                summary: summary,
                completedPage: 1,
                activity: activityPage?.events ?? [],
                activityNextCursor: activityPage?.nextCursor
            )
            if let gamificationStatus {
                pendingCelebration = gamificationStatus.pendingCelebration
            }
            paginationMessage = nil
            state = .loaded
        } catch is CancellationError {
            return
        } catch let error as APIError {
            guard activeGeneration == generation else { return }
            map(error)
        } catch {
            guard activeGeneration == generation else { return }
            state = .failed(requestID: nil)
        }
    }

    func refresh() async {
        await load()
    }

    func loadMoreCompleted() async {
        guard var current = content,
              current.summary.completed.count < current.summary.completedTotal,
              !isLoadingMoreCompleted
        else { return }

        isLoadingMoreCompleted = true
        paginationMessage = nil
        defer { isLoadingMoreCompleted = false }

        do {
            let page = try await client.loadCompleted(page: current.completedPage + 1)
            let known = Set(current.summary.completed.map(\.id))
            current.summary.completed.append(
                contentsOf: page.completed.filter { !known.contains($0.id) }
            )
            current.summary.completedTotal = page.completedTotal
            current.completedPage = page.page
            content = current
        } catch is CancellationError {
            return
        } catch {
            paginationMessage = "We could not load more completed actions. Please try again."
        }
    }

    func loadMoreActivity() async {
        guard var current = content,
              let cursor = current.activityNextCursor,
              !isLoadingMoreActivity
        else { return }

        isLoadingMoreActivity = true
        paginationMessage = nil
        defer { isLoadingMoreActivity = false }

        do {
            let page = try await client.loadActivity(before: cursor)
            let known = Set(current.activity.map(\.id))
            current.activity.append(
                contentsOf: page.events.filter { !known.contains($0.id) }
            )
            current.activityNextCursor = page.nextCursor
            content = current
        } catch is CancellationError {
            return
        } catch {
            paginationMessage = "We could not load more activity. Please try again."
        }
    }

    // /m's store.fetchStatus() equivalent for the dashboard shell — missed
    // celebrations are delivered on open, without loading the full page.
    func refreshCelebration() async {
        guard let status = try? await client.loadStatus() else { return }
        pendingCelebration = status.pendingCelebration
    }

    // /m's store.ack(): clear locally first; the server acknowledgement is
    // best-effort and non-fatal (an unacked flag is simply redelivered by
    // the next status fetch).
    func dismissCelebration() async {
        pendingCelebration = nil
        try? await client.acknowledgeCelebration()
    }

    func stop() {
        generation &+= 1
        state = .idle
        content = nil
        paginationMessage = nil
        pendingCelebration = nil
    }

    private func optionalActivity() async -> AchievementsActivityPage? {
        try? await client.loadActivity(before: nil)
    }

    private func optionalStatus() async -> GamificationStatus? {
        try? await client.loadStatus()
    }

    private func map(_ error: APIError) {
        switch error {
        case .offline:
            state = .offline
        case .unauthenticated:
            state = .unauthenticated
        case let .server(_, requestID), let .decoding(requestID):
            state = .failed(requestID: requestID)
        case .validation,
             .forbidden,
             .upgradeRequired,
             .nativeUpdateRequired,
             .rateLimited,
             .conflict:
            state = .failed(requestID: nil)
        }
    }
}
