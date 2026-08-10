import Foundation

/// Identifier-only actions shared by native product surfaces.
///
/// Existing labels and financial facts never enter this builder. Laravel
/// owns resource lookup, authorisation, and canonical context hydration.
enum FynContextualActions {
    static func personalInformation() -> FynContextualAction {
        overview(action: .edit, resourceType: "personal_information", screen: "personal_information")
    }

    static func savingsOverview(hasAccounts: Bool) -> FynContextualAction {
        overview(action: .add, resourceType: "savings", screen: "savings")
    }

    static func savingsAccount(id: Int) -> FynContextualAction {
        entity(
            resourceType: "savings_account",
            resourceID: id,
            screen: "savings_account_detail",
            params: ["account_id": .int(id)],
            fallback: "savings"
        )
    }

    static func investmentOverview(hasAccounts: Bool) -> FynContextualAction {
        overview(action: .add, resourceType: "investment", screen: "investment")
    }

    static func investmentAccount(id: Int) -> FynContextualAction {
        entity(
            resourceType: "investment_account",
            resourceID: id,
            screen: "investment_account_detail",
            params: ["account_id": .int(id)],
            fallback: "investment"
        )
    }

    static func retirementOverview(hasPensions: Bool) -> FynContextualAction {
        overview(action: .add, resourceType: "retirement", screen: "retirement")
    }

    static func pension(type: String, id: Int) -> FynContextualAction {
        let resourceType = switch type {
        case "dc": "dc_pension"
        case "db": "db_pension"
        case "state": "state_pension"
        default: "pension"
        }
        return entity(
            resourceType: resourceType,
            resourceID: id,
            screen: "pension_detail",
            params: ["pension_id": .int(id), "pension_type": .string(type)],
            fallback: "retirement"
        )
    }

    static func protectionOverview(hasPolicies: Bool) -> FynContextualAction {
        overview(action: .add, resourceType: "protection", screen: "protection")
    }

    static func protectionPolicy(type: String, id: Int) -> FynContextualAction {
        let resourceType = switch type {
        case "life": "life_insurance_policy"
        case "criticalIllness": "critical_illness_policy"
        case "incomeProtection": "income_protection_policy"
        case "disability": "disability_policy"
        case "sicknessIllness": "sickness_illness_policy"
        default: "protection_policy"
        }
        return entity(
            resourceType: resourceType,
            resourceID: id,
            screen: "protection_policy_detail",
            params: ["policy_id": .int(id), "policy_type": .string(type)],
            fallback: "protection"
        )
    }

    static func goalsOverview(hasGoals: Bool) -> FynContextualAction {
        overview(action: .add, resourceType: "goals", screen: "goals")
    }

    static func addGoal() -> FynContextualAction {
        overview(action: .add, resourceType: "goals", screen: "goals")
    }

    static func goal(id: Int) -> FynContextualAction {
        entity(
            resourceType: "goal",
            resourceID: id,
            screen: "goals",
            params: ["goal_id": .int(id)],
            fallback: "goals"
        )
    }

    private static func overview(
        action: FynContextualActionKind,
        resourceType: String,
        screen: String
    ) -> FynContextualAction {
        FynContextualAction(
            action: action,
            resourceType: resourceType,
            currentDestination: SemanticDestination(
                screen: screen,
                params: [:],
                fallback: "dashboard"
            ),
            origin: FynContextualOrigin(kind: .surfaceAction)
        )
    }

    private static func entity(
        resourceType: String,
        resourceID: Int,
        screen: String,
        params: [String: SemanticParameter],
        fallback: String
    ) -> FynContextualAction {
        FynContextualAction(
            action: .edit,
            resourceType: resourceType,
            resourceID: resourceID,
            currentDestination: SemanticDestination(
                screen: screen,
                params: params,
                fallback: fallback
            ),
            origin: FynContextualOrigin(kind: .surfaceAction)
        )
    }
}
