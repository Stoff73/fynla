import SwiftUI

struct GoalDetailView: View {
    let goalID: Int
    let model: GoalsModel
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.detailState {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading this goal…") }
            case let .loaded(detail):
                content(detail)
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
        .task(id: goalID) { await model.loadGoal(id: goalID) }
        .accessibilityIdentifier("goal-detail.screen")
    }

    private func content(
        _ detail: GoalDetailResponse,
        offline: Bool = false
    ) -> some View {
        let goal = detail.goal
        return ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: "Goal details", subtitle: goal.displayName)

                if goal.isPrimaryOwner != false {
                    MobilePageActions(editDetails: {
                        onOpenContextualFyn(FynContextualActions.goal(id: goal.id))
                    })
                }

                Group {
                    if offline { offlineNotice }
                    headingCard(goal)
                    MobileHeroCard(
                        label: "Current progress",
                        metric: MoneyFormatter.gbpWhole(goal.currentAmount),
                        sub: "\(percentage(goal.progressPercentage)) of \(MoneyFormatter.gbpWhole(goal.targetAmount)) target"
                    )
                    goalCard(goal)
                    datesCard(goal)
                    contributionsCard(goal)
                    if !detail.milestones.isEmpty {
                        milestonesCard(detail.milestones)
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private func headingCard(_ goal: FinancialGoal) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(goal.displayName)
                .font(FynlaTypography.pageTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .accessibilityAddTraits(.isHeader)
                .accessibilityIdentifier("goal-detail.heading")
            Text(goal.typeLabel)
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.secondaryText)
            if let description = nonEmpty(goal.description) {
                Text(description)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.top, 4)
            }
        }
        .cardStyle()
    }

    private func goalCard(_ goal: FinancialGoal) -> some View {
        detailCard(title: "Goal") {
            detailRow("Target", MoneyFormatter.gbpWhole(goal.targetAmount))
            detailRow("Current value", MoneyFormatter.gbpWhole(goal.currentAmount))
            detailRow("Progress", percentage(goal.progressPercentage))
            detailRow("Status", goal.statusLabel)
            detailRow("Ownership", label(goal.ownershipType))
        }
    }

    private func datesCard(_ goal: FinancialGoal) -> some View {
        detailCard(title: "Dates") {
            detailRow("Created", date(goal.createdAt))
            detailRow("Target date", date(goal.targetDate))
        }
    }

    private func contributionsCard(_ goal: FinancialGoal) -> some View {
        detailCard(title: "Contributions") {
            detailRow("Contribution", money(goal.monthlyContribution))
            detailRow("Frequency", label(goal.contributionFrequency))
            detailRow("Required monthly", money(goal.requiredMonthlyContribution))
            detailRow("Last contribution", date(goal.lastContributionDate))
        }
    }

    private func milestonesCard(_ milestones: [GoalMilestone]) -> some View {
        detailCard(title: "Milestones") {
            ForEach(Array(milestones.enumerated()), id: \.element.id) { index, milestone in
                detailRow(
                    "Milestone \(index + 1)",
                    "\(percentage(milestone.percentage ?? milestone.progressPercentage)) · \(milestone.reached == true ? "Reached" : "Next")"
                )
            }
        }
    }

    private func detailCard<Content: View>(
        title: String,
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
        .cardStyle()
    }

    private func detailRow(_ key: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key)
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.secondaryText)
            Spacer()
            Text(value)
                .font(.system(size: 13, weight: .semibold))
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, 9)
        .overlay(alignment: .bottom) {
            FynlaColor.Token.horizon100.color.frame(height: 1)
        }
    }

    private func percentage(_ value: Decimal?) -> String {
        guard let value else { return "—" }
        return "\(NSDecimalNumber(decimal: value).doubleValue.formatted(.number.precision(.fractionLength(0 ... 1))))%"
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbpWhole) ?? "—"
    }

    private func label(_ value: String?) -> String {
        guard let value = nonEmpty(value) else { return "—" }
        return value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private func date(_ value: String?) -> String {
        guard let value = nonEmpty(value) else { return "—" }
        let iso = ISO8601DateFormatter()
        let day = DateFormatter()
        day.locale = Locale(identifier: "en_US_POSIX")
        day.timeZone = TimeZone(secondsFromGMT: 0)
        day.dateFormat = "yyyy-MM-dd"
        guard let parsed = iso.date(from: value) ?? day.date(from: value) else { return "—" }
        let output = DateFormatter()
        output.locale = Locale(identifier: "en_GB")
        output.timeZone = TimeZone(secondsFromGMT: 0)
        output.dateFormat = "dd/MM/yyyy"
        return output.string(from: parsed)
    }

    private func nonEmpty(_ value: String?) -> String? {
        guard let trimmed = value?.trimmingCharacters(in: .whitespacesAndNewlines),
              !trimmed.isEmpty
        else {
            return nil
        }
        return trimmed
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing the last loaded goal details.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: "Goal details", subtitle: "Your financial milestone")
                content()
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        framed {
            ScreenStateView(
                state: state,
                retry: state.canRetry ? { Task { await model.loadGoal(id: goalID) } } : nil,
                openSubscription: state.canUpgrade ? onOpenSubscription : nil
            )
        }
    }
}

private extension View {
    func cardStyle() -> some View {
        self
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}
