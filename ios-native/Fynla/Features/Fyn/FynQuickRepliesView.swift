import SwiftUI

// Transcribes /m's onboarding bubble choices (md-fyn__bubble): wrapped row of
// white pills with a raspberry border and raspberry label.
struct FynQuickRepliesView: View {
    let replies: [FynReply]
    let onReply: (FynReply) -> Void

    var body: some View {
        BubbleFlowLayout(spacing: 8) {
            ForEach(replies) { reply in
                Button(reply.label) { onReply(reply) }
                    .font(.system(size: 13, weight: .semibold))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    .padding(.horizontal, 14)
                    .padding(.vertical, 8)
                    .background(Color.white)
                    .clipShape(Capsule())
                    .overlay(
                        Capsule()
                            .stroke(FynlaColor.Token.raspberry500.color, lineWidth: 1)
                    )
                    .accessibilityIdentifier("fyn.reply.\(reply.id)")
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Suggested replies")
    }
}

// Minimal wrapping row (flex-wrap equivalent) for the reply pills.
struct BubbleFlowLayout: Layout {
    var spacing: CGFloat = 8

    func sizeThatFits(
        proposal: ProposedViewSize,
        subviews: Subviews,
        cache: inout ()
    ) -> CGSize {
        let width = proposal.width ?? .infinity
        var x: CGFloat = 0
        var y: CGFloat = 0
        var rowHeight: CGFloat = 0
        for subview in subviews {
            let size = subview.sizeThatFits(.unspecified)
            if x > 0, x + size.width > width {
                x = 0
                y += rowHeight + spacing
                rowHeight = 0
            }
            x += size.width + spacing
            rowHeight = max(rowHeight, size.height)
        }
        return CGSize(width: width, height: y + rowHeight)
    }

    func placeSubviews(
        in bounds: CGRect,
        proposal: ProposedViewSize,
        subviews: Subviews,
        cache: inout ()
    ) {
        var x = bounds.minX
        var y = bounds.minY
        var rowHeight: CGFloat = 0
        for subview in subviews {
            let size = subview.sizeThatFits(.unspecified)
            if x > bounds.minX, x + size.width > bounds.maxX {
                x = bounds.minX
                y += rowHeight + spacing
                rowHeight = 0
            }
            subview.place(
                at: CGPoint(x: x, y: y),
                proposal: ProposedViewSize(size)
            )
            x += size.width + spacing
            rowHeight = max(rowHeight, size.height)
        }
    }
}
