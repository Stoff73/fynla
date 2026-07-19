import SwiftUI

struct IncomeView: View {
    let model: IncomeModel
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
        .navigationTitle("Income")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("income.screen")
    }

    private func content(
        _ summary: IncomeSummary,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Income")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your income")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                if offline {
                    offlineNotice
                }

                hero(summary.user.total)
                incomeCard(
                    title: "Your income",
                    emptyMessage: "No income recorded yet.",
                    sources: summary.user
                )

                if let spouse = summary.spouse {
                    incomeCard(
                        title: "Your spouse's income",
                        emptyMessage: "No spouse income recorded yet.",
                        sources: spouse
                    )
                }

                Button("Update income with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "income",
                            addPhrase: "I'd like to add my income.",
                            names: summary.user.nonZeroSources.map(\.title)
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(
                    maxWidth: .infinity,
                    minHeight: FynlaSpacing.minimumInteractiveTarget
                )
                .accessibilityIdentifier("income.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(_ total: Decimal) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Your total annual income")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(total)
                .accessibilityIdentifier("income.total")
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func incomeCard(
        title: String,
        emptyMessage: String,
        sources: IncomeSources
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)

            if sources.nonZeroSources.isEmpty {
                Text(emptyMessage)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(sources.nonZeroSources) { row in
                    incomeRow(row)
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func incomeRow(_ row: IncomeSourceRow) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.medium) {
            VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                Text(row.title)
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                if let detail = row.detail {
                    Text(detail)
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
            }
            Spacer(minLength: FynlaSpacing.small)
            Text(MoneyFormatter.gbp(row.amount))
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, FynlaSpacing.medium)
        .overlay(alignment: .bottom) {
            Divider()
        }
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("income.source.\(row.id.rawValue)")
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded income.")
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
            .accessibilityIdentifier("income.offline")
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
