import SwiftUI

struct PrivacySettingsView: View {
    let model: PrivacySettingsModel
    let dataExportModel: DataExportModel
    let accountDeletionModel: AccountDeletionModel
    let appleManager: any AppleSubscriptionManaging

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                ScreenStateView(state: .loading)
            case let .loaded(snapshot):
                content(snapshot)
            case let .offline(previous):
                if let previous { content(previous, offline: true) }
                else { stateView(.offline) }
            case .unauthenticated:
                stateView(.unauthenticated)
            case let .failed(requestID):
                stateView(.failed(requestID: requestID))
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Privacy and data")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("privacy.screen")
    }

    private func content(
        _ snapshot: ConsentSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Privacy and data")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Control consent and obtain a copy of your Fynla data")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline {
                    Text("You're offline. Showing your last loaded consent choices.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                        .padding(FynlaSpacing.medium)
                        .background(FynlaColor.Token.savannah100.color)
                        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                }
                consentCard(snapshot)
                if !model.history.isEmpty { historyCard }
                dataCard
                deletionCard
            }
            .padding(FynlaSpacing.standard)
        }
    }

    private var historyCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Consent history")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            ForEach(model.history.prefix(5)) { entry in
                Text("\(entry.type.replacingOccurrences(of: "_", with: " ").capitalized): \(entry.consented ? "Allowed" : "Withdrawn")")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .privacyCard()
    }

    // Consent presentation follows the settled contract: the required
    // consents (and AI features) are agreed at registration via the privacy
    // policy and have NO in-app toggle — they render as a statement of record.
    // Marketing is the one revocable preference.
    private func consentCard(_ snapshot: ConsentSnapshot) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Consent choices")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            ForEach(Self.requiredConsents, id: \.type) { definition in
                VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                    Text(definition.label)
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Agreed at registration")
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .accessibilityElement(children: .combine)
                .accessibilityIdentifier("privacy.consent.\(definition.type)")
            }

            Toggle(
                isOn: Binding(
                    get: { snapshot.consents["marketing"]?.consented == true },
                    set: { value in
                        Task {
                            await model.setConsent(
                                type: "marketing",
                                consented: value
                            )
                        }
                    }
                )
            ) {
                VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                    Text("Marketing messages")
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Optional")
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
            }
            .tint(FynlaColor.focus)
            .disabled(model.updatingType != nil)
            .accessibilityIdentifier("privacy.consent.marketing")

            if model.updateFailed {
                Text("Your consent choice was not saved. Please try again.")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryAction)
                    .accessibilityIdentifier("privacy.consent.error")
            }
        }
        .privacyCard()
    }

    private var dataCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Your data")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Request an authenticated export, then share or save it using iOS.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            NavigationLink("Request data export") {
                DataExportView(model: dataExportModel)
            }
            .font(FynlaTypography.button)
            .foregroundStyle(FynlaColor.primaryAction)
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            .accessibilityIdentifier("privacy.export.open")
        }
        .privacyCard()
    }

    private var deletionCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Account deletion")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Deletion is completed in this app with verification and clear retention information.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            NavigationLink("Delete account") {
                AccountDeletionView(
                    model: accountDeletionModel,
                    appleManager: appleManager
                )
            }
            .font(FynlaTypography.button)
            .foregroundStyle(FynlaColor.primaryAction)
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            .accessibilityIdentifier("privacy.deletion.open")
        }
        .privacyCard()
        .accessibilityIdentifier("privacy.deletion")
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil
        )
    }

    private static let requiredConsents = [
        (type: "terms", label: "Terms of service"),
        (type: "privacy", label: "Privacy policy"),
        (type: "data_processing", label: "Data processing"),
    ]
}

private extension View {
    func privacyCard() -> some View {
        padding(FynlaSpacing.standard)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.surface)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }
}
