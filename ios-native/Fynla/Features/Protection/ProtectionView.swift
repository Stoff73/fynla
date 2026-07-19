import SwiftUI

struct ProtectionView: View {
    let model: ProtectionModel
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
                if let previous { content(previous, offline: true) }
                else { stateView(.offline) }
            case .unauthenticated:
                stateView(.unauthenticated)
            case let .upgradeRequired(message):
                stateView(.upgradeRequired(message: message))
            case let .failed(requestID):
                stateView(.failed(requestID: requestID))
            }
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Protection")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("protection.screen")
    }

    private func content(
        _ snapshot: ProtectionSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Protection")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Your insurance cover and the gaps that remain")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline { offlineNotice }
                hero(snapshot)
                gapsCard(snapshot.openGaps)
                policiesCard(snapshot.policies)
                if let recommendations = snapshot.analysis?.recommendations,
                   !recommendations.isEmpty
                {
                    recommendationsCard(recommendations)
                }
                Button("Update protection cover with Fyn") {
                    onOpenFyn(
                        FynEditIntent.message(
                            updateScope: "protection cover",
                            addPhrase: "I'd like to add a protection policy.",
                            names: snapshot.policies.map {
                                Optional($0.policy.provider ?? $0.type.label)
                            }
                        )
                    )
                }
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .frame(maxWidth: .infinity, minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("protection.edit-with-fyn")
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    private func hero(_ snapshot: ProtectionSnapshot) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Total lump-sum cover")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(snapshot.totalLumpSumCover)
                .accessibilityIdentifier("protection.total-cover")
            Text(heroSubtitle(snapshot))
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func gapsCard(_ gaps: [ProtectionGapSummary]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Coverage gaps")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)
            if gaps.isEmpty {
                Text("No shortfalls were returned by your protection analysis.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(gaps) { gap in
                    VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                        HStack(alignment: .firstTextBaseline) {
                            Text(gap.label)
                                .font(FynlaTypography.heading)
                                .foregroundStyle(FynlaColor.primaryText)
                            Spacer()
                            Text("Shortfall")
                                .font(FynlaTypography.caption)
                                .foregroundStyle(FynlaColor.primaryAction)
                        }
                        HStack(alignment: .firstTextBaseline) {
                            Text("\(MoneyFormatter.gbp(gap.shortfall))\(gap.perYear ? " a year" : "") short")
                                .font(FynlaTypography.bodySmall)
                                .foregroundStyle(FynlaColor.primaryAction)
                            Spacer()
                            if let have = gap.have, let need = gap.need {
                                Text("\(MoneyFormatter.gbp(have)) of \(MoneyFormatter.gbp(need))")
                                    .font(FynlaTypography.caption)
                                    .foregroundStyle(FynlaColor.secondaryText)
                            }
                        }
                    }
                    .padding(.vertical, FynlaSpacing.medium)
                    .overlay(alignment: .bottom) { Divider() }
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func policiesCard(_ policies: [ProtectionPolicyItem]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Policies")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)
            if policies.isEmpty {
                Text("You have no protection policies recorded. Adding cover protects your family's income and debts if something happens to you.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .padding(.vertical, FynlaSpacing.small)
            } else {
                ForEach(policies) { item in
                    Button {
                        onRoute(.protection(policyType: item.type.rawValue, id: item.policy.id))
                    } label: {
                        HStack(alignment: .center, spacing: FynlaSpacing.medium) {
                            VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                                Text(item.policy.provider ?? "Unknown provider")
                                    .font(FynlaTypography.heading)
                                    .foregroundStyle(FynlaColor.primaryText)
                                Text(item.type.label)
                                    .font(FynlaTypography.caption)
                                    .foregroundStyle(FynlaColor.secondaryText)
                            }
                            Spacer()
                            VStack(alignment: .trailing, spacing: FynlaSpacing.micro) {
                                Text(coverDisplay(item))
                                    .font(FynlaTypography.heading)
                                    .foregroundStyle(FynlaColor.primaryText)
                                Text(premiumDisplay(item.policy))
                                    .font(FynlaTypography.caption)
                                    .foregroundStyle(FynlaColor.secondaryText)
                            }
                        }
                        .padding(.vertical, FynlaSpacing.medium)
                        .contentShape(Rectangle())
                    }
                    .buttonStyle(.plain)
                    .overlay(alignment: .bottom) { Divider() }
                    .accessibilityIdentifier("protection.policy.\(item.id)")
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func recommendationsCard(
        _ recommendations: [ProtectionRecommendation]
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Recommended actions")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            ForEach(Array(recommendations.enumerated()), id: \.offset) { _, recommendation in
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(recommendation.action ?? recommendation.category ?? "Recommendation")
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    if let rationale = recommendation.rationale {
                        Text(rationale)
                            .font(FynlaTypography.bodySmall)
                            .foregroundStyle(FynlaColor.secondaryText)
                    }
                }
                .padding(.vertical, FynlaSpacing.small)
                .overlay(alignment: .bottom) { Divider() }
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func heroSubtitle(_ snapshot: ProtectionSnapshot) -> String {
        let count = snapshot.policies.count
        var value = "Across \(count) \(count == 1 ? "policy" : "policies")."
        if let income = snapshot.annualIncomeCover, income > 0 {
            value += " Plus \(MoneyFormatter.gbp(income)) a year of income-style cover."
        }
        return value
    }

    private func coverDisplay(_ item: ProtectionPolicyItem) -> String {
        guard let amount = item.policy.coverageAmount(for: item.type) else { return "—" }
        if item.type.isLumpSum { return MoneyFormatter.gbp(amount) }
        return "\(MoneyFormatter.gbp(amount)) / \(shortFrequency(item.policy.benefitFrequency))"
    }

    private func premiumDisplay(_ policy: ProtectionPolicy) -> String {
        guard let premium = policy.premiumAmount else { return "No premium recorded" }
        return "\(MoneyFormatter.gbp(premium)) / \(shortFrequency(policy.premiumFrequency))"
    }

    private func shortFrequency(_ value: String?) -> String {
        switch value {
        case "monthly": "mo"
        case "weekly": "wk"
        case "quarterly": "qtr"
        case "annually", "annual", "yearly": "yr"
        case let value?: value
        case nil: "mo"
        }
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded protection position.")
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
