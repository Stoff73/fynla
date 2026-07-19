import SwiftUI

struct NetWorthCategoryView: View {
    let categoryKey: String
    let model: NetWorthModel
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            if let category = NetWorthCategory(rawValue: categoryKey) {
                loadedState(category)
            } else {
                ScreenStateView(
                    state: .empty(message: "This net worth category is unavailable.")
                )
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Net Worth")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("net-worth.category.screen")
    }

    @ViewBuilder
    private func loadedState(_ category: NetWorthCategory) -> some View {
        switch model.state {
        case .idle, .loading:
            ScreenStateView(state: .loading)
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
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(category.title)
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(category.subtitle)
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                if offline {
                    Text("You're offline. Showing the last loaded values.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                        .padding(FynlaSpacing.medium)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .background(FynlaColor.Token.savannah100.color)
                        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                }

                hero(category, snapshot: snapshot)
                itemCard(category, snapshot: snapshot)

                Button("Update \(category.title.lowercased()) with Fyn") {
                    onOpenFyn(editPrompt(category, snapshot: snapshot))
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(
                    maxWidth: .infinity,
                    minHeight: FynlaSpacing.minimumInteractiveTarget
                )
                .accessibilityIdentifier("net-worth.category.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(
        _ category: NetWorthCategory,
        snapshot: NetWorthSnapshot
    ) -> some View {
        let isLiability = category == .liabilities
        let rows = itemCount(category, snapshot: snapshot)
        return VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(isLiability ? "Total owed" : "Total value")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(total(category, snapshot: snapshot))
            Text("Across \(rows) \(isLiability ? (rows == 1 ? "type" : "types") + " of debt" : (rows == 1 ? "item" : "items")).")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    @ViewBuilder
    private func itemCard(
        _ category: NetWorthCategory,
        snapshot: NetWorthSnapshot
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(category == .liabilities ? "Breakdown" : "Items")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)

            if category == .liabilities {
                if snapshot.liabilityRows.isEmpty {
                    emptyMessage
                } else {
                    ForEach(snapshot.liabilityRows) { row in
                        itemRow(name: row.title, value: row.value, fields: [])
                    }
                }
            } else if let items = snapshot.section(for: category)?.items,
                      !items.isEmpty
            {
                ForEach(items) { item in
                    itemRow(
                        name: item.name,
                        value: item.value,
                        fields: fields(for: item, category: category)
                    )
                }
            } else {
                emptyMessage
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func itemRow(
        name: String,
        value: Decimal,
        fields: [String]
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.medium) {
                Text(name)
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer(minLength: FynlaSpacing.small)
                Text(MoneyFormatter.gbp(value))
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
            }
            if !fields.isEmpty {
                Text(fields.joined(separator: " · "))
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .padding(.vertical, FynlaSpacing.medium)
        .overlay(alignment: .bottom) { Divider() }
        .accessibilityElement(children: .combine)
    }

    private var emptyMessage: some View {
        Text("Nothing recorded in this category yet.")
            .font(FynlaTypography.body)
            .foregroundStyle(FynlaColor.secondaryText)
            .padding(.vertical, FynlaSpacing.small)
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
            ? snapshot.liabilityRows.count
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
                values.append("\(MoneyFormatter.gbp(annual)) a year")
            }
        case .cash:
            if item.isISA == true { values.append("ISA") }
            if item.isEmergencyFund == true { values.append("Emergency fund") }
        case .business:
            appendTitle(item.businessType, to: &values)
            if let percentage = item.ownershipPercentage {
                values.append("\(MoneyFormatter.percentage(percentage)) owned")
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

    private func editPrompt(
        _ category: NetWorthCategory,
        snapshot: NetWorthSnapshot
    ) -> String {
        let names = category == .liabilities
            ? snapshot.liabilityRows.map { Optional($0.title) }
            : snapshot.section(for: category)?.items.map { Optional($0.name) } ?? []
        return FynEditIntent.message(
            updateScope: category.title.lowercased(),
            addPhrase: "I'd like to add \(category == .liabilities ? "a liability" : "an asset").",
            names: names
        )
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
