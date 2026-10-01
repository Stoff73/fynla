import SwiftUI

// Transcribes /m's Savings page (resources/mobile/views/modules/Savings.vue):
// gradient page hero, Edit details pill (rich /m edit prompt), total-cash
// hero card, bank-account and Cash ISA rows with the emergency-fund tag and
// rate, account-cap head with Upgrade, status-coloured emergency-fund runway
// and ISA allowance bars. Whole-pound amounts as /m's formatCurrency.
struct SavingsView: View {
    let model: SavingsModel
    let onRoute: (AppRoute) -> Void
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void
    @State private var showsISAContributionHistory = false

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your bank accounts…") }
            case let .loaded(snapshot):
                content(snapshot)
            case let .offline(previous):
                if let previous {
                    content(previous, offline: true)
                } else {
                    stateView(.offline)
                }
            case .unauthenticated:
                stateView(.unauthenticated)
            case let .upgradeRequired(message):
                stateView(.upgradeRequired(message: message))
            case let .failed(requestID):
                stateView(.failed(requestID: requestID))
            }
        }
        .background(FynlaColor.pageBackground)
        .task { await model.load() }
        .accessibilityIdentifier("savings.screen")
    }

    private func content(
        _ snapshot: SavingsSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Bank Accounts",
                    subtitle: "Your cash, emergency-fund runway and ISA allowance"
                )

                MobilePageActions(actionTitle: "Add bank account", editDetails: {
                    onOpenContextualFyn(
                        FynContextualActions.savingsOverview(
                            hasAccounts: !snapshot.accounts.isEmpty
                        )
                    )
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    heroCard(snapshot)

                    accountsCard(
                        title: "Bank accounts",
                        accounts: snapshot.bankAccounts,
                        emptyMessage: "You haven't added any bank accounts yet.",
                        snapshot: snapshot,
                        showsLimit: true
                    )

                    if !snapshot.cashISAs.isEmpty {
                        accountsCard(
                            title: "Cash ISA Accounts",
                            accounts: snapshot.cashISAs,
                            emptyMessage: "",
                            snapshot: snapshot,
                            showsLimit: false
                        )
                    }

                    emergencyFundCard(snapshot)

                    if let allowance = snapshot.isaAllowance {
                        let shown = model.isaAllowance ?? allowance
                        // The server's position block is this tax year's; a past
                        // year chosen in the history shows that year's own figures.
                        isaAllowanceCard(
                            shown,
                            position: shown.taxYear == allowance.taxYear ? snapshot.position?.isa : nil
                        )
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero: dark card with the big metric + account-count sub-line.
    private func heroCard(_ snapshot: SavingsSnapshot) -> some View {
        MobileHeroCard(
            label: "Total cash",
            metric: MoneyFormatter.gbpWhole(snapshot.totalCash),
            sub: accountCountLabel(snapshot.accounts.count)
        )
        .accessibilityIdentifier("savings.total-cash")
    }

    private func accountsCard(
        title: String,
        accounts: [SavingsAccount],
        emptyMessage: String,
        snapshot: SavingsSnapshot,
        showsLimit: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline, spacing: 8) {
                Text(title.uppercased())
                    .font(.system(size: 12, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                Spacer()
                if showsLimit, let limit = snapshot.accountLimit {
                    HStack(spacing: 8) {
                        Text("\(snapshot.accountCount) of \(limit) accounts used")
                            .font(.system(size: 12, weight: .bold))
                            .foregroundStyle(
                                snapshot.isAtAccountLimit
                                    ? FynlaColor.Token.raspberry500.color
                                    : FynlaColor.Token.neutral500.color
                            )
                        Button {
                            onOpenSubscription()
                        } label: {
                            Text("Upgrade".uppercased())
                                .font(.system(size: 12, weight: .bold))
                                .kerning(0.5)
                                .foregroundStyle(FynlaColor.Token.raspberry500.color)
                        }
                        .accessibilityIdentifier("savings.upgrade")
                    }
                }
            }
            .padding(.bottom, 6)

            if accounts.isEmpty {
                Text(emptyMessage)
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.vertical, 8)
            } else {
                ForEach(accounts) { account in
                    accountRow(
                        account,
                        showsDivider: account.id != accounts.last?.id
                    )
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // ms-acct: provider + tag/rate left, balance + VIEW right.
    private func accountRow(
        _ account: SavingsAccount,
        showsDivider: Bool
    ) -> some View {
        Button {
            onRoute(.savings(accountID: account.id))
        } label: {
            HStack(alignment: .center, spacing: 12) {
                VStack(alignment: .leading, spacing: 4) {
                    Text(account.displayName)
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    HStack(spacing: 6) {
                        if account.isEmergencyFund {
                            Text("Emergency fund")
                                .font(.system(size: 11, weight: .bold))
                                .foregroundStyle(FynlaColor.Token.spring600.color)
                                .padding(.horizontal, 7)
                                .padding(.vertical, 1)
                                .background(FynlaColor.Token.spring500.color.opacity(0.12))
                                .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
                        }
                        Text(rateLabel(account.interestRate))
                            .font(.system(size: 12))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                    }
                }
                Spacer(minLength: 8)
                VStack(alignment: .trailing, spacing: 2) {
                    Text(MoneyFormatter.gbpWhole(account.fullBalanceValue))
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text("View".uppercased())
                        .font(.system(size: 11, weight: .bold))
                        .kerning(0.5)
                        .foregroundStyle(FynlaColor.Token.raspberry500.color)
                }
            }
            .padding(.vertical, 14)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.lightGray.color.frame(height: 1)
            }
        }
        .accessibilityIdentifier("savings.account.\(account.id)")
    }

    // ms-ef: cash/target rows, status-coloured runway bar, runway + covered.
    // Every value is the server's (SavingsPosition, CSJ 2026-10-01), the same
    // block web and /m render.
    private func emergencyFundCard(_ snapshot: SavingsSnapshot) -> some View {
        let fund = snapshot.position?.emergencyFund
        let target = snapshot.emergencyFundTarget
        let status = RunwayStatus(server: fund?.status)
        let fill = Double(fund?.coveredPercent ?? 0) / 100
        let months = fund?.targetMonths ?? target.targetMonths
        return VStack(alignment: .leading, spacing: 4) {
            Text("Emergency fund".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 4)

            efRow("Cash held", MoneyFormatter.gbpWhole(snapshot.totalCash))
            efRow(
                "Target (\(months) \(months == 1 ? "month" : "months"))",
                MoneyFormatter.gbpWhole(fund?.targetAmount ?? target.targetAmount)
            )

            statusBar(fill: fill, color: status.color)
                .padding(.vertical, 8)
                .accessibilityIdentifier("savings.emergency-progress")

            HStack(alignment: .firstTextBaseline) {
                Text(fund?.runwayLabel ?? "")
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(status.color)
                Spacer()
                Text(fund?.coveredLabel ?? "")
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }

            if let hint = fund?.runwayHint, !hint.isEmpty {
                Text(hint)
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.top, 6)
            }

            if let rationale = target.rationale, !rationale.isEmpty {
                Text(rationale)
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.top, 10)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mts-allow: label/cap head, status bar, remaining/used foot.
    private func isaAllowanceCard(_ allowance: SavingsISAAllowance, position: SavingsPosition.ISA?) -> some View {
        // The server's percentage, status and wording (SavingsPosition).
        let pct = NSDecimalNumber(decimal: position?.percentUsed ?? allowance.percentageUsed).doubleValue
        let status: RunwayStatus = position?.status == "nearly" ? .violet : .spring
        return VStack(alignment: .leading, spacing: 6) {
            Button {
                withAnimation(.easeInOut(duration: 0.2)) {
                    showsISAContributionHistory.toggle()
                }
            } label: {
                HStack(spacing: 8) {
                    Text("ISA allowance this year".uppercased())
                        .font(.system(size: 12, weight: .bold))
                        .kerning(0.5)
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                    Spacer()
                    Image(systemName: showsISAContributionHistory ? "chevron.up" : "chevron.down")
                        .font(.system(size: 11, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.violet500.color)
                }
                .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .accessibilityIdentifier("savings.isa-history.toggle")
            .padding(.bottom, 4)

            HStack(alignment: .firstTextBaseline) {
                Text("ISA allowance used")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer()
                Text("of \(MoneyFormatter.gbpWhole(allowance.totalAllowance))")
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }

            statusBar(fill: min(max(pct / 100, 0), 1), color: status.color)

            HStack(alignment: .firstTextBaseline) {
                Text(position?.remainingLabel ?? "\(MoneyFormatter.gbpWhole(allowance.remaining)) remaining")
                .font(.system(size: 12, weight: .bold))
                .foregroundStyle(status.color)
                Spacer()
                Text("\(MoneyFormatter.gbpWhole(allowance.totalUsed)) used")
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }

            if showsISAContributionHistory {
                Divider()
                    .padding(.vertical, 8)
                ISAContributionHistoryView(
                    allowance: allowance,
                    accountID: nil,
                    accountKind: .all,
                    isLoading: model.isLoadingISAAllowance,
                    onSelectTaxYear: { taxYear in
                        Task { await model.loadISAAllowance(taxYear: taxYear) }
                    }
                )
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func efRow(_ title: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(title)
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            Spacer()
            Text(value)
                .font(.system(size: 14, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
        }
        .padding(.vertical, 4)
    }

    // mts-bar: 6pt track horizon200, status-coloured fill.
    private func statusBar(fill: Double, color: Color) -> some View {
        GeometryReader { proxy in
            ZStack(alignment: .leading) {
                Capsule().fill(FynlaColor.Token.horizon200.color)
                Capsule()
                    .fill(color)
                    .frame(width: proxy.size.width * min(max(fill, 0), 1))
            }
        }
        .frame(height: 6)
    }

    // The server's status against the user's own target (SavingsPosition):
    // on_track, part or low; the colour is this surface's.
    private enum RunwayStatus {
        case spring, violet, raspberry

        init(server: String?) {
            switch server {
            case "part": self = .violet
            case "low": self = .raspberry
            default: self = .spring
            }
        }

        var color: Color {
            switch self {
            case .spring: FynlaColor.Token.spring500.color
            case .violet: FynlaColor.Token.violet500.color
            case .raspberry: FynlaColor.Token.raspberry500.color
            }
        }
    }

    private func rateLabel(_ rate: Decimal?) -> String {
        guard let rate else { return "—" }
        let value = NSDecimalNumber(decimal: rate).doubleValue
        return String(format: "%.2f%%", value)
    }

    private func accountCountLabel(_ count: Int) -> String {
        count == 0 ? "No accounts added yet." : "Across \(count) \(count == 1 ? "account" : "accounts")."
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded savings.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("savings.offline")
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Bank Accounts",
                    subtitle: "Your cash, emergency-fund runway and ISA allowance"
                )
                content()
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        framed {
            ScreenStateView(
                state: state,
                retry: state.canRetry ? { Task { await model.load() } } : nil,
                openSubscription: state.canUpgrade ? onOpenSubscription : nil
            )
        }
    }
}
