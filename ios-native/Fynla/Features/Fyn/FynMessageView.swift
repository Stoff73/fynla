import Foundation
import SwiftUI

struct FynMessageView: View {
    let message: FynMessage
    let onReply: (FynReply) -> Void

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.small) {
            HStack {
                VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                    Text(markdownText)
                        .font(.system(size: 15))
                        .foregroundStyle(
                            message.role == .fyn
                                ? FynlaColor.Token.horizon600.color
                                : FynlaColor.Token.horizon500.color
                        )
                        .textSelection(.enabled)
                    deliveryText
                    if let capture = message.capture {
                        FynCaptureConfirmationView(capture: capture)
                    }
                }
                .padding(.horizontal, 16)
                .padding(.vertical, 12)
                .background(message.role == .fyn ? Color.white : Color.clear)
                .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                .overlay {
                    // /m's Fyn bubbles carry a hairline border (md-fyn__msg--fyn).
                    if message.role == .fyn {
                        RoundedRectangle(cornerRadius: 12, style: .continuous)
                            .stroke(FynlaColor.Token.horizon100.color, lineWidth: 1)
                    }
                }
                if message.role == .fyn { Spacer(minLength: FynlaSpacing.large) }
            }

            if !message.replies.isEmpty {
                FynQuickRepliesView(replies: message.replies, onReply: onReply)
            }
        }
        // /m defines no .md-fyn__msg--user rule, so user messages render
        // live as full-width, left-aligned plain text in the body colour —
        // verified on csjones by computed-style probe (sweep item 8).
        .frame(maxWidth: .infinity, alignment: .leading)
        .accessibilityElement(children: .contain)
        .accessibilityLabel(message.role == .user ? "You" : "Fyn")
        .accessibilityIdentifier("fyn.message.\(message.id)")
    }

    @ViewBuilder
    private var deliveryText: some View {
        switch message.delivery {
        case .submitting:
            Text("Sending…")
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.secondaryText)
        case .queued:
            Text("Queued")
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.secondaryText)
        case .failed:
            Text("Not confirmed")
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.Token.raspberry700.color)
        case .persisted, .streaming:
            EmptyView()
        }
    }

    private var markdownText: AttributedString {
        let safe = message.text
            .replacingOccurrences(of: "<", with: "‹")
            .replacingOccurrences(of: ">", with: "›")
        guard var attributed = try? AttributedString(
            markdown: safe,
            options: AttributedString.MarkdownParsingOptions(
                interpretedSyntax: .inlineOnlyPreservingWhitespace
            )
        ) else {
            return AttributedString(message.text)
        }
        for run in attributed.runs where run.link != nil {
            attributed[run.range].link = nil
        }
        return attributed
    }
}
