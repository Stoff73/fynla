import SwiftUI

struct NextMilestoneView: View {
    let milestone: DashboardNextMilestone
    let onSelect: () -> Void

    var body: some View {
        Button(action: onSelect) {
            VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                Text("Next milestone: \(milestone.title)")
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                Text(milestone.steps)
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(FynlaSpacing.standard)
            .background(FynlaColor.surface)
            .overlay {
                RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius)
                    .stroke(FynlaColor.Token.horizon200.color)
            }
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        }
        .buttonStyle(.plain)
        .accessibilityIdentifier("dashboard.next-milestone")
    }
}
