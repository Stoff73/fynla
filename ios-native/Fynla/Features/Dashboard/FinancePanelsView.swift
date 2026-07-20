import SwiftUI

// Mirrors /m's "Your finances" panel grid (finances() in Dashboard.vue):
// five tinted panels with a small donut or bar visual, headline value and
// caption. All derivations are display-only mappings of server figures,
// matching /m's computed exactly.
struct FinancePanel: Identifiable {
    enum Tone {
        case horizon
        case raspberry
        case spring
        case violet

        var accent: Color {
            switch self {
            case .horizon: FynlaColor.Token.horizon500.color
            case .raspberry: FynlaColor.Token.raspberry500.color
            case .spring: FynlaColor.Token.spring500.color
            case .violet: FynlaColor.Token.violet500.color
            }
        }

        var background: Color {
            switch self {
            case .horizon: FynlaColor.Token.horizon100.color
            case .raspberry: FynlaColor.Token.raspberry100.color
            case .spring: FynlaColor.Token.spring100.color
            case .violet: FynlaColor.Token.violet500.color.opacity(0.12)
            }
        }
    }

    enum Visual {
        case donut(progress: Double, number: String, caption: String)
        case bar(fill: Double, value: String, unit: String)
    }

    let id: String
    let label: String
    let tone: Tone
    let value: String
    let caption: String
    let visual: Visual
    let wide: Bool
    let route: AppRoute

    // Builds the /m panel list from the dashboard snapshot.
    static func panels(from snapshot: DashboardSnapshot) -> [FinancePanel] {
        func money(_ value: Decimal?) -> String {
            MoneyFormatter.gbp(value ?? 0)
        }
        func double(_ value: Decimal?) -> Double {
            NSDecimalNumber(decimal: value ?? 0).doubleValue
        }

        let netWorth = snapshot.netWorth
        let assets = netWorth.breakdown.totalAssets ?? {
            let breakdown = netWorth.breakdown.assets
            return [
                breakdown.property, breakdown.savings, breakdown.investments,
                breakdown.pensions, breakdown.business, breakdown.chattels,
                breakdown.cash,
            ].compactMap { $0 }.reduce(0, +)
        }()
        let trend = double(netWorth.trend)
        let trendLabel = (trend >= 0 ? "+" : "") + trend.formatted(.number.precision(.fractionLength(0...1))) + "%"

        let protection = snapshot.modules.protection
        let protectionValue = protection.totalCoverage ?? 0
        let hasCover = protectionValue > 0

        let savings = snapshot.modules.savings
        let emergencyMonths = double(savings.emergencyFundMonths)
        let emergencyTarget = 6.0
        let emergencyFill = min(1, emergencyMonths / emergencyTarget)
        let monthsLabel = emergencyMonths > 0
            ? (emergencyMonths * 10).rounded() / 10
            : 0
        let savingsCaption = emergencyMonths >= emergencyTarget
            ? "Emergency fund on track"
            : (emergencyMonths > 0 ? "Building your fund" : "Start your emergency fund")

        let retirement = snapshot.modules.retirement
        let projected = double(retirement.projectedIncome)
        let target = double(retirement.targetIncome)
        let retirementPct = target > 0 ? min(100, (projected / target * 100).rounded()) : 0
        let pensionAssets = netWorth.breakdown.assets.pensions ?? 0
        let retirementValue = retirement.potValue
            ?? (pensionAssets > 0 ? pensionAssets : retirement.incomeGap)

        let investment = snapshot.modules.investment
        let investmentValue = investment.portfolioValue ?? 0
        let investmentAccounts = investment.accountsCount ?? 0
        let investmentHoldings = investment.holdingsCount ?? 0

        return [
            FinancePanel(
                id: "net_worth",
                label: "Net worth",
                tone: .horizon,
                value: money(netWorth.total),
                caption: money(assets) + " assets",
                visual: .donut(progress: 0.72, number: trendLabel, caption: "Trend"),
                wide: false,
                route: .netWorth(category: nil)
            ),
            FinancePanel(
                id: "protection",
                label: "Protection",
                tone: .raspberry,
                value: money(protectionValue),
                caption: hasCover ? "Cover in place" : "Add your cover",
                visual: .donut(
                    progress: hasCover ? 0.85 : 0,
                    number: hasCover ? "Active" : "None",
                    caption: "Cover"
                ),
                wide: false,
                route: .protection(policyType: nil, id: nil)
            ),
            FinancePanel(
                id: "savings",
                label: "Savings",
                tone: .spring,
                value: money(savings.totalSavings),
                caption: savingsCaption,
                visual: .bar(
                    fill: emergencyFill,
                    value: monthsLabel.formatted(.number.precision(.fractionLength(0...1))),
                    unit: "/ 6 months"
                ),
                wide: false,
                route: .savings(accountID: nil)
            ),
            FinancePanel(
                id: "retirement",
                label: "Retirement",
                tone: .violet,
                value: money(retirementValue),
                caption: target > 0
                    ? "Towards your target"
                    : (double(retirementValue) > 0 ? "Your pension pot" : "Plan your retirement"),
                visual: .bar(
                    fill: retirementPct / 100,
                    value: target > 0 ? "\(Int(retirementPct))%" : "Target not set",
                    unit: target > 0 ? "of target" : ""
                ),
                wide: false,
                route: .retirement(pensionType: nil, id: nil)
            ),
            FinancePanel(
                id: "investment",
                label: "Investment",
                tone: .horizon,
                value: money(investmentValue),
                caption: investmentValue > 0
                    ? "\(investmentHoldings) \(investmentHoldings == 1 ? "holding" : "holdings")"
                    : "Add your investments",
                visual: .donut(
                    progress: investmentValue > 0 ? 0.72 : 0,
                    number: "\(investmentAccounts)",
                    caption: investmentAccounts == 1 ? "Account" : "Accounts"
                ),
                wide: true,
                route: .investment(accountID: nil)
            ),
        ]
    }
}

