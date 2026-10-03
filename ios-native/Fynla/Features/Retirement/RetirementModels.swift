import Foundation

struct RetirementSnapshot: Sendable, Equatable {
    let index: RetirementIndex
    let analysis: RetirementAnalysis?
    let projections: RetirementProjections?

    var pensions: [RetirementPensionListItem] {
        var result = index.dcPensions.map {
            RetirementPensionListItem(
                id: "dc-\($0.id)",
                type: .dc,
                pensionID: $0.id,
                name: $0.displayName,
                typeLabel: "Defined Contribution",
                valueLabel: MoneyFormatter.gbpWhole($0.currentFundValue)
            )
        }
        result += index.dbPensions.map {
            RetirementPensionListItem(
                id: "db-\($0.id)",
                type: .db,
                pensionID: $0.id,
                name: $0.displayName,
                typeLabel: "Defined Benefit",
                valueLabel: "\(MoneyFormatter.gbpWhole($0.accruedAnnualPension)) a year"
            )
        }
        if let state = index.statePension {
            result.append(RetirementPensionListItem(
                id: "state-\(state.id ?? 0)",
                type: .state,
                pensionID: state.id,
                name: "State Pension",
                typeLabel: "State Pension",
                valueLabel: "\(MoneyFormatter.gbpWhole(state.annualForecast)) a year"
            ))
        }
        return result
    }

    // Every retirement figure is the server's RetirementHeadline, shown as sent
    // (CSJ 2026-10-01: one figure, every surface). Nothing here adds, subtracts
    // or falls back between fields.
    var headline: RetirementHeadline? { projections?.headline }
    var totalDCPensionWealth: Decimal { headline?.dcValueToday ?? 0 }
    var projectedIncome: Decimal? { headline?.projectedIncome }
    var targetIncome: Decimal? { headline?.targetIncome }
    /// Signed: positive is short, negative is over.
    var incomeGap: Decimal? { headline?.incomeGap }
    var heroValue: Decimal? { headline?.value }
    var heroIsGuaranteed: Bool { headline?.kind == "guaranteed" }
    var yearsToRetirement: Int? { headline?.yearsToRetirement }
    /// Someone drawing their pension: this year's income and how long the pot
    /// lasts, the server's drawdown_position, as web and /m show it (TODO item 6;
    /// audit item 13, CSJ 2026-10-01).
    var drawing: RetirementDrawdownPosition? { projections?.drawdownPosition }
    var isAtAccountLimit: Bool {
        guard let limit = index.accountLimit else { return false }
        return index.accountCount >= limit
    }
}

struct RetirementIndex: Decodable, Sendable, Equatable {
    let profile: RetirementProfile?
    let dcPensions: [DCPension]
    let dbPensions: [DBPension]
    let statePension: StatePension?
    let accountCount: Int
    let accountLimit: Int?

    private enum CodingKeys: String, CodingKey {
        case profile
        case dcPensions = "dc_pensions"
        case dbPensions = "db_pensions"
        case statePension = "state_pension"
        case accountCount = "account_count"
        case accountLimit = "account_limit"
    }
}

struct RetirementProfile: Decodable, Sendable, Equatable {
    let targetRetirementAge: Int?
    let currentAge: Int?
    let targetRetirementIncome: Decimal?

    private enum CodingKeys: String, CodingKey {
        case targetRetirementAge = "target_retirement_age"
        case currentAge = "current_age"
        case targetRetirementIncome = "target_retirement_income"
    }
}

struct DCPension: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let schemeName: String?
    let pensionType: String?
    let schemeType: String?
    let provider: String?
    let currentFundValue: Decimal
    let employeeContributionPercent: Decimal?
    let employerContributionPercent: Decimal?
    let annualSalary: Decimal?
    let monthlyContributionAmount: Decimal?
    /// The server's monthly figure (DCPension `monthly_contribution`, PensionContributionRule).
    let serverMonthlyContribution: Decimal?
    let retirementAge: Int?
    let portfolio: CanonicalPortfolio?

    private enum CodingKeys: String, CodingKey {
        case id
        case schemeName = "scheme_name"
        case pensionType = "pension_type"
        case schemeType = "scheme_type"
        case provider
        case currentFundValue = "current_fund_value"
        case employeeContributionPercent = "employee_contribution_percent"
        case employerContributionPercent = "employer_contribution_percent"
        case annualSalary = "annual_salary"
        case monthlyContributionAmount = "monthly_contribution_amount"
        case serverMonthlyContribution = "monthly_contribution"
        case retirementAge = "retirement_age"
        case portfolio
    }

    var displayName: String { schemeName ?? provider ?? "Defined Contribution Pension" }
    var monthlyContribution: Decimal { serverMonthlyContribution ?? 0 }
}

