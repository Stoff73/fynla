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
          "occupation":"Product manager",
          "sources":[{"key":"employment","label":"Employment","amount":52000,"frequency":"annual","ownership":"user","ownership_label":"You","detail":"Example Ltd · Product manager","tax_position":"Taxable earned income"},{"key":"dividend","label":"Dividends","amount":1200,"frequency":"annual","ownership":"user","ownership_label":"You","detail":null,"tax_position":"Dividend income"},{"key":"interest","label":"Interest","amount":350,"frequency":"annual","ownership":"user","ownership_label":"You","detail":null,"tax_position":"Savings income"}],
          "tax_position":{"total_income":53550,"adjusted_net_income":53550,"personal_allowance":12570,"personal_allowance_label":"Standard personal allowance","pension_annual_allowance":60000,"pension_annual_allowance_label":"Standard pension annual allowance"}
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
        "presentation":{"entry_mode":"category","entry_mode_label":"Category detail","active_monthly_total":2450,"active_annual_total":29400,"manual_monthly_total":2450,"commitments_monthly_total":0,"total_basis":"Category entries plus financial commitments","detail_available":true,"reconciles":true,"summary_only_reason":null},
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
