import SwiftUI

struct NavigationMenuItem: Equatable, Identifiable, Sendable {
    let label: String
    // SF Symbol mirroring /m's drawer NAV_ICON glyphs (CSJ /m design match,
    // 2026-07-20).
    let icon: String
    let route: AppRoute

    var id: AppRoute { route }
}

struct NavigationMenuSection: Equatable, Identifiable, Sendable {
    let title: String?
    let items: [NavigationMenuItem]

    var id: String { title ?? "Primary" }

    // Transcribes /m's shared primaryNavigationSections exactly: the same
    // groups, labels, order and routes. Native-only actions remain below.
    static let mDrawer: [NavigationMenuSection] = [
        NavigationMenuSection(
            title: "Overview",
            items: [
                NavigationMenuItem(label: "Dashboard", icon: "house", route: .dashboard),
                NavigationMenuItem(label: "Achievements", icon: "star.circle", route: .achievements),
                NavigationMenuItem(label: "Conversation History", icon: "clock.arrow.circlepath", route: .conversationHistory),
            ]
        ),
        NavigationMenuSection(
            title: "Cash Management",
            items: [
                NavigationMenuItem(label: "Income", icon: "sterlingsign.circle", route: .income),
                NavigationMenuItem(label: "Expenditure", icon: "doc.plaintext", route: .expenditure),
            ]
        ),
        NavigationMenuSection(
            title: "Finances",
            items: [
                NavigationMenuItem(label: "Net Worth", icon: "chart.bar", route: .netWorth(category: nil)),
                NavigationMenuItem(label: "Bank Accounts", icon: "creditcard", route: .savings(accountID: nil)),
                NavigationMenuItem(label: "Investments", icon: "chart.line.uptrend.xyaxis", route: .investment(accountID: nil)),
                NavigationMenuItem(label: "Retirement", icon: "clock", route: .retirement(pensionType: nil, id: nil)),
            ]
        ),
        NavigationMenuSection(
            title: "Family",
            items: [
                NavigationMenuItem(label: "Protection", icon: "checkmark.shield", route: .protection(policyType: nil, id: nil)),
                NavigationMenuItem(label: "Estate Planning", icon: "building.columns", route: .estate),
            ]
        ),
        NavigationMenuSection(
            title: "Planning",
            items: [
                NavigationMenuItem(label: "Goals", icon: "star", route: .goals),
                NavigationMenuItem(label: "Tax Strategy", icon: "doc.text", route: .taxStrategy),
                NavigationMenuItem(label: "Holistic Plan", icon: "square.stack.3d.up", route: .holisticPlan),
            ]
        ),
        NavigationMenuSection(
            title: "Account",
            items: [
                NavigationMenuItem(label: "Personal Information", icon: "person.text.rectangle", route: .personalInformation),
                NavigationMenuItem(label: "Subscription", icon: "creditcard", route: .subscription),
                NavigationMenuItem(label: "Settings", icon: "slider.horizontal.3", route: .settings),
            ]
        ),
    ]
}

// The /m drawer (md-drawer in MobileChrome.vue/Dashboard.vue): white panel,
// user head with circular close, grouped icon links with hairline section
// dividers, admin section for admins, then Share Fynla / Settings / Lock /
// Sign out. The shell presents it sliding in from the left over a backdrop.
struct NavigationMenuView: View {
    let account: SettingsAccount?
    let activeRoute: AppRoute
    let shareClient: any ShareContentClient
    let isOpeningAdmin: Bool
    let adminError: String?
    let onSelect: (AppRoute) -> Void
    let onOpenAdmin: () -> Void
    let onLock: () -> Void
    let onSignOut: () -> Void
    let onDismiss: () -> Void

    @State private var shareContent: ShareContent?
    @State private var isFetchingShare = false

