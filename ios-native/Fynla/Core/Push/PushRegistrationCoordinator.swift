import Foundation
import Observation

@MainActor
@Observable
final class PushRegistrationCoordinator {
    private(set) var state: PushAuthorizationStatus = .notDetermined
    private(set) var preferences: PushPreferences?
    private(set) var pendingRoute: AppRoute?
    private(set) var message: String?
    private(set) var updatingPreference: PushPreferenceKey?

    private let system: any SystemPushClient
    private let client: any PushClient
    private let session: AppSession
    private let router: AppRouter
    private let nativeSessionID: @MainActor @Sendable () -> String?
    private let metadata: PushDeviceMetadata
    private let notificationRouter = NotificationRouter()
    private var registeredDeviceID: String?

    init(
        system: any SystemPushClient,
        client: any PushClient,
        session: AppSession,
        router: AppRouter,
        nativeSessionID: @escaping @MainActor @Sendable () -> String?,
        metadata: PushDeviceMetadata
    ) {
        self.system = system
        self.client = client
        self.session = session
        self.router = router
        self.nativeSessionID = nativeSessionID
        self.metadata = metadata
    }

    func start() async {
        state = await system.authorizationStatus()
        if state.permitsRegistration {
            system.registerForRemoteNotifications()
            await loadPreferences()
        }
    }

    func enable() async {
        message = nil
        do {
            state = try await system.requestAuthorization()
            if state.permitsRegistration {
                system.registerForRemoteNotifications()
                await loadPreferences()
            }
        } catch {
            message = "Notification permission could not be requested. Please try again."
        }
    }

    func didRegisterForRemoteNotifications(deviceToken: Data) async {
        guard state.permitsRegistration,
              let deviceID = nativeSessionID(),
              !deviceID.isEmpty,
              !deviceToken.isEmpty
        else { return }
        let token = deviceToken.map { String(format: "%02x", $0) }.joined()
        do {
            try await client.register(PushDeviceRegistration(
                token: token,
                deviceID: deviceID,
                appVersion: metadata.appVersion,
                osVersion: metadata.osVersion
            ))
            registeredDeviceID = deviceID
            message = nil
        } catch {
            message = "This iPhone could not be registered for notifications."
        }
    }

    func didFailToRegisterForRemoteNotifications() {
        message = "This iPhone could not be registered for notifications."
    }

    func receiveNotification(_ userInfo: [AnyHashable: Any]) {
        guard let route = notificationRouter.route(from: userInfo) else { return }
        pendingRoute = route
        deliverPendingRouteIfPossible()
    }

    func sessionDidChange() {
        deliverPendingRouteIfPossible()
    }

    func setPreference(_ key: PushPreferenceKey, enabled: Bool) async {
        guard var preferences, updatingPreference == nil else { return }
        updatingPreference = key
        message = nil
        defer { updatingPreference = nil }
        do {
            try await client.updatePreferences([key: enabled])
            preferences[key] = enabled
            self.preferences = preferences
        } catch {
            message = "Your notification preference was not saved."
        }
    }

    func unregister() async {
        guard let deviceID = registeredDeviceID ?? nativeSessionID(),
              !deviceID.isEmpty
        else { return }
        try? await client.unregister(deviceID: deviceID)
        registeredDeviceID = nil
        preferences = nil
    }

    private func loadPreferences() async {
        guard session.state == .authenticatedUnlocked else { return }
        do {
            preferences = try await client.loadPreferences()
        } catch {
            message = "Notification preferences are unavailable."
        }
    }

    private func deliverPendingRouteIfPossible() {
        guard session.state == .authenticatedUnlocked,
              let pendingRoute,
              router.open(pendingRoute)
        else { return }
        self.pendingRoute = nil
    }
}
