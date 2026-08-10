import SwiftUI

struct LiabilityDetailView: View {
    let liabilityID: Int
    let model: NetWorthModel
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            switch model.detailState {
            case .idle, .loading: framed { DashboardLoadingView(message: "Loading this liability…") }
            case let .loaded(.liability(liability)): content(liability)
            case let .offline(.liability(liability)): content(liability, offline: true)
            case .offline: stateView(.offline)
            case .unauthenticated: stateView(.unauthenticated)
            case let .upgradeRequired(message): stateView(.upgradeRequired(message: message))
            case let .failed(requestID): stateView(.failed(requestID: requestID))
            case .loaded: framed { DashboardLoadingView(message: "Loading this liability…") }
            }
        }
        .background(FynlaColor.pageBackground)
        .task(id: liabilityID) { await model.loadLiability(id: liabilityID) }
        .accessibilityIdentifier("liability-detail.screen")
    }

    private func content(_ liability: LiabilityDetail, offline: Bool = false) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: liability.displayName, subtitle: "Liability details")
                MobilePageActions(onBack: { dismiss() }, editDetails: liability.isPrimaryOwner == false ? nil : { onOpenContextualFyn(FynContextualActions.liability(id: liability.id)) })
                Group {
                    if offline { Text("You're offline. Showing the last loaded liability.").font(.system(size: 13)) }
                    MobileHeroCard(label: "Current balance", metric: CanonicalNetWorthDetailFormatting.money(liability.currentBalance), sub: CanonicalNetWorthDetailFormatting.label(liability.liabilityType))
                    CanonicalNetWorthDetailCard(title: "Liability") {
                        CanonicalNetWorthDetailRow(key: "Type", value: CanonicalNetWorthDetailFormatting.label(liability.liabilityType))
                        CanonicalNetWorthDetailRow(key: "Ownership", value: CanonicalNetWorthDetailFormatting.label(liability.ownershipType))
                        CanonicalNetWorthDetailRow(key: "Monthly repayment", value: CanonicalNetWorthDetailFormatting.money(liability.monthlyPayment))
                        CanonicalNetWorthDetailRow(key: "Interest rate", value: CanonicalNetWorthDetailFormatting.percentage(liability.interestRate))
                        CanonicalNetWorthDetailRow(key: "Maturity date", value: CanonicalNetWorthDetailFormatting.date(liability.maturityDate))
                        CanonicalNetWorthDetailRow(key: "Secured against", value: liability.securedAgainst ?? "Unsecured")
                        CanonicalNetWorthDetailRow(key: "Rate fixed until", value: CanonicalNetWorthDetailFormatting.date(liability.fixedUntil))
                    }
                    if let notes = liability.notes, !notes.isEmpty {
                        CanonicalNetWorthDetailCard(title: "Notes") { Text(notes).font(FynlaTypography.body).foregroundStyle(FynlaColor.secondaryText) }
                    }
                }
                .padding(.horizontal, 16)
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View { ScrollView { VStack(alignment: .leading, spacing: 12) { MobilePageHero(title: "Liability details", subtitle: "Your liability"); content() } } }
    private func stateView(_ state: ScreenStatePresentation) -> some View { framed { ScreenStateView(state: state, retry: state.canRetry ? { Task { await model.loadLiability(id: liabilityID) } } : nil, openSubscription: state.canUpgrade ? onOpenSubscription : nil) } }
}
