import Foundation

/// The level-up celebration rule, in one place.
///
/// The full-screen takeover was removed on 2026-09-17: level-ups are banked
/// server-side as a range and spent only on the dashboard hero wheel, where
/// the number climbs one level at a time with a confetti burst per increment.
///
/// The web SPA and `/m` each carry their own copy of this rule — separate Vite
/// bundles cannot import each other and this one is Swift — so the three are
/// kept honest by the same test vectors, not by a shared import. If you change
/// behaviour here, change it in `resources/js/utils/levelCelebration.js` and
/// `resources/mobile/navigation/levelCelebration.js` in the same commit.
enum LevelCelebrationSequence {
    /// Pause between level steps. Three levels lands under 3s.
    static let step: Duration = .milliseconds(900)

    /// Every level owed, ascending. The ladder starts at 1, so a missing
    /// `from` means "never celebrated anything".
    static func levelsOwed(from: Int?, to: Int?) -> [Int] {
        let start = from ?? 1
        let end = to ?? 1
        guard end > start else { return [] }
        return Array((start + 1)...end)
    }

    /// The dashboard is what the user is actually looking at: it is the active
    /// screen, Fyn is not over it, and the app is in the foreground. The last
    /// one matters — the climb would otherwise be spent and acknowledged
    /// without anyone seeing it.
    static func dashboardIsBeingViewed(onDashboard: Bool, fynOpen: Bool, backgrounded: Bool) -> Bool {
        onDashboard && !fynOpen && !backgrounded
    }

    /// Walk the owed levels, calling `onLevel(level, burst)` at each.
    ///
    /// Under reduced motion the number simply lands on the final level with no
    /// burst — but the sequence STILL returns a level to acknowledge, or the
    /// user accumulates a queue they can never spend.
    ///
    /// - Returns: the level to acknowledge, or `nil` when nothing was owed.
    /// Main-actor bound: `onLevel` drives SwiftUI `@State` on the wheel, so
    /// the whole walk stays on the main actor rather than sending a
    /// non-Sendable closure across an isolation boundary.
    @MainActor
    @discardableResult
    static func run(
        from: Int?,
        to: Int?,
        reducedMotion: Bool,
        wait: (Duration) async -> Void = { try? await Task.sleep(for: $0) },
        onLevel: (Int, Bool) -> Void
    ) async -> Int? {
        let levels = levelsOwed(from: from, to: to)
        guard let last = levels.last else { return nil }

        if reducedMotion {
            onLevel(last, false)
            return last
        }

        for level in levels {
            onLevel(level, true)
            // The pause between levels IS the animation, so this is deliberate.
            await wait(step)
        }

        return last
    }
}
