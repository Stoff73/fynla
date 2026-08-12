import SwiftUI

struct SubscriptionView: View {
    let model: SubscriptionModel
    let appleManager: any AppleSubscriptionManaging

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: FynlaSpacing.large) {
                VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                    Text("Premium")
                        .font(FynlaTypography.pageTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Manage your plan and billing")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }

                if !model.plans.isEmpty {
                    PlanComparisonSection(plans: model.plans)
                }

                SubscriptionManagementView(
                    model: model,
                    appleManager: appleManager
                )
                .padding(FynlaSpacing.standard)
                .background(FynlaColor.surface)
                .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
            }
            .padding(FynlaSpacing.standard)
        }
        .background(FynlaColor.pageBackground)
        .navigationTitle("Premium")
        .navigationBarTitleDisplayMode(.inline)
        .accessibilityIdentifier("subscription.screen")
    }
}

private struct PlanComparisonSection: View {
    let plans: [PlanComparison]

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                Text("Compare plans")
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Text("Features and limits are kept up to date by Fynla.")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }

            ForEach(plans) { plan in
                VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
                    Text(plan.displayName)
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)

                    ForEach(plan.features) { feature in
                        HStack(alignment: .top, spacing: FynlaSpacing.small) {
                            Image(
                                systemName: feature.included
                                    ? "checkmark.circle.fill"
                                    : "xmark.circle"
                            )
                            .foregroundStyle(
                                feature.included
                                    ? FynlaColor.primaryAction
                                    : FynlaColor.secondaryText
                            )
                            .accessibilityHidden(true)

                            Text(feature.label)
                                .font(FynlaTypography.bodySmall)
                                .foregroundStyle(
                                    feature.included
                                        ? FynlaColor.primaryText
                                        : FynlaColor.secondaryText
                                )
                                .frame(maxWidth: .infinity, alignment: .leading)
                        }
                        .accessibilityElement(children: .ignore)
                        .accessibilityLabel(feature.label)
                        .accessibilityValue(availabilityLabel(for: feature))
                        .accessibilityIdentifier(
                            "subscription.feature.\(plan.tier).\(feature.key)"
                        )
                    }
                }
                .padding(FynlaSpacing.standard)
                .background(FynlaColor.surface)
                .overlay {
                    RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius)
                        .stroke(FynlaColor.Token.horizon200.color, lineWidth: 1)
                }
                .clipShape(
                    RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius)
                )
                .accessibilityElement(children: .contain)
                .accessibilityIdentifier("subscription.plan.\(plan.tier)")
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("subscription.comparison")
    }

    private func availabilityLabel(for feature: PlanFeature) -> String {
        switch feature.availability {
        case "full":
            "Included"
        case "limited":
            "Limited"
        case "teaser":
            "Preview only"
        default:
            feature.included ? "Included" : "Not included"
        }
    }
}
