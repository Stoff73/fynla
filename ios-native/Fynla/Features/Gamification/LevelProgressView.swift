import SwiftUI

struct LevelProgressView: View {
    let level: DashboardLevel
    let percentile: Int

    private var drawingProgress: Double {
        min(max(Double(level.progressPercent), 0), 100) / 100
    }

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            HStack(alignment: .center, spacing: FynlaSpacing.standard) {
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

                VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                    Text("\(level.actionsCompleted) of \(level.actionsTotal) actions to your next level")
                        .font(FynlaTypography.heading)
                        .foregroundStyle(FynlaColor.primaryText)
                    if let nextLevelName = level.nextLevelName {
                        Text("Complete actions to reach \(nextLevelName).")
                            .font(FynlaTypography.bodySmall)
                            .foregroundStyle(FynlaColor.secondaryText)
                    }
                }
            }
            .accessibilityElement(children: .ignore)
            .accessibilityLabel(
                "Level \(level.level), \(level.actionsCompleted) of \(level.actionsTotal) actions complete"
            )
            .accessibilityValue("\(level.progressPercent) percent to the next level")

            Text("You're ahead of \(percentile)% of people")
                .font(FynlaTypography.heading)
                .foregroundStyle(FynlaColor.primaryText)
                .accessibilityLabel("You're ahead of \(percentile) percent of people")
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(FynlaSpacing.standard)
        .background(
            LinearGradient(
                colors: [
                    FynlaColor.Token.raspberry100.color,
                    FynlaColor.Token.savannah100.color,
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
        .accessibilityIdentifier("dashboard.level")
    }
}
