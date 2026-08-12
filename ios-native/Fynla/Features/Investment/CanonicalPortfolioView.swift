import Charts
import SwiftUI

/// Renders the server-owned `financial_portfolio_v1` contract without
/// reconstructing exposure, drift, fees or performance on the client.
struct CanonicalPortfolioView: View {
    let portfolio: CanonicalPortfolio

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            holdingsCard
            exposureCard
            comparisonCard(
                title: "Entered portfolio",
                comparison: portfolio.analysis.comparisons.entered
            )
            comparisonCard(
                title: "Recommended portfolio",
                comparison: portfolio.analysis.comparisons.recommended
            )
            historyCard
        }
        .accessibilityElement(children: .contain)
    }

    private var holdingsCard: some View {
        card {
            sectionTitle("Holdings")

            if portfolio.holdings.isEmpty {
                unavailable("No individual holdings are recorded for this portfolio.")
            } else {
                ForEach(Array(portfolio.holdings.enumerated()), id: \.element.id) { index, holding in
                    holdingRow(holding)
                        .padding(.vertical, 12)
                        .overlay(alignment: .bottom) {
                            if index < portfolio.holdings.count - 1 {
                                FynlaColor.Token.horizon100.color.frame(height: 1)
                            }
                        }
                }
            }
        }
    }

    private func holdingRow(_ holding: CanonicalPortfolioHolding) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(holding.displayName)
                        .font(.system(size: 14, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    if let ticker = holding.ticker, ticker != holding.displayName {
                        Text(ticker)
                            .font(.system(size: 12))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                    }
                }
                Spacer(minLength: 8)
                Text(MoneyFormatter.gbpWhole(holding.currentValue))
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
            }

            HStack(spacing: 12) {
                Text("\(percentage(holding.wrapperPercentage)) of account")
                Text("\(percentage(holding.wholeRelevantPortfolioPercentage)) overall")
            }
            .font(.system(size: 12))
            .foregroundStyle(FynlaColor.Token.neutral500.color)

            if !holding.classifiedExposure.isEmpty {
                VStack(alignment: .leading, spacing: 4) {
                    Text("Recorded look-through")
                        .font(.system(size: 11, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                    ForEach(holding.classifiedExposure) { exposure in
                        valueRow(
                            titleCase(exposure.assetClass),
                            "\(percentage(exposure.holdingPercentage)) · \(MoneyFormatter.gbpWhole(exposure.value))"
                        )
                    }
                    if let source = holding.classification?.source {
                        Text("Source: \(titleCase(source))\(effectiveSuffix(holding.classification?.effectiveAt))")
                            .font(.system(size: 11))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                    }
                }
            }

            HStack(alignment: .top, spacing: 16) {
                metricBlock(
                    title: "Holding charge",
                    value: holding.fees.available
                        ? feeLabel(holding.fees)
                        : "Unavailable"
                )
                metricBlock(
                    title: "Recorded performance",
                    value: holding.performance.available
                        ? performanceLabel(holding.performance)
                        : "Unavailable",
                    color: performanceColor(holding.performance)
                )
            }
        }
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("canonical-portfolio.holding.\(holding.id)")
    }

    private var exposureCard: some View {
        card {
            sectionTitle("Portfolio exposure")
            valueRow("Recorded portfolio value", MoneyFormatter.gbpWhole(portfolio.recordedWrapperValue))
            valueRow("Classified", MoneyFormatter.gbpWhole(portfolio.analysis.classifiedValue))
            valueRow("Unclassified", MoneyFormatter.gbpWhole(portfolio.analysis.unclassifiedValue))
            valueRow("Classification coverage", percentage(portfolio.analysis.coveragePercent))

            ForEach(portfolio.analysis.allocation) { allocation in
                valueRow(
                    titleCase(allocation.assetClass),
                    "\(percentage(allocation.portfolioPercentage)) · \(MoneyFormatter.gbpWhole(allocation.value))"
                )
            }

            if !portfolio.analysis.driftAvailable {
                unavailable(
                    "Drift is unavailable because classified coverage is below the server threshold of \(percentage(portfolio.analysis.coverageThresholdPercent))."
                )
                .padding(.top, 6)
            }
        }
    }

    @ViewBuilder
    private func comparisonCard(
        title: String,
        comparison: CanonicalPortfolioComparison?
    ) -> some View {
        card {
            sectionTitle(title)
                .accessibilityIdentifier(
                    "canonical-portfolio.comparison.\(title.lowercased().replacingOccurrences(of: " ", with: "-"))"
                )

            if let comparison {
                if let reason = comparison.unavailableReason {
                    unavailable(unavailableReason(reason))
                } else {
                    let driftKeys = comparison.driftPercentagePoints.map {
                        Array($0.keys)
                    } ?? []
                    let keys = Set(comparison.allocation.keys)
                        .union(driftKeys)
                        .sorted()
                    ForEach(keys, id: \.self) { key in
                        let target = comparison.allocation[key]
                        let drift = comparison.driftPercentagePoints?[key]
                        valueRow(
                            titleCase(key),
                            comparisonValue(target: target, drift: drift)
                        )
                    }
                    if let source = comparison.source {
                        Text("Source: \(titleCase(source))\(effectiveSuffix(comparison.effectiveAt))")
                            .font(.system(size: 11))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                            .padding(.top, 4)
                    }
                }
            } else {
                unavailable("No \(title.lowercased()) has been recorded for comparison.")
            }
        }
    }

    private var historyCard: some View {
        card {
            sectionTitle("Recorded performance history")
                .accessibilityIdentifier("canonical-portfolio.history")

            if portfolio.performanceHistory.available,
               !portfolio.performanceHistory.points.isEmpty
            {
                Chart(portfolio.performanceHistory.points) { point in
                    LineMark(
                        x: .value("Date", point.date ?? "Unknown"),
                        y: .value("Value", double(point.value))
                    )
                    .foregroundStyle(FynlaColor.Token.violet500.color)

                    PointMark(
                        x: .value("Date", point.date ?? "Unknown"),
                        y: .value("Value", double(point.value))
                    )
                    .foregroundStyle(FynlaColor.Token.violet500.color)
                }
                .frame(height: 150)
                .chartYAxis {
                    AxisMarks(position: .leading) { value in
                        AxisGridLine()
                        AxisValueLabel {
                            if let amount = value.as(Double.self) {
                                Text(MoneyFormatter.gbpWhole(Decimal(amount)))
                            }
                        }
                    }
                }

                ForEach(portfolio.performanceHistory.points) { point in
                    valueRow(point.date ?? "Date unavailable", MoneyFormatter.gbpWhole(point.value))
                }

                Text("Recorded account-value snapshots only; no missing values are inferred.")
                    .font(.system(size: 11))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.top, 4)
            } else {
                unavailable("Recorded performance history is unavailable.")
            }
        }
    }

    private func card<Content: View>(@ViewBuilder content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            content()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func sectionTitle(_ title: String) -> some View {
        Text(title.uppercased())
            .font(.system(size: 12, weight: .bold))
            .kerning(0.5)
            .foregroundStyle(FynlaColor.Token.neutral500.color)
    }

    private func valueRow(_ key: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key)
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            Spacer(minLength: 8)
            Text(value)
                .font(.system(size: 13, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
                .multilineTextAlignment(.trailing)
        }
    }

    private func metricBlock(
        title: String,
        value: String,
        color: Color = FynlaColor.Token.horizon500.color
    ) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(title)
                .font(.system(size: 11))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            Text(value)
                .font(.system(size: 12, weight: .bold))
                .foregroundStyle(color)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func unavailable(_ message: String) -> some View {
        Text(message)
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.neutral500.color)
    }

    private func feeLabel(_ fees: CanonicalHoldingFees) -> String {
        let percentageValue = fees.ocfPercent.map(percentage)
        let cost = fees.estimatedAnnualCost.map { "\(MoneyFormatter.gbpWhole($0)) a year" }
        return [percentageValue, cost].compactMap { $0 }.joined(separator: " · ")
    }

    private func performanceLabel(_ performance: CanonicalHoldingPerformance) -> String {
        let amount = performance.gainLoss.map { signedMoney($0) }
        let percentageValue = performance.gainLossPercent.map { signedPercentage($0) }
        return [amount, percentageValue].compactMap { $0 }.joined(separator: " · ")
    }

    private func performanceColor(_ performance: CanonicalHoldingPerformance) -> Color {
        guard let gain = performance.gainLoss else {
            return FynlaColor.Token.neutral500.color
        }
        return gain >= 0
            ? FynlaColor.Token.spring600.color
            : FynlaColor.Token.raspberry500.color
    }

    private func comparisonValue(target: Decimal?, drift: Decimal?) -> String {
        let targetValue = target.map { "Target \(percentage($0))" }
        let driftValue = drift.map { "Drift \(signedPercentagePoints($0))" }
        return [targetValue, driftValue].compactMap { $0 }.joined(separator: " · ")
    }

    private func signedMoney(_ value: Decimal) -> String {
        value >= 0
            ? "+\(MoneyFormatter.gbpWhole(value))"
            : MoneyFormatter.gbpWhole(value)
    }

    private func signedPercentage(_ value: Decimal) -> String {
        value >= 0 ? "+\(percentage(value))" : percentage(value)
    }

    private func signedPercentagePoints(_ value: Decimal) -> String {
        let prefix = value > 0 ? "+" : ""
        return "\(prefix)\(oneDecimal(value))pp"
    }

    private func percentage(_ value: Decimal) -> String { "\(oneDecimal(value))%" }

    private func oneDecimal(_ value: Decimal) -> String {
        String(format: "%.1f", double(value))
    }

    private func double(_ value: Decimal) -> Double {
        NSDecimalNumber(decimal: value).doubleValue
    }

    private func titleCase(_ value: String) -> String {
        value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private func effectiveSuffix(_ value: String?) -> String {
        guard let value, !value.isEmpty else { return "" }
        return " · effective \(value)"
    }

    private func unavailableReason(_ value: String) -> String {
        value.replacingOccurrences(of: "_", with: " ").capitalized + "."
    }
}
