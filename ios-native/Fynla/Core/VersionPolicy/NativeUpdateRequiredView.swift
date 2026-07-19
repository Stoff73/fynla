import SwiftUI

struct NativeUpdateRequiredView: View {
    let requirement: NativeUpdateRequirement
    let appStoreURL: URL

    @Environment(\.openURL) private var openURL

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.large) {
            Image(systemName: "arrow.down.app.fill")
                .font(.system(size: 44))
                .foregroundStyle(FynlaColor.primaryAction)
                .accessibilityHidden(true)

            Text("Update Fynla")
                .font(FynlaTypography.pageTitle)
                .foregroundStyle(FynlaColor.primaryText)

            Text(requirement.message)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)

            Text(
                "Required version \(requirement.minimumVersion) "
                    + "(build \(requirement.minimumBuild)) or later."
            )
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.secondaryText)

            FynlaButton(
                "Open the App Store",
                accessibilityLabel: "Open Fynla in the App Store"
            ) {
                openURL(appStoreURL)
            }

            Spacer()
        }
        .padding(FynlaSpacing.large)
        .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topLeading)
        .background(FynlaColor.pageBackground)
        .accessibilityIdentifier("native-update.required")
    }
}
