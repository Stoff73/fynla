import Foundation

struct HolisticPlanEnvelope: Decodable, Sendable, Equatable {
    let success: Bool
    let data: HolisticPlan
}

struct HolisticPlan: Decodable, Sendable, Equatable {
    let items: [HolisticPlanItem]
    let locked: [HolisticLockedStrategy]
    let availableMonthlySurplus: Decimal
    let goalCommitments: Decimal
    let effectiveSurplus: Decimal
    let nearTermCapital: Decimal

    private enum CodingKeys: String, CodingKey {
        case items
        case locked
        case availableMonthlySurplus = "available_monthly_surplus"
        case goalCommitments = "goal_commitments"
        case effectiveSurplus = "effective_surplus"
        case nearTermCapital = "near_term_capital"
    }
}

struct HolisticPlanItem: Decodable, Sendable, Equatable, Identifiable {
    let type: String
    let priority: String
    let title: String
    let description: String?
    let estimatedAnnualTaxSaved: Decimal?
    let requiresAdvice: Bool?
    let requiredMonthlyCost: Decimal?
    let requiredLumpSum: Decimal?
    let sequencePosition: Int?
    let conflictNote: String?
    let module: String
    let affordability: String
    let surplusConsumedToHere: Decimal?

    var id: String { "\(module)_\(type)_\(sequencePosition ?? 0)" }

    private enum CodingKeys: String, CodingKey {
        case type
        case priority
        case title
        case description
        case estimatedAnnualTaxSaved = "estimated_annual_tax_saved"
        case requiresAdvice = "requires_advice"
        case requiredMonthlyCost = "required_monthly_cost"
        case requiredLumpSum = "required_lump_sum"
        case sequencePosition = "sequence_position"
        case conflictNote = "conflict_note"
        case module
        case affordability
        case surplusConsumedToHere = "surplus_consumed_to_here"
    }
}

struct HolisticLockedStrategy: Decodable, Sendable, Equatable, Identifiable {
    let strategyType: String
    let missing: [String]
    let module: String

    var id: String { "\(module)_\(strategyType)" }

    private enum CodingKeys: String, CodingKey {
        case strategyType = "strategy_type"
        case missing
        case module
    }
}

enum HolisticPlanViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(HolisticPlan)
    case offline(previous: HolisticPlan?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
