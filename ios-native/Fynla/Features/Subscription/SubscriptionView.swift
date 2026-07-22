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
