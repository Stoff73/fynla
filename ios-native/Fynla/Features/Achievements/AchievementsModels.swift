import Foundation

enum AchievementState: String, Decodable, Equatable, Sendable {
    case earned
    case inProgress = "in_progress"
    case locked
    case inapplicable
}

struct AchievementProvenance: Decodable, Equatable, Sendable {
    let kind: String
    let event: String
    let occurredAt: String?

    private enum CodingKeys: String, CodingKey {
        case kind, event
        case occurredAt = "occurred_at"
    }
}

struct AchievementProgress: Decodable, Equatable, Sendable {
    let current: Double
    let target: Double
    let percent: Double
    let label: String
}

struct AchievementNextAction: Decodable, Equatable, Sendable {
    let label: String
    let destination: SemanticDestination
}

struct AchievementBadge: Decodable, Equatable, Identifiable, Sendable {
    let key: String
    let title: String
    let description: String
    let earned: Bool
    let earnedAt: String?
    let state: AchievementState
    let provenance: AchievementProvenance?
    let progress: AchievementProgress?
    let nextAction: AchievementNextAction?

    var id: String { key }

    private enum CodingKeys: String, CodingKey {
        case key, title, description, earned, state, provenance, progress
        case earnedAt = "earned_at"
        case nextAction = "next_action"
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
    let state: AchievementState
    let provenance: AchievementProvenance?
    let progress: AchievementProgress?
    let nextAction: AchievementNextAction?

    var id: String { key }

    private enum CodingKeys: String, CodingKey {
        case key, title, achieved, state, provenance, progress
        case achievedAt = "achieved_at"
        case nextAction = "next_action"
    }
}

struct AchievementStatusPresentation: Equatable, Sendable {
    let status: String
    let isEarned: Bool
}

enum AchievementPresentation {
    static func badge(
        _ badge: AchievementBadge
    ) -> AchievementStatusPresentation {
        status(
            state: badge.state,
            canonicalDate: badge.provenance?.occurredAt,
            legacyDate: badge.earnedAt,
            earnedLabel: "Earned"
        )
    }

    static func milestone(
        _ milestone: AchievementMilestone
    ) -> AchievementStatusPresentation {
        status(
            state: milestone.state,
            canonicalDate: milestone.provenance?.occurredAt,
            legacyDate: milestone.achievedAt,
            earnedLabel: "Reached"
        )
    }

    private static func status(
        state: AchievementState,
        canonicalDate: String?,
        legacyDate: String?,
        earnedLabel: String
    ) -> AchievementStatusPresentation {
        switch state {
        case .earned:
            let dateLabel = formattedDate(canonicalDate ?? legacyDate)
            return AchievementStatusPresentation(
                status: dateLabel.isEmpty
                    ? earnedLabel
                    : "\(earnedLabel) \(dateLabel)",
                isEarned: true
            )
        case .inProgress:
            return AchievementStatusPresentation(
                status: "In progress",
                isEarned: false
            )
        case .locked:
            return AchievementStatusPresentation(
                status: "Locked",
                isEarned: false
            )
        case .inapplicable:
            return AchievementStatusPresentation(
                status: "Not applicable",
                isEarned: false
            )
        }
    }

    private static func formattedDate(_ value: String?) -> String {
        guard let value else { return "" }
        let plain = ISO8601DateFormatter()
        let fractional = ISO8601DateFormatter()
        fractional.formatOptions = [
            .withInternetDateTime,
            .withFractionalSeconds,
        ]
        guard let date = plain.date(from: value) ?? fractional.date(from: value)
        else { return "" }

        let output = DateFormatter()
        output.locale = Locale(identifier: "en_GB")
        output.timeZone = TimeZone(secondsFromGMT: 0)
        output.dateFormat = "dd/MM/yyyy"
        return output.string(from: date)
    }
}

struct AchievementUpcoming: Decodable, Equatable, Identifiable, Sendable {
    let key: String
    let group: String
    let title: String
    let steps: String
    let state: AchievementState
    let progress: AchievementProgress?
    let nextAction: AchievementNextAction?
    let route: String?

