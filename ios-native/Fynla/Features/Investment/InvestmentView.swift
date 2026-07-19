import SwiftUI

struct InvestmentView: View {
    let model: InvestmentModel
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

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
            case let .upgradeRequired(message):
                stateView(.upgradeRequired(message: message))
            case let .failed(requestID):
                stateView(.failed(requestID: requestID))
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Investments")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("investment.screen")
    }

    private func content(
        _ snapshot: InvestmentSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Investments")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your investment accounts, holdings and allowances")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline { offlineNotice }
                hero(snapshot)
                if let risk = snapshot.riskLabel {
                    valueCard(title: "Risk profile", key: "Attitude to risk", value: risk)
                }
                accountsCard(snapshot)
                Button("Update investments with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "investments",
                            addPhrase: "I'd like to add an investment account.",
                            names: snapshot.accounts.map { Optional($0.displayName) }
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("investment.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(_ snapshot: InvestmentSnapshot) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Total portfolio value")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(snapshot.totalValue)
                .accessibilityIdentifier("investment.total-value")
            Text(accountCountLabel(snapshot.accounts.count))
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func valueCard(title: String, key: String, value: String) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            HStack(alignment: .firstTextBaseline) {
                Text(key)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
                Spacer()
                Text(value)
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func accountsCard(_ snapshot: InvestmentSnapshot) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.small) {
                Text("Investment accounts")
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                if let limit = snapshot.accountLimit {
                    VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                        Text("\(snapshot.accountCount) of \(limit) accounts used")
                            .font(FynlaTypography.caption)
                            .foregroundStyle(
                                snapshot.isAtAccountLimit
                                    ? FynlaColor.primaryAction
                                    : FynlaColor.secondaryText
                            )
                        Button("Upgrade", action: onOpenSubscription)
                            .font(FynlaTypography.caption)
                            .foregroundStyle(FynlaColor.primaryAction)
                    }
                }
            }
            .padding(.bottom, FynlaSpacing.small)

            if snapshot.accounts.isEmpty {
                Text("You haven't added any investment accounts yet.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(snapshot.accounts) { account in
                    Button {
                        onRoute(.investment(accountID: account.id))
                    } label: {
                        HStack(alignment: .center, spacing: FynlaSpacing.medium) {
                            VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                                Text(account.displayName)
                                    .font(FynlaTypography.heading)
                                    .foregroundStyle(FynlaColor.primaryText)
                                HStack(spacing: FynlaSpacing.xSmall) {
                                    if account.isISA && !account.accountTypeLabel.lowercased().contains("isa") {
                                        Text("ISA").foregroundStyle(FynlaColor.focus)
                                    }
                                    Text(account.accountTypeLabel)
                                    if !account.holdings.isEmpty {
                                        Text("\(account.holdings.count) \(account.holdings.count == 1 ? "holding" : "holdings")")
                                    }
                                }
                                .font(FynlaTypography.caption)
                                .foregroundStyle(FynlaColor.secondaryText)
                            }
                            Spacer(minLength: FynlaSpacing.small)
                            VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                                Text(MoneyFormatter.gbp(account.currentValue))
                                    .font(FynlaTypography.heading)
                                    .foregroundStyle(FynlaColor.primaryText)
                                Text("View")
                                    .font(FynlaTypography.caption)
                                    .foregroundStyle(FynlaColor.primaryAction)
                            }
                        }
                        .padding(.vertical, FynlaSpacing.medium)
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .overlay(alignment: .bottom) { Divider() }
                    .accessibilityIdentifier("investment.account.\(account.id)")
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func accountCountLabel(_ count: Int) -> String {
        count == 0 ? "No accounts added yet." : "Across \(count) \(count == 1 ? "account" : "accounts")."
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded investments.")
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
