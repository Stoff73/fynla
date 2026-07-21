import SwiftUI

// Transcribes /m's protection policy sub-page (resources/mobile/views/
// modules/ProtectionPolicy.vue): gradient page hero, Back + Edit details
// pills, provider identity card, dark cover hero with the premium sub-line,
// and the Policy details / Coverage / Premium / Important dates /
// Beneficiaries / Covered conditions cards. Whole-pound amounts; the ×12
// default annual-premium fallback is /m-parity (ledger P0-5).
struct ProtectionPolicyView: View {
    let policyTypeKey: String
    let policyID: Int
    let model: ProtectionModel
    let onOpenFyn: (String) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            if let type = ProtectionPolicyType(rawValue: policyTypeKey) {
                stateContent(type)
            } else {
                notFound
            }
        }
        .background(FynlaColor.pageBackground)
        .task(id: "\(policyTypeKey)-\(policyID)") { await model.load() }
        .accessibilityIdentifier("protection.policy.screen")
    }

    @ViewBuilder
    private func stateContent(_ type: ProtectionPolicyType) -> some View {
        switch model.state {
        case .idle, .loading:
            DashboardLoadingView(message: "Loading this policy…")
        case let .loaded(snapshot):
            if let policy = snapshot.policy(type: type, id: policyID) {
                content(type, policy: policy)
            } else {
                notFound
            }
        case let .offline(previous):
            if let policy = previous?.policy(type: type, id: policyID) {
                content(type, policy: policy, offline: true)
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

    private func content(
        _ type: ProtectionPolicyType,
        policy: ProtectionPolicy,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Protection",
                    subtitle: "Your insurance cover and the gaps that remain"
                )

                MobilePageActions(
                    onBack: { dismiss() },
                    editDetails: { onOpenFyn("What would you like to update?") }
                )

                Group {
                    MobileDetailHeader(
                        title: policy.provider ?? "Policy",
                        subtitle: type.label
                    )

                    if offline {
                        offlineNotice
                    }

                    hero(type, policy: policy)

                    MobileDetailCard(title: "Policy details", rows: policyRows(type, policy: policy))
                    MobileDetailCard(title: "Coverage", rows: coverageRows(type, policy: policy))
                    MobileDetailCard(title: "Premium", rows: premiumRows(policy))

                    if policy.policyStartDate != nil
                        || policy.policyTermYears != nil
                        || policy.policyEndDate != nil
                    {
                        MobileDetailCard(title: "Important dates", rows: dateRows(policy))
                    }

                    if type == .life,
                       let beneficiaries = policy.beneficiaries,
                       !beneficiaries.isEmpty
                    {
                        textCard("Beneficiaries", beneficiaries)
                    }

                    if let conditions = policy.conditionsCovered, !conditions.isEmpty {
                        conditionsCard(conditions)
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    // m-hero — dark card: cover amount + premium sub-line.
    private func hero(
        _ type: ProtectionPolicyType,
        policy: ProtectionPolicy
    ) -> some View {
        MobileHeroCard(
            label: type.isLumpSum ? "Sum assured" : "Benefit amount",
            metric: money(policy.coverageAmount(for: type) ?? 0),
            sub: "\(money(policy.premiumAmount)) \(policy.premiumFrequency ?? "monthly") premium, \(money(policy.annualPremium)) a year"
        )
        .accessibilityIdentifier("protection.policy.cover")
    }

    private func policyRows(
        _ type: ProtectionPolicyType,
        policy: ProtectionPolicy
    ) -> [(key: String, value: String)] {
        var rows: [(key: String, value: String)] = [
            ("Provider", policy.provider ?? "—"),
            ("Policy number", policy.policyNumber ?? "—"),
            ("Policy type", type.label),
        ]
        if type == .life, let subtype = policy.policyType, !subtype.isEmpty {
            rows.append(("Life policy type", lifeSubtypeLabel(subtype)))
        }
        return rows
    }

    private func coverageRows(
        _ type: ProtectionPolicyType,
        policy: ProtectionPolicy
    ) -> [(key: String, value: String)] {
        var rows: [(key: String, value: String)] = [
            (
                type.isLumpSum ? "Sum assured" : "Benefit amount",
                money(policy.coverageAmount(for: type) ?? 0)
            ),
        ]
        if !type.isLumpSum, let frequency = policy.benefitFrequency {
            rows.append(("Benefit frequency", frequencyLabel(frequency)))
        }
        if let weeks = policy.deferredPeriodWeeks, weeks > 0 {
            rows.append(("Deferred period", "\(weeks) weeks"))
        }
        if let months = policy.benefitPeriodMonths, months > 0 {
            rows.append(("Benefit period", "\(months) months"))
        }
        if let occupation = policy.occupationClass, !occupation.isEmpty {
            rows.append(("Occupation class", occupation))
        }
        if let coverage = policy.coverageType, !coverage.isEmpty {
            rows.append(("Coverage type", coverage))
        }
        if policy.isMortgageProtection == true {
            rows.append(("Mortgage protection", "Yes"))
        }
        if policy.inTrust == true {
            rows.append(("Held in trust", "Yes"))
        }
        return rows
    }

    private func premiumRows(_ policy: ProtectionPolicy) -> [(key: String, value: String)] {
        [
            ("Premium amount", money(policy.premiumAmount)),
            ("Frequency", capitalise(policy.premiumFrequency)),
            // ponytail: ×12 default annual cost is /m-parity (ledger P0-5).
            ("Annual cost", money(policy.annualPremium)),
        ]
    }

    private func dateRows(_ policy: ProtectionPolicy) -> [(key: String, value: String)] {
        var rows: [(key: String, value: String)] = []
        if let start = policy.policyStartDate {
            rows.append(("Start date", dateLabel(start)))
        }
        if let term = policy.policyTermYears {
            rows.append(("Term", "\(term) years"))
        }
        if let end = policy.policyEndDate {
            rows.append(("End date", dateLabel(end)))
        }
        return rows
    }

    // mpd-text — free text block under a section label.
    private func textCard(_ title: String, _ text: String) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionLabel(title)
                .padding(.bottom, 12)
            Text(text)
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
                .lineSpacing(3)
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mpd-list — hairline-separated covered conditions.
    private func conditionsCard(_ conditions: [String]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            sectionLabel("Covered conditions")
                .padding(.bottom, 4)
            ForEach(Array(conditions.enumerated()), id: \.offset) { index, condition in
                Text(condition)
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .padding(.vertical, 8)
                    .overlay(alignment: .bottom) {
                        if index != conditions.count - 1 {
                            FynlaColor.Token.horizon100.color.frame(height: 1)
                        }
                    }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func sectionLabel(_ text: String) -> some View {
        Text(text.uppercased())
            .font(.system(size: 12, weight: .bold))
            .kerning(0.5)
            .foregroundStyle(FynlaColor.Token.neutral500.color)
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbpWhole) ?? "—"
    }

    private func capitalise(_ value: String?) -> String {
        guard let value, !value.isEmpty else { return "—" }
        return value.prefix(1).uppercased() + value.dropFirst()
    }

    private func frequencyLabel(_ value: String) -> String {
        switch value {
        case "monthly": "Monthly"
        case "weekly": "Weekly"
        case "annually": "Annually"
        case "lump_sum": "Lump sum"
        default: capitalise(value)
        }
    }

    private func lifeSubtypeLabel(_ value: String) -> String {
        switch value {
        case "decreasing_term": "Decreasing Term"
        case "level_term": "Level Term"
        case "whole_of_life": "Whole of Life"
        case "term": "Term"
        case "family_income_benefit": "Family Income Benefit"
        default: value
        }
    }

    private func dateLabel(_ value: String) -> String {
        let input = DateFormatter()
        input.locale = Locale(identifier: "en_US_POSIX")
        input.dateFormat = "yyyy-MM-dd"
        guard let date = input.date(from: value) ?? ISO8601DateFormatter().date(from: value) else {
            return "—"
        }
        let output = DateFormatter()
        output.locale = Locale(identifier: "en_GB")
        output.dateFormat = "dd/MM/yyyy"
        return output.string(from: date)
    }

    private var notFound: some View {
        ScreenStateView(state: .empty(message: "We could not find that policy."))
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing the last loaded policy values.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil
        )
    }
}
