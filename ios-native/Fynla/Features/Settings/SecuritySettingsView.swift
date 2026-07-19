import SwiftUI

struct SecuritySettingsView: View {
    let model: SettingsModel
    @State private var confirmsFaceIDDisable = false

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.medium) {
            Text("Security")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)

            Toggle(
                "Use Face ID",
                isOn: Binding(
                    get: { model.faceIDEnabled },
                    set: { enabled in
                        if enabled {
                            Task { await model.enableFaceID() }
                        } else {
                            confirmsFaceIDDisable = true
                        }
                    }
                )
            )
            .tint(FynlaColor.focus)
            .disabled(
                model.isChangingFaceID
                    || (!model.faceIDEnabled && !model.canEnableFaceID)
            )
            .accessibilityIdentifier("settings.face-id")

            Text(model.faceIDEnabled
                ? "Face ID protects your saved sign-in on this iPhone."
                : "Face ID is off. You will need your full sign-in after this session ends.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)

            if let securityError = model.securityError {
                Text(securityError)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.primaryAction)
                    .accessibilityIdentifier("settings.security.error")
            }

            Button("Lock") { model.lock() }
                .font(FynlaTypography.button)
                .foregroundStyle(FynlaColor.primaryText)
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("app.unlocked.lock")
        }
        .padding(FynlaSpacing.standard)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .alert("Turn off Face ID?", isPresented: $confirmsFaceIDDisable) {
            Button("Keep Face ID", role: .cancel) {}
            Button("Turn off", role: .destructive) {
                Task { await model.disableFaceID() }
            }
        } message: {
            Text("Your protected sign-in will be removed from this iPhone. Your current session stays signed in, but you will need your full sign-in next time.")
        }
    }
}
