import SwiftUI

enum NavigationDestinationFactory {
    static func title(for route: AppRoute) -> String {
        switch route {
        case .dashboard: "Dashboard"
        case .achievements: "Achievements"
        case let .module(slug): slug.replacingOccurrences(of: "-", with: " ").capitalized
        case .income: "Income"
        case .expenditure: "Expenditure"
        case .netWorth: "Net Worth"
        case .balanceHistory: "Balance History"
        case .protection: "Protection"
        case .savings: "Savings"
        case .investment: "Investments"
        case .retirement: "Retirement"
        case .estate: "Estate Planning"
        case .goals: "Goals"
        case .taxStrategy: "Tax Strategy"
        case .holisticPlan: "Holistic Plan"
        case .bugReport: "Report a problem"
        case .settings: "Settings"
        }
    }

    @MainActor @ViewBuilder
    static func destination(
        for route: AppRoute,
        subscriptionModel: SubscriptionModel,
        achievementsModel: AchievementsModel,
        incomeModel: IncomeModel,
        expenditureModel: ExpenditureModel,
        netWorthModel: NetWorthModel,
        balanceHistoryModel: BalanceHistoryModel,
        savingsModel: SavingsModel,
        investmentModel: InvestmentModel,
        retirementModel: RetirementModel,
        protectionModel: ProtectionModel,
        estateModel: EstateModel,
        goalsModel: GoalsModel,
        taxStrategyModel: TaxStrategyModel,
        holisticPlanModel: HolisticPlanModel,
        settingsModel: SettingsModel,
        bugReportModel: BugReportModel,
        appleManager: any AppleSubscriptionManaging,
        onOpenFyn: @escaping (String) -> Void,
        onRoute: @escaping (AppRoute) -> Void
    ) -> some View {
        switch route {
        case .achievements:
            AchievementsView(model: achievementsModel, onRoute: onRoute)
        case .bugReport:
            BugReportView(model: bugReportModel)
        case .income:
            IncomeView(
                model: incomeModel,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(.settings) }
            )
        case .expenditure:
            ExpenditureView(
                model: expenditureModel,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(.settings) }
            )
        case let .netWorth(category):
            if let category {
                NetWorthCategoryView(
                    categoryKey: category,
                    model: netWorthModel,
                    onOpenFyn: onOpenFyn,
                    onOpenSubscription: { onRoute(.settings) }
                )
            } else {
                NetWorthView(
                    model: netWorthModel,
                    onRoute: onRoute,
                    onOpenFyn: onOpenFyn,
                    onOpenSubscription: { onRoute(.settings) }
                )
            }
        case .balanceHistory:
            BalanceHistoryView(
                model: balanceHistoryModel,
                onOpenSubscription: { onRoute(.settings) }
            )
        case let .savings(accountID):
            if let accountID {
                SavingsAccountView(
                    accountID: accountID,
                    model: savingsModel,
                    onOpenFyn: onOpenFyn
                )
            } else {
                SavingsView(
                    model: savingsModel,
                    onRoute: onRoute,
                    onOpenFyn: onOpenFyn,
                    onOpenSubscription: { onRoute(.settings) }
                )
            }
        case let .investment(accountID):
            if let accountID {
                InvestmentAccountView(
                    accountID: accountID,
                    model: investmentModel,
                    onOpenFyn: onOpenFyn
                )
            } else {
                InvestmentView(
                    model: investmentModel,
                    onRoute: onRoute,
                    onOpenFyn: onOpenFyn,
                    onOpenSubscription: { onRoute(.settings) }
                )
            }
        case let .retirement(pensionType, pensionID):
            if let pensionType {
                RetirementPensionView(
                    pensionType: pensionType,
                    pensionID: pensionID,
                    model: retirementModel,
                    onOpenFyn: onOpenFyn
                )
            } else {
                RetirementView(
                    model: retirementModel,
                    onRoute: onRoute,
                    onOpenFyn: onOpenFyn,
                    onOpenSubscription: { onRoute(.settings) }
                )
            }
        case let .protection(policyType, policyID):
            if let policyType, let policyID {
                ProtectionPolicyView(
                    policyTypeKey: policyType,
                    policyID: policyID,
                    model: protectionModel,
                    onOpenFyn: onOpenFyn
                )
            } else {
                ProtectionView(
                    model: protectionModel,
                    onRoute: onRoute,
                    onOpenFyn: onOpenFyn,
                    onOpenSubscription: { onRoute(.settings) }
                )
            }
        case .estate:
            EstateView(
                model: estateModel,
                onOpenSubscription: { onRoute(.settings) }
            )
        case .goals:
            GoalsView(
                model: goalsModel,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(.settings) }
            )
        case .taxStrategy:
            TaxStrategyView(
                model: taxStrategyModel,
                onRoute: onRoute,
                onOpenSubscription: { onRoute(.settings) }
            )
        case .holisticPlan:
            HolisticPlanView(
                model: holisticPlanModel,
                onOpenSubscription: { onRoute(.settings) }
            )
        case .settings:
            SettingsView(
                model: settingsModel,
                subscriptionModel: subscriptionModel,
                appleManager: appleManager
            )
        case .dashboard, .module:
            StagedNativeDestinationView(title: title(for: route))
        }
    }
}

private struct StagedNativeDestinationView: View {
    let title: String

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text(title)
                .font(FynlaTypography.pageTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text("This native screen is enabled for the staged development build and will connect to the existing Fynla backend.")
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.pageBackground)
        .navigationTitle(title)
        .navigationBarTitleDisplayMode(.inline)
        .accessibilityIdentifier("navigation.destination")
    }
}
