import Foundation
import Testing
@testable import Fynla

@Suite("Native version policy")
struct NativeVersionPolicyTests {
    @Test
    func mapsTheExactNativeUpdateResponseWithoutTreatingItAsAPremiumGate() async {
        let transport = TestHTTPTransport([
            .response(
                status: 426,
                body: Data(#"{"success":false,"error":"native_update_required","message":"Update Fynla to continue.","minimum_version":"1.2.3","minimum_build":42,"app_store_url":"https://apps.apple.com/app/id123456789"}"#.utf8)
            ),
        ])
        let client = LiveNativeVersionPolicyClient(apiClient: apiClient(transport))

        await #expect(throws: APIError.nativeUpdateRequired(
            NativeUpdateRequirement(
                message: "Update Fynla to continue.",
                minimumVersion: "1.2.3",
                minimumBuild: 42,
                appStoreURL: "https://apps.apple.com/app/id123456789"
            )
        )) {
            try await client.policy()
        }
    }

    @Test
    func decodesAcceptedRuntimePurchasePolicyFromTheNativeHealthEndpoint() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"api_version":"v1","storekit_purchase_enabled":false}}"#.utf8)
            ),
        ])
        let client = LiveNativeVersionPolicyClient(apiClient: apiClient(transport))

        let policy = try await client.policy()

        #expect(policy == NativeRuntimePolicy(storeKitPurchaseEnabled: false))
        #expect(await transport.requests().first?.url?.path == "/fynla/api/v1/native/health")
    }

    @Test
    func treatsTheCurrentBackendHealthPayloadAsPurchasesDisabled() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"api_version":"v1"}}"#.utf8)
            ),
        ])
        let client = LiveNativeVersionPolicyClient(apiClient: apiClient(transport))

        let policy = try await client.policy()

        #expect(policy == NativeRuntimePolicy(storeKitPurchaseEnabled: false))
    }

    @Test @MainActor
    func modelBlocksWithAValidatedAppStoreURLWhenUpdateIsRequired() async {
        let requirement = NativeUpdateRequirement(
            message: "Update Fynla to continue.",
            minimumVersion: "1.2.3",
            minimumBuild: 42,
            appStoreURL: "https://apps.apple.com/app/id123456789"
        )
        let model = NativeVersionPolicyModel(
            client: NativeVersionPolicyClientStub(result: .failure(
                .nativeUpdateRequired(requirement)
            ))
        )

        #expect(await model.validate() == false)
        #expect(model.state == .updateRequired(
            requirement: requirement,
            appStoreURL: URL(string: requirement.appStoreURL)!
        ))
    }

    @Test @MainActor
    func malformedUpdateURLFailsClosedAndSupportedPolicyExposesPurchaseState() async {
        let invalid = NativeVersionPolicyModel(
            client: NativeVersionPolicyClientStub(result: .failure(
                .nativeUpdateRequired(NativeUpdateRequirement(
                    message: "Update.",
                    minimumVersion: "2.0.0",
                    minimumBuild: 100,
                    appStoreURL: "javascript:alert(1)"
                ))
            ))
        )
        #expect(await invalid.validate() == false)
        #expect(invalid.state == .unavailable)

        let supported = NativeVersionPolicyModel(
            client: NativeVersionPolicyClientStub(result: .success(
                NativeRuntimePolicy(storeKitPurchaseEnabled: false)
            ))
        )
        #expect(await supported.validate())
        #expect(supported.state == .supported)
        #expect(supported.storeKitPurchaseEnabled == false)
    }

    private func apiClient(_ transport: TestHTTPTransport) -> APIClient {
        APIClient(
            environment: try! AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.2.3",
            build: "42",
            transport: transport,
            tokenProvider: NativePolicyTokenProvider(),
            requestID: { "native-policy-request" }
        )
    }
}

private struct NativePolicyTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "native-policy-token" }
}

private actor NativeVersionPolicyClientStub: NativeVersionPolicyClient {
    let result: Result<NativeRuntimePolicy, APIError>

    init(result: Result<NativeRuntimePolicy, APIError>) {
        self.result = result
    }

    func policy() async throws -> NativeRuntimePolicy {
        try result.get()
    }
}
