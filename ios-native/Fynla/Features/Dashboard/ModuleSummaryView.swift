import Foundation
import SwiftUI

struct ModuleSummaryView: View {
    let key: String
    let title: String
    let summary: DashboardModuleSummary
    let onSelect: () -> Void

    var body: some View {
        Button(action: onSelect) {
            VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                Text(title)
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                Text(statusText)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
                if let headline {
                    Text(headline)
                        .font(FynlaTypography.sectionTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                }
                if let detail {
                    Text(detail)
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                }
            }
            .frame(maxWidth: .infinity, minHeight: 132, alignment: .topLeading)
            .padding(FynlaSpacing.standard)
            .background(FynlaColor.surface)
            .overlay {
                RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius)
                    .stroke(FynlaColor.Token.horizon200.color)
            }
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("dashboard.module.\(key)")
    }

    private var statusText: String {
        switch summary.status {
        case .active: "Active"
        case .notConfigured: summary.message ?? "Not configured"
        case .unavailable: summary.message ?? "Temporarily unavailable"
        }
    }

    private var headline: String? {
        guard summary.status == .active else { return nil }
        let value: Decimal? = switch key {
        case "protection": summary.totalCoverage
        case "savings": summary.totalSavings
        case "investment", "net-worth": summary.portfolioValue
        case "retirement": summary.potValue
        case "estate": summary.netEstate
        case "goals": summary.totalSaved
        default: nil
        }
        return value.map(DashboardFormatting.currency)
    }

    private var detail: String? {
        guard summary.status == .active else { return nil }
        switch key {
        case "protection":
            return summary.policyCount.map { "\($0) polic\($0 == 1 ? "y" : "ies")" }
        case "savings":
            return summary.emergencyFundMonths.map { "\($0) months of emergency funding" }
        case "investment":
            return summary.accountsCount.map { "\($0) investment account\($0 == 1 ? "" : "s")" }
        case "retirement":
            return summary.yearsToRetirement.map { "\($0) years to retirement" }
        case "estate":
            return summary.ihtLiability.map { "Estimated IHT: \(DashboardFormatting.currency($0))" }
        case "goals":
            guard let complete = summary.completedGoals, let total = summary.totalGoals else { return nil }
            return "\(complete) of \(total) goals complete"
        default:
            return nil
        }
    }
}

enum DashboardFormatting {
    static func currency(_ value: Decimal) -> String {
        let formatter = NumberFormatter()
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        formatter.maximumFractionDigits = 0
        return formatter.string(from: value as NSDecimalNumber) ?? "Value unavailable"
    }
}
