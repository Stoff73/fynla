import Charts
import SwiftUI

struct BalanceHistoryView: View {
    let model: BalanceHistoryModel
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                ScreenStateView(state: .loading)
            case let .loaded(history):
                content(history)
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
        .navigationTitle("Balance History")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("balance-history.screen")
    }

    private func content(
        _ history: BalanceHistory,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Balance history")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("How your recorded balances have changed")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                if offline {
                    Text("You're offline. Showing your last loaded balance history.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                        .padding(FynlaSpacing.medium)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .background(FynlaColor.Token.savannah100.color)
                        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                }

                Text(history.visibility.label)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .accessibilityIdentifier("balance-history.visibility")

                if history.visibility.windowDays == 90 {
                    freeHistoryCard
                }

                if let latest = history.latest {
                    latestCard(latest)
                    if let change = history.yearOnYear {
                        yearOnYearCard(change)
                    }
                    historyChart(history.totals)
                } else {
                    emptyCard
                }

                if history.visibility.windowDays == nil {
                    exportCard
                }
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private var freeHistoryCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("90 days of history are included on Free.")
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Premium includes your full available retained history and the financial adviser export pack.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            Button("See plans and upgrade", action: onOpenSubscription)
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.Token.savannah100.color)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .accessibilityIdentifier("balance-history.free-window")
    }

    private func latestCard(_ latest: BalanceHistoryTotal) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Latest net worth")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(latest.netWorth)
            Text("\(MoneyFormatter.gbp(latest.assets)) in assets, less \(MoneyFormatter.gbp(latest.liabilities)) of liabilities.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .accessibilityIdentifier("balance-history.latest")
    }

    private func yearOnYearCard(
        _ change: BalanceHistoryYearOnYear
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Year-on-year change")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text(changeText(change))
                .font(FynlaTypography.pageTitle)
                .foregroundStyle(
                    change.netWorthChange < 0
                        ? FynlaColor.Token.raspberry600.color
                        : FynlaColor.primaryText
                )
            Text("Compared with \(displayDate(change.comparedWith))")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .accessibilityIdentifier("balance-history.year-on-year")
    }

    private func historyChart(
        _ totals: [BalanceHistoryTotal]
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Recorded balances")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Chart {
                ForEach(totals) { point in
                    LineMark(
                        x: .value("Date", point.date),
                        y: .value("Net worth", double(point.netWorth))
                    )
                    .foregroundStyle(by: .value("Balance", "Net worth"))
                }
                ForEach(totals) { point in
                    LineMark(
                        x: .value("Date", point.date),
                        y: .value("Assets", double(point.assets))
                    )
                    .foregroundStyle(by: .value("Balance", "Assets"))
                }
                ForEach(totals) { point in
                    LineMark(
                        x: .value("Date", point.date),
                        y: .value("Liabilities", double(point.liabilities))
                    )
                    .foregroundStyle(by: .value("Balance", "Liabilities"))
                }
            }
            .frame(height: 280)
            .accessibilityLabel("Recorded net worth, assets and liabilities")
        }
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .accessibilityIdentifier("balance-history.chart")
    }

    private var emptyCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("No balance history yet")
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Your balance history will appear after account values change.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .accessibilityIdentifier("balance-history.empty")
    }

    private var exportCard: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Financial adviser export pack")
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
            Text("Download a dated summary of your financial information and module plans.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            Button(model.isExporting ? "Preparing export…" : "Prepare adviser export pack") {
                Task { await model.prepareAdviserExport() }
            }
            .buttonStyle(.borderedProminent)
            .tint(FynlaColor.primaryAction)
            .disabled(model.isExporting)

            if let exportURL = model.exportURL {
                ShareLink("Share adviser export pack", item: exportURL)
                    .font(FynlaTypography.button)
                    .accessibilityIdentifier("balance-history.export-share")
            }
            if let exportError = model.exportError {
                Text(exportError)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryAction)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .accessibilityIdentifier("balance-history.export")
    }

    private func changeText(_ change: BalanceHistoryYearOnYear) -> String {
        let amount = change.netWorthChange
        let amountPrefix = amount > 0 ? "+" : ""
        let percentage = change.percentageChange.map {
            let prefix = $0 > 0 ? "+" : ""
            return " (\(prefix)\(MoneyFormatter.percentage($0)))"
        } ?? ""
        return "\(amountPrefix)\(MoneyFormatter.gbp(amount))\(percentage)"
    }

    private func displayDate(_ date: String) -> String {
        let parser = DateFormatter()
        parser.locale = Locale(identifier: "en_US_POSIX")
        parser.timeZone = TimeZone(secondsFromGMT: 0)
        parser.dateFormat = "yyyy-MM-dd"
        guard let parsed = parser.date(from: date) else { return date }
        return MoneyFormatter.ukDate(parsed)
    }

    private func double(_ value: Decimal) -> Double {
        NSDecimalNumber(decimal: value).doubleValue
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
