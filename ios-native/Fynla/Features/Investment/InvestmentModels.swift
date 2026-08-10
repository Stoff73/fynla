import Foundation

struct InvestmentSnapshot: Decodable, Sendable, Equatable {
    let accounts: [InvestmentAccount]
    let accountCount: Int
    let accountLimit: Int?
    let riskProfile: InvestmentRiskProfile?

    private enum CodingKeys: String, CodingKey {
        case accounts
        case accountCount = "account_count"
        case accountLimit = "account_limit"
        case riskProfile = "risk_profile"
    }

    var totalValue: Decimal { accounts.reduce(0) { $0 + $1.currentValue } }
    var isAtAccountLimit: Bool {
        guard let accountLimit else { return false }
        return accountCount >= accountLimit
    }
    var riskLabel: String? {
        guard let value = riskProfile?.riskCategory
            ?? riskProfile?.attitudeToRisk
        else {
            return riskProfile?.riskLevel.map(String.init)
        }
        return value.replacingOccurrences(of: "_", with: " ").capitalized
    }
}

struct InvestmentRiskProfile: Decodable, Sendable, Equatable {
    let riskCategory: String?
    let attitudeToRisk: String?
    let riskLevel: Int?

    private enum CodingKeys: String, CodingKey {
        case riskCategory = "risk_category"
        case attitudeToRisk = "attitude_to_risk"
        case riskLevel = "risk_level"
    }
}

struct InvestmentAccount: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let accountName: String?
    let accountType: String?
    let accountTypeOther: String?
    let provider: String?
    let platform: String?
    let currentValue: Decimal
    let contributionsYTD: Decimal?
    let monthlyContributionAmount: Decimal?
    let ownershipType: String?
    let ownershipPercentage: Decimal?
    let country: String?
    let isaType: String?
    let isaSubscriptionCurrentYear: Decimal?
    let isPrimaryOwner: Bool?
    let ownerName: String?
    let holdings: [InvestmentHolding]
    let portfolio: CanonicalPortfolio?

    private enum CodingKeys: String, CodingKey {
        case id
        case accountName = "account_name"
        case accountType = "account_type"
        case accountTypeOther = "account_type_other"
        case provider
        case platform
        case currentValue = "current_value"
        case contributionsYTD = "contributions_ytd"
        case monthlyContributionAmount = "monthly_contribution_amount"
        case ownershipType = "ownership_type"
        case ownershipPercentage = "ownership_percentage"
        case country
        case isaType = "isa_type"
        case isaSubscriptionCurrentYear = "isa_subscription_current_year"
        case isPrimaryOwner = "is_primary_owner"
        case ownerName = "owner_name"
        case holdings
        case portfolio
    }

    var displayName: String {
        provider ?? platform ?? accountName ?? "Investment account"
    }
    var isISA: Bool { accountType?.contains("isa") == true || isaType != nil }
    var accountTypeLabel: String {
        if accountType == "other", let accountTypeOther, !accountTypeOther.isEmpty {
            return accountTypeOther
        }
        let labels = [
            "stocks_and_shares_isa": "Stocks & Shares ISA",
            "cash_isa": "Cash ISA",
            "lifetime_isa": "Lifetime ISA",
            "innovative_finance_isa": "Innovative Finance ISA",
            "junior_isa": "Junior ISA",
            "gia": "General Investment Account",
            "general_investment_account": "General Investment Account",
            "investment_bond": "Investment Bond",
            "onshore_bond": "Onshore Bond",
            "offshore_bond": "Offshore Bond",
            "vct": "Venture Capital Trust",
            "eis": "Enterprise Investment Scheme",
            "seis": "Seed Enterprise Investment Scheme",
            "employee_share_scheme": "Employee Share Scheme",
        ]
        guard let accountType else { return "Investment account" }
        if accountType == "isa" {
            return isaType == "lifetime" || isaType == "lifetime_isa"
                ? "Lifetime ISA"
                : "Stocks & Shares ISA"
        }
        return labels[accountType]
            ?? accountType.replacingOccurrences(of: "_", with: " ").capitalized
    }
}

struct InvestmentHolding: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let assetType: String?
    let securityName: String?
    let ticker: String?
    let allocationPercent: Decimal?
    let currentValue: Decimal?
    let gainLoss: Decimal?
    let gainLossPercent: Decimal?

    private enum CodingKeys: String, CodingKey {
        case id
        case assetType = "asset_type"
        case securityName = "security_name"
        case ticker
        case allocationPercent = "allocation_percent"
        case currentValue = "current_value"
        case gainLoss = "gain_loss"
        case gainLossPercent = "gain_loss_percent"
    }

    var displayName: String { securityName ?? ticker ?? "Holding" }
}

enum InvestmentViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(InvestmentSnapshot)
    case offline(previous: InvestmentSnapshot?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
