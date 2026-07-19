import Foundation

protocol PushClient: Sendable {
    func register(_ registration: PushDeviceRegistration) async throws
    func unregister(deviceID: String) async throws
    func loadPreferences() async throws -> PushPreferences
    func updatePreferences(_ values: [PushPreferenceKey: Bool]) async throws
}

struct LivePushClient: PushClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func register(_ registration: PushDeviceRegistration) async throws {
        let body = try JSONEncoder().encode(registration)
        _ = try await apiClient.send(APIRequest<PushRegistrationResponse>(
            path: "api/v1/mobile/devices",
            method: .post,
            body: body
        ))
    }

    func unregister(deviceID: String) async throws {
        _ = try await apiClient.sendData(APIRequest<EmptyPushResponse>(
            path: "api/v1/mobile/devices/\(deviceID)",
            method: .delete,
            responseDecoding: .raw
        ))
    }

    func loadPreferences() async throws -> PushPreferences {
        try await apiClient.send(APIRequest<PushPreferences>(
            path: "api/v1/mobile/notifications/preferences",
            method: .get,
            headers: ["Cache-Control": "no-cache"]
        ))
    }

    func updatePreferences(_ values: [PushPreferenceKey: Bool]) async throws {
        let body = try JSONEncoder().encode(
            Dictionary(uniqueKeysWithValues: values.map { ($0.key.rawValue, $0.value) })
        )
        _ = try await apiClient.sendData(APIRequest<EmptyPushResponse>(
            path: "api/v1/mobile/notifications/preferences",
            method: .put,
            body: body,
            responseDecoding: .raw
        ))
    }
}

private struct PushRegistrationResponse: Decodable, Sendable {
    let deviceID: String

    private enum CodingKeys: String, CodingKey {
        case deviceID = "device_id"
    }
}

private struct EmptyPushResponse: Decodable, Sendable {}
