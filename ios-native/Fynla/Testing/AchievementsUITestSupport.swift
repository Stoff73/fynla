#if FYNLA_UI_TESTING
import Foundation

@MainActor
enum AchievementsUITestComposition {
    static func model() -> AchievementsModel {
        AchievementsModel(client: AchievementsUITestClient())
    }
}

private struct AchievementsUITestClient: AchievementsClient {
    func loadAchievements() async throws -> AchievementsSnapshot {
        AchievementsSnapshot(
            achievements: [
                AchievementBadge(
                    key: "level",
                    title: "Reached Builder",
                    description: "Your current planning level.",
                    earned: true,
                    earnedAt: "2026-07-18T10:00:00Z",
                    state: .earned,
                    provenance: nil,
                    progress: nil,
                    nextAction: nil
                ),
            ],
            completed: [
                AchievementCompletedAction(
                    id: "action-1",
                    title: "Build your emergency fund",
                    module: "savings",
                    completedAt: "2026-07-18T10:00:00Z"
                ),
            ],
            completedTotal: 1,
            milestones: [
                AchievementMilestone(
                    key: "action:0:1",
                    title: "You completed your first action.",
                    achieved: true,
                    achievedAt: "2026-07-18T10:00:00Z",
                    state: .earned,
                    provenance: nil,
                    progress: nil,
                    nextAction: nil
                ),
            ],
            milestonesTotal: 1,
            perPage: 50,
            nextCursor: nil,
            upcoming: []
        )
    }

    func loadCompleted(page: Int) async throws -> AchievementsCompletedPage {
        AchievementsCompletedPage(
            completed: [],
            completedTotal: 1,
            page: page,
            perPage: 25
        )
    }

    func loadMilestones(cursor: String) async throws -> AchievementsMilestonePage {
        let data = Data(#"{"milestones":[],"milestones_total":1,"per_page":50,"next_cursor":null}"#.utf8)
        return try JSONDecoder().decode(AchievementsMilestonePage.self, from: data)
    }

    func loadActivity(before: Int?) async throws -> AchievementsActivityPage {
        AchievementsActivityPage(
            events: [
                AchievementActivity(
                    id: "1",
                    kind: "action",
                    label: "Completed: Build your emergency fund",
                    occurredAt: "2026-07-18T10:00:00Z"
                ),
            ],
            nextCursor: nil
        )
    }

    func loadStatus() async throws -> GamificationStatus {
        // The fireworks capture harness opts in via launch argument so the
        // takeover never covers the ordinary journey tests.
        if ProcessInfo.processInfo.arguments.contains("-fynla-pending-celebration") {
            return GamificationStatus(
                pendingCelebration: LevelCelebration(
                    level: 3,
                    levelName: "Builder",
                    nextActions: ["Add your first savings account"]
                )
            )
        }
        return GamificationStatus(pendingCelebration: nil)
    }

    func acknowledgeCelebration() async throws {}
}
#endif
