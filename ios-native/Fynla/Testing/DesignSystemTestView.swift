#if FYNLA_UI_TESTING
import SwiftUI

struct DesignSystemTestView: View {
    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: FynlaSpacing.large) {
                Text("Your financial plan")
                    .font(FynlaTypography.pageTitle)
                    .foregroundStyle(FynlaColor.primaryText)

                Text("Review the next steps at your preferred text size. Every action remains readable and reachable.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .accessibilityIdentifier("design-system.instructions")

                FynlaButton(
                    "Continue to review your financial plan",
                    variant: .primary
                ) {}
                    .accessibilityIdentifier("design-system.primary")

                FynlaButton("Back", variant: .secondary) {}
                    .accessibilityIdentifier("design-system.secondary")

                FynlaButton("Delete account", variant: .destructive) {}
                    .accessibilityIdentifier("design-system.destructive")
            }
            .padding(FynlaSpacing.large)
        }
        .background(FynlaColor.pageBackground)
        .accessibilityIdentifier("design-system.showcase")
        .preferredColorScheme(.light)
    }
}
#endif
