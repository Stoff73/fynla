import Testing
@testable import Fynla

@Suite("Native navigation menu")
struct NavigationMenuTests {
    // The drawer transcribes /m's shared primaryNavigationSections exactly.
    // Native-only Share / Lock / Sign out actions remain outside this data.
    @Test
    func mirrorsTheMobileDrawerGroupsAndLabels() {
        let sections = NavigationMenuSection.mDrawer

        #expect(sections.map(\.title) == [
            "Overview",
            "Cash Management",
            "Finances",
            "Family",
            "Planning",
            "Account",
        ])
        #expect(sections.flatMap(\.items).map(\.label) == [
            "Dashboard",
            "Achievements",
            "Conversation History",
            "Income",
            "Expenditure",
            "Net Worth",
            "Bank Accounts",
            "Investments",
            "Retirement",
            "Protection",
            "Estate Planning",
            "Goals",
            "Tax Strategy",
            "Holistic Plan",
            "Personal Information",
            "Subscription",
            "Settings",
        ])
        #expect(sections.flatMap(\.items).map(\.route) == [
            .dashboard,
            .achievements,
            .conversationHistory,
            .income,
            .expenditure,
            .netWorth(category: nil),
            .savings(accountID: nil),
            .investment(accountID: nil),
            .retirement(pensionType: nil, id: nil),
            .protection(policyType: nil, id: nil),
            .estate,
            .goals,
            .taxStrategy,
            .holisticPlan,
            .personalInformation,
            .subscription,
            .settings,
        ])
    }

    @Test
    func everyDrawerDestinationHasAStableTextTitle() {
        for item in NavigationMenuSection.mDrawer.flatMap(\.items) {
            #expect(NavigationDestinationFactory.title(for: item.route) == item.label)
        }
        #expect(
            NavigationDestinationFactory.title(
                for: .netWorth(category: "chattels")
            ) == "Valuables"
        )
    }

    @Test
    func everyPackageSixRouteShapeHasANativeDestination() {
        let routes: [AppRoute] = [
            .income,
            .expenditure,
            .netWorth(category: nil),
            .netWorth(category: "properties"),
            .protection(policyType: nil, id: nil),
            .protection(policyType: "life", id: 41),
            .savings(accountID: nil),
            .savings(accountID: 42),
            .investment(accountID: nil),
            .investment(accountID: 43),
            .retirement(pensionType: nil, id: nil),
            .retirement(pensionType: "dc", id: 44),
            .estate,
            .goals,
            .taxStrategy,
            .holisticPlan,
        ]

        #expect(Set(routes).count == 16)
        #expect(routes.allSatisfy {
            !NavigationDestinationFactory.title(for: $0).isEmpty
        })
    }

    @Test
    func everyPremiumGateRoutesDirectlyToSubscription() {
        #expect(NavigationDestinationFactory.premiumGateRoute == .subscription)
    }

    @Test
    func conversationHistoryHasTheCanonicalMobilePath() {
        #expect(AppRoute.conversationHistory.mobilePath == "/conversation-history")
    }
}
