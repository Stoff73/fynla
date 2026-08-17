import Foundation

struct SavingsSnapshot: Decodable, Sendable, Equatable {
    let accounts: [SavingsAccount]
    let accountCount: Int
    let accountLimit: Int?
    let expenditureProfile: SavingsExpenditureProfile
    let isaAllowance: SavingsISAAllowance?
    let emergencyFundTarget: SavingsEmergencyFundTarget

    private enum CodingKeys: String, CodingKey {
        case accounts
        case accountCount = "account_count"
        case accountLimit = "account_limit"
        case expenditureProfile = "expenditure_profile"
        case isaAllowance = "isa_allowance"
        case emergencyFundTarget = "emergency_fund_target"
    }

    var bankAccounts: [SavingsAccount] { accounts.filter { !$0.isISA } }
    var cashISAs: [SavingsAccount] { accounts.filter(\.isISA) }
    var totalCash: Decimal { accounts.reduce(0) { $0 + $1.fullBalanceValue } }
    var isAtAccountLimit: Bool {
        guard let accountLimit else { return false }
        return accountCount >= accountLimit
    }
}

struct SavingsAccount: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let accountName: String?
    let provider: String?
    let institution: String?
    let accountType: String?
    let currentBalance: Decimal
    let fullBalance: Decimal?
    let userShare: Decimal?
    let interestRate: Decimal?
    let accessType: String?
    let ownershipType: String?
    let ownershipPercentage: Decimal?
    let country: String?
    let isEmergencyFund: Bool
    let isISA: Bool
    let isaType: String?
    let isaSubscriptionYear: String?
    let isaSubscriptionAmount: Decimal?
    let noticePeriodDays: Int?
    let maturityDate: String?
    let isPrimaryOwner: Bool?
    let isShared: Bool?
    let ownerName: String?
    let jointOwnerName: String?

    private enum CodingKeys: String, CodingKey {
        case id
        case accountName = "account_name"
        case provider
        case institution
        case accountType = "account_type"
        case currentBalance = "current_balance"
        case fullBalance = "full_balance"
        case userShare = "user_share"
        case interestRate = "interest_rate"
        case accessType = "access_type"
        case ownershipType = "ownership_type"
        case ownershipPercentage = "ownership_percentage"
        case country
        case isEmergencyFund = "is_emergency_fund"
        case isISA = "is_isa"
        case isaType = "isa_type"
        case isaSubscriptionYear = "isa_subscription_year"
        case isaSubscriptionAmount = "isa_subscription_amount"
        case noticePeriodDays = "notice_period_days"
        case maturityDate = "maturity_date"
        case isPrimaryOwner = "is_primary_owner"
        case isShared = "is_shared"
        case ownerName = "owner_name"
        case jointOwnerName = "joint_owner_name"
    }

    var fullBalanceValue: Decimal { fullBalance ?? currentBalance }
    var displayName: String {
        provider ?? institution ?? accountName ?? (isISA ? "Cash ISA" : "Bank account")
    }
    var isJoint: Bool { ownershipType == "joint" }
}

struct SavingsExpenditureProfile: Decodable, Sendable, Equatable {
    let totalMonthlyExpenditure: Decimal
    let totalAnnualExpenditure: Decimal

    private enum CodingKeys: String, CodingKey {
        case totalMonthlyExpenditure = "total_monthly_expenditure"
        case totalAnnualExpenditure = "total_annual_expenditure"
    }
}

struct SavingsISAAllowance: Decodable, Sendable, Equatable {
    let taxYear: String?
    let currentTaxYear: String?
    let priorTaxYear: String?
    let availableTaxYears: [String]
    let cashISAUsed: Decimal
    let stocksSharesISAUsed: Decimal
    let lisaUsed: Decimal
    let totalUsed: Decimal
    let totalAllowance: Decimal
    let remaining: Decimal
    let percentageUsed: Decimal
    let owners: [SavingsISAOwnerStatus]
    private let directAccountBreakdown: [SavingsISAAccountBreakdown]

    var accountBreakdown: [SavingsISAAccountBreakdown] {
        directAccountBreakdown.isEmpty
            ? owners.flatMap(\.accountBreakdown)
            : directAccountBreakdown
    }

