import SwiftUI

struct SavingsView: View {
    let model: SavingsModel
    let onRoute: (AppRoute) -> Void
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                ScreenStateView(state: .loading)
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
        .navigationTitle("Savings")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("savings.screen")
    }

    private func content(
        _ snapshot: SavingsSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Bank accounts & cash")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your cash, emergency-fund runway and ISA allowance")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                if offline { offlineNotice }
                hero(snapshot)
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
                    isaAllowanceCard(allowance)
                }

                Button("Update savings with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "savings",
                            addPhrase: "I'd like to add a bank account.",
                            names: snapshot.accounts.map { Optional($0.displayName) }
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("savings.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(_ snapshot: SavingsSnapshot) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Total cash")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(snapshot.totalCash)
                .accessibilityIdentifier("savings.total-cash")
            Text(accountCountLabel(snapshot.accounts.count))
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func accountsCard(
        title: String,
        accounts: [SavingsAccount],
        emptyMessage: String,
        snapshot: SavingsSnapshot,
        showsLimit: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.small) {
                Text(title)
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                if showsLimit, let limit = snapshot.accountLimit {
                    VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                        Text("\(snapshot.accountCount) of \(limit) accounts used")
                            .font(FynlaTypography.caption)
                            .foregroundStyle(
                                snapshot.isAtAccountLimit
                                    ? FynlaColor.primaryAction
                                    : FynlaColor.secondaryText
                            )
                        Button("Upgrade", action: onOpenSubscription)
                            .font(FynlaTypography.caption)
                            .foregroundStyle(FynlaColor.primaryAction)
                    }
                }
            }
            .padding(.bottom, FynlaSpacing.small)

            if accounts.isEmpty {
                Text(emptyMessage)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(accounts) { account in
                    accountRow(account)
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func accountRow(_ account: SavingsAccount) -> some View {
        Button {
            onRoute(.savings(accountID: account.id))
        } label: {
            HStack(alignment: .center, spacing: FynlaSpacing.medium) {
                VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                    Text(account.displayName)
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    HStack(spacing: FynlaSpacing.xSmall) {
                        if account.isEmergencyFund {
                            Text("Emergency fund")
                                .foregroundStyle(FynlaColor.focus)
                        }
                        Text(account.interestRate.map(MoneyFormatter.percentage) ?? "—")
                            .foregroundStyle(FynlaColor.secondaryText)
                    }
                    .font(FynlaTypography.caption)
                }
                Spacer(minLength: FynlaSpacing.small)
                VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                    Text(MoneyFormatter.gbp(account.fullBalanceValue))
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("View")
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.primaryAction)
                }
            }
            .padding(.vertical, FynlaSpacing.medium)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .overlay(alignment: .bottom) { Divider() }
        .accessibilityIdentifier("savings.account.\(account.id)")
    }

    private func emergencyFundCard(_ snapshot: SavingsSnapshot) -> some View {
        let target = snapshot.emergencyFundTarget
        let progress = progressValue(current: snapshot.totalCash, target: target.targetAmount)
        return VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Emergency fund")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            valueRow("Cash held", MoneyFormatter.gbp(snapshot.totalCash))
            valueRow(
                "Target (\(target.targetMonths) \(target.targetMonths == 1 ? "month" : "months"))",
                MoneyFormatter.gbp(target.targetAmount)
            )
            ProgressView(value: progress)
                .tint(FynlaColor.focus)
                .accessibilityIdentifier("savings.emergency-progress")
            HStack(alignment: .firstTextBaseline) {
                Text(runwayLabel(snapshot))
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                Text("\(Int((progress * 100).rounded()))% of target")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            if let rationale = target.rationale, !rationale.isEmpty {
                Text(rationale)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func isaAllowanceCard(_ allowance: SavingsISAAllowance) -> some View {
        let progress = min(
            max(NSDecimalNumber(decimal: allowance.percentageUsed).doubleValue / 100, 0),
            1
        )
        return VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("ISA allowance this year")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            valueRow("ISA allowance used", "of \(MoneyFormatter.gbp(allowance.totalAllowance))")
            ProgressView(value: progress)
                .tint(FynlaColor.focus)
            HStack(alignment: .firstTextBaseline) {
                Text(allowance.remaining > 0 ? "\(MoneyFormatter.gbp(allowance.remaining)) remaining" : "Fully used")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryText)
                Spacer()
                Text("\(MoneyFormatter.gbp(allowance.totalUsed)) used")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func valueRow(_ title: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.small) {
            Text(title)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Spacer()
            Text(value)
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
        }
    }

    private func progressValue(current: Decimal, target: Decimal) -> Double {
        guard target > 0 else { return current > 0 ? 1 : 0 }
        return min(
            max(NSDecimalNumber(decimal: current / target).doubleValue, 0),
            1
        )
    }

    private func runwayLabel(_ snapshot: SavingsSnapshot) -> String {
        let monthly = snapshot.expenditureProfile.totalMonthlyExpenditure
        guard monthly > 0 else { return "Runway unavailable" }
        let months = NSDecimalNumber(decimal: snapshot.totalCash / monthly).doubleValue
        let rounded = months >= 10 ? months.rounded() : (months * 10).rounded() / 10
        return "\(rounded.formatted()) \(rounded == 1 ? "month" : "months") of cover"
    }

    private func accountCountLabel(_ count: Int) -> String {
        count == 0 ? "No accounts added yet." : "Across \(count) \(count == 1 ? "account" : "accounts")."
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded savings.")
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
