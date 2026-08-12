import Foundation

struct ProtectionSnapshot: Sendable, Equatable {
    let index: ProtectionIndex
    let analysis: ProtectionAnalysis?

    var policies: [ProtectionPolicyItem] {
        var result: [ProtectionPolicyItem] = []
        result += index.policies.lifeInsurance.map { .init(type: .life, policy: $0) }
        result += index.policies.criticalIllness.map { .init(type: .criticalIllness, policy: $0) }
        result += index.policies.incomeProtection.map { .init(type: .incomeProtection, policy: $0) }
        result += index.policies.disability.map { .init(type: .disability, policy: $0) }
        result += index.policies.sicknessIllness.map { .init(type: .sicknessIllness, policy: $0) }
        return result
    }

    var totalLumpSumCover: Decimal? {
        index.coverageGaps?.totals.cover ?? analysis?.coverage.totalCoverage
    }
    var annualIncomeCover: Decimal? {
        index.coverageGaps?.categories
            .first { $0.key == "income_protection" }?.cover
            ?? analysis?.coverage.totalIncomeCoverage
    }
    var openGaps: [ProtectionGapSummary] {
        if let coverageGaps = index.coverageGaps {
            return coverageGaps.categories
                .filter { $0.status == "gap" && $0.shortfall > 0 }
                .map(ProtectionGapSummary.init)
        }
        return analysis?.gapSummaries ?? []
    }
    var calculatedAt: String? { index.coverageGaps?.calculatedAt }

    func policy(type: ProtectionPolicyType, id: Int) -> ProtectionPolicy? {
        policies.first { $0.type == type && $0.policy.id == id }?.policy
    }
}

struct ProtectionIndex: Decodable, Sendable, Equatable {
    let profile: ProtectionProfile
    let policies: ProtectionPolicyGroups
    let coverageGaps: ProtectionGapPresentation?

    private enum CodingKeys: String, CodingKey {
        case profile, policies
        case coverageGaps = "coverage_gaps"
    }
}

struct ProtectionGapPresentation: Decodable, Sendable, Equatable {
    let contractVersion: String
    let totals: ProtectionGapTotals
    let categories: [ProtectionGapCategory]
    let calculatedAt: String

    private enum CodingKeys: String, CodingKey {
        case contractVersion = "contract_version"
        case totals, categories
        case calculatedAt = "calculated_at"
    }
}

struct ProtectionGapTotals: Decodable, Sendable, Equatable {
    let need: Decimal
    let cover: Decimal
    let shortfall: Decimal
    let coveragePercentage: Decimal

    private enum CodingKeys: String, CodingKey {
        case need, cover, shortfall
        case coveragePercentage = "coverage_percentage"
    }
}

struct ProtectionGapCategory: Decodable, Sendable, Equatable {
    let key: String
    let label: String
    let need: Decimal
    let cover: Decimal
    let shortfall: Decimal
    let status: String
    let severity: String
    let inputs: [String: ProtectionJSONValue]
    let assumptions: [ProtectionGapAssumption]
    let explanation: String
    let relevantPolicies: [ProtectionGapPolicyReference]

    private enum CodingKeys: String, CodingKey {
        case key, label, need, cover, shortfall, status, severity, inputs, assumptions, explanation
        case relevantPolicies = "relevant_policies"
    }
}

struct ProtectionGapAssumption: Decodable, Sendable, Equatable {
    let key: String
    let value: ProtectionJSONValue
    let unit: String?
}

struct ProtectionGapPolicyReference: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let type: String
    let provider: String?
    let name: String?
    let cover: Decimal
}

