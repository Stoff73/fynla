import SwiftUI

// Transcribes /m's savings account sub-page (resources/mobile/views/modules/
// SavingsAccount.vue): gradient page hero, Back + Edit details pills, provider
// identity card, dark full-balance hero with joint share + ISA/emergency-fund
// tags, and the Balance & interest / Account information / ISA details row
// cards. Whole-pound amounts; interest maths is /m-parity (ledger P0-1).
struct SavingsAccountView: View {
    let accountID: Int
    let model: SavingsModel
    let onOpenFyn: (String) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            switch model.accountState {
            case .idle, .loading:
                DashboardLoadingView(message: "Loading this account…")
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
        .task(id: accountID) { await model.loadAccount(id: accountID) }
        .accessibilityIdentifier("savings.account.screen")
    }

    private func content(_ account: SavingsAccount) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Savings and emergency fund",
                    subtitle: "Your cash, emergency-fund runway and ISA allowance"
                )

                MobilePageActions(
                    onBack: { dismiss() },
                    editDetails: { onOpenFyn("What would you like to update?") }
                )

                Group {
                    MobileDetailHeader(
                        title: account.provider ?? account.institution ?? "Savings account",
                        subtitle: accountTypeLabel(account.accountType)
                    )

                    hero(account)

                    MobileDetailCard(title: "Balance & interest", rows: balanceRows(account))
                    MobileDetailCard(title: "Account information", rows: infoRows(account))
                    if account.isISA {
                        MobileDetailCard(title: "ISA details", rows: isaRows(account))
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    // m-hero — dark card: full balance + joint share + tags.
    private func hero(_ account: SavingsAccount) -> some View {
        MobileHeroCard(
            label: "Full balance",
            metric: MoneyFormatter.gbpWhole(account.fullBalanceValue),
            sub: account.isJoint
                ? account.ownershipPercentage.map {
                    "Your share (\(MoneyFormatter.percentage($0))): \(MoneyFormatter.gbpWhole(userShare(account)))"
                }
                : nil
        ) {
            if account.isEmergencyFund || account.isISA {
                HStack(spacing: 6) {
                    if account.isEmergencyFund {
                        tag(
                            "Emergency fund",
                            color: FynlaColor.Token.spring600.color,
                            background: FynlaColor.Token.spring500.color.opacity(0.12)
                        )
                    }
                    if account.isISA {
                        tag(
                            "ISA",
                            color: FynlaColor.Token.violet500.color,
                            background: FynlaColor.Token.violet500.color.opacity(0.12)
                        )
                    }
                }
                .padding(.top, 12)
            }
        }
        .accessibilityIdentifier("savings.account.balance")
    }

    private func tag(_ title: String, color: Color, background: Color) -> some View {
        Text(title)
            .font(.system(size: 11, weight: .bold))
            .foregroundStyle(color)
            .padding(.horizontal, 8)
            .padding(.vertical, 2)
            .background(background)
            .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
    }

    private func balanceRows(_ account: SavingsAccount) -> [(key: String, value: String)] {
        var rows: [(key: String, value: String)] = [
            ("Full balance", MoneyFormatter.gbpWhole(account.fullBalanceValue)),
        ]
        if account.isJoint, let percentage = account.ownershipPercentage {
            rows.append((
                "Your share (\(MoneyFormatter.percentage(percentage)))",
                MoneyFormatter.gbpWhole(userShare(account))
            ))
        }
        rows.append(("Interest rate", rateLabel(account.interestRate)))
        rows.append(("Monthly interest", MoneyFormatter.gbpWhole(annualInterest(account) / 12)))
        rows.append(("Annual interest", MoneyFormatter.gbpWhole(annualInterest(account))))
        return rows
    }

    private func infoRows(_ account: SavingsAccount) -> [(key: String, value: String)] {
        var rows: [(key: String, value: String)] = [
            ("Provider", account.provider ?? account.institution ?? "—"),
            ("Account type", accountTypeLabel(account.accountType)),
            ("Access", accessTypeLabel(account.accessType)),
        ]
        if account.accessType == "notice", let days = account.noticePeriodDays {
            rows.append(("Notice period", "\(days) days"))
        }
        if account.accessType == "fixed", let maturity = account.maturityDate {
            rows.append(("Maturity date", dateLabel(maturity)))
            rows.append(("Time to maturity", timeToMaturity(maturity)))
        }
        if let country = account.country, !country.isEmpty {
            rows.append(("Country", country))
        }
        if let ownership = account.ownershipType {
            rows.append(("Ownership", ownershipLabel(ownership)))
        }
        return rows
    }

    private func isaRows(_ account: SavingsAccount) -> [(key: String, value: String)] {
        var rows: [(key: String, value: String)] = [
            ("ISA type", isaTypeLabel(account.isaType)),
        ]
        if let year = account.isaSubscriptionYear, !year.isEmpty {
            rows.append(("Subscription year", year))
        }
        if let amount = account.isaSubscriptionAmount {
            rows.append(("Subscribed this year", MoneyFormatter.gbpWhole(amount)))
        }
        rows.append(("Interest", "Tax-free"))
        return rows
    }

    // /m userShare: explicit user_share, else joint split, else full balance.
    private func userShare(_ account: SavingsAccount) -> Decimal {
        if let share = account.userShare { return share }
        if account.isJoint, let percentage = account.ownershipPercentage {
            return account.fullBalanceValue * percentage / 100
        }
        return account.fullBalanceValue
    }

    // ponytail: client-side simple-interest estimate is /m-parity (ledger P0-1).
    private func annualInterest(_ account: SavingsAccount) -> Decimal {
        account.fullBalanceValue * (account.interestRate ?? 0) / 100
    }

    private func rateLabel(_ rate: Decimal?) -> String {
        guard let rate else { return "—" }
        return String(format: "%.2f%%", NSDecimalNumber(decimal: rate).doubleValue)
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

    private func parsedDate(_ value: String) -> Date? {
        let input = DateFormatter()
        input.locale = Locale(identifier: "en_US_POSIX")
        input.dateFormat = "yyyy-MM-dd"
        return input.date(from: value) ?? ISO8601DateFormatter().date(from: value)
    }

    private func dateLabel(_ value: String) -> String {
        guard let date = parsedDate(value) else { return "—" }
        let output = DateFormatter()
        output.locale = Locale(identifier: "en_GB")
        output.dateFormat = "dd/MM/yyyy"
        return output.string(from: date)
    }

    // /m timeToMaturity: days under a month, else months, else years+months.
    private func timeToMaturity(_ value: String) -> String {
        guard let date = parsedDate(value) else { return "—" }
        let days = Int(ceil(date.timeIntervalSinceNow / 86_400))
        if days <= 0 { return "Matured" }
        if days < 31 { return "\(days) days" }
        let months = Int(ceil(Double(days) / 30.44))
        let years = months / 12
        let remainder = months % 12
        if years == 0 { return "\(remainder) \(remainder == 1 ? "month" : "months")" }
        if remainder == 0 { return "\(years) \(years == 1 ? "year" : "years")" }
        return "\(years) \(years == 1 ? "year" : "years"), \(remainder) \(remainder == 1 ? "month" : "months")"
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.loadAccount(id: accountID) } } : nil
        )
    }
}
