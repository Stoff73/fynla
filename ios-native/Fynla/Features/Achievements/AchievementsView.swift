import Foundation
import SwiftUI

struct AchievementsView: View {
    let model: AchievementsModel
    let onRoute: (AppRoute) -> Void
    @State private var selectedTab: AchievementsTab = .achievements

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                LoadingView(message: "Loading your progress…")
            case .loaded:
                loadedContent
            case .offline:
                if model.content == nil {
                    ErrorView(
                        title: "You're offline",
                        message: "Reconnect to load your progress."
                    ) {
                        Task { await model.load() }
                    }
                } else {
                    loadedContent
                }
            case .unauthenticated:
                ErrorView(
                    title: "Please sign in again",
                    message: "Your secure session has expired.",
                    retryTitle: nil
                )
            case let .failed(requestID):
                ErrorView(message: failureMessage(requestID)) {
                    Task { await model.load() }
                }
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Achievements")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .overlay {
            if let celebration = model.content?.pendingCelebration {
                celebrationView(celebration)
            }
        }
        .accessibilityIdentifier("achievements.screen")
    }

    @ViewBuilder
    private var loadedContent: some View {
        if let content = model.content {
            ScrollView {
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

                    VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                        Text("Your progress")
                            .font(FynlaTypography.pageTitle)
                            .foregroundStyle(FynlaColor.primaryText)
                        Text("Achievements you've earned and milestones you've reached")
                            .font(FynlaTypography.body)
                            .foregroundStyle(FynlaColor.secondaryText)
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
            }
            .refreshable { await model.refresh() }
        } else {
            LoadingView(message: "Loading your progress…")
        }
    }

    private var tabSelector: some View {
        HStack(spacing: FynlaSpacing.small) {
            ForEach(AchievementsTab.allCases) { tab in
                tabButton(tab)
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Progress sections")
    }

    private func tabButton(_ tab: AchievementsTab) -> some View {
        let isSelected = selectedTab == tab
        let foreground: Color = isSelected ? .white : FynlaColor.primaryText
        let background = isSelected
            ? FynlaColor.Token.horizon500.color
            : FynlaColor.Token.eggshell50.color

        return Button(tab.title) {
            selectedTab = tab
        }
        .font(FynlaTypography.button)
        .foregroundStyle(foreground)
        .frame(
            maxWidth: .infinity,
            minHeight: FynlaSpacing.minimumInteractiveTarget
        )
        .background(background)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .accessibilityAddTraits(isSelected ? .isSelected : [])
        .accessibilityIdentifier("achievements.tab.\(tab.id)")
    }

    @ViewBuilder
    private func achievements(_ content: AchievementsContent) -> some View {
        if !content.summary.completed.isEmpty {
            progressCard(title: "Done") {
                ForEach(content.summary.completed) { action in
                    progressRow(
                        title: action.title,
                        detail: datedLabel("Done", action.completedAt),
                        prominent: true
                    )
                }
                if content.summary.completed.count < content.summary.completedTotal {
                    Button(model.isLoadingMoreCompleted ? "Loading…" : "Show more") {
                        Task { await model.loadMoreCompleted() }
                    }
                    .disabled(model.isLoadingMoreCompleted)
                    .buttonStyle(.borderedProminent)
                    .accessibilityIdentifier("achievements.completed.more")
                }
            }
        }

        progressCard(title: "Earned") {
            if content.summary.achievements.isEmpty {
                emptyText("No achievements yet — keep building your plan to earn your first.")
            } else {
                ForEach(content.summary.achievements) { badge in
                    progressRow(
                        title: badge.title,
                        description: badge.description,
                        detail: badge.earned
                            ? datedLabel("Earned", badge.earnedAt)
                            : "Not yet earned",
                        prominent: badge.earned
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
                    Text(section.group)
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .textCase(.uppercase)
                    ForEach(section.items) { item in
                        Button {
                            if let route = appRoute(for: item.route) {
                                onRoute(route)
                            }
                        } label: {
                            progressRowContent(
                                title: item.title,
                                description: item.steps,
                                detail: nil,
                                prominent: false
                            )
                        }
                        .buttonStyle(.plain)
                    }
                }
            }
        }

        progressCard(title: "Reached") {
            if content.summary.milestones.isEmpty {
                emptyText("No milestones reached yet — keep building your plan.")
            } else {
                ForEach(content.summary.milestones) { milestone in
                    progressRow(
                        title: milestone.title,
                        detail: milestone.achieved
                            ? datedLabel("Reached", milestone.achievedAt)
                            : "Not yet reached",
                        prominent: milestone.achieved
                    )
                }
            }
        }
    }

    @ViewBuilder
    private func history(_ content: AchievementsContent) -> some View {
        progressCard(title: "Your activity") {
            if content.activity.isEmpty {
                emptyText("Nothing here yet — your answers, records, and completed actions will show up as you go.")
            } else {
                ForEach(content.activity) { activity in
                    progressRow(
                        title: activity.label,
                        detail: formattedDate(activity.occurredAt),
                        prominent: true
                    )
                }
                if content.activityNextCursor != nil {
                    Button(model.isLoadingMoreActivity ? "Loading…" : "Show more activity") {
                        Task { await model.loadMoreActivity() }
                    }
                    .disabled(model.isLoadingMoreActivity)
                    .buttonStyle(.borderedProminent)
                    .accessibilityIdentifier("achievements.activity.more")
                }
            }
        }
    }

    private func progressCard<Content: View>(
        title: String,
        @ViewBuilder content: () -> Content
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
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

    private func progressRow(
        title: String,
        description: String? = nil,
        detail: String?,
        prominent: Bool
    ) -> some View {
        progressRowContent(
            title: title,
            description: description,
            detail: detail,
            prominent: prominent
        )
    }

    private func progressRowContent(
        title: String,
        description: String?,
        detail: String?,
        prominent: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
            Text(title)
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
            if let description {
                Text(description)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            if let detail, !detail.isEmpty {
                Text(detail)
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .padding(FynlaSpacing.medium)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(
            prominent
                ? FynlaColor.Token.horizon200.color.opacity(0.25)
                : FynlaColor.Token.savannah100.color
        )
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func emptyText(_ text: String) -> some View {
        Text(text)
            .font(FynlaTypography.body)
            .foregroundStyle(FynlaColor.secondaryText)
    }

    private func celebrationView(_ celebration: LevelCelebration) -> some View {
        ZStack {
            FynlaColor.Token.horizon500.color.opacity(0.45)
                .ignoresSafeArea()
            VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                Text("Level \(celebration.level)")
                    .font(FynlaTypography.pageTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Text("You've reached \(celebration.levelName).")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                if let message = model.celebrationMessage {
                    Text(message)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                }
                Button(model.isAcknowledgingCelebration ? "Saving…" : "Continue") {
                    Task { await model.dismissCelebration() }
                }
                .disabled(model.isAcknowledgingCelebration)
                .buttonStyle(.borderedProminent)
                .accessibilityIdentifier("achievements.celebration.continue")
            }
            .padding(FynlaSpacing.large)
            .background(FynlaColor.surface)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
            .padding(FynlaSpacing.large)
            .accessibilityElement(children: .contain)
            .accessibilityIdentifier("achievements.celebration")
        }
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

    private func appRoute(for mobileRoute: String) -> AppRoute? {
        switch mobileRoute {
        case "dashboard": .dashboard
        case "m-income": .income
        case "m-expenditure": .expenditure
        case "m-net-worth": .netWorth(category: nil)
        case "m-protection": .protection(policyType: nil, id: nil)
        case "m-savings": .savings(accountID: nil)
        case "m-investment": .investment(accountID: nil)
        case "m-retirement": .retirement(pensionType: nil, id: nil)
        case "m-estate": .estate
        case "m-goals": .goals
        case "tax-strategy": .taxStrategy
        case "holistic-plan": .holisticPlan
        default: nil
        }
    }

    private func datedLabel(_ prefix: String, _ value: String?) -> String {
        let date = formattedDate(value)
        return date.isEmpty ? prefix : "\(prefix) \(date)"
    }

    private func formattedDate(_ value: String?) -> String {
        guard let value,
              let date = ISO8601DateFormatter().date(from: value)
        else { return "" }
        return date.formatted(
            Date.FormatStyle()
                .day()
                .month(.abbreviated)
                .year()
                .locale(Locale(identifier: "en_GB"))
        )
    }

    private func failureMessage(_ requestID: String?) -> String {
        guard let requestID else {
            return "We could not load your progress. Please try again."
        }
        return "We could not load your progress. Reference: \(requestID)"
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
