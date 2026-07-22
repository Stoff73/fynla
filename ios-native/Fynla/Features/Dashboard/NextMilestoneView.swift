import SwiftUI

// Transcribes /m's md-next-milestone: horizon-100 nudge under the level wheel
// with title + steps, tappable through to the surface where the user acts.
struct NextMilestoneView: View {
    let milestone: DashboardNextMilestone
    let onSelect: () -> Void

    var body: some View {
        Button(action: onSelect) {
            VStack(alignment: .leading, spacing: 2) {
                Text("Next milestone: \(milestone.title)")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon600.color)
                Text(milestone.steps)
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(.horizontal, 14)
            .padding(.vertical, 10)
            .background(FynlaColor.Token.horizon100.color)
            .clipShape(RoundedRectangle(cornerRadius: 8, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 8, style: .continuous)
                    .stroke(FynlaColor.Token.horizon200.color, lineWidth: 1)
            )
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("dashboard.next-milestone")
    }
}
