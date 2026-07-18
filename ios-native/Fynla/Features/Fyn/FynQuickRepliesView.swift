import SwiftUI

struct FynQuickRepliesView: View {
    let replies: [FynReply]
    let onReply: (FynReply) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            ForEach(replies) { reply in
                Button(reply.label) { onReply(reply) }
                    .font(FynlaTypography.button)
                    .frame(
                        maxWidth: .infinity,
                        minHeight: FynlaSpacing.minimumInteractiveTarget,
                        alignment: .leading
                    )
                    .buttonStyle(.bordered)
                    .accessibilityIdentifier("fyn.reply.\(reply.id)")
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Suggested replies")
    }
}
