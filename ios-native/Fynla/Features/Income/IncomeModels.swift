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

    private enum CodingKeys: String, CodingKey {
        case employment
        case selfEmployment = "self_employment"
        case dividend
        case interest
        case other
        case total
        case employer
        case occupation
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
        [
            IncomeSourceRow(
                id: .employment,
                title: "Employment",
                amount: employment,
                detail: employmentDetail
            ),
            IncomeSourceRow(
                id: .selfEmployment,
                title: "Self-employment",
                amount: selfEmployment
            ),
            IncomeSourceRow(
                id: .dividend,
                title: "Dividends",
                amount: dividend
            ),
            IncomeSourceRow(
                id: .interest,
                title: "Interest",
                amount: interest
            ),
            IncomeSourceRow(
                id: .other,
                title: "Other",
                amount: other
            ),
        ].filter { $0.amount > 0 }
    }

    var sourceTotal: Decimal {
        employment + selfEmployment + dividend + interest + other
    }
}

struct IncomeSourceRow: Identifiable, Sendable, Equatable {
    enum ID: String, Sendable {
        case employment
        case selfEmployment
        case dividend
        case interest
        case other
    }

    let id: ID
    let title: String
    let amount: Decimal
    let detail: String?

    init(id: ID, title: String, amount: Decimal, detail: String? = nil) {
        self.id = id
        self.title = title
        self.amount = amount
        self.detail = detail
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
