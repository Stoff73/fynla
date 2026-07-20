import SwiftUI

// Mirrors /m's dashboard (resources/mobile/views/Dashboard.vue): greeting
// header, gradient hero holding the level card + next-milestone nudge, the
// "LEVEL UP" callout with the focus carousel, Today's insight, and the
// finances section, with the milestone toast overlaid.
struct DashboardView: View {
    let model: DashboardModel
    let greetingName: () -> String?
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String?) -> Void

    @State private var dismissedMilestoneIDs: Set<String> = []

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                DashboardLoadingView()
            case let .loaded(snapshot):
                dashboard(snapshot)
            case let .offline(previous):
                if let previous {
                    dashboard(previous, offline: true)
                } else {
                    ErrorView(
                        title: "You're offline",
                        message: "Reconnect to load your dashboard."
                    ) {
                        Task { await model.load() }
                    }
                }
            case .unauthenticated:
                ErrorView(
                    title: "Please sign in again",
                    message: "Your secure session has expired.",
                    retryTitle: nil
                )
            case let .failed(requestID):
                ErrorView(
                    message: failureMessage(requestID: requestID)
                ) {
                    Task { await model.load() }
                }
            }
        }
        .background(FynlaColor.pageBackground)
        .toolbar {
            ToolbarItem(placement: .principal) {
                Text(greeting)
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .accessibilityAddTraits(.isHeader)
                    .accessibilityIdentifier("dashboard.greeting")
            }
        }
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("dashboard.screen")
    }

    // Time-of-day greeting, mirroring /m's computed greeting.
    private var greeting: String {
        let hour = Calendar.current.component(.hour, from: Date())
        let part = hour < 12 ? "morning" : (hour < 18 ? "afternoon" : "evening")
        if let name = greetingName(), !name.isEmpty {
            return "Good \(part), \(name)"
        }
        return "Good \(part)"
    }

    private func dashboard(
        _ snapshot: DashboardSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.large) {
                if offline {
                    Text("You're offline. Showing your last loaded dashboard.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                        .padding(FynlaSpacing.medium)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .background(FynlaColor.Token.savannah100.color)
                        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                        .padding(.horizontal, FynlaSpacing.standard)
                        .accessibilityIdentifier("dashboard.offline")
                }

                hero(snapshot)

                Group {
                    FocusAreasView(
                        areas: snapshot.focusAreas,
                        percentile: snapshot.percentile,
                        onAction: { action in
                            switch action.action.kind {
                            case .navigate:
                                onRoute(route(forPath: action.action.payload))
                            case .fynCapture:
                                onOpenFyn(action.action.payload)
                            }
                        },
                        onComplete: { action in
                            Task { await model.complete(action) }
                        },
                        onViewAll: { key in
                            onRoute(route(forPath: key))
                        },
                        onSeeAllActions: {
                            onRoute(.achievements)
                        },
                        completingActionIDs: model.completingActionIDs
                    )

                    if let actionMessage = model.actionMessage {
                        Text(actionMessage)
                            .font(FynlaTypography.bodySmall)
                            .foregroundStyle(FynlaColor.primaryText)
                            .accessibilityIdentifier("dashboard.action-error")
                    }

                    alerts(snapshot.alerts)

                    if let insight = snapshot.fynInsight, !insight.isEmpty {
                        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                            Text("Today's insight")
                                .font(FynlaTypography.sectionTitle)
                                .foregroundStyle(FynlaColor.primaryText)
                            Text(insight)
                                .font(FynlaTypography.body)
                                .foregroundStyle(FynlaColor.secondaryText)
                        }
                        .padding(FynlaSpacing.standard)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .background(FynlaColor.surface)
                        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                        .accessibilityIdentifier("dashboard.insight")
                    }

                    FinancePanelsView(
                        panels: FinancePanel.panels(from: snapshot),
                        onRoute: onRoute
                    )
                }
                .padding(.horizontal, FynlaSpacing.standard)
            }
            .padding(.bottom, FynlaSpacing.xLarge)
        }
        .refreshable { await model.refresh() }
        .overlay(alignment: .top) {
            if let milestone = activeMilestoneToast(snapshot) {
                milestoneToast(milestone)
            }
        }
    }

    // Gradient hero mirroring /m's md-scroll-hero: full-bleed 135° horizon →
    // raspberry gradient carrying the level card and milestone nudge.
    private func hero(_ snapshot: DashboardSnapshot) -> some View {
        VStack(spacing: FynlaSpacing.standard) {
            Button {
                onRoute(.achievements)
            } label: {
                LevelProgressView(level: snapshot.level)
            }
            .buttonStyle(.plain)

            if let milestone = snapshot.nextMilestone {
                NextMilestoneView(milestone: milestone) {
                    onRoute(route(forMilestone: milestone.route))
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .padding(.bottom, FynlaSpacing.small)
        .frame(maxWidth: .infinity)
        .background(
            LinearGradient(
                colors: [
                    FynlaColor.Token.horizon500.color,
                    FynlaColor.Token.raspberry500.color,
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
    }

    // Alerts from the dashboard payload, severity-ordered by the server.
    @ViewBuilder
    private func alerts(_ alerts: [DashboardAlert]) -> some View {
        if !alerts.isEmpty {
            VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                ForEach(alerts) { alert in
                    Button {
                        onRoute(route(forPath: alert.actionLink))
                    } label: {
                        VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                            Text(alert.title)
                                .font(FynlaTypography.bodySmall.weight(.semibold))
                                .foregroundStyle(FynlaColor.primaryText)
                            Text(alert.message)
                                .font(FynlaTypography.caption)
                                .foregroundStyle(FynlaColor.secondaryText)
                            Text(alert.actionText)
                                .font(FynlaTypography.caption.weight(.semibold))
                                .foregroundStyle(FynlaColor.primaryAction)
                        }
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .padding(FynlaSpacing.medium)
                        .background(
                            alert.severity == .critical
                                ? FynlaColor.Token.raspberry100.color
                                : FynlaColor.surface
                        )
                        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                    }
                    .buttonStyle(.plain)
                }
            }
            .accessibilityIdentifier("dashboard.alerts")
        }
    }

    private func activeMilestoneToast(_ snapshot: DashboardSnapshot) -> DashboardMilestone? {
        snapshot.newMilestones.first { !dismissedMilestoneIDs.contains($0.id) }
    }

    // Milestone toast mirroring /m's md-milestone: Share + Dismiss.
    private func milestoneToast(_ milestone: DashboardMilestone) -> some View {
        HStack(alignment: .center, spacing: FynlaSpacing.medium) {
            VStack(alignment: .leading, spacing: 2) {
                Text("Milestone reached")
                    .font(FynlaTypography.caption.weight(.semibold))
                    .foregroundStyle(FynlaColor.secondaryText)
                Text(milestone.label)
                    .font(FynlaTypography.bodySmall.weight(.semibold))
                    .foregroundStyle(FynlaColor.primaryText)
            }
            .frame(maxWidth: .infinity, alignment: .leading)

            ShareLink(item: "Milestone reached on Fynla — \(milestone.label)") {
                Text("Share")
                    .font(FynlaTypography.button)
                    .foregroundStyle(FynlaColor.primaryAction)
            }

            Button("Dismiss") {
                dismissedMilestoneIDs.insert(milestone.id)
            }
            .font(FynlaTypography.button)
            .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous))
        .shadow(
            color: FynlaColor.Token.horizon500.color.opacity(0.2),
            radius: 12,
            y: 6
        )
        .padding(.horizontal, FynlaSpacing.standard)
        .padding(.top, 136)
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("dashboard.milestone-toast")
    }

    private func failureMessage(requestID: String?) -> String {
        guard let requestID else {
            return "We could not load your dashboard. Please try again."
        }
        return "We could not load your dashboard. Reference: \(requestID)"
    }

    private func route(forPath path: String) -> AppRoute {
        switch path.trimmingCharacters(in: CharacterSet(charactersIn: "/")) {
        case "income": .income
        case "expenditure": .expenditure
        case "net-worth": .netWorth(category: nil)
        case "protection": .protection(policyType: nil, id: nil)
        case "savings": .savings(accountID: nil)
        case "investment": .investment(accountID: nil)
        case "retirement": .retirement(pensionType: nil, id: nil)
        case "estate": .estate
        case "goals": .goals
        case "tax-strategy": .taxStrategy
        case "holistic-plan": .holisticPlan
        case "achievements": .achievements
        default: .dashboard
        }
    }

    private func route(forMilestone route: String) -> AppRoute {
        switch route {
        case "m-net-worth": .netWorth(category: nil)
        case "m-goals": .goals
        case "m-savings": .savings(accountID: nil)
        case "m-retirement": .retirement(pensionType: nil, id: nil)
        case "m-protection": .protection(policyType: nil, id: nil)
        case "m-estate": .estate
        case "tax-strategy": .taxStrategy
        default: .dashboard
        }
    }

}

// Branded £-coin loader mirroring /m's md-loader.
struct DashboardLoadingView: View {
    @State private var isSpinning = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        VStack(spacing: FynlaSpacing.standard) {
            ZStack {
                Circle()
                    .stroke(FynlaColor.Token.horizon200.color, lineWidth: 4)
                Circle()
                    .trim(from: 0, to: 0.25)
                    .stroke(
                        FynlaColor.primaryAction,
                        style: StrokeStyle(lineWidth: 4, lineCap: .round)
                    )
                    .rotationEffect(.degrees(isSpinning ? 360 : 0))
                    .animation(
                        reduceMotion
                            ? nil
                            : .linear(duration: 1).repeatForever(autoreverses: false),
                        value: isSpinning
                    )
                Text("£")
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
            }
            .frame(width: 56, height: 56)
            .accessibilityHidden(true)

            Text("Loading your dashboard…")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .onAppear { isSpinning = true }
        .accessibilityLabel("Loading your dashboard")
    }
}
