#if FYNLA_UI_TESTING
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
                    earnedAt: "2026-07-18T10:00:00Z"
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
                    achievedAt: "2026-07-18T10:00:00Z"
                ),
            ],
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
        GamificationStatus(pendingCelebration: nil)
    }

    func acknowledgeCelebration() async throws {}
}
#endif
