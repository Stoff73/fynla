import Foundation
import Testing
@testable import Fynla

/// The same vectors as `resources/js/utils/__tests__/levelCelebration.spec.js`
/// and its `/m` twin. Three implementations of one rule are held together by
/// these, not by a shared import — if you change one, change all three.
@Suite("Level celebration sequence")
@MainActor
struct LevelCelebrationSequenceTests {
    @Test
    func owesNothingWhenTheRangeIsEmpty() {
        #expect(LevelCelebrationSequence.levelsOwed(from: 4, to: 4) == [])
    }

    @Test
    func owesEveryLevelAboveTheOneLastCelebrated() {
        #expect(LevelCelebrationSequence.levelsOwed(from: 3, to: 6) == [4, 5, 6])
    }

    @Test
    func owesASingleLevelForASingleCrossing() {
        #expect(LevelCelebrationSequence.levelsOwed(from: 1, to: 2) == [2])
    }

    @Test
    func owesNothingWhenTheServerSendsARangeThatRunsBackwards() {
        #expect(LevelCelebrationSequence.levelsOwed(from: 6, to: 3) == [])
    }

    /// The bottom of the ladder is 1, never the current level: coalescing to
    /// the level would give a brand-new user from == to on their first
    /// level-up, and they would never be celebrated at all.
    @Test
    func treatsAMissingStartAsTheBottomOfTheLadder() {
        #expect(LevelCelebrationSequence.levelsOwed(from: nil, to: 2) == [2])
        #expect(LevelCelebrationSequence.levelsOwed(from: nil, to: nil) == [])
    }

    @Test
    func isBeingViewedOnTheDashboardWithFynClosedAndTheAppInFront() {
        #expect(LevelCelebrationSequence.dashboardIsBeingViewed(
            onDashboard: true, fynOpen: false, backgrounded: false
        ))
    }

    @Test
    func isNotBeingViewedWhileFynIsOverTheDashboard() {
        #expect(!LevelCelebrationSequence.dashboardIsBeingViewed(
            onDashboard: true, fynOpen: true, backgrounded: false
        ))
    }

    @Test
    func isNotBeingViewedAwayFromTheDashboard() {
        #expect(!LevelCelebrationSequence.dashboardIsBeingViewed(
            onDashboard: false, fynOpen: false, backgrounded: false
        ))
    }

    /// A backgrounded app matters: the climb would otherwise be spent and
    /// acknowledged without anyone seeing it.
    @Test
    func isNotBeingViewedWhileBackgrounded() {
        #expect(!LevelCelebrationSequence.dashboardIsBeingViewed(
            onDashboard: true, fynOpen: false, backgrounded: true
        ))
    }

    @Test
    func doesNothingAndAcknowledgesNothingWhenNoLevelIsOwed() async {
        var seen: [(Int, Bool)] = []
        let acked = await LevelCelebrationSequence.run(
            from: 5, to: 5, reducedMotion: false,
            wait: { _ in },
            onLevel: { level, burst in seen.append((level, burst)) }
        )

        #expect(seen.isEmpty)
        #expect(acked == nil)
    }

    @Test
    func stepsThroughEveryOwedLevelInOrderBurstingAtEach() async {
        var seen: [(Int, Bool)] = []
        let acked = await LevelCelebrationSequence.run(
            from: 3, to: 6, reducedMotion: false,
            wait: { _ in },
            onLevel: { level, burst in seen.append((level, burst)) }
        )

        let levels = seen.map(\.0)
        let everyStepBurst = seen.filter(\.1).count == seen.count
        #expect(levels == [4, 5, 6])
        #expect(everyStepBurst)
        #expect(acked == 6)
    }

    @Test
    func waitsBetweenLevelsSoTheClimbIsLegible() async {
        var waits: [Duration] = []
        _ = await LevelCelebrationSequence.run(
            from: 3, to: 5, reducedMotion: false,
            wait: { waits.append($0) },
            onLevel: { _, _ in }
        )

        let everyWaitIsAStep = waits.filter { $0 == LevelCelebrationSequence.step }.count == waits.count
        #expect(waits.count == 2)
        #expect(everyWaitIsAStep)
    }

    /// Reduced motion still acknowledges, or the user accumulates a queue they
    /// can never spend.
    @Test
    func underReducedMotionLandsOnTheFinalLevelWithNoBurstAndStillAcknowledges() async {
        var seen: [(Int, Bool)] = []
        var waits: [Duration] = []
        let acked = await LevelCelebrationSequence.run(
            from: 3, to: 6, reducedMotion: true,
            wait: { waits.append($0) },
            onLevel: { level, burst in seen.append((level, burst)) }
        )

        #expect(seen.count == 1)
        #expect(seen.first?.0 == 6)
        #expect(seen.first?.1 == false)
        #expect(waits.isEmpty)
        #expect(acked == 6)
    }
}
