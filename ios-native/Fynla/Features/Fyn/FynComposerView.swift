import SwiftUI

// Transcribes /m's Fyn compose bar (md-fyn__compose, v41 overrides): white bar
// with a hairline top border, small Fyn avatar, bordered "Ask Fyn anything..."
// input and a raspberry send square. The Stop control replaces send while
// streaming — a native necessity /m does not have (recorded in the parity
// ledger).
struct FynComposerView: View {
    @Bindable var model: FynConversationModel

    private var sendDisabled: Bool {
        model.draft.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            || model.phase.isBusy
    }

    var body: some View {
        HStack(spacing: 10) {
            Image("FynAvatar")
                .resizable()
                .scaledToFill()
                .frame(width: 36, height: 36)
                .clipShape(Circle())
                .accessibilityHidden(true)

            TextField(
                "Ask Fyn anything...",
                text: $model.draft,
                axis: .vertical
            )
            .lineLimit(1...5)
            .font(.system(size: 15))
            .foregroundStyle(FynlaColor.Token.horizon600.color)
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .stroke(FynlaColor.Token.horizon200.color, lineWidth: 1)
            )
            .disabled(model.phase.isBusy)
            .accessibilityIdentifier("fyn.composer")

            if model.phase == .streaming {
                Button {
                    model.stop()
                } label: {
                    Image(systemName: "stop.fill")
                        .font(.system(size: 15, weight: .bold))
                        .foregroundStyle(.white)
                        .frame(width: 40, height: 40)
                        .background(FynlaColor.Token.raspberry500.color)
                        .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
                }
                .accessibilityLabel("Stop Fyn's reply")
                .accessibilityIdentifier("fyn.stop")
            } else {
                Button {
                    Task { await model.sendDraft() }
                } label: {
                    Image(systemName: "arrow.right")
                        .font(.system(size: 16, weight: .bold))
                        .foregroundStyle(.white)
                        .frame(width: 40, height: 40)
                        .background(FynlaColor.Token.raspberry500.color)
                        .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
                        .opacity(sendDisabled ? 0.5 : 1)
                }
                .disabled(sendDisabled)
                .accessibilityLabel("Send to Fyn")
                .accessibilityIdentifier("fyn.send")
            }
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .background(Color.white)
        .overlay(alignment: .top) {
            FynlaColor.Token.lightGray.color.frame(height: 1)
        }
    }
}
