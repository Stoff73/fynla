import Foundation

struct BalanceHistoryResponse: Decodable, Sendable, Equatable {
    let data: BalanceHistory
}

struct BalanceHistory: Decodable, Sendable, Equatable {
    let visibility: BalanceHistoryVisibility
    let totals: [BalanceHistoryTotal]
    let yearOnYear: BalanceHistoryYearOnYear?

    private enum CodingKeys: String, CodingKey {
        case visibility
        case totals
        case yearOnYear = "year_on_year"
    }

    var latest: BalanceHistoryTotal? { totals.last }
}

struct BalanceHistoryVisibility: Decodable, Sendable, Equatable {
    let windowDays: Int?
    let label: String

    private enum CodingKeys: String, CodingKey {
        case windowDays = "window_days"
        case label
    }
}

struct BalanceHistoryTotal: Decodable, Sendable, Equatable, Identifiable {
    let date: String
    let assets: Decimal
    let liabilities: Decimal
    let netWorth: Decimal

    var id: String { date }

    private enum CodingKeys: String, CodingKey {
        case date
        case assets
        case liabilities
        case netWorth = "net_worth"
    }
}

struct BalanceHistoryYearOnYear: Decodable, Sendable, Equatable {
    let asAt: String
    let comparedWith: String
    let currency: String
    let netWorthChange: Decimal
    let percentageChange: Decimal?

    private enum CodingKeys: String, CodingKey {
        case asAt = "as_at"
        case comparedWith = "compared_with"
        case currency
        case netWorthChange = "net_worth_change"
        case percentageChange = "percentage_change"
    }
}

enum BalanceHistoryViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(BalanceHistory)
    case offline(previous: BalanceHistory?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
