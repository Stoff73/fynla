import SwiftUI

struct MortgageDetailView: View {
    let mortgageID: Int
    let model: NetWorthModel
    let onRoute: (AppRoute) -> Void
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            switch model.detailState {
            case .idle, .loading: framed { DashboardLoadingView(message: "Loading this mortgage…") }
            case let .loaded(.mortgage(mortgage)): content(mortgage)
            case let .offline(.mortgage(mortgage)): content(mortgage, offline: true)
            case .offline: stateView(.offline)
            case .unauthenticated: stateView(.unauthenticated)
            case let .upgradeRequired(message): stateView(.upgradeRequired(message: message))
            case let .failed(requestID): stateView(.failed(requestID: requestID))
            case .loaded: framed { DashboardLoadingView(message: "Loading this mortgage…") }
            }
        }
        .background(FynlaColor.pageBackground)
        .task(id: mortgageID) { await model.loadMortgage(id: mortgageID) }
        .accessibilityIdentifier("mortgage-detail.screen")
    }

    private func content(_ mortgage: MortgageDetail, offline: Bool = false) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: mortgage.displayName, subtitle: "Mortgage details")
                MobilePageActions(onBack: { dismiss() }, editDetails: mortgage.isPrimaryOwner == false ? nil : { onOpenContextualFyn(FynContextualActions.mortgage(id: mortgage.id)) })
                Group {
                    if offline { Text("You're offline. Showing the last loaded mortgage.").font(.system(size: 13)) }
                    MobileHeroCard(label: "Outstanding balance", metric: CanonicalNetWorthDetailFormatting.money(mortgage.outstandingBalance), sub: CanonicalNetWorthDetailFormatting.percentage(mortgage.interestRate))
                    CanonicalNetWorthDetailCard(title: "Mortgage") {
                        CanonicalNetWorthDetailRow(key: "Type", value: CanonicalNetWorthDetailFormatting.label(mortgage.mortgageType))
                        CanonicalNetWorthDetailRow(key: "Ownership", value: CanonicalNetWorthDetailFormatting.label(mortgage.ownershipType))
                        CanonicalNetWorthDetailRow(key: "Monthly payment", value: CanonicalNetWorthDetailFormatting.money(mortgage.monthlyPayment))
                        CanonicalNetWorthDetailRow(key: "Interest rate", value: CanonicalNetWorthDetailFormatting.percentage(mortgage.interestRate))
                        CanonicalNetWorthDetailRow(key: "Rate type", value: CanonicalNetWorthDetailFormatting.label(mortgage.rateType))
                        CanonicalNetWorthDetailRow(key: "Remaining term", value: mortgage.remainingTermMonths.map { "\($0) months" } ?? "—")
                        CanonicalNetWorthDetailRow(key: "Maturity date", value: CanonicalNetWorthDetailFormatting.date(mortgage.maturityDate))
                    }
                    if let property = mortgage.property {
                        Button { onRoute(property.detailRoute) } label: {
                            CanonicalNetWorthDetailCard(title: "Secured on") { CanonicalNetWorthDetailRow(key: "Property", value: property.displayName) }
                        }
                        .buttonStyle(.plain)
                    }
                }
                .padding(.horizontal, 16)
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View { ScrollView { VStack(alignment: .leading, spacing: 12) { MobilePageHero(title: "Mortgage details", subtitle: "Your mortgage"); content() } } }
    private func stateView(_ state: ScreenStatePresentation) -> some View { framed { ScreenStateView(state: state, retry: state.canRetry ? { Task { await model.loadMortgage(id: mortgageID) } } : nil, openSubscription: state.canUpgrade ? onOpenSubscription : nil) } }
}
