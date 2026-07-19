import SwiftUI

struct ProtectionPolicyView: View {
    let policyTypeKey: String
    let policyID: Int
    let model: ProtectionModel
    let onOpenFyn: (String) -> Void

    var body: some View {
        Group {
            if let type = ProtectionPolicyType(rawValue: policyTypeKey) {
                stateContent(type)
            } else {
                notFound
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Protection")
        .navigationBarTitleDisplayMode(.inline)
        .task(id: "\(policyTypeKey)-\(policyID)") { await model.load() }
        .accessibilityIdentifier("protection.policy.screen")
    }

    @ViewBuilder
    private func stateContent(_ type: ProtectionPolicyType) -> some View {
        switch model.state {
        case .idle, .loading:
            ScreenStateView(state: .loading)
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
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(policy.provider ?? "Policy")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text(type.label)
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline {
                    Text("You're offline. Showing the last loaded policy values.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.primaryText)
                }
                hero(type, policy: policy)
                policyDetails(type, policy: policy)
                coverageDetails(type, policy: policy)
                premiumDetails(policy)
                importantDates(policy)
                if type == .life,
                   let beneficiaries = policy.beneficiaries,
                   !beneficiaries.isEmpty
                {
                    textCard("Beneficiaries", beneficiaries)
                }
                if let conditions = policy.conditionsCovered, !conditions.isEmpty {
                    conditionsCard(conditions)
                }
                Button("Update this policy with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "protection cover",
                            addPhrase: "I'd like to add a protection policy.",
                            names: [policy.provider ?? type.label]
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("protection.policy.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
    }

    private func hero(
        _ type: ProtectionPolicyType,
        policy: ProtectionPolicy
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(type.isLumpSum ? "Sum assured" : "Benefit amount")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(policy.coverageAmount(for: type))
                .accessibilityIdentifier("protection.policy.cover")
            Text(heroSubtitle(policy))
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func policyDetails(
        _ type: ProtectionPolicyType,
        policy: ProtectionPolicy
    ) -> some View {
        detailCard("Policy details") {
            detailRow("Provider", policy.provider ?? "—")
            detailRow("Policy number", policy.policyNumber ?? "—")
            detailRow("Policy type", type.label)
            if type == .life, let subtype = policy.policyType {
                detailRow("Life policy type", lifeSubtypeLabel(subtype))
            }
        }
    }

    private func coverageDetails(
        _ type: ProtectionPolicyType,
        policy: ProtectionPolicy
    ) -> some View {
        detailCard("Coverage") {
            detailRow(
                type.isLumpSum ? "Sum assured" : "Benefit amount",
                money(policy.coverageAmount(for: type))
            )
            if !type.isLumpSum, let frequency = policy.benefitFrequency {
                detailRow("Benefit frequency", frequencyLabel(frequency))
            }
            if let weeks = policy.deferredPeriodWeeks, weeks > 0 {
                detailRow("Deferred period", "\(weeks) weeks")
            }
            if let months = policy.benefitPeriodMonths, months > 0 {
                detailRow("Benefit period", "\(months) months")
            }
            if let occupation = policy.occupationClass, !occupation.isEmpty {
                detailRow("Occupation class", occupation)
            }
            if let coverage = policy.coverageType, !coverage.isEmpty {
                detailRow("Coverage type", titleCase(coverage))
            }
            if policy.isMortgageProtection == true {
                detailRow("Mortgage protection", "Yes")
            }
            if policy.inTrust == true {
                detailRow("Held in trust", "Yes")
            }
        }
    }

    private func premiumDetails(_ policy: ProtectionPolicy) -> some View {
        detailCard("Premium") {
            detailRow("Premium amount", money(policy.premiumAmount))
            detailRow("Frequency", titleCase(policy.premiumFrequency ?? ""))
            detailRow("Annual cost", money(policy.annualPremium))
        }
    }

    @ViewBuilder
    private func importantDates(_ policy: ProtectionPolicy) -> some View {
        if policy.policyStartDate != nil
            || policy.policyEndDate != nil
            || policy.policyTermYears != nil
        {
            detailCard("Important dates") {
                if let start = policy.policyStartDate {
                    detailRow("Start date", dateLabel(start))
                }
                if let term = policy.policyTermYears {
                    detailRow("Term", "\(term) years")
                }
                if let end = policy.policyEndDate {
                    detailRow("End date", dateLabel(end))
                }
            }
        }
    }

    private func textCard(_ title: String, _ text: String) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text(text)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.primaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func conditionsCard(_ conditions: [String]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Covered conditions")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)
            ForEach(Array(conditions.enumerated()), id: \.offset) { _, condition in
                Text(condition)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.primaryText)
                    .padding(.vertical, FynlaSpacing.xSmall)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .overlay(alignment: .bottom) { Divider() }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func detailCard<Content: View>(
        _ title: String,
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
    }

    private func heroSubtitle(_ policy: ProtectionPolicy) -> String {
        let premium = money(policy.premiumAmount)
        let frequency = policy.premiumFrequency ?? "monthly"
        return "\(premium) \(frequency) premium, \(money(policy.annualPremium)) a year"
    }

    private func lifeSubtypeLabel(_ value: String) -> String {
        switch value {
        case "decreasing_term": "Decreasing Term"
        case "level_term": "Level Term"
        case "whole_of_life": "Whole of Life"
        case "term": "Term"
        case "family_income_benefit": "Family Income Benefit"
        default: titleCase(value)
        }
    }

    private func frequencyLabel(_ value: String) -> String {
        switch value {
        case "monthly": "Monthly"
        case "weekly": "Weekly"
        case "annually": "Annually"
        case "lump_sum": "Lump sum"
        default: titleCase(value)
        }
    }

    private func titleCase(_ value: String) -> String {
        guard !value.isEmpty else { return "—" }
        return value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbp) ?? "—"
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

    private var notFound: some View {
        ScreenStateView(state: .empty(message: "We could not find that policy."))
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil
        )
    }
}
