import Foundation
import Testing
@testable import Fynla

@Suite("Achievements model")
struct AchievementsModelTests {
    @Test @MainActor
    func loadsPaginatesAndDeduplicatesByServerIdentifier() async throws {
        let summary = try summaryFixture()
        let page = try fixture(
            "completed-page-2",
            as: APIEnvelope<AchievementsCompletedPage>.self
        ).data
        let activity1 = try fixture(
            "activity-page-1",
            as: AchievementsActivityPage.self
        )
        let activity2 = try fixture(
            "activity-page-2",
            as: AchievementsActivityPage.self
        )
        let status = try fixture("status", as: GamificationStatus.self)
        let client = AchievementsClientStub(
            summary: summary,
            completedPages: [page],
            activityPages: [activity1, activity2],
            status: status
        )
        let model = AchievementsModel(client: client)

        await model.load()
        await model.loadMoreCompleted()
        await model.loadMoreActivity()

        let content = try #require(model.content)
        #expect(content.summary.completed.map(\.id) == [
            "action-2", "action-1", "action-0",
        ])
        #expect(content.activity.map(\.id) == ["102", "101", "100"])
        #expect(content.activityNextCursor == nil)
        #expect(model.paginationMessage == nil)
    }

    @Test @MainActor
    func onlyClearsCelebrationAfterBackendAcknowledgement() async throws {
        let client = AchievementsClientStub(
            summary: try summaryFixture(),
            completedPages: [],
            activityPages: [
                try fixture("activity-page-1", as: AchievementsActivityPage.self),
            ],
            status: try fixture("status", as: GamificationStatus.self),
            acknowledgementResults: [
                .failure(APIError.offline),
                .success(()),
            ]
        )
        let model = AchievementsModel(client: client)

        await model.load()
        await model.dismissCelebration()
        #expect(model.content?.pendingCelebration?.level == 3)
        #expect(model.celebrationMessage != nil)

        await model.dismissCelebration()
        #expect(model.content?.pendingCelebration == nil)
        #expect(model.celebrationMessage == nil)
    }

    @Test @MainActor
    func paginationFailureKeepsLoadedProgressVisible() async throws {
        let client = AchievementsClientStub(
            summary: try summaryFixture(),
            completedPages: [],
            activityPages: [
                try fixture("activity-page-1", as: AchievementsActivityPage.self),
            ],
            status: GamificationStatus(pendingCelebration: nil),
            activityFailure: APIError.offline
        )
        let model = AchievementsModel(client: client)

        await model.load()
        await model.loadMoreActivity()

        #expect(model.content?.activity.map(\.id) == ["102", "101"])
        #expect(model.paginationMessage != nil)
    }

    private func summaryFixture() throws -> AchievementsSnapshot {
        try fixture(
            "summary",
            as: APIEnvelope<AchievementsSnapshot>.self
        ).data
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

private actor AchievementsClientStub: AchievementsClient {
    private let summary: AchievementsSnapshot
    private var completedPages: [AchievementsCompletedPage]
    private var activityPages: [AchievementsActivityPage]
    private let status: GamificationStatus
    private var acknowledgementResults: [Result<Void, Error>]
    private let activityFailure: Error?

    init(
        summary: AchievementsSnapshot,
        completedPages: [AchievementsCompletedPage],
        activityPages: [AchievementsActivityPage],
        status: GamificationStatus,
        acknowledgementResults: [Result<Void, Error>] = [.success(())],
        activityFailure: Error? = nil
    ) {
        self.summary = summary
        self.completedPages = completedPages
        self.activityPages = activityPages
        self.status = status
        self.acknowledgementResults = acknowledgementResults
        self.activityFailure = activityFailure
    }

    func loadAchievements() async throws -> AchievementsSnapshot { summary }

    func loadCompleted(page: Int) async throws -> AchievementsCompletedPage {
        guard !completedPages.isEmpty else { throw APIError.server(status: 500, requestID: nil) }
        return completedPages.removeFirst()
    }

    func loadActivity(before: Int?) async throws -> AchievementsActivityPage {
        if before != nil, let activityFailure { throw activityFailure }
        guard !activityPages.isEmpty else { throw APIError.server(status: 500, requestID: nil) }
        return activityPages.removeFirst()
    }

    func loadStatus() async throws -> GamificationStatus { status }

    func acknowledgeCelebration() async throws {
        guard !acknowledgementResults.isEmpty else { return }
        try acknowledgementResults.removeFirst().get()
    }
}
