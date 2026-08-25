import Foundation

struct IncomeProfile: Decodable, Sendable, Equatable {
    let incomeSummary: IncomeSummary

    private enum CodingKeys: String, CodingKey {
        case incomeSummary = "income_summary"
    }
}

struct IncomeSummary: Decodable, Sendable, Equatable {
    let user: IncomeSources
    let spouse: IncomeSources?
}

struct IncomeSources: Decodable, Sendable, Equatable {
    let employment: Decimal
    let selfEmployment: Decimal
    let dividend: Decimal
    let interest: Decimal
    let other: Decimal
    let total: Decimal
    let employer: String?
    let occupation: String?
    let sources: [IncomeSourceRow]
    let taxPosition: IncomeTaxPosition

    private enum CodingKeys: String, CodingKey {
        case employment
        case selfEmployment = "self_employment"
        case dividend
        case interest
        case other
        case total
        case employer
        case occupation
        case sources
        case taxPosition = "tax_position"
    }

    var employmentDetail: String? {
        let detail = [employer, occupation]
            .compactMap { value in
                value?.trimmingCharacters(in: .whitespacesAndNewlines)
            }
            .filter { !$0.isEmpty }
            .joined(separator: " · ")
        return detail.isEmpty ? nil : detail
    }

    var nonZeroSources: [IncomeSourceRow] {
        if !sources.isEmpty { return sources }
        return [
            IncomeSourceRow(
                key: "employment",
                label: "Employment",
                amount: employment,
                frequency: "annual",
                ownership: "user",
                ownershipLabel: "You",
                detail: employmentDetail,
                taxPosition: "Taxable earned income"
            ),
            IncomeSourceRow(
                key: "self_employment", label: "Self-employment", amount: selfEmployment,
                frequency: "annual", ownership: "user", ownershipLabel: "You",
                taxPosition: "Taxable earned income"
            ),
            IncomeSourceRow(
                key: "dividend", label: "Dividends", amount: dividend,
                frequency: "annual", ownership: "user", ownershipLabel: "You",
                taxPosition: "Dividend income"
            ),
            IncomeSourceRow(
                key: "interest", label: "Interest", amount: interest,
                frequency: "annual", ownership: "user", ownershipLabel: "You",
                taxPosition: "Savings income"
            ),
            IncomeSourceRow(
                key: "other", label: "Other", amount: other,
                frequency: "annual", ownership: "user", ownershipLabel: "You",
                taxPosition: "Other taxable income"
            ),
        ].filter { $0.amount > 0 }
    }

    var sourceTotal: Decimal {
        employment + selfEmployment + dividend + interest + other
    }
}

struct IncomeSourceRow: Decodable, Identifiable, Sendable, Equatable {
    let key: String
    let label: String
    let amount: Decimal
    let frequency: String
    let ownership: String
    let ownershipLabel: String
    let detail: String?
    let taxPosition: String

    var id: String { key }
    var title: String { label }

    init(
        key: String,
        label: String,
        amount: Decimal,
        frequency: String,
        ownership: String,
        ownershipLabel: String,
        detail: String? = nil,
        taxPosition: String
    ) {
        self.key = key
        self.label = label
        self.amount = amount
        self.frequency = frequency
        self.ownership = ownership
        self.ownershipLabel = ownershipLabel
        self.detail = detail
        self.taxPosition = taxPosition
    }

    private enum CodingKeys: String, CodingKey {
        case key, label, amount, frequency, ownership, detail
        case ownershipLabel = "ownership_label"
        case taxPosition = "tax_position"
    }
}

struct IncomeTaxPosition: Decodable, Sendable, Equatable {
    let totalIncome: Decimal
    let adjustedNetIncome: Decimal?
    let personalAllowance: Decimal?
    let personalAllowanceLabel: String
    let pensionAnnualAllowance: Decimal?
    let pensionAnnualAllowanceLabel: String

    private enum CodingKeys: String, CodingKey {
        case totalIncome = "total_income"
        case adjustedNetIncome = "adjusted_net_income"
        case personalAllowance = "personal_allowance"
        case personalAllowanceLabel = "personal_allowance_label"
        case pensionAnnualAllowance = "pension_annual_allowance"
        case pensionAnnualAllowanceLabel = "pension_annual_allowance_label"
    }
}

enum IncomeViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(IncomeSummary)
    case offline(previous: IncomeSummary?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