struct DBPension: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let schemeName: String?
    let schemeType: String?
    let accruedAnnualPension: Decimal
    let normalRetirementAge: Int?
    let lumpSumEntitlement: Decimal?
    let spousePensionPercent: Decimal?

    private enum CodingKeys: String, CodingKey {
        case id
        case schemeName = "scheme_name"
        case schemeType = "scheme_type"
        case accruedAnnualPension = "accrued_annual_pension"
        case normalRetirementAge = "normal_retirement_age"
        case lumpSumEntitlement = "lump_sum_entitlement"
        case spousePensionPercent = "spouse_pension_percent"
    }

    var displayName: String { schemeName ?? "Defined Benefit Pension" }
}

struct StatePension: Decodable, Sendable, Equatable {
    let id: Int?
    let annualForecast: Decimal
    let niYearsCompleted: Int?
    let niYearsRequired: Int?
    let statePensionAge: Int?
    /// Server-computed (StatePension appends), never derived here.
    let weeklyForecast: Decimal?
    let niYearsForFullPension: Int?
    let niYearsNeeded: Int?
    let resolvedStatePensionAge: Int?
    /// The same age to the month ("66 years and 5 months"), from the server.
    let resolvedStatePensionAgeLabel: String?

    private enum CodingKeys: String, CodingKey {
        case id
        case annualForecast = "state_pension_forecast_annual"
        case niYearsCompleted = "ni_years_completed"
        case niYearsRequired = "ni_years_required"
        case statePensionAge = "state_pension_age"
        case weeklyForecast = "weekly_forecast"
        case niYearsForFullPension = "ni_years_for_full_pension"
        case niYearsNeeded = "ni_years_needed"
        case resolvedStatePensionAge = "resolved_state_pension_age"
        case resolvedStatePensionAgeLabel = "resolved_state_pension_age_label"
    }
}

enum RetirementPensionType: String, Sendable {
    case dc
    case db
    case state
}

struct RetirementPensionListItem: Identifiable, Sendable, Equatable {
    let id: String
    let type: RetirementPensionType
    let pensionID: Int?
    let name: String
    let typeLabel: String
    let valueLabel: String
}

struct RetirementAnalysis: Decodable, Sendable, Equatable {
    let projectedIncome: Decimal
    let targetIncome: Decimal
    let incomeGap: Decimal?
    let yearsToRetirement: Int?
    let totalPensionWealth: Decimal?
    let recommendations: [RetirementRecommendation]

    private enum CodingKeys: String, CodingKey {
        case projectedIncome = "projected_income"
        case targetIncome = "target_income"
        case incomeGap = "income_gap"
        case yearsToRetirement = "years_to_retirement"
        case totalPensionWealth = "total_pension_wealth"
        case recommendations
    }
}

struct RetirementRecommendation: Decodable, Sendable, Equatable {
    let type: String?
    let title: String?
    let action: String?
    let description: String?
}

struct RetirementProjections: Decodable, Sendable, Equatable {
    let pensionPotProjection: RetirementPotProjection?
    let incomeDrawdown: RetirementIncomeDrawdown?
    let planningProjection: RetirementPlanningProjection?
    let headline: RetirementHeadline?
    let drawdownPosition: RetirementDrawdownPosition?

    private enum CodingKeys: String, CodingKey {
        case pensionPotProjection = "pension_pot_projection"
        case incomeDrawdown = "income_drawdown"
        case planningProjection = "planning_projection"
        case headline
        case drawdownPosition = "drawdown_position"
    }
}

