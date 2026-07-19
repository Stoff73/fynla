import SwiftUI

struct EstateView: View {
    let model: EstateModel
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
        .navigationTitle("Estate")
        .navigationBarTitleDisplayMode(.inline)
        .task { await model.load() }
        .accessibilityIdentifier("estate.screen")
    }

    private func content(
        _ snapshot: EstateSnapshot,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Estate")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Inheritance Tax exposure and planning")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                if offline { offlineNotice }
                switch snapshot.index.mode {
                case .teaser:
                    teaser(snapshot.index)
                case .full:
                    fullEstate(snapshot)
                }
            }
            .padding(FynlaSpacing.standard)
        }
        .refreshable { await model.refresh() }
    }

    @ViewBuilder
    private func teaser(_ index: EstateIndexResponse) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Estimated Inheritance Tax liability")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            FinancialValueView.money(index.teaser?.estimatedLiability)
                .accessibilityIdentifier("estate.teaser.liability")
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))

        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            if let headline = index.teaser?.headline, !headline.isEmpty {
                Text(headline)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            Text("Full estate planning — assets, gifts, trusts, will and personalised Inheritance Tax planning — is part of Premium.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            Button(index.cta?.label ?? "Compare plans", action: onOpenSubscription)
                .buttonStyle(.borderedProminent)
                .tint(FynlaColor.primaryAction)
                .accessibilityIdentifier("estate.teaser.upgrade")
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    @ViewBuilder
    private func fullEstate(_ snapshot: EstateSnapshot) -> some View {
        if let netWorth = snapshot.netWorth {
            VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                Text("Estimated estate value")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
                FinancialValueView.money(netWorth.netWorth)
                    .accessibilityIdentifier("estate.net-estate")
                Text("\(MoneyFormatter.gbp(netWorth.totalAssets)) in assets, less \(MoneyFormatter.gbp(netWorth.totalLiabilities)) of liabilities.")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            .padding(FynlaSpacing.standard)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.surface)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))

            breakdownCard(netWorth)
        } else {
            ScreenStateView(
                state: .empty(message: "Your complete estate value is unavailable right now.")
            )
            .background(FynlaColor.surface)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        }

        planningCard(snapshot.index.data)
    }

    private func breakdownCard(_ netWorth: EstateNetWorth) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Estate breakdown")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)
            ForEach(netWorth.assetComposition) { component in
                detailRow(compositionLabel(component.type), MoneyFormatter.gbp(component.value))
            }
            detailRow("Liabilities", MoneyFormatter.gbp(netWorth.totalLiabilities))
            detailRow("Net estate", MoneyFormatter.gbp(netWorth.netWorth), isTotal: true)
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func planningCard(_ data: EstateFullData?) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text("Estate planning")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.bottom, FynlaSpacing.small)
            let gifts = data?.gifts.count ?? 0
            let trusts = data?.trusts.count ?? 0
            detailRow("Lifetime gifts", "\(gifts) \(gifts == 1 ? "gift" : "gifts")")
            detailRow("Trusts", "\(trusts) \(trusts == 1 ? "trust" : "trusts")")
            detailRow("Will", data?.willInfo?.hasWill == true ? "In place" : "Not set up")
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func detailRow(
        _ key: String,
        _ value: String,
        isTotal: Bool = false
    ) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: FynlaSpacing.small) {
            Text(key)
                .font(isTotal ? FynlaTypography.heading : FynlaTypography.body)
                .foregroundStyle(isTotal ? FynlaColor.primaryText : FynlaColor.secondaryText)
            Spacer()
            Text(value)
                .font(isTotal ? FynlaTypography.sectionTitle : FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
        }
        .padding(.vertical, FynlaSpacing.xSmall)
        .overlay(alignment: .bottom) { Divider() }
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
