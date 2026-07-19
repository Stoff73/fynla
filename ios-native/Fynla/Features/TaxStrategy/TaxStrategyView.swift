import SwiftUI

struct TaxStrategyView: View {
    let model: TaxStrategyModel
    let onRoute: (AppRoute) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                ScreenStateView(state: .loading)
            case let .loaded(dashboard):
                content(dashboard)
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
        .navigationTitle("Tax Strategy")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("tax-strategy.screen")
    }

    private func content(
        _ dashboard: TaxStrategyDashboard,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Tax Strategy")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(dashboard.taxYear.map { "Tax year \($0)" } ?? "Your allowances and tax-saving actions")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline { offlineNotice }
                if let saving = dashboard.composedPlan.combinedAnnualSaving, saving > 0 {
                    Text("Your composed tax plan has identified around \(MoneyFormatter.gbp(saving)) a year you could keep.")
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                        .padding(FynlaSpacing.standard)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .background(FynlaColor.surface)
                        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                }
                if dashboard.isHousehold && !dashboard.householdRecommendations.isEmpty {
                    householdCard(dashboard)
                }
                recommendationCard(dashboard)
                headroomHero(dashboard)
                allowanceCard(
                    title: dashboard.isHousehold ? "Your allowances" : "Allowances",
                    allowances: dashboard.userAllowances
                )
                if dashboard.isHousehold,
                   let spouse = dashboard.spouseAllowances,
                   !spouse.isEmpty
                {
                    allowanceCard(title: "Your spouse's allowances", allowances: spouse)
                }
                Button("See all your actions to get more for your money") {
                    onRoute(.dashboard)
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func householdCard(_ dashboard: TaxStrategyDashboard) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(
                dashboard.calculationMode == "single_earner_couple"
                    ? "Move assets to use spouse allowances"
                    : "Coordinate as a household"
            )
            .font(FynlaTypography.sectionTitle)
            .foregroundStyle(FynlaColor.primaryText)
            Text(householdIntro(dashboard.calculationMode))
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            ForEach(dashboard.householdRecommendations) { recommendation in
                recommendationSummary(recommendation, showActions: false)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func recommendationCard(_ dashboard: TaxStrategyDashboard) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Recommended actions")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            if dashboard.openRecommendations.isEmpty {
                Text(
                    dashboard.headroomCount > 0
                        ? "No additional recommended actions are available from the information on file right now. Your unused allowances are shown below."
                        : "Your allowances are well-utilised — nothing to act on right now."
                )
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
            } else {
                ForEach(dashboard.openRecommendations) { recommendation in
                    recommendationSummary(recommendation, showActions: true)
                }
            }
            if model.completionFailed {
                Text("That action could not be marked done. Nothing was changed.")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryAction)
            }
            if !dashboard.completedRecommendations.isEmpty {
                Text("Done")
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                    .padding(.top, FynlaSpacing.small)
                ForEach(dashboard.completedRecommendations) { recommendation in
                    HStack(alignment: .firstTextBaseline) {
                        Text(recommendation.title)
                            .font(FynlaTypography.heading)
                            .foregroundStyle(FynlaColor.primaryText)
                        Spacer()
                        Text(doneLabel(recommendation.completedAt))
                            .font(FynlaTypography.caption)
                            .foregroundStyle(FynlaColor.focus)
                    }
                    .padding(.vertical, FynlaSpacing.small)
                    .overlay(alignment: .bottom) { Divider() }
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func recommendationSummary(
        _ recommendation: TaxRecommendation,
        showActions: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
            if recommendation.category == "warning" {
                Text("Watch out")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.focus)
            }
            HStack(alignment: .top, spacing: FynlaSpacing.small) {
                Text(recommendation.title)
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                if let saving = recommendation.estimatedAnnualTaxSaved, saving > 0 {
                    VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                        Text("Saves")
                        Text(MoneyFormatter.gbp(saving))
                            .font(FynlaTypography.heading)
                        Text("a year")
                    }
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.focus)
                }
            }
            if let description = recommendation.description {
                Text(description)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            if showActions {
                HStack(spacing: FynlaSpacing.medium) {
                    if let route = nextRoute(recommendation.type) {
                        Button(nextLabel(route)) { onRoute(route) }
                            .font(FynlaTypography.button)
                            .foregroundStyle(FynlaColor.primaryAction)
                    }
                    Button("Mark as done") {
                        Task { await model.markDone(recommendation) }
                    }
                    .font(FynlaTypography.button)
                    .foregroundStyle(FynlaColor.focus)
                    .disabled(model.markingRecommendationID != nil)
                    if recommendation.requiresAdvice == true {
                        Text("Speak to an adviser")
                            .font(FynlaTypography.caption)
                            .foregroundStyle(FynlaColor.focus)
                    }
                }
                .padding(.top, FynlaSpacing.xSmall)
            }
        }
        .padding(.vertical, FynlaSpacing.medium)
        .overlay(alignment: .bottom) { Divider() }
    }

    private func headroomHero(_ dashboard: TaxStrategyDashboard) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Allowances with potential headroom")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Text(String(dashboard.headroomCount))
                .font(FynlaTypography.pageTitle)
                .foregroundStyle(FynlaColor.focus)
                .accessibilityIdentifier("tax-strategy.headroom")
            Text("Review each allowance below. The amounts have different tax meanings and are not additive.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Text("Income Tax bands use England, Wales and Northern Ireland rates. Scottish Income Tax bands are not modelled in this journey.")
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func allowanceCard(
        title: String,
        allowances: [TaxAllowance]
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)
            ForEach(allowances) { allowance in
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    HStack(alignment: .firstTextBaseline) {
                        Text(allowance.label)
                            .font(FynlaTypography.heading)
                            .foregroundStyle(FynlaColor.primaryText)
                        Spacer()
                        Text("of \(money(allowance.amount))")
                            .font(FynlaTypography.caption)
                            .foregroundStyle(FynlaColor.secondaryText)
                    }
                    ProgressView(value: progress(allowance.utilisationPercentage))
                        .tint(FynlaColor.focus)
                    HStack(alignment: .firstTextBaseline) {
                        Text(allowance.remainingLabel)
                            .font(FynlaTypography.bodySmall)
                            .foregroundStyle(FynlaColor.primaryText)
                        Spacer()
                        if allowance.available != false,
                           allowance.known != false,
                           let used = allowance.used
                        {
                            Text("\(MoneyFormatter.gbp(used)) used")
                                .font(FynlaTypography.caption)
                                .foregroundStyle(FynlaColor.secondaryText)
                        }
                    }
                }
                .padding(.vertical, FynlaSpacing.medium)
                .overlay(alignment: .bottom) { Divider() }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func progress(_ percentage: Decimal?) -> Double {
        min(max(NSDecimalNumber(decimal: percentage ?? 0).doubleValue / 100, 0), 1)
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbp) ?? "—"
    }

    private func nextRoute(_ type: String) -> AppRoute? {
        switch type {
        case "pa_taper_rescue", "additional_rate_avoidance", "pension_aa_carry_forward", "salary_sacrifice_ni":
            .retirement(pensionType: nil, id: nil)
        case "isa_topup_vs_psa":
            .savings(accountID: nil)
        case "bed_and_isa", "dividend_allowance":
            .investment(accountID: nil)
        default:
            nil
        }
    }

    private func nextLabel(_ route: AppRoute) -> String {
        switch route {
        case .retirement: "Open retirement"
        case .savings: "Open savings"
        case .investment: "Open investments"
        default: "Open"
        }
    }

    private func householdIntro(_ mode: String?) -> String {
        let qualification = "Transfers between eligible spouses or civil partners can usually be made without an immediate Capital Gains Tax charge, but the recipient normally inherits the original acquisition cost and may pay tax on a later disposal. Inheritance Tax spouse-exemption conditions apply."
        if mode == "single_earner_couple" {
            return "Move assets into your spouse's name to use their unused allowances. \(qualification)"
        }
        return "Coordinate as a household. \(qualification)"
    }

    private func doneLabel(_ value: String?) -> String {
        guard let value,
              let date = ISO8601DateFormatter().date(from: value)
        else {
            return "Done"
        }
        return "Done \(MoneyFormatter.ukDate(date))"
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded tax strategy.")
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
