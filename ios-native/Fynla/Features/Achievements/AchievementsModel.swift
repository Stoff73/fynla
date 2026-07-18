import Observation

@MainActor
@Observable
final class AchievementsModel {
    private(set) var state: AchievementsViewState = .idle
    private(set) var content: AchievementsContent?
    private(set) var isLoadingMoreCompleted = false
    private(set) var isLoadingMoreActivity = false
    private(set) var isAcknowledgingCelebration = false
    private(set) var paginationMessage: String?
    private(set) var celebrationMessage: String?
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
                activityNextCursor: activityPage?.nextCursor,
                pendingCelebration: gamificationStatus?.pendingCelebration
            )
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

    func dismissCelebration() async {
        guard var current = content,
              current.pendingCelebration != nil,
              !isAcknowledgingCelebration
        else { return }

        isAcknowledgingCelebration = true
        celebrationMessage = nil
        defer { isAcknowledgingCelebration = false }

        do {
            try await client.acknowledgeCelebration()
            current.pendingCelebration = nil
            content = current
        } catch is CancellationError {
            return
        } catch {
            celebrationMessage = "We could not save that acknowledgement. Please try again."
        }
    }

    func stop() {
        generation &+= 1
        state = .idle
        content = nil
        paginationMessage = nil
        celebrationMessage = nil
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
             .rateLimited,
             .conflict:
            state = .failed(requestID: nil)
        }
    }
}
