import Foundation

enum DashboardModuleStatus: String, Decodable, Sendable, Equatable {
    case active
    case notConfigured = "not_configured"
    case unavailable
}

struct DashboardModuleSummary: Decodable, Sendable, Equatable {
    let status: DashboardModuleStatus
    let message: String?

    let totalCoverage: Decimal?
    let policyCount: Int?
    let criticalGaps: Int?
    let hasIncomeProtection: Bool?

    let totalSavings: Decimal?
    let totalAccounts: Int?
    let emergencyFundMonths: Decimal?
    let emergencyFundStatus: String?

    let portfolioValue: Decimal?
    let accountsCount: Int?
    let holdingsCount: Int?

    let yearsToRetirement: Int?
    let potValue: Decimal?
    let projectedIncome: Decimal?
    let targetIncome: Decimal?
    let incomeGap: Decimal?
    let totalPensions: Int?

    let netEstate: Decimal?
    let ihtLiability: Decimal?
    let effectiveTaxRate: Decimal?

    let totalGoals: Int?
    let completedGoals: Int?
    let totalTarget: Decimal?
    let totalSaved: Decimal?

    private enum CodingKeys: String, CodingKey {
        case status, message
        case totalCoverage = "total_coverage"
        case policyCount = "policy_count"
        case criticalGaps = "critical_gaps"
        case hasIncomeProtection = "has_income_protection"
        case totalSavings = "total_savings"
        case totalAccounts = "total_accounts"
        case emergencyFundMonths = "emergency_fund_months"
        case emergencyFundStatus = "emergency_fund_status"
        case portfolioValue = "portfolio_value"
        case accountsCount = "accounts_count"
        case holdingsCount = "holdings_count"
        case yearsToRetirement = "years_to_retirement"
        case potValue = "pot_value"
        case projectedIncome = "projected_income"
        case targetIncome = "target_income"
        case incomeGap = "income_gap"
        case totalPensions = "total_pensions"
        case netEstate = "net_estate"
        case ihtLiability = "iht_liability"
        case effectiveTaxRate = "effective_tax_rate"
        case totalGoals = "total_goals"
        case completedGoals = "completed_goals"
        case totalTarget = "total_target"
        case totalSaved = "total_saved"
    }
}

struct DashboardModules: Decodable, Sendable, Equatable {
    let protection: DashboardModuleSummary
    let savings: DashboardModuleSummary
    let investment: DashboardModuleSummary
    let retirement: DashboardModuleSummary
    let estate: DashboardModuleSummary
    let goals: DashboardModuleSummary

    var ordered: [(key: String, title: String, summary: DashboardModuleSummary)] {
        [
            ("protection", "Protection", protection),
            ("savings", "Savings", savings),
            ("investment", "Investment", investment),
            ("retirement", "Retirement", retirement),
            ("estate", "Estate planning", estate),
            ("goals", "Goals", goals),
        ]
    }
}

struct DashboardAssetBreakdown: Decodable, Sendable, Equatable {
    let property: Decimal?
    let savings: Decimal?
    let investments: Decimal?
    let pensions: Decimal?
    let business: Decimal?
    let chattels: Decimal?
    let cash: Decimal?
}

struct DashboardLiabilityBreakdown: Decodable, Sendable, Equatable {
    let mortgages: Decimal?
    let otherLiabilities: Decimal?

    private enum CodingKeys: String, CodingKey {
        case mortgages
        case otherLiabilities = "other_liabilities"
    }
}

struct DashboardNetWorthBreakdown: Decodable, Sendable, Equatable {
    let assets: DashboardAssetBreakdown
    let liabilities: DashboardLiabilityBreakdown
    let totalAssets: Decimal?
    let totalLiabilities: Decimal?

    private enum CodingKeys: String, CodingKey {
        case assets, liabilities
        case totalAssets = "total_assets"
        case totalLiabilities = "total_liabilities"
    }
}

struct DashboardNetWorth: Decodable, Sendable, Equatable {
    let total: Decimal
    let breakdown: DashboardNetWorthBreakdown
}

