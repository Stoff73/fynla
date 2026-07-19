import SwiftUI

struct HolisticPlanView: View {
    let model: HolisticPlanModel
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                ScreenStateView(state: .loading)
            case let .loaded(plan):
                content(plan)
            case let .offline(previous):
                if let previous { content(previous, offline: true) }
                else { stateView(.offline) }
            case .unauthenticated:
                stateView(.unauthenticated)
            case let .upgradeRequired(message):
                stateView(.upgradeRequired(message: message))
            case let .failed(requestID):
                stateView(.failed(requestID: requestID))
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Holistic Plan")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("holistic-plan.screen")
    }

    private func content(
        _ plan: HolisticPlan,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Holistic Plan")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your whole plan, ranked against what you can afford")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline { offlineNotice }
                surplusHero(plan)
                rankedActions(plan.items)
                if !plan.locked.isEmpty {
                    lockedStrategies(plan.locked)
                }
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func surplusHero(_ plan: HolisticPlan) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Left to put to work each month")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Text(MoneyFormatter.gbp(plan.effectiveSurplus))
                .font(FynlaTypography.pageTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .accessibilityIdentifier("holistic-plan.effective-surplus")
            Text(surplusContext(plan))
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func rankedActions(_ items: [HolisticPlanItem]) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Your ranked actions")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            if items.isEmpty {
                Text("No cross-module actions just yet. Add more detail to your modules to build your plan.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
            } else {
                ForEach(items) { item in
                    actionCard(item)
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func actionCard(_ item: HolisticPlanItem) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            HStack(alignment: .top, spacing: FynlaSpacing.small) {
                HStack(spacing: FynlaSpacing.xSmall) {
                    badge(priorityLabel(item.priority))
                    badge(moduleLabel(item.module))
                }
                Spacer()
                if let cost = item.requiredMonthlyCost, cost > 0 {
                    Text("\(MoneyFormatter.gbp(cost))/mo")
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                }
            }
            Text(item.title)
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
            if let description = item.description, !description.isEmpty {
                Text(description)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            if let conflict = item.conflictNote, !conflict.isEmpty {
                Text(conflict)
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.primaryAction)
            }
            Text(affordabilityLabel(item.affordability))
                .font(FynlaTypography.button)
                .foregroundStyle(affordabilityColor(item.affordability))
        }
        .padding(FynlaSpacing.medium)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(actionBackground(item.affordability))
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .overlay {
            RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius)
                .stroke(FynlaColor.Token.horizon200.color, lineWidth: 1)
        }
        .accessibilityIdentifier("holistic-plan.action.\(item.id)")
    }

    private func lockedStrategies(_ strategies: [HolisticLockedStrategy]) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Unlock more of your plan")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text("These strategies need a little more detail before we can rank them.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            ForEach(strategies) { strategy in
                Text("\(moduleLabel(strategy.module)): \(strategyLabel(strategy.strategyType))")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryText)
                    .padding(.vertical, FynlaSpacing.xSmall)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func badge(_ text: String) -> some View {
        Text(text)
            .font(FynlaTypography.caption)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(.horizontal, FynlaSpacing.small)
            .padding(.vertical, FynlaSpacing.micro)
            .background(FynlaColor.Token.horizon200.color.opacity(0.35))
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.small))
    }

    private func surplusContext(_ plan: HolisticPlan) -> String {
        var text = "From a \(MoneyFormatter.gbp(plan.availableMonthlySurplus)) monthly surplus"
        if plan.goalCommitments > 0 {
            text += ", after \(MoneyFormatter.gbp(plan.goalCommitments)) committed to your goals"
        }
        text += "."
        if plan.nearTermCapital > 0 {
            text += " Plus \(MoneyFormatter.gbp(plan.nearTermCapital)) of capital expected in the next 12 months."
        } else if plan.nearTermCapital < 0 {
            text += " \(MoneyFormatter.gbp(-plan.nearTermCapital)) of expected outgoings is kept in reserve."
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
        case "partially_fits": FynlaColor.primaryAction
        case "beyond_current_surplus": FynlaColor.secondaryText
        default: FynlaColor.focus
        }
    }

    private func actionBackground(_ value: String) -> Color {
        value == "beyond_current_surplus"
            ? FynlaColor.Token.savannah100.color
            : FynlaColor.surface
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
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}

private extension String {
    var capitalizedSentence: String {
        guard let first else { return self }
        return first.uppercased() + dropFirst()
    }
}