    private enum CodingKeys: String, CodingKey {
        case taxYear = "tax_year"
        case currentTaxYear = "current_tax_year"
        case priorTaxYear = "prior_tax_year"
        case availableTaxYears = "available_tax_years"
        case cashISAUsed = "cash_isa_used"
        case stocksSharesISAUsed = "stocks_shares_isa_used"
        case lisaUsed = "lisa_used"
        case totalUsed = "total_used"
        case totalAllowance = "total_allowance"
        case remaining
        case percentageUsed = "percentage_used"
        case owners
        case directAccountBreakdown = "account_breakdown"
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        taxYear = try container.decodeIfPresent(String.self, forKey: .taxYear)
        currentTaxYear = try container.decodeIfPresent(String.self, forKey: .currentTaxYear)
        priorTaxYear = try container.decodeIfPresent(String.self, forKey: .priorTaxYear)
        availableTaxYears = try container.decodeIfPresent([String].self, forKey: .availableTaxYears) ?? []
        cashISAUsed = try container.decodeIfPresent(Decimal.self, forKey: .cashISAUsed) ?? 0
        stocksSharesISAUsed = try container.decodeIfPresent(Decimal.self, forKey: .stocksSharesISAUsed) ?? 0
        lisaUsed = try container.decodeIfPresent(Decimal.self, forKey: .lisaUsed) ?? 0
        totalUsed = try container.decodeIfPresent(Decimal.self, forKey: .totalUsed) ?? 0
        totalAllowance = try container.decodeIfPresent(Decimal.self, forKey: .totalAllowance) ?? 0
        remaining = try container.decodeIfPresent(Decimal.self, forKey: .remaining) ?? 0
        percentageUsed = try container.decodeIfPresent(Decimal.self, forKey: .percentageUsed) ?? 0
        owners = try container.decodeIfPresent([SavingsISAOwnerStatus].self, forKey: .owners) ?? []
        directAccountBreakdown = try container.decodeIfPresent(
            [SavingsISAAccountBreakdown].self,
            forKey: .directAccountBreakdown
        ) ?? []
    }
}

struct SavingsISAOwnerStatus: Decodable, Sendable, Equatable {
    let owner: SavingsISAOwner
    let cashISAUsed: Decimal
    let stocksSharesISAUsed: Decimal
    let lisaUsed: Decimal
    let totalUsed: Decimal
    let accountBreakdown: [SavingsISAAccountBreakdown]

    private enum CodingKeys: String, CodingKey {
        case owner
        case cashISAUsed = "cash_isa_used"
        case stocksSharesISAUsed = "stocks_shares_isa_used"
        case lisaUsed = "lisa_used"
        case totalUsed = "total_used"
        case accountBreakdown = "account_breakdown"
    }
}

struct SavingsISAOwner: Decodable, Sendable, Equatable {
    let id: Int?
    let relationship: String?
    let label: String?
    let name: String?

    var displayName: String { name ?? label ?? "Owner unavailable" }
}

struct SavingsISAAccountBreakdown: Decodable, Sendable, Equatable, Identifiable {
    let accountID: Int
    let accountType: String
    let accountName: String
    let isaType: String
    let owner: SavingsISAOwner
    let contributed: Decimal
    let provenance: String
    let contributions: [SavingsISAContribution]

    private enum CodingKeys: String, CodingKey {
        case accountID = "account_id"
        case accountType = "account_type"
        case accountName = "account_name"
        case isaType = "isa_type"
        case owner, contributed, provenance, contributions
    }

    var id: String { "\(accountType)-\(accountID)" }
    var isSavingsAccount: Bool { accountType.hasSuffix("SavingsAccount") }
    var isInvestmentAccount: Bool { accountType.hasSuffix("InvestmentAccount") }
    var isaTypeLabel: String {
        switch isaType {
        case "cash_isa": "Cash ISA"
        case "stocks_and_shares_isa": "Stocks & Shares ISA"
        case "lifetime_isa": "Lifetime ISA"
        default: isaType.replacingOccurrences(of: "_", with: " ").capitalized
        }
    }
    var provenanceLabel: String {
        switch provenance {
        case "recorded_ledger": "Recorded contribution ledger"
        case "legacy_annual_summary", "legacy_current_year_summary":
            "Annual summary from the account record"
        default: "Recorded account data"
        }
    }
}

struct SavingsISAContribution: Decodable, Sendable, Equatable, Identifiable {
    let id: Int?
    let date: String?
    let amount: Decimal
    let entryType: String?
    let source: String?
    let provenance: String?

    private enum CodingKeys: String, CodingKey {
        case id, date, amount, source, provenance
        case entryType = "entry_type"
    }

    var stableID: String { "\(id.map(String.init) ?? date ?? "summary")-\(amount)" }
}

struct SavingsEmergencyFundTarget: Decodable, Sendable, Equatable {
    let targetMonths: Int
    let targetAmount: Decimal
    let employmentStatus: String?
    let rationale: String?

    private enum CodingKeys: String, CodingKey {
        case targetMonths = "target_months"
        case targetAmount = "target_amount"
        case employmentStatus = "employment_status"
        case rationale
    }
}

enum SavingsViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(SavingsSnapshot)
    case offline(previous: SavingsSnapshot?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}

enum SavingsAccountViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(SavingsAccount)
    case offline
    case unauthenticated
    case forbidden
    case notFound
    case failed(requestID: String?)
}
