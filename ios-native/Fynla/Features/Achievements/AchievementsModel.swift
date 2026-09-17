import Observation

@MainActor
@Observable
final class AchievementsModel {
    private(set) var state: AchievementsViewState = .idle
    private(set) var content: AchievementsContent?
    private(set) var isLoadingMoreCompleted = false
    private(set) var isLoadingMoreActivity = false
    private(set) var isLoadingMoreMilestones = false
    private(set) var paginationMessage: String?
    // The banked level climb, spent on the dashboard hero wheel (mirrors /m's
    // store.gamification.celebrateFrom/To): set by load()/refreshCelebration(),
    // moved forward by acknowledgeCelebration(level:).
    private(set) var celebrateFrom = 1
    private(set) var celebrateTo = 1
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
            var deduplicated = summary
            deduplicated.milestones = mergeMilestones([], with: summary.milestones)
            content = AchievementsContent(
                summary: deduplicated,
                completedPage: 1,
                activity: activityPage?.events ?? [],
                activityNextCursor: activityPage?.nextCursor
            )
            if let gamificationStatus {
                celebrateFrom = gamificationStatus.celebrateFrom ?? 1
                celebrateTo = gamificationStatus.celebrateTo ?? 1
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

    func loadMoreMilestones() async {
        guard let cursor = content?.summary.nextCursor,
              !isLoadingMoreMilestones
        else { return }

        let activeGeneration = generation
        isLoadingMoreMilestones = true
        paginationMessage = nil
        defer { isLoadingMoreMilestones = false }

        do {
            let page = try await client.loadMilestones(cursor: cursor)
            guard activeGeneration == generation,
                  var current = content,
                  current.summary.nextCursor == cursor
            else { return }

            current.summary.milestones = mergeMilestones(
                current.summary.milestones,
                with: page.milestones
            )
            current.summary.milestonesTotal = page.milestonesTotal
            current.summary.perPage = page.perPage
            current.summary.nextCursor = page.nextCursor
            content = current
        } catch is CancellationError {
            return
        } catch let error as APIError {
            guard activeGeneration == generation,
                  content?.summary.nextCursor == cursor
            else { return }

            if case .unauthenticated = error {
                map(error)
            } else {
                paginationMessage = "We could not load more milestones. Please try again."
            }
        } catch {
            guard activeGeneration == generation,
                  content?.summary.nextCursor == cursor
            else { return }
            paginationMessage = "We could not load more milestones. Please try again."
        }
    }

    // /m's store.fetchStatus() equivalent for the dashboard shell — a climb
    // banked while the user was elsewhere is delivered on open, without
    // loading the full page.
    func refreshCelebration() async {
        guard let status = try? await client.loadStatus() else { return }
        celebrateFrom = status.celebrateFrom ?? 1
        celebrateTo = status.celebrateTo ?? 1
    }

    // /m's store.ackCelebration(): settle locally first; the server
    // acknowledgement is best-effort and non-fatal (an unacked range is simply
    // redelivered by the next status fetch, and the ack is idempotent).
    func acknowledgeCelebration(level: Int) async {
        celebrateFrom = level
        celebrateTo = max(level, celebrateTo)
        try? await client.acknowledgeCelebration(level: level)
    }

    func stop() {
        generation &+= 1
        state = .idle
        content = nil
        paginationMessage = nil
        celebrateFrom = 1
        celebrateTo = 1
    }

    private func optionalActivity() async -> AchievementsActivityPage? {
        try? await client.loadActivity(before: nil)
    }

    private func optionalStatus() async -> GamificationStatus? {
        try? await client.loadStatus()
    }

    private func mergeMilestones(
        _ existing: [AchievementMilestone],
        with incoming: [AchievementMilestone]
    ) -> [AchievementMilestone] {
        var keys = Set<String>()
        return (existing + incoming).filter { keys.insert($0.key).inserted }
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
