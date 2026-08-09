import Foundation

enum WebHandoffDestination: String, Codable, CaseIterable, Sendable {
    case admin
    case subscription
    case settings
    case privacy
    case notifications
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
