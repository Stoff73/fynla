import UIKit
import UserNotifications

@MainActor
final class PushAppDelegateBridge {
    static let shared = PushAppDelegateBridge()

    private weak var coordinator: PushRegistrationCoordinator?
    private var pendingDeviceToken: Data?
    private var pendingNotification: [AnyHashable: Any]?

    private init() {}

    func configure(_ coordinator: PushRegistrationCoordinator) {
        self.coordinator = coordinator
        if let pendingDeviceToken {
            self.pendingDeviceToken = nil
            Task {
                await coordinator.didRegisterForRemoteNotifications(
                    deviceToken: pendingDeviceToken
                )
            }
        }
        if let pendingNotification {
            self.pendingNotification = nil
            coordinator.receiveNotification(pendingNotification)
        }
    }

    func didRegister(deviceToken: Data) {
        guard let coordinator else {
            pendingDeviceToken = deviceToken
            return
        }
        Task {
            await coordinator.didRegisterForRemoteNotifications(
                deviceToken: deviceToken
            )
        }
    }

    func didFailToRegister() {
        coordinator?.didFailToRegisterForRemoteNotifications()
    }

    func receive(_ userInfo: [AnyHashable: Any]) {
        guard let coordinator else {
            pendingNotification = userInfo
            return
        }
        coordinator.receiveNotification(userInfo)
    }

    func receive(routeKey: String) {
        receive(["route": routeKey])
    }
}

@MainActor
final class FynlaAppDelegate: NSObject,
    UIApplicationDelegate,
    UNUserNotificationCenterDelegate
{
    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil
    ) -> Bool {
        UNUserNotificationCenter.current().delegate = self
        return true
    }

    func application(
        _ application: UIApplication,
        didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
    ) {
        PushAppDelegateBridge.shared.didRegister(deviceToken: deviceToken)
    }

    func application(
        _ application: UIApplication,
        didFailToRegisterForRemoteNotificationsWithError error: any Error
    ) {
        PushAppDelegateBridge.shared.didFailToRegister()
    }

    nonisolated func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification
    ) async -> UNNotificationPresentationOptions {
        [.banner, .sound, .badge]
    }

    nonisolated func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse
    ) async {
        let userInfo = response.notification.request.content.userInfo
        let routeKey = userInfo["route"] as? String
            ?? (userInfo["data"] as? [String: Any])?["route"] as? String
        guard let routeKey else { return }
        await MainActor.run {
            PushAppDelegateBridge.shared.receive(routeKey: routeKey)
        }
    }
}