enum ProtectionJSONValue: Decodable, Sendable, Equatable {
    case string(String)
    case number(Decimal)
    case bool(Bool)
    case array([ProtectionJSONValue])
    case object([String: ProtectionJSONValue])
    case null

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()
        if container.decodeNil() {
            self = .null
        } else if let value = try? container.decode(Bool.self) {
            self = .bool(value)
        } else if let value = try? container.decode(Decimal.self) {
            self = .number(value)
        } else if let value = try? container.decode(String.self) {
            self = .string(value)
        } else if let value = try? container.decode([ProtectionJSONValue].self) {
            self = .array(value)
        } else if let value = try? container.decode([String: ProtectionJSONValue].self) {
            self = .object(value)
        } else {
            throw DecodingError.dataCorruptedError(
                in: container,
                debugDescription: "Unsupported protection presentation value"
            )
        }
    }
}

struct ProtectionProfile: Decodable, Sendable, Equatable {
    let id: Int?
    let annualIncome: Decimal
    let mortgageBalance: Decimal
    let otherDebts: Decimal
    let numberOfDependents: Int?
    let hasNoPolicies: Bool?

    private enum CodingKeys: String, CodingKey {
        case id
        case annualIncome = "annual_income"
        case mortgageBalance = "mortgage_balance"
        case otherDebts = "other_debts"
        case numberOfDependents = "number_of_dependents"
        case hasNoPolicies = "has_no_policies"
    }
}

struct ProtectionPolicyGroups: Decodable, Sendable, Equatable {
    let lifeInsurance: [ProtectionPolicy]
    let criticalIllness: [ProtectionPolicy]
    let incomeProtection: [ProtectionPolicy]
    let disability: [ProtectionPolicy]
    let sicknessIllness: [ProtectionPolicy]

    private enum CodingKeys: String, CodingKey {
        case lifeInsurance = "life_insurance"
        case criticalIllness = "critical_illness"
        case incomeProtection = "income_protection"
        case disability
        case sicknessIllness = "sickness_illness"
    }
}

enum ProtectionPolicyType: String, Decodable, Sendable, Equatable {
    case life
    case criticalIllness
    case incomeProtection
    case disability
    case sicknessIllness

    var label: String {
        switch self {
        case .life: "Life Insurance"
        case .criticalIllness: "Critical Illness"
        case .incomeProtection: "Income Protection"
        case .disability: "Disability"
        case .sicknessIllness: "Sickness/Illness"
        }
    }

    var isLumpSum: Bool { self == .life || self == .criticalIllness }
}

struct ProtectionPolicyItem: Identifiable, Sendable, Equatable {
    let type: ProtectionPolicyType
    let policy: ProtectionPolicy

    var id: String { "\(type.rawValue)-\(policy.id)" }
}

struct ProtectionPolicy: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let policyType: String?
    let provider: String?
    let policyNumber: String?
    let sumAssured: Decimal?
    let benefitAmount: Decimal?
    let benefitFrequency: String?
    let premiumAmount: Decimal?
    let premiumFrequency: String?
    let deferredPeriodWeeks: Int?
    let benefitPeriodMonths: Int?
    let occupationClass: String?
    let coverageType: String?
    let isMortgageProtection: Bool?
    let inTrust: Bool?
    let policyStartDate: String?
    let policyEndDate: String?
    let policyTermYears: Int?
    let beneficiaries: String?
    let conditionsCovered: [String]?

    private enum CodingKeys: String, CodingKey {
        case id
        case policyType = "policy_type"
        case provider
        case policyNumber = "policy_number"
        case sumAssured = "sum_assured"
        case benefitAmount = "benefit_amount"
        case benefitFrequency = "benefit_frequency"
        case premiumAmount = "premium_amount"
        case premiumFrequency = "premium_frequency"
        case deferredPeriodWeeks = "deferred_period_weeks"
        case benefitPeriodMonths = "benefit_period_months"
        case occupationClass = "occupation_class"
        case coverageType = "coverage_type"
        case isMortgageProtection = "is_mortgage_protection"
        case inTrust = "in_trust"
        case policyStartDate = "policy_start_date"
        case policyEndDate = "policy_end_date"
        case policyTermYears = "policy_term_years"
        case beneficiaries
        case conditionsCovered = "conditions_covered"
    }

    func coverageAmount(for type: ProtectionPolicyType) -> Decimal? {
        type.isLumpSum ? sumAssured : benefitAmount
    }

    var annualPremium: Decimal? {
        guard let premiumAmount else { return nil }
        switch premiumFrequency {
        case "quarterly": return premiumAmount * 4
        case "annually", "annual", "yearly": return premiumAmount
        default: return premiumAmount * 12
        }
    }
}

