import SwiftUI

struct AccountSummaryView: View {
    let account: SettingsAccount?

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            Text("Account")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            if let account {
                Text(account.name)
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                    .accessibilityIdentifier("settings.account.name")
                Text(account.email)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .accessibilityIdentifier("settings.account.email")
            } else {
                Text("Account details are unavailable. Sign in again if this continues.")
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.secondaryText)
                    .accessibilityIdentifier("settings.account.unavailable")
            }
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
    }
}
