import SwiftUI

// Transcribes /m's Holistic Plan page (resources/mobile/views/HolisticPlan.vue):
// gradient page hero, Edit details pill (generic /m prompt), dark surplus hero,
// ranked cross-module actions with priority/module badges + monthly cost +
// affordability line, and the locked-strategies card. Whole-pound amounts as
// /m's formatCurrency. The premium gate flows through the shared /m-styled
// ScreenStateView.
struct HolisticPlanView: View {
    let model: HolisticPlanModel
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your holistic plan…") }
            case let .loaded(plan):
                content(plan)
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
        .accessibilityIdentifier("holistic-plan.screen")
    }

    private func content(
        _ plan: HolisticPlan,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Holistic Plan",
                    subtitle: "Your whole plan, ranked against what you can afford"
                )

                // /m's MobileChrome default Edit details pill with the generic
                // fallback prompt (HolisticPlan.vue passes no editPrompt).
                MobilePageActions(editDetails: {
                    onOpenFyn("What would you like to update?")
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    surplusHero(plan)

                    rankedActionsCard(plan.items)

                    if !plan.locked.isEmpty {
                        lockedCard(plan.locked)
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero — dark card: effective surplus + how it was derived.
    private func surplusHero(_ plan: HolisticPlan) -> some View {
        MobileHeroCard(
            label: "Left to put to work each month",
            metric: MoneyFormatter.gbpWhole(plan.effectiveSurplus),
            sub: surplusContext(plan)
        )
        .accessibilityIdentifier("holistic-plan.effective-surplus")
    }

    private func rankedActionsCard(_ items: [HolisticPlanItem]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionLabel("Your ranked actions")
                .padding(.bottom, 8)
            if items.isEmpty {
                Text("No cross-module actions just yet. Add more detail to your modules to build your plan.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            } else {
                ForEach(Array(items.enumerated()), id: \.offset) { index, item in
                    actionCard(item, isLast: index == items.count - 1)
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mhp-rec — bordered sub-card: priority/module badges + cost, title,
    // description, conflict note, affordability line.
    private func actionCard(_ item: HolisticPlanItem, isLast: Bool) -> some View {
        let beyond = item.affordability == "beyond_current_surplus"
        return VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .top, spacing: 12) {
                HStack(spacing: 6) {
                    priorityBadge(item.priority)
                    badge(
                        moduleLabel(item.module),
                        color: FynlaColor.Token.horizon500.color,
                        background: FynlaColor.Token.horizon100.color
                    )
                }
                Spacer(minLength: 8)
                if let cost = item.requiredMonthlyCost, cost > 0 {
                    (Text(MoneyFormatter.gbpWhole(cost))
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                        + Text("/mo")
                        .font(.system(size: 11))
                        .foregroundStyle(FynlaColor.Token.neutral500.color))
                        .lineLimit(1)
                }
            }
            .padding(.bottom, 8)
            Text(item.title)
                .font(.system(size: 15, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            if let description = item.description, !description.isEmpty {
                Text(description)
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.neutral600.color)
                    .lineSpacing(3)
                    .padding(.top, 4)
            }
            if let conflict = item.conflictNote, !conflict.isEmpty {
                // /m's --violet-700 is undefined, so the note renders in the
                // inherited body colour (horizon-500) — match that.
                Text(conflict)
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .padding(.top, 6)
            }
            Text(affordabilityLabel(item.affordability))
                .font(.system(size: 12, weight: .bold))
                .foregroundStyle(affordabilityColor(item.affordability))
                .padding(.top, 8)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(beyond ? FynlaColor.Token.eggshell500.color : Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .stroke(
                    // /m's --violet-200 is undefined, so partially_fits
                    // borders compute to currentcolor (body horizon-500).
                    item.affordability == "partially_fits"
                        ? FynlaColor.Token.horizon500.color
                        : FynlaColor.Token.lightGray.color,
                    lineWidth: 1
                )
        )
        .opacity(beyond ? 0.85 : 1)
        .padding(.bottom, isLast ? 0 : 12)
        .accessibilityIdentifier("holistic-plan.action.\(item.id)")
    }

    private func lockedCard(_ strategies: [HolisticLockedStrategy]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionLabel("Unlock more of your plan")
                .padding(.bottom, 8)
            Text("These strategies need a little more detail before we can rank them.")
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 8)
            ForEach(Array(strategies.enumerated()), id: \.offset) { index, strategy in
                (Text("\(moduleLabel(strategy.module)): ")
                    .fontWeight(.bold)
                    + Text(strategyLabel(strategy.strategyType)))
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .padding(.vertical, 6)
                    .overlay(alignment: .bottom) {
                        if index != strategies.count - 1 {
                            FynlaColor.Token.horizon100.color.frame(height: 1)
                        }
                    }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mhp-rec__priority — 11/700 pill. High and low reference undefined
    // violet-800/100 and spring-800/100 vars on /m, so they render as the
    // inherited body colour on a transparent background — match that.
    private func priorityBadge(_ priority: String) -> some View {
        switch priority {
        case "medium":
            badge(
                "Medium",
                color: FynlaColor.Token.horizon500.color,
                background: FynlaColor.Token.savannah100.color
            )
        default:
            badge(
                priorityLabel(priority),
                color: FynlaColor.Token.horizon500.color,
                background: .clear
            )
        }
    }

    private func badge(_ text: String, color: Color, background: Color) -> some View {
        Text(text)
            .font(.system(size: 11, weight: .bold))
            .foregroundStyle(color)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(background)
            .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
    }

    // m-section-label — uppercase 12/700 neutral500.
    private func sectionLabel(_ text: String) -> some View {
        Text(text.uppercased())
            .font(.system(size: 12, weight: .bold))
            .kerning(0.5)
            .foregroundStyle(FynlaColor.Token.neutral500.color)
    }

    private func surplusContext(_ plan: HolisticPlan) -> String {
        var text = "From a \(MoneyFormatter.gbpWhole(plan.availableMonthlySurplus)) monthly surplus"
        if plan.goalCommitments > 0 {
            text += ", after \(MoneyFormatter.gbpWhole(plan.goalCommitments)) committed to your goals"
        }
        text += "."
        if plan.nearTermCapital > 0 {
            text += " Plus \(MoneyFormatter.gbpWhole(plan.nearTermCapital)) of capital expected in the next 12 months."
        } else if plan.nearTermCapital < 0 {
            text += " \(MoneyFormatter.gbpWhole(-plan.nearTermCapital)) of expected outgoings is kept in reserve."
        }
        return text
    }

    private func priorityLabel(_ value: String) -> String {
        switch value {
        case "high": "High"
        case "low": "Low"
        default: "Medium"
        }
    }

    private func moduleLabel(_ value: String) -> String {
        switch value {
        case "tax": "Tax"
        case "retirement": "Retirement"
        case "savings": "Savings"
        case "investment": "Investment"
        case "protection": "Protection"
        case "estate": "Estate"
        default: value.capitalized
        }
    }

    private func affordabilityLabel(_ value: String) -> String {
        switch value {
        case "partially_fits": "Partially fits"
        case "beyond_current_surplus": "Beyond your current surplus"
        default: "Fits within your surplus"
        }
    }

    private func affordabilityColor(_ value: String) -> Color {
        switch value {
        case "partially_fits": FynlaColor.Token.violet500.color
        case "beyond_current_surplus": FynlaColor.Token.neutral500.color
        default: FynlaColor.Token.spring600.color
        }
    }

    private func strategyLabel(_ value: String) -> String {
        let expansions = [
            "aa": "Annual Allowance",
            "mpaa": "Money Purchase Annual Allowance",
            "gia": "General Investment Account",
            "psa": "Personal Savings Allowance",
            "isa": "ISA",
            "cgt": "Capital Gains Tax",
            "iht": "Inheritance Tax",
            "nrb": "Nil-Rate Band",
            "rnrb": "Residence Nil-Rate Band",
            "topup": "top-up",
        ]
        return value
            .split(separator: "_")
            .map { expansions[String($0)] ?? String($0) }
            .joined(separator: " ")
            .capitalizedSentence
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded holistic plan.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("holistic-plan.offline")
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Holistic Plan",
                    subtitle: "Your whole plan, ranked against what you can afford"
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

private extension String {
    var capitalizedSentence: String {
        guard let first else { return self }
        return first.uppercased() + dropFirst()
    }
}
