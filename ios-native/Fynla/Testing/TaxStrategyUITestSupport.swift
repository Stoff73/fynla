#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum TaxStrategyUITestComposition {
    static func model() -> TaxStrategyModel {
        TaxStrategyModel(client: TaxStrategyUITestClient())
    }
}

// Offline fixture exercising every transcribed /m surface: household mode
// (household card + spouse allowances), an open action with a next-step CTA,
// a Watch-out warning with the adviser tag, a completed Done item, and
// allowance rows in all four statuses (spring / violet / raspberry / muted)
// plus a not-available row.
private struct TaxStrategyUITestClient: TaxStrategyClient {
    func load() async throws -> TaxStrategyDashboard {
        try JSONDecoder().decode(
            TaxStrategyEnvelope.self,
            from: Data(Self.fixture.utf8)
        ).data
    }

    func markDone(_ recommendation: TaxRecommendation) async throws {}

    private static let fixture = #"""
    {
      "data":{
        "tax_year":"2026/27",
        "calculation_mode":"dual_earner",
        "user_allowances":[
          {"key":"personal_allowance","label":"Personal Allowance","amount":12570,"used":12570,"remaining":0,"utilisation_pct":100,"status":"spring","available":true,"known":true},
          {"key":"isa","label":"ISA Allowance","amount":20000,"used":14000,"remaining":6000,"utilisation_pct":70,"status":"violet","available":true,"known":true},
          {"key":"dividend","label":"Dividend Allowance","amount":500,"used":0,"remaining":500,"utilisation_pct":0,"status":"raspberry","available":true,"known":true},
          {"key":"cgt","label":"Capital Gains Tax Allowance","amount":3000,"used":null,"remaining":null,"utilisation_pct":0,"status":"muted","available":true,"known":false},
          {"key":"marriage","label":"Marriage Allowance","amount":1260,"used":null,"remaining":null,"utilisation_pct":0,"status":"muted","available":false,"known":true}
        ],
        "spouse_allowances":[
          {"key":"personal_allowance","label":"Personal Allowance","amount":12570,"used":6000,"remaining":6570,"utilisation_pct":48,"status":"raspberry","available":true,"known":true},
          {"key":"isa","label":"ISA Allowance","amount":20000,"used":0,"remaining":20000,"utilisation_pct":0,"status":"raspberry","available":true,"known":true}
        ],
        "composed_plan":{
          "combined_annual_saving":1840,
          "items":[
            {"type":"spousal_transfer","title":"Move savings interest to your spouse","description":"Your spouse has unused Personal Savings Allowance headroom.","category":"household","estimated_annual_tax_saved":420,"recommendation_id":"rec-household-1","completed":false,"completed_at":null,"requires_advice":false},
            {"type":"isa_topup_vs_psa","title":"Shelter savings interest in your ISA","description":"Moving taxable savings into your remaining ISA allowance keeps the interest tax-free.","category":"action","estimated_annual_tax_saved":640,"recommendation_id":"rec-isa-1","completed":false,"completed_at":null,"requires_advice":false},
            {"type":"pension_aa_carry_forward","title":"Watch your pension Annual Allowance","description":"This year's contributions are close to the Annual Allowance. Carry-forward may help.","category":"warning","estimated_annual_tax_saved":780,"recommendation_id":"rec-aa-1","completed":false,"completed_at":null,"requires_advice":true},
            {"type":"dividend_allowance","title":"Use your Dividend Allowance","description":"Dividends within the allowance are tax-free.","category":"action","estimated_annual_tax_saved":null,"recommendation_id":"rec-div-1","completed":true,"completed_at":"2026-07-18T09:00:00+00:00","requires_advice":false}
          ]
        }
      }
    }
    """#
}
#endif
