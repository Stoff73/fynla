import Foundation

struct ExpenditureProfile: Decodable, Sendable, Equatable {
    let expenditure: ExpenditureSummary
}

struct ExpenditureSummary: Decodable, Sendable, Equatable {
    let monthly: Decimal?
    let annual: Decimal?
    let categories: ExpenditureCategories?
    let presentation: ExpenditurePresentation

    private enum CodingKeys: String, CodingKey {
        case monthly = "monthly_expenditure"
        case annual = "annual_expenditure"
        case categories
        case presentation
    }
}

struct ExpenditurePresentation: Decodable, Sendable, Equatable {
    let entryMode: String
    let entryModeLabel: String
    let activeMonthlyTotal: Decimal
    let activeAnnualTotal: Decimal
    let manualMonthlyTotal: Decimal
    let commitmentsMonthlyTotal: Decimal
    let totalBasis: String
    let detailAvailable: Bool
    let reconciles: Bool
    let summaryOnlyReason: String?

    private enum CodingKeys: String, CodingKey {
        case entryMode = "entry_mode"
        case entryModeLabel = "entry_mode_label"
        case activeMonthlyTotal = "active_monthly_total"
        case activeAnnualTotal = "active_annual_total"
        case manualMonthlyTotal = "manual_monthly_total"
        case commitmentsMonthlyTotal = "commitments_monthly_total"
        case totalBasis = "total_basis"
        case detailAvailable = "detail_available"
        case reconciles
        case summaryOnlyReason = "summary_only_reason"
    }
}

struct ExpenditureCategories: Decodable, Sendable, Equatable {
    let foodGroceries: Decimal?
    let transportFuel: Decimal?
    let clothingPersonalCare: Decimal?
    let entertainmentDining: Decimal?
    let childcare: Decimal?
    let other: Decimal?

    private enum CodingKeys: String, CodingKey {
        case foodGroceries = "food_groceries"
        case transportFuel = "transport_fuel"
        case clothingPersonalCare = "clothing_personal_care"
        case entertainmentDining = "entertainment_dining"
        case childcare
        case other = "other_expenditure"
    }

    var nonZeroRows: [ExpenditureCategoryRow] {
        [
            row(.foodGroceries, "Food & groceries", foodGroceries),
            row(.transportFuel, "Transport & fuel", transportFuel),
            row(
                .clothingPersonalCare,
                "Clothing & personal care",
                clothingPersonalCare
            ),
            row(
                .entertainmentDining,
                "Entertainment & dining",
                entertainmentDining
            ),
            row(.childcare, "Childcare", childcare),
            row(.other, "Other", other),
        ].compactMap { $0 }
    }

    private func row(
        _ id: ExpenditureCategoryRow.ID,
        _ title: String,
        _ amount: Decimal?
    ) -> ExpenditureCategoryRow? {
        guard let amount, amount > 0 else { return nil }
        return ExpenditureCategoryRow(id: id, title: title, amount: amount)
    }
}

struct ExpenditureCategoryRow: Identifiable, Sendable, Equatable {
    enum ID: String, Sendable {
        case foodGroceries
        case transportFuel
        case clothingPersonalCare
        case entertainmentDining
        case childcare
        case other
    }

    let id: ID
    let title: String
    let amount: Decimal
}

enum ExpenditureViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(ExpenditureSummary)
    case offline(previous: ExpenditureSummary?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
