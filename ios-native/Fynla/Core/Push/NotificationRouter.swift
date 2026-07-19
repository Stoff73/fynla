import Foundation

struct NotificationRouter: Sendable {
    func route(from userInfo: [AnyHashable: Any]) -> AppRoute? {
        let routeKey = userInfo["route"] as? String
            ?? (userInfo["data"] as? [String: Any])?["route"] as? String
        guard let routeKey else { return nil }
        return switch routeKey {
        case "dashboard": .dashboard
        case "achievements": .achievements
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
        case "settings": .settings
        default: nil
        }
    }
}