struct ProtectionAnalysis: Decodable, Sendable, Equatable {
    let needs: ProtectionNeeds
    let coverage: ProtectionCoverage
    let gaps: ProtectionGaps
    let recommendations: [ProtectionRecommendation]

    var gapSummaries: [ProtectionGapSummary] {
        let values = gaps.gapsByCategory
        return [
            gap(
                id: "human-capital",
                label: "Life cover",
                shortfall: values.humanCapitalGap,
                have: gaps.coverageAllocated?.humanCapitalCovered,
                need: needs.humanCapital,
                perYear: false
            ),
            gap(
                id: "debt-protection",
                label: "Debt protection",
                shortfall: values.debtProtectionGap,
                have: gaps.coverageAllocated?.debtCovered,
                need: needs.debtProtection,
                perYear: false
            ),
            gap(
                id: "final-expenses",
                label: "Final expenses",
                shortfall: values.finalExpensesGap,
                have: gaps.coverageAllocated?.finalExpensesCovered,
                need: needs.finalExpenses,
                perYear: false
            ),
            gap(
                id: "education-funding",
                label: "Education funding",
                shortfall: values.educationFundingGap,
                have: gaps.coverageAllocated?.educationCovered,
                need: needs.educationFunding,
                perYear: false
            ),
            gap(
                id: "income-protection",
                label: "Income protection",
                shortfall: values.incomeProtectionGap,
                have: gaps.incomeReplacementCoverage,
                need: needs.incomeProtectionNeed,
                perYear: true
            ),
            gap(
                id: "disability",
                label: "Disability cover",
                shortfall: values.disabilityCoverageGap,
                have: coverage.disabilityCoverage,
                need: nil,
                perYear: true
            ),
            gap(
                id: "sickness-illness",
                label: "Sickness/illness cover",
                shortfall: values.sicknessIllnessGap,
                have: coverage.sicknessIllnessCoverage,
                need: nil,
                perYear: true
            ),
        ].compactMap { $0 }
    }

    private func gap(
        id: String,
        label: String,
        shortfall: Decimal?,
        have: Decimal?,
        need: Decimal?,
        perYear: Bool
    ) -> ProtectionGapSummary? {
        guard let shortfall, shortfall > 0 else { return nil }
        return ProtectionGapSummary(
            id: id,
            label: label,
            shortfall: shortfall,
            have: have,
            need: need,
            perYear: perYear
        )
    }
}

struct ProtectionNeeds: Decodable, Sendable, Equatable {
    let humanCapital: Decimal?
    let debtProtection: Decimal?
    let finalExpenses: Decimal?
    let educationFunding: Decimal?
    let incomeProtectionNeed: Decimal?

    private enum CodingKeys: String, CodingKey {
        case humanCapital = "human_capital"
        case debtProtection = "debt_protection"
        case finalExpenses = "final_expenses"
        case educationFunding = "education_funding"
        case incomeProtectionNeed = "income_protection_need"
    }
}

struct ProtectionCoverage: Decodable, Sendable, Equatable {
    let lifeCoverage: Decimal?
    let criticalIllnessCoverage: Decimal?
    let incomeProtectionCoverage: Decimal?
    let disabilityCoverage: Decimal?
    let sicknessIllnessCoverage: Decimal?
    let totalCoverage: Decimal?
    let totalIncomeCoverage: Decimal?

    private enum CodingKeys: String, CodingKey {
        case lifeCoverage = "life_coverage"
        case criticalIllnessCoverage = "critical_illness_coverage"
        case incomeProtectionCoverage = "income_protection_coverage"
        case disabilityCoverage = "disability_coverage"
        case sicknessIllnessCoverage = "sickness_illness_coverage"
        case totalCoverage = "total_coverage"
        case totalIncomeCoverage = "total_income_coverage"
    }
}

