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

    private enum CodingKeys: String, CodingKey {
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
    var headroomCount: Int {
        userAllowances.filter {
            $0.available != false && $0.known != false && ($0.remaining ?? 0) > 0
        }.count
    }
}

struct TaxAllowance: Decodable, Sendable, Equatable, Identifiable {
    let key: String
    let label: String
    let amount: Decimal?
    let used: Decimal?
    let remaining: Decimal?
    let utilisationPercentage: Decimal?
    let available: Bool?
    let known: Bool?

    var id: String { key }

    private enum CodingKeys: String, CodingKey {
        case key
        case label
        case amount
        case used
        case remaining
        case utilisationPercentage = "utilisation_pct"
        case available
        case known
    }

    var remainingLabel: String {
        if available == false { return "Not available" }
        if known == false { return "Current-year use not confirmed" }
        if (utilisationPercentage ?? 0) >= 100 || (remaining ?? 0) <= 0 {
            return "Fully used"
        }
        guard let remaining else { return "Unavailable" }
        return "\(MoneyFormatter.gbp(remaining)) available"
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
