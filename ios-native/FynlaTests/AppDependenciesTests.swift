import Foundation
import Testing
@testable import Fynla

@Suite("App dependencies")
struct AppDependenciesTests {
    @Test
    func buildsAnAPIClientFromTheSingleCompositionValue() async throws {
        let transport = TestHTTPTransport([
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"value":"ready"}}"#.utf8)
            ),
        ])
        let tokens = TestTokenStore(token: "runtime-token")
        let dependencies = AppDependencies(
            environment: stagingEnvironment,
            appVersion: "1.0",
            appBuild: "7",
            httpTransport: transport,
            diagnostics: NoOpDiagnosticsClient(),
            accessTokenProvider: tokens,
            clock: { Date(timeIntervalSince1970: 1_700_000_000) },
            requestID: { "composition-request" },
            featureClients: .foundation
        )

        let value = try await dependencies.makeAPIClient().send(
            APIRequest<TestValue>(path: "api/v1/native/health", method: .get)
        )

        #expect(value == TestValue(value: "ready"))
        let request = try #require(await transport.requests().first)
        #expect(request.url?.absoluteString == "https://csjones.co/fynla/api/v1/native/health")
        #expect(request.value(forHTTPHeaderField: "X-Fynla-Version") == "1.0.0")
        #expect(request.value(forHTTPHeaderField: "X-Fynla-Build") == "7")
        #expect(request.value(forHTTPHeaderField: "X-Request-ID") == "composition-request")
        #expect(request.value(forHTTPHeaderField: "Authorization") == "Bearer runtime-token")
    }

    @Test
    func authenticatedCompositionUsesInMemoryAccessAndNativeRefreshClients() async throws {
        let transport = TestHTTPTransport([
            .response(status: 401, body: Data()),
            .response(
                status: 200,
                body: Data(#"{"success":true,"data":{"value":"ready"}}"#.utf8)
            ),
        ])
        let unavailable = TestTokenStore(token: nil)
        let sessionTokens = TestTokenStore(
            token: "stale-native-access",
            refreshedToken: "rotated-native-access"
        )
        let base = AppDependencies(
            environment: stagingEnvironment,
            appVersion: "1.0",
            appBuild: "7",
            httpTransport: transport,
            diagnostics: NoOpDiagnosticsClient(),
            accessTokenProvider: unavailable,
            clock: { Date(timeIntervalSince1970: 1_700_000_000) },
            requestID: { "composition-request" },
            featureClients: .foundation
        )
        let authenticated = base.authenticatedSession(
            accessTokenProvider: sessionTokens,
            tokenRefresher: sessionTokens
        )

        let value = try await authenticated.makeAPIClient().send(
            APIRequest<TestValue>(path: "api/v1/native/health", method: .get)
        )

        #expect(value == TestValue(value: "ready"))
        #expect(await sessionTokens.refreshCount() == 1)
        let requests = await transport.requests()
        #expect(requests.map {
            $0.value(forHTTPHeaderField: "Authorization")
        } == ["Bearer stale-native-access", "Bearer rotated-native-access"])
    }

    #if FYNLA_UI_TESTING
    @Test
    @MainActor
    func acceptsOnlyCompiledUITestModes() {
        #expect(
            UITestMode(arguments: ["Fynla", "-fynla-ui-test-mode", "signed-out"])
                == .signedOut
        )
        #expect(
            UITestMode(arguments: ["Fynla", "-fynla-ui-test-mode", "unlocked"])
                == .unlocked
        )
        #expect(
            UITestMode(arguments: ["Fynla", "-fynla-ui-test-mode", "design-system"])
                == .designSystem
        )
        #expect(
            UITestMode(arguments: ["Fynla", "-fynla-ui-test-mode", "face-id-opt-in"])
                == .faceIDOptIn
        )
        #expect(
            UITestMode(
                arguments: ["Fynla", "-fynla-ui-test-mode", "face-id-unlock-success"]
            ) == .faceIDUnlockSuccess
        )
        #expect(UITestMode.signedOut.initialSessionState == .signedOut)
        #expect(UITestMode.unlocked.initialSessionState == .authenticatedUnlocked)
        #expect(UITestMode.designSystem.showsDesignSystem)
        #expect(UITestMode.faceIDOptIn.faceIDScenario == .optIn)
        #expect(UITestMode.faceIDInvalidated.faceIDScenario == .invalidated)
        #expect(
            UITestMode(
                arguments: ["Fynla", "-fynla-ui-test-mode", "subscription-free"]
            )?.subscriptionScenario == .free
        )
        #expect(
            UITestMode(
                arguments: ["Fynla", "-fynla-ui-test-mode", "subscription-web-premium"]
            )?.subscriptionScenario == .webPremium
        )
    }

    @Test
    func rejectsRetiredUnknownAndUntrustedUITestArguments() {
        #expect(UITestMode(arguments: ["Fynla", "-fynla-design-system-ui-test"]) == nil)
        #expect(UITestMode(arguments: ["Fynla", "-fynla-ui-test-mode"]) == nil)
        #expect(
            UITestMode(arguments: ["Fynla", "-fynla-ui-test-mode", "unknown"])
                == nil
        )
        #expect(
            UITestMode(
                arguments: [
                    "Fynla",
                    "-fynla-ui-test-mode",
                    "https://attacker.invalid/fixture.json",
                ]
            ) == nil
        )
        #expect(
            UITestMode(
                arguments: [
                    "Fynla",
                    "-fynla-ui-test-mode",
                    "signed-out",
                    "-fynla-ui-test-mode",
                    "unlocked",
                ]
            ) == nil
        )
    }

    @Test
    func testDependenciesFailEveryNetworkAttempt() async throws {
        let transport = TestAppDependencies.make().httpTransport
        let request = URLRequest(url: URL(string: "https://example.invalid")!)

        await expectUnexpectedNetwork {
            _ = try await transport.data(for: request)
        }
        await expectUnexpectedNetwork {
            _ = try await transport.byteStream(for: request)
        }
    }

    private func expectUnexpectedNetwork(
        _ operation: () async throws -> Void
    ) async {
        do {
            try await operation()
            Issue.record("Expected deterministic test transport to reject network access")
        } catch let error as TestDependencyError {
            #expect(error == .unexpectedNetworkRequest)
        } catch {
            Issue.record("Unexpected error: \(error)")
        }
    }
    #endif

    private var stagingEnvironment: AppEnvironment {
        try! AppEnvironment.values([
            "FYNLA_ENVIRONMENT": "staging",
            "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
            "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
        ])
    }

    private struct TestValue: Decodable, Sendable, Equatable {
        let value: String
    }
}

private struct NoOpDiagnosticsClient: DiagnosticsClient {
    func record(_ event: DiagnosticEvent) async {}
}
