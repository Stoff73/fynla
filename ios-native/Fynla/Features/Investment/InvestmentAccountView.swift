import SwiftUI

// Transcribes /m's investment account sub-page (resources/mobile/views/
// modules/InvestmentAccountDetail.vue): gradient page hero, Back + Edit
// details pills, provider identity card, dark current-value hero with the
// contributions sub-line, Account information rows and the holdings list
// with one-decimal allocation/gain percentages. Whole-pound amounts.
struct InvestmentAccountView: View {
    let accountID: Int
    let model: InvestmentModel
    let onOpenContextualFyn: (FynContextualAction) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading this account…") }
            case .loaded:
                if let account = model.account(id: accountID) {
                    content(account)
                } else {
                    framed { ScreenStateView(state: .empty(message: "We could not find that account.")) }
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
        .task(id: accountID) { await model.load() }
        .accessibilityIdentifier("investment.account.screen")
    }

    private func content(
        _ account: InvestmentAccount,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Investments",
                    subtitle: "Your investment accounts, holdings and allowances"
                )

                MobilePageActions(
                    onBack: { dismiss() },
                    editDetails: account.isPrimaryOwner == false
                        ? nil
                        : {
                            onOpenContextualFyn(FynContextualActions.investmentAccount(id: accountID))
                        }
                )

                Group {
                    MobileDetailHeader(
                        title: account.provider ?? account.platform ?? "Investment account",
                        subtitle: account.accountTypeLabel
                    )

                    if offline {
                        offlineNotice
                    }

                    hero(account)

                    MobileDetailCard(
                        title: "Account information",
                        rows: infoRows(account),
                        keyFontSize: 13
                    )

                    holdingsCard(account.holdings)
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    // m-hero — dark card: current value + contributions-this-year sub.
    private func hero(_ account: InvestmentAccount) -> some View {
        MobileHeroCard(
            label: "Current value",
            metric: MoneyFormatter.gbpWhole(account.currentValue),
            sub: account.contributionsYTD.flatMap { contributions in
                contributions > 0
                    ? "\(MoneyFormatter.gbpWhole(contributions)) contributed this tax year"
                    : nil
            }
        )
        .accessibilityIdentifier("investment.account.value")
    }

    private func infoRows(_ account: InvestmentAccount) -> [(key: String, value: String)] {
        var rows: [(key: String, value: String)] = [
            ("Provider", account.provider ?? "—"),
            ("Platform", account.platform ?? "—"),
            ("Account type", account.accountTypeLabel),
            ("Ownership", titleCase(account.ownershipType) ?? "Individual"),
            ("Country", account.country == "UK" ? "United Kingdom" : (account.country ?? "United Kingdom")),
        ]
        if let contribution = account.monthlyContributionAmount, contribution > 0 {
            rows.append(("Monthly contribution", MoneyFormatter.gbpWhole(contribution)))
        }
        if account.isISA, let subscribed = account.isaSubscriptionCurrentYear {
            rows.append(("ISA subscribed this year", MoneyFormatter.gbpWhole(subscribed)))
        }
        return rows
    }

    // mid-holding — name/value head, meta + gain/loss foot, hairlines.
    private func holdingsCard(_ holdings: [InvestmentHolding]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Holdings".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 8)
            if holdings.isEmpty {
                Text("No individual holdings recorded for this account.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            } else {
                ForEach(Array(holdings.enumerated()), id: \.offset) { index, holding in
                    holdingRow(holding, isLast: index == holdings.count - 1)
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func holdingRow(_ holding: InvestmentHolding, isLast: Bool) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                Text(holding.displayName)
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer(minLength: 8)
                Text(holding.currentValue.map(MoneyFormatter.gbpWhole) ?? "—")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .lineLimit(1)
            }
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                HStack(spacing: 8) {
                    if let allocation = holding.allocationPercent {
                        Text("\(oneDecimal(allocation)) of account")
                    }
                    if let assetType = titleCase(holding.assetType) {
                        Text(assetType)
                    }
                }
                .font(.system(size: 12))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                Spacer(minLength: 8)
                if let gain = holding.gainLoss {
                    Text(gainLabel(holding))
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(
                            gain >= 0
                                ? FynlaColor.Token.spring600.color
                                : FynlaColor.Token.raspberry500.color
                        )
                        .lineLimit(1)
                }
            }
        }
        .padding(.vertical, 12)
        .overlay(alignment: .bottom) {
            if !isLast {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
    }

    private func gainLabel(_ holding: InvestmentHolding) -> String {
        guard let gain = holding.gainLoss else { return "" }
        let prefix = gain >= 0 ? "+" : ""
        let percentage = holding.gainLossPercent.map { " (\(oneDecimal($0)))" } ?? ""
        return "\(prefix)\(MoneyFormatter.gbpWhole(gain))\(percentage)"
    }

    // /m pct(): always one decimal place.
    private func oneDecimal(_ value: Decimal) -> String {
        String(format: "%.1f%%", NSDecimalNumber(decimal: value).doubleValue)
    }

    private func titleCase(_ value: String?) -> String? {
        guard let value, !value.isEmpty else { return nil }
        return value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing the last loaded account values.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Investments",
                    subtitle: "Your investment accounts, holdings and allowances"
                )
                content()
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        framed {
            ScreenStateView(
                state: state,
                retry: state.canRetry ? { Task { await model.load() } } : nil
            )
        }
    }
}
