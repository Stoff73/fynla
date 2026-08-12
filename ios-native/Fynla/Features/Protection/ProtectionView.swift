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
    @State private var expandedGapID: String?

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

                MobilePageActions(actionTitle: "Add policy", editDetails: {
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
                    gapsCard(snapshot)
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
    private func gapsCard(_ snapshot: ProtectionSnapshot) -> some View {
        let gaps = snapshot.openGaps
        return VStack(alignment: .leading, spacing: 0) {
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
                    gapRow(
                        gap,
                        calculatedAt: snapshot.calculatedAt,
                        showsDivider: gap.id != gaps.last?.id
                    )
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
        calculatedAt: String?,
        showsDivider: Bool
    ) -> some View {
        let severity = severityStyle(gap.severity)
        return Button {
            withAnimation(.easeInOut(duration: 0.2)) {
                expandedGapID = expandedGapID == gap.id ? nil : gap.id
            }
        } label: {
            VStack(alignment: .leading, spacing: 6) {
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
                    Image(systemName: expandedGapID == gap.id ? "chevron.up" : "chevron.down")
                        .font(.system(size: 10, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.neutral500.color)
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

                if expandedGapID == gap.id {
                    gapExplanation(gap, calculatedAt: calculatedAt)
                        .padding(.top, 8)
                }
            }
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .padding(.vertical, 12)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.horizon100.color.frame(height: 1)
            }
        }
        .accessibilityIdentifier("protection.gap.\(gap.id)")
    }

    private func gapExplanation(
        _ gap: ProtectionGapSummary,
        calculatedAt: String?
    ) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            if !gap.explanation.isEmpty {
                Text(gap.explanation)
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
            }

            if !gap.inputs.isEmpty {
                detailTitle("Inputs")
                ForEach(gap.inputs.keys.sorted(), id: \.self) { key in
                    detailRow(
                        titleCase(key),
                        displayValue(gap.inputs[key] ?? .null, key: key)
                    )
                }
            }

            if !gap.assumptions.isEmpty {
                detailTitle("Assumptions")
                ForEach(gap.assumptions, id: \.key) { assumption in
                    detailRow(
                        titleCase(assumption.key),
                        displayValue(
                            assumption.value,
                            key: assumption.key,
                            unit: assumption.unit
                        )
                    )
                }
            }

            if !gap.relevantPolicies.isEmpty {
                detailTitle("Related policies")
                ForEach(gap.relevantPolicies) { policy in
                    detailRow(
                        policy.provider ?? policy.name ?? titleCase(policy.type),
                        MoneyFormatter.gbpWhole(policy.cover)
                    )
                }
            }

            if let calculatedAt {
                Text("Calculated \(dateTimeLabel(calculatedAt)) from your recorded financial information.")
                    .font(.system(size: 11))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }
        }
        .padding(12)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.Token.lightBlue100.color.opacity(0.55))
        .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
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

    // Severity is supplied by the canonical protection contract. The client
    // maps labels to colours but never derives severity from the amount.
    private func severityStyle(
        _ value: String
    ) -> (label: String, foreground: Color, background: Color) {
        switch value.lowercased() {
        case "high", "critical":
            return ("High", .white, FynlaColor.Token.raspberry500.color)
        case "medium":
            return ("Medium", FynlaColor.Token.violet500.color, FynlaColor.Token.lightBlue100.color)
        case "low":
            return ("Low", FynlaColor.Token.spring600.color, FynlaColor.Token.spring500.color.opacity(0.12))
        case "none", "covered":
            return ("Covered", FynlaColor.Token.spring600.color, FynlaColor.Token.spring500.color.opacity(0.12))
        default:
            return ("Review", FynlaColor.Token.violet500.color, FynlaColor.Token.lightBlue100.color)
        }
    }

    private func detailTitle(_ value: String) -> some View {
        Text(value.uppercased())
            .font(.system(size: 10, weight: .bold))
            .kerning(0.4)
            .foregroundStyle(FynlaColor.Token.neutral500.color)
            .padding(.top, 2)
    }

    private func detailRow(_ key: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key)
                .font(.system(size: 12))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            Spacer(minLength: 8)
            Text(value)
                .font(.system(size: 12, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
                .multilineTextAlignment(.trailing)
        }
    }

    private func displayValue(
        _ value: ProtectionJSONValue,
        key: String,
        unit: String? = nil
    ) -> String {
        switch value {
        case let .string(value):
            value
        case let .number(value):
            if unit?.lowercased() == "percent" {
                MoneyFormatter.percentage(value)
            } else if unit?.uppercased() == "GBP" || isMoneyInput(key) {
                MoneyFormatter.gbpWhole(value)
            } else {
                NSDecimalNumber(decimal: value).stringValue
            }
        case let .bool(value):
            value ? "Yes" : "No"
        case let .array(values):
            values.map { displayValue($0, key: key) }.joined(separator: ", ")
        case let .object(values):
            values.keys.sorted().map {
                "\(titleCase($0)): \(displayValue(values[$0] ?? .null, key: $0))"
            }.joined(separator: ", ")
        case .null:
            "Unavailable"
        }
    }

    private func isMoneyInput(_ key: String) -> Bool {
        ["income", "debt", "mortgage", "cover", "need", "expense", "capital"]
            .contains { key.lowercased().contains($0) }
    }

    private func titleCase(_ value: String) -> String {
        value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    private func dateTimeLabel(_ value: String) -> String {
        guard let date = ISO8601DateFormatter().date(from: value) else { return value }
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_GB")
        formatter.dateStyle = .medium
        formatter.timeStyle = .short
        return formatter.string(from: date)
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
