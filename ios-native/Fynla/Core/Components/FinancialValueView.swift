import Foundation
import SwiftUI

struct FinancialValueView: View {
    let displayText: String
    private let isUnavailable: Bool

    static func money(
        _ value: Decimal?,
        unavailableText: String = "Unavailable"
    ) -> Self {
        guard let value else {
            return Self(displayText: unavailableText, isUnavailable: true)
        }
        return Self(displayText: MoneyFormatter.gbp(value), isUnavailable: false)
    }

    static func percentage(
        _ value: Decimal?,
        unavailableText: String = "Unavailable"
    ) -> Self {
        guard let value else {
            return Self(displayText: unavailableText, isUnavailable: true)
        }
        return Self(
            displayText: MoneyFormatter.percentage(value),
            isUnavailable: false
        )
    }

    var body: some View {
        Text(displayText)
            .font(FynlaTypography.heading)
            .foregroundStyle(
                isUnavailable ? FynlaColor.secondaryText : FynlaColor.primaryText
            )
            .accessibilityLabel(displayText)
    }
}
