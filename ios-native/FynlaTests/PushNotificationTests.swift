import Foundation
import Testing
@testable import Fynla

@Suite("Privacy-safe push notifications")
struct PushNotificationTests {
    @Test
    func clientUsesExistingDeviceAndPreferenceEndpoints() async throws {
        let transport = TestHTTPTransport([
            .response(status: 201, body: json(#"{"success":true,"data":{"device_id":"session-123"}}"#)),
            .response(status: 200, body: preferencesJSON),
            .response(status: 200, body: json(#"{"success":true,"message":"Notification preferences updated."}"#)),
            .response(status: 200, body: json(#"{"success":true,"message":"Device revoked."}"#)),
        ])
        let client = LivePushClient(apiClient: apiClient(transport))
        let registration = PushDeviceRegistration(
            token: "00abff",
            deviceID: "session-123",
            appVersion: "1.0.0",
            osVersion: "iOS 26.0"
        )

        try await client.register(registration)
        let preferences = try await client.loadPreferences()
        try await client.updatePreferences([.marketUpdates: true])
        try await client.unregister(deviceID: "session-123")

        #expect(preferences.marketUpdates == false)
        let requests = await transport.requests()
        #expect(requests.map(\.url?.path) == [
            "/fynla/api/v1/mobile/devices",
            "/fynla/api/v1/mobile/notifications/preferences",
            "/fynla/api/v1/mobile/notifications/preferences",
            "/fynla/api/v1/mobile/devices/session-123",
        ])
        #expect(requests.map(\.httpMethod) == ["POST", "GET", "PUT", "DELETE"])
        #expect(body(requests[0]) == [
            "device_token": "00abff",
            "device_id": "session-123",
            "platform": "ios",
            "device_name": "iPhone",
            "app_version": "1.0.0",
            "os_version": "iOS 26.0",
        ])
        #expect(body(requests[2]) == ["market_updates": true])
    }

    @Test
    func notificationRouterMapsOnlyAllowlistedRouteKeys() {
        let router = NotificationRouter()

        #expect(router.route(from: ["route": "dashboard"]) == .dashboard)
        #expect(router.route(from: ["route": "income"]) == .income)
        #expect(router.route(from: ["route": "net_worth"]) == .netWorth(category: nil))
        #expect(router.route(from: ["route": "tax_strategy"]) == .taxStrategy)
        #expect(router.route(from: ["route": "settings"]) == .settings)
        #expect(router.route(from: ["route": "admin/users"]) == nil)
        #expect(router.route(from: ["route": "api/v1/users"]) == nil)
        #expect(router.route(from: ["route": "unknown"]) == nil)
        #expect(router.route(from: ["amount": "£50,000"]) == nil)
    }

    @Test @MainActor
    func authorisedRegistrationEncodesTokenAsLowercaseHexAndUsesNativeSessionID() async {
        let session = AppSession(state: .authenticatedUnlocked)
        let appRouter = AppRouter(session: session)
        let system = SystemPushClientStub(status: .authorized)
        let client = PushClientStub()
        let coordinator = PushRegistrationCoordinator(
            system: system,
            client: client,
            session: session,
            router: appRouter,
            nativeSessionID: { "session-123" },
            metadata: PushDeviceMetadata(
                appVersion: "1.0.0",
                osVersion: "iOS 26.0"
            )
        )

        await coordinator.start()
        await coordinator.didRegisterForRemoteNotifications(
            deviceToken: Data([0x00, 0xAB, 0xFF])
        )

        #expect(system.registrationCount == 1)
        #expect(await client.registrations() == [
            PushDeviceRegistration(
                token: "00abff",
                deviceID: "session-123",
                appVersion: "1.0.0",
                osVersion: "iOS 26.0"
            ),
        ])
        #expect(coordinator.state == .authorized)
    }

    @Test @MainActor
    func permissionIsRequestedOnlyFromExplicitEnableAction() async {
        let session = AppSession(state: .authenticatedUnlocked)
        let system = SystemPushClientStub(
            status: .notDetermined,
            requestedStatus: .denied
        )
        let coordinator = PushRegistrationCoordinator(
            system: system,
            client: PushClientStub(),
            session: session,
            router: AppRouter(session: session),
            nativeSessionID: { "session-123" },
            metadata: PushDeviceMetadata(appVersion: "1.0.0", osVersion: "iOS 26.0")
        )

        await coordinator.start()
        #expect(system.requestCount == 0)
        #expect(system.registrationCount == 0)
        #expect(coordinator.state == .notDetermined)

        await coordinator.enable()
        #expect(system.requestCount == 1)
        #expect(system.registrationCount == 0)
        #expect(coordinator.state == .denied)
    }

