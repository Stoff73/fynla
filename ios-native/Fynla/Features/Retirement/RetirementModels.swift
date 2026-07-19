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
                valueLabel: MoneyFormatter.gbp($0.currentFundValue)
            )
        }
        result += index.dbPensions.map {
            RetirementPensionListItem(
                id: "db-\($0.id)",
                type: .db,
                pensionID: $0.id,
                name: $0.displayName,
                typeLabel: "Defined Benefit",
                valueLabel: "\(MoneyFormatter.gbp($0.accruedAnnualPension)) a year"
            )
        }
        if let state = index.statePension {
            result.append(RetirementPensionListItem(
                id: "state-\(state.id ?? 0)",
                type: .state,
                pensionID: state.id,
                name: "State Pension",
                typeLabel: "State Pension",
                valueLabel: "\(MoneyFormatter.gbp(state.annualForecast)) a year"
            ))
        }
        return result
    }

    var totalDCPensionWealth: Decimal {
        index.dcPensions.reduce(0) { $0 + $1.currentFundValue }
    }
    var projectedIncome: Decimal? {
        if let analysis { return analysis.projectedIncome }
        return projections?.incomeDrawdown?.yearlyIncome.first?.totalIncome
    }
    var targetIncome: Decimal? {
        if let target = analysis?.targetIncome, target > 0 { return target }
        if let target = index.profile?.targetRetirementIncome, target > 0 { return target }
        return nil
    }
    var incomeGap: Decimal? {
        guard let targetIncome, let projectedIncome else { return nil }
        return targetIncome - projectedIncome
    }
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
    let retirementAge: Int?

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
        case retirementAge = "retirement_age"
    }

    var displayName: String { schemeName ?? provider ?? "Defined Contribution Pension" }
    var monthlyContribution: Decimal {
        if let employeeContributionPercent,
           employeeContributionPercent > 0,
           let annualSalary,
           annualSalary > 0
        {
            let totalPercent = employeeContributionPercent + (employerContributionPercent ?? 0)
            return totalPercent * annualSalary / 100 / 12
        }
        return monthlyContributionAmount ?? 0
    }
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

    private enum CodingKeys: String, CodingKey {
        case id
        case annualForecast = "state_pension_forecast_annual"
        case niYearsCompleted = "ni_years_completed"
        case niYearsRequired = "ni_years_required"
        case statePensionAge = "state_pension_age"
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

    private enum CodingKeys: String, CodingKey {
        case pensionPotProjection = "pension_pot_projection"
        case incomeDrawdown = "income_drawdown"
    }
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
