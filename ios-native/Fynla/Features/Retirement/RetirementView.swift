import SwiftUI

struct RetirementView: View {
    let model: RetirementModel
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                ScreenStateView(state: .loading)
            case let .loaded(snapshot):
                content(snapshot)
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
        .navigationTitle("Retirement")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("retirement.screen")
    }

    private func content(
        _ snapshot: RetirementSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Retirement")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your projected retirement income, pensions and projections")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline { offlineNotice }
                hero(snapshot)
                pensionsCard(snapshot)
                overviewCard(snapshot)
                projectionCard(snapshot.projections?.pensionPotProjection)
                recommendationsCard(snapshot.analysis?.recommendations ?? [])
                Button("Update pensions with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "pensions",
                            addPhrase: "I'd like to add a pension.",
                            names: snapshot.pensions.map { Optional($0.name) }
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("retirement.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(_ snapshot: RetirementSnapshot) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Projected retirement income")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.xSmall) {
                FinancialValueView.money(snapshot.projectedIncome)
                    .accessibilityIdentifier("retirement.projected-income")
                Text("a year")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            Text(gapNarrative(snapshot))
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Divider()
            HStack(alignment: .top, spacing: FynlaSpacing.standard) {
                heroStat("Target income", snapshot.targetIncome.map(MoneyFormatter.gbp) ?? "—")
                let gap = snapshot.incomeGap
                heroStat(
                    gap.map { $0 <= 0 ? "Surplus" : "Shortfall" } ?? "Comparison",
                    gap.map { MoneyFormatter.gbp(abs($0)) } ?? "—"
                )
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func heroStat(_ title: String, _ value: String) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
            Text(title)
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.secondaryText)
            Text(value)
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func pensionsCard(_ snapshot: RetirementSnapshot) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.small) {
                Text("Your pensions")
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                if let limit = snapshot.index.accountLimit {
                    VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                        Text("\(snapshot.index.accountCount) of \(limit) pensions used")
                            .font(FynlaTypography.caption)
                            .foregroundStyle(
                                snapshot.isAtAccountLimit
                                    ? FynlaColor.primaryAction
                                    : FynlaColor.secondaryText
                            )
                        Button("Upgrade", action: onOpenSubscription)
                            .font(FynlaTypography.caption)
                            .foregroundStyle(FynlaColor.primaryAction)
                    }
                }
            }
            .padding(.bottom, FynlaSpacing.small)
            if snapshot.pensions.isEmpty {
                Text("No pensions recorded yet. Add a pension to see your full retirement picture.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(snapshot.pensions) { pension in
                    Button {
                        onRoute(.retirement(
                            pensionType: pension.type.rawValue,
                            id: pension.pensionID
                        ))
                    } label: {
                        HStack(alignment: .center, spacing: FynlaSpacing.medium) {
                            VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                                Text(pension.name)
                                    .font(FynlaTypography.heading)
                                    .foregroundStyle(FynlaColor.primaryText)
                                Text(pension.typeLabel)
                                    .font(FynlaTypography.caption)
                                    .foregroundStyle(FynlaColor.secondaryText)
                            }
                            Spacer()
                            VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                                Text(pension.valueLabel)
                                    .font(FynlaTypography.heading)
                                    .foregroundStyle(FynlaColor.primaryText)
                                Text("View")
                                    .font(FynlaTypography.caption)
                                    .foregroundStyle(FynlaColor.primaryAction)
                            }
                        }
                        .padding(.vertical, FynlaSpacing.medium)
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .overlay(alignment: .bottom) { Divider() }
                    .accessibilityIdentifier("retirement.pension.\(pension.id)")
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func overviewCard(_ snapshot: RetirementSnapshot) -> some View {
        detailCard("Overview") {
            detailRow("Defined Contribution pension value", MoneyFormatter.gbp(snapshot.totalDCPensionWealth))
            detailRow("Years to retirement", yearsToRetirement(snapshot).map(String.init) ?? "—")
            detailRow("Target retirement age", snapshot.index.profile?.targetRetirementAge.map(String.init) ?? "—")
        }
    }

    private func projectionCard(_ projection: RetirementPotProjection?) -> some View {
        detailCard("Pension pot projection") {
            if let projection {
                detailRow("Current pot value", money(projection.currentValue))
                detailRow("Monthly contributions", money(projection.monthlyContribution))
                detailRow("Projected at retirement", money(projection.percentile20AtRetirement))
                detailRow("Median projection", money(projection.medianAtRetirement))
                Text(projectionNote(projection))
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.top, FynlaSpacing.xSmall)
            } else {
                Text("No projection available yet.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
    }

    @ViewBuilder
    private func recommendationsCard(_ recommendations: [RetirementRecommendation]) -> some View {
        if !recommendations.isEmpty {
            detailCard("Recommended actions") {
                ForEach(Array(recommendations.enumerated()), id: \.offset) { _, recommendation in
                    VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                        Text(recommendation.title ?? recommendation.action ?? "Recommendation")
                            .font(FynlaTypography.heading)
                            .foregroundStyle(FynlaColor.primaryText)
                        if let description = recommendation.description {
                            Text(description)
                                .font(FynlaTypography.bodySmall)
                                .foregroundStyle(FynlaColor.secondaryText)
                        }
                    }
                    .padding(.vertical, FynlaSpacing.small)
                    .overlay(alignment: .bottom) { Divider() }
                }
            }
        }
    }

    private func detailCard<Content: View>(
        _ title: String,
        @ViewBuilder content: () -> Content
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            content()
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func detailRow(_ key: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.small) {
            Text(key)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Spacer()
            Text(value)
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, FynlaSpacing.xSmall)
        .overlay(alignment: .bottom) { Divider() }
    }

    private func gapNarrative(_ snapshot: RetirementSnapshot) -> String {
        guard snapshot.targetIncome != nil else {
            return "Add a target retirement income to see how your projection compares."
        }
        guard let gap = snapshot.incomeGap else {
            return "A projected income is not available yet."
        }
        if gap <= 0 {
            return "You are on track to exceed your target by \(MoneyFormatter.gbp(abs(gap))) a year."
        }
        return "You have a shortfall of \(MoneyFormatter.gbp(gap)) a year against your target."
    }

    private func yearsToRetirement(_ snapshot: RetirementSnapshot) -> Int? {
        snapshot.projections?.pensionPotProjection?.yearsToRetirement
            ?? snapshot.analysis?.yearsToRetirement
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbp) ?? "—"
    }

    private func projectionNote(_ projection: RetirementPotProjection) -> String {
        let age = projection.retirementAge.map(String.init) ?? "—"
        let years = projection.yearsToRetirement.map(String.init) ?? "—"
        let expectedReturn = projection.expectedReturn.map(MoneyFormatter.percentage) ?? "—"
        return "Projected value at retirement age \(age), based on \(years) years to retirement and an estimated \(expectedReturn) annual return. The projected figure is a conservative estimate (80% likelihood of exceeding it)."
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded retirement position.")
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
