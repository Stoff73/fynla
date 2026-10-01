import SwiftUI

// Transcribes /m's "Your finances" grid (md-panels in Dashboard.vue +
// dashboard.css): 2-up centred tiles with a soft per-tone gradient tint, a
// donut (conic pie with white inner) or progress-bar visual on top, then the
// uppercase label with a small glyph, headline value and caption. Every figure
// is the server's DashboardCards block, rendered as sent.
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

        // md-panel--{tone}: 135° gradient tint of the accent (12–14% → 2%).
        var background: LinearGradient {
            let strength: Double = self == .horizon ? 0.12 : 0.14
            return LinearGradient(
                colors: [accent.opacity(strength), accent.opacity(0.02)],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        }
    }

    enum Visual {
        case donut(progress: Double, number: String, caption: String)
        case bar(fill: Double, value: String, unit: String)
    }

    let id: String
    let label: String
    let icon: String
    let tone: Tone
    let value: String
    let caption: String
    let visual: Visual
    let wide: Bool
    let route: AppRoute

    // Builds the panel list from the server's `cards` block (DashboardCards):
    // every figure, ring and caption is rendered as sent, the same fields web
    // and /m render (CSJ 2026-10-01: one figure, every surface). Only labels,
    // glyphs, tones and routes are this surface's own.
    static func panels(from snapshot: DashboardSnapshot) -> [FinancePanel] {
        func panel(
            _ card: DashboardCard?,
            id: String,
            label: String,
            icon: String,
            tone: Tone,
            wide: Bool = false,
            route: AppRoute
        ) -> FinancePanel {
            let value = card?.value ?? 0
            let money = MoneyFormatter.gbpWhole(value)
            let visual = card?.visual
            let progress = Double(visual?.progress ?? 0) / 100
            let number = visual?.number ?? ""
            let caption = visual?.label ?? ""
            return FinancePanel(
                id: id,
                label: label,
                icon: icon,
                tone: tone,
                value: (card?.valueIsIncome ?? false) ? "\(money)/year" : money,
                caption: card?.caption ?? "",
                visual: visual?.type == "donut"
                    ? .donut(progress: progress, number: number, caption: caption)
                    : .bar(fill: progress, value: number, unit: caption),
                wide: wide,
                route: route
            )
        }

        let cards = snapshot.cards
        return [
            panel(cards?.netWorth, id: "net_worth", label: "Net worth", icon: "chart.bar", tone: .horizon,
                  route: .netWorth(category: nil)),
            panel(cards?.protection, id: "protection", label: "Protection", icon: "checkmark.shield", tone: .raspberry,
                  route: .protection(policyType: nil, id: nil)),
            panel(cards?.savings, id: "savings", label: "Bank Accounts", icon: "creditcard", tone: .spring,
                  route: .savings(accountID: nil)),
            panel(cards?.retirement, id: "retirement", label: "Retirement", icon: "clock", tone: .violet,
                  route: .retirement(pensionType: nil, id: nil)),
            panel(cards?.investment, id: "investment", label: "Investment", icon: "chart.line.uptrend.xyaxis",
                  tone: .horizon, wide: true, route: .investment(accountID: nil)),
        ]
    }
}

struct FinancePanelsView: View {
    let panels: [FinancePanel]
    let onRoute: (AppRoute) -> Void

    private var narrowPanels: [FinancePanel] { panels.filter { !$0.wide } }
    private var widePanels: [FinancePanel] { panels.filter(\.wide) }

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("Your finances")
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon600.color)

            // Eager 2-up rows (not LazyVGrid): five fixed tiles need no
            // laziness, and offscreen tiles stay in the accessibility tree.
            VStack(spacing: 10) {
                ForEach(Array(stride(from: 0, to: narrowPanels.count, by: 2)), id: \.self) { start in
                    HStack(alignment: .top, spacing: 10) {
                        ForEach(narrowPanels[start..<min(start + 2, narrowPanels.count)]) { panel in
                            panelCard(panel)
                        }
                    }
                }
            }

            ForEach(widePanels) { panel in
                panelCard(panel)
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("dashboard.finances")
    }

    private func panelCard(_ panel: FinancePanel) -> some View {
        Button {
            onRoute(panel.route)
        } label: {
            VStack(spacing: 10) {
                visual(panel)

                VStack(spacing: 2) {
                    HStack(spacing: 5) {
                        Image(systemName: panel.icon)
                            .font(.system(size: 11, weight: .medium))
                            .foregroundStyle(FynlaColor.Token.horizon400.color)
                            .accessibilityHidden(true)
                        Text(panel.label.uppercased())
                            .font(.system(size: 11, weight: .semibold))
                            .kerning(0.4)
                            .foregroundStyle(FynlaColor.Token.horizon400.color)
                    }
                    Text(panel.value)
                        .font(.system(size: 18, weight: .heavy))
                        .foregroundStyle(FynlaColor.Token.horizon600.color)
                        .lineLimit(1)
                        .minimumScaleFactor(0.8)
                    Text(panel.caption)
                        .font(.system(size: 11))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                        .lineLimit(1)
                }
            }
            .frame(maxWidth: .infinity)
            .padding(14)
            .background(panel.tone.background)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
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
            ZStack {
                Circle()
                    .fill(FynlaColor.Token.horizon100.color)
                PieWedge(progress: min(max(progress, 0), 1))
                    .fill(panel.tone.accent)
                Circle()
                    .fill(Color.white)
                    .frame(width: 52, height: 52)
                VStack(spacing: 1) {
                    Text(number)
                        .font(.system(size: 12, weight: .heavy))
                        .foregroundStyle(FynlaColor.Token.horizon600.color)
                        .lineLimit(1)
                        .minimumScaleFactor(0.7)
                    Text(caption.uppercased())
                        .font(.system(size: 8, weight: .semibold))
                        .kerning(0.3)
                        .foregroundStyle(FynlaColor.Token.horizon400.color)
                        .lineLimit(1)
                        .minimumScaleFactor(0.6)
                }
                .frame(width: 50)
            }
            .frame(width: 68, height: 68)
            .accessibilityHidden(true)
        case let .bar(fill, value, unit):
            VStack(spacing: 6) {
                ZStack(alignment: .leading) {
                    GeometryReader { proxy in
                        Capsule()
                            .fill(FynlaColor.Token.horizon100.color)
                        Capsule()
                            .fill(panel.tone.accent)
                            .frame(width: proxy.size.width * min(max(fill, 0), 1))
                    }
                }
                .frame(height: 8)
                .padding(.horizontal, 8)

                (
                    Text(value)
                        .font(.system(size: 13, weight: .heavy))
                        .foregroundColor(FynlaColor.Token.horizon600.color)
                    + Text(unit.isEmpty ? "" : " \(unit)")
                        .font(.system(size: 11))
                        .foregroundColor(FynlaColor.Token.horizon500.color)
                )
                .lineLimit(1)
            }
            .padding(.top, 4)
            .accessibilityHidden(true)
        }
    }
}

// Filled pie wedge from 12 o'clock, matching /m's conic-gradient donut fill.
private struct PieWedge: Shape {
    let progress: Double

    func path(in rect: CGRect) -> Path {
        var path = Path()
        guard progress > 0 else { return path }
        let center = CGPoint(x: rect.midX, y: rect.midY)
        path.move(to: center)
        path.addArc(
            center: center,
            radius: min(rect.width, rect.height) / 2,
            startAngle: .degrees(-90),
            endAngle: .degrees(-90 + 360 * progress),
            clockwise: false
        )
        path.closeSubpath()
        return path
    }
}
