import Foundation

/// Mirrors `app/Enums/WebHandoffDestination.php`, which is the source of truth.
///
/// **Every case carries an explicit raw value equal to the PHP enum's backing
/// value, and new cases must too.** The raw value is what `IssueRequest` puts on
/// the wire, and `IssueWebHandoffRequest` validates it against the PHP enum — so a
/// Swift-defaulted raw value (`"estateWill"` rather than `"estate_will"`) is
/// rejected by the server as a 422 while looking perfectly correct in Swift. The
/// five original cases worked only because their names happen to be single
/// lowercase words; the first multi-word destination is where that runs out.
///
/// `estateWill` was absent from this mirror until W-0044 (2026-08-21), which meant
/// the native app had **no route to the Will Builder at all**. When a case is added
/// to the PHP enum, add it here and to `WebHandoffClientTests`; the PHP-side
/// `WebHandoffDestinationTest` fails until this list is brought back into line.
enum WebHandoffDestination: String, Codable, CaseIterable, Sendable {
    case admin = "admin"
    case subscription = "subscription"
    case settings = "settings"
    case privacy = "privacy"
    case notifications = "notifications"
    case estateWill = "estate_will"
    /// W-0469. `/m` hands the Inheritance Tax breakdown off to the web screen
    /// rather than rendering a subset of it; native mirrors that destination so
    /// the two mobile surfaces do not diverge on where the detail lives.
    case estateIht = "estate_iht"
    /// W-0279. Both mobile surfaces show the risk engine's conclusion and neither
    /// has a screen for the nine factors behind it, so the breakdown lives on the
    /// web app and both hand off to it.
    case riskProfile = "risk_profile"
}

private struct WebHandoffResponse: Decodable, Sendable {
    let url: URL
    let expiresAt: String

    private enum CodingKeys: String, CodingKey {
        case url
        case expiresAt = "expires_at"
    }
}

enum WebHandoffError: Error, Equatable, Sendable {
    case untrustedURL
}

protocol WebHandoffClient: Sendable {
    func issue(_ destination: WebHandoffDestination) async throws -> URL
}

struct LiveWebHandoffClient: WebHandoffClient {
    private struct IssueRequest: Encodable {
        let destination: WebHandoffDestination
    }

    private let apiClient: APIClient
    private let trustedWebBaseURL: URL

    init(apiClient: APIClient, trustedWebBaseURL: URL) {
        self.apiClient = apiClient
        self.trustedWebBaseURL = trustedWebBaseURL
    }

    func issue(_ destination: WebHandoffDestination) async throws -> URL {
        let handoff = try await apiClient.send(
            APIRequest<WebHandoffResponse>(
                path: "api/v1/mobile/web-handoffs",
                method: .post,
                body: try JSONEncoder().encode(IssueRequest(destination: destination)),
                headers: ["Cache-Control": "no-store"]
            )
        )

        guard Self.isTrusted(handoff.url, relativeTo: trustedWebBaseURL) else {
            throw WebHandoffError.untrustedURL
        }
        return handoff.url
    }

    static func isTrusted(_ candidate: URL, relativeTo baseURL: URL) -> Bool {
        guard let candidateComponents = URLComponents(
            url: candidate,
            resolvingAgainstBaseURL: false
        ),
        let baseComponents = URLComponents(
            url: baseURL,
            resolvingAgainstBaseURL: false
        ),
        candidateComponents.scheme?.lowercased() == "https",
        candidateComponents.scheme?.lowercased() == baseComponents.scheme?.lowercased(),
        candidateComponents.host?.lowercased() == baseComponents.host?.lowercased(),
        candidateComponents.port == baseComponents.port,
        candidateComponents.user == nil,
        candidateComponents.password == nil
        else {
            return false
        }

        let basePath = baseComponents.path.trimmingCharacters(in: CharacterSet(charactersIn: "/"))
        guard !basePath.isEmpty else { return true }
        let trustedPrefix = "/\(basePath)"
        return candidateComponents.path == trustedPrefix
            || candidateComponents.path.hasPrefix("\(trustedPrefix)/")
    }
}
