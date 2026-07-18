import SwiftUI

struct FynComposerView: View {
    @Bindable var model: FynConversationModel

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            TextField(
                "Message Fyn",
                text: $model.draft,
                axis: .vertical
            )
            .lineLimit(1...5)
            .textFieldStyle(.roundedBorder)
            .disabled(model.phase.isBusy)
            .accessibilityIdentifier("fyn.composer")

            HStack {
                Text("Do not include passwords or security codes.")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
                Spacer()
                if model.phase == .streaming {
                    Button("Stop") { model.stop() }
                        .buttonStyle(.bordered)
                        .accessibilityIdentifier("fyn.stop")
                } else {
                    Button("Send") {
                        Task { await model.sendDraft() }
                    }
                    .disabled(
                        model.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
                            || model.phase.isBusy
                    )
                    .buttonStyle(.borderedProminent)
                    .accessibilityIdentifier("fyn.send")
                }
            }
        }
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.surface)
    }
}
