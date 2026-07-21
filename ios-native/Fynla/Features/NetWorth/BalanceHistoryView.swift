import Charts
import SwiftUI

// Transcribes /m's balance history page (resources/mobile/views/
// BalanceHistory.vue): gradient page hero, Edit details pill, visibility
// line, Free 90-day window card (windowDays == 90 is /m-parity, ledger
// P1-6), dark latest-net-worth hero, signed year-on-year change card,
// recorded-balances line chart in the designSystem series colours, and the
// premium adviser-export card. Whole-pound amounts.
struct BalanceHistoryView: View {
    let model: BalanceHistoryModel
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your balance history…") }
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
        .task { await model.load() }
        .accessibilityIdentifier("balance-history.screen")
    }

    private func content(
        _ history: BalanceHistory,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Balance history",
                    subtitle: "How your recorded balances have changed"
                )

                MobilePageActions(editDetails: {
                    onOpenFyn("What would you like to update?")
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    Text(history.visibility.label)
                        .font(.system(size: 13, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                        .padding(.horizontal, 4)
                        .accessibilityIdentifier("balance-history.visibility")

                    // ponytail: windowDays == 90 IS the Free-window contract
                    // (ledger P1-6).
                    if history.visibility.windowDays == 90 {
                        freeHistoryCard
                    }

                    if let latest = history.latest {
                        latestHero(latest)
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
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // mbh-premium — violet-bordered savannah card with the upgrade button.
    private var freeHistoryCard: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text("90 days of history are included on Free.")
                .font(.system(size: 17, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            Text("Premium includes your full available retained history and the financial adviser export pack.")
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 10)
            fullWidthButton("See plans and upgrade", action: onOpenSubscription)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.Token.savannah100.color)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .stroke(FynlaColor.Token.violet500.color, lineWidth: 1)
        )
        .accessibilityIdentifier("balance-history.free-window")
    }

    // m-hero — dark card: latest net worth + assets/liabilities split.
    private func latestHero(_ latest: BalanceHistoryTotal) -> some View {
        MobileHeroCard(
            label: "Latest net worth",
            metric: MoneyFormatter.gbpWhole(latest.netWorth),
            sub: "\(MoneyFormatter.gbpWhole(latest.assets)) in assets, less \(MoneyFormatter.gbpWhole(latest.liabilities)) of liabilities."
        )
        .accessibilityIdentifier("balance-history.latest")
    }

    // mbh-change — 24/900 signed value, spring for ≥ 0, raspberry-600 below.
    private func yearOnYearCard(
        _ change: BalanceHistoryYearOnYear
    ) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            sectionLabel("Year-on-year change")
                .padding(.bottom, 4)
            Text(changeText(change))
                .font(.system(size: 24, weight: .black))
                .foregroundStyle(
                    change.netWorthChange >= 0
                        ? FynlaColor.Token.spring600.color
                        : FynlaColor.Token.raspberry600.color
                )
            Text("Compared with \(displayDate(change.comparedWith))")
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .accessibilityIdentifier("balance-history.year-on-year")
    }

    private func historyChart(
        _ totals: [BalanceHistoryTotal]
    ) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            sectionLabel("Recorded balances")
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
            // /m series colours: CHART_COLORS[0]/[1]/[3] — horizon, spring,
            // raspberry.
            .chartForegroundStyleScale([
                "Net worth": FynlaColor.Token.horizon500.color,
                "Assets": FynlaColor.Token.spring500.color,
                "Liabilities": FynlaColor.Token.raspberry500.color,
            ])
            .frame(height: 280)
            .accessibilityLabel("Recorded net worth, assets and liabilities")
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .accessibilityIdentifier("balance-history.chart")
    }

    private var emptyCard: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text("No balance history yet")
                .font(.system(size: 17, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            Text("Your balance history will appear after account values change.")
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .accessibilityIdentifier("balance-history.empty")
    }

    // The native export flow prepares the file then shares it (no browser
    // auto-download); button styling follows /m's m-btn.
    private var exportCard: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text("Financial adviser export pack")
                .font(.system(size: 17, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            Text("Download a dated summary of your financial information and module plans.")
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 10)
            fullWidthButton(
                model.isExporting ? "Preparing export…" : "Download adviser export pack",
                action: { Task { await model.prepareAdviserExport() } }
            )
            .disabled(model.isExporting)
            .opacity(model.isExporting ? 0.5 : 1)

            if let exportURL = model.exportURL {
                ShareLink("Share adviser export pack", item: exportURL)
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    .padding(.top, 6)
                    .accessibilityIdentifier("balance-history.export-share")
            }
            if let exportError = model.exportError {
                Text(exportError)
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.raspberry600.color)
                    .padding(.top, 6)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .accessibilityIdentifier("balance-history.export")
    }

    // m-btn — full-width raspberry.
    private func fullWidthButton(_ title: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(title)
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(.white)
                .frame(maxWidth: .infinity)
                .padding(14)
                .background(FynlaColor.Token.raspberry500.color)
                .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
        }
    }

    private func sectionLabel(_ text: String) -> some View {
        Text(text.uppercased())
            .font(.system(size: 12, weight: .bold))
            .kerning(0.5)
            .foregroundStyle(FynlaColor.Token.neutral500.color)
    }

    // /m signedCurrency + signedPercentage: +/− prefixed whole pounds and a
    // one-decimal percentage.
    private func changeText(_ change: BalanceHistoryYearOnYear) -> String {
        let amount = change.netWorthChange
        let prefix = amount > 0 ? "+" : (amount < 0 ? "-" : "")
        let magnitude = MoneyFormatter.gbpWhole(abs(amount))
        let percentage = change.percentageChange.map {
            let sign = $0 > 0 ? "+" : ""
            let value = String(
                format: "%.1f",
                NSDecimalNumber(decimal: $0).doubleValue
            )
            return " (\(sign)\(value)%)"
        } ?? ""
        return "\(prefix)\(magnitude)\(percentage)"
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

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded balance history.")
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
                    title: "Balance history",
                    subtitle: "How your recorded balances have changed"
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
