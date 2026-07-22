import StoreKit
import SwiftUI
import UIKit

protocol AppleSubscriptionManaging: Sendable {
    @MainActor
    func showManageSubscriptions() async throws
}

struct SystemAppleSubscriptionManager: AppleSubscriptionManaging {
    @MainActor
    func showManageSubscriptions() async throws {
        guard let scene = UIApplication.shared.connectedScenes
            .compactMap({ $0 as? UIWindowScene })
            .first(where: { $0.activationState == .foregroundActive })
        else {
            throw SubscriptionManagementError.sceneUnavailable
        }
        try await AppStore.showManageSubscriptions(in: scene)
    }
}

enum SubscriptionManagementError: Error, Sendable {
    case sceneUnavailable
}

struct SubscriptionManagementView: View {
    let model: SubscriptionModel
    let appleManager: any AppleSubscriptionManaging

    @Environment(\.locale) private var locale
    @Environment(\.timeZone) private var timeZone
    @State private var managementError: String?

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.large) {
            if let message = model.message {
                messageBanner(message)
            }
            if let managementError {
                messageBanner(managementError)
            }

            switch model.state {
            case .loading:
                LoadingView(message: "Loading subscription details…")
                    .accessibilityIdentifier("subscription.loading")
            case let .free(products, selectedProductID, isPending):
                freeContent(
                    products: products,
                    selectedProductID: selectedProductID,
                    isPending: isPending
                )
            case let .applePremium(entitlement):
                applePremiumContent(entitlement)
            case let .webPremium(entitlement):
                webPremiumContent(entitlement)
            case let .unavailable(message):
                ErrorView(message: message) {
                    Task { await model.load() }
                }
                .accessibilityIdentifier("subscription.unavailable")
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private func freeContent(
        products: [StoreProduct],
        selectedProductID: String,
        isPending: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.large) {
            planHeader(
                title: isPending ? "Payment pending" : "Free",
                detail: isPending
                    ? "Premium will activate only after the App Store verifies your purchase. Free access remains available now."
                    : "Your Free plan includes Fynla's core financial planning features.",
                badge: isPending ? "Pending" : "Free"
            )

            if !isPending {
                VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
                    Text("Choose Premium")
                        .font(FynlaTypography.sectionTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("Unlock all Premium planning capabilities and higher limits.")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)

                    ForEach(products, id: \.id) { product in
                        productButton(
                            product,
                            selected: product.id == selectedProductID
                        )
                    }
                }

                FynlaButton(
                    "Subscribe to Premium",
                    accessibilityLabel: "Subscribe to Premium",
                    isLoading: model.isPurchasing,
                    isDisabled: !model.canPurchase
                ) {
                    Task { await model.purchase() }
                }
                .accessibilityIdentifier("subscription.purchase")
            }

            FynlaButton(
                "Restore App Store purchase",
                variant: .secondary,
                accessibilityLabel: "Restore App Store purchase",
                isLoading: model.isRestoring,
                isDisabled: model.isPurchasing || model.isRestoring
            ) {
                Task { await model.restore() }
            }
            .accessibilityIdentifier("subscription.restore")
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier(
            isPending ? "subscription.pending" : "subscription.free"
        )
    }

    private func applePremiumContent(
        _ entitlement: NativeEntitlement
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.large) {
            planHeader(
                title: "Premium",
                detail: premiumDetail(entitlement),
                badge: statusLabel(entitlement)
            )

            entitlementDetails(entitlement)

            FynlaButton(
                "Manage App Store subscription",
                variant: .secondary,
                accessibilityLabel: "Manage App Store subscription"
            ) {
                Task {
                    do {
                        try await appleManager.showManageSubscriptions()
                        managementError = nil
                    } catch {
                        managementError = "App Store subscription management is unavailable. Please try again."
                    }
                }
            }
            .accessibilityIdentifier("subscription.manage-apple")
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("subscription.apple-premium")
    }

    private func webPremiumContent(
        _ entitlement: NativeEntitlement
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.large) {
            planHeader(
                title: "Premium",
                detail: premiumDetail(entitlement),
                badge: statusLabel(entitlement)
            )
            entitlementDetails(entitlement)
            VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                Text("Billing managed on the web")
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                Text("This Premium subscription was purchased on the Fynla website. Sign in to Fynla on the web to manage billing.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            .padding(FynlaSpacing.standard)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("subscription.web-premium")
    }

    private func productButton(
        _ product: StoreProduct,
        selected: Bool
    ) -> some View {
        Button {
            model.selectProduct(product.id)
        } label: {
            HStack(spacing: FynlaSpacing.medium) {
                Image(systemName: selected ? "checkmark.circle.fill" : "circle")
                    .foregroundStyle(
                        selected ? FynlaColor.primaryAction : FynlaColor.secondaryText
                    )
                    .accessibilityHidden(true)
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text(product.displayName)
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    Text("\(product.displayPrice) \(product.periodLabel(locale: locale))")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
                Spacer()
            }
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            .padding(FynlaSpacing.standard)
            .background(FynlaColor.surface)
            .overlay {
                RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius)
                    .stroke(
                        selected
                            ? FynlaColor.primaryAction
                            : FynlaColor.Token.horizon200.color,
                        lineWidth: selected ? 2 : 1
                    )
            }
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        }
        .buttonStyle(.plain)
        .accessibilityLabel(
            "\(product.displayName), \(product.displayPrice) \(product.periodLabel(locale: locale))"
        )
        .accessibilityValue(selected ? "Selected" : "Not selected")
        .accessibilityIdentifier(
            product.id == StoreProductIdentifier.monthly
                ? "subscription.product.monthly"
                : "subscription.product.annual"
        )
    }

    private func planHeader(
        title: String,
        detail: String,
        badge: String
    ) -> some View {
        HStack(alignment: .top, spacing: FynlaSpacing.standard) {
            VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                Text(title)
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Text(detail)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            Spacer(minLength: FynlaSpacing.small)
            Text(badge)
                .font(FynlaTypography.caption.bold())
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.horizontal, FynlaSpacing.medium)
                .padding(.vertical, FynlaSpacing.small)
                .background(FynlaColor.Token.savannah100.color)
                .clipShape(Capsule())
        }
    }

    private func entitlementDetails(_ entitlement: NativeEntitlement) -> some View {
        VStack(spacing: FynlaSpacing.medium) {
            detailRow(label: "Plan", value: "Premium")
            detailRow(
                label: entitlement.renews ? "Next renewal" : "Access ends",
                value: formattedDate(entitlement.currentPeriodEnd) ?? "See billing details"
            )
        }
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }

    private func detailRow(label: String, value: String) -> some View {
        HStack {
            Text(label)
                .foregroundStyle(FynlaColor.secondaryText)
            Spacer()
            Text(value)
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.trailing)
        }
        .font(FynlaTypography.body)
    }

    private func messageBanner(_ message: String) -> some View {
        Text(message)
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(FynlaSpacing.standard)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
            .accessibilityIdentifier("subscription.message")
    }

    private func premiumDetail(_ entitlement: NativeEntitlement) -> String {
        switch entitlement.status {
        case "cancelled":
            "Auto-renewal is off. You retain Premium until the end of the current period."
        case "billing_retry", "grace_period":
            "There is a payment issue. You retain Premium while the App Store resolves it."
        default:
            "Your Premium plan is active."
        }
    }

    private func statusLabel(_ entitlement: NativeEntitlement) -> String {
        switch entitlement.status {
        case "cancelled": "Cancelled"
        case "billing_retry", "grace_period": "Payment issue"
        default: "Active"
        }
    }

    private func formattedDate(_ value: String?) -> String? {
        SubscriptionDateFormatter.string(
            from: value,
            locale: locale,
            timeZone: timeZone
        )
    }
}

enum SubscriptionDateFormatter {
    static func string(
        from value: String?,
        locale: Locale,
        timeZone: TimeZone
    ) -> String? {
        guard let value,
              let date = fractionalISO8601.date(from: value)
                ?? secondISO8601.date(from: value)
        else {
            return nil
        }
        var style = Date.FormatStyle.dateTime
            .day()
            .month(.wide)
            .year()
            .locale(locale)
        style.timeZone = timeZone
        return date.formatted(style)
    }

    private static var fractionalISO8601: ISO8601DateFormatter {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter
    }

    private static var secondISO8601: ISO8601DateFormatter {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        return formatter
    }
}
