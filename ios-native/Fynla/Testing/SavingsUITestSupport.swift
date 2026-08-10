#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum SavingsUITestComposition {
    static func model() -> SavingsModel {
        SavingsModel(client: SavingsUITestClient())
    }
}

private struct SavingsUITestClient: SavingsClient {
    func load() async throws -> SavingsSnapshot {
        try decode(
            #"""
            {
              "accounts":[
                {"id":11,"account_name":"Emergency savings","provider":"Example Bank","institution":"Example Bank","account_type":"easy_access","current_balance":12500,"full_balance":12500,"user_share":12500,"interest_rate":4.35,"access_type":"immediate","ownership_type":"individual","ownership_percentage":100,"country":"UK","is_emergency_fund":true,"is_isa":false,"is_primary_owner":true,"is_shared":false},
                {"id":12,"account_name":"Cash ISA","provider":"Example Mutual","institution":"Example Mutual","account_type":"cash_isa","current_balance":20000,"full_balance":20000,"user_share":10000,"interest_rate":4.10,"access_type":"fixed","ownership_type":"joint","ownership_percentage":50,"country":"UK","is_emergency_fund":false,"is_isa":true,"isa_type":"cash","isa_subscription_year":"2026/27","isa_subscription_amount":5000,"maturity_date":"2027-07-18","is_primary_owner":true,"is_shared":true}
              ],
              "account_count":2,
              "account_limit":2,
              "expenditure_profile":{"total_monthly_expenditure":3000,"total_annual_expenditure":36000},
              "isa_allowance":{"cash_isa_used":5000,"stocks_shares_isa_used":3000,"lisa_used":0,"total_used":8000,"total_allowance":20000,"remaining":12000,"percentage_used":40},
              "emergency_fund_target":{"target_months":6,"target_amount":18000,"employment_status":"employed","rationale":"Six months of essential expenditure."}
            }
            """#
        )
    }

    func loadAccount(id: Int) async throws -> SavingsAccount {
        try decode(
            """
            {"id":\(id),"account_name":"Cash ISA","provider":"Example Mutual","institution":"Example Mutual","account_type":"cash_isa","current_balance":20000,"full_balance":20000,"user_share":10000,"interest_rate":4.10,"access_type":"fixed","ownership_type":"joint","ownership_percentage":50,"country":"UK","is_emergency_fund":false,"is_isa":true,"isa_type":"cash","isa_subscription_year":"2026/27","isa_subscription_amount":5000,"maturity_date":"2027-07-18","is_primary_owner":true,"is_shared":true,"owner_name":"Alex Example","joint_owner_name":"Sam Example"}
            """
        )
    }

    private func decode<Value: Decodable>(_ json: String) throws -> Value {
        try JSONDecoder().decode(Value.self, from: Data(json.utf8))
    }
}
#endif
