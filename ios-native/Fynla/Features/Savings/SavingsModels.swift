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
    let cashISAUsed: Decimal
    let stocksSharesISAUsed: Decimal
    let lisaUsed: Decimal
    let totalUsed: Decimal
    let totalAllowance: Decimal
    let remaining: Decimal
    let percentageUsed: Decimal

    private enum CodingKeys: String, CodingKey {
        case cashISAUsed = "cash_isa_used"
        case stocksSharesISAUsed = "stocks_shares_isa_used"
        case lisaUsed = "lisa_used"
        case totalUsed = "total_used"
        case totalAllowance = "total_allowance"
        case remaining
        case percentageUsed = "percentage_used"
    }
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
