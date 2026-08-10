import SwiftUI

struct PropertyDetailView: View {
    let propertyID: Int
    let model: NetWorthModel
    let onRoute: (AppRoute) -> Void
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        detailState
            .background(FynlaColor.pageBackground)
            .task(id: propertyID) { await model.loadProperty(id: propertyID) }
            .accessibilityIdentifier("property-detail.screen")
    }

    @ViewBuilder private var detailState: some View {
        switch model.detailState {
        case .idle, .loading:
            framed { DashboardLoadingView(message: "Loading this property…") }
        case let .loaded(.property(property)):
            content(property)
        case let .offline(.property(property)):
            content(property, offline: true)
        case .offline:
            stateView(.offline)
        case .unauthenticated:
            stateView(.unauthenticated)
        case let .upgradeRequired(message):
            stateView(.upgradeRequired(message: message))
        case let .failed(requestID):
            stateView(.failed(requestID: requestID))
        case .loaded:
            framed { DashboardLoadingView(message: "Loading this property…") }
        }
    }

    private func content(_ property: PropertyDetail, offline: Bool = false) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: property.displayName, subtitle: "Property details", accessibilityID: "property-detail.heading")
                MobilePageActions(
                    onBack: { dismiss() },
                    editDetails: property.isPrimaryOwner == false ? nil : {
                        onOpenContextualFyn(FynContextualActions.property(id: property.id))
                    }
                )
                Group {
                    if offline { offlineNotice }
                    MobileHeroCard(
                        label: "Current value",
                        metric: CanonicalNetWorthDetailFormatting.money(property.currentValue),
                        sub: property.outstandingMortgage.map { "Mortgage \(CanonicalNetWorthDetailFormatting.money($0))" }
                    )
                    CanonicalNetWorthDetailCard(title: "Property") {
                        CanonicalNetWorthDetailRow(key: "Type", value: CanonicalNetWorthDetailFormatting.label(property.propertyType))
                        CanonicalNetWorthDetailRow(key: "Ownership", value: CanonicalNetWorthDetailFormatting.label(property.ownershipType))
                        CanonicalNetWorthDetailRow(key: "Purchase price", value: CanonicalNetWorthDetailFormatting.money(property.purchasePrice))
                        CanonicalNetWorthDetailRow(key: "Purchase date", value: CanonicalNetWorthDetailFormatting.date(property.purchaseDate))
                        CanonicalNetWorthDetailRow(key: "Valuation date", value: CanonicalNetWorthDetailFormatting.date(property.valuationDate))
                        CanonicalNetWorthDetailRow(key: "Equity", value: CanonicalNetWorthDetailFormatting.money(property.equity))
                    }
                    if let mortgages = property.mortgages, !mortgages.isEmpty {
                        CanonicalNetWorthDetailCard(title: "Mortgages") {
                            ForEach(mortgages) { mortgage in
                                Button { onRoute(mortgage.detailRoute) } label: {
                                    CanonicalNetWorthDetailRow(
                                        key: mortgage.lenderName ?? "Mortgage",
                                        value: CanonicalNetWorthDetailFormatting.money(mortgage.outstandingBalance),
                                        debt: true
                                    )
                                }
                                .buttonStyle(.plain)
                            }
                        }
                    }
                }
                .padding(.horizontal, 16)
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private var offlineNotice: some View { Text("You're offline. Showing the last loaded property.").font(.system(size: 13)).foregroundStyle(FynlaColor.Token.horizon500.color) }
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View { ScrollView { VStack(alignment: .leading, spacing: 12) { MobilePageHero(title: "Property details", subtitle: "Your property", accessibilityID: "property-detail.heading"); content() } } }
    private func stateView(_ state: ScreenStatePresentation) -> some View { framed { ScreenStateView(state: state, retry: state.canRetry ? { Task { await model.loadProperty(id: propertyID) } } : nil, openSubscription: state.canUpgrade ? onOpenSubscription : nil) } }
}
