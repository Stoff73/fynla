import SwiftUI

// Transcribes /m's Income page (resources/mobile/views/Income.vue): gradient
// page hero, "Edit details" pill, total-income hero card, per-source rows
// (employment detail line under the label), optional spouse card. Whole-pound
// amounts as /m's formatCurrency.
struct IncomeView: View {
    let model: IncomeModel
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                DashboardLoadingView(message: "Loading your income…")
            case let .loaded(summary):
                content(summary)
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
        .accessibilityIdentifier("income.screen")
    }

    private func content(
        _ summary: IncomeSummary,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: "Income", subtitle: "Your income")

                MobilePageActions(editDetails: {
                    onOpenFyn("What would you like to update?")
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    heroCard(summary.user.total)

                    incomeCard(
                        title: "Your income",
                        emptyMessage: "No income recorded yet.",
                        sources: summary.user
                    )

                    if let spouse = summary.spouse {
                        incomeCard(
                            title: "Your spouse's income",
                            emptyMessage: "No spouse income recorded yet.",
                            sources: spouse
                        )
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero: dark card with the big metric.
    private func heroCard(_ total: Decimal) -> some View {
        MobileHeroCard(
            label: "Your total annual income",
            metric: MoneyFormatter.gbpWhole(total)
        )
        .accessibilityIdentifier("income.total")
    }

    private func incomeCard(
        title: String,
        emptyMessage: String,
        sources: IncomeSources
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(title.uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)

            if sources.nonZeroSources.isEmpty {
                Text(emptyMessage)
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.vertical, 8)
            } else {
                ForEach(sources.nonZeroSources) { row in
                    incomeRow(
                        row,
                        isLast: row.id == sources.nonZeroSources.last?.id
                    )
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // inc-row: bold label (+ small detail beneath), amount right, hairline
    // between rows.
    private func incomeRow(_ row: IncomeSourceRow, isLast: Bool) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            VStack(alignment: .leading, spacing: 2) {
                Text(row.title)
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                if let detail = row.detail {
                    Text(detail)
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
            }
            Spacer(minLength: 8)
            Text(MoneyFormatter.gbpWhole(row.amount))
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral600.color)
        }
        .padding(.vertical, 10)
        .overlay(alignment: .bottom) {
            if !isLast {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("income.source.\(row.id.rawValue)")
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded income.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("income.offline")
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
