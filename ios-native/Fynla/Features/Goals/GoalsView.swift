import SwiftUI

// Transcribes /m's Goals page (resources/mobile/views/modules/Goals.vue):
// gradient page hero, Edit details pill, on-track hero card, overall-progress
// card with the status bar, and per-goal blocks (name/type, status pill,
// progress bar, amounts + time remaining, EDIT action). Goal add/edit run
// through Fyn with /m's exact prompts. Whole-pound amounts.
struct GoalsView: View {
    let model: GoalsModel
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your goals…") }
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
        .task { await model.load() }
        .accessibilityIdentifier("goals.screen")
    }

    private func content(
        _ snapshot: GoalsSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Goals and life events",
                    subtitle: "Your financial milestones and how they're tracking"
                )

                MobilePageActions(actionTitle: "Add goal", editDetails: {
                    onOpenContextualFyn(
                        FynContextualActions.goalsOverview(
                            hasGoals: !snapshot.goals.isEmpty
                        )
                    )
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    heroCard(snapshot)

                    if totalGoals(snapshot) > 0 {
                        overallCard(snapshot)
                    }

                    goalsCard(snapshot)
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero: dark card with "X of Y" + saved sub-line.
    private func heroCard(_ snapshot: GoalsSnapshot) -> some View {
        MobileHeroCard(
            label: "Goals on track",
            metric: "\(onTrackCount(snapshot)) of \(totalGoals(snapshot))",
            sub: savedLabel(snapshot)
        )
        .accessibilityIdentifier("goals.on-track")
    }

    // Overall progress: mts-allow head/bar/foot with the status colour.
    private func overallCard(_ snapshot: GoalsSnapshot) -> some View {
        let progress = overallProgress(snapshot)
        let status = status(forPercent: Double(progress))
        return VStack(alignment: .leading, spacing: 6) {
            Text("Overall progress".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 4)

            HStack(alignment: .firstTextBaseline) {
                Text("Saved towards your goals")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer()
                Text("of \(MoneyFormatter.gbpWhole(totalTarget(snapshot)))")
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }

            statusBar(fill: Double(min(progress, 100)) / 100, color: status.color)

            HStack(alignment: .firstTextBaseline) {
                Text("\(progress)% complete")
                    .font(.system(size: 12, weight: .bold))
                    .foregroundStyle(status.color)
                Spacer()
                Text("\(MoneyFormatter.gbpWhole(totalCurrent(snapshot))) saved")
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .accessibilityIdentifier("goals.overall-progress")
    }

    private func goalsCard(_ snapshot: GoalsSnapshot) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline) {
                Text("Your goals".uppercased())
                    .font(.system(size: 12, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }
            .padding(.bottom, 6)

            if snapshot.goals.isEmpty {
                Text("You haven't set any goals yet.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.vertical, 8)
            } else {
                ForEach(snapshot.goals) { goal in
                    goalBlock(goal, showsDivider: goal.id != snapshot.goals.last?.id)
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mg-goal: name/type + status pill head, status bar, amounts/remaining
    // foot, EDIT action.
    private func goalBlock(_ goal: FinancialGoal, showsDivider: Bool) -> some View {
        let status = status(for: goal)
        let pct = NSDecimalNumber(decimal: goal.progressPercentage).doubleValue
        return VStack(alignment: .leading, spacing: 6) {
            HStack(alignment: .top, spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(goal.displayName)
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text(goal.typeLabel)
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
                Spacer()
                Text(statusLabel(goal))
                    .font(.system(size: 11, weight: .bold))
                    .foregroundStyle(status.color)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 2)
                    .background(status.color.opacity(0.12))
                    .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
            }

            statusBar(fill: min(max(pct / 100, 0), 1), color: status.color)
                .padding(.vertical, 2)

            HStack(alignment: .firstTextBaseline) {
                Text("\(MoneyFormatter.gbpWhole(goal.currentAmount)) of \(MoneyFormatter.gbpWhole(goal.targetAmount))")
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer()
                Text(goal.remainingLabel)
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }

            if goal.isPrimaryOwner != false {
                Button {
                    onOpenContextualFyn(FynContextualActions.goal(id: goal.id))
                } label: {
                    Text("Edit".uppercased())
                        .font(.system(size: 12, weight: .bold))
                        .kerning(0.5)
                        .foregroundStyle(FynlaColor.Token.raspberry500.color)
                }
                .padding(.top, 2)
                .accessibilityIdentifier("goals.edit.\(goal.id)")
            }
        }
        .padding(.vertical, 12)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("goals.goal.\(goal.id)")
    }

    // /m statusOf(): complete/on-track spring; else violet ≥50%, raspberry.
    private enum GoalStatus {
        case spring, violet, raspberry

        var color: Color {
            switch self {
            case .spring: FynlaColor.Token.spring500.color
            case .violet: FynlaColor.Token.violet500.color
            case .raspberry: FynlaColor.Token.raspberry500.color
            }
        }
    }

    private func status(for goal: FinancialGoal) -> GoalStatus {
        let pct = NSDecimalNumber(decimal: goal.progressPercentage).doubleValue
        if pct >= 100 || goal.status == "completed" { return .spring }
        if goal.isOnTrack { return .spring }
        return pct >= 50 ? .violet : .raspberry
    }

    private func statusLabel(_ goal: FinancialGoal) -> String {
        let pct = NSDecimalNumber(decimal: goal.progressPercentage).doubleValue
        if pct >= 100 || goal.status == "completed" { return "Complete" }
        return goal.isOnTrack ? "On track" : "Behind"
    }

    private func status(forPercent pct: Double) -> GoalStatus {
        if pct >= 100 { return .spring }
        if pct >= 50 { return .violet }
        return .raspberry
    }

    private func statusBar(fill: Double, color: Color) -> some View {
        GeometryReader { proxy in
            ZStack(alignment: .leading) {
                Capsule().fill(FynlaColor.Token.horizon200.color)
                Capsule()
                    .fill(color)
                    .frame(width: proxy.size.width * min(max(fill, 0), 1))
            }
        }
        .frame(height: 6)
    }

    private func totalGoals(_ snapshot: GoalsSnapshot) -> Int {
        snapshot.overview?.totalGoals ?? snapshot.goals.count
    }

    private func onTrackCount(_ snapshot: GoalsSnapshot) -> Int {
        snapshot.overview?.onTrackCount ?? snapshot.goals.filter(\.isOnTrack).count
    }

    private func totalTarget(_ snapshot: GoalsSnapshot) -> Decimal {
        snapshot.overview?.totalTarget
            ?? snapshot.goals.reduce(0) { $0 + $1.targetAmount }
    }

    private func totalCurrent(_ snapshot: GoalsSnapshot) -> Decimal {
        snapshot.overview?.totalCurrent
            ?? snapshot.goals.reduce(0) { $0 + $1.currentAmount }
    }

    private func overallProgress(_ snapshot: GoalsSnapshot) -> Int {
        if let progress = snapshot.overview?.overallProgress {
            return Int(NSDecimalNumber(decimal: progress).doubleValue.rounded())
        }
        let target = totalTarget(snapshot)
        guard target > 0 else { return 0 }
        let ratio = NSDecimalNumber(decimal: totalCurrent(snapshot) / target).doubleValue
        return Int((ratio * 100).rounded())
    }

    private func savedLabel(_ snapshot: GoalsSnapshot) -> String {
        guard totalGoals(snapshot) > 0 else { return "No goals set yet." }
        return "\(MoneyFormatter.gbpWhole(totalCurrent(snapshot))) of \(MoneyFormatter.gbpWhole(totalTarget(snapshot))) saved so far."
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded goals.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("goals.offline")
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Goals and life events",
                    subtitle: "Your financial milestones and how they're tracking"
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
