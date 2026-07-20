import SwiftUI

struct NavigationMenuItem: Equatable, Identifiable, Sendable {
    let label: String
    // SF Symbol mirroring /m's drawer NAV_ICON glyphs (CSJ /m design match,
    // 2026-07-20).
    let icon: String
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
                NavigationMenuItem(label: "Dashboard", icon: "house", route: .dashboard, isStaged: false),
                NavigationMenuItem(label: "Achievements", icon: "rosette", route: .achievements, isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Cash Management",
            items: [
                NavigationMenuItem(label: "Income", icon: "sterlingsign.circle", route: .income, isStaged: false),
                NavigationMenuItem(label: "Expenditure", icon: "list.bullet.rectangle", route: .expenditure, isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Finances",
            items: [
                NavigationMenuItem(label: "Net Worth", icon: "chart.bar", route: .netWorth(category: nil), isStaged: false),
                NavigationMenuItem(label: "Savings", icon: "creditcard", route: .savings(accountID: nil), isStaged: false),
                NavigationMenuItem(label: "Investments", icon: "chart.line.uptrend.xyaxis", route: .investment(accountID: nil), isStaged: false),
                NavigationMenuItem(label: "Retirement", icon: "clock", route: .retirement(pensionType: nil, id: nil), isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Family",
            items: [
                NavigationMenuItem(label: "Protection", icon: "checkmark.shield", route: .protection(policyType: nil, id: nil), isStaged: false),
                NavigationMenuItem(label: "Estate Planning", icon: "building.columns", route: .estate, isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Planning",
            items: [
                NavigationMenuItem(label: "Goals", icon: "star", route: .goals, isStaged: false),
                NavigationMenuItem(label: "Tax Strategy", icon: "doc.text", route: .taxStrategy, isStaged: false),
                NavigationMenuItem(label: "Holistic Plan", icon: "square.stack.3d.up", route: .holisticPlan, isStaged: false),
            ]
        ),
        NavigationMenuSection(
            title: "Account",
            items: [
                NavigationMenuItem(label: "Report a problem", icon: "exclamationmark.bubble", route: .bugReport, isStaged: false),
                NavigationMenuItem(label: "Settings", icon: "gearshape", route: .settings, isStaged: false),
            ]
        ),
    ]
}

struct NavigationMenuView: View {
    let includeStagedDestinations: Bool
    let account: SettingsAccount?
    let shareURL: URL
    let onSelect: (AppRoute) -> Void
    let onLock: () -> Void
    let onSignOut: () -> Void
    let onDismiss: () -> Void

    var body: some View {
        NavigationStack {
            List {
                // User header mirroring /m's drawer head (name + email).
                if let account {
                    Section {
                        VStack(alignment: .leading, spacing: 2) {
                            Text(account.name)
                                .font(FynlaTypography.bodySmall.weight(.bold))
                                .foregroundStyle(FynlaColor.primaryText)
                            Text(account.email)
                                .font(FynlaTypography.caption)
                                .foregroundStyle(FynlaColor.secondaryText)
                        }
                        .accessibilityElement(children: .combine)
                        .accessibilityIdentifier("navigation.account")
                    }
                }

                ForEach(visibleSections) { section in
                    Section(section.title ?? "") {
                        ForEach(section.items) { item in
                            Button {
                                onSelect(item.route)
                            } label: {
                                Label(item.label, systemImage: item.icon)
                                    .font(FynlaTypography.body)
                                    .foregroundStyle(FynlaColor.primaryText)
                            }
                            .frame(
                                maxWidth: .infinity,
                                minHeight: FynlaSpacing.minimumInteractiveTarget,
                                alignment: .leading
                            )
                            .accessibilityIdentifier("navigation.\(identifier(for: item.route))")
                        }
                    }
                }

                // Share + sign out mirroring /m's drawer account section.
                Section {
                    ShareLink(item: shareURL) {
                        Label("Share Fynla", systemImage: "square.and.arrow.up")
                            .font(FynlaTypography.body)
                            .foregroundStyle(FynlaColor.primaryText)
                    }
                    .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                    .accessibilityIdentifier("navigation.share")

                    Button {
                        onLock()
                    } label: {
                        Label("Lock", systemImage: "lock")
                            .font(FynlaTypography.body)
                            .foregroundStyle(FynlaColor.primaryText)
                    }
                    .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                    .accessibilityIdentifier("navigation.lock")

                    Button {
                        onSignOut()
                    } label: {
                        Label("Sign out", systemImage: "rectangle.portrait.and.arrow.right")
                            .font(FynlaTypography.body)
                            .foregroundStyle(FynlaColor.Token.raspberry700.color)
                    }
                    .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                    .accessibilityIdentifier("navigation.sign-out")
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
