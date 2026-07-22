import SwiftUI

// Transcribes /m's level card (md-level in dashboard.css): translucent white
// blurred card on the gradient hero, 140pt progress wheel with a dark inner
// circle ("LEVEL" + number), heading and sub copy below. The whole card is a
// button through to Achievements.
struct LevelWheelCard: View {
    let level: DashboardLevel
    let onTap: () -> Void

    private var drawingProgress: Double {
        min(max(Double(level.progressPercent), 0), 100) / 100
    }

    var body: some View {
        Button(action: onTap) {
            VStack(spacing: 20) {
                wheel
                copy
            }
            .frame(maxWidth: .infinity)
            .padding(.top, 32)
            .padding(.bottom, 28)
            .padding(.horizontal, 24)
            .background(.ultraThinMaterial)
            .background(Color.white.opacity(0.45))
            .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
            .overlay(
                RoundedRectangle(cornerRadius: 16, style: .continuous)
                    .stroke(Color.white.opacity(0.6), lineWidth: 1)
            )
            .shadow(color: .black.opacity(0.18), radius: 16, y: 14)
        }
        .buttonStyle(.plain)
        .accessibilityLabel(
            "Level \(level.level), \(level.actionsCompleted) of \(level.actionsTotal) actions complete"
        )
        .accessibilityValue("\(level.progressPercent) percent to the next level")
        .accessibilityIdentifier("dashboard.level")
    }

    // 140pt wheel: horizon-100 track, raspberry rounded arc (stroke 10 of the
    // r44/viewBox100 SVG ≈ 14pt at this scale), 100pt horizon-600 inner circle.
    private var wheel: some View {
        ZStack {
            Circle()
                .stroke(FynlaColor.Token.horizon100.color, lineWidth: 14)
                .frame(width: 123, height: 123)
            Circle()
                .trim(from: 0, to: drawingProgress)
                .stroke(
                    FynlaColor.Token.raspberry500.color,
                    style: StrokeStyle(lineWidth: 14, lineCap: .round)
                )
                .rotationEffect(.degrees(-90))
                .frame(width: 123, height: 123)
                .animation(.easeInOut(duration: 0.45), value: drawingProgress)

            VStack(spacing: 2) {
                Text("LEVEL")
                    .font(.system(size: 11, weight: .semibold))
                    .kerning(1.1)
                    .foregroundStyle(.white.opacity(0.75))
                Text(level.level.formatted())
                    .font(.system(size: 42, weight: .black))
                    .foregroundStyle(.white)
            }
            .frame(width: 100, height: 100)
            .background(FynlaColor.Token.horizon600.color)
            .clipShape(Circle())
        }
        .frame(width: 140, height: 140)
        .accessibilityHidden(true)
    }

    private var copy: some View {
        VStack(spacing: 6) {
            Text("\(level.actionsCompleted) of \(level.actionsTotal) actions to your next level")
                .font(.system(size: 18, weight: .heavy))
                .foregroundStyle(FynlaColor.Token.horizon600.color)
                .multilineTextAlignment(.center)
            (
                Text("Complete actions to reach ")
                + Text("Level \(level.level + 1)")
                    .fontWeight(.bold)
                    .foregroundColor(FynlaColor.Token.raspberry600.color)
                + Text(".")
            )
            .font(.system(size: 14))
            .foregroundStyle(FynlaColor.Token.horizon500.color)
            .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
    }
}
