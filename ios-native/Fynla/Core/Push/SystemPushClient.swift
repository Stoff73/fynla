import UIKit
import UserNotifications

@MainActor
protocol SystemPushClient {
    func authorizationStatus() async -> PushAuthorizationStatus
    func requestAuthorization() async throws -> PushAuthorizationStatus
    func registerForRemoteNotifications()
}

@MainActor
final class LiveSystemPushClient: SystemPushClient {
    private let center: UNUserNotificationCenter

    init(center: UNUserNotificationCenter = .current()) {
        self.center = center
    }

    func authorizationStatus() async -> PushAuthorizationStatus {
        let settings = await center.notificationSettings()
        return Self.status(settings.authorizationStatus)
    }

    func requestAuthorization() async throws -> PushAuthorizationStatus {
        _ = try await center.requestAuthorization(options: [.alert, .badge, .sound])
        return await authorizationStatus()
    }

    func registerForRemoteNotifications() {
        UIApplication.shared.registerForRemoteNotifications()
    }

    private static func status(
        _ status: UNAuthorizationStatus
    ) -> PushAuthorizationStatus {
        switch status {
        case .notDetermined: .notDetermined
        case .denied: .denied
        case .provisional, .ephemeral: .provisional
        case .authorized: .authorized
        @unknown default: .denied
        }
    }
}
