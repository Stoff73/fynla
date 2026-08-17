import Foundation

struct PersonalInformationProfile: Decodable, Sendable, Equatable {
    let personalInfo: PersonalInformation
    let household: PersonalInformationHousehold?
    let spouse: PersonalInformationHouseholdMember?
    let incomeOccupation: PersonalInformationIncome?
    let expenditure: PersonalInformationExpenditure?
    let domicileInfo: PersonalInformationDomicile?
    let assetsSummary: PersonalInformationFinancialTotal?
    let liabilitiesSummary: PersonalInformationFinancialTotal?
    let netWorth: Decimal?

    private enum CodingKeys: String, CodingKey {
        case personalInfo = "personal_info"
        case household
        case spouse
        case incomeOccupation = "income_occupation"
        case expenditure
        case domicileInfo = "domicile_info"
        case assetsSummary = "assets_summary"
        case liabilitiesSummary = "liabilities_summary"
        case netWorth = "net_worth"
    }

    var householdLabel: String {
        if let name = household?.name?.nonEmpty {
            return name
        }
        if let name = spouse?.name.nonEmpty {
            return "Household with \(name)"
        }
        return "Single-person household"
    }
}

struct PersonalInformation: Decodable, Sendable, Equatable {
    let id: Int
    let name: String
    let email: String
    let dateOfBirth: String?
    let age: Int?
    let gender: String?
    let maritalStatus: String?
    let nationalInsuranceNumber: String?
    let address: PersonalInformationAddress
    let phone: String?

    private enum CodingKeys: String, CodingKey {
        case id
        case name
        case email
        case dateOfBirth = "date_of_birth"
        case age
        case gender
        case maritalStatus = "marital_status"
        case nationalInsuranceNumber = "national_insurance_number"
        case address
        case phone
    }
}

struct PersonalInformationAddress: Decodable, Sendable, Equatable {
    let line1: String?
    let line2: String?
    let city: String?
    let county: String?
    let postcode: String?

    private enum CodingKeys: String, CodingKey {
        case line1 = "line_1"
        case line2 = "line_2"
        case city
        case county
        case postcode
    }

    var formatted: String {
        [line1, line2, city, county, postcode]
            .compactMap { $0?.nonEmpty }
            .joined(separator: ", ")
    }
}

struct PersonalInformationHousehold: Decodable, Sendable, Equatable {
    let id: Int?
    let name: String?
}

struct PersonalInformationHouseholdMember: Decodable, Sendable, Equatable {
    let id: Int
    let name: String
    let email: String?
}

struct PersonalInformationIncome: Decodable, Sendable, Equatable {
    let totalAnnualIncome: Decimal?

    private enum CodingKeys: String, CodingKey {
        case totalAnnualIncome = "total_annual_income"
    }
}

struct PersonalInformationExpenditure: Decodable, Sendable, Equatable {
    let monthlyExpenditure: Decimal?

    private enum CodingKeys: String, CodingKey {
        case monthlyExpenditure = "monthly_expenditure"
    }
}

struct PersonalInformationDomicile: Decodable, Sendable, Equatable {
    let domicileStatus: String?
    let explanation: String?
    let countryOfBirth: String?

    private enum CodingKeys: String, CodingKey {
        case domicileStatus = "domicile_status"
        case explanation
        case countryOfBirth = "country_of_birth"
    }

    var display: String {
        if let explanation = explanation?.nonEmpty {
            return explanation
        }
        if let domicileStatus = domicileStatus?.nonEmpty {
            return domicileStatus.replacingOccurrences(of: "_", with: " ")
        }
        return "Not recorded"
    }
}

struct PersonalInformationFinancialTotal: Decodable, Sendable, Equatable {
    let total: Decimal?
}

enum PersonalInformationViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(PersonalInformationProfile)
    case offline(previous: PersonalInformationProfile?)
    case unauthenticated
    case failed(requestID: String?)
}

private extension String {
    var nonEmpty: String? {
        let trimmed = trimmingCharacters(in: .whitespacesAndNewlines)
        return trimmed.isEmpty ? nil : trimmed
    }
}
