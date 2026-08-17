import Foundation
import Testing
@testable import Fynla

@Suite("Achievements contracts")
struct AchievementsModelsTests {
    @Test
    func canonicalBadgeStateAndProvenanceOverrideContradictoryLegacyFields() throws {
        let badge = try JSONDecoder().decode(
            AchievementBadge.self,
            from: Data(#"""
            {
                "key":"canonical-badge",
                "title":"Canonical badge",
                "description":"Server-owned presentation",
                "earned":false,
                "earned_at":"2001-01-01T00:00:00Z",
                "state":"earned",
                "provenance":{
                    "kind":"point_award",
                    "event":"raw.internal.event",
                    "occurred_at":"2026-08-10T09:30:00Z"
                },
                "progress":null,
                "next_action":null
            }
            """#.utf8)
        )

        let presentation = AchievementPresentation.badge(badge)

        #expect(presentation.status == "Earned 10/08/2026")
        #expect(presentation.isEarned)
        #expect(!presentation.status.contains("raw.internal.event"))
        #expect(!presentation.status.contains("01/01/2001"))
    }

    @Test
    func canonicalMilestoneStateOverridesContradictoryLegacyFields() throws {
        let milestone = try JSONDecoder().decode(
            AchievementMilestone.self,
            from: Data(#"""
            {
                "key":"canonical-milestone",
                "title":"Canonical milestone",
                "achieved":true,
                "achieved_at":"2001-01-01T00:00:00Z",
                "state":"locked",
                "provenance":{
                    "kind":"user_milestone",
                    "event":"raw.milestone.event",
                    "occurred_at":"2026-08-10T09:30:00Z"
                },
                "progress":null,
                "next_action":null
            }
            """#.utf8)
        )

        let presentation = AchievementPresentation.milestone(milestone)

        #expect(presentation.status == "Locked")
        #expect(!presentation.isEarned)
        #expect(!presentation.status.contains("raw.milestone.event"))
        #expect(!presentation.status.contains("01/01/2001"))
    }

    @Test
    func decodesTheCanonicalMobileAchievementsContractWithoutReordering() throws {
        let snapshot = try fixture(
            "summary",
            as: APIEnvelope<AchievementsSnapshot>.self
        ).data

        #expect(snapshot.achievements.map(\.key) == ["level", "streak"])
        #expect(snapshot.completed.map(\.id) == ["action-2", "action-1"])
        #expect(snapshot.completedTotal == 3)
        #expect(snapshot.milestones.map(\.key) == ["action:0:1"])
        #expect(snapshot.milestonesTotal == 2)
        #expect(snapshot.perPage == 1)
        #expect(snapshot.nextCursor == "eyJ2IjoxLCJjdXJzb3IiOiJub25jZSJ9")
        #expect(snapshot.achievements[0].state == .earned)
        #expect(snapshot.achievements[0].provenance?.kind == "point_award")
        #expect(snapshot.upcoming.map(\.state) == [.inProgress, .locked, .inapplicable])
        #expect(snapshot.upcoming[0].progress?.percent == 50)
        #expect(snapshot.upcoming[0].nextAction?.destination.screen == "savings")
        #expect(snapshot.upcoming[1].progress == nil)
        #expect(snapshot.upcoming[2].progress == nil)
        #expect(SemanticDestinationResolver.route(for: snapshot.upcoming[1].nextAction?.destination, legacyPath: snapshot.upcoming[1].route) == .estate)
    }

    @Test
    func decodesCompletedPageAndCursorActivityShapes() throws {
        let completed = try fixture(
            "completed-page-2",
            as: APIEnvelope<AchievementsCompletedPage>.self
        ).data
        let activity = try fixture(
            "activity-page-1",
            as: AchievementsActivityPage.self
        )

        #expect(completed.page == 2)
        #expect(completed.completed.map(\.id) == ["action-1", "action-0"])
        #expect(activity.events.map(\.id) == ["102", "101"])
        #expect(activity.nextCursor == 101)
    }

    @Test
    func decodesPendingLevelCelebrationAsPlainTextData() throws {
        let status = try fixture("status", as: GamificationStatus.self)

        #expect(status.pendingCelebration?.level == 3)
        #expect(status.pendingCelebration?.levelName == "Builder")
    }

    private func fixture<Value: Decodable>(
        _ name: String,
        as type: Value.Type
    ) throws -> Value {
        let data = try Data(
            contentsOf: URL(fileURLWithPath: #filePath)
                .deletingLastPathComponent()
                .appending(path: "Fixtures/Achievements/\(name).json")
        )
        return try JSONDecoder().decode(type, from: data)
    }
}
