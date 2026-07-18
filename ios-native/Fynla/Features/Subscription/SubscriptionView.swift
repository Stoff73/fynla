import SwiftUI

struct SubscriptionView: View {
    let model: SubscriptionModel
    let appleManager: any AppleSubscriptionManaging

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: FynlaSpacing.large) {
                VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                    Text("Premium")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Manage your plan and billing")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                SubscriptionManagementView(
                    model: model,
                    appleManager: appleManager
                )
                .padding(FynlaSpacing.standard)
                .background(FynlaColor.surface)
                .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
            }
            .padding(FynlaSpacing.standard)
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Premium")
        .navigationBarTitleDisplayMode(.inline)
        .accessibilityIdentifier("subscription.screen")
    }
}

struct SettingsView: View {
    let subscriptionModel: SubscriptionModel
    let appleManager: any AppleSubscriptionManaging
    let privacyLockController: PrivacyLockController?

    var body: some View {
        List {
            Section("Plan and billing") {
                NavigationLink {
                    SubscriptionView(
                        model: subscriptionModel,
                        appleManager: appleManager
                    )
                } label: {
                    Label("Premium", systemImage: "star.circle")
                }
                .accessibilityIdentifier("settings.premium")
            }

            if let privacyLockController {
                Section("Session") {
                    Button("Lock") {
                        privacyLockController.lock()
                    }
                    .accessibilityIdentifier("app.unlocked.lock")

                    Button("Sign out", role: .destructive) {
                        Task { @MainActor in
                            await privacyLockController.signOut()
                        }
                    }
                    .accessibilityIdentifier("app.unlocked.sign-out")
                }
            }
        }
        .navigationTitle("Settings")
        .accessibilityIdentifier("settings.screen")
    }
}
