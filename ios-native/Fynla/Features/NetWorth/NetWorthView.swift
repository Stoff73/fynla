import SwiftUI

struct NetWorthView: View {
    let model: NetWorthModel
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
        .navigationTitle("Net Worth")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("net-worth.screen")
    }

    private func content(
        _ snapshot: NetWorthSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Net Worth")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Everything you own, less what you owe")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                if offline {
                    offlineNotice
                }

                hero(snapshot.overview)

                if snapshot.overview.hasDBPensions {
                    Text("Defined Benefit pensions are excluded from net worth — they provide a guaranteed income rather than accessible capital.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .accessibilityIdentifier("net-worth.db-note")
                }

                historyCard
                assetCard(snapshot.assetCategories)
                liabilityCard(snapshot.overview.totalLiabilities)

                Button("Update net worth with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "assets and liabilities",
                            addPhrase: "I'd like to add an asset or liability.",
                            names: snapshot.assetCategories.map(\.title)
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(
                    maxWidth: .infinity,
                    minHeight: FynlaSpacing.minimumInteractiveTarget
                )
                .accessibilityIdentifier("net-worth.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(_ overview: NetWorthOverview) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Total net worth")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(overview.netWorth)
                .accessibilityIdentifier("net-worth.total")
            Text("\(MoneyFormatter.gbp(overview.totalAssets)) in assets, less \(MoneyFormatter.gbp(overview.totalLiabilities)) of liabilities.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private var historyCard: some View {
        HStack(alignment: .center, spacing: FynlaSpacing.medium) {
            VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                Text("Balance history")
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                Text("Track how your recorded balances change over time.")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            Spacer(minLength: FynlaSpacing.small)
            Button("View") { onRoute(.balanceHistory) }
                .font(FynlaTypography.button)
                .foregroundStyle(FynlaColor.primaryAction)
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("net-worth.balance-history")
        }
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func assetCard(
        _ categories: [NetWorthAssetCategorySummary]
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Assets")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)

            if categories.isEmpty {
                Text("No assets recorded yet.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(categories) { category in
                    Button {
                        onRoute(.netWorth(category: category.id.rawValue))
                    } label: {
                        HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.medium) {
                            VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                                Text(category.title)
                                    .font(FynlaTypography.heading)
                                    .foregroundStyle(FynlaColor.primaryText)
                                Text("\(category.count) \(category.count == 1 ? "item" : "items"), \(category.percentageOfAssets)% of assets")
                                    .font(FynlaTypography.caption)
                                    .foregroundStyle(FynlaColor.secondaryText)
                            }
                            Spacer(minLength: FynlaSpacing.small)
                            VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                                Text(MoneyFormatter.gbp(category.totalValue))
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
                    .accessibilityIdentifier("net-worth.category.\(category.id.rawValue)")
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func liabilityCard(_ total: Decimal) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Liabilities")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Button {
                onRoute(.netWorth(category: NetWorthCategory.liabilities.rawValue))
            } label: {
                HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.medium) {
                    VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                        Text("Liabilities")
                            .font(FynlaTypography.heading)
                            .foregroundStyle(FynlaColor.primaryText)
                        Text("Mortgages, loans and other debts")
                            .font(FynlaTypography.caption)
                            .foregroundStyle(FynlaColor.secondaryText)
                    }
                    Spacer(minLength: FynlaSpacing.small)
                    Text(MoneyFormatter.gbp(total))
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryAction)
                }
                .padding(.vertical, FynlaSpacing.small)
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("net-worth.category.liabilities")
        }
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded net worth.")
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
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