/// RetirementDrawdownPosition (app/Services/Retirement/RetirementDrawdownPosition.php):
/// the drawing view's figures and words, built on the server.
struct RetirementDrawdownPosition: Decodable, Sendable, Equatable {
    struct RetiredSince: Decodable, Sendable, Equatable {
        let date: String
        let age: Int?
    }

    struct IncomeLine: Decodable, Sendable, Equatable, Identifiable {
        let key: String
        let label: String
        let amount: Decimal
        var id: String { key }
    }

    struct Income: Decodable, Sendable, Equatable {
        let lines: [IncomeLine]
        let statePensionStatus: String?
        let total: Decimal
        let incomeTax: Decimal
        let nationalInsurance: Decimal
        let takeHome: Decimal

        private enum CodingKeys: String, CodingKey {
            case lines, total
            case statePensionStatus = "state_pension_status"
            case incomeTax = "income_tax"
            case nationalInsurance = "national_insurance"
            case takeHome = "take_home"
        }
    }

    struct Labels: Decodable, Sendable, Equatable {
        let middle: String
        let lower: String
    }

    struct LifeExpectancy: Decodable, Sendable, Equatable {
        let age: Int
        let source: String
    }

    struct Pot: Decodable, Sendable, Equatable {
        let value: Decimal
        let drawingPerYear: Decimal
        let riskLevelLabel: String
        let expectedReturn: Decimal
        let lastsLabels: Labels
        let lifeExpectancy: LifeExpectancy
        let incomeToLastToLifeExpectancy: Decimal?

        private enum CodingKeys: String, CodingKey {
            case value
            case drawingPerYear = "drawing_per_year"
            case riskLevelLabel = "risk_level_label"
            case expectedReturn = "expected_return"
            case lastsLabels = "lasts_labels"
            case lifeExpectancy = "life_expectancy"
            case incomeToLastToLifeExpectancy = "income_to_last_to_life_expectancy"
        }
    }

    let retiredSince: RetiredSince?
    let income: Income
    let pot: Pot?

    private enum CodingKeys: String, CodingKey {
        case income, pot
        case retiredSince = "retired_since"
    }
}

/// RetirementHeadline (app/Services/Retirement/RetirementHeadline.php): the
/// retirement figures every surface shows, computed once on the server.
struct RetirementHeadline: Decodable, Sendable, Equatable {
    let kind: String
    let value: Decimal
    let projectedIncome: Decimal
    let guaranteedIncome: Decimal
    let targetIncome: Decimal?
    let targetSource: String?
    let incomeGap: Decimal?
    let progressPercent: Int?
    let targetAge: Int
    let yearsToRetirement: Int?
    let dcValueToday: Decimal
    let dcValueAtRetirement: Decimal?
    let requiredCapital: Decimal?

    private enum CodingKeys: String, CodingKey {
        case kind
        case value
        case projectedIncome = "projected_income"
        case guaranteedIncome = "guaranteed_income"
        case targetIncome = "target_income"
        case targetSource = "target_source"
        case incomeGap = "income_gap"
        case progressPercent = "progress_percent"
        case targetAge = "target_age"
        case yearsToRetirement = "years_to_retirement"
        case dcValueToday = "dc_value_today"
        case dcValueAtRetirement = "dc_value_at_retirement"
        case requiredCapital = "required_capital"
    }
}

struct RetirementPlanningProjection: Decodable, Sendable, Equatable {
    let contractVersion: String
    let asOf: String
    let targetRetirementAge: Int
    let projectionEndAge: Int
    let planningTotalAtTargetAge: Decimal
    let products: [RetirementProjectionProduct]
    let ageBands: [RetirementIncomeBand]
    let assumptions: RetirementProjectionAssumptions
    let warnings: [String]

    private enum CodingKeys: String, CodingKey {
        case contractVersion = "contract_version"
        case asOf = "as_of"
        case targetRetirementAge = "target_retirement_age"
        case projectionEndAge = "projection_end_age"
        case planningTotalAtTargetAge = "planning_total_at_target_age"
        case products
        case ageBands = "age_bands"
        case assumptions
        case warnings
    }
}