struct FinancePanelsView: View {
    let panels: [FinancePanel]
    let onRoute: (AppRoute) -> Void

    private var narrowPanels: [FinancePanel] { panels.filter { !$0.wide } }
    private var widePanels: [FinancePanel] { panels.filter(\.wide) }

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Your finances")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)

            LazyVGrid(
                columns: [
                    GridItem(.flexible(), spacing: FynlaSpacing.medium),
                    GridItem(.flexible(), spacing: FynlaSpacing.medium),
                ],
                spacing: FynlaSpacing.medium
            ) {
                ForEach(narrowPanels) { panel in
                    panelCard(panel)
                }
            }

            ForEach(widePanels) { panel in
                panelCard(panel)
            }
        }
        .accessibilityIdentifier("dashboard.finances")
    }

    private func panelCard(_ panel: FinancePanel) -> some View {
        Button {
            onRoute(panel.route)
        } label: {
            VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
                visual(panel)

                VStack(alignment: .leading, spacing: 2) {
                    Text(panel.label)
                        .font(FynlaTypography.caption.weight(.semibold))
                        .foregroundStyle(FynlaColor.secondaryText)
                    Text(panel.value)
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(panel.caption)
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(FynlaSpacing.standard)
            .background(panel.tone.background)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous))
        }
        .buttonStyle(.plain)
        .accessibilityElement(children: .combine)
        .accessibilityLabel("\(panel.label), \(panel.value), \(panel.caption)")
        .accessibilityIdentifier("dashboard.panel.\(panel.id)")
    }

    @ViewBuilder
    private func visual(_ panel: FinancePanel) -> some View {
        switch panel.visual {
        case let .donut(progress, number, caption):
            HStack(spacing: FynlaSpacing.medium) {
                ZStack {
                    Circle()
                        .stroke(panel.tone.accent.opacity(0.25), lineWidth: 6)
                    Circle()
                        .trim(from: 0, to: min(max(progress, 0), 1))
                        .stroke(
                            panel.tone.accent,
                            style: StrokeStyle(lineWidth: 6, lineCap: .round)
                        )
                        .rotationEffect(.degrees(-90))
                }
                .frame(width: 44, height: 44)

                VStack(alignment: .leading, spacing: 0) {
                    Text(number)
                        .font(FynlaTypography.bodySmall.weight(.bold))
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(caption)
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
            }
            .accessibilityHidden(true)
        case let .bar(fill, value, unit):
            VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                GeometryReader { proxy in
                    ZStack(alignment: .leading) {
                        Capsule()
                            .fill(panel.tone.accent.opacity(0.25))
                        Capsule()
                            .fill(panel.tone.accent)
                            .frame(width: proxy.size.width * min(max(fill, 0), 1))
                    }
                }
                .frame(height: 8)

                (
                    Text(value).fontWeight(.bold)
                    + Text(unit.isEmpty ? "" : " \(unit)")
                )
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.primaryText)
            }
            .accessibilityHidden(true)
        }
    }
}
