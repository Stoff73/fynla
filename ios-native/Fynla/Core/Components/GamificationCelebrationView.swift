import SwiftUI

// Transcribes /m's GamificationCelebration.vue (Rule #12 carve-out): the
// full-screen level-up takeover — dark horizon-to-violet gradient, five
// staggered fireworks (white flash core + ten burst particles each), nine
// falling confetti pieces, the LEVEL UP kicker, the animated level ring,
// congratulations copy, next-actions line, raspberry "Keep going" CTA and
// the tap-anywhere-to-dismiss hint.
struct GamificationCelebrationView: View {
    let level: Int
    let levelName: String
    let nextActions: [String]
    let onDismiss: () -> Void

    @State private var appeared = false
    @State private var animating = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    // /m palettes (component-scoped hexes, not app tokens — match exactly).
    private static let fireworkPalette: [Color] = [
        Color(hexValue: 0x20B486),
        Color(hexValue: 0xE83E6D),
        Color(hexValue: 0xA78BFA),
        Color(hexValue: 0xE6C9A8),
        Color(hexValue: 0x6EE7B7),
    ]
    private static let confettiPalette: [Color] = [
        Color(hexValue: 0xE83E6D),
        Color(hexValue: 0x20B486),
        Color(hexValue: 0xE6C9A8),
        Color(hexValue: 0xA78BFA),
        Color(hexValue: 0x6EE7B7),
    ]
    // /m firework spots as (x%, y%).
    private static let fireworkSpots: [(x: CGFloat, y: CGFloat)] = [
        (0.24, 0.30), (0.72, 0.22), (0.50, 0.14), (0.16, 0.58), (0.84, 0.62),
    ]

    var body: some View {
        GeometryReader { geo in
            ZStack {
                // 165deg gradient: #141a2e 0% → #1F2A44 35% → #2c2466 72% →
                // #5854E6 100%.
                LinearGradient(
                    stops: [
                        .init(color: Color(hexValue: 0x141A2E), location: 0),
                        .init(color: Color(hexValue: 0x1F2A44), location: 0.35),
                        .init(color: Color(hexValue: 0x2C2466), location: 0.72),
                        .init(color: Color(hexValue: 0x5854E6), location: 1),
                    ],
                    startPoint: .top,
                    endPoint: .bottom
                )

                if !reduceMotion {
                    ForEach(0..<Self.fireworkSpots.count, id: \.self) { index in
                        firework(index)
                            .position(
                                x: geo.size.width * Self.fireworkSpots[index].x,
                                y: geo.size.height * Self.fireworkSpots[index].y
                            )
                    }
                    ForEach(0..<9, id: \.self) { index in
                        confetto(index, height: geo.size.height)
                            .position(
                                x: geo.size.width * (0.08 + CGFloat(index) * 0.10),
                                y: -20
                            )
                    }
                }

                body_
            }
        }
        .ignoresSafeArea()
        .opacity(appeared ? 1 : 0)
        .contentShape(Rectangle())
        .onTapGesture { onDismiss() }
        .onAppear {
            withAnimation(.easeInOut(duration: 0.3)) { appeared = true }
            animating = true
        }
        .accessibilityElement(children: .contain)
        .accessibilityLabel("Level up: \(levelName)")
        .accessibilityAddTraits(.isModal)
        .accessibilityIdentifier("achievements.celebration")
    }

    private var body_: some View {
        VStack(spacing: 0) {
            Text("LEVEL UP")
                .font(.system(size: 13, weight: .bold))
                .kerning(3)
                .foregroundStyle(Color(hexValue: 0xA7F3D0))

            ring
                .padding(.top, 14)
                .padding(.bottom, 6)

            Text("Congratulations")
                .font(.system(size: 28, weight: .black))
                .foregroundStyle(.white)
                .padding(.top, 12)

            Text("You reached \(levelName)")
                .font(.system(size: 20, weight: .bold))
                .foregroundStyle(Color(hexValue: 0x6EE7B7))
                .padding(.top, 2)

            if !nextActions.isEmpty {
                Text("Next: \(nextActions.joined(separator: " and ")) to reach the next level.")
                    .font(.system(size: 14))
                    .foregroundStyle(Color(hexValue: 0xCBD5E1))
                    .multilineTextAlignment(.center)
                    .lineSpacing(3)
                    .frame(maxWidth: 260)
                    .padding(.top, 16)
            }

            Button {
                onDismiss()
            } label: {
                Text("Keep going")
                    .font(.system(size: 16, weight: .bold))
                    .foregroundStyle(.white)
                    .padding(.vertical, 15)
                    .padding(.horizontal, 28)
                    .background(Color(hexValue: 0xE83E6D))
                    .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
            }
            .padding(.top, 20)
            .accessibilityIdentifier("achievements.celebration.continue")

            Text("Tap anywhere to dismiss and continue")
                .font(.system(size: 13))
                .foregroundStyle(Color(hexValue: 0xCBD5E1))
                .padding(.top, 12)
        }
        .padding(28)
    }

