import SwiftUI

struct DashboardView: View {
    let model: DashboardModel
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String?) -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                LoadingView(message: "Loading your dashboard…")
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
        .navigationTitle("Dashboard")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("dashboard.screen")
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
                        .accessibilityIdentifier("dashboard.offline")
                }

                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Your financial plan")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your dashboard, actions and progress in one place.")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                LevelProgressView(
                    level: snapshot.level,
                    percentile: snapshot.percentile
                )
                .onTapGesture { onRoute(.achievements) }

                if let milestone = snapshot.nextMilestone {
                    NextMilestoneView(milestone: milestone) {
                        onRoute(route(forMilestone: milestone.route))
                    }
                }

                FocusAreasView(
                    areas: snapshot.focusAreas,
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
                    completingActionIDs: model.completingActionIDs
                )

                if let actionMessage = model.actionMessage {
                    Text(actionMessage)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                        .accessibilityIdentifier("dashboard.action-error")
                }

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

                VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                    Text("Your finances")
                        .font(FynlaTypography.sectionTitle)
                        .foregroundStyle(FynlaColor.primaryText)

                    ModuleSummaryView(
                        key: "net-worth",
                        title: "Net worth",
                        summary: netWorthSummary(snapshot.netWorth)
                    ) {
                        onRoute(.netWorth(category: nil))
                    }

                    ForEach(snapshot.modules.ordered, id: \.key) { module in
                        ModuleSummaryView(
                            key: module.key,
                            title: module.title,
                            summary: module.summary
                        ) {
                            onRoute(route(forModule: module.key))
                        }
                    }
                }
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func failureMessage(requestID: String?) -> String {
        guard let requestID else {
            return "We could not load your dashboard. Please try again."
        }
        return "We could not load your dashboard. Reference: \(requestID)"
    }

    private func route(forModule key: String) -> AppRoute {
        switch key {
        case "protection": .protection(policyType: nil, id: nil)
        case "savings": .savings(accountID: nil)
        case "investment": .investment(accountID: nil)
        case "retirement": .retirement(pensionType: nil, id: nil)
        case "estate": .estate
        case "goals": .goals
        default: .dashboard
        }
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

    private func netWorthSummary(_ netWorth: DashboardNetWorth) -> DashboardModuleSummary {
        DashboardModuleSummary(
            status: .active,
            message: nil,
            totalCoverage: nil,
            policyCount: nil,
            criticalGaps: nil,
            hasIncomeProtection: nil,
            totalSavings: nil,
            totalAccounts: nil,
            emergencyFundMonths: nil,
            emergencyFundStatus: nil,
            portfolioValue: netWorth.total,
            accountsCount: nil,
            holdingsCount: nil,
            yearsToRetirement: nil,
            potValue: nil,
            projectedIncome: nil,
            targetIncome: nil,
            incomeGap: nil,
            totalPensions: nil,
            netEstate: nil,
            ihtLiability: nil,
            effectiveTaxRate: nil,
            totalGoals: nil,
            completedGoals: nil,
            totalTarget: nil,
            totalSaved: nil
        )
    }
}