    @Test @MainActor
    func tappedRouteWaitsForUnlockAndMalformedPayloadIsIgnored() async {
        let session = AppSession(state: .authenticatedLocked)
        let appRouter = AppRouter(session: session)
        let coordinator = PushRegistrationCoordinator(
            system: SystemPushClientStub(status: .authorized),
            client: PushClientStub(),
            session: session,
            router: appRouter,
            nativeSessionID: { "session-123" },
            metadata: PushDeviceMetadata(appVersion: "1.0.0", osVersion: "iOS 26.0")
        )

        coordinator.receiveNotification(["route": "income"])
        coordinator.receiveNotification(["route": "admin/users"])
        #expect(appRouter.path.isEmpty)
        #expect(coordinator.pendingRoute == .income)

        #expect(session.unlock())
        coordinator.sessionDidChange()
        #expect(appRouter.path == [.income])
        #expect(coordinator.pendingRoute == nil)
    }

    @Test @MainActor
    func unregisterRemovesTheStableDeviceBeforeLocalSignOut() async {
        let session = AppSession(state: .authenticatedUnlocked)
        let client = PushClientStub()
        let coordinator = PushRegistrationCoordinator(
            system: SystemPushClientStub(status: .authorized),
            client: client,
            session: session,
            router: AppRouter(session: session),
            nativeSessionID: { "session-123" },
            metadata: PushDeviceMetadata(appVersion: "1.0.0", osVersion: "iOS 26.0")
        )

        await coordinator.unregister()

        #expect(await client.unregisteredDeviceIDs() == ["session-123"])
    }

    private var preferencesJSON: Data {
        json(#"{"success":true,"data":{"policy_renewals":true,"goal_milestones":true,"contribution_reminders":true,"market_updates":false,"fyn_daily_insight":true,"security_alerts":true,"payment_alerts":true,"mortgage_rate_alerts":true,"estate_alerts":true,"lifecycle_churned_subscriber":true,"lifecycle_lapsed_subscriber":true}}"#)
    }

    private func apiClient(_ transport: TestHTTPTransport) -> APIClient {
        APIClient(
            environment: try! AppEnvironment.values([
                "FYNLA_ENVIRONMENT": "staging",
                "FYNLA_API_BASE_URL": "https://csjones.co/fynla",
                "FYNLA_WEB_BASE_URL": "https://csjones.co/fynla",
            ]),
            version: "1.0.0",
            build: "12",
            transport: transport,
            tokenProvider: PushTokenProvider(),
            requestID: { "push-request" }
        )
    }

    private func body(_ request: URLRequest) -> [String: AnyHashable] {
        guard let data = request.httpBody,
              let object = try? JSONSerialization.jsonObject(with: data) as? [String: AnyHashable]
        else { return [:] }
        return object
    }

    private func json(_ value: String) -> Data { Data(value.utf8) }
}

private struct PushTokenProvider: AccessTokenProviding {
    func accessToken() async -> String? { "push-token" }
}

@MainActor
private final class SystemPushClientStub: SystemPushClient {
    private(set) var requestCount = 0
    private(set) var registrationCount = 0
    var status: PushAuthorizationStatus
    let requestedStatus: PushAuthorizationStatus

    init(
        status: PushAuthorizationStatus,
        requestedStatus: PushAuthorizationStatus? = nil
    ) {
        self.status = status
        self.requestedStatus = requestedStatus ?? status
    }

    func authorizationStatus() async -> PushAuthorizationStatus { status }

    func requestAuthorization() async throws -> PushAuthorizationStatus {
        requestCount += 1
        status = requestedStatus
        return status
    }

    func registerForRemoteNotifications() {
        registrationCount += 1
    }
}

private actor PushClientStub: PushClient {
    private var registered: [PushDeviceRegistration] = []
    private var unregistered: [String] = []

    func register(_ registration: PushDeviceRegistration) async throws {
        registered.append(registration)
    }

    func unregister(deviceID: String) async throws {
        unregistered.append(deviceID)
    }

    func loadPreferences() async throws -> PushPreferences { .defaults }

    func updatePreferences(_ values: [PushPreferenceKey: Bool]) async throws {}

    func registrations() -> [PushDeviceRegistration] { registered }
    func unregisteredDeviceIDs() -> [String] { unregistered }
}
