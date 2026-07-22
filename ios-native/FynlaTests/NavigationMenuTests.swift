import Testing
@testable import Fynla

@Suite("Native navigation menu")
struct NavigationMenuTests {
    // The drawer transcribes /m's navSections (MobileChrome.vue) exactly:
    // Dashboard, then Cash Management / Finances / Family / Planning. The
    // native-only Share / Settings / Lock / Sign out entries live in the
    // account section of the view, not in this data.
    @Test
    func mirrorsTheMobileDrawerGroupsAndLabels() {
        let sections = NavigationMenuSection.mDrawer

        #expect(sections.map(\.title) == [
            nil,
            "Cash Management",
            "Finances",
            "Family",
            "Planning",
        ])
        #expect(sections.flatMap(\.items).map(\.label) == [
            "Dashboard",
            "Income",
            "Expenditure",
            "Net Worth",
            "Savings",
            "Investments",
            "Retirement",
            "Protection",
            "Estate Planning",
            "Goals",
            "Tax Strategy",
            "Holistic Plan",
        ])
        #expect(sections.flatMap(\.items).map(\.route) == [
            .dashboard,
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
        ])
    }

    @Test
    func everyDrawerDestinationHasAStableTextTitle() {
        for item in NavigationMenuSection.mDrawer.flatMap(\.items) {
            #expect(NavigationDestinationFactory.title(for: item.route) == item.label)
        }
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
}
