import SwiftUI

struct GoalsView: View {
    let model: GoalsModel
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
        .navigationTitle("Goals")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("goals.screen")
    }

    private func content(
        _ snapshot: GoalsSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Goals and life events")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your financial milestones and how they're tracking")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline { offlineNotice }
                hero(snapshot)
                if let overview = snapshot.overview, overview.totalGoals > 0 {
                    overallProgress(overview)
                }
                goalsCard(snapshot.goals)
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(_ snapshot: GoalsSnapshot) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Goals on track")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            if let overview = snapshot.overview {
                Text("\(overview.onTrackCount) of \(overview.totalGoals)")
                    .font(FynlaTypography.pageTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                    .accessibilityIdentifier("goals.on-track")
                Text(
                    overview.totalGoals == 0
                        ? "No goals set yet."
                        : "\(MoneyFormatter.gbp(overview.totalCurrent)) of \(MoneyFormatter.gbp(overview.totalTarget)) saved so far."
                )
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            } else {
                Text("Unavailable")
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.secondaryText)
                Text("Goal totals are unavailable right now; your individual goals are still shown below.")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func overallProgress(_ overview: GoalsOverview) -> some View {
        let progress = min(
            max(NSDecimalNumber(decimal: overview.overallProgress).doubleValue / 100, 0),
            1
        )
        return VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Overall progress")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            HStack(alignment: .firstTextBaseline) {
                Text("Saved towards your goals")
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                Text("of \(MoneyFormatter.gbp(overview.totalTarget))")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            ProgressView(value: progress)
                .tint(FynlaColor.focus)
            HStack(alignment: .firstTextBaseline) {
                Text("\(MoneyFormatter.percentage(overview.overallProgress)) complete")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                Text("\(MoneyFormatter.gbp(overview.totalCurrent)) saved")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func goalsCard(_ goals: [FinancialGoal]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline) {
                Text("Your goals")
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                Button("Add goal") {
                    onOpenFyn("I'd like to add a new goal.")
                }
                .font(FynlaTypography.button)
                .foregroundStyle(FynlaColor.primaryAction)
                .accessibilityIdentifier("goals.add")
            }
            .padding(.bottom, FynlaSpacing.small)

            if goals.isEmpty {
                Text("You haven't set any goals yet.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(goals) { goal in
                    goalRow(goal)
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func goalRow(_ goal: FinancialGoal) -> some View {
        let progress = min(
            max(NSDecimalNumber(decimal: goal.progressPercentage).doubleValue / 100, 0),
            1
        )
        return VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
            HStack(alignment: .top, spacing: FynlaSpacing.small) {
                VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                    Text(goal.displayName)
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(goal.typeLabel)
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                Spacer()
                Text(goal.statusLabel)
                    .font(FynlaTypography.caption)
                    .foregroundStyle(goal.isOnTrack ? FynlaColor.focus : FynlaColor.primaryAction)
            }
            ProgressView(value: progress)
                .tint(goal.isOnTrack ? FynlaColor.focus : FynlaColor.primaryAction)
            HStack(alignment: .firstTextBaseline) {
                Text("\(MoneyFormatter.gbp(goal.currentAmount)) of \(MoneyFormatter.gbp(goal.targetAmount))")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                Text(goal.remainingLabel)
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            HStack {
                Spacer()
                Button("Edit") {
                    onOpenFyn("I'd like to update my \"\(goal.displayName)\" goal.")
                }
                .font(FynlaTypography.button)
                .foregroundStyle(FynlaColor.primaryAction)
                .accessibilityIdentifier("goals.edit.\(goal.id)")
            }
        }
        .padding(.vertical, FynlaSpacing.medium)
        .overlay(alignment: .bottom) { Divider() }
        .accessibilityIdentifier("goals.goal.\(goal.id)")
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded goals.")
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
