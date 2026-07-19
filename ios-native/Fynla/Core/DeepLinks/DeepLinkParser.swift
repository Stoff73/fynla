import Foundation

enum DeepLinkDestination: Sendable, Equatable {
    case native(AppRoute)
    case external(URL)
    case ignored
}

struct DeepLinkParser: Sendable {
    private let allowedHost: String
    private let basePath: String

    init(environment: AppEnvironment) {
        allowedHost = environment.webBaseURL.host?.lowercased() ?? ""
        let configuredPath = environment.webBaseURL.path
        basePath = configuredPath == "/" ? "" : configuredPath
    }

    func destination(for url: URL) -> DeepLinkDestination {
        guard let components = URLComponents(url: url, resolvingAgainstBaseURL: false),
              components.scheme?.lowercased() == "https",
              components.host?.lowercased() == allowedHost,
              components.user == nil,
              components.password == nil,
              components.port == nil
        else {
            return .ignored
        }

        guard components.query == nil,
              components.fragment == nil,
              !components.percentEncodedPath.contains("%"),
              let relativePath = relativePath(components.percentEncodedPath),
              let route = route(for: relativePath)
        else {
            return .external(url)
        }

        return .native(route)
    }

    private func relativePath(_ path: String) -> String? {
        guard !basePath.isEmpty else { return path }
        guard path.hasPrefix(basePath + "/") else { return nil }
        return String(path.dropFirst(basePath.count))
    }

    private func route(for path: String) -> AppRoute? {
        let simpleRoutes: [String: AppRoute] = [
            "/dashboard": .dashboard,
            "/achievements": .achievements,
            "/income": .income,
            "/expenditure": .expenditure,
            "/net-worth": .netWorth(category: nil),
            "/net-worth/history": .balanceHistory,
            "/protection": .protection(policyType: nil, id: nil),
            "/savings": .savings(accountID: nil),
            "/investment": .investment(accountID: nil),
            "/retirement": .retirement(pensionType: nil, id: nil),
            "/estate": .estate,
            "/goals": .goals,
            "/tax-strategy": .taxStrategy,
            "/holistic-plan": .holisticPlan,
            "/settings": .settings,
        ]
        if let route = simpleRoutes[path] {
            return route
        }

        let segments = path.split(separator: "/", omittingEmptySubsequences: false)
        guard segments.first == "",
              !segments.dropFirst().contains(where: \String.SubSequence.isEmpty)
        else {
            return nil
        }
        let parts = Array(segments.dropFirst())

        if parts.count == 2,
           parts[0] == "net-worth",
           Self.netWorthCategories.contains(String(parts[1]))
        {
            return .netWorth(category: String(parts[1]))
        }

        if parts.count == 4,
           parts[0] == "protection",
           parts[1] == "policy",
           Self.protectionTypes.contains(String(parts[2])),
           let id = positiveID(parts[3])
        {
            return .protection(policyType: String(parts[2]), id: id)
        }

        if parts.count == 3,
           parts[0] == "savings",
           parts[1] == "account",
           let id = positiveID(parts[2])
        {
            return .savings(accountID: id)
        }

        if parts.count == 3,
           parts[0] == "investment",
           parts[1] == "account",
           let id = positiveID(parts[2])
        {
            return .investment(accountID: id)
        }

        if parts.count == 4,
           parts[0] == "retirement",
           parts[1] == "pension",
           Self.retirementTypes.contains(String(parts[2]))
        {
            let type = String(parts[2])
            if type == "state", parts[3] == "self" {
                return .retirement(pensionType: type, id: nil)
            }
            if let id = positiveID(parts[3]) {
                return .retirement(pensionType: type, id: id)
            }
        }

        return nil
    }

    private func positiveID(_ segment: Substring) -> Int? {
        guard segment.first != "0",
              segment.allSatisfy({ $0.isASCII && $0.isNumber }),
              let value = Int(segment),
              value > 0,
              String(value) == segment
        else {
            return nil
        }
        return value
    }

    private static let netWorthCategories: Set<String> = [
        "property",
        "investments",
        "pensions",
        "cash",
        "business",
        "chattels",
        "liabilities",
    ]

    private static let protectionTypes: Set<String> = [
        "life",
        "criticalIllness",
        "incomeProtection",
        "disability",
        "sicknessIllness",
    ]

    private static let retirementTypes: Set<String> = ["dc", "db", "state"]
}
