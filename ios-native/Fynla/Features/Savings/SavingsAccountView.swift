import SwiftUI

struct SavingsAccountView: View {
    let accountID: Int
    let model: SavingsModel
    let onOpenFyn: (String) -> Void

    var body: some View {
        Group {
            switch model.accountState {
            case .idle, .loading:
                ScreenStateView(state: .loading)
            case let .loaded(account):
                content(account)
            case .offline:
                stateView(.offline)
            case .unauthenticated:
                stateView(.unauthenticated)
            case .forbidden:
                ScreenStateView(state: .empty(message: "You don't have access to this account."))
            case .notFound:
                ScreenStateView(state: .empty(message: "This savings account could not be found."))
            case let .failed(requestID):
                stateView(.failed(requestID: requestID))
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Savings")
        .navigationBarTitleDisplayMode(.inline)
        .task(id: accountID) { await model.loadAccount(id: accountID) }
        .accessibilityIdentifier("savings.account.screen")
    }

    private func content(_ account: SavingsAccount) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(account.displayName)
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(accountTypeLabel(account.accountType))
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                hero(account)
                detailCard(title: "Balance & interest") {
                    detailRow("Full balance", MoneyFormatter.gbp(account.fullBalanceValue))
                    if account.isJoint,
                       let percentage = account.ownershipPercentage,
                       let userShare = account.userShare
                    {
                        detailRow(
                            "Your share (\(MoneyFormatter.percentage(percentage)))",
                            MoneyFormatter.gbp(userShare)
                        )
                    }
                    detailRow(
                        "Interest rate",
                        account.interestRate.map(MoneyFormatter.percentage) ?? "—"
                    )
                    detailRow("Monthly interest", MoneyFormatter.gbp(monthlyInterest(account)))
                    detailRow("Annual interest", MoneyFormatter.gbp(annualInterest(account)))
                }

                detailCard(title: "Account information") {
                    detailRow("Provider", account.provider ?? account.institution ?? "—")
                    detailRow("Account type", accountTypeLabel(account.accountType))
                    detailRow("Access", accessTypeLabel(account.accessType))
                    if account.accessType == "notice", let days = account.noticePeriodDays {
                        detailRow("Notice period", "\(days) days")
                    }
                    if account.accessType == "fixed", let maturity = account.maturityDate {
                        detailRow("Maturity date", dateLabel(maturity))
                    }
                    if let country = account.country, !country.isEmpty {
                        detailRow("Country", country)
                    }
                    if let ownership = account.ownershipType {
                        detailRow("Ownership", ownershipLabel(ownership))
                    }
                    if let owner = account.ownerName, !owner.isEmpty {
                        detailRow("Owner", owner)
                    }
                    if let jointOwner = account.jointOwnerName, !jointOwner.isEmpty {
                        detailRow("Joint owner", jointOwner)
                    }
                }

                if account.isISA {
                    detailCard(title: "ISA details") {
                        detailRow("ISA type", isaTypeLabel(account.isaType))
                        if let year = account.isaSubscriptionYear {
                            detailRow("Subscription year", year)
                        }
                        if let amount = account.isaSubscriptionAmount {
                            detailRow("Subscribed this year", MoneyFormatter.gbp(amount))
                        }
                        detailRow("Interest", "Tax-free")
                    }
                }

                Button("Update this account with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "savings",
                            addPhrase: "I'd like to add a bank account.",
                            names: [account.displayName]
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("savings.account.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
    }

    private func hero(_ account: SavingsAccount) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Full balance")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(account.fullBalanceValue)
                .accessibilityIdentifier("savings.account.balance")
            if account.isJoint,
               let percentage = account.ownershipPercentage,
               let userShare = account.userShare
            {
                Text("Your share (\(MoneyFormatter.percentage(percentage))): \(MoneyFormatter.gbp(userShare))")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            HStack(spacing: FynlaSpacing.xSmall) {
                if account.isEmergencyFund { tag("Emergency fund") }
                if account.isISA { tag("ISA") }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func tag(_ title: String) -> some View {
        Text(title)
            .font(FynlaTypography.caption)
            .foregroundStyle(FynlaColor.focus)
            .padding(.horizontal, FynlaSpacing.xSmall)
            .padding(.vertical, FynlaSpacing.micro)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(Capsule())
    }

    private func detailCard<Content: View>(
        title: String,
        @ViewBuilder content: () -> Content
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            content()
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func detailRow(_ key: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.small) {
            Text(key)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Spacer()
            Text(value)
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, FynlaSpacing.xSmall)
        .overlay(alignment: .bottom) { Divider() }
        .accessibilityElement(children: .combine)
    }

    private func annualInterest(_ account: SavingsAccount) -> Decimal {
        account.fullBalanceValue * (account.interestRate ?? 0) / 100
    }

    private func monthlyInterest(_ account: SavingsAccount) -> Decimal {
        annualInterest(account) / 12
    }

    private func accountTypeLabel(_ value: String?) -> String {
        labels(value, map: [
            "savings_account": "Savings account",
            "current_account": "Current account",
            "easy_access": "Easy access",
            "notice": "Notice account",
            "fixed": "Fixed term",
            "cash_isa": "Cash ISA",
            "lisa": "Lifetime ISA",
        ])
    }

    private func accessTypeLabel(_ value: String?) -> String {
        labels(value, map: [
            "immediate": "Immediate access",
            "instant": "Instant access",
            "notice": "Notice required",
            "fixed": "Fixed term",
        ])
    }

    private func isaTypeLabel(_ value: String?) -> String {
        labels(value, map: [
            "cash": "Cash ISA",
            "stocks_and_shares": "Stocks & Shares ISA",
            "lifetime": "Lifetime ISA",
            "LISA": "Lifetime ISA",
            "lisa": "Lifetime ISA",
            "junior": "Junior ISA",
            "junior_isa": "Junior ISA",
            "innovative_finance": "Innovative Finance ISA",
        ])
    }

    private func ownershipLabel(_ value: String) -> String {
        labels(value, map: [
            "individual": "Individual",
            "joint": "Joint",
            "tenants_in_common": "Tenants in common",
            "trust": "Trust",
        ])
    }

    private func labels(_ value: String?, map: [String: String]) -> String {
        guard let value, !value.isEmpty else { return "—" }
        return map[value] ?? value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private func dateLabel(_ value: String) -> String {
        let input = DateFormatter()
        input.locale = Locale(identifier: "en_US_POSIX")
        input.dateFormat = "yyyy-MM-dd"
        guard let date = input.date(from: value) else { return "—" }
        let output = DateFormatter()
        output.locale = Locale(identifier: "en_GB")
        output.dateFormat = "dd/MM/yyyy"
        return output.string(from: date)
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.loadAccount(id: accountID) } } : nil
        )
    }
}
