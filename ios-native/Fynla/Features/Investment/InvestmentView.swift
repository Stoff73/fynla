import SwiftUI

// Transcribes /m's Investments page (resources/mobile/views/modules/
// Investment.vue): gradient page hero, Edit details pill (rich /m edit
// prompt), portfolio-value hero card, risk-profile row, account rows with
// the violet ISA tag, type and holdings meta, account-cap head with Upgrade.
// Whole-pound amounts as /m's formatCurrency.
struct InvestmentView: View {
    let model: InvestmentModel
    let onRoute: (AppRoute) -> Void
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your investments…") }
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
        .task { await model.load() }
        .accessibilityIdentifier("investment.screen")
    }

    private func content(
        _ snapshot: InvestmentSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Investments",
                    subtitle: "Your investment accounts, holdings and allowances"
                )

                MobilePageActions(editDetails: {
                    onOpenContextualFyn(
                        FynContextualActions.investmentOverview(
                            hasAccounts: !snapshot.accounts.isEmpty
                        )
                    )
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    heroCard(snapshot)

                    if let risk = snapshot.riskLabel {
                        riskCard(risk)
                    }

                    accountsCard(snapshot)
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero: dark card with the big metric + account-count sub-line.
    private func heroCard(_ snapshot: InvestmentSnapshot) -> some View {
        MobileHeroCard(
            label: "Total portfolio value",
            metric: MoneyFormatter.gbpWhole(snapshot.totalValue),
            sub: accountCountLabel(snapshot.accounts.count)
        )
        .accessibilityIdentifier("investment.total-value")
    }

    // mi-row: attitude-to-risk key/value.
    private func riskCard(_ risk: String) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("Risk profile".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 4)
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                Text("Attitude to risk")
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                Spacer()
                Text(risk)
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
            }
            .padding(.vertical, 4)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func accountsCard(_ snapshot: InvestmentSnapshot) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text("Investment accounts".uppercased())
                    .font(.system(size: 12, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                Spacer()
                if let limit = snapshot.accountLimit {
                    HStack(spacing: 8) {
                        Text("\(snapshot.accountCount) of \(limit) accounts used")
                            .font(.system(size: 12, weight: .bold))
                            .foregroundStyle(
                                snapshot.isAtAccountLimit
                                    ? FynlaColor.Token.raspberry500.color
                                    : FynlaColor.Token.neutral500.color
                            )
                        Button {
                            onOpenSubscription()
                        } label: {
                            Text("Upgrade".uppercased())
                                .font(.system(size: 12, weight: .bold))
                                .kerning(0.5)
                                .foregroundStyle(FynlaColor.Token.raspberry500.color)
                        }
                        .accessibilityIdentifier("investment.upgrade")
                    }
                }
            }
            .padding(.bottom, 6)

            if snapshot.accounts.isEmpty {
                Text("You haven't added any investment accounts yet.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.vertical, 8)
            } else {
                ForEach(snapshot.accounts) { account in
                    accountRow(
                        account,
                        showsDivider: account.id != snapshot.accounts.last?.id
                    )
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mi-acct: provider + tag/type/holdings meta left, value + VIEW right.
    private func accountRow(
        _ account: InvestmentAccount,
        showsDivider: Bool
    ) -> some View {
        Button {
            onRoute(.investment(accountID: account.id))
        } label: {
            HStack(alignment: .center, spacing: 12) {
                VStack(alignment: .leading, spacing: 4) {
                    Text(account.displayName)
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    HStack(spacing: 6) {
                        if account.isISA,
                           !account.accountTypeLabel.lowercased().contains("isa")
                        {
                            Text("ISA")
                                .font(.system(size: 11, weight: .bold))
                                .foregroundStyle(FynlaColor.Token.violet500.color)
                                .padding(.horizontal, 7)
                                .padding(.vertical, 1)
                                .background(FynlaColor.Token.violet500.color.opacity(0.12))
                                .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
                        }
                        Text(account.accountTypeLabel)
                            .font(.system(size: 12))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                        if !account.holdings.isEmpty {
                            Text("\(account.holdings.count) \(account.holdings.count == 1 ? "holding" : "holdings")")
                                .font(.system(size: 12))
                                .foregroundStyle(FynlaColor.Token.neutral500.color)
                        }
                    }
                }
                Spacer(minLength: 8)
                VStack(alignment: .trailing, spacing: 2) {
                    Text(MoneyFormatter.gbpWhole(account.currentValue))
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text("View".uppercased())
                        .font(.system(size: 11, weight: .bold))
                        .kerning(0.5)
                        .foregroundStyle(FynlaColor.Token.raspberry500.color)
                }
            }
            .padding(.vertical, 14)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.lightGray.color.frame(height: 1)
            }
        }
        .accessibilityIdentifier("investment.account.\(account.id)")
    }

    private func accountCountLabel(_ count: Int) -> String {
        count == 0 ? "No accounts added yet." : "Across \(count) \(count == 1 ? "account" : "accounts")."
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded investments.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("investment.offline")
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
                retry: state.canRetry ? { Task { await model.load() } } : nil,
                openSubscription: state.canUpgrade ? onOpenSubscription : nil
            )
        }
    }
}
