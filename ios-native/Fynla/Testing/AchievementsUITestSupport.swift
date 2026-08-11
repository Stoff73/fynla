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
                    key: "data_savings_account",
                    title: "Added savings details",
                    description: "You started building your savings picture.",
                    earned: true,
                    earnedAt: "2026-08-01T10:00:00Z",
                    state: .earned,
                    provenance: AchievementProvenance(
                        kind: "point_award",
                        event: "data:savings_account:first",
                        occurredAt: "2026-08-01T10:00:00Z"
                    ),
                    progress: nil,
                    nextAction: nil
                ),
            ],
            completed: [],
            completedTotal: 0,
            milestones: [
                AchievementMilestone(
                    key: "emergency_fund:0:1",
                    title: "Your emergency fund covers a month of your spending.",
                    achieved: true,
                    achievedAt: "2026-08-02T10:00:00Z",
                    state: .earned,
                    provenance: AchievementProvenance(
                        kind: "user_milestone",
                        event: "emergency_fund:0:1",
                        occurredAt: "2026-08-02T10:00:00Z"
                    ),
                    progress: nil,
                    nextAction: nil
                ),
            ],
            milestonesTotal: 1,
            perPage: 50,
            nextCursor: nil,
            upcoming: [
                AchievementUpcoming(
                    key: "net_worth:0:10000",
                    group: "Wealth",
                    title: "Net worth £10,000",
                    steps: "Add to your savings, investments or pension — you're £6,000 away.",
                    state: .inProgress,
                    progress: AchievementProgress(
                        current: 4000,
                        target: 10000,
                        percent: 40,
                        label: "£4,000 of £10,000"
                    ),
                    nextAction: AchievementNextAction(
                        label: "Review your net worth",
                        destination: SemanticDestination(
                            screen: "net_worth",
                            params: [:],
                            fallback: "dashboard"
                        )
                    ),
                    route: "m-net-worth"
                ),
                AchievementUpcoming(
                    key: "retirement_on_track:0:1",
                    group: "Retirement",
                    title: "On track for retirement",
                    steps: "Add your pensions and set your retirement target so we can check.",
                    state: .inapplicable,
                    progress: nil,
                    nextAction: AchievementNextAction(
                        label: "Review your retirement plan",
                        destination: SemanticDestination(
                            screen: "retirement",
                            params: [:],
                            fallback: "dashboard"
                        )
                    ),
                    route: "m-retirement"
                ),
            ]
        )
    }

    func loadCompleted(page: Int) async throws -> AchievementsCompletedPage {
        AchievementsCompletedPage(
            completed: [],
            completedTotal: 0,
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
