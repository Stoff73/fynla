import SwiftUI

struct AccountDeletionView: View {
    let model: AccountDeletionModel
    let appleManager: any AppleSubscriptionManaging

    @State private var showsStartConfirmation = false
    @State private var managementError: String?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                header
                if let message = model.message {
                    messageBanner(message)
                }
                if let error = model.errorMessage ?? managementError {
                    errorBanner(error)
                }
                stateContent
            }
            .padding(FynlaSpacing.standard)
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Delete account")
        .navigationBarTitleDisplayMode(.inline)
        .task {
            if model.state == .idle { await model.prepare() }
        }
        .alert("Start account deletion?", isPresented: $showsStartConfirmation) {
            Button("Cancel", role: .cancel) {}
            Button("Send verification code", role: .destructive) {
                Task { await model.initiate() }
            }
        } message: {
            Text("You will verify your identity and confirm the deletion before anything is removed.")
        }
        .accessibilityIdentifier("account-deletion.screen")
    }

    private var header: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
            Text("Delete your Fynla account")
                .font(FynlaTypography.pageTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Complete every step here. Fynla will show the server's deletion or retention outcome before finishing.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
        }
    }

    @ViewBuilder
    private var stateContent: some View {
        switch model.state {
        case .idle, .loadingEntitlement:
            LoadingView(message: "Checking your current plan…")
        case .appleBillingWarning:
            appleWarning
        case let .ready(billing):
            readyCard(billing)
        case .requestingVerification:
            LoadingView(message: "Starting account deletion…")
        case let .verificationRequired(usesAuthenticator):
            verificationCard(usesAuthenticator: usesAuthenticator)
        case .verifying:
            LoadingView(message: "Verifying your identity…")
        case .verified:
            executionCard
        case .executing:
            LoadingView(message: "Confirming the server outcome…")
        case let .scheduled(message, deletionAt):
            scheduledCard(message: message, deletionAt: deletionAt)
        case let .deleted(message):
            resultCard(title: "Account deleted", message: message)
        case let .cancelled(message):
            resultCard(title: "Deletion cancelled", message: message)
        case let .failed(message):
            failureCard(message)
        }
    }

    private var appleWarning: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("App Store subscription")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Deleting your Fynla account does not cancel an App Store subscription. Manage it with Apple before continuing if you do not want it to renew.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            FynlaButton("Manage App Store subscription", variant: .secondary) {
                Task {
                    do {
                        try await appleManager.showManageSubscriptions()
                    } catch {
                        managementError = "App Store subscription management is unavailable right now."
                    }
                }
            }
            FynlaButton("Continue account deletion", variant: .destructive) {
                model.continueAfterAppleWarning()
            }
        }
        .deletionCard()
        .accessibilityIdentifier("account-deletion.apple-warning")
    }

    private func readyCard(_ billing: AccountDeletionBillingState) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Verify before deletion")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text(readyMessage(for: billing))
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            FynlaButton("Start account deletion", variant: .destructive) {
                showsStartConfirmation = true
            }
        }
        .deletionCard()
        .accessibilityIdentifier("account-deletion.ready")
    }

    private func verificationCard(usesAuthenticator: Bool) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Enter verification code")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            TextField(
                "Six-digit code",
                text: Binding(
                    get: { model.verificationCode },
                    set: { model.setVerificationCode($0) }
                )
            )
            .keyboardType(.numberPad)
            .textContentType(.oneTimeCode)
            .textFieldStyle(.roundedBorder)
            .accessibilityIdentifier("account-deletion.code")
            FynlaButton(
                "Verify identity",
                isDisabled: !model.canVerify
            ) {
                Task { await model.verify() }
            }
            if !usesAuthenticator {
                Button("Resend code") {
                    Task { await model.resend() }
                }
                .font(FynlaTypography.button)
                .foregroundStyle(FynlaColor.primaryAction)
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("account-deletion.resend")
            }
        }
        .deletionCard()
        .accessibilityIdentifier("account-deletion.verification")
    }

    private var executionCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Final confirmation")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Type exactly: \"\(AccountDeletionModel.confirmationPhrase)\"")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            TextField(
                AccountDeletionModel.confirmationPhrase,
                text: Binding(
                    get: { model.confirmation },
                    set: { model.setConfirmation($0) }
                )
            )
            .textInputAutocapitalization(.never)
            .autocorrectionDisabled()
            .textFieldStyle(.roundedBorder)
            .accessibilityIdentifier("account-deletion.confirmation")
            FynlaButton(
                "Delete my account",
                variant: .destructive,
                isDisabled: !model.canExecute
            ) {
                Task { await model.execute() }
            }
            .accessibilityIdentifier("account-deletion.execute")
        }
        .deletionCard()
    }

    private func scheduledCard(message: String, deletionAt: String?) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Deletion scheduled")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text(message)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.primaryText)
            if let deletionAt {
                Text("Server retention date: \(deletionAt)")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            FynlaButton("Cancel scheduled deletion", variant: .secondary) {
                Task { await model.cancelScheduled() }
            }
        }
        .deletionCard()
        .accessibilityIdentifier("account-deletion.scheduled")
    }

    private func resultCard(title: String, message: String) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text(message)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .deletionCard()
    }

    private func failureCard(_ message: String) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text(message)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.primaryText)
            FynlaButton("Try again", variant: .secondary) {
                Task { await model.prepare() }
            }
        }
        .deletionCard()
    }

    private func readyMessage(for billing: AccountDeletionBillingState) -> String {
        switch billing {
        case .webPremium:
            "Your Premium billing is managed on the Fynla website. The server will show whether deletion is immediate or scheduled for the end of the paid period."
        case .applePremium:
            "You have acknowledged that App Store billing is managed separately by Apple."
        case .free:
            "Fynla will send a six-digit code before accepting the final deletion confirmation."
        case .unavailable:
            "Subscription details are unavailable."
        }
    }

    private func messageBanner(_ message: String) -> some View {
        Text(message)
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func errorBanner(_ message: String) -> some View {
        Text(message)
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryAction)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.surface)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }
}

private extension View {
    func deletionCard() -> some View {
        padding(FynlaSpacing.standard)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.surface)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }
}
