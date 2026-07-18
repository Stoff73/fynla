import SwiftUI

struct FynCaptureConfirmationView: View {
    let capture: CaptureState

    var body: some View {
        switch capture {
        case let .pending(names):
            Text(names.isEmpty ? "Saving…" : "Saving \(names.joined(separator: ", "))…")
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.secondaryText)
                .accessibilityIdentifier("fyn.capture.pending")
        case let .confirmed(summary):
            Text(summary)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.primaryText)
                .accessibilityLabel("Saved. \(summary)")
                .accessibilityIdentifier("fyn.capture.confirmed")
        }
    }
}
