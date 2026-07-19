import SwiftUI

struct NavigationMenuItem: Equatable, Identifiable, Sendable {
    let label: String
    let route: AppRoute
    let isStaged: Bool

    var id: AppRoute { route }
}

struct NavigationMenuSection: Equatable, Identifiable, Sendable {
    let title: String?
    let items: [NavigationMenuItem]

    var id: String { title ?? "Primary" }

    static let version1: [NavigationMenuSection] = [
        NavigationMenuSection(
            title: nil,
            items: [
                NavigationMenuItem(label: "Dashboard", route: .dashboard, isStaged: false),
                NavigationMenuItem(label: "Achievements", route: .achievements, isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Cash Management",
            items: [
                NavigationMenuItem(label: "Income", route: .income, isStaged: false),
                NavigationMenuItem(label: "Expenditure", route: .expenditure, isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Finances",
            items: [
                NavigationMenuItem(label: "Net Worth", route: .netWorth(category: nil), isStaged: false),
                NavigationMenuItem(label: "Savings", route: .savings(accountID: nil), isStaged: false),
                NavigationMenuItem(label: "Investments", route: .investment(accountID: nil), isStaged: false),
                NavigationMenuItem(label: "Retirement", route: .retirement(pensionType: nil, id: nil), isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Family",
            items: [
                NavigationMenuItem(label: "Protection", route: .protection(policyType: nil, id: nil), isStaged: false),
                NavigationMenuItem(label: "Estate Planning", route: .estate, isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Planning",
            items: [
                NavigationMenuItem(label: "Goals", route: .goals, isStaged: false),
                NavigationMenuItem(label: "Tax Strategy", route: .taxStrategy, isStaged: false),
                NavigationMenuItem(label: "Holistic Plan", route: .holisticPlan, isStaged: true),
            ]
        ),
        NavigationMenuSection(
            title: "Account",
            items: [
                NavigationMenuItem(label: "Report a problem", route: .bugReport, isStaged: false),
                NavigationMenuItem(label: "Settings", route: .settings, isStaged: false),
            ]
        ),
    ]
}

struct NavigationMenuView: View {
    let includeStagedDestinations: Bool
    let onSelect: (AppRoute) -> Void
    let onDismiss: () -> Void

    var body: some View {
        NavigationStack {
            List {
                ForEach(visibleSections) { section in
                    Section(section.title ?? "") {
                        ForEach(section.items) { item in
                            Button(item.label) {
                                onSelect(item.route)
                            }
                            .font(FynlaTypography.body)
                            .foregroundStyle(FynlaColor.primaryText)
                            .frame(
                                maxWidth: .infinity,
                                minHeight: FynlaSpacing.minimumInteractiveTarget,
                                alignment: .leading
                            )
                            .accessibilityIdentifier("navigation.\(identifier(for: item.route))")
                        }
                    }
                }
            }
            .navigationTitle("Menu")
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Close", action: onDismiss)
                        .accessibilityIdentifier("navigation.close")
                }
            }
        }
        .accessibilityIdentifier("navigation.menu")
    }

    private var visibleSections: [NavigationMenuSection] {
        NavigationMenuSection.version1.compactMap { section in
            let items = section.items.filter {
                includeStagedDestinations || !$0.isStaged
            }
            guard !items.isEmpty else { return nil }
            return NavigationMenuSection(title: section.title, items: items)
        }
    }

    private func identifier(for route: AppRoute) -> String {
        NavigationDestinationFactory.title(for: route)
            .lowercased()
            .replacingOccurrences(of: " ", with: "-")
    }
}
