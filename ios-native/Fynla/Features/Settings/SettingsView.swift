import SafariServices
import SwiftUI

struct SettingsView: View {
    let model: SettingsModel
    let subscriptionModel: SubscriptionModel
    let appleManager: any AppleSubscriptionManaging
    let privacySettingsModel: PrivacySettingsModel
    let dataExportModel: DataExportModel
    let accountDeletionModel: AccountDeletionModel
    let pushCoordinator: PushRegistrationCoordinator
    @State private var browserItem: SettingsBrowserItem?

    var body: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Settings")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your account, security and data controls")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                AccountSummaryView(account: model.account)
                planCard
                SecuritySettingsView(model: model)
                preferencesCard
                helpAndLegalCard

                Button("Sign out", role: .destructive) {
                    Task { await model.signOut() }
                }
                .font(FynlaTypography.button)
                .frame(
                    maxWidth: .infinity,
                    minHeight: FynlaSpacing.minimumInteractiveTarget
                )
                .accessibilityIdentifier("app.unlocked.sign-out")
            }
            .padding(FynlaSpacing.standard)
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Settings")
        .navigationBarTitleDisplayMode(.inline)
        .task { model.refresh(subscription: subscriptionModel.state) }
        .onChange(of: subscriptionModel.state) { _, state in
            model.refresh(subscription: state)
        }
        .sheet(item: $browserItem) { item in
            SettingsBrowserView(url: item.url)
                .ignoresSafeArea()
        }
        .accessibilityIdentifier("settings.screen")
    }

    private var planCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Plan and billing")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            NavigationLink {
                SubscriptionView(
                    model: subscriptionModel,
                    appleManager: appleManager
                )
            } label: {
                HStack(alignment: .firstTextBaseline) {
                    VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                        Text(model.plan.title)
                            .font(FynlaTypography.heading)
                            .foregroundStyle(FynlaColor.primaryText)
                            .accessibilityIdentifier("settings.plan.title")
                        Text(model.plan.detail)
                            .font(FynlaTypography.bodySmall)
                            .foregroundStyle(FynlaColor.secondaryText)
                            .accessibilityIdentifier("settings.plan.detail")
                    }
                    Spacer()
                    Text("Manage")
                        .font(FynlaTypography.button)
                        .foregroundStyle(FynlaColor.primaryAction)
                }
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("settings.premium")
        }
        .settingsCard()
    }

    private var preferencesCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Preferences and data")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            NavigationLink {
                NotificationSettingsView(coordinator: pushCoordinator)
            } label: {
                settingsRow(
                    title: "Notifications",
                    detail: notificationDetail
                )
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("settings.notifications")
            NavigationLink {
                PrivacySettingsView(
                    model: privacySettingsModel,
                    dataExportModel: dataExportModel,
                    accountDeletionModel: accountDeletionModel,
                    appleManager: appleManager
                )
            } label: {
                settingsRow(
                    title: "Privacy and data",
                    detail: "Consent, export and account deletion"
                )
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("settings.privacy")
            NavigationLink {
                AccountDeletionView(
                    model: accountDeletionModel,
                    appleManager: appleManager
                )
            } label: {
                settingsRow(
                    title: "Delete account",
                    detail: "Verify and complete deletion in Fynla"
                )
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("settings.account-deletion")
        }
        .settingsCard()
    }

    private var notificationDetail: String {
        switch pushCoordinator.state {
        case .notDetermined: "Not enabled"
        case .denied: "Disabled in iOS Settings"
        case .provisional: "Quiet notifications"
        case .authorized: "Enabled"
        }
    }

    private var helpAndLegalCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Help and legal")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            browserButton("Help and support", url: model.supportURL)
            browserButton("Privacy policy", url: model.privacyURL)
            browserButton("Terms of service", url: model.termsURL)
        }
        .settingsCard()
    }

    private func settingsRow(title: String, detail: String) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
            Text(title)
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
            Text(detail)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .frame(
            maxWidth: .infinity,
            minHeight: FynlaSpacing.minimumInteractiveTarget,
            alignment: .leading
        )
    }

    private func browserButton(_ title: String, url: URL) -> some View {
        Button(title) {
            browserItem = SettingsBrowserItem(url: url)
        }
        .font(FynlaTypography.body)
        .foregroundStyle(FynlaColor.primaryAction)
        .frame(
            maxWidth: .infinity,
            minHeight: FynlaSpacing.minimumInteractiveTarget,
            alignment: .leading
        )
        .accessibilityIdentifier(
            "settings.link.\(title.lowercased().replacingOccurrences(of: " ", with: "-"))"
        )
    }
}

private struct SettingsBrowserItem: Identifiable {
    let url: URL
    var id: String { url.absoluteString }
}

private struct SettingsBrowserView: UIViewControllerRepresentable {
    let url: URL

    func makeUIViewController(context: Context) -> SFSafariViewController {
        SFSafariViewController(url: url)
    }

    func updateUIViewController(
        _ uiViewController: SFSafariViewController,
        context: Context
    ) {}
}

private extension View {
    func settingsCard() -> some View {
        padding(FynlaSpacing.standard)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.surface)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }
}
