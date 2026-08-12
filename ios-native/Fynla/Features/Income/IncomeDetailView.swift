import SwiftUI

struct IncomeDetailView: View {
    let owner: String
    let sourceKey: String
    let model: IncomeModel
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading this income source…") }
            case let .loaded(summary):
                content(summary)
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
        .accessibilityIdentifier("income.detail.\(owner).\(sourceKey)")
    }

    private func content(_ summary: IncomeSummary, offline: Bool = false) -> some View {
        let ownerSummary = owner == "spouse" ? summary.spouse : summary.user
        let source = ownerSummary?.nonZeroSources.first { $0.key == sourceKey }

        return ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: source?.label ?? "Income details", subtitle: "Canonical income source", accessibilityID: "income-detail.heading")

                if let source, let ownerSummary {
                    MobilePageActions(editDetails: {
                        onOpenContextualFyn(FynContextualActions.income(owner: owner, source: sourceKey))
                    })

                    Group {
                        if offline { offlineNotice }
                        MobileHeroCard(label: "Annual amount", metric: MoneyFormatter.gbpWhole(source.amount))
                        detailCard(title: "Income source") {
                            detailRow("Source", source.label)
                            detailRow("Frequency", label(source.frequency))
                            detailRow("Ownership", source.ownershipLabel)
                            if let detail = source.detail { detailRow("Details", detail) }
                            detailRow("Tax position", source.taxPosition)
                        }
                        taxCard(ownerSummary.taxPosition)
                    }
                    .padding(.horizontal, 16)
                } else {
                    ScreenStateView(state: .failed(requestID: nil), retry: { Task { await model.load() } })
                }

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    private func taxCard(_ tax: IncomeTaxPosition) -> some View {
        detailCard(title: "Tax position") {
            detailRow("Adjusted net income", money(tax.adjustedNetIncome))
            detailRow(tax.personalAllowanceLabel, money(tax.personalAllowance))
            detailRow(tax.pensionAnnualAllowanceLabel, money(tax.pensionAnnualAllowance))
        }
    }

    private func detailCard<Content: View>(title: String, @ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(title.uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)
            content()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func detailRow(_ key: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key).font(.system(size: 13)).foregroundStyle(FynlaColor.secondaryText)
            Spacer()
            Text(value).font(.system(size: 13, weight: .semibold)).foregroundStyle(FynlaColor.primaryText).multilineTextAlignment(.trailing)
        }
        .padding(.vertical, 9)
        .overlay(alignment: .bottom) { FynlaColor.Token.horizon100.color.frame(height: 1) }
    }

    private func money(_ value: Decimal?) -> String { value.map(MoneyFormatter.gbpWhole) ?? "—" }
    private func label(_ value: String) -> String { value.replacingOccurrences(of: "_", with: " ").capitalized }

    private var offlineNotice: some View {
        Text("You're offline. Showing the last loaded income details.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: "Income details", subtitle: "Canonical income source", accessibilityID: "income-detail.heading")
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
