import SwiftUI

struct InvestmentAccountView: View {
    let accountID: Int
    let model: InvestmentModel
    let onOpenFyn: (String) -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                ScreenStateView(state: .loading)
            case .loaded:
                if let account = model.account(id: accountID) {
                    content(account)
                } else {
                    ScreenStateView(state: .empty(message: "We could not find that account."))
                }
            case let .offline(previous):
                if let account = previous?.accounts.first(where: { $0.id == accountID }) {
                    content(account, offline: true)
                } else {
                    stateView(.offline)
                }
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
        .task(id: accountID) { await model.load() }
        .accessibilityIdentifier("investment.account.screen")
    }

    private func content(
        _ account: InvestmentAccount,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(account.displayName)
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(account.accountTypeLabel)
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline {
                    Text("You're offline. Showing the last loaded account values.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                }
                hero(account)
                informationCard(account)
                holdingsCard(account.holdings)
                Button("Update this account with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "investments",
                            addPhrase: "I'd like to add an investment account.",
                            names: [account.displayName]
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("investment.account.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
    }

    private func hero(_ account: InvestmentAccount) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Current value")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(account.currentValue)
                .accessibilityIdentifier("investment.account.value")
            if let contributions = account.contributionsYTD, contributions > 0 {
                Text("\(MoneyFormatter.gbp(contributions)) contributed this tax year")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func informationCard(_ account: InvestmentAccount) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Account information")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            detailRow("Provider", account.provider ?? "—")
            detailRow("Platform", account.platform ?? "—")
            detailRow("Account type", account.accountTypeLabel)
            detailRow("Ownership", titleCase(account.ownershipType) ?? "Individual")
            detailRow("Country", account.country == "UK" ? "United Kingdom" : (account.country ?? "United Kingdom"))
            if let contribution = account.monthlyContributionAmount, contribution > 0 {
                detailRow("Monthly contribution", MoneyFormatter.gbp(contribution))
            }
            if account.isISA, let subscribed = account.isaSubscriptionCurrentYear {
                detailRow("ISA subscribed this year", MoneyFormatter.gbp(subscribed))
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func holdingsCard(_ holdings: [InvestmentHolding]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Holdings")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)
            if holdings.isEmpty {
                Text("No individual holdings recorded for this account.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(holdings) { holding in
                    VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                        HStack(alignment: .firstTextBaseline) {
                            Text(holding.displayName)
                                .font(FynlaTypography.heading)
                                .foregroundStyle(FynlaColor.primaryText)
                            Spacer()
                            FinancialValueView.money(holding.currentValue)
                        }
                        HStack(alignment: .firstTextBaseline) {
                            Text(holdingMetadata(holding))
                                .font(FynlaTypography.caption)
                                .foregroundStyle(FynlaColor.secondaryText)
                            Spacer()
                            if let gain = holding.gainLoss {
                                Text(gainLabel(holding))
                                    .font(FynlaTypography.caption)
                                    .foregroundStyle(gain >= 0 ? FynlaColor.focus : FynlaColor.primaryAction)
                            }
                        }
                    }
                    .padding(.vertical, FynlaSpacing.medium)
                    .overlay(alignment: .bottom) { Divider() }
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func detailRow(_ key: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.small) {
            Text(key)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Spacer()
            Text(value)
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, FynlaSpacing.xSmall)
        .overlay(alignment: .bottom) { Divider() }
    }

    private func holdingMetadata(_ holding: InvestmentHolding) -> String {
        [
            holding.allocationPercent.map { "\(MoneyFormatter.percentage($0)) of account" },
            titleCase(holding.assetType),
        ].compactMap { $0 }.joined(separator: " · ")
    }

    private func gainLabel(_ holding: InvestmentHolding) -> String {
        guard let gain = holding.gainLoss else { return "" }
        let prefix = gain >= 0 ? "+" : ""
        let percentage = holding.gainLossPercent.map { " (\(MoneyFormatter.percentage($0)))" } ?? ""
        return "\(prefix)\(MoneyFormatter.gbp(gain))\(percentage)"
    }

    private func titleCase(_ value: String?) -> String? {
        guard let value, !value.isEmpty else { return nil }
        return value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil
        )
    }
}
