import Foundation
import XCTest
@testable import Fynla

@MainActor
final class StagingHealthIntegrationTests: XCTestCase {
    func testAuthenticatedNativeHealth() async throws {
        let runtime = ProcessInfo.processInfo.environment
        guard let token = runtime["FYNLA_STAGING_BEARER_TOKEN"]?
                .trimmingCharacters(in: .whitespacesAndNewlines),
              !token.isEmpty
        else {
            throw XCTSkip("FYNLA_STAGING_BEARER_TOKEN is not set")
        }

        let environment = try AppEnvironment.values([
            "FYNLA_ENVIRONMENT": "staging",
            "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
            "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
        ])

        let dependencies = AppDependencies(
            environment: environment,
            appVersion: "1.0.0",
            appBuild: "1",
            httpTransport: URLSessionHTTPTransport.ephemeral(),
            diagnostics: IntegrationNoOpDiagnosticsClient(),
            accessTokenProvider: RuntimeTokenProvider(token: token),
            clock: { Date() },
            requestID: { UUID().uuidString },
            featureClients: .foundation
        )

        let health = try await dependencies.makeAPIClient().send(
            APIRequest<NativeHealth>(path: "api/v1/native/health", method: .get)
        )

        XCTAssertEqual(health.apiVersion, "v1")
    }
}

private struct NativeHealth: Decodable, Sendable {
    let apiVersion: String

    private enum CodingKeys: String, CodingKey {
        case apiVersion = "api_version"
    }
}

private struct RuntimeTokenProvider: AccessTokenProviding {
    let token: String

    func accessToken() async -> String? {
        token
    }
}

private struct IntegrationNoOpDiagnosticsClient: DiagnosticsClient {
    func record(_ event: DiagnosticEvent) async {}
}
