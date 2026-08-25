import Foundation

enum NetWorthForecastCategory: String, CaseIterable, Codable, Sendable, Identifiable {
    case property
    case investments
    case pensions
    case cash
    case business
    case valuables
    case mortgages
    case otherLiabilities = "other_liabilities"

    var id: String { rawValue }

    var title: String {
        switch self {
        case .property: "Property"
        case .investments: "Investments"
        case .pensions: "Pensions"
        case .cash: "Cash & savings"
        case .business: "Business interests"
        case .valuables: "Valuables"
        case .mortgages: "Mortgages"
        case .otherLiabilities: "Other liabilities"
        }
    }
}

enum NetWorthForecastBasis: String, Codable, Sendable, Equatable, CaseIterable {
    case nominal
    case real

    var title: String { rawValue.capitalized }
}

enum NetWorthForecastAssumptionSource: String, Codable, Sendable, Equatable {
    case systemDefault = "system_default"
    case userOverride = "user_override"

    var title: String {
        switch self {
        case .systemDefault: "Fynla default"
        case .userOverride: "Your assumption"
        }
    }
}

struct NetWorthForecastAssumption: Codable, Sendable, Equatable {
    let ratePercent: Decimal
    let source: NetWorthForecastAssumptionSource
    let effectiveFrom: String
    let basis: NetWorthForecastBasis

    private enum CodingKeys: String, CodingKey {
        case ratePercent = "rate_percent"
        case source
        case effectiveFrom = "effective_from"
        case basis
    }
}

struct NetWorthForecastAssumptions: Codable, Sendable, Equatable {
    let property: NetWorthForecastAssumption
    let investments: NetWorthForecastAssumption
    let pensions: NetWorthForecastAssumption
    let cash: NetWorthForecastAssumption
    let business: NetWorthForecastAssumption
    let valuables: NetWorthForecastAssumption
    let mortgages: NetWorthForecastAssumption
    let otherLiabilities: NetWorthForecastAssumption

    private enum CodingKeys: String, CodingKey {
        case property
        case investments
        case pensions
        case cash
        case business
        case valuables
        case mortgages
        case otherLiabilities = "other_liabilities"
    }

    subscript(category: NetWorthForecastCategory) -> NetWorthForecastAssumption {
        switch category {
        case .property: property
        case .investments: investments
        case .pensions: pensions
        case .cash: cash
        case .business: business
        case .valuables: valuables
        case .mortgages: mortgages
        case .otherLiabilities: otherLiabilities
        }
    }
}

struct NetWorthForecastCurrent: Decodable, Sendable, Equatable {
    let assets: [String: Decimal]
    let liabilities: [String: Decimal]
    let totalAssets: Decimal
    let totalLiabilities: Decimal
    let netWorth: Decimal

    private enum CodingKeys: String, CodingKey {
        case assets
        case liabilities
        case totalAssets = "total_assets"
        case totalLiabilities = "total_liabilities"
        case netWorth = "net_worth"
    }
}

enum NetWorthForecastPointSource: String, Decodable, Sendable, Equatable {
    case recorded
    case projected
}

struct NetWorthForecastPoint: Decodable, Sendable, Equatable, Identifiable {
    let year: Int
    let calendarYear: Int
    let categories: [String: Decimal]
    let liabilities: [String: Decimal]
    let totalAssets: Decimal
    let totalLiabilities: Decimal
    let netWorth: Decimal
    let source: NetWorthForecastPointSource

    var id: Int { year }

    private enum CodingKeys: String, CodingKey {
        case year
        case calendarYear = "calendar_year"
        case categories
        case liabilities
        case totalAssets = "total_assets"
        case totalLiabilities = "total_liabilities"
        case netWorth = "net_worth"
        case source
    }
}

struct NetWorthForecast: Decodable, Sendable, Equatable {
    let contractVersion: String
    let recordedAsOf: String
    let current: NetWorthForecastCurrent
    let points: [NetWorthForecastPoint]
    let assumptions: NetWorthForecastAssumptions
    let warnings: [String]

    private enum CodingKeys: String, CodingKey {
        case contractVersion = "contract_version"
        case recordedAsOf = "recorded_as_of"
        case current
        case points
        case assumptions
        case warnings
    }
}

struct NetWorthForecastAssumptionUpdate: Encodable, Sendable, Equatable {
    let rates: [NetWorthForecastCategory: Decimal]
    let basis: NetWorthForecastBasis

    func encode(to encoder: Encoder) throws {
        var container = encoder.container(keyedBy: CodingKey.self)
        for (category, rate) in rates {
            try container.encode(rate, forKey: CodingKey(category.rawValue))
        }
        try container.encode(basis, forKey: CodingKey("basis"))
    }

    private struct CodingKey: Swift.CodingKey {
        let stringValue: String
        let intValue: Int? = nil

        init(_ stringValue: String) {
            self.stringValue = stringValue
        }

        init?(stringValue: String) {
            self.init(stringValue)
        }

        init?(intValue: Int) {
            return nil
        }
    }
}

enum NetWorthForecastViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(NetWorthForecast)
    case saving(previous: NetWorthForecast)
    case offline(previous: NetWorthForecast?)
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)
}
