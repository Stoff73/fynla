import Foundation
import SwiftUI

struct AchievementsView: View {
    let model: AchievementsModel
    let onRoute: (AppRoute) -> Void
    @State private var selectedTab: AchievementsTab = .achievements
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your progress…") }
            case .loaded:
                loadedContent
            case .offline:
                if model.content == nil {
                    framed {
                        ScreenStateView(
                            state: .offline,
                            retry: { Task { await model.load() } }
                        )
                    }
                } else {
                    loadedContent
                }
            case .unauthenticated:
                framed { ScreenStateView(state: .unauthenticated) }
            case let .failed(requestID):
                framed {
                    ScreenStateView(
                        state: .failed(requestID: requestID),
                        retry: { Task { await model.load() } }
                    )
                }
            }
        }
        .background(FynlaColor.pageBackground)
        .task { await model.load() }
        .accessibilityIdentifier("achievements.screen")
    }

    @ViewBuilder
    private var loadedContent: some View {
        if let content = model.content {
            ScrollView {
                VStack(alignment: .leading, spacing: 0) {
                // /m: MobileChrome title "Your progress" + subtitle, with a
                // Back pill and no Edit details (Achievements.vue).
                MobilePageHero(
                    title: "Your progress",
                    subtitle: "Achievements you've earned and milestones you've reached"
                )

                MobilePageActions(onBack: { dismiss() })

                LazyVStack(alignment: .leading, spacing: FynlaSpacing.large) {
                    if model.state == .offline {
                        Text("You're offline. Showing your last loaded progress.")
                            .font(FynlaTypography.bodySmall)
                            .foregroundStyle(FynlaColor.primaryText)
                            .padding(FynlaSpacing.medium)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .background(FynlaColor.Token.savannah100.color)
                            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                    }

                    tabSelector

                    switch selectedTab {
                    case .achievements:
                        achievements(content)
                    case .milestones:
                        milestones(content)
                    case .history:
                        history(content)
                    }

                    if let message = model.paginationMessage {
                        Text(message)
                            .font(FynlaTypography.bodySmall)
                            .foregroundStyle(FynlaColor.primaryText)
                            .accessibilityIdentifier("achievements.pagination-error")
                    }
                }
                .padding(FynlaSpacing.standard)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
                }
            }
            .refreshable { await model.refresh() }
        } else {
            LoadingView(message: "Loading your progress…")
        }
    }

    // ma-tabs — white card holding the three tab buttons: 14/700 neutral500
    // on horizon100 with a horizon200 border; active = white on horizon500.
    private var tabSelector: some View {
        HStack(spacing: 8) {
            ForEach(AchievementsTab.allCases) { tab in
                tabButton(tab)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Progress sections")
    }

    private func tabButton(_ tab: AchievementsTab) -> some View {
        let isSelected = selectedTab == tab

        return Button(tab.title) {
            selectedTab = tab
        }
        .font(.system(size: 14, weight: .bold))
        .foregroundStyle(isSelected ? .white : FynlaColor.Token.neutral500.color)
        .frame(
            maxWidth: .infinity,
            minHeight: FynlaSpacing.minimumInteractiveTarget
        )
        .background(
            isSelected
                ? FynlaColor.Token.horizon500.color
                : FynlaColor.Token.horizon100.color
        )
        .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 8, style: .continuous)
                .stroke(
                    isSelected
                        ? FynlaColor.Token.horizon500.color
                        : FynlaColor.Token.horizon200.color,
                    lineWidth: 1
                )
        )
        .accessibilityAddTraits(isSelected ? .isSelected : [])
        .accessibilityIdentifier("achievements.tab.\(tab.id)")
    }

    @ViewBuilder
    private func achievements(_ content: AchievementsContent) -> some View {
        if !content.summary.completed.isEmpty {
            progressCard(title: "Done") {
                ForEach(content.summary.completed) { action in
                    badgeRow(
                        title: action.title,
                        status: datedLabel("Done", action.completedAt),
                        earned: true
                    )
                }
                if content.summary.completed.count < content.summary.completedTotal {
                    fullWidthButton(
                        model.isLoadingMoreCompleted ? "Loading…" : "Show more"
                    ) {
                        Task { await model.loadMoreCompleted() }
                    }
                    .disabled(model.isLoadingMoreCompleted)
                    .padding(.top, 2)
                    .accessibilityIdentifier("achievements.completed.more")
                }
            }
        }

        progressCard(title: "Earned") {
            if content.summary.achievements.isEmpty {
                emptyText("No achievements yet — keep building your plan to earn your first.")
            } else {
                ForEach(content.summary.achievements) { badge in
                    badgeRow(
                        title: badge.title,
                        description: badge.description,
                        status: badge.earned
                            ? datedLabel("Earned", badge.earnedAt)
                            : "Not yet earned",
                        earned: badge.earned
                    )
                }
            }
        }
    }

    @ViewBuilder
    private func milestones(_ content: AchievementsContent) -> some View {
        if !content.summary.upcoming.isEmpty {
            progressCard(title: "Next up") {
                ForEach(groupedUpcoming(content.summary.upcoming), id: \.group) { section in
                    // ma-group — uppercase 12/700 group heading.
                    Text(section.group.uppercased())
                        .font(.system(size: 12, weight: .bold))
                        .kerning(0.5)
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                        .padding(.top, 4)
                    ForEach(section.items) { item in
                        upcomingRow(item)
                    }
                }
            }
        }

        progressCard(title: "Reached") {
            if content.summary.milestones.isEmpty {
                emptyText("No milestones reached yet — keep building your plan.")
            } else {
                ForEach(content.summary.milestones) { milestone in
                    badgeRow(
                        title: milestone.title,
                        status: milestone.achieved
                            ? datedLabel("Reached", milestone.achievedAt)
                            : "Not yet reached",
                        earned: milestone.achieved
                    )
                }
                if content.summary.nextCursor != nil {
                    fullWidthButton(model.isLoadingMoreMilestones ? "Loading…" : "Load more milestones") {
                        Task { await model.loadMoreMilestones() }
                    }
                    .disabled(model.isLoadingMoreMilestones)
                    .accessibilityIdentifier("achievements.milestones.more")
                }
            }
        }
    }

    private func upcomingRow(_ item: AchievementUpcoming) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            badgeContent(title: item.title, description: item.steps, status: upcomingStatus(item), earned: false)
            if item.state == .inProgress, let progress = item.progress {
                ProgressView(value: progress.percent, total: 100) {
                    Text(progress.label)
                }
                .accessibilityLabel(progress.label)
                .accessibilityValue("\(progress.percent)%")
            }
            if let action = item.nextAction {
                Button(action.label) {
                    onRoute(
                        SemanticDestinationResolver.route(
                            for: action.destination,
                            legacyPath: item.route
                        )
                    )
                }
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            } else if item.route != nil {
                Button("View details") {
                    onRoute(
                        SemanticDestinationResolver.route(
                            for: nil,
                            legacyPath: item.route
                        )
                    )
                }
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            }
        }
    }

    private func upcomingStatus(_ item: AchievementUpcoming) -> String? {
        switch item.state {
        case .inProgress:
            "In progress"
        case .locked:
            "Locked"
        case .inapplicable:
            "Not applicable"
        case .earned:
            nil
        }
    }

    // ma-history — hairline-separated label/date rows; the trailing sentinel
    // loads the next ledger page as it scrolls into view (/m's
    // IntersectionObserver equivalent).
    @ViewBuilder
    private func history(_ content: AchievementsContent) -> some View {
        progressCard(title: "Your activity") {
            if content.activity.isEmpty {
                emptyText("Nothing here yet — your answers, records, and completed actions will show up as you go.")
            } else {
                VStack(alignment: .leading, spacing: 0) {
                    ForEach(Array(content.activity.enumerated()), id: \.offset) { index, activity in
                        HStack(alignment: .firstTextBaseline, spacing: 12) {
                            Text(activity.label)
                                .font(.system(size: 14, weight: .semibold))
                                .foregroundStyle(FynlaColor.Token.horizon500.color)
                            Spacer(minLength: 8)
                            Text(formattedDate(activity.occurredAt))
                                .font(.system(size: 12))
                                .foregroundStyle(FynlaColor.Token.neutral500.color)
                                .lineLimit(1)
                        }
                        .padding(.vertical, 10)
                        .overlay(alignment: .bottom) {
                            if index != content.activity.count - 1 {
                                FynlaColor.Token.horizon100.color.frame(height: 1)
                            }
                        }
                        .accessibilityElement(children: .combine)
                    }
                }
                if content.activityNextCursor != nil {
                    Text(model.isLoadingMoreActivity ? "Loading more…" : "")
                        .font(.system(size: 14))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                        .frame(maxWidth: .infinity, minHeight: 24)
                        .onAppear {
                            Task { await model.loadMoreActivity() }
                        }
                        .accessibilityIdentifier("achievements.activity.more")
                }
            }
        }
    }

    private func progressCard<Content: View>(
        title: String,
        @ViewBuilder content: () -> Content
    ) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            Text(title.uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            content()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // ma-badge — earned prominent (spring border + tint), unearned muted.
    private func badgeRow(
        title: String,
        description: String? = nil,
        status: String?,
        earned: Bool
    ) -> some View {
        badgeContent(
            title: title,
            description: description,
            status: status,
            earned: earned
        )
    }

    private func badgeContent(
        title: String,
        description: String?,
        status: String?,
        earned: Bool
    ) -> some View {
        HStack(alignment: .top, spacing: 10) {
            if earned {
                Image(systemName: "seal.fill")
                    .font(.system(size: 24, weight: .semibold))
                    .foregroundStyle(FynlaColor.Token.spring600.color)
                    .accessibilityLabel("Earned badge")
            }
            VStack(alignment: .leading, spacing: 4) {
                Text(title)
                    .font(.system(size: 15, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                if let description, !description.isEmpty {
                    Text(description)
                        .font(.system(size: 13))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
                if let status, !status.isEmpty {
                    Text(status)
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(
                            earned
                                ? FynlaColor.Token.spring600.color
                                : FynlaColor.Token.neutral500.color
                        )
                }
            }
        }
        .padding(14)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(
            earned
                ? FynlaColor.Token.spring500.color.opacity(0.08)
                : FynlaColor.Token.savannah100.color
        )
        .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 8, style: .continuous)
                .stroke(
                    earned
                        ? FynlaColor.Token.spring500.color
                        : FynlaColor.Token.horizon200.color,
                    lineWidth: 1
                )
        )
        .opacity(earned ? 1 : 0.55)
        .accessibilityElement(children: .combine)
    }

    // m-btn — full-width raspberry.
    private func fullWidthButton(_ title: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(title)
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(.white)
                .frame(maxWidth: .infinity)
                .padding(14)
                .background(FynlaColor.Token.raspberry500.color)
                .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
        }
    }

    private func emptyText(_ text: String) -> some View {
        Text(text)
            .font(.system(size: 14))
            .foregroundStyle(FynlaColor.Token.neutral500.color)
    }

    private func groupedUpcoming(
        _ items: [AchievementUpcoming]
    ) -> [(group: String, items: [AchievementUpcoming])] {
        items.reduce(into: []) { groups, item in
            if groups.last?.group == item.group {
                groups[groups.count - 1].items.append(item)
            } else {
                groups.append((group: item.group, items: [item]))
            }
        }
    }

    private func datedLabel(_ prefix: String, _ value: String?) -> String {
        let date = formattedDate(value)
        return date.isEmpty ? prefix : "\(prefix) \(date)"
    }

    // /m formatDate: toLocaleDateString('en-GB') — dd/mm/yyyy.
    private func formattedDate(_ value: String?) -> String {
        guard let value else { return "" }
        let plain = ISO8601DateFormatter()
        let fractional = ISO8601DateFormatter()
        fractional.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        guard let date = plain.date(from: value) ?? fractional.date(from: value) else {
            return ""
        }
        let output = DateFormatter()
        output.locale = Locale(identifier: "en_GB")
        output.timeZone = TimeZone(secondsFromGMT: 0)
        output.dateFormat = "dd/MM/yyyy"
        return output.string(from: date)
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Your progress",
                    subtitle: "Achievements you've earned and milestones you've reached"
                )
                content()
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }
}

private enum AchievementsTab: String, CaseIterable, Identifiable {
    case achievements
    case milestones
    case history

    var id: String { rawValue }

    var title: String {
        rawValue.capitalized
    }
}
