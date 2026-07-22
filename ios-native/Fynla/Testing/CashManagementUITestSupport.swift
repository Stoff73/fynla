#if FYNLA_UI_TESTING
import Foundation

// Offline fixtures for the Cash Management screens so the capture harness
// shows content instead of error states (sweep item: fixture stubs).

@MainActor
enum IncomeUITestComposition {
    static func model() -> IncomeModel {
        IncomeModel(client: IncomeUITestClient())
    }
}

private struct IncomeUITestClient: IncomeClient {
    func load() async throws -> IncomeProfile {
        try JSONDecoder().decode(
            IncomeProfile.self,
            from: Data(Self.fixture.utf8)
        )
    }

    private static let fixture = #"""
    {
      "income_summary":{
        "user":{
          "employment":52000,
          "self_employment":0,
          "dividend":1200,
          "interest":350,
          "other":0,
          "total":53550,
          "employer":"Example Ltd",
          "occupation":"Product manager"
        },
        "spouse":null
      }
    }
    """#
}

@MainActor
enum ExpenditureUITestComposition {
    static func model() -> ExpenditureModel {
        ExpenditureModel(client: ExpenditureUITestClient())
    }
}

private struct ExpenditureUITestClient: ExpenditureClient {
    func load() async throws -> ExpenditureProfile {
        try JSONDecoder().decode(
            ExpenditureProfile.self,
            from: Data(Self.fixture.utf8)
        )
    }

    private static let fixture = #"""
    {
      "expenditure":{
        "monthly_expenditure":2450,
        "annual_expenditure":29400,
        "categories":{
          "food_groceries":520,
          "transport_fuel":260,
          "clothing_personal_care":140,
          "entertainment_dining":310,
          "childcare":0,
          "other_expenditure":1220
        }
      }
    }
    """#
}
#endif
