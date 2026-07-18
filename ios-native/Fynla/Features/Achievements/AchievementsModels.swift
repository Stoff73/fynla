struct AchievementBadge: Decodable, Equatable, Identifiable, Sendable {
    let key: String
    let title: String
    let description: String
    let earned: Bool
    let earnedAt: String?

    var id: String { key }

    private enum CodingKeys: String, CodingKey {
        case key, title, description, earned
        case earnedAt = "earned_at"
    }
}

struct AchievementCompletedAction: Decodable, Equatable, Identifiable, Sendable {
    let id: String
    let title: String
    let module: String
    let completedAt: String?

    private enum CodingKeys: String, CodingKey {
        case id, title, module
        case completedAt = "completed_at"
    }
}

struct AchievementMilestone: Decodable, Equatable, Identifiable, Sendable {
    let key: String
    let title: String
    let achieved: Bool
    let achievedAt: String?

    var id: String { key }

    private enum CodingKeys: String, CodingKey {
        case key, title, achieved
        case achievedAt = "achieved_at"
    }
}

struct AchievementUpcoming: Decodable, Equatable, Identifiable, Sendable {
    let group: String
    let title: String
    let steps: String
    let route: String

    var id: String { "\(group)|\(title)|\(route)" }
}

struct AchievementsSnapshot: Decodable, Equatable, Sendable {
    var achievements: [AchievementBadge]
    var completed: [AchievementCompletedAction]
    var completedTotal: Int
    var milestones: [AchievementMilestone]
    var upcoming: [AchievementUpcoming]

    private enum CodingKeys: String, CodingKey {
        case achievements, completed, milestones, upcoming
        case completedTotal = "completed_total"
    }
}

struct AchievementsCompletedPage: Decodable, Equatable, Sendable {
    let completed: [AchievementCompletedAction]
    let completedTotal: Int
    let page: Int
    let perPage: Int

    private enum CodingKeys: String, CodingKey {
        case completed, page
        case completedTotal = "completed_total"
        case perPage = "per_page"
    }
}

struct AchievementActivity: Decodable, Equatable, Identifiable, Sendable {
    let id: String
    let kind: String
    let label: String
    let occurredAt: String?

    private enum CodingKeys: String, CodingKey {
        case id, kind, label
        case occurredAt = "occurred_at"
    }
}

struct AchievementsActivityPage: Decodable, Equatable, Sendable {
    let events: [AchievementActivity]
    let nextCursor: Int?

    private enum CodingKeys: String, CodingKey {
        case events = "data"
        case nextCursor = "next_cursor"
    }
}

struct LevelCelebration: Decodable, Equatable, Sendable {
    let level: Int
    let levelName: String

    private enum CodingKeys: String, CodingKey {
        case level
        case levelName = "level_name"
    }
}

struct GamificationStatus: Decodable, Equatable, Sendable {
    let pendingCelebration: LevelCelebration?

    private enum CodingKeys: String, CodingKey {
        case pendingCelebration = "pending_celebration"
    }
}

struct AchievementsContent: Equatable, Sendable {
    var summary: AchievementsSnapshot
    var completedPage: Int
    var activity: [AchievementActivity]
    var activityNextCursor: Int?
    var pendingCelebration: LevelCelebration?
}

enum AchievementsViewState: Equatable, Sendable {
    case idle
    case loading
    case loaded
    case offline
    case unauthenticated
    case failed(requestID: String?)
}
