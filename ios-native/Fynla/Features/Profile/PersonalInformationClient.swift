import Foundation

protocol PersonalInformationClient: Sendable {
    func load() async throws -> PersonalInformationProfile
}

struct LivePersonalInformationClient: PersonalInformationClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func load() async throws -> PersonalInformationProfile {
        try await apiClient.send(
            APIRequest<PersonalInformationProfile>(
                path: "api/user/profile",
                method: .get,
                headers: ["Cache-Control": "no-cache"]
            )
        )
    }
}
