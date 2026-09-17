import SwiftUI

// Transcribes /m's level card (md-level in dashboard.css): translucent white
// blurred card on the gradient hero, 140pt progress wheel with a dark inner
// circle ("LEVEL" + number), heading and sub copy below. The whole card is a
// button through to Achievements.
struct LevelWheelCard: View {
    let level: DashboardLevel
    /// The banked climb, from the gamification status. When `celebrateTo`
    /// exceeds `celebrateFrom` the number walks the range one level at a time
    /// with a confetti burst per step, replacing the full-screen takeover
    /// removed on 2026-09-17.
    var celebrateFrom: Int = 1
    var celebrateTo: Int = 1
    /// True while Fyn is presented over the dashboard — the climb waits.
    var fynOpen: Bool = false
    var onAcknowledge: (Int) async -> Void = { _ in }
    /// Last, so the card keeps its trailing-closure call site.
    let onTap: () -> Void

    @Environment(\.accessibilityReduceMotion) private var reduceMotion
    @Environment(\.scenePhase) private var scenePhase

    @State private var displayLevel: Int?
    @State private var ringPercent: Double?
    @State private var stepping = false
    @State private var burstID: Int?
    @State private var celebrating = false

    /// The number the wheel shows. Before and after a climb this is simply the
    /// real level; during one it is whatever step we are on.
    private var shownLevel: Int { displayLevel ?? level.level }

    private var drawingProgress: Double {
        let pct = ringPercent ?? Double(level.progressPercent)
        return min(max(pct, 0), 100) / 100
    }

    private var truePercent: Double { Double(level.progressPercent) }

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
        .task(id: owedCount) { await playBankedLevels() }
        .task(id: fynOpen) { if !fynOpen { await playBankedLevels() } }
        .onChange(of: level.progressPercent) { _, pct in
            if !celebrating { ringPercent = Double(pct) }
        }
    }

    private var owedCount: Int { max(0, celebrateTo - celebrateFrom) }

    /// Walk the banked range on the wheel: the number steps one level at a
    /// time, confetti bursts from the wheel at each step, and the ring sweeps
    /// full then resets, settling on the true value at the end.
    ///
    /// Under Reduce Motion the number simply lands on the final level with no
    /// burst and no sweep — but it STILL acknowledges, or the user accumulates
    /// a queue they can never spend.
    private func playBankedLevels() async {
        guard !celebrating else { return }

        guard owedCount > 0 else {
            displayLevel = nil
            ringPercent = truePercent
            return
        }

        guard LevelCelebrationSequence.dashboardIsBeingViewed(
            onDashboard: true,
            fynOpen: fynOpen,
            backgrounded: scenePhase != .active
        ) else { return }

        celebrating = true
        let target = celebrateTo
        displayLevel = celebrateFrom

        let acked = await LevelCelebrationSequence.run(
            from: celebrateFrom,
            to: target,
            reducedMotion: reduceMotion
        ) { lvl, burst in
            displayLevel = lvl
            stepping = true
            Task {
                try? await Task.sleep(for: .milliseconds(260))
                stepping = false
            }

            guard burst else {
                ringPercent = truePercent
                return
            }

            burstID = lvl
            Task {
                try? await Task.sleep(for: .milliseconds(800))
                if burstID == lvl { burstID = nil }
            }

            // Sweep the ring full, then snap it back ready for the next level.
            // 420ms sits just inside the 0.45s ring animation, so each sweep
            // lands before the next starts.
            ringPercent = 100
            Task {
                try? await Task.sleep(for: .milliseconds(420))
                ringPercent = lvl == target ? truePercent : 0
            }
        }

        celebrating = false
        if let acked { await onAcknowledge(acked) }
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
                Text(shownLevel.formatted())
                    .font(.system(size: 42, weight: .black))
                    .foregroundStyle(.white)
                    .scaleEffect(stepping ? 1.28 : 1)
                    .animation(
                        reduceMotion ? nil : .spring(response: 0.26, dampingFraction: 0.5),
                        value: stepping
                    )
            }
            .frame(width: 100, height: 100)
            .background(FynlaColor.Token.horizon600.color)
            .clipShape(Circle())
        }
        .frame(width: 140, height: 140)
        .overlay {
            if let burstID, !reduceMotion {
                LevelConfettiBurst().id(burstID)
            }
        }
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

/// Eighteen pieces thrown radially from the level wheel's edge. The takeover
/// this replaced covered the whole screen; this sprays from the wheel itself.
/// Spring, raspberry and violet only (Rule 8).
private struct LevelConfettiBurst: View {
    private static let palette: [FynlaColor.Token] = [.spring500, .raspberry500, .violet500]
    private static let count = 18

    @State private var flung = false

    var body: some View {
        ZStack {
            ForEach(0..<Self.count, id: \.self) { index in
                let angle = Angle.degrees(Double(index) * (360.0 / Double(Self.count)))
                let distance: Double = flung ? 116 : 46

                RoundedRectangle(cornerRadius: 1, style: .continuous)
                    .fill(Self.palette[index % Self.palette.count].color)
                    .frame(width: 7, height: 11)
                    .rotationEffect(.degrees(flung ? 420 : 0))
                    .offset(
                        x: cos(angle.radians - .pi / 2) * distance,
                        y: sin(angle.radians - .pi / 2) * distance
                    )
                    .opacity(flung ? 0 : 1)
                    .scaleEffect(flung ? 1 : 0.6)
                    .animation(
                        .easeOut(duration: 0.8).delay(Double(index % 6) * 0.015),
                        value: flung
                    )
            }
        }
        .allowsHitTesting(false)
        .onAppear { flung = true }
    }
}