struct ProtectionGaps: Decodable, Sendable, Equatable {
    let totalNeed: Decimal?
    let totalCoverage: Decimal?
    let totalGap: Decimal?
    let gapsByCategory: ProtectionGapsByCategory
    let coverageAllocated: ProtectionCoverageAllocated?
    let incomeReplacementCoverage: Decimal?

    private enum CodingKeys: String, CodingKey {
        case totalNeed = "total_need"
        case totalCoverage = "total_coverage"
        case totalGap = "total_gap"
        case gapsByCategory = "gaps_by_category"
        case coverageAllocated = "coverage_allocated"
        case incomeReplacementCoverage = "income_replacement_coverage"
    }
}

struct ProtectionGapsByCategory: Decodable, Sendable, Equatable {
    let humanCapitalGap: Decimal?
    let debtProtectionGap: Decimal?
    let finalExpensesGap: Decimal?
    let educationFundingGap: Decimal?
    let incomeProtectionGap: Decimal?
    let disabilityCoverageGap: Decimal?
    let sicknessIllnessGap: Decimal?

    private enum CodingKeys: String, CodingKey {
        case humanCapitalGap = "human_capital_gap"
        case debtProtectionGap = "debt_protection_gap"
        case finalExpensesGap = "final_expenses_gap"
        case educationFundingGap = "education_funding_gap"
        case incomeProtectionGap = "income_protection_gap"
        case disabilityCoverageGap = "disability_coverage_gap"
        case sicknessIllnessGap = "sickness_illness_gap"
    }
}

struct ProtectionCoverageAllocated: Decodable, Sendable, Equatable {
    let debtCovered: Decimal?
    let humanCapitalCovered: Decimal?
    let finalExpensesCovered: Decimal?
    let educationCovered: Decimal?

    private enum CodingKeys: String, CodingKey {
        case debtCovered = "debt_covered"
        case humanCapitalCovered = "human_capital_covered"
        case finalExpensesCovered = "final_expenses_covered"
        case educationCovered = "education_covered"
    }
}

struct ProtectionRecommendation: Decodable, Sendable, Equatable {
    let priority: Int?
    let category: String?
    let action: String?
    let rationale: String?
}

struct ProtectionGapSummary: Identifiable, Sendable, Equatable {
    let id: String
    let label: String
    let shortfall: Decimal
    let have: Decimal?
    let need: Decimal?
    let perYear: Bool
    let severity: String
    let explanation: String
    let inputs: [String: ProtectionJSONValue]
    let assumptions: [ProtectionGapAssumption]
    let relevantPolicies: [ProtectionGapPolicyReference]

    init(
        id: String,
        label: String,
        shortfall: Decimal,
        have: Decimal?,
        need: Decimal?,
        perYear: Bool,
        severity: String = "unknown",
        explanation: String = "",
        inputs: [String: ProtectionJSONValue] = [:],
        assumptions: [ProtectionGapAssumption] = [],
        relevantPolicies: [ProtectionGapPolicyReference] = []
    ) {
        self.id = id
        self.label = label
        self.shortfall = shortfall
        self.have = have
        self.need = need
        self.perYear = perYear
        self.severity = severity
        self.explanation = explanation
        self.inputs = inputs
        self.assumptions = assumptions
        self.relevantPolicies = relevantPolicies
    }

    init(_ category: ProtectionGapCategory) {
        self.init(
            id: category.key,
            label: category.label,
            shortfall: category.shortfall,
            have: category.cover,
            need: category.need,
            perYear: category.key == "income_protection",
            severity: category.severity,
            explanation: category.explanation,
            inputs: category.inputs,
            assumptions: category.assumptions,
            relevantPolicies: category.relevantPolicies
        )
    }
}

enum ProtectionViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(ProtectionSnapshot)
    case offline(previous: ProtectionSnapshot?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
