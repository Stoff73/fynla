#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum PersonalInformationUITestComposition {
    static func model() -> PersonalInformationModel {
        PersonalInformationModel(client: PersonalInformationUITestClient())
    }
}

private struct PersonalInformationUITestClient: PersonalInformationClient {
    func load() async throws -> PersonalInformationProfile {
        try JSONDecoder().decode(
            PersonalInformationProfile.self,
            from: Data(
                #"""
                {
                  "personal_info":{"id":73,"name":"Alex Morgan","email":"alex@example.test","date_of_birth":"1984-06-12","age":42,"gender":"female","marital_status":"married","national_insurance_number":"***3456","address":{"line_1":"10 Savannah Way","line_2":null,"city":"London","county":"Greater London","postcode":"SW1A 1AA"},"phone":"020 7946 0958"},
                  "household":{"id":19,"name":"Morgan household"},
                  "spouse":{"id":74,"name":"Sam Morgan","email":"sam@example.test"},
                  "income_occupation":{"total_annual_income":86000},
                  "expenditure":{"monthly_expenditure":3250},
                  "domicile_info":{"domicile_status":"uk_domiciled","explanation":"Domiciled in the United Kingdom","country_of_birth":"United Kingdom"},
                  "assets_summary":{"total":512500},
                  "liabilities_summary":{"total":187250},
                  "net_worth":325250
                }
                """#.utf8
            )
        )
    }
}
#endif
