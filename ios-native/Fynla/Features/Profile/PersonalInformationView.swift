import SwiftUI

struct PersonalInformationView: View {
    let model: PersonalInformationModel

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your personal information…") }
            case let .loaded(profile):
                content(profile)
            case let .offline(previous):
                if let previous {
                    content(previous, offline: true)
                } else {
                    stateView(.offline)
                }
            case .unauthenticated:
                stateView(.unauthenticated)
            case let .failed(requestID):
                stateView(.failed(requestID: requestID))
            }
        }
        .background(FynlaColor.pageBackground)
        .task { await model.load() }
        .accessibilityIdentifier("personal-information.screen")
    }

    private func content(
        _ profile: PersonalInformationProfile,
        offline: Bool = false
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Personal Information",
                    subtitle: "Your canonical profile and financial position"
                )

                if offline {
                    Text("You're offline. Showing your last loaded personal information.")
                        .font(.system(size: 13))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                        .padding(12)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .background(FynlaColor.Token.savannah100.color)
                        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                        .padding(.horizontal, 16)
                        .accessibilityIdentifier("personal-information.offline")
                }

                VStack(spacing: 12) {
                    informationCard(title: "About you") {
                        informationRow("Name", profile.personalInfo.name)
                        informationRow("Email", profile.personalInfo.email)
                        informationRow("Date of birth", display(profile.personalInfo.dateOfBirth))
                        informationRow(
                            "National Insurance",
                            display(profile.personalInfo.nationalInsuranceNumber, fallback: "Not recorded")
                        )
                        informationRow(
                            "Address",
                            profile.personalInfo.address.formatted.isEmpty
                                ? "Not recorded"
                                : profile.personalInfo.address.formatted
                        )
                    }

                    informationCard(title: "Household") {
                        informationRow("Household", profile.householdLabel)
                        if let spouse = profile.spouse {
                            informationRow("Spouse or partner", spouse.name)
                        }
                    }

                    informationCard(title: "Domicile") {
                        Text(profile.domicileInfo?.display ?? "Not recorded")
                            .font(.system(size: 14))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                        if let country = profile.domicileInfo?.countryOfBirth {
                            Text("Country of birth: \(country)")
                                .font(.system(size: 12))
                                .foregroundStyle(FynlaColor.Token.neutral500.color)
                        }
                    }

                    informationCard(title: "Financial summary") {
                        informationRow(
                            "Annual income",
                            money(profile.incomeOccupation?.totalAnnualIncome)
                        )
                        informationRow(
                            "Monthly expenditure",
                            money(profile.expenditure?.monthlyExpenditure)
                        )
                        informationRow("Total assets", money(profile.assetsSummary?.total))
                        informationRow(
                            "Total liabilities",
                            money(profile.liabilitiesSummary?.total)
                        )
                        informationRow("Net Worth", money(profile.netWorth), emphasized: true)
                    }
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.refresh() }
    }

    private func framed<Content: View>(
        @ViewBuilder _ content: () -> Content
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(
                    title: "Personal Information",
                    subtitle: "Your canonical profile and financial position"
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
                retry: state.canRetry ? { Task { await model.load() } } : nil
            )
        }
    }

    private func informationCard<Content: View>(
        title: String,
        @ViewBuilder content: () -> Content
    ) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(title.uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)
            content()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func informationRow(
        _ label: String,
        _ value: String,
        emphasized: Bool = false
    ) -> some View {
        HStack(alignment: .firstTextBaseline, spacing: 16) {
            Text(label)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
            Spacer(minLength: 8)
            Text(value)
                .fontWeight(emphasized ? .heavy : .bold)
                .foregroundStyle(FynlaColor.Token.horizon500.color)
                .multilineTextAlignment(.trailing)
        }
        .font(.system(size: emphasized ? 15 : 13))
        .padding(.vertical, 10)
        .overlay(alignment: .bottom) {
            FynlaColor.Token.horizon100.color.frame(height: 1)
        }
        .accessibilityElement(children: .combine)
    }

    private func display(_ value: String?, fallback: String = "—") -> String {
        guard let value, !value.isEmpty else { return fallback }
        return value
    }

    private func money(_ value: Decimal?) -> String {
        guard let value else { return "—" }
        return MoneyFormatter.gbpWhole(value)
    }
}
