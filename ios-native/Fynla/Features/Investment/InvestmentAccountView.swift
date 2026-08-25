import SwiftUI

// Renders the canonical investment account and shared portfolio contracts.
struct InvestmentAccountView: View {
    let accountID: Int
    let model: InvestmentModel
    let savingsModel: SavingsModel
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
        .task(id: accountID) {
            await model.load()
            if model.account(id: accountID)?.isISA == true {
                await savingsModel.load()
            }
        }
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

                    if account.isISA, let allowance = savingsModel.isaAllowance {
                        contributionHistoryCard(allowance)
                    }

                    if let portfolio = account.portfolio {
                        CanonicalPortfolioView(portfolio: portfolio)
                    } else {
                        portfolioUnavailableCard
                    }
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
            ("Country", account.country == "UK" ? "United Kingdom" : (account.country ?? "United Kingdom")),
        ]
        if account.isISA {
            rows.append(("Owner", account.ownerName ?? "Owner unavailable"))
        } else {
            rows.append(("Ownership", titleCase(account.ownershipType) ?? "Individual"))
        }
        if let contribution = account.monthlyContributionAmount, contribution > 0 {
            rows.append(("Monthly contribution", MoneyFormatter.gbpWhole(contribution)))
        }
        return rows
    }

    private func contributionHistoryCard(_ allowance: SavingsISAAllowance) -> some View {
        ISAContributionHistoryView(
            allowance: allowance,
            accountID: accountID,
            accountKind: .investment,
            isLoading: savingsModel.isLoadingISAAllowance,
            onSelectTaxYear: { taxYear in
                Task { await savingsModel.loadISAAllowance(taxYear: taxYear) }
            }
        )
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private var portfolioUnavailableCard: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("Portfolio detail".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            Text("Canonical holding exposure, drift, fees and recorded performance are unavailable for this account.")
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .accessibilityIdentifier("canonical-portfolio.unavailable")
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
