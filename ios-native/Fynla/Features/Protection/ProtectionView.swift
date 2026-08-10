import SwiftUI

// Transcribes /m's Protection page (resources/mobile/views/modules/
// Protection.vue): gradient page hero, Edit details pill (rich /m edit
// prompt), lump-sum cover hero card, coverage gaps with /m's severity tags
// (Low/Medium violet, High raspberry), and tappable policy rows. Whole-pound
// amounts as /m's formatCurrency.
struct ProtectionView: View {
    let model: ProtectionModel
    let onRoute: (AppRoute) -> Void
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your protection position…") }
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
        .task { await model.load() }
        .accessibilityIdentifier("protection.screen")
    }

    private func content(
        _ snapshot: ProtectionSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Protection",
                    subtitle: "Your insurance cover and the gaps that remain"
                )

                MobilePageActions(editDetails: {
                    onOpenContextualFyn(
                        FynContextualActions.protectionOverview(
                            hasPolicies: !snapshot.policies.isEmpty
                        )
                    )
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    heroCard(snapshot)
                    gapsCard(snapshot.openGaps)
                    policiesCard(snapshot.policies)
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // m-hero: dark card with the big metric + policy-count sub-line.
    private func heroCard(_ snapshot: ProtectionSnapshot) -> some View {
        MobileHeroCard(
            label: "Total lump-sum cover",
            metric: MoneyFormatter.gbpWhole(snapshot.totalLumpSumCover ?? 0),
            sub: heroSubtitle(snapshot)
        )
        .accessibilityIdentifier("protection.total-cover")
    }

    // mp-gap rows: label + severity tag head, raspberry shortfall + detail foot.
    private func gapsCard(_ gaps: [ProtectionGapSummary]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Coverage gaps".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)

            if gaps.isEmpty {
                Text("No shortfalls identified against your debts and income. Your cover looks well-matched to your needs.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.vertical, 8)
            } else {
                ForEach(gaps) { gap in
                    gapRow(gap, showsDivider: gap.id != gaps.last?.id)
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func gapRow(
        _ gap: ProtectionGapSummary,
        showsDivider: Bool
    ) -> some View {
        let severity = severity(for: gap.shortfall)
        return VStack(alignment: .leading, spacing: 6) {
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                Text(gap.label)
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer()
                Text(severity.label.uppercased())
                    .font(.system(size: 11, weight: .bold))
                    .kerning(0.5)
                    .foregroundStyle(severity.foreground)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 2)
                    .background(severity.background)
                    .clipShape(RoundedRectangle(cornerRadius: 6, style: .continuous))
            }
            HStack(alignment: .firstTextBaseline, spacing: 12) {
                Text("\(MoneyFormatter.gbpWhole(gap.shortfall))\(gap.perYear ? " a year" : "") short")
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                Spacer()
                if let have = gap.have, let need = gap.need {
                    Text("\(MoneyFormatter.gbpWhole(have)) of \(MoneyFormatter.gbpWhole(need))\(gap.perYear ? " p.a." : "")")
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
            }
        }
        .padding(.vertical, 12)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
    }

    private func policiesCard(_ policies: [ProtectionPolicyItem]) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Policies".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)

            if policies.isEmpty {
                Text("You have no protection policies recorded. Adding cover protects your family's income and debts if something happens to you.")
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .padding(.vertical, 8)
            } else {
                ForEach(policies) { item in
                    policyRow(
                        item,
                        showsDivider: item.id != policies.last?.id
                    )
                }
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // mp-policy: provider + type left, cover + premium right.
    private func policyRow(
        _ item: ProtectionPolicyItem,
        showsDivider: Bool
    ) -> some View {
        Button {
            onRoute(.protection(policyType: item.type.rawValue, id: item.policy.id))
        } label: {
            HStack(alignment: .center, spacing: 12) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(item.policy.provider ?? "Unknown provider")
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text(item.type.label)
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
                Spacer(minLength: 8)
                VStack(alignment: .trailing, spacing: 2) {
                    Text(coverDisplay(item))
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    Text(premiumDisplay(item.policy))
                        .font(.system(size: 12))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
                }
            }
            .padding(.vertical, 14)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
        .accessibilityIdentifier("protection.policy.\(item.id)")
    }

    // /m's severity(): Low < £50k, Medium < £150k (violet on light blue),
    // High otherwise (white on raspberry).
    private func severity(
        for shortfall: Decimal
    ) -> (label: String, foreground: Color, background: Color) {
        let value = NSDecimalNumber(decimal: shortfall).doubleValue
        if value < 50_000 {
            return ("Low", FynlaColor.Token.violet500.color, FynlaColor.Token.lightBlue100.color)
        }
        if value < 150_000 {
            return ("Medium", FynlaColor.Token.violet500.color, FynlaColor.Token.lightBlue100.color)
        }
        return ("High", .white, FynlaColor.Token.raspberry500.color)
    }

    private func heroSubtitle(_ snapshot: ProtectionSnapshot) -> String {
        let count = snapshot.policies.count
        var value = "Across \(count) \(count == 1 ? "policy" : "policies")."
        if let income = snapshot.annualIncomeCover, income > 0 {
            value += " Plus \(MoneyFormatter.gbpWhole(income)) a year of income-style cover."
        }
        return value
    }

    private func coverDisplay(_ item: ProtectionPolicyItem) -> String {
        guard let amount = item.policy.coverageAmount(for: item.type) else { return "—" }
        if item.type.isLumpSum { return MoneyFormatter.gbpWhole(amount) }
        return "\(MoneyFormatter.gbpWhole(amount)) / \(shortFrequency(item.policy.benefitFrequency))"
    }

    private func premiumDisplay(_ policy: ProtectionPolicy) -> String {
        guard let premium = policy.premiumAmount else { return "No premium recorded" }
        return "\(MoneyFormatter.gbpWhole(premium)) / \(shortFrequency(policy.premiumFrequency))"
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
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("protection.offline")
    }

    // /m's MobileChrome keeps the gradient page hero visible during
    // loading/error states — state screens render below it, not instead
    // of it (sweep: hero persistence).
    private func framed<Content: View>(@ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Protection",
                    subtitle: "Your insurance cover and the gaps that remain"
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
