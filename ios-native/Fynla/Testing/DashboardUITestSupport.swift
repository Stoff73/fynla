#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum DashboardUITestComposition {
    static func model() -> DashboardModel {
        DashboardModel(client: DashboardUITestClient())
    }
}

private struct DashboardUITestClient: DashboardClient {
    func load() async throws -> DashboardSnapshot {
        let data = Data(Self.fixture.utf8)
        return try JSONDecoder().decode(
            APIEnvelope<DashboardSnapshot>.self,
            from: data
        ).data
    }

    func markRecommendationDone(_ action: DashboardAction) async throws {}

    private static let fixture = #"""
    {
      "success":true,
      "data":{
        "modules":{
          "protection":{"status":"not_configured","message":"Protection profile not yet set up."},
          "savings":{"status":"active","total_savings":12500,"total_accounts":1,"emergency_fund_months":3.2,"emergency_fund_status":"Building"},
          "investment":{"status":"not_configured","message":"No investment accounts found."},
          "retirement":{"status":"active","years_to_retirement":24,"pot_value":42000,"projected_income":9000,"income_gap":0,"total_pensions":1},
          "estate":{"status":"not_configured","message":"Estate planning not yet set up."},
          "goals":{"status":"not_configured","message":"No goals set yet."}
        },
        "net_worth":{"total":54500,"breakdown":{"assets":{"savings":12500,"pensions":42000},"liabilities":{},"total_assets":54500,"total_liabilities":0}},
        "alerts":[],
        "fyn_insight":"Your financial plan is taking shape. Regular reviews help ensure you stay on track with your goals.",
        "cached_at":"2026-07-18T09:00:00Z",
        "focus_areas":[{"key":"top","label":"Top actions","locked":false,"stat":"1 action","actions":[{"id":"saving-1","type":"recommendation","module":"savings","title":"Build your emergency fund","meta":"Review your monthly contribution","value":1200,"done":false,"action":{"kind":"navigate","payload":"/savings"}}]}],
        "next_actions":[],
        "level":{"level":2,"level_name":"Building","next_level_name":"Growing","progress_percent":25,"actions_completed":1,"actions_total":4,"actions_to_next":3},
        "percentile":42,
        "new_milestones":[],
        "next_milestone":{"group":"Savings","title":"Build a three-month emergency fund","steps":"Keep adding to your savings.","route":"m-savings"},
        "entitlement":{"tier":"free","provider":null,"status":"free","renews":false,"current_period_end":null,"capabilities":{"dashboard":"full"},"limits":{"savings_account":2}}
      }
    }
    """#
}
#endif
