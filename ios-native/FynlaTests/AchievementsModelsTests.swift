import Foundation
import Testing
@testable import Fynla

@Suite("Achievements contracts")
struct AchievementsModelsTests {
    @Test
    func decodesTheExistingMobileAchievementsContractWithoutReordering() throws {
        let snapshot = try fixture(
            "summary",
            as: APIEnvelope<AchievementsSnapshot>.self
        ).data

        #expect(snapshot.achievements.map(\.key) == ["level", "streak"])
        #expect(snapshot.completed.map(\.id) == ["action-2", "action-1"])
        #expect(snapshot.completedTotal == 3)
        #expect(snapshot.milestones.map(\.key) == ["action:0:1"])
        #expect(snapshot.upcoming.map(\.route) == ["m-savings"])
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
