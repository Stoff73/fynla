import SwiftUI

// Transcribes /m's Expenditure page (resources/mobile/views/Expenditure.vue):
// gradient page hero, "Edit details" pill, monthly hero card with the annual
// line, per-category rows. Whole-pound amounts as /m's formatCurrency.
struct ExpenditureView: View {
    let model: ExpenditureModel
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your expenditure…") }
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
        .accessibilityIdentifier("expenditure.screen")
    }

    private func content(
        _ summary: ExpenditureSummary,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Expenditure",
                    subtitle: "What you spend each month"
                )

                MobilePageActions(editDetails: {
                    onOpenFyn("What would you like to update?")
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    heroCard(summary)

                    if let rows = summary.categories?.nonZeroRows,
                       !rows.isEmpty
                    {
                        categoryCard(rows)
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero: dark card with the big metric + annual sub-line.
    private func heroCard(_ summary: ExpenditureSummary) -> some View {
        MobileHeroCard(
            label: "Monthly expenditure",
            metric: MoneyFormatter.gbpWhole(summary.monthly ?? 0),
            sub: annualText(summary)
        )
        .accessibilityIdentifier("expenditure.monthly")
    }

    private func categoryCard(_ rows: [ExpenditureCategoryRow]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Where it goes each month".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)

            ForEach(rows) { row in
                HStack(alignment: .firstTextBaseline, spacing: 12) {
                    Text(row.title)
                        .font(.system(size: 14, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Spacer(minLength: 8)
                    Text(MoneyFormatter.gbpWhole(row.amount))
                        .font(.system(size: 14))
                        .foregroundStyle(FynlaColor.Token.neutral600.color)
                }
                .padding(.vertical, 10)
                .overlay(alignment: .bottom) {
                    if row.id != rows.last?.id {
                        FynlaColor.Token.horizon100.color.frame(height: 1)
                    }
                }
                .accessibilityElement(children: .combine)
                .accessibilityIdentifier("expenditure.category.\(row.id.rawValue)")
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // /m: "{annual} a year", falling back to monthly × 12 when the server
    // omits the annual figure (Expenditure.vue's annual computed).
    private func annualText(_ summary: ExpenditureSummary) -> String {
        let value = summary.annual ?? (summary.monthly ?? 0) * 12
        return "\(MoneyFormatter.gbpWhole(value)) a year"
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded expenditure.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("expenditure.offline")
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Expenditure",
                    subtitle: "What you spend each month"
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
