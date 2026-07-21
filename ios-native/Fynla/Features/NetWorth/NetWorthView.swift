import SwiftUI

// Transcribes /m's Net Worth page (resources/mobile/views/modules/
// NetWorth.vue): gradient page hero, "Edit details" pill, net-worth hero card
// with the assets/liabilities line, Defined Benefit note, balance-history
// card, tappable asset category rows and the liabilities row. Whole-pound
// amounts as /m's formatCurrency.
struct NetWorthView: View {
    let model: NetWorthModel
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                DashboardLoadingView(message: "Loading your net worth…")
            case let .loaded(snapshot):
                content(snapshot)
            case let .offline(previous):
                if let previous {
                    content(previous, offline: true)
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
        .task { await model.load() }
        .accessibilityIdentifier("net-worth.screen")
    }

    private func content(
        _ snapshot: NetWorthSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Net Worth",
                    subtitle: "Everything you own, less what you owe"
                )

                MobilePageActions(editDetails: {
                    onOpenFyn("What would you like to update?")
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    heroCard(snapshot.overview)

                    if snapshot.overview.hasDBPensions {
                        Text("Defined Benefit pensions are excluded from net worth — they provide a guaranteed income rather than accessible capital.")
                            .font(.system(size: 13))
                            .foregroundStyle(FynlaColor.Token.neutral600.color)
                            .padding(.horizontal, 4)
                            .accessibilityIdentifier("net-worth.db-note")
                    }

                    historyCard
                    assetCard(snapshot.assetCategories)
                    liabilityCard(snapshot.overview.totalLiabilities)
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero: dark card with the big metric + assets/liabilities sub-line.
    private func heroCard(_ overview: NetWorthOverview) -> some View {
        MobileHeroCard(
            label: "Total net worth",
            metric: MoneyFormatter.gbpWhole(overview.netWorth),
            sub: "\(MoneyFormatter.gbpWhole(overview.totalAssets)) in assets, less \(MoneyFormatter.gbpWhole(overview.totalLiabilities)) of liabilities."
        )
        .accessibilityIdentifier("net-worth.total")
    }

    // mnw-history: title + meta left, uppercase raspberry action right.
    private var historyCard: some View {
        HStack(alignment: .center, spacing: 12) {
            VStack(alignment: .leading, spacing: 2) {
                Text("Balance history")
                    .font(.system(size: 15, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Text("Track how your recorded balances change over time.")
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }
            Spacer(minLength: 8)
            Button {
                onRoute(.balanceHistory)
            } label: {
                Text("View balance history".uppercased())
                    .font(.system(size: 12, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
            }
            .frame(minHeight: 44)
            .accessibilityIdentifier("net-worth.balance-history")
        }
        .padding(16)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func assetCard(
        _ categories: [NetWorthAssetCategorySummary]
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Assets".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)

            if categories.isEmpty {
                Text("No assets recorded yet.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.vertical, 8)
            } else {
                ForEach(categories) { category in
                    categoryRow(
                        title: category.title,
                        meta: "\(category.count) \(category.count == 1 ? "item" : "items"), \(category.percentageOfAssets)% of assets",
                        value: MoneyFormatter.gbpWhole(category.totalValue),
                        valueIsDebt: false,
                        showsDivider: category.id != categories.last?.id
                    ) {
                        onRoute(.netWorth(category: category.id.rawValue))
                    }
                    .accessibilityIdentifier("net-worth.category.\(category.id.rawValue)")
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func liabilityCard(_ total: Decimal) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Liabilities".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)

            categoryRow(
                title: "Liabilities",
                meta: "Mortgages, loans and other debts",
                value: MoneyFormatter.gbpWhole(total),
                valueIsDebt: true,
                showsDivider: false
            ) {
                onRoute(.netWorth(category: NetWorthCategory.liabilities.rawValue))
            }
            .accessibilityIdentifier("net-worth.category.liabilities")
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mnw-cat: name + meta left; value + uppercase VIEW right; hairline below.
    private func categoryRow(
        title: String,
        meta: String,
        value: String,
        valueIsDebt: Bool,
        showsDivider: Bool,
        action: @escaping () -> Void
    ) -> some View {
        Button(action: action) {
            HStack(alignment: .center, spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text(meta)
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
                Spacer(minLength: 8)
                VStack(alignment: .trailing, spacing: 2) {
                    Text(value)
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(
                            valueIsDebt
                                ? FynlaColor.Token.raspberry500.color
                                : FynlaColor.Token.horizon500.color
                        )
                    Text("View".uppercased())
                        .font(.system(size: 11, weight: .bold))
                        .kerning(0.5)
                        .foregroundStyle(FynlaColor.Token.raspberry500.color)
                }
            }
            .padding(.vertical, 12)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.horizon200.color.frame(height: 1)
            }
        }
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded net worth.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("net-worth.offline")
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
