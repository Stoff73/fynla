import Foundation

struct NativeRuntimePolicy: Sendable, Equatable {
    let storeKitPurchaseEnabled: Bool
}

protocol NativeVersionPolicyClient: Sendable {
    func policy() async throws -> NativeRuntimePolicy
}

struct LiveNativeVersionPolicyClient: NativeVersionPolicyClient {
    private let apiClient: APIClient

    init(apiClient: APIClient) {
        self.apiClient = apiClient
    }

    func policy() async throws -> NativeRuntimePolicy {
        let response = try await apiClient.send(
            APIRequest<NativeHealthResponse>(
                path: "api/v1/native/health",
                method: .get
            )
        )
        return NativeRuntimePolicy(
            storeKitPurchaseEnabled: response.storeKitPurchaseEnabled
        )
    }
}

struct StaticNativeVersionPolicyClient: NativeVersionPolicyClient {
    let runtimePolicy: NativeRuntimePolicy

    func policy() async throws -> NativeRuntimePolicy {
        runtimePolicy
    }
}

private struct NativeHealthResponse: Decodable, Sendable {
    let apiVersion: String
    let storeKitPurchaseEnabled: Bool

    private enum CodingKeys: String, CodingKey {
        case apiVersion = "api_version"
        case storeKitPurchaseEnabled = "storekit_purchase_enabled"
    }
}
