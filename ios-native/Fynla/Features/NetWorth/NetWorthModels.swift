import Foundation

struct NetWorthOverview: Decodable, Sendable, Equatable {
    let totalAssets: Decimal
    let totalLiabilities: Decimal
    let netWorth: Decimal
    let asOfDate: String
    let breakdown: NetWorthBreakdown
    let hasDBPensions: Bool
    let liabilitiesBreakdown: NetWorthLiabilitiesBreakdown

    private enum CodingKeys: String, CodingKey {
        case totalAssets = "total_assets"
        case totalLiabilities = "total_liabilities"
        case netWorth = "net_worth"
        case asOfDate = "as_of_date"
        case breakdown
        case hasDBPensions = "has_db_pensions"
        case liabilitiesBreakdown = "liabilities_breakdown"
    }
}

struct NetWorthBreakdown: Decodable, Sendable, Equatable {
    let pensions: Decimal
    let property: Decimal
    let investments: Decimal
    let cash: Decimal
    let business: Decimal
    let chattels: Decimal
}

struct NetWorthLiabilitiesBreakdown: Decodable, Sendable, Equatable {
    let mortgages: Decimal
    let loans: Decimal
    let creditCards: Decimal
    let other: Decimal

    private enum CodingKeys: String, CodingKey {
        case mortgages
        case loans
        case creditCards = "credit_cards"
        case other
    }
}

struct NetWorthDetailedAssets: Decodable, Sendable, Equatable {
    let pensions: NetWorthAssetSection
    let property: NetWorthAssetSection
    let investments: NetWorthAssetSection
    let cash: NetWorthAssetSection
    let business: NetWorthAssetSection
    let chattels: NetWorthAssetSection
    let liabilities: NetWorthLiabilitySection?
}

struct NetWorthAssetSection: Decodable, Sendable, Equatable {
    let count: Int
    let totalValue: Decimal
    let items: [NetWorthAssetItem]

    private enum CodingKeys: String, CodingKey {
        case count
        case totalValue = "total_value"
        case items
    }
}

struct NetWorthAssetItem: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let name: String
    let value: Decimal
    let type: String?
    let provider: String?
    let accountType: String?
    let institution: String?
    let ownershipType: String?
    let ownershipPercentage: Decimal?
    let annualPension: Decimal?
    let isISA: Bool?
    let isEmergencyFund: Bool?
    let businessType: String?
    let chattelType: String?
    let year: Int?
    let outstandingMortgage: Decimal?

    private enum CodingKeys: String, CodingKey {
        case id
        case name
        case value
        case type
        case provider
        case accountType = "account_type"
        case institution
        case ownershipType = "ownership_type"
        case ownershipPercentage = "ownership_percentage"
        case annualPension = "annual_pension"
        case isISA = "is_isa"
        case isEmergencyFund = "is_emergency_fund"
        case businessType = "business_type"
        case chattelType = "chattel_type"
        case year
        case outstandingMortgage = "outstanding_mortgage"
    }

    func detailRoute(for category: NetWorthCategory) -> AppRoute? {
        switch category {
        case .property: .propertyDetail(id: id)
        case .investments: .investment(accountID: id)
        case .pensions:
            type.map { .retirement(pensionType: $0, id: id) }
        case .cash: .savings(accountID: id)
        case .business, .chattels, .liabilities: nil
        }
    }
}

struct NetWorthLiabilitySection: Decodable, Sendable, Equatable {
    let count: Int
    let totalValue: Decimal
    let items: [NetWorthDebtItem]

    private enum CodingKeys: String, CodingKey {
        case count
        case totalValue = "total_value"
        case items
    }
}

