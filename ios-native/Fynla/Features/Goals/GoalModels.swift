import Foundation

struct GoalsSnapshot: Sendable, Equatable {
    let list: GoalListResponse
    let overview: GoalsOverview?

    var goals: [FinancialGoal] { list.goals }
}

struct GoalListResponse: Decodable, Sendable, Equatable {
    let goals: [FinancialGoal]
    let count: Int
}

struct FinancialGoal: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let name: String?
    let goalName: String?
    let goalType: String?
    let displayGoalType: String?
    let targetAmount: Decimal
    let currentAmount: Decimal
    let targetDate: String?
    let status: String?
    let progressPercentage: Decimal
    let amountRemaining: Decimal?
    let daysRemaining: Int?
    let monthsRemaining: Int?
    let isOnTrack: Bool
    let isPrimaryOwner: Bool?
    let description: String?
    let startDate: String?
    let currentMilestone: Decimal?
    let nextMilestone: Decimal?
    let requiredMonthlyContribution: Decimal?
    let monthlyContribution: Decimal?
    let contributionFrequency: String?
    let contributionStreak: Int?
    let longestStreak: Int?
    let lastContributionDate: String?
    let ownershipType: String?
    let ownershipPercentage: Decimal?
    let createdAt: String?

    private enum CodingKeys: String, CodingKey {
        case id
        case name
        case goalName = "goal_name"
        case goalType = "goal_type"
        case displayGoalType = "display_goal_type"
        case targetAmount = "target_amount"
        case currentAmount = "current_amount"
        case targetDate = "target_date"
        case status
        case progressPercentage = "progress_percentage"
        case amountRemaining = "amount_remaining"
        case daysRemaining = "days_remaining"
        case monthsRemaining = "months_remaining"
        case isOnTrack = "is_on_track"
        case isPrimaryOwner = "is_primary_owner"
        case description
        case startDate = "start_date"
        case currentMilestone = "current_milestone"
        case nextMilestone = "next_milestone"
        case requiredMonthlyContribution = "required_monthly_contribution"
        case monthlyContribution = "monthly_contribution"
        case contributionFrequency = "contribution_frequency"
        case contributionStreak = "contribution_streak"
        case longestStreak = "longest_streak"
        case lastContributionDate = "last_contribution_date"
        case ownershipType = "ownership_type"
        case ownershipPercentage = "ownership_percentage"
        case createdAt = "created_at"
    }

    var displayName: String { name ?? goalName ?? "Goal" }
    var detailRoute: AppRoute { .goalDetail(id: id) }
    var typeLabel: String {
        displayGoalType
            ?? goalType?.replacingOccurrences(of: "_", with: " ").capitalized
            ?? "Goal"
    }
    var statusLabel: String {
        if progressPercentage >= 100 || status == "completed" { return "Complete" }
        return isOnTrack ? "On track" : "Behind"
    }
    var remainingLabel: String {
        if let monthsRemaining, monthsRemaining > 0 {
            return "\(monthsRemaining) \(monthsRemaining == 1 ? "month" : "months") left"
        }
        if let daysRemaining, daysRemaining > 0 {
            return "\(daysRemaining) \(daysRemaining == 1 ? "day" : "days") left"
        }
        return status == "completed" ? "Complete" : "Target date passed"
    }
}

struct GoalDetailResponse: Decodable, Sendable, Equatable {
    let goal: FinancialGoal
    let milestones: [GoalMilestone]
}

struct GoalMilestone: Decodable, Sendable, Equatable, Identifiable {
    let percentage: Decimal?
    let progressPercentage: Decimal?
    let reached: Bool?

    var id: String {
        "\(percentage ?? progressPercentage ?? 0)-\(reached == true)"
    }

    private enum CodingKeys: String, CodingKey {
        case percentage
        case progressPercentage = "progress_percentage"
        case reached
    }
}

struct GoalsOverview: Decodable, Sendable, Equatable {
    let totalGoals: Int
    let onTrackCount: Int
    let totalTarget: Decimal
    let totalCurrent: Decimal
    let overallProgress: Decimal

    private enum CodingKeys: String, CodingKey {
        case totalGoals = "total_goals"
        case onTrackCount = "on_track_count"
        case totalTarget = "total_target"
        case totalCurrent = "total_current"
        case overallProgress = "overall_progress"
    }
}

enum GoalsViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(GoalsSnapshot)
    case offline(previous: GoalsSnapshot?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}

enum GoalDetailViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(GoalDetailResponse)
    case offline(previous: GoalDetailResponse?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