    var id: String { key }
    private enum CodingKeys: String, CodingKey {
        case key, group, title, steps, state, progress, route
        case nextAction = "next_action"
    }
}

struct AchievementsSnapshot: Decodable, Equatable, Sendable {
    var achievements: [AchievementBadge]
    var completed: [AchievementCompletedAction]
    var completedTotal: Int
    var milestones: [AchievementMilestone]
    var milestonesTotal: Int
    var perPage: Int
    var nextCursor: String?
    var upcoming: [AchievementUpcoming]

    private enum CodingKeys: String, CodingKey {
        case achievements, completed, milestones, upcoming
        case completedTotal = "completed_total"
        case milestonesTotal = "milestones_total"
        case nextCursor = "next_cursor"
        case perPage = "per_page"
    }

    init(achievements: [AchievementBadge], completed: [AchievementCompletedAction], completedTotal: Int, milestones: [AchievementMilestone], milestonesTotal: Int, perPage: Int, nextCursor: String?, upcoming: [AchievementUpcoming]) {
        self.achievements = achievements
        self.completed = completed
        self.completedTotal = completedTotal
        self.milestones = milestones
        self.milestonesTotal = milestonesTotal
        self.perPage = perPage
        self.nextCursor = nextCursor
        self.upcoming = upcoming
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        achievements = try c.decode([AchievementBadge].self, forKey: .achievements)
        completed = try c.decode([AchievementCompletedAction].self, forKey: .completed)
        completedTotal = try c.decode(Int.self, forKey: .completedTotal)
        milestones = try c.decode([AchievementMilestone].self, forKey: .milestones)
        milestonesTotal = try c.decode(Int.self, forKey: .milestonesTotal)
        perPage = try c.decode(Int.self, forKey: .perPage)
        upcoming = try c.decode([AchievementUpcoming].self, forKey: .upcoming)
        if try c.decodeNil(forKey: .nextCursor) { nextCursor = nil } else {
            let cursor = try c.decode(String.self, forKey: .nextCursor)
            guard !cursor.isEmpty else { throw DecodingError.dataCorruptedError(forKey: .nextCursor, in: c, debugDescription: "Achievement cursor must be non-empty.") }
            nextCursor = cursor
        }
    }
}

struct AchievementsMilestonePage: Decodable, Equatable, Sendable {
    let milestones: [AchievementMilestone]
    let milestonesTotal: Int
    let perPage: Int
    let nextCursor: String?

    private enum CodingKeys: String, CodingKey {
        case milestones
        case milestonesTotal = "milestones_total"
        case perPage = "per_page"
        case nextCursor = "next_cursor"
    }

    init(milestones: [AchievementMilestone], milestonesTotal: Int, perPage: Int, nextCursor: String?) {
        self.milestones = milestones
        self.milestonesTotal = milestonesTotal
        self.perPage = perPage
        self.nextCursor = nextCursor
    }

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        milestones = try c.decode([AchievementMilestone].self, forKey: .milestones)
        milestonesTotal = try c.decode(Int.self, forKey: .milestonesTotal)
        perPage = try c.decode(Int.self, forKey: .perPage)
        if try c.decodeNil(forKey: .nextCursor) { nextCursor = nil } else {
            let cursor = try c.decode(String.self, forKey: .nextCursor)
            guard !cursor.isEmpty else { throw DecodingError.dataCorruptedError(forKey: .nextCursor, in: c, debugDescription: "Achievement cursor must be non-empty.") }
            nextCursor = cursor
        }
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
    let nextActions: [String]?

    private enum CodingKeys: String, CodingKey {
        case level
        case levelName = "level_name"
        case nextActions = "next_actions"
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
}

enum AchievementsViewState: Equatable, Sendable {
    case idle
    case loading
    case loaded
    case offline
    case unauthenticated
    case failed(requestID: String?)
}
