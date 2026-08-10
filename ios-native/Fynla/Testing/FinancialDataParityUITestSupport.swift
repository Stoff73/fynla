#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum InvestmentUITestComposition {
    static func model() -> InvestmentModel {
        InvestmentModel(client: InvestmentUITestClient())
    }
}

private struct InvestmentUITestClient: InvestmentClient {
    func load() async throws -> InvestmentSnapshot {
        try decodeFinancialFixture(
            #"""
            {
              "accounts":[{
                "id":21,
                "account_name":"Main ISA",
                "account_type":"stocks_and_shares_isa",
                "isa_type":"stocks_and_shares",
                "provider":"Example Investments",
                "platform":"Example Platform",
                "current_value":48000,
                "contributions_ytd":6000,
                "monthly_contribution_amount":500,
                "ownership_type":"individual",
                "ownership_percentage":100,
                "country":"UK",
                "owner_name":"Alex Example",
                "isa_subscription_current_year":6000,
                "holdings":[
                  {"id":201,"asset_type":"global_equity","security_name":"Global Index Fund","ticker":"GLBL","allocation_percent":75,"current_value":36000,"gain_loss":6000,"gain_loss_percent":20},
                  {"id":202,"asset_type":"bonds","security_name":"UK Bond Fund","ticker":null,"allocation_percent":25,"current_value":12000,"gain_loss":null,"gain_loss_percent":null}
                ],
                "portfolio":{
                  "contract_version":"financial_portfolio_v1",
                  "wrapper_type":"stocks_and_shares_isa",
                  "wrapper_id":21,
                  "wrapper_name":"Main ISA",
                  "recorded_wrapper_value":48000,
                  "holdings":[
                    {
                      "id":201,"name":"Global Index Fund","ticker":"GLBL","asset_type":"fund","current_value":36000,"wrapper_percentage":75,"whole_relevant_portfolio_percentage":60,
                      "classified_exposure":[{"asset_class":"equities","value":21600,"holding_percentage":60},{"asset_class":"bonds","value":9600,"holding_percentage":26.667},{"asset_class":"unclassified","value":4800,"holding_percentage":13.333}],
                      "classification":{"method":"recorded_look_through","source":"provider_factsheet","effective_at":"2026-07-31"},
                      "fees":{"available":true,"ocf_percent":0.15,"estimated_annual_cost":54,"method":"recorded_ocf","unavailable_reason":null},
                      "performance":{"available":true,"gain_loss":6000,"gain_loss_percent":20,"method":"recorded_cost_basis","unavailable_reason":null}
                    },
                    {
                      "id":202,"name":"UK Bond Fund","ticker":null,"asset_type":"bonds","current_value":12000,"wrapper_percentage":25,"whole_relevant_portfolio_percentage":20,
                      "classified_exposure":[{"asset_class":"bonds","value":12000,"holding_percentage":100}],
                      "classification":{"method":"recorded_asset_type","source":"holding_record","effective_at":null},
                      "fees":{"available":false,"ocf_percent":null,"estimated_annual_cost":null,"method":null,"unavailable_reason":"recorded_holding_charge_unavailable"},
                      "performance":{"available":false,"gain_loss":null,"gain_loss_percent":null,"method":null,"unavailable_reason":"recorded_cost_basis_unavailable"}
                    }
                  ],
                  "analysis":{
                    "total_value":48000,"classified_value":43200,"unclassified_value":4800,"coverage_percent":90,"coverage_threshold_percent":80,"drift_available":true,
                    "allocation":[{"asset_class":"equities","value":21600,"portfolio_percentage":45,"classified_percentage":50},{"asset_class":"bonds","value":21600,"portfolio_percentage":45,"classified_percentage":50},{"asset_class":"unclassified","value":4800,"portfolio_percentage":10,"classified_percentage":null}],
                    "comparisons":{
                      "entered":{"source":"user_entered","effective_at":"2026-04-06","allocation":{"equities":45,"bonds":55},"drift_percentage_points":{"equities":5,"bonds":-5},"unavailable_reason":null},
                      "recommended":{"source":"fynla_recommended_asset_allocation","effective_at":"2026-08-01","allocation":{"equities":40,"bonds":60},"drift_percentage_points":{"equities":10,"bonds":-10},"unavailable_reason":null}
                    }
                  },
                  "performance_history":{"available":true,"method":"recorded_value_snapshots","points":[{"date":"2026-01-01","value":42000,"currency":"GBP","source":"form"},{"date":"2026-07-01","value":48000,"currency":"GBP","source":"form"}],"unavailable_reason":null}
                }
              }],
              "account_count":1,
              "account_limit":2,
              "risk_profile":{"risk_category":"balanced","attitude_to_risk":"medium","risk_level":3}
            }
            """#
        )
    }
}

@MainActor
enum ProtectionUITestComposition {
    static func model() -> ProtectionModel {
        ProtectionModel(client: ProtectionUITestClient())
    }
}

private struct ProtectionUITestClient: ProtectionClient {
    func loadIndex() async throws -> ProtectionIndex {
        try decodeFinancialFixture(
            #"""
            {
              "profile":{"id":1,"annual_income":60000,"mortgage_balance":200000,"other_debts":10000,"number_of_dependents":2,"has_no_policies":false},
              "policies":{
                "life_insurance":[{"id":41,"policy_type":"level_term","provider":"Example Life","policy_number":"LIFE-41","sum_assured":250000,"premium_amount":35,"premium_frequency":"monthly","in_trust":true,"is_mortgage_protection":false}],
                "critical_illness":[],"income_protection":[],"disability":[],"sickness_illness":[]
              },
              "coverage_gaps":{
                "contract_version":"protection_gap_v1",
                "totals":{"need":410000,"cover":250000,"shortfall":160000,"coverage_percentage":60.98},
                "categories":[{
                  "key":"human_capital","label":"Income replacement capital","need":300000,"cover":40000,"shortfall":260000,"status":"gap","severity":"high",
                  "inputs":{"income_that_stops":60000,"income_that_continues":0,"net_income_difference":45000},
                  "assumptions":[{"key":"sustainable_withdrawal_rate","value":4.7,"unit":"percent"}],
                  "explanation":"This estimates capital needed to replace recorded earned income.",
                  "relevant_policies":[{"id":41,"type":"life_insurance","provider":"Example Life","name":"level_term","cover":250000}]
                }],
                "calculated_at":"2026-08-10T12:00:00Z"
              }
            }
            """#
        )
    }

    func analyze() async throws -> ProtectionAnalysis {
        throw FinancialDataParityUITestError.unavailable
    }
}

@MainActor
enum RetirementUITestComposition {
    static func model() -> RetirementModel {
        RetirementModel(client: RetirementUITestClient())
    }
}

private struct RetirementUITestClient: RetirementClient {
    func loadIndex() async throws -> RetirementIndex {
        try decodeFinancialFixture(
            #"""
            {
              "profile":{"target_retirement_age":65,"current_age":45,"target_retirement_income":42000},
              "dc_pensions":[{
                "id":31,"scheme_name":"Workplace Pension","pension_type":"workplace","scheme_type":"workplace","provider":"Example Pensions","current_fund_value":180000,"employee_contribution_percent":5,"employer_contribution_percent":5,"annual_salary":60000,"monthly_contribution_amount":0,"retirement_age":65,
                "portfolio":{
                  "contract_version":"financial_portfolio_v1","wrapper_type":"dc_pension","wrapper_id":31,"wrapper_name":"Workplace Pension","recorded_wrapper_value":180000,
                  "holdings":[{
                    "id":301,"name":"Workplace Mixed Fund","ticker":null,"asset_type":"fund","current_value":180000,"wrapper_percentage":100,"whole_relevant_portfolio_percentage":100,
                    "classified_exposure":[{"asset_class":"equities","value":126000,"holding_percentage":70},{"asset_class":"bonds","value":54000,"holding_percentage":30}],
                    "classification":{"method":"recorded_look_through","source":"provider_factsheet","effective_at":"2026-07-31"},
                    "fees":{"available":false,"ocf_percent":null,"estimated_annual_cost":null,"method":null,"unavailable_reason":"recorded_holding_charge_unavailable"},
                    "performance":{"available":false,"gain_loss":null,"gain_loss_percent":null,"method":null,"unavailable_reason":"recorded_cost_basis_unavailable"}
                  }],
                  "analysis":{
                    "total_value":180000,"classified_value":180000,"unclassified_value":0,"coverage_percent":100,"coverage_threshold_percent":80,"drift_available":true,
                    "allocation":[{"asset_class":"equities","value":126000,"portfolio_percentage":70,"classified_percentage":70},{"asset_class":"bonds","value":54000,"portfolio_percentage":30,"classified_percentage":30}],
                    "comparisons":{
                      "entered":{"source":"user_entered","effective_at":"2026-04-06","allocation":{"equities":65,"bonds":35},"drift_percentage_points":{"equities":5,"bonds":-5},"unavailable_reason":null},
                      "recommended":{"source":"fynla_recommended_asset_allocation","effective_at":"2026-08-01","allocation":{"equities":60,"bonds":40},"drift_percentage_points":{"equities":10,"bonds":-10},"unavailable_reason":null}
                    }
                  },
                  "performance_history":{"available":false,"points":[],"method":null,"unavailable_reason":"dated_value_history_unavailable"}
                }
              }],
              "db_pensions":[],"state_pension":null,"account_count":1,"account_limit":2
            }
            """#
        )
    }

    func analyze() async throws -> RetirementAnalysis {
        throw FinancialDataParityUITestError.unavailable
    }

    func loadProjections() async throws -> RetirementProjections {
        try ProjectionParityUITestFixtures.retirementProjections()
    }

    func loadDCPensionProjection(id: Int) async throws -> RetirementPotProjection {
        throw FinancialDataParityUITestError.unavailable
    }
}

private enum FinancialDataParityUITestError: Error {
    case unavailable
}

private func decodeFinancialFixture<Value: Decodable>(_ json: String) throws -> Value {
    try JSONDecoder().decode(Value.self, from: Data(json.utf8))
}
#endif
