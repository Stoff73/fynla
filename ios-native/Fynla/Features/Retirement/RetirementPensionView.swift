import SwiftUI

struct RetirementPensionView: View {
    let pensionType: String
    let pensionID: Int?
    let model: RetirementModel
    let onOpenFyn: (String) -> Void

    var body: some View {
        Group {
            if let type = RetirementPensionType(rawValue: pensionType) {
                stateContent(type)
            } else {
                ScreenStateView(state: .empty(message: "This pension type is unavailable."))
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Retirement")
        .navigationBarTitleDisplayMode(.inline)
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
            ScreenStateView(state: .loading)
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
            title: pension.displayName,
            subtitle: "Defined Contribution Pension",
            offline: offline,
            editName: pension.displayName
        ) {
            hero(
                label: "Current fund value",
                value: pension.currentFundValue,
                suffix: nil,
                subtitle: pension.provider
            )
            detailCard("Scheme information") {
                detailRow("Scheme name", pension.schemeName ?? "—")
                detailRow("Pension type", dcSchemeType(pension.pensionType ?? pension.schemeType))
                detailRow("Provider", pension.provider ?? "—")
                detailRow("Current fund value", MoneyFormatter.gbp(pension.currentFundValue))
                detailRow("Monthly contribution", MoneyFormatter.gbp(pension.monthlyContribution))
                detailRow("Retirement age", pension.retirementAge.map(String.init) ?? "—")
            }
            detailCard("Pension pot projection") {
                if model.isLoadingDCProjection {
                    LoadingView(message: "Loading projection")
                } else if let projection = model.dcProjection {
                    detailRow("Current value", money(projection.currentValue))
                    detailRow("Projected at retirement", money(projection.percentile20AtRetirement))
                    detailRow("Median projection", money(projection.medianAtRetirement))
                    Text(projectionNote(projection))
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                } else {
                    Text("No projection available for this pension.")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
            }
        }
    }

    private func dbContent(_ pension: DBPension, offline: Bool) -> some View {
        page(
            title: pension.displayName,
            subtitle: "Defined Benefit Pension",
            offline: offline,
            editName: pension.displayName
        ) {
            hero(
                label: "Accrued annual pension",
                value: pension.accruedAnnualPension,
                suffix: "a year",
                subtitle: nil
            )
            detailCard("Benefit details") {
                detailRow("Scheme name", pension.schemeName ?? "—")
                detailRow("Scheme type", dbSchemeType(pension.schemeType))
                detailRow("Accrued annual pension", MoneyFormatter.gbp(pension.accruedAnnualPension))
                detailRow("Normal retirement age", pension.normalRetirementAge.map(String.init) ?? "—")
                detailRow("Lump sum entitlement", MoneyFormatter.gbp(pension.lumpSumEntitlement ?? 0))
                detailRow("Spouse pension", MoneyFormatter.percentage(pension.spousePensionPercent ?? 0))
            }
        }
    }

    private func statePensionContent(_ pension: StatePension, offline: Bool) -> some View {
        let weekly = pension.annualForecast / 52
        return page(
            title: "State Pension",
            subtitle: "State Pension",
            offline: offline,
            editName: "State Pension"
        ) {
            hero(
                label: "Annual forecast",
                value: pension.annualForecast,
                suffix: "a year",
                subtitle: "\(MoneyFormatter.gbp(weekly)) a week"
            )
            detailCard("Entitlement") {
                detailRow("Forecast weekly amount", "\(MoneyFormatter.gbp(weekly)) a week")
                detailRow("Annual forecast", MoneyFormatter.gbp(pension.annualForecast))
                detailRow(
                    "Qualifying years",
                    "\(pension.niYearsCompleted ?? 0) of \(pension.niYearsRequired ?? 35)"
                )
                detailRow("State Pension age", pension.statePensionAge.map(String.init) ?? "—")
            }
        }
    }

    private func page<Content: View>(
        title: String,
        subtitle: String,
        offline: Bool,
        editName: String,
        @ViewBuilder content: () -> Content
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(title)
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(subtitle)
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline {
                    Text("You're offline. Showing the last loaded pension values.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                }
                content()
                Button("Update this pension with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "pensions",
                            addPhrase: "I'd like to add a pension.",
                            names: [editName]
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("retirement.pension.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
    }

    private func hero(
        label: String,
        value: Decimal,
        suffix: String?,
        subtitle: String?
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(label)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.xSmall) {
                FinancialValueView.money(value)
                if let suffix {
                    Text(suffix)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
            }
            if let subtitle {
                Text(subtitle)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
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

    private var notFound: some View {
        ScreenStateView(state: .empty(message: "Pension not found."))
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
        return map[value] ?? value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbp) ?? "—"
    }

    private func projectionNote(_ projection: RetirementPotProjection) -> String {
        let age = projection.retirementAge.map(String.init) ?? "—"
        let years = projection.yearsToRetirement.map(String.init) ?? "—"
        let expectedReturn = projection.expectedReturn.map(MoneyFormatter.percentage) ?? "—"
        return "Projected to age \(age) over \(years) years at an estimated \(expectedReturn) annual return. The projected figure is a conservative estimate (80% likelihood of exceeding it)."
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil
        )
    }
}
