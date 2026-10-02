import Foundation

struct TaxStrategyEnvelope: Decodable, Sendable, Equatable {
    let data: TaxStrategyDashboard
}

struct TaxStrategyDashboard: Decodable, Sendable, Equatable {
    let taxYear: String?
    let calculationMode: String?
    let userAllowances: [TaxAllowance]
    let spouseAllowances: [TaxAllowance]?
    let composedPlan: ComposedTaxPlan
    /// The header's figures, decided on the server (TaxStrategyService::withDisplayState).
    let summary: TaxStrategySummary?

    private enum CodingKeys: String, CodingKey {
        case summary
        case taxYear = "tax_year"
        case calculationMode = "calculation_mode"
        case userAllowances = "user_allowances"
        case spouseAllowances = "spouse_allowances"
        case composedPlan = "composed_plan"
    }

    var isHousehold: Bool {
        calculationMode == "dual_earner" || calculationMode == "single_earner_couple"
    }
    var openRecommendations: [TaxRecommendation] {
        composedPlan.items.filter { $0.category != "household" && !$0.completed }
    }
    var completedRecommendations: [TaxRecommendation] {
        composedPlan.items.filter { $0.category != "household" && $0.completed }
    }
    var householdRecommendations: [TaxRecommendation] {
        composedPlan.items.filter { $0.category == "household" }
    }
    /// The server's count of allowances with headroom, as web and /m show it.
    var headroomCount: Int { summary?.headroomCount ?? 0 }
}

struct TaxStrategySummary: Decodable, Sendable, Equatable {
    let totalSaving: Decimal?
    let headroomCount: Int

    private enum CodingKeys: String, CodingKey {
        case totalSaving = "total_saving"
        case headroomCount = "headroom_count"
    }
}

struct TaxAllowance: Decodable, Sendable, Equatable, Identifiable {
    let key: String
    let label: String
    let amount: Decimal?
    let used: Decimal?
    let remaining: Decimal?
    let utilisationPercentage: Decimal?
    // Bar/remaining colour from the calculator: spring | violet | raspberry |
    // muted (muted draws no bar fill on /m).
    let status: String?
    let available: Bool?
    let known: Bool?
    /// The tile's state and the affordability cap, from the server
    /// (TaxStrategyService `tile_state`, `budget_limited`). iOS did not read
    /// the cap, so a capped pension tile read "Fully used" (audit item 41).
    let tileState: String?
    let budgetLimited: Bool?

    var id: String { key }

    private enum CodingKeys: String, CodingKey {
        case tileState = "tile_state"
        case budgetLimited = "budget_limited"
        case key
        case label
        case amount
        case used
        case remaining
        case utilisationPercentage = "utilisation_pct"
        case status
        case available
        case known
    }

    // The words are /m's approved copy; the state is the server's.
    var remainingLabel: String {
        switch tileState {
        case "unavailable": return "Not available"
        case "unconfirmed": return "Current-year use not confirmed"
        case "budget_capped": return "\(MoneyFormatter.gbpWhole(0)) available"
        case "open": return "\(remaining.map(MoneyFormatter.gbpWhole) ?? "—") available"
        default: return "Fully used"
        }
    }

    /// As /m: say when what is affordable, not use, lowered the figure.
    var budgetNote: String? {
        budgetLimited == true ? "Limited to what you can afford this year" : nil
    }
}

struct ComposedTaxPlan: Decodable, Sendable, Equatable {
    let combinedAnnualSaving: Decimal?
    let items: [TaxRecommendation]

    private enum CodingKeys: String, CodingKey {
        case combinedAnnualSaving = "combined_annual_saving"
        case items
    }
}

struct TaxRecommendation: Decodable, Sendable, Equatable, Identifiable {
    let type: String
    let title: String
    let description: String?
    let category: String?
    let estimatedAnnualTaxSaved: Decimal?
    let recommendationID: String?
    let completed: Bool
    let completedAt: String?
    let requiresAdvice: Bool?

    var id: String { recommendationID ?? type }

    private enum CodingKeys: String, CodingKey {
        case type
        case title
        case description
        case category
        case estimatedAnnualTaxSaved = "estimated_annual_tax_saved"
        case recommendationID = "recommendation_id"
        case completed
        case completedAt = "completed_at"
        case requiresAdvice = "requires_advice"
    }
}

enum TaxStrategyViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(TaxStrategyDashboard)
    case offline(previous: TaxStrategyDashboard?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
