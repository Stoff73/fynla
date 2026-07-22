import SwiftUI
import UIKit

struct NotificationSettingsView: View {
    let coordinator: PushRegistrationCoordinator

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Notifications")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Choose which generic alerts Fynla may show. Financial details load only after you unlock the app.")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if let message = coordinator.message {
                    Text(message)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryAction)
                }
                stateContent
            }
            .padding(FynlaSpacing.standard)
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Notifications")
        .navigationBarTitleDisplayMode(.inline)
        .task { await coordinator.start() }
        .accessibilityIdentifier("notifications.screen")
    }

    @ViewBuilder
    private var stateContent: some View {
        switch coordinator.state {
        case .notDetermined:
            VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
                Text("Enable notifications when you're ready. Fynla will ask iOS for permission only after you tap below.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                FynlaButton("Enable notifications") {
                    Task { await coordinator.enable() }
                }
            }
            .notificationCard()
        case .denied:
            VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
                Text("Notifications are disabled in iOS Settings.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                FynlaButton("Open iOS Settings", variant: .secondary) {
                    guard let url = URL(string: UIApplication.openSettingsURLString)
                    else { return }
                    UIApplication.shared.open(url)
                }
            }
            .notificationCard()
        case .authorized, .provisional:
            if let preferences = coordinator.preferences {
                preferencesCard(preferences)
            } else {
                LoadingView(message: "Loading notification choices…")
            }
        }
    }

    private func preferencesCard(_ preferences: PushPreferences) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text(coordinator.state == .provisional ? "Quiet notifications" : "Notification choices")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            ForEach(PushPreferenceKey.allCases, id: \.self) { key in
                Toggle(
                    key.title,
                    isOn: Binding(
                        get: { preferences[key] },
                        set: { enabled in
                            Task {
                                await coordinator.setPreference(key, enabled: enabled)
                            }
                        }
                    )
                )
                .tint(FynlaColor.focus)
                .disabled(coordinator.updatingPreference != nil)
                .accessibilityIdentifier("notifications.preference.\(key.rawValue)")
            }
        }
        .notificationCard()
    }
}

private extension View {
    func notificationCard() -> some View {
        padding(FynlaSpacing.standard)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.surface)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }
}
