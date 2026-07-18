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
    func marksPackageSixRoutesAsStagedWithoutHidingImplementedDestinations() {
        let items = NavigationMenuSection.version1.flatMap(\.items)
        let implemented = items.filter { !$0.isStaged }.map(\.route)

        #expect(implemented == [.dashboard, .achievements, .bugReport, .settings])
    }

    @Test
    func everyVersionOneDestinationHasAStableTextTitle() {
        for item in NavigationMenuSection.version1.flatMap(\.items) {
            #expect(NavigationDestinationFactory.title(for: item.route) == item.label)
        }
    }
}