struct NetWorthDebtItem: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let kind: String
    let name: String
    let value: Decimal
    let propertyID: Int?
    let liabilityType: String?
    let ownershipType: String?

    private enum CodingKeys: String, CodingKey {
        case id, kind, name, value
        case propertyID = "property_id"
        case liabilityType = "liability_type"
        case ownershipType = "ownership_type"
    }

    var detailRoute: AppRoute? {
        switch kind {
        case "mortgage": .mortgageDetail(id: id)
        case "liability": .liabilityDetail(id: id)
        default: nil
        }
    }
}

struct PropertyDetailResponse: Decodable, Sendable, Equatable {
    let property: PropertyDetail
}

struct PropertyDetail: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let addressLine1: String?
    let propertyType: String?
    let currentValue: Decimal?
    let purchasePrice: Decimal?
    let purchaseDate: String?
    let valuationDate: String?
    let ownershipType: String?
    let ownershipPercentage: Decimal?
    let equity: Decimal?
    let outstandingMortgage: Decimal?
    let isPrimaryOwner: Bool?
    let mortgages: [PropertyMortgageSummary]?

    private enum CodingKeys: String, CodingKey {
        case id
        case addressLine1 = "address_line_1"
        case propertyType = "property_type"
        case currentValue = "current_value"
        case purchasePrice = "purchase_price"
        case purchaseDate = "purchase_date"
        case valuationDate = "valuation_date"
        case ownershipType = "ownership_type"
        case ownershipPercentage = "ownership_percentage"
        case equity
        case outstandingMortgage = "outstanding_mortgage"
        case isPrimaryOwner = "is_primary_owner"
        case mortgages
    }

    var displayName: String { addressLine1 ?? "Property" }
    var detailRoute: AppRoute { .propertyDetail(id: id) }
}

struct PropertyMortgageSummary: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let lenderName: String?
    let outstandingBalance: Decimal?

    private enum CodingKeys: String, CodingKey {
        case id
        case lenderName = "lender_name"
        case outstandingBalance = "outstanding_balance"
    }

    var detailRoute: AppRoute { .mortgageDetail(id: id) }
}

struct MortgageDetailResponse: Decodable, Sendable, Equatable {
    let mortgage: MortgageDetail
}

struct MortgageDetail: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let lenderName: String?
    let mortgageType: String?
    let outstandingBalance: Decimal?
    let interestRate: Decimal?
    let rateType: String?
    let monthlyPayment: Decimal?
    let maturityDate: String?
    let remainingTermMonths: Int?
    let ownershipType: String?
    let ownershipPercentage: Decimal?
    let isPrimaryOwner: Bool?
    let property: PropertyDetail?

    private enum CodingKeys: String, CodingKey {
        case id
        case lenderName = "lender_name"
        case mortgageType = "mortgage_type"
        case outstandingBalance = "outstanding_balance"
        case interestRate = "interest_rate"
        case rateType = "rate_type"
        case monthlyPayment = "monthly_payment"
        case maturityDate = "maturity_date"
        case remainingTermMonths = "remaining_term_months"
        case ownershipType = "ownership_type"
        case ownershipPercentage = "ownership_percentage"
        case isPrimaryOwner = "is_primary_owner"
        case property
    }

    var displayName: String { lenderName ?? "Mortgage" }
}

struct LiabilityDetailResponse: Decodable, Sendable, Equatable {
    let liability: LiabilityDetail
}

struct LiabilityDetail: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let liabilityName: String?
    let liabilityType: String?
    let currentBalance: Decimal?
    let monthlyPayment: Decimal?
    let interestRate: Decimal?
    let maturityDate: String?
    let securedAgainst: String?
    let fixedUntil: String?
    let ownershipType: String?
    let ownershipPercentage: Decimal?
    let isPrimaryOwner: Bool?
    let notes: String?

    private enum CodingKeys: String, CodingKey {
        case id
        case liabilityName = "liability_name"
        case liabilityType = "liability_type"
        case currentBalance = "current_balance"
        case monthlyPayment = "monthly_payment"
        case interestRate = "interest_rate"
        case maturityDate = "maturity_date"
        case securedAgainst = "secured_against"
        case fixedUntil = "fixed_until"
        case ownershipType = "ownership_type"
        case ownershipPercentage = "ownership_percentage"
        case isPrimaryOwner = "is_primary_owner"
        case notes
    }

    var displayName: String { liabilityName ?? "Liability" }
}

