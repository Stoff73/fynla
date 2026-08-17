#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum ProjectionParityUITestComposition {
    static func netWorthModel() -> NetWorthModel {
        NetWorthModel(client: ProjectionParityNetWorthClient())
    }

    static func netWorthForecastModel() -> NetWorthForecastModel {
        NetWorthForecastModel(client: ProjectionParityNetWorthForecastClient())
    }
}

enum ProjectionParityUITestFixtures {
    static func retirementProjections() throws -> RetirementProjections {
        try decode(
            #"""
            {
              "planning_projection":{
                "contract_version":"retirement_projection_v1",
                "as_of":"2026-08-10",
                "target_retirement_age":65,
                "projection_end_age":100,
                "planning_total_at_target_age":28405,
                "products":[
                  {"resource_type":"db_pension","resource_id":32,"name":"Career Average Pension","commencement_age":65,"current_value":null,"monthly_contribution":null,"projected_value":null,"annual_income":12000,"income_method":"guaranteed_scheme_income"},
                  {"resource_type":"state_pension","resource_id":33,"name":"State Pension","commencement_age":67,"current_value":null,"monthly_contribution":null,"projected_value":null,"annual_income":11502,"income_method":"recorded_state_pension_forecast"},
                  {"resource_type":"dc_pension","resource_id":31,"name":"Workplace Pension","commencement_age":70,"current_value":180000,"monthly_contribution":500,"projected_value":348650,"annual_income":16405,"income_method":"sustainable_withdrawal_rate"}
                ],
                "age_bands":[
                  {"start_age":65,"end_age":66,"annual_income":12000,"source_ids":["db_pension:32"]},
                  {"start_age":67,"end_age":69,"annual_income":23502,"source_ids":["db_pension:32","state_pension:33"]},
                  {"start_age":70,"end_age":100,"annual_income":39907,"source_ids":["db_pension:32","state_pension:33","dc_pension:31"]}
                ],
                "assumptions":{
                  "sustainable_withdrawal_rate":{"decimal":0.047,"percent":4.7,"source":"tax_configuration"},
                  "growth_rate_percent":5,
                  "net_growth_rate_percent":4.5,
                  "inflation_rate_percent":2,
                  "fee_rate_percent":0.5,
                  "compound_periods":12,
                  "basis":"nominal",
                  "has_user_overrides":false
                },
                "warnings":[]
              }
            }
            """#
        )
    }

    static func netWorthSnapshot() throws -> NetWorthSnapshot {
        let overview: NetWorthOverview = try decode(
            #"""
            {
              "total_assets":700000,
              "total_liabilities":220000,
              "net_worth":480000,
              "as_of_date":"2026-08-10",
              "breakdown":{"pensions":180000,"property":420000,"investments":60000,"cash":30000,"business":5000,"chattels":5000},
              "has_db_pensions":true,
              "liabilities_breakdown":{"mortgages":210000,"loans":10000,"credit_cards":0,"other":0}
            }
            """#
        )
        let detailed: NetWorthDetailedAssets = try decode(
            #"""
            {
              "pensions":{"count":1,"total_value":180000,"items":[]},
              "property":{"count":1,"total_value":420000,"items":[]},
              "investments":{"count":1,"total_value":60000,"items":[]},
              "cash":{"count":2,"total_value":30000,"items":[]},
              "business":{"count":1,"total_value":5000,"items":[]},
              "chattels":{"count":1,"total_value":5000,"items":[]},
              "liabilities":{"count":2,"total_value":220000,"items":[]}
            }
            """#
        )
        return NetWorthSnapshot(overview: overview, detailed: detailed)
    }

    static func netWorthForecast() throws -> NetWorthForecast {
        try decode(
            #"""
            {
              "contract_version":"net_worth_forecast_v1",
              "recorded_as_of":"2026-08-10",
              "current":{"assets":{"property":420000,"investments":60000,"pensions":180000,"cash":30000,"business":5000,"valuables":5000},"liabilities":{"mortgages":210000,"other_liabilities":10000},"total_assets":700000,"total_liabilities":220000,"net_worth":480000},
              "points":[
                {"year":0,"calendar_year":2026,"categories":{"property":420000,"investments":60000,"pensions":180000,"cash":30000,"business":5000,"valuables":5000},"liabilities":{"mortgages":210000,"other_liabilities":10000},"total_assets":700000,"total_liabilities":220000,"net_worth":480000,"source":"recorded"},
                {"year":1,"calendar_year":2027,"categories":{"property":432600,"investments":63000,"pensions":189000,"cash":30600,"business":5150,"valuables":5100},"liabilities":{"mortgages":202000,"other_liabilities":9000},"total_assets":725450,"total_liabilities":211000,"net_worth":514450,"source":"projected"},
                {"year":10,"calendar_year":2036,"categories":{"property":564443,"investments":97734,"pensions":293202,"cash":36570,"business":6719,"valuables":6095},"liabilities":{"mortgages":120000,"other_liabilities":1000},"total_assets":1004763,"total_liabilities":121000,"net_worth":883763,"source":"projected"},
                {"year":30,"calendar_year":2056,"categories":{"property":1019510,"investments":259317,"pensions":777951,"cash":54341,"business":12136,"valuables":9057},"liabilities":{"mortgages":0,"other_liabilities":0},"total_assets":2132312,"total_liabilities":0,"net_worth":2132312,"source":"projected"}
              ],
              "assumptions":{
                "property":{"rate_percent":3,"source":"system_default","effective_from":"2026-08-10","basis":"nominal"},
                "investments":{"rate_percent":5,"source":"system_default","effective_from":"2026-08-10","basis":"nominal"},
                "pensions":{"rate_percent":5,"source":"system_default","effective_from":"2026-08-10","basis":"nominal"},
                "cash":{"rate_percent":2,"source":"system_default","effective_from":"2026-08-10","basis":"nominal"},
                "business":{"rate_percent":3,"source":"system_default","effective_from":"2026-08-10","basis":"nominal"},
                "valuables":{"rate_percent":2,"source":"system_default","effective_from":"2026-08-10","basis":"nominal"},
                "mortgages":{"rate_percent":0,"source":"system_default","effective_from":"2026-08-10","basis":"nominal"},
                "other_liabilities":{"rate_percent":0,"source":"system_default","effective_from":"2026-08-10","basis":"nominal"}
              },
              "warnings":["Forecasts are illustrations, not guarantees."]
            }
            """#
        )
    }

    private static func decode<Value: Decodable>(_ json: String) throws -> Value {
        try JSONDecoder().decode(Value.self, from: Data(json.utf8))
    }
}

private struct ProjectionParityNetWorthClient: NetWorthClient {
    func load() async throws -> NetWorthSnapshot {
        try ProjectionParityUITestFixtures.netWorthSnapshot()
    }
}

private actor ProjectionParityNetWorthForecastClient: NetWorthForecastClient {
    private var forecast: NetWorthForecast

    init() {
        forecast = try! ProjectionParityUITestFixtures.netWorthForecast()
    }

    func load() async throws -> NetWorthForecast {
        forecast
    }

    func updateAssumptions(
        _ update: NetWorthForecastAssumptionUpdate
    ) async throws -> NetWorthForecastAssumptions {
        let assumptions = makeAssumptions(
            rates: update.rates,
            source: .userOverride,
            basis: update.basis
        )
        forecast = replacingAssumptions(in: forecast, with: assumptions)
        return assumptions
    }

    func resetAssumptions() async throws -> NetWorthForecastAssumptions {
        let defaults = try ProjectionParityUITestFixtures.netWorthForecast().assumptions
        forecast = replacingAssumptions(in: forecast, with: defaults)
        return defaults
    }

    private func makeAssumptions(
        rates: [NetWorthForecastCategory: Decimal],
        source: NetWorthForecastAssumptionSource,
        basis: NetWorthForecastBasis
    ) -> NetWorthForecastAssumptions {
        func value(_ category: NetWorthForecastCategory) -> NetWorthForecastAssumption {
            NetWorthForecastAssumption(
                ratePercent: rates[category] ?? forecast.assumptions[category].ratePercent,
                source: source,
                effectiveFrom: "2026-08-10",
                basis: basis
            )
        }

        return NetWorthForecastAssumptions(
            property: value(.property),
            investments: value(.investments),
            pensions: value(.pensions),
            cash: value(.cash),
            business: value(.business),
            valuables: value(.valuables),
            mortgages: value(.mortgages),
            otherLiabilities: value(.otherLiabilities)
        )
    }

    private func replacingAssumptions(
        in forecast: NetWorthForecast,
        with assumptions: NetWorthForecastAssumptions
    ) -> NetWorthForecast {
        NetWorthForecast(
            contractVersion: forecast.contractVersion,
            recordedAsOf: forecast.recordedAsOf,
            current: forecast.current,
            points: forecast.points,
            assumptions: assumptions,
            warnings: forecast.warnings
        )
    }
}
#endif
