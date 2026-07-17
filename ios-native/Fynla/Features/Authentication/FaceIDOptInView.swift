import SwiftUI

struct FaceIDOptInView: View {
    let controller: PrivacyLockController
    @State private var message: String?

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Use Face ID next time?")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
            Text(
                "Face ID can unlock this device session. Your password, access token and financial data are never stored for Face ID."
            )
            .font(FynlaTypography.body)
            .foregroundStyle(FynlaColor.secondaryText)

            if let message {
                Text(message)
                    .font(FynlaTypography.body)
                    .foregroundStyle(FynlaColor.primaryAction)
                    .accessibilityIdentifier("face-id.opt-in.message")
            }

            FynlaButton(
                "Use Face ID",
                accessibilityLabel: "Use Face ID to unlock Fynla",
                isLoading: controller.isFaceIDChoiceInProgress,
                isDisabled: controller.isFaceIDChoiceInProgress
            ) {
                Task { @MainActor in
                    do {
                        try await controller.enableFaceID()
                    } catch {
                        message = "Face ID could not be enabled. You can continue without it."
                    }
                }
            }
            .accessibilityIdentifier("face-id.opt-in.enable")

            FynlaButton(
                "Not now",
                variant: .secondary,
                accessibilityLabel: "Continue without Face ID",
                isDisabled: controller.isFaceIDChoiceInProgress
            ) {
                Task { @MainActor in
                    await controller.declineFaceID()
                }
            }
            .accessibilityIdentifier("face-id.opt-in.not-now")
        }
        .padding(FynlaSpacing.large)
        .background(FynlaColor.surface)
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("face-id.opt-in")
    }
}