enum NetWorthCanonicalDetail: Sendable, Equatable {
    case property(PropertyDetail)
    case mortgage(MortgageDetail)
    case liability(LiabilityDetail)
}

enum NetWorthDetailViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(NetWorthCanonicalDetail)
    case offline(previous: NetWorthCanonicalDetail?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}

enum NetWorthCategory: String, CaseIterable, Sendable {
    case property
    case investments
    case pensions
    case cash
    case business
    case chattels
    case liabilities

    var title: String {
        switch self {
        case .property: "Property"
        case .investments: "Investments"
        case .pensions: "Pensions"
        case .cash: "Cash & savings"
        case .business: "Business interests"
        case .chattels: "Valuables"
        case .liabilities: "Liabilities"
        }
    }

    var subtitle: String {
        switch self {
        case .property: "Homes and other property you own"
        case .investments: "Investment accounts and ISAs"
        case .pensions: "Accessible pension capital"
        case .cash: "Savings accounts and cash"
        case .business: "Your share of business holdings"
        case .chattels: "Valuable personal possessions"
        case .liabilities: "Everything you owe"
        }
    }
}

struct NetWorthAssetCategorySummary: Identifiable, Sendable, Equatable {
    let id: NetWorthCategory
    let title: String
    let count: Int
    let totalValue: Decimal
    let percentageOfAssets: Int
}

struct NetWorthLiabilityRow: Identifiable, Sendable, Equatable {
    let id: String
    let title: String
    let value: Decimal
}

struct NetWorthSnapshot: Sendable, Equatable {
    let overview: NetWorthOverview
    let detailed: NetWorthDetailedAssets

    var assetCategories: [NetWorthAssetCategorySummary] {
        NetWorthCategory.allCases.compactMap { category in
            guard category != .liabilities,
                  let section = section(for: category),
                  section.count > 0 || section.totalValue != 0
            else {
                return nil
            }
            return NetWorthAssetCategorySummary(
                id: category,
                title: category.title,
                count: section.count,
                totalValue: section.totalValue,
                percentageOfAssets: percentage(
                    section.totalValue,
                    of: overview.totalAssets
                )
            )
        }
    }

    var liabilityRows: [NetWorthLiabilityRow] {
        [
            NetWorthLiabilityRow(
                id: "mortgages",
                title: "Mortgages",
                value: overview.liabilitiesBreakdown.mortgages
            ),
            NetWorthLiabilityRow(
                id: "loans",
                title: "Loans",
                value: overview.liabilitiesBreakdown.loans
            ),
            NetWorthLiabilityRow(
                id: "credit-cards",
                title: "Credit cards",
                value: overview.liabilitiesBreakdown.creditCards
            ),
            NetWorthLiabilityRow(
                id: "other",
                title: "Other debts",
                value: overview.liabilitiesBreakdown.other
            ),
        ].filter { $0.value > 0 }
    }

    func section(for category: NetWorthCategory) -> NetWorthAssetSection? {
        switch category {
        case .property: detailed.property
        case .investments: detailed.investments
        case .pensions: detailed.pensions
        case .cash: detailed.cash
        case .business: detailed.business
        case .chattels: detailed.chattels
        case .liabilities: nil
        }
    }

    private func percentage(_ value: Decimal, of total: Decimal) -> Int {
        guard total > 0 else { return 0 }
        let ratio = NSDecimalNumber(decimal: value / total).doubleValue * 100
        return Int(ratio.rounded())
    }
}

enum NetWorthViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(NetWorthSnapshot)
    case offline(previous: NetWorthSnapshot?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
