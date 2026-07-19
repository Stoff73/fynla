import SwiftUI

struct ExpenditureView: View {
    let model: ExpenditureModel
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                ScreenStateView(state: .loading)
            case let .loaded(summary):
                content(summary)
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
        .navigationTitle("Expenditure")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("expenditure.screen")
    }

    private func content(
        _ summary: ExpenditureSummary,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Expenditure")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("What you spend each month")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                if offline {
                    offlineNotice
                }

                hero(summary)

                if let rows = summary.categories?.nonZeroRows,
                   !rows.isEmpty
                {
                    categoryCard(rows)
                }

                Button("Update expenditure with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "expenditure",
                            addPhrase: "I'd like to add my expenditure.",
                            names: summary.categories?.nonZeroRows.map(\.title) ?? []
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(
                    maxWidth: .infinity,
                    minHeight: FynlaSpacing.minimumInteractiveTarget
                )
                .accessibilityIdentifier("expenditure.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(_ summary: ExpenditureSummary) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Monthly expenditure")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(summary.monthly)
                .accessibilityIdentifier("expenditure.monthly")
            Text(annualText(summary.annual))
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
                .accessibilityIdentifier("expenditure.annual")
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func categoryCard(_ rows: [ExpenditureCategoryRow]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Where it goes each month")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)

            ForEach(rows) { row in
                HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.medium) {
                    Text(row.title)
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    Spacer(minLength: FynlaSpacing.small)
                    Text(MoneyFormatter.gbp(row.amount))
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                .padding(.vertical, FynlaSpacing.medium)
                .overlay(alignment: .bottom) { Divider() }
                .accessibilityElement(children: .combine)
                .accessibilityIdentifier("expenditure.category.\(row.id.rawValue)")
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func annualText(_ annual: Decimal?) -> String {
        annual.map { "\(MoneyFormatter.gbp($0)) a year" }
            ?? "Annual total unavailable"
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded expenditure.")
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
            .accessibilityIdentifier("expenditure.offline")
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
