import SwiftUI

// Transcribes /m's net-worth category sub-page (resources/mobile/views/
// modules/NetWorthCategory.vue): gradient page hero, Back navigation
// pills, category identity card, dark total hero with the item-count
// sub-line, and the Items/Breakdown list with 15/700 rows (raspberry
// liability values), horizon-200 hairlines and the 11/700 field chips.
// Whole-pound amounts.
struct NetWorthCategoryView: View {
    let categoryKey: String
    let model: NetWorthModel
    let onRoute: (AppRoute) -> Void
    let onOpenSubscription: () -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            if let category = NetWorthCategory(rawValue: categoryKey) {
                loadedState(category)
            } else {
                framed {
                    ScreenStateView(
                        state: .empty(message: "This net worth category is unavailable.")
                    )
                }
            }
        }
        .background(FynlaColor.pageBackground)
        .task { await model.load() }
        .accessibilityIdentifier("net-worth.category.screen")
    }

    @ViewBuilder
    private func loadedState(_ category: NetWorthCategory) -> some View {
        switch model.state {
        case .idle, .loading:
            framed { DashboardLoadingView(message: "Loading your accounts…") }
        case let .loaded(snapshot):
            content(category, snapshot: snapshot)
        case let .offline(previous):
            if let previous {
                content(category, snapshot: previous, offline: true)
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

    private func content(
        _ category: NetWorthCategory,
        snapshot: NetWorthSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Net Worth",
                    subtitle: "Everything you own, less what you owe"
                )

                MobilePageActions(onBack: { dismiss() })

                Group {
                    MobileDetailHeader(title: category.title, subtitle: category.subtitle)

                    if offline {
                        offlineNotice
                    }

                    hero(category, snapshot: snapshot)
                    itemCard(category, snapshot: snapshot)
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero — dark card: category total + item-count sub.
    private func hero(
        _ category: NetWorthCategory,
        snapshot: NetWorthSnapshot
    ) -> some View {
        let isLiability = category == .liabilities
        let rows = itemCount(category, snapshot: snapshot)
        return MobileHeroCard(
            label: isLiability ? "Total owed" : "Total value",
            metric: MoneyFormatter.gbpWhole(total(category, snapshot: snapshot)),
            sub: isLiability
                ? "Across \(rows) \(rows == 1 ? "type" : "types") of debt."
                : "Across \(rows) \(rows == 1 ? "item" : "items")."
        )
        .accessibilityIdentifier("net-worth.category.total")
    }

    @ViewBuilder
    private func itemCard(
        _ category: NetWorthCategory,
        snapshot: NetWorthSnapshot
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text((category == .liabilities ? "Breakdown" : "Items").uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 4)

            if category == .liabilities {
                if let debts = snapshot.detailed.liabilities?.items, !debts.isEmpty {
                    ForEach(Array(debts.enumerated()), id: \.element.id) { index, debt in
                        if let route = debt.detailRoute {
                            Button { onRoute(route) } label: {
                                itemRow(
                                    name: debt.name,
                                    value: debt.value,
                                    fields: debt.liabilityType.map { [titleCase($0)] } ?? [],
                                    outstandingMortgage: nil,
                                    isDebt: true,
                                    isLast: index == debts.count - 1
                                )
                            }
                            .buttonStyle(.plain)
                        }
                    }
                } else if snapshot.liabilityRows.isEmpty {
                    emptyMessage
                } else {
                    ForEach(Array(snapshot.liabilityRows.enumerated()), id: \.offset) { index, row in
                        itemRow(
                            name: row.title,
                            value: row.value,
                            fields: [],
                            outstandingMortgage: nil,
                            isDebt: true,
                            isLast: index == snapshot.liabilityRows.count - 1
                        )
                    }
                }
            } else if let items = snapshot.section(for: category)?.items,
                      !items.isEmpty
            {
                ForEach(Array(items.enumerated()), id: \.offset) { index, item in
                    if let route = item.detailRoute(for: category) {
                        Button { onRoute(route) } label: {
                            itemRow(
                                name: item.name,
                                value: item.value,
                                fields: fields(for: item, category: category),
                                outstandingMortgage: item.outstandingMortgage,
                                isDebt: false,
                                isLast: index == items.count - 1
                            )
                        }
                        .buttonStyle(.plain)
                    } else {
                        itemRow(
                            name: item.name,
                            value: item.value,
                            fields: fields(for: item, category: category),
                            outstandingMortgage: item.outstandingMortgage,
                            isDebt: false,
                            isLast: index == items.count - 1
                        )
                    }
                }
            } else {
                emptyMessage
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mnwc-item — name/value head + field chips, horizon-200 hairlines.
    private func itemRow(
        name: String,
        value: Decimal,
        fields: [String],
        outstandingMortgage: Decimal?,
        isDebt: Bool,
        isLast: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                Text(name)
                    .font(.system(size: 15, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer(minLength: 8)
                Text(MoneyFormatter.gbpWhole(value))
                    .font(.system(size: 15, weight: .bold))
                    .foregroundStyle(
                        isDebt
                            ? FynlaColor.Token.raspberry500.color
                            : FynlaColor.Token.horizon500.color
                    )
                    .lineLimit(1)
            }
            if !fields.isEmpty {
                FlowChips(fields: fields)
                    .padding(.top, 6)
            }
            if let outstandingMortgage, outstandingMortgage > 0 {
                Text("Mortgage \(MoneyFormatter.gbpWhole(outstandingMortgage))")
                    .font(.system(size: 12, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    .padding(.top, 6)
            }
        }
        .padding(.vertical, 12)
        .overlay(alignment: .bottom) {
            if !isLast {
                FynlaColor.Token.horizon200.color.frame(height: 1)
            }
        }
        .accessibilityElement(children: .combine)
    }

    private var emptyMessage: some View {
        Text("Nothing recorded in this category yet.")
            .font(.system(size: 14))
            .foregroundStyle(FynlaColor.Token.neutral500.color)
            .padding(.vertical, 8)
    }

    private func total(
        _ category: NetWorthCategory,
        snapshot: NetWorthSnapshot
    ) -> Decimal {
        category == .liabilities
            ? snapshot.overview.totalLiabilities
            : snapshot.section(for: category)?.totalValue ?? 0
    }

    private func itemCount(
        _ category: NetWorthCategory,
        snapshot: NetWorthSnapshot
    ) -> Int {
        category == .liabilities
            ? snapshot.detailed.liabilities?.items.count ?? snapshot.liabilityRows.count
            : snapshot.section(for: category)?.items.count ?? 0
    }

    private func fields(
        for item: NetWorthAssetItem,
        category: NetWorthCategory
    ) -> [String] {
        var values: [String] = []
        switch category {
        case .property:
            appendTitle(item.type, to: &values)
        case .investments:
            appendTitle(item.accountType, to: &values)
        case .pensions:
            if item.type == "db" {
                values.append("Defined Benefit")
            } else if item.type == "dc" {
                values.append("Defined Contribution")
            }
            if let annual = item.annualPension, annual > 0 {
                values.append("\(MoneyFormatter.gbpWhole(annual)) a year")
            }
        case .cash:
            if item.isISA == true { values.append("ISA") }
            if item.isEmergencyFund == true { values.append("Emergency fund") }
        case .business:
            appendTitle(item.businessType, to: &values)
            if let percentage = item.ownershipPercentage {
                let rounded = Int(
                    NSDecimalNumber(decimal: percentage).doubleValue.rounded()
                )
                values.append("\(rounded)% owned")
            }
        case .chattels:
            appendTitle(item.chattelType, to: &values)
            if let year = item.year { values.append(String(year)) }
        case .liabilities:
            break
        }
        if let ownership = item.ownershipType {
            values.append(ownershipLabel(ownership))
        }
        return values
    }

    private func appendTitle(_ value: String?, to values: inout [String]) {
        guard let value, !value.isEmpty else { return }
        values.append(titleCase(value))
    }

    private func titleCase(_ value: String) -> String {
        value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private func ownershipLabel(_ value: String) -> String {
        switch value {
        case "individual": "Individually owned"
        case "joint": "Jointly owned"
        case "tenants_in_common": "Tenants in common"
        case "trust": "Held in trust"
        default: titleCase(value)
        }
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing the last loaded values.")
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
                    title: "Net Worth",
                    subtitle: "Everything you own, less what you owe"
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

// mnwc-item__field chips — 11/700 neutral600 on horizon100 capsules,
// wrapping with 6pt gaps.
private struct FlowChips: View {
    let fields: [String]

    var body: some View {
        FlowLayout(spacing: 6) {
            ForEach(fields.indices, id: \.self) { index in
                Text(fields[index])
                    .font(.system(size: 11, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.neutral600.color)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 2)
                    .background(FynlaColor.Token.horizon100.color)
                    .clipShape(Capsule())
            }
        }
    }
}

// Minimal wrapping layout for the field chips.
private struct FlowLayout: Layout {
    var spacing: CGFloat

    func sizeThatFits(
        proposal: ProposedViewSize,
        subviews: Subviews,
        cache: inout Void
    ) -> CGSize {
        let width = proposal.width ?? .infinity
        var x: CGFloat = 0
        var y: CGFloat = 0
        var rowHeight: CGFloat = 0
        for subview in subviews {
            let size = subview.sizeThatFits(.unspecified)
            if x > 0, x + size.width > width {
                x = 0
                y += rowHeight + spacing
                rowHeight = 0
            }
            x += size.width + spacing
            rowHeight = max(rowHeight, size.height)
        }
        return CGSize(width: width == .infinity ? x : width, height: y + rowHeight)
    }

    func placeSubviews(
        in bounds: CGRect,
        proposal: ProposedViewSize,
        subviews: Subviews,
        cache: inout Void
    ) {
        var x = bounds.minX
        var y = bounds.minY
        var rowHeight: CGFloat = 0
        for subview in subviews {
            let size = subview.sizeThatFits(.unspecified)
            if x > bounds.minX, x + size.width > bounds.maxX {
                x = bounds.minX
                y += rowHeight + spacing
                rowHeight = 0
            }
            subview.place(
                at: CGPoint(x: x, y: y),
                anchor: .topLeading,
                proposal: .unspecified
            )
            x += size.width + spacing
            rowHeight = max(rowHeight, size.height)
        }
    }
}