    var body: some View {
        VStack(spacing: 0) {
            head
            navigation
        }
        .frame(maxHeight: .infinity)
        .background(Color.white.ignoresSafeArea())
        .sheet(item: $shareContent) { content in
            ShareContentSheet(content: content)
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("navigation.menu")
    }

    // Drawer head — user name + email, circular eggshell close (md-drawer__head).
    private var head: some View {
        HStack(alignment: .center, spacing: FynlaSpacing.medium) {
            VStack(alignment: .leading, spacing: 4) {
                Text(account?.name ?? "Your account")
                    .font(.system(size: 17, weight: .heavy))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .lineLimit(1)
                Text(account?.email ?? "")
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .lineLimit(1)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            .accessibilityElement(children: .combine)
            .accessibilityIdentifier("navigation.account")

            Button(action: onDismiss) {
                Image(systemName: "xmark")
                    .font(.system(size: 15, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .frame(width: 36, height: 36)
                    .background(FynlaColor.Token.eggshell500.color)
                    .clipShape(Circle())
            }
            .accessibilityLabel("Close menu")
            .accessibilityIdentifier("navigation.close")
        }
        .padding(.horizontal, 20)
        .padding(.top, 20)
        .padding(.bottom, 18)
        .overlay(alignment: .bottom) {
            FynlaColor.Token.lightGray.color.frame(height: 1)
        }
    }

    private var navigation: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 0) {
                ForEach(Array(NavigationMenuSection.mDrawer.enumerated()), id: \.element.id) { index, section in
                    if index > 0 {
                        sectionDivider
                    }
                    sectionView(section)
                }

                if account?.isAdmin == true {
                    sectionDivider
                    groupLabel("Admin")
                    // The desktop Admin Panel has no native equivalent. Its
                    // callback obtains a one-time server handoff before the
                    // shared Safari sheet is presented.
                    link(label: "Admin Panel", icon: "gearshape", isActive: false) {
                        onOpenAdmin()
                    }
                    .disabled(isOpeningAdmin)
                    .accessibilityIdentifier("navigation.admin")
                    if isOpeningAdmin {
                        ProgressView("Opening Admin Panel…")
                            .font(.system(size: 12))
                            .padding(.horizontal, 12)
                    }
                    if let adminError {
                        VStack(alignment: .leading, spacing: 6) {
                            Text(adminError)
                                .font(.system(size: 12))
                                .foregroundStyle(FynlaColor.Token.raspberry600.color)
                            Button("Try again", action: onOpenAdmin)
                                .font(.system(size: 12, weight: .bold))
                                .foregroundStyle(FynlaColor.Token.horizon500.color)
                        }
                        .padding(.horizontal, 12)
                        .accessibilityIdentifier("navigation.admin.error")
                    }
                }

                sectionDivider
                accountSection
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 8)
        }
    }

    private func sectionView(_ section: NavigationMenuSection) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            if let title = section.title {
                groupLabel(title)
            }
            ForEach(section.items) { item in
                link(
                    label: item.label,
                    icon: item.icon,
                    isActive: item.route == activeRoute
                ) {
                    onSelect(item.route)
                }
                .accessibilityIdentifier("navigation.\(identifier(for: item.route))")
            }
        }
        .padding(.vertical, 4)
    }

    // Share Fynla (server-provided referral copy, /m doShare('app_referral')),
    // then native-only Lock and Sign out entries. Settings lives in the shared
    // Account navigation group above.
    private var accountSection: some View {
        VStack(alignment: .leading, spacing: 0) {
            link(label: "Share Fynla", icon: "square.and.arrow.up", isActive: false) {
                fetchShare()
            }
            .disabled(isFetchingShare)
            .accessibilityIdentifier("navigation.share")

            link(label: "Lock", icon: "lock", isActive: false) {
                onLock()
            }
            .accessibilityIdentifier("navigation.lock")

            link(
                label: "Sign out",
                icon: "rectangle.portrait.and.arrow.right",
                isActive: false,
                tint: FynlaColor.Token.raspberry600.color
            ) {
                onSignOut()
            }
            .accessibilityIdentifier("navigation.sign-out")
        }
        .padding(.top, 4)
        .padding(.vertical, 4)
    }

    private var sectionDivider: some View {
        FynlaColor.Token.lightGray.color
            .frame(height: 1)
            .padding(.vertical, 4)
    }

    private func groupLabel(_ title: String) -> some View {
        Text(title.uppercased())
            .font(.system(size: 12, weight: .semibold))
            .kerning(0.6)
            .foregroundStyle(FynlaColor.Token.neutral400.color)
            .padding(.horizontal, 12)
            .padding(.top, 12)
            .padding(.bottom, 4)
    }

    // One drawer link (md-drawer__link): 20pt icon + 14pt label; the active
    // route gets the light-blue fill.
    private func link(
        label: String,
        icon: String,
        isActive: Bool,
        tint: Color? = nil,
        action: @escaping () -> Void
    ) -> some View {
        Button(action: action) {
            HStack(spacing: 12) {
                Image(systemName: icon)
                    .font(.system(size: 15, weight: .regular))
                    .foregroundStyle(
                        tint ?? (isActive
                            ? FynlaColor.Token.horizon500.color
                            : FynlaColor.Token.neutral500.color)
                    )
                    .frame(width: 20, height: 20)
                    .accessibilityHidden(true)
                Text(label)
                    .font(.system(size: 14, weight: isActive ? .semibold : .medium))
                    .foregroundStyle(
                        tint ?? (isActive
                            ? FynlaColor.Token.horizon500.color
                            : FynlaColor.Token.neutral600.color)
                    )
                Spacer(minLength: 0)
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 10)
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            .background(isActive ? FynlaColor.Token.lightBlue100.color : Color.clear)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        }
        .buttonStyle(.plain)
    }

    private func fetchShare() {
        guard !isFetchingShare else { return }
        isFetchingShare = true
        Task { @MainActor in
            defer { isFetchingShare = false }
            // Fall back to the generic app URL if the endpoint fails — same
            // net effect as /m's silent no-op, but the tap still shares.
            let content = (try? await shareClient.content(for: "app_referral"))
                ?? ShareContent(title: "Fynla", text: nil, url: "https://fynla.org")
            shareContent = content
        }
    }

    private func identifier(for route: AppRoute) -> String {
        NavigationDestinationFactory.title(for: route)
            .lowercased()
            .replacingOccurrences(of: " ", with: "-")
    }
}
