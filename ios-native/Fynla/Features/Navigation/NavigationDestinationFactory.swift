import SwiftUI

enum NavigationDestinationFactory {
    static let premiumGateRoute = AppRoute.subscription

    static func title(for route: AppRoute) -> String {
        switch route {
        case .dashboard: "Dashboard"
        case .achievements: "Achievements"
        case .conversationHistory: "Conversation History"
        case .personalInformation: "Personal Information"
        case .subscription: "Subscription"
        case .income: "Income"
        case .expenditure: "Expenditure"
        case let .netWorth(category):
            category.flatMap { NetWorthCategory(rawValue: $0) }?.title ?? "Net Worth"
        case .balanceHistory: "Balance History"
        case .protection: "Protection"
        case .savings: "Bank Accounts"
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
        conversationHistoryModel: ConversationHistoryModel,
        personalInformationModel: PersonalInformationModel,
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
        privacySettingsModel: PrivacySettingsModel,
        dataExportModel: DataExportModel,
        accountDeletionModel: AccountDeletionModel,
        pushCoordinator: PushRegistrationCoordinator,
        bugReportModel: BugReportModel,
        appleManager: any AppleSubscriptionManaging,
        onOpenFyn: @escaping (String) -> Void,
        onOpenContextualFyn: @escaping (FynContextualAction) -> Void,
        onOpenConversation: @escaping (String) -> Void,
        onOpenRoute: @escaping (AppRoute) -> Void,
        onRoute: @escaping (AppRoute) -> Void
    ) -> some View {
        switch route {
        case .achievements:
            AchievementsView(model: achievementsModel, onRoute: onRoute)
        case .conversationHistory:
            ConversationHistoryView(
                model: conversationHistoryModel,
                onOpenConversation: onOpenConversation,
                onRoute: onOpenRoute
            )
        case .personalInformation:
            PersonalInformationView(
                model: personalInformationModel,
                onOpenContextualFyn: onOpenContextualFyn
            )
        case .subscription:
            SubscriptionView(
                model: subscriptionModel,
                appleManager: appleManager
            )
        case .bugReport:
            BugReportView(model: bugReportModel)
        case .income:
            IncomeView(
                model: incomeModel,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(premiumGateRoute) }
            )
        case .expenditure:
            ExpenditureView(
                model: expenditureModel,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(premiumGateRoute) }
            )
        case let .netWorth(category):
            if let category {
                NetWorthCategoryView(
                    categoryKey: category,
                    model: netWorthModel,
                    onOpenFyn: onOpenFyn,
                    onOpenSubscription: { onRoute(premiumGateRoute) }
                )
            } else {
                NetWorthView(
                    model: netWorthModel,
                    onRoute: onRoute,
                    onOpenFyn: onOpenFyn,
                    onOpenSubscription: { onRoute(premiumGateRoute) }
                )
            }
        case .balanceHistory:
            BalanceHistoryView(
                model: balanceHistoryModel,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(premiumGateRoute) }
            )
        case let .savings(accountID):
            if let accountID {
                SavingsAccountView(
                    accountID: accountID,
                    model: savingsModel,
                    onOpenContextualFyn: onOpenContextualFyn
                )
            } else {
                SavingsView(
                    model: savingsModel,
                    onRoute: onRoute,
                    onOpenContextualFyn: onOpenContextualFyn,
                    onOpenSubscription: { onRoute(premiumGateRoute) }
                )
            }
        case let .investment(accountID):
            if let accountID {
                InvestmentAccountView(
                    accountID: accountID,
                    model: investmentModel,
                    onOpenContextualFyn: onOpenContextualFyn
                )
            } else {
                InvestmentView(
                    model: investmentModel,
                    onRoute: onRoute,
                    onOpenContextualFyn: onOpenContextualFyn,
                    onOpenSubscription: { onRoute(premiumGateRoute) }
                )
            }
        case let .retirement(pensionType, pensionID):
            if let pensionType {
                RetirementPensionView(
                    pensionType: pensionType,
                    pensionID: pensionID,
                    model: retirementModel,
                    onOpenContextualFyn: onOpenContextualFyn
                )
            } else {
                RetirementView(
                    model: retirementModel,
                    onRoute: onRoute,
                    onOpenContextualFyn: onOpenContextualFyn,
                    onOpenSubscription: { onRoute(premiumGateRoute) }
                )
            }
        case let .protection(policyType, policyID):
            if let policyType, let policyID {
                ProtectionPolicyView(
                    policyTypeKey: policyType,
                    policyID: policyID,
                    model: protectionModel,
                    onOpenContextualFyn: onOpenContextualFyn
                )
            } else {
                ProtectionView(
                    model: protectionModel,
                    onRoute: onRoute,
                    onOpenContextualFyn: onOpenContextualFyn,
                    onOpenSubscription: { onRoute(premiumGateRoute) }
                )
            }
        case .estate:
            EstateView(
                model: estateModel,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(premiumGateRoute) }
            )
        case .goals:
            GoalsView(
                model: goalsModel,
                onOpenContextualFyn: onOpenContextualFyn,
                onOpenSubscription: { onRoute(premiumGateRoute) }
            )
        case .taxStrategy:
            TaxStrategyView(
                model: taxStrategyModel,
                firstName: settingsModel.greetingFirstName,
                onboardingCompleted: settingsModel.onboardingCompleted,
                onRoute: onRoute,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(premiumGateRoute) }
            )
        case .holisticPlan:
            HolisticPlanView(
                model: holisticPlanModel,
                onOpenFyn: onOpenFyn,
                onOpenSubscription: { onRoute(premiumGateRoute) }
            )
        case .settings:
            SettingsView(
                model: settingsModel,
                subscriptionModel: subscriptionModel,
                appleManager: appleManager,
                privacySettingsModel: privacySettingsModel,
                dataExportModel: dataExportModel,
                accountDeletionModel: accountDeletionModel,
                pushCoordinator: pushCoordinator
            )
        case .dashboard:
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
