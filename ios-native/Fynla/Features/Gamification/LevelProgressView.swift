import SwiftUI

// Mirrors /m's level card (md-level in Dashboard.vue): centred progress wheel
// above the copy, translucent white card sitting on the gradient hero, the
// whole card tappable through to Achievements.
struct LevelProgressView: View {
    let level: DashboardLevel

    private var drawingProgress: Double {
        min(max(Double(level.progressPercent), 0), 100) / 100
    }

    var body: some View {
        VStack(spacing: FynlaSpacing.standard) {
            ZStack {
                Circle()
                    .stroke(FynlaColor.Token.horizon200.color, lineWidth: 9)
                Circle()
                    .trim(from: 0, to: drawingProgress)
                    .stroke(
                        FynlaColor.primaryAction,
                        style: StrokeStyle(lineWidth: 9, lineCap: .round)
                    )
                    .rotationEffect(.degrees(-90))
                VStack(spacing: 0) {
                    Text("Level")
                        .font(FynlaTypography.caption)
                    Text(level.level.formatted())
                        .font(FynlaTypography.sectionTitle)
                }
                .foregroundStyle(FynlaColor.primaryText)
            }
            .frame(width: 104, height: 104)
            .accessibilityHidden(true)

            VStack(spacing: FynlaSpacing.xSmall) {
                Text("\(level.actionsCompleted) of \(level.actionsTotal) actions to your next level")
                    .font(FynlaTypography.heading)
                    .foregroundStyle(FynlaColor.primaryText)
                    .multilineTextAlignment(.center)
                (
                    Text("Complete actions to reach ")
                    + Text("Level \(level.level + 1)").fontWeight(.bold)
                    + Text(".")
                )
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.center)
            }
        }
        .frame(maxWidth: .infinity)
        .padding(FynlaSpacing.standard)
        .background(FynlaColor.surface.opacity(0.88))
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.cardCornerRadius, style: .continuous))
        .accessibilityElement(children: .ignore)
        .accessibilityLabel(
            "Level \(level.level), \(level.actionsCompleted) of \(level.actionsTotal) actions complete"
        )
        .accessibilityValue("\(level.progressPercent) percent to the next level")
        .accessibilityIdentifier("dashboard.level")
    }
}
