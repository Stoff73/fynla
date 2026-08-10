import Foundation

struct CanonicalPortfolio: Decodable, Sendable, Equatable {
    let contractVersion: String
    let wrapperType: String
    let wrapperID: Int
    let wrapperName: String
    let recordedWrapperValue: Decimal
    let holdings: [CanonicalPortfolioHolding]
    let analysis: CanonicalPortfolioAnalysis
    let performanceHistory: CanonicalPortfolioHistory

    private enum CodingKeys: String, CodingKey {
        case contractVersion = "contract_version"
        case wrapperType = "wrapper_type"
        case wrapperID = "wrapper_id"
        case wrapperName = "wrapper_name"
        case recordedWrapperValue = "recorded_wrapper_value"
        case holdings
        case analysis
        case performanceHistory = "performance_history"
    }
}

struct CanonicalPortfolioHolding: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
    let name: String?
    let ticker: String?
    let assetType: String?
    let currentValue: Decimal
    let wrapperPercentage: Decimal
    let wholeRelevantPortfolioPercentage: Decimal
    let classifiedExposure: [CanonicalHoldingExposure]
    let classification: CanonicalHoldingClassification?
    let fees: CanonicalHoldingFees
    let performance: CanonicalHoldingPerformance

    private enum CodingKeys: String, CodingKey {
        case id, name, ticker
        case assetType = "asset_type"
        case currentValue = "current_value"
        case wrapperPercentage = "wrapper_percentage"
        case wholeRelevantPortfolioPercentage = "whole_relevant_portfolio_percentage"
        case classifiedExposure = "classified_exposure"
        case classification, fees, performance
    }

    var displayName: String { name ?? ticker ?? "Holding" }
}

struct CanonicalHoldingExposure: Decodable, Sendable, Equatable, Identifiable {
    let assetClass: String
    let value: Decimal
    let holdingPercentage: Decimal

    private enum CodingKeys: String, CodingKey {
        case assetClass = "asset_class"
        case value
        case holdingPercentage = "holding_percentage"
    }

    var id: String { assetClass }
}

struct CanonicalHoldingClassification: Decodable, Sendable, Equatable {
    let method: String?
    let source: String?
    let effectiveAt: String?

    private enum CodingKeys: String, CodingKey {
        case method, source
        case effectiveAt = "effective_at"
    }
}

struct CanonicalHoldingFees: Decodable, Sendable, Equatable {
    let available: Bool
    let ocfPercent: Decimal?
    let estimatedAnnualCost: Decimal?
    let method: String?
    let unavailableReason: String?

    private enum CodingKeys: String, CodingKey {
        case available
        case ocfPercent = "ocf_percent"
        case estimatedAnnualCost = "estimated_annual_cost"
        case method
        case unavailableReason = "unavailable_reason"
    }
}

struct CanonicalHoldingPerformance: Decodable, Sendable, Equatable {
    let available: Bool
    let gainLoss: Decimal?
    let gainLossPercent: Decimal?
    let method: String?
    let unavailableReason: String?

    private enum CodingKeys: String, CodingKey {
        case available
        case gainLoss = "gain_loss"
        case gainLossPercent = "gain_loss_percent"
        case method
        case unavailableReason = "unavailable_reason"
    }
}

struct CanonicalPortfolioAnalysis: Decodable, Sendable, Equatable {
    let totalValue: Decimal
    let classifiedValue: Decimal
    let unclassifiedValue: Decimal
    let coveragePercent: Decimal
    let coverageThresholdPercent: Decimal
    let driftAvailable: Bool
    let allocation: [CanonicalPortfolioAllocation]
    let comparisons: CanonicalPortfolioComparisons

    private enum CodingKeys: String, CodingKey {
        case totalValue = "total_value"
        case classifiedValue = "classified_value"
        case unclassifiedValue = "unclassified_value"
        case coveragePercent = "coverage_percent"
        case coverageThresholdPercent = "coverage_threshold_percent"
        case driftAvailable = "drift_available"
        case allocation, comparisons
    }
}

struct CanonicalPortfolioAllocation: Decodable, Sendable, Equatable, Identifiable {
    let assetClass: String
    let value: Decimal
    let portfolioPercentage: Decimal
    let classifiedPercentage: Decimal?

    private enum CodingKeys: String, CodingKey {
        case assetClass = "asset_class"
        case value
        case portfolioPercentage = "portfolio_percentage"
        case classifiedPercentage = "classified_percentage"
    }

    var id: String { assetClass }
}

struct CanonicalPortfolioComparisons: Decodable, Sendable, Equatable {
    let entered: CanonicalPortfolioComparison?
    let recommended: CanonicalPortfolioComparison?
}

struct CanonicalPortfolioComparison: Decodable, Sendable, Equatable {
    let source: String?
    let effectiveAt: String?
    let allocation: [String: Decimal]
    let driftPercentagePoints: [String: Decimal]?
    let unavailableReason: String?

    private enum CodingKeys: String, CodingKey {
        case source
        case effectiveAt = "effective_at"
        case allocation
        case driftPercentagePoints = "drift_percentage_points"
        case unavailableReason = "unavailable_reason"
    }
}

struct CanonicalPortfolioHistory: Decodable, Sendable, Equatable {
    let available: Bool
    let points: [CanonicalPortfolioHistoryPoint]
    let method: String?
    let unavailableReason: String?

    private enum CodingKeys: String, CodingKey {
        case available, points, method
        case unavailableReason = "unavailable_reason"
    }
}

struct CanonicalPortfolioHistoryPoint: Decodable, Sendable, Equatable, Identifiable {
    let date: String?
    let value: Decimal
    let currency: String?
    let source: String?

    var id: String { "\(date ?? "unknown")-\(value)" }
}
