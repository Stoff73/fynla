import SwiftUI

// Transcribes /m's Estate page (resources/mobile/views/modules/Estate.vue):
// gradient page hero, Edit details pill, teaser mode (estimated liability
// hero + Premium note + Compare plans pill) and full mode (estate value hero,
// breakdown rows with the bordered net-estate total, planning rows with the
// coloured will status). Whole-pound amounts as /m's formatCurrency.
struct EstateView: View {
    let model: EstateModel
    let onOpenFyn: (String) -> Void
    let onOpenSubscription: () -> Void

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                DashboardLoadingView(message: "Loading your estate position…")
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
        .accessibilityIdentifier("estate.screen")
    }

    private func content(
        _ snapshot: EstateSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Estate",
                    subtitle: "Inheritance tax exposure and planning"
                )

                MobilePageActions(editDetails: {
                    onOpenFyn("What would you like to update?")
                })

                Group {
                    if offline {
                        offlineNotice
                    }

                    switch snapshot.index.mode {
                    case .teaser:
                        teaser(snapshot.index)
                    case .full:
                        fullEstate(snapshot)
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    // Teaser (Free): liability hero + Premium note + Compare plans pill.
    @ViewBuilder
    private func teaser(_ index: EstateIndexResponse) -> some View {
        VStack(alignment: .leading, spacing: 6) {
            Text("Estimated Inheritance Tax liability")
                .font(.system(size: 13, weight: .semibold))
                .foregroundStyle(FynlaColor.Token.horizon400.color)
            Text(MoneyFormatter.gbpWhole(index.teaser?.estimatedLiability ?? 0))
                .font(.system(size: 28, weight: .heavy))
                .foregroundStyle(FynlaColor.Token.horizon600.color)
                .accessibilityIdentifier("estate.teaser.liability")
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))

        VStack(alignment: .leading, spacing: 12) {
            if let headline = index.teaser?.headline, !headline.isEmpty {
                Text(headline)
                    .font(.system(size: 14))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }
            Text("Full estate planning — assets, gifts, trusts, will and personalised Inheritance Tax planning — is part of Premium.")
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.neutral600.color)
            Button {
                onOpenSubscription()
            } label: {
                Text(index.cta?.label ?? "Compare plans")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(.white)
                    .padding(.horizontal, 20)
                    .padding(.vertical, 9)
                    .background(FynlaColor.Token.raspberry500.color)
                    .clipShape(Capsule())
            }
            .padding(.top, 4)
            .accessibilityIdentifier("estate.teaser.upgrade")
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // Full (Premium): estate value hero, breakdown and planning cards.
    @ViewBuilder
    private func fullEstate(_ snapshot: EstateSnapshot) -> some View {
        if let netWorth = snapshot.netWorth {
            VStack(alignment: .leading, spacing: 6) {
                Text("Estimated estate value")
                    .font(.system(size: 13, weight: .semibold))
                    .foregroundStyle(FynlaColor.Token.horizon400.color)
                Text(MoneyFormatter.gbpWhole(netWorth.netWorth))
                    .font(.system(size: 28, weight: .heavy))
                    .foregroundStyle(FynlaColor.Token.horizon600.color)
                    .accessibilityIdentifier("estate.net-estate")
                Text("\(MoneyFormatter.gbpWhole(netWorth.totalAssets)) in assets, less \(MoneyFormatter.gbpWhole(netWorth.totalLiabilities)) of liabilities.")
                    .font(.system(size: 14, weight: .semibold))
                    .foregroundStyle(FynlaColor.Token.horizon400.color)
            }
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))

            breakdownCard(netWorth)
        } else {
            Text("Your complete estate value is unavailable right now.")
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(16)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(Color.white)
                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        }

        planningCard(snapshot.index.data)
    }

    private func breakdownCard(_ netWorth: EstateNetWorth) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Estate breakdown".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.horizon400.color)
                .padding(.bottom, 6)

            ForEach(netWorth.assetComposition) { component in
                estateRow(
                    compositionLabel(component.type),
                    MoneyFormatter.gbpWhole(component.value)
                )
            }
            estateRow("Liabilities", MoneyFormatter.gbpWhole(netWorth.totalLiabilities))
            totalRow("Net estate", MoneyFormatter.gbpWhole(netWorth.netWorth))
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func planningCard(_ data: EstateFullData?) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Estate planning".uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.horizon400.color)
                .padding(.bottom, 6)

            let gifts = data?.gifts.count ?? 0
            let trusts = data?.trusts.count ?? 0
            estateRow("Lifetime gifts", "\(gifts) \(gifts == 1 ? "gift" : "gifts")")
            estateRow("Trusts", "\(trusts) \(trusts == 1 ? "trust" : "trusts")")
            // /m colours the will status: spring "In place", violet "Not set up".
            estateRow(
                "Will",
                data?.willInfo?.hasWill == true ? "In place" : "Not set up",
                valueColor: data?.willInfo?.hasWill == true
                    ? FynlaColor.Token.spring600.color
                    : FynlaColor.Token.violet500.color,
                showsDivider: false
            )
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    // me-row: label 14 neutral, value 14/700 horizon, light hairline.
    private func estateRow(
        _ key: String,
        _ value: String,
        valueColor: Color? = nil,
        showsDivider: Bool = true
    ) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key)
                .font(.system(size: 14))
                .foregroundStyle(FynlaColor.Token.neutral600.color)
            Spacer()
            Text(value)
                .font(.system(size: 14, weight: .bold))
                .foregroundStyle(valueColor ?? FynlaColor.Token.horizon500.color)
        }
        .padding(.vertical, 10)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.lightGray.color.frame(height: 1)
            }
        }
    }

    // me-row--total: horizon200 top border, bold label, 16pt value.
    private func totalRow(_ key: String, _ value: String) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key)
                .font(.system(size: 14, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            Spacer()
            Text(value)
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
        }
        .padding(.top, 12)
        .padding(.bottom, 2)
        .overlay(alignment: .top) {
            FynlaColor.Token.horizon200.color.frame(height: 1)
        }
    }

    private func compositionLabel(_ type: String) -> String {
        switch type {
        case "property": "Property"
        case "investment": "Investments"
        case "cash": "Cash & savings"
        case "pension": "Pensions"
        case "business": "Business interests"
        case "chattel": "Possessions"
        case "other": "Other assets"
        default: type.replacingOccurrences(of: "_", with: " ").capitalized
        }
    }

    private var offlineNotice: some View {
        Text("You're offline. Showing your last loaded estate position.")
            .font(.system(size: 13))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .padding(12)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .accessibilityIdentifier("estate.offline")
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        ScreenStateView(
            state: state,
            retry: state.canRetry ? { Task { await model.load() } } : nil,
            openSubscription: state.canUpgrade ? onOpenSubscription : nil
        )
    }
}