    // ring-wrap: 140pt ring popping in; the fill sweeps to /m's dash target
    // ((389 − 96) / 389 of the circumference) with the level number centred.
    private var ring: some View {
        ZStack {
            Circle()
                .stroke(Color.white.opacity(0.15), lineWidth: 9)
            Circle()
                .trim(from: 0, to: animating ? (389 - 96) / 389 : 0)
                .stroke(
                    Color(hexValue: 0x20B486),
                    style: StrokeStyle(lineWidth: 9, lineCap: .round)
                )
                .rotationEffect(.degrees(-90))
                .animation(.easeOut(duration: 1.3).delay(0.4), value: animating)
            Text("\(level)")
                .font(.system(size: 50, weight: .black))
                .foregroundStyle(.white)
        }
        .frame(width: 140, height: 140)
        .scaleEffect(animating ? 1 : 0.4)
        .animation(
            .spring(response: 0.7, dampingFraction: 0.65),
            value: animating
        )
    }

    // fw: white flash core + ten particles bursting 58pt outward, repeating
    // on /m's 1.6s cycle with the 0.4s per-firework stagger.
    private func firework(_ index: Int) -> some View {
        let color = Self.fireworkPalette[index % Self.fireworkPalette.count]
        let delay = Double(index) * 0.4
        return ZStack {
            Circle()
                .fill(
                    RadialGradient(
                        colors: [.white, .clear],
                        center: .center,
                        startRadius: 0,
                        endRadius: 9
                    )
                )
                .frame(width: 18, height: 18)
                .scaleEffect(animating ? 1.6 : 0)
                .opacity(animating ? 0 : 1)
                .animation(
                    .easeOut(duration: 1.6).delay(delay).repeatForever(autoreverses: false),
                    value: animating
                )
            ForEach(0..<10, id: \.self) { particle in
                Circle()
                    .fill(color)
                    .frame(width: 6, height: 6)
                    .offset(y: animating ? -58 : 0)
                    .opacity(animating ? 0 : 1)
                    .rotationEffect(.degrees(Double(particle) * 36))
                    .animation(
                        .easeOut(duration: 1.6).delay(delay + 0.5).repeatForever(autoreverses: false),
                        value: animating
                    )
            }
        }
    }

    // confetti: 9pt squares falling the screen height with a 420° spin on
    // /m's 3s linear loop, staggered by (i × 0.3) mod 2.
    private func confetto(_ index: Int, height: CGFloat) -> some View {
        RoundedRectangle(cornerRadius: 2, style: .continuous)
            .fill(Self.confettiPalette[index % Self.confettiPalette.count])
            .frame(width: 9, height: 9)
            .rotationEffect(.degrees(animating ? 420 : 0))
            .offset(y: animating ? height + 40 : 0)
            .animation(
                .linear(duration: 3)
                    .delay((Double(index) * 0.3).truncatingRemainder(dividingBy: 2))
                    .repeatForever(autoreverses: false),
                value: animating
            )
    }
}

// /m uses component-scoped hexes for the celebration; a file-private hex
// initialiser keeps them exact without polluting the token catalogue.
private extension Color {
    init(hexValue: UInt32) {
        self.init(
            red: Double((hexValue >> 16) & 0xFF) / 255,
            green: Double((hexValue >> 8) & 0xFF) / 255,
            blue: Double(hexValue & 0xFF) / 255
        )
    }
}
