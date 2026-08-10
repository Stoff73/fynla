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
            if pensionType == RetirementPensionType.dc.rawValue, let pensionID {
                await model.loadDCPensionProjection(id: pensionID)
            }
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
                dcContent(pension, offline: offline)
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

    private func dcContent(_ pension: DCPension, offline: Bool) -> some View {
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
                ("Monthly contribution", MoneyFormatter.gbpWhole(pension.monthlyContribution)),
                ("Retirement age", pension.retirementAge.map(String.init) ?? "—"),
            ])

            projectionCard
        }
    }

    private var projectionCard: some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Pension pot projection".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 12)
            if model.isLoadingDCProjection {
                Text("Loading projection…")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            } else if let projection = model.dcProjection {
                projectionRow("Current value", money(projection.currentValue), divider: true)
                projectionRow("Projected at retirement", money(projection.percentile20AtRetirement), divider: true)
                projectionRow("Median projection", money(projection.medianAtRetirement), divider: false)
                Text(projectionNote(projection))
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .lineSpacing(3)
                    .padding(.top, 12)
            } else {
                Text("No projection available for this pension.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
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

    private func projectionNote(_ projection: RetirementPotProjection) -> String {
        let age = projection.retirementAge.map(String.init) ?? "—"
        let years = projection.yearsToRetirement.map(String.init) ?? "—"
        let expectedReturn = projection.expectedReturn.map(MoneyFormatter.percentage) ?? "—"
        return "Projected to age \(age) over \(years) years at an estimated \(expectedReturn) annual return. The projected figure is a conservative estimate (80% likelihood of exceeding it)."
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