enum DashboardAlertSeverity: String, Decodable, Sendable, Equatable {
    case critical
    case important
    case info
}

struct DashboardAlert: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let module: String
    let severity: DashboardAlertSeverity
    let title: String
    let message: String
    let actionLink: String
    let actionText: String
    let createdAt: String

    private enum CodingKeys: String, CodingKey {
        case id, module, severity, title, message
        case actionLink = "action_link"
        case actionText = "action_text"
        case createdAt = "created_at"
    }
}

enum DashboardActionType: String, Decodable, Sendable, Equatable {
    case recommendation
    case unlock
}

enum DashboardActionKind: String, Decodable, Sendable, Equatable {
    case navigate
    case fynCapture = "fyn_capture"
}

struct DashboardActionDestination: Decodable, Sendable, Equatable {
    let kind: DashboardActionKind
    let payload: String
}

struct DashboardAction: Decodable, Sendable, Equatable, Identifiable {
    let id: String
    let type: DashboardActionType
    let module: String
    let title: String
    let meta: String
    let value: Decimal
    let done: Bool
    let action: DashboardActionDestination
}

struct DashboardFocusArea: Decodable, Sendable, Equatable, Identifiable {
    let key: String
    let label: String
    let locked: Bool
    let stat: String
    let actions: [DashboardAction]

    var id: String { key }
}

struct DashboardLevel: Decodable, Sendable, Equatable {
    let level: Int
    let levelName: String
    let nextLevelName: String?
    let progressPercent: Int
    let actionsCompleted: Int
    let actionsTotal: Int
    let actionsToNext: Int

    private enum CodingKeys: String, CodingKey {
        case level
        case levelName = "level_name"
        case nextLevelName = "next_level_name"
        case progressPercent = "progress_percent"
        case actionsCompleted = "actions_completed"
        case actionsTotal = "actions_total"
        case actionsToNext = "actions_to_next"
    }
}

struct DashboardMilestone: Decodable, Sendable, Equatable, Identifiable {
    let type: String
    let referenceID: Int?
    let threshold: Decimal?
    let label: String
    let shareType: String?

    var id: String { "\(type):\(referenceID ?? 0):\(threshold?.description ?? label)" }

    private enum CodingKeys: String, CodingKey {
        case type, threshold, label
        case referenceID = "reference_id"
        case shareType = "share_type"
    }
}

struct DashboardNextMilestone: Decodable, Sendable, Equatable {
    let group: String
    let title: String
    let steps: String
    let route: String
}

enum DashboardEntitlementTier: String, Decodable, Sendable, Equatable {
    case free
    case premium
}

struct DashboardEntitlement: Decodable, Sendable, Equatable {
    let tier: DashboardEntitlementTier
    let provider: String?
    let status: String
    let renews: Bool
    let currentPeriodEnd: String?
    let capabilities: [String: String]
    let limits: [String: Int?]

    private enum CodingKeys: String, CodingKey {
        case tier, provider, status, renews, capabilities, limits
        case currentPeriodEnd = "current_period_end"
    }
}

struct DashboardSnapshot: Decodable, Sendable, Equatable {
    let modules: DashboardModules
    let netWorth: DashboardNetWorth
    let alerts: [DashboardAlert]
    let fynInsight: String?
    let cachedAt: String
    let focusAreas: [DashboardFocusArea]
    let nextActions: [DashboardAction]
    let level: DashboardLevel
    let percentile: Int
    let newMilestones: [DashboardMilestone]
    let nextMilestone: DashboardNextMilestone?
    let entitlement: DashboardEntitlement?

    private enum CodingKeys: String, CodingKey {
        case modules, alerts, level, percentile, entitlement
        case netWorth = "net_worth"
        case fynInsight = "fyn_insight"
        case cachedAt = "cached_at"
        case focusAreas = "focus_areas"
        case nextActions = "next_actions"
        case newMilestones = "new_milestones"
        case nextMilestone = "next_milestone"
    }
}

enum DashboardViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(DashboardSnapshot)
    case offline(previous: DashboardSnapshot?)
    case unauthenticated
    case failed(requestID: String?)
}
