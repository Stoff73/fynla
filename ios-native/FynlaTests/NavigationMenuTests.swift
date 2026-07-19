import Testing
@testable import Fynla

@Suite("Native navigation menu")
struct NavigationMenuTests {
    @Test
    func mirrorsTheMobileRouteGroupsAndLabels() {
        let sections = NavigationMenuSection.version1

        #expect(sections.map(\.title) == [
            nil,
            "Cash Management",
            "Finances",
            "Family",
            "Planning",
            "Account",
        ])
        #expect(sections.flatMap(\.items).map(\.label) == [
            "Dashboard",
            "Achievements",
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
            "Report a problem",
            "Settings",
        ])
        #expect(sections.flatMap(\.items).map(\.route) == [
            .dashboard,
            .achievements,
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
            .bugReport,
            .settings,
        ])
    }

    @Test
    func exposesEveryVersionOneMenuDestinationAsImplemented() {
        let items = NavigationMenuSection.version1.flatMap(\.items)

        #expect(items.allSatisfy { !$0.isStaged })
    }

    @Test
    func everyVersionOneDestinationHasAStableTextTitle() {
        for item in NavigationMenuSection.version1.flatMap(\.items) {
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