struct RetirementProjectionProduct: Decodable, Sendable, Equatable, Identifiable {
    let resourceType: String
    let resourceID: Int
    let name: String
    let commencementAge: Int
    /// The age in words from the server (State Pension to the month).
    let commencementAgeLabel: String?
    let currentValue: Decimal?
    let monthlyContribution: Decimal?
    let projectedValue: Decimal?
    let annualIncome: Decimal
    let incomeMethod: String

    var id: String { "\(resourceType):\(resourceID)" }

    private enum CodingKeys: String, CodingKey {
        case resourceType = "resource_type"
        case resourceID = "resource_id"
        case name
        case commencementAge = "commencement_age"
        case commencementAgeLabel = "commencement_age_label"
        case currentValue = "current_value"
        case monthlyContribution = "monthly_contribution"
        case projectedValue = "projected_value"
        case annualIncome = "annual_income"
        case incomeMethod = "income_method"
    }
}

struct RetirementIncomeBand: Decodable, Sendable, Equatable, Identifiable {
    let startAge: Int
    let endAge: Int
    let annualIncome: Decimal
    let sourceIDs: [String]

    var id: String { "\(startAge)-\(endAge)" }
    var rangeLabel: String { "Age \(startAge)–\(endAge)" }
    var accessibilityIdentifier: String { "retirement.age-band.\(startAge)-\(endAge)" }

    private enum CodingKeys: String, CodingKey {
        case startAge = "start_age"
        case endAge = "end_age"
        case annualIncome = "annual_income"
        case sourceIDs = "source_ids"
    }
}

struct RetirementProjectionAssumptions: Decodable, Sendable, Equatable {
    let sustainableWithdrawalRate: RetirementWithdrawalRateAssumption
    let growthRatePercent: Decimal
    let netGrowthRatePercent: Decimal
    let inflationRatePercent: Decimal
    let feeRatePercent: Decimal
    let compoundPeriods: Int
    let basis: String
    let hasUserOverrides: Bool

    private enum CodingKeys: String, CodingKey {
        case sustainableWithdrawalRate = "sustainable_withdrawal_rate"
        case growthRatePercent = "growth_rate_percent"
        case netGrowthRatePercent = "net_growth_rate_percent"
        case inflationRatePercent = "inflation_rate_percent"
        case feeRatePercent = "fee_rate_percent"
        case compoundPeriods = "compound_periods"
        case basis
        case hasUserOverrides = "has_user_overrides"
    }
}

struct RetirementWithdrawalRateAssumption: Decodable, Sendable, Equatable {
    let decimal: Decimal
    let percent: Decimal
    let source: String
}

struct RetirementPotProjection: Decodable, Sendable, Equatable {
    let currentValue: Decimal?
    let monthlyContribution: Decimal?
    let percentile20AtRetirement: Decimal?
    let medianAtRetirement: Decimal?
    let retirementAge: Int?
    let yearsToRetirement: Int?
    let expectedReturn: Decimal?
    let retirementAgeSource: String?
    let currentAge: Int?
    let currentAgeSource: String?

    private enum CodingKeys: String, CodingKey {
        case currentValue = "current_value"
        case monthlyContribution = "monthly_contribution"
        case percentile20AtRetirement = "percentile_20_at_retirement"
        case medianAtRetirement = "median_at_retirement"
        case retirementAge = "retirement_age"
        case yearsToRetirement = "years_to_retirement"
        case expectedReturn = "expected_return"
        case retirementAgeSource = "retirement_age_source"
        case currentAge = "current_age"
        case currentAgeSource = "current_age_source"
    }
}

struct RetirementIncomeDrawdown: Decodable, Sendable, Equatable {
    let yearlyIncome: [RetirementYearlyIncome]

    private enum CodingKeys: String, CodingKey {
        case yearlyIncome = "yearly_income"
    }
}

struct RetirementYearlyIncome: Decodable, Sendable, Equatable {
    let totalIncome: Decimal

    private enum CodingKeys: String, CodingKey {
        case totalIncome = "total_income"
    }
}

enum RetirementViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(RetirementSnapshot)
    case offline(previous: RetirementSnapshot?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
