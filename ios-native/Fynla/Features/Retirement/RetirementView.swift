import SwiftUI

// Transcribes /m's Retirement page (resources/mobile/views/modules/
// Retirement.vue): gradient page hero, Edit details pill (rich /m edit
// prompt), dark hero card with the projected income, gap narrative and the
// target/surplus split, pension rows with the cap head and Upgrade, Overview
// and projection detail rows with /m's age-source note, and bordered
// recommendation cards. Whole-pound amounts as /m's formatCurrency.
struct RetirementView: View {
    let model: RetirementModel
    let onRoute: (AppRoute) -> Void
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your retirement position…") }
            case let .loaded(snapshot):
                content(snapshot)
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
        .accessibilityIdentifier("retirement.screen")
    }

    private func content(
        _ snapshot: RetirementSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Retirement",
                    subtitle: "Your projected retirement income, pensions and projections"
                )

                MobilePageActions(actionTitle: "Add pension", editDetails: {
                    onOpenContextualFyn(
                        FynContextualActions.retirementOverview(
                            hasPensions: !snapshot.pensions.isEmpty
                        )
                    )
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    heroCard(snapshot)
                    pensionsCard(snapshot)
                    overviewCard(snapshot)
                    projectionCard(snapshot.projections)
                    recommendationsCard(snapshot.analysis?.recommendations ?? [])
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero: dark card with the projected income, gap narrative and the
    // target/surplus split (mr-hero-split).
    private func heroCard(_ snapshot: RetirementSnapshot) -> some View {
        MobileHeroCard(
            label: "Projected retirement income",
            metric: snapshot.projectedIncome.map(MoneyFormatter.gbpWhole) ?? "—",
            metricSuffix: "a year",
            sub: gapNarrative(snapshot)
        ) {
            HStack(alignment: .top, spacing: 16) {
                heroStat(
                    "Target income",
                    snapshot.targetIncome.map(MoneyFormatter.gbpWhole) ?? "—",
                    tone: .white
                )
                let gap = snapshot.incomeGap
                heroStat(
                    gap.map { $0 <= 0 ? "Surplus" : "Shortfall" } ?? "Comparison",
                    gap.map { MoneyFormatter.gbpWhole(abs($0)) } ?? "—",
                    tone: gap.map {
                        $0 <= 0
                            ? FynlaColor.Token.spring400.color
                            : FynlaColor.Token.raspberry300.color
                    } ?? .white
                )
            }
            .padding(.top, 16)
            .overlay(alignment: .top) {
                FynlaColor.Token.horizon400.color.frame(height: 1)
            }
            .padding(.top, 16)
        }
        .accessibilityIdentifier("retirement.projected-income")
    }

    // mr-hero-stat: light cap + white 18/900 value (toned for surplus/shortfall).
    private func heroStat(_ title: String, _ value: String, tone: Color) -> some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(title)
                .font(.system(size: 12))
                .foregroundStyle(.white.opacity(0.65))
            Text(value)
                .font(.system(size: 18, weight: .black))
                .foregroundStyle(tone)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func pensionsCard(_ snapshot: RetirementSnapshot) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text("Your pensions".uppercased())
                    .font(.system(size: 12, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                Spacer()
                if let limit = snapshot.index.accountLimit {
                    HStack(spacing: 8) {
                        Text("\(snapshot.index.accountCount) of \(limit) pensions used")
                            .font(.system(size: 12, weight: .bold))
                            .foregroundStyle(
                                snapshot.isAtAccountLimit
                                    ? FynlaColor.Token.raspberry500.color
                                    : FynlaColor.Token.neutral500.color
                            )
                        Button {
                            onOpenSubscription()
                        } label: {
                            Text("Upgrade".uppercased())
                                .font(.system(size: 12, weight: .bold))
                                .kerning(0.5)
                                .foregroundStyle(FynlaColor.Token.raspberry500.color)
                        }
                        .accessibilityIdentifier("retirement.upgrade")
                    }
                }
            }
            .padding(.bottom, 6)

            if snapshot.pensions.isEmpty {
                Text("No pensions recorded yet. Add a pension to see your full retirement picture.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.vertical, 8)
            } else {
                ForEach(snapshot.pensions) { pension in
                    pensionRow(
                        pension,
                        showsDivider: pension.id != snapshot.pensions.last?.id
                    )
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mr-pension: name/type left, value + VIEW right.
    private func pensionRow(
        _ pension: RetirementPensionListItem,
        showsDivider: Bool
    ) -> some View {
        Button {
            onRoute(.retirement(
                pensionType: pension.type.rawValue,
                id: pension.pensionID
            ))
        } label: {
            HStack(alignment: .center, spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(pension.name)
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text(pension.typeLabel)
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
                Spacer(minLength: 8)
                VStack(alignment: .trailing, spacing: 2) {
                    Text(pension.valueLabel)
                        .font(.system(size: 14, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text("View".uppercased())
                        .font(.system(size: 11, weight: .bold))
                        .kerning(0.5)
                        .foregroundStyle(FynlaColor.Token.raspberry500.color)
                }
            }
            .padding(.vertical, 14)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
        .accessibilityIdentifier("retirement.pension.\(pension.id)")
    }

    private func overviewCard(_ snapshot: RetirementSnapshot) -> some View {
        detailCard("Overview") {
            detailRow(
                "Defined Contribution pension value",
                MoneyFormatter.gbpWhole(snapshot.totalDCPensionWealth)
            )
            detailRow(
                "Years to retirement",
                yearsToRetirement(snapshot).map(String.init) ?? "—"
            )
            detailRow(
                "Target retirement age",
                snapshot.index.profile?.targetRetirementAge.map(String.init) ?? "—",
                showsDivider: false
            )
        }
    }

    private func projectionCard(_ projections: RetirementProjections?) -> some View {
        detailCard("Pension pot projection") {
            if let projection = projections?.pensionPotProjection {
                detailRow("Current pot value", money(projection.currentValue))
                detailRow("Monthly contributions", money(projection.monthlyContribution))
                detailRow("Projected at retirement", money(projection.percentile20AtRetirement))
                detailRow("Median projection", money(projection.medianAtRetirement), showsDivider: false)
                Text(projectionNote(projection))
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .lineSpacing(3)
                    .padding(.top, 12)
            } else if projections == nil {
                // The projections fetch failed — /m's copy for this state.
                Text("Projections are not available right now.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .accessibilityIdentifier("retirement.projections-unavailable")
            } else {
                Text("No projection available yet.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }
        }
    }

    // mr-rec: bordered recommendation cards.
    @ViewBuilder
    private func recommendationsCard(_ recommendations: [RetirementRecommendation]) -> some View {
        if !recommendations.isEmpty {
            VStack(alignment: .leading, spacing: 10) {
                Text("Recommended actions".uppercased())
                    .font(.system(size: 12, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(FynlaColor.Token.neutral500.color)

                ForEach(Array(recommendations.enumerated()), id: \.offset) { _, recommendation in
                    VStack(alignment: .leading, spacing: 4) {
                        Text(recommendation.title ?? recommendation.action ?? "Recommendation")
                            .font(.system(size: 14, weight: .bold))
                            .foregroundStyle(FynlaColor.Token.horizon500.color)
                        if let description = recommendation.description {
                            Text(description)
                                .font(.system(size: 13))
                                .foregroundStyle(FynlaColor.Token.neutral600.color)
                                .lineSpacing(3)
                        }
                    }
                    .padding(14)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .overlay(
                        RoundedRectangle(cornerRadius: 12, style: .continuous)
                            .stroke(FynlaColor.Token.lightGray.color, lineWidth: 1)
                    )
                }
            }
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        }
    }

    private func detailCard<Content: View>(
        _ title: String,
        @ViewBuilder content: () -> Content
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(title.uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)
            content()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // m-detail-row: key 14 neutral, value 14/700 horizon, light hairline.
    private func detailRow(
        _ key: String,
        _ value: String,
        showsDivider: Bool = true
    ) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key)
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral600.color)
            Spacer()
            Text(value)
                .font(.system(size: 14, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, 10)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
    }

    private func gapNarrative(_ snapshot: RetirementSnapshot) -> String {
        guard snapshot.targetIncome != nil else {
            return "Add a target retirement income to see how your projection compares."
        }
        guard let gap = snapshot.incomeGap else {
            return "A projected income is not available yet."
        }
        if gap <= 0 {
            return "You are on track to exceed your target by \(MoneyFormatter.gbpWhole(abs(gap))) a year."
        }
        return "You have a shortfall of \(MoneyFormatter.gbpWhole(gap)) a year against your target."
    }

    private func yearsToRetirement(_ snapshot: RetirementSnapshot) -> Int? {
        snapshot.projections?.pensionPotProjection?.yearsToRetirement
            ?? snapshot.analysis?.yearsToRetirement
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbpWhole) ?? "—"
    }

    // /m's projection note with the age-source phrasing and the assumed
    // current-age sentence.
    private func projectionNote(_ projection: RetirementPotProjection) -> String {
        let ageLabel: String = switch projection.retirementAgeSource {
        case "user_profile", "retirement_profile": "your target retirement age"
        case "pension": "the retirement age recorded on your pension"
        default: "an assumed retirement age"
        }
        let age = projection.retirementAge.map(String.init) ?? "—"
        let years = projection.yearsToRetirement.map(String.init) ?? "—"
        let expectedReturn = projection.expectedReturn
            .map { NSDecimalNumber(decimal: $0).doubleValue }
            .map { String(format: "%g", $0) } ?? "—"
        var note = "Projected value at \(ageLabel) \(age) based on \(years) years to retirement and an estimated \(expectedReturn)% annual return. The projected figure is a conservative estimate (80% likelihood of exceeding it)."
        if projection.currentAgeSource == "assumed", let currentAge = projection.currentAge {
            note += " This projection uses an assumed current age of \(currentAge)."
        }
        return note
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded retirement position.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("retirement.offline")
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Retirement",
                    subtitle: "Your projected retirement income, pensions and projections"
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
