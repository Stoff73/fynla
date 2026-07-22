#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum HolisticPlanUITestComposition {
    static func model() -> HolisticPlanModel {
        HolisticPlanModel(client: HolisticPlanUITestClient())
    }
}

// Offline fixture exercising every transcribed /m surface: goal-adjusted
// surplus context with near-term capital, ranked items in all three
// affordability states (with priority badges, monthly cost and a conflict
// note), and locked strategies with acronym-expanded labels.
private struct HolisticPlanUITestClient: HolisticPlanClient {
    func load() async throws -> HolisticPlan {
        try JSONDecoder().decode(
            HolisticPlan.self,
            from: Data(Self.fixture.utf8)
        )
    }

    private static let fixture = #"""
    {
      "available_monthly_surplus":650,
      "goal_commitments":150,
      "effective_surplus":500,
      "near_term_capital":4000,
      "items":[
        {"type":"emergency_fund_topup","priority":"high","title":"Top up your emergency fund","description":"Three months of essential spending keeps you resilient.","estimated_annual_tax_saved":null,"requires_advice":false,"required_monthly_cost":200,"required_lump_sum":null,"sequence_position":1,"conflict_note":null,"module":"savings","affordability":"fits"},
        {"type":"pension_contribution_increase","priority":"medium","title":"Increase your pension contributions","description":"Extra contributions attract tax relief at your marginal rate.","estimated_annual_tax_saved":720,"requires_advice":false,"required_monthly_cost":250,"required_lump_sum":null,"sequence_position":2,"conflict_note":"Overlaps with your ISA top-up — the same surplus cannot fund both in full.","module":"retirement","affordability":"partially_fits"},
        {"type":"isa_topup","priority":"low","title":"Move taxable savings into your ISA","description":"Shelters future interest from tax.","estimated_annual_tax_saved":180,"requires_advice":false,"required_monthly_cost":400,"required_lump_sum":null,"sequence_position":3,"conflict_note":null,"module":"investment","affordability":"beyond_current_surplus"}
      ],
      "locked":[
        {"strategy_type":"pension_aa_carry_forward","missing":["pension_contribution_history"],"module":"retirement"},
        {"strategy_type":"cgt_harvest_gia","missing":["investment_cost_basis"],"module":"investment"}
      ]
    }
    """#
}
#endif
