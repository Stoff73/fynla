import Foundation

struct ThresholdLever: Decodable, Sendable, Equatable {
    let title: String
    let recovers: Decimal
    let downside: String
    let route: String

    private enum CodingKeys: String, CodingKey { case title, recovers, downside, action }
    private enum ActionKeys: String, CodingKey { case route }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        title = try container.decode(String.self, forKey: .title)
        recovers = try container.decode(Decimal.self, forKey: .recovers)
        downside = try container.decode(String.self, forKey: .downside)
        route = try container
            .nestedContainer(keyedBy: ActionKeys.self, forKey: .action)
            .decode(String.self, forKey: .route)
    }
}

struct ThresholdLine: Decodable, Sendable, Equatable {
    let key: String
    let title: String
    let headline: String
    let body: String
    let costTotal: Decimal
    let lever: ThresholdLever?

    private enum CodingKeys: String, CodingKey {
        case key, title, headline, body, lever
        case costTotal = "cost_total"
    }
}

struct ThresholdPosition: Decodable, Sendable, Equatable {
    let strip: String?
    let lines: [ThresholdLine]

    /// The one line the evaluator put on the strip. Native shows this line
    /// only; `suppressed` and the remaining lines belong to web and /m.
    var stripLine: ThresholdLine? { lines.first { $0.key == strip } }
}
