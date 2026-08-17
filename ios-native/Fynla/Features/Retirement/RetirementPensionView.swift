import SwiftUI

// Transcribes /m's pension detail sub-page (resources/mobile/views/modules/
// RetirementPensionDetail.vue): gradient page hero, Back + Edit details
// pills, scheme identity card, dark headline hero ("a year" suffix for DB /
// State, provider or weekly sub-line), and the per-type detail row cards —
// DC scheme information + pot projection, DB benefit details, State Pension
// entitlement (35 qualifying-years fallback is /m-parity, ledger P0-6).
// Whole-pound amounts; the weekly State Pension figure keeps pence as /m.
struct RetirementPensionView: View {
    let pensionType: String
    let pensionID: Int?
    let model: RetirementModel
    let onOpenContextualFyn: (FynContextualAction) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            if let type = RetirementPensionType(rawValue: pensionType) {
                stateContent(type)
            } else {
                framed { ScreenStateView(state: .empty(message: "This pension type is unavailable.")) }
            }
        }
        .background(FynlaColor.pageBackground)
        .task(id: "\(pensionType)-\(pensionID ?? 0)") {
            await model.load()
        }
        .accessibilityIdentifier("retirement.pension.screen")
    }

    @ViewBuilder
    private func stateContent(_ type: RetirementPensionType) -> some View {
        switch model.state {
        case .idle, .loading:
            framed { DashboardLoadingView(message: "Loading this pension…") }
        case let .loaded(snapshot):
            pensionContent(type, snapshot: snapshot)
        case let .offline(previous):
            if let previous {
                pensionContent(type, snapshot: previous, offline: true)
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

    @ViewBuilder
    private func pensionContent(
        _ type: RetirementPensionType,
        snapshot: RetirementSnapshot,
        offline: Bool = false
    ) -> some View {
        switch type {
        case .dc:
            if let pensionID,
               let pension = snapshot.index.dcPensions.first(where: { $0.id == pensionID })
            {
                let planning = snapshot.projections?.planningProjection
                let product = planning?.products.first {
                    $0.resourceType == "dc_pension" && $0.resourceID == pensionID
                }
                dcContent(
                    pension,
                    planningProduct: product,
                    assumptions: planning?.assumptions,
                    offline: offline
                )
            } else {
                notFound
            }
        case .db:
            if let pensionID,
               let pension = snapshot.index.dbPensions.first(where: { $0.id == pensionID })
            {
                dbContent(pension, offline: offline)
            } else {
                notFound
            }
        case .state:
            if let pension = snapshot.index.statePension {
                statePensionContent(pension, offline: offline)
            } else {
                notFound
            }
        }
    }

    private func dcContent(
        _ pension: DCPension,
        planningProduct: RetirementProjectionProduct?,
        assumptions: RetirementProjectionAssumptions?,
        offline: Bool
    ) -> some View {
        page(
            title: pension.schemeName ?? pension.provider ?? "Pension",
            subtitle: "Defined Contribution Pension",
            offline: offline
        ) {
            MobileHeroCard(
                label: "Current fund value",
                metric: MoneyFormatter.gbpWhole(pension.currentFundValue),
                sub: pension.provider
            )
            .accessibilityIdentifier("retirement.pension.hero")

            MobileDetailCard(title: "Scheme information", rows: [
                ("Scheme name", pension.schemeName ?? "—"),
                ("Pension type", dcSchemeType(pension.pensionType ?? pension.schemeType)),
                ("Provider", pension.provider ?? "—"),
                ("Current fund value", MoneyFormatter.gbpWhole(pension.currentFundValue)),
                (
                    "Monthly contribution",
                    MoneyFormatter.gbpWhole(planningProduct?.monthlyContribution ?? pension.monthlyContribution)
                ),
                ("Retirement age", pension.retirementAge.map(String.init) ?? "—"),
            ])

            if let portfolio = pension.portfolio {
                CanonicalPortfolioView(portfolio: portfolio)
            }

            projectionCard(planningProduct, assumptions: assumptions)
        }
    }

    private func projectionCard(
        _ product: RetirementProjectionProduct?,
        assumptions: RetirementProjectionAssumptions?
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Pension pot projection".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 12)
            if let product {
                projectionRow("Current value", money(product.currentValue), divider: true)
                projectionRow("Monthly contribution", money(product.monthlyContribution), divider: true)
                projectionRow("Planning value at retirement", money(product.projectedValue), divider: true)
                projectionRow(
                    "Projected income from age \(product.commencementAge)",
                    "\(MoneyFormatter.gbpWhole(product.annualIncome)) a year",
                    divider: false
                )
                if let assumptions {
                    Text(assumptionsNote(assumptions))
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                        .lineSpacing(3)
                        .padding(.top, 12)
                        .accessibilityIdentifier("retirement.pension.projection.assumptions")
                }
            } else {
                Text("The reconciled planning projection is not available yet.")
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .lineSpacing(3)
                    .padding(.top, 12)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func projectionRow(_ key: String, _ value: String, divider: Bool) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key)
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            Spacer(minLength: 8)
            Text(value)
                .font(.system(size: 14, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, 10)
        .overlay(alignment: .bottom) {
            if divider {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
        .accessibilityElement(children: .combine)
    }

    private func dbContent(_ pension: DBPension, offline: Bool) -> some View {
        page(
            title: pension.schemeName ?? "Pension",
            subtitle: "Defined Benefit Pension",
            offline: offline
        ) {
            MobileHeroCard(
                label: "Accrued annual pension",
                metric: MoneyFormatter.gbpWhole(pension.accruedAnnualPension),
                metricSuffix: "a year"
            )
            .accessibilityIdentifier("retirement.pension.hero")

            MobileDetailCard(title: "Benefit details", rows: [
                ("Scheme name", pension.schemeName ?? "—"),
                ("Scheme type", dbSchemeType(pension.schemeType)),
                ("Accrued annual pension", MoneyFormatter.gbpWhole(pension.accruedAnnualPension)),
                ("Normal retirement age", pension.normalRetirementAge.map(String.init) ?? "—"),
                ("Lump sum entitlement", MoneyFormatter.gbpWhole(pension.lumpSumEntitlement ?? 0)),
                ("Spouse pension", MoneyFormatter.percentage(pension.spousePensionPercent ?? 0)),
            ])
        }
    }

    private func statePensionContent(_ pension: StatePension, offline: Bool) -> some View {
        let weekly = pension.annualForecast / 52
        return page(
            title: "State Pension",
            subtitle: "State Pension",
            offline: offline
        ) {
            MobileHeroCard(
                label: "Annual forecast",
                metric: MoneyFormatter.gbpWhole(pension.annualForecast),
                metricSuffix: "a year",
                sub: "\(MoneyFormatter.gbp(weekly)) a week"
            )
            .accessibilityIdentifier("retirement.pension.hero")

            MobileDetailCard(title: "Entitlement", rows: [
                ("Forecast weekly amount", "\(MoneyFormatter.gbp(weekly)) a week"),
                ("Annual forecast", MoneyFormatter.gbpWhole(pension.annualForecast)),
                // ponytail: 35-year fallback is /m-parity (ledger P0-6).
                ("Qualifying years", "\(pension.niYearsCompleted ?? 0) of \(pension.niYearsRequired ?? 35)"),
                ("State Pension age", pension.statePensionAge.map(String.init) ?? "—"),
            ])
        }
    }

    private func page<Content: View>(
        title: String,
        subtitle: String,
        offline: Bool,
        @ViewBuilder content: () -> Content
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Retirement",
                    subtitle: "Your projected retirement income, pensions and projections"
                )

                MobilePageActions(
                    onBack: { dismiss() },
                    editDetails: {
                        guard let pensionID else { return }
                        onOpenContextualFyn(
                            FynContextualActions.pension(type: pensionType, id: pensionID)
                        )
                    }
                )

                Group {
                    MobileDetailHeader(title: title, subtitle: subtitle)
                    if offline {
                        offlineNotice
                    }
                    content()
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private var notFound: some View {
        framed { ScreenStateView(state: .empty(message: "Pension not found.")) }
    }

    private func dcSchemeType(_ value: String?) -> String {
        labels(value, map: [
            "occupational": "Occupational (Workplace) Pension",
            "workplace": "Workplace Pension",
            "sipp": "Self-Invested Personal Pension",
            "personal": "Personal Pension",
            "stakeholder": "Stakeholder Pension",
        ])
    }

    private func dbSchemeType(_ value: String?) -> String {
        labels(value, map: [
            "final_salary": "Final Salary",
            "career_average": "Career Average",
            "public_sector": "Public Sector",
        ])
    }

    private func labels(_ value: String?, map: [String: String]) -> String {
        guard let value, !value.isEmpty else { return "—" }
        return map[value] ?? value
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbpWhole) ?? "—"
    }

    private func assumptionsNote(_ assumptions: RetirementProjectionAssumptions) -> String {
        "This planning value uses a \(MoneyFormatter.percentage(assumptions.sustainableWithdrawalRate.percent)) sustainable withdrawal rate, \(MoneyFormatter.percentage(assumptions.growthRatePercent)) growth, \(MoneyFormatter.percentage(assumptions.feeRatePercent)) fees (\(MoneyFormatter.percentage(assumptions.netGrowthRatePercent)) net growth), \(MoneyFormatter.percentage(assumptions.inflationRatePercent)) inflation, and this pension's recorded contributions. Figures are \(assumptions.basis); uncertainty is separate from the primary planning value."
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing the last loaded pension values.")
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
                retry: state.canRetry ? { Task { await model.load() } } : nil
            )
        }
    }
}
