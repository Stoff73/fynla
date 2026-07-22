import SwiftUI

// Transcribes /m's Tax Strategy page (resources/mobile/views/TaxStrategy.vue):
// gradient page hero, Edit details pill (generic /m prompt), personalised
// intro card, household coordination card, recommended actions with save
// chips / next-step CTAs / Mark as done, the Done group, the dark
// allowance-headroom hero and the status-coloured allowance bars. Whole-pound
// amounts as /m's formatCurrency, em-dash for null.
struct TaxStrategyView: View {
    let model: TaxStrategyModel
    // /m's personalised intro (CSJ 3.2) reads store.user — first name +
    // onboarding_completed arrive from the session user via SettingsModel.
    let firstName: String?
    let onboardingCompleted: Bool
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your tax strategy…") }
            case let .loaded(dashboard):
                content(dashboard)
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
        .accessibilityIdentifier("tax-strategy.screen")
    }

    private func content(
        _ dashboard: TaxStrategyDashboard,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Tax Strategy",
                    subtitle: dashboard.taxYear.map { "Tax year \($0)" }
                        ?? "Your allowances and tax-saving actions"
                )

                // /m's MobileChrome default Edit details pill with the generic
                // fallback prompt (TaxStrategy.vue passes no editPrompt).
                MobilePageActions(editDetails: {
                    onOpenFyn("What would you like to update?")
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    if let intro = personalisedIntro(dashboard) {
                        introCard(intro)
                    }

                    if dashboard.isHousehold && !dashboard.householdRecommendations.isEmpty {
                        householdCard(dashboard)
                    }

                    recommendationsCard(dashboard)

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

                    // m-btn mts-back — back to the dashboard action list (CSJ 3.5).
                    Button {
                        onRoute(.dashboard)
                    } label: {
                        Text("See all your actions to get more for your money")
                            .font(.system(size: 16, weight: .bold))
                            .foregroundStyle(.white)
                            .multilineTextAlignment(.center)
                            .frame(maxWidth: .infinity)
                            .padding(14)
                            .background(FynlaColor.Token.raspberry500.color)
                            .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
                    }
                    .accessibilityIdentifier("tax-strategy.back-to-dashboard")
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // mts-intro — personal line naming the user + the composed-plan saving,
    // shown once onboarding is complete (CSJ 3.2).
    private func personalisedIntro(_ dashboard: TaxStrategyDashboard) -> String? {
        guard onboardingCompleted else { return nil }
        let name = firstName ?? "there"
        let saving = dashboard.composedPlan.combinedAnnualSaving ?? 0
        if saving > 0 {
            return "Here's your personal tax strategy, \(name). From what you told us, we've found around \(MoneyFormatter.gbpWhole(saving)) a year you could keep."
        }
        return "Here's your personal tax strategy, \(name). From what you told us, here's how to make the most of your allowances."
    }

    private func introCard(_ text: String) -> some View {
        Text(text)
            .font(.system(size: 14, weight: .bold))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .lineSpacing(4)
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.eggshell500.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .stroke(FynlaColor.Token.lightGray.color, lineWidth: 1)
            )
            .accessibilityIdentifier("tax-strategy.intro")
    }

    // mts-household — eggshell card: heading label, CGT/IHT qualification
    // intro, then the household recommendations (no action row).
    private func householdCard(_ dashboard: TaxStrategyDashboard) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionLabel(
                dashboard.calculationMode == "single_earner_couple"
                    ? "Move assets to use spouse allowances"
                    : "Coordinate as a household"
            )
            .padding(.bottom, 8)
            Text(householdIntro(dashboard.calculationMode))
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.neutral600.color)
                .lineSpacing(3)
                .padding(.bottom, 12)
            ForEach(dashboard.householdRecommendations) { recommendation in
                recommendationCard(
                    recommendation,
                    showActions: false,
                    isLast: recommendation.id == dashboard.householdRecommendations.last?.id
                )
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.Token.eggshell500.color)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .stroke(FynlaColor.Token.lightGray.color, lineWidth: 1)
        )
    }

    // Recommended actions + the Done group (completed items stay visible here;
    // the dashboard replaces them).
    private func recommendationsCard(_ dashboard: TaxStrategyDashboard) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionLabel("Recommended actions")
                .padding(.bottom, 8)
            if dashboard.openRecommendations.isEmpty {
                Text(
                    dashboard.headroomCount > 0
                        ? "No additional recommended actions are available from the information on file right now. Your unused allowances are shown below."
                        : "Your allowances are well-utilised — nothing to act on right now."
                )
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            } else {
                ForEach(dashboard.openRecommendations) { recommendation in
                    recommendationCard(
                        recommendation,
                        showActions: true,
                        isLast: recommendation.id == dashboard.openRecommendations.last?.id
                    )
                }
            }
            // Native-only write-error surface: /m fails silently and leaves
            // the item open; the audit keeps this visible instead.
            if model.completionFailed {
                Text("That action could not be marked done. Nothing was changed.")
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    .padding(.top, 8)
            }
            if !dashboard.completedRecommendations.isEmpty {
                sectionLabel("Done")
                    .padding(.top, 12)
                    .padding(.bottom, 8)
                ForEach(dashboard.completedRecommendations) { recommendation in
                    doneCard(
                        recommendation,
                        isLast: recommendation.id == dashboard.completedRecommendations.last?.id
                    )
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mts-rec — bordered sub-card: Watch-out flag, title + Saves chip, body,
    // then the CTA / Mark as done / adviser foot under a hairline.
    private func recommendationCard(
        _ recommendation: TaxRecommendation,
        showActions: Bool,
        isLast: Bool
    ) -> some View {
        let isWarning = recommendation.category == "warning"
        return VStack(alignment: .leading, spacing: 0) {
            if isWarning {
                Text("Watch out".uppercased())
                    .font(.system(size: 11, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(FynlaColor.Token.violet500.color)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 2)
                    .background(FynlaColor.Token.violet500.color.opacity(0.12))
                    .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
                    .padding(.bottom, 6)
            }
            HStack(alignment: .top, spacing: 12) {
                Text(recommendation.title)
                    .font(.system(size: 15, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer(minLength: 8)
                if let saving = recommendation.estimatedAnnualTaxSaved, saving > 0 {
                    VStack(alignment: .trailing, spacing: 0) {
                        Text("Saves")
                            .font(.system(size: 11))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                        Text(MoneyFormatter.gbpWhole(saving))
                            .font(.system(size: 18, weight: .black))
                            .foregroundStyle(FynlaColor.Token.spring600.color)
                        Text("a year")
                            .font(.system(size: 11))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                    }
                }
            }
            if let description = recommendation.description {
                Text(description)
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.neutral600.color)
                    .lineSpacing(3)
                    .padding(.top, 6)
            }
            if showActions {
                HStack(spacing: 12) {
                    if let route = nextRoute(recommendation.type) {
                        Button(nextLabel(route)) { onRoute(route) }
                            .font(.system(size: 13, weight: .bold))
                            .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    }
                    Button {
                        Task { await model.markDone(recommendation) }
                    } label: {
                        // /m's --spring-700 is undefined, so the pill text
                        // renders in the inherited body colour (horizon-500)
                        // with the spring border — match that live rendering.
                        Text("Mark as done")
                            .font(.system(size: 12, weight: .bold))
                            .foregroundStyle(FynlaColor.Token.horizon500.color)
                            .padding(.vertical, 6)
                            .padding(.horizontal, 12)
                            .background(Color.white)
                            .clipShape(Capsule())
                            .overlay(
                                Capsule().stroke(FynlaColor.Token.spring500.color, lineWidth: 1)
                            )
                    }
                    .disabled(model.markingRecommendationID != nil)
                    .opacity(
                        model.markingRecommendationID == recommendation.recommendationID
                            ? 0.6 : 1
                    )
                    .accessibilityIdentifier("tax-strategy.mark-done.\(recommendation.id)")
                    if recommendation.requiresAdvice == true {
                        Text("Speak to an adviser")
                            .font(.system(size: 11, weight: .bold))
                            .foregroundStyle(FynlaColor.Token.violet500.color)
                            .padding(.horizontal, 8)
                            .padding(.vertical, 2)
                            .background(FynlaColor.Token.violet500.color.opacity(0.12))
                            .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
                    }
                    Spacer(minLength: 0)
                }
                .padding(.top, 12)
                .overlay(alignment: .top) {
                    FynlaColor.Token.horizon100.color.frame(height: 1)
                }
                .padding(.top, 12)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .stroke(
                    isWarning
                        ? FynlaColor.Token.violet500.color
                        : FynlaColor.Token.lightGray.color,
                    lineWidth: 1
                )
        )
        .padding(.bottom, isLast ? 0 : 12)
    }

    // mts-rec--done — faded card: title + "Done dd/mm/yyyy" tag.
    private func doneCard(_ recommendation: TaxRecommendation, isLast: Bool) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(recommendation.title)
                .font(.system(size: 15, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            Spacer(minLength: 8)
            Text(doneLabel(recommendation.completedAt))
                .font(.system(size: 12, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 12, style: .continuous)
                .stroke(FynlaColor.Token.lightGray.color, lineWidth: 1)
        )
        .opacity(0.75)
        .padding(.bottom, isLast ? 0 : 12)
    }

    // m-hero — dark allowance-position card: headroom count + the two
    // qualification lines.
    private func headroomHero(_ dashboard: TaxStrategyDashboard) -> some View {
        MobileHeroCard(
            label: "Allowances with potential headroom",
            metric: String(dashboard.headroomCount),
            sub: "Review each allowance below. The amounts have different tax meanings and are not additive.",
            metricColor: FynlaColor.Token.spring600.color
        ) {
            Text("Income Tax bands use England, Wales and Northern Ireland rates. Scottish Income Tax bands are not modelled in this journey.")
                .font(.system(size: 14))
                .foregroundStyle(.white.opacity(0.9))
        }
        .accessibilityIdentifier("tax-strategy.headroom")
    }

    // mts-allow — per-allowance rows: label/cap head, status bar,
    // remaining/used foot, hairline-separated.
    private func allowanceCard(
        title: String,
        allowances: [TaxAllowance]
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionLabel(title)
                .padding(.bottom, 4)
            ForEach(allowances) { allowance in
                VStack(alignment: .leading, spacing: 6) {
                    HStack(alignment: .firstTextBaseline, spacing: 12) {
                        Text(allowance.label)
                            .font(.system(size: 14, weight: .bold))
                            .foregroundStyle(FynlaColor.Token.horizon500.color)
                        Spacer(minLength: 8)
                        Text("of \(money(allowance.amount))")
                            .font(.system(size: 12))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                    }
                    allowanceBar(allowance)
                    HStack(alignment: .firstTextBaseline, spacing: 12) {
                        Text(allowance.remainingLabel)
                            .font(.system(size: 12, weight: .bold))
                            .foregroundStyle(remainingColor(allowance.status))
                        Spacer(minLength: 8)
                        if allowance.available != false, allowance.known != false {
                            Text("\(money(allowance.used)) used")
                                .font(.system(size: 12))
                                .foregroundStyle(FynlaColor.Token.neutral500.color)
                        }
                    }
                }
                .padding(.vertical, 12)
                .overlay(alignment: .bottom) {
                    if allowance.id != allowances.last?.id {
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

    // mts-bar — 6pt horizon200 track; fill width min(pct, 100)%; muted status
    // draws no fill on /m (no mts-bar__fill--muted rule).
    private func allowanceBar(_ allowance: TaxAllowance) -> some View {
        GeometryReader { proxy in
            ZStack(alignment: .leading) {
                Capsule().fill(FynlaColor.Token.horizon200.color)
                if let fill = barFillColor(allowance.status) {
                    Capsule()
                        .fill(fill)
                        .frame(width: proxy.size.width * barFraction(allowance.utilisationPercentage))
                }
            }
        }
        .frame(height: 6)
    }

    private func barFraction(_ percentage: Decimal?) -> Double {
        min(max(NSDecimalNumber(decimal: percentage ?? 0).doubleValue / 100, 0), 1)
    }

    private func barFillColor(_ status: String?) -> Color? {
        switch status {
        case "spring": FynlaColor.Token.spring500.color
        case "violet": FynlaColor.Token.violet500.color
        case "raspberry": FynlaColor.Token.raspberry500.color
        default: nil
        }
    }

    private func remainingColor(_ status: String?) -> Color {
        switch status {
        case "spring": FynlaColor.Token.spring600.color
        case "violet": FynlaColor.Token.violet500.color
        case "raspberry": FynlaColor.Token.raspberry500.color
        default: FynlaColor.Token.neutral500.color
        }
    }

    // m-section-label — uppercase 12/700 neutral500.
    private func sectionLabel(_ text: String) -> some View {
        Text(text.uppercased())
            .font(.system(size: 12, weight: .bold))
            .kerning(0.5)
            .foregroundStyle(FynlaColor.Token.neutral500.color)
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbpWhole) ?? "—"
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
        case .investment: "Open investment"
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

    // /m: `Done ${d.toLocaleDateString('en-GB')}` — dd/mm/yyyy, bare "Done"
    // when the timestamp doesn't parse. The API emits toIso8601String()
    // (offset, no fraction); the fractional fallback tolerates format drift.
    private func doneLabel(_ value: String?) -> String {
        guard let value else { return "Done" }
        let plain = ISO8601DateFormatter()
        let fractional = ISO8601DateFormatter()
        fractional.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        guard let date = plain.date(from: value) ?? fractional.date(from: value) else {
            return "Done"
        }
        let output = DateFormatter()
        output.locale = Locale(identifier: "en_GB")
        output.timeZone = TimeZone(secondsFromGMT: 0)
        output.dateFormat = "dd/MM/yyyy"
        return "Done \(output.string(from: date))"
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded tax strategy.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("tax-strategy.offline")
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Tax Strategy",
                    subtitle: "Your allowances and tax-saving actions"
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
