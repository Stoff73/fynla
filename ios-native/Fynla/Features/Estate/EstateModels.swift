import Foundation

struct EstateSnapshot: Sendable, Equatable {
    let index: EstateIndexResponse
    let netWorth: EstateNetWorth?
}

enum EstateMode: String, Decodable, Sendable, Equatable {
    case teaser
    case full
}

struct EstateIndexResponse: Decodable, Sendable, Equatable {
    let mode: EstateMode
    let teaser: EstateTeaser?
    let cta: EstateCTA?
    let data: EstateFullData?
}

struct EstateTeaser: Decodable, Sendable, Equatable {
    let estimatedLiability: Decimal?
    let headline: String?

    private enum CodingKeys: String, CodingKey {
        case estimatedLiability = "estimated_liability_gbp"
        case headline
    }
}

struct EstateCTA: Decodable, Sendable, Equatable {
    let label: String?
    let targetTier: String?

    private enum CodingKeys: String, CodingKey {
        case label
        case targetTier = "target_tier"
    }
}

struct EstateFullData: Decodable, Sendable, Equatable {
    let gifts: [EstateRecord]
    let trusts: [EstateRecord]
    let willInfo: EstateWillInfo?

    private enum CodingKeys: String, CodingKey {
        case gifts
        case trusts
        case willInfo = "will_info"
    }
}

struct EstateRecord: Decodable, Sendable, Equatable, Identifiable {
    let id: Int
}

struct EstateWillInfo: Decodable, Sendable, Equatable {
    let hasWill: Bool
    let executorName: String?

    private enum CodingKeys: String, CodingKey {
        case hasWill = "has_will"
        case executorName = "executor_name"
    }
}

struct EstateNetWorthResponse: Decodable, Sendable, Equatable {
    let netWorth: EstateNetWorth

    private enum CodingKeys: String, CodingKey {
        case netWorth = "net_worth"
    }
}

struct EstateNetWorth: Decodable, Sendable, Equatable {
    let totalAssets: Decimal
    let totalLiabilities: Decimal
    let netWorth: Decimal
    let assetComposition: [EstateAssetComposition]

    private enum CodingKeys: String, CodingKey {
        case totalAssets = "total_assets"
        case totalLiabilities = "total_liabilities"
        case netWorth = "net_worth"
        case assetComposition = "asset_composition"
    }
}

struct EstateAssetComposition: Decodable, Sendable, Equatable, Identifiable {
    let type: String
    let value: Decimal
    let percentage: Decimal?
    let count: Int?

    var id: String { type }
}

enum EstateViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(EstateSnapshot)
    case offline(previous: EstateSnapshot?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
