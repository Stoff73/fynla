import Foundation
import OSLog

enum SemanticParameter: Codable, Sendable, Equatable {
    case string(String)
    case int(Int)

    init(from decoder: Decoder) throws {
        let container = try decoder.singleValueContainer()

        if let value = try? container.decode(Int.self) {
            self = .int(value)
            return
        }

        if let value = try? container.decode(String.self) {
            self = .string(value)
            return
        }

        throw DecodingError.typeMismatch(
            SemanticParameter.self,
            DecodingError.Context(
                codingPath: decoder.codingPath,
                debugDescription: "Semantic navigation parameters must be strings or integers."
            )
        )
    }

    func encode(to encoder: Encoder) throws {
        var container = encoder.singleValueContainer()
        switch self {
        case .string(let value):
            try container.encode(value)
        case .int(let value):
            try container.encode(value)
        }
    }

    var stringValue: String {
        switch self {
        case .string(let value): value
        case .int(let value): String(value)
        }
    }

    var intValue: Int? {
        switch self {
        case .string(let value): Int(value)
        case .int(let value): value
        }
    }
}

struct SemanticDestination: Codable, Sendable, Equatable {
    let screen: String
    let params: [String: SemanticParameter]
    let fallback: String
}

enum SemanticDestinationResolver {
    private static let logger = Logger(
        subsystem: Bundle.main.bundleIdentifier ?? "org.fynla.app",
        category: "navigation"
    )

    static func route(
        for destination: SemanticDestination?,
        legacyPath: String?,
        onUnknown: (String) -> Void = recordUnknown
    ) -> AppRoute {
        guard let destination else {
            return route(forLegacyPath: legacyPath)
        }

        if let route = route(forScreen: destination.screen, params: destination.params) {
            return route
        }

        onUnknown(destination.screen)
        return route(forScreen: destination.fallback, params: [:]) ?? .dashboard
    }

    private static func route(
        forScreen screen: String,
        params: [String: SemanticParameter]
    ) -> AppRoute? {
        switch screen {
        case "dashboard": .dashboard
        case "achievements": .achievements
        case "conversation_history": .conversationHistory
        case "income": .income
        case "expenditure": .expenditure
        case "net_worth": .netWorth(category: nil)
        case "protection": .protection(policyType: nil, id: nil)
        case "savings": .savings(accountID: nil)
        case "investment": .investment(accountID: nil)
        case "retirement": .retirement(pensionType: nil, id: nil)
        case "estate": .estate
        case "goals": .goals
        case "tax_strategy": .taxStrategy
        case "holistic_plan": .holisticPlan
        case "personal_information": .personalInformation
        case "subscription": .subscription
        case "settings": .settings
        case "protection_policy_detail": protectionPolicyRoute(params)
        case "savings_account_detail": savingsAccountRoute(params)
        case "investment_account_detail": investmentAccountRoute(params)
        case "pension_detail": pensionRoute(params)
        default: nil
        }
    }

    private static func protectionPolicyRoute(
        _ params: [String: SemanticParameter]
    ) -> AppRoute? {
        guard
            let policyType = params["policy_type"]?.stringValue,
            let policyID = params["policy_id"]?.intValue
        else {
            return nil
        }

        return .protection(policyType: policyType, id: policyID)
    }

    private static func savingsAccountRoute(
        _ params: [String: SemanticParameter]
    ) -> AppRoute? {
        guard let accountID = params["account_id"]?.intValue else {
            return nil
        }

        return .savings(accountID: accountID)
    }

    private static func investmentAccountRoute(
        _ params: [String: SemanticParameter]
    ) -> AppRoute? {
        guard let accountID = params["account_id"]?.intValue else {
            return nil
        }

        return .investment(accountID: accountID)
    }

    private static func pensionRoute(
        _ params: [String: SemanticParameter]
    ) -> AppRoute? {
        guard
            let pensionType = params["pension_type"]?.stringValue,
            let pensionID = params["pension_id"]?.intValue
        else {
            return nil
        }

        return .retirement(pensionType: pensionType, id: pensionID)
    }

    private static func route(forLegacyPath path: String?) -> AppRoute {
        switch path?.trimmingCharacters(in: CharacterSet(charactersIn: "/")) {
        case "dashboard": .dashboard
        case "achievements": .achievements
        case "conversation-history": .conversationHistory
        case "income": .income
        case "expenditure": .expenditure
        case "net-worth": .netWorth(category: nil)
        case "protection": .protection(policyType: nil, id: nil)
        case "savings": .savings(accountID: nil)
        case "investment": .investment(accountID: nil)
        case "retirement": .retirement(pensionType: nil, id: nil)
        case "estate": .estate
        case "goals": .goals
        case "tax", "tax-strategy": .taxStrategy
        case "holistic-plan": .holisticPlan
        case "settings": .settings
        default: .dashboard
        }
    }

    private static func recordUnknown(_ screen: String) {
        logger.notice("operation=navigation.unknown_destination")
    }
}
