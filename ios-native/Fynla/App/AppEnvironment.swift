import Foundation

enum ConfigurationError: Error, Equatable, Sendable {
    case missingValue(String)
    case invalidEnvironment(String)
    case invalidURL(String)
    case nonHTTPSURL(String)
    case userInfoURL(String)
}

struct AppEnvironment: Sendable, Equatable {
    enum Name: String, Sendable {
        case staging
        case production
    }

    let name: Name
    let apiBaseURL: URL
    let webBaseURL: URL
    let clientName = "ios"
    let productIdentifiers: Set<String> = [
        "org.fynla.premium.monthly",
        "org.fynla.premium.annual",
    ]

    static func bundle(_ bundle: Bundle = .main) throws -> Self {
        try values(bundle.infoDictionary ?? [:])
    }

    static func values(_ values: [String: Any]) throws -> Self {
        let environmentValue = try requiredString("FYNLA_ENVIRONMENT", in: values)
        guard let name = Name(rawValue: environmentValue) else {
            throw ConfigurationError.invalidEnvironment(environmentValue)
        }

        return try Self(
            name: name,
            apiBaseURL: validatedURL("FYNLA_API_BASE_URL", in: values),
            webBaseURL: validatedURL("FYNLA_WEB_BASE_URL", in: values)
        )
    }

    private static func requiredString(
        _ key: String,
        in values: [String: Any]
    ) throws -> String {
        guard let value = values[key] as? String,
              !value.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
        else {
            throw ConfigurationError.missingValue(key)
        }

        return value
    }

    private static func validatedURL(
        _ key: String,
        in values: [String: Any]
    ) throws -> URL {
        let value = try requiredString(key, in: values)

        guard let components = URLComponents(string: value),
              components.scheme != nil,
              components.host?.isEmpty == false,
              let url = components.url
        else {
            throw ConfigurationError.invalidURL(key)
        }

        guard components.scheme?.lowercased() == "https" else {
            throw ConfigurationError.nonHTTPSURL(key)
        }

        guard components.user == nil, components.password == nil else {
            throw ConfigurationError.userInfoURL(key)
        }

        return url
    }
}
