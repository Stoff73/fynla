import Foundation
import Testing
@testable import Fynla

@Suite("Fyn typed stream reducer")
struct FynEventReducerTests {
    @Test
    func decodesEveryMobileEventAndRetainsUnknownNames() throws {
        let events = try eventFixture().map(FynEventDecoder().decode)

        #expect(events[0] == .conversationCreated("321"))
        #expect(events[1] == .text("I have saved that. "))
        #expect(events[2] == .entityCreated(name: "Cash ISA"))
        #expect(events[3] == .captureComplete(summary: "Saved Cash ISA with a balance of £12,000."))
        #expect(events[4] == .onboardingAdvance)
        #expect(events[5] == .quickReplies(
            prompt: "Do you have a pension?",
            replies: [
                FynReply(id: "yes", label: "Yes", route: nil, isAction: false),
                FynReply(id: "no", label: "No", route: nil, isAction: false),
            ],
            actionReplies: false
        ))
        #expect(events[6] == .done(messageID: "9003"))
        #expect(events[7] == .levelUp(
            FynLevelUp(level: 3, levelName: "Builder", nextActions: ["Add a pension"])
        ))
        #expect(events[8] == .unknown("future_server_event"))
        #expect(events[9] == .text("This still arrives."))
    }

    @Test
    func captureOnlyBecomesConfirmedOnCaptureComplete() throws {
        var state = FynReductionState()
        var reducer = FynEventReducer()

        reducer.reduce(.text("Saving your account."), into: &state)
        reducer.reduce(.entityCreated(name: "Cash ISA"), into: &state)
        #expect(state.messages.last?.capture == .pending(names: ["Cash ISA"]))

        reducer.reduce(
            .captureComplete(summary: "Saved Cash ISA with a balance of £12,000."),
            into: &state
        )
        #expect(state.messages.last?.capture == .confirmed(
            summary: "Saved Cash ISA with a balance of £12,000."
        ))
    }

    @Test
    func advanceSplitsAssistantTurnsAndDoneStillAllowsTrailingEvents() {
        var state = FynReductionState()
        var reducer = FynEventReducer()

        reducer.reduce(.text("That is saved."), into: &state)
        reducer.reduce(.onboardingAdvance, into: &state)
        reducer.reduce(.text("Now tell me about retirement."), into: &state)
        reducer.reduce(.done(messageID: "44"), into: &state)
        reducer.reduce(
            .levelUp(FynLevelUp(level: 2, levelName: "Planner", nextActions: [])),
            into: &state
        )

        #expect(state.messages.map(\.text) == [
            "That is saved.",
            "Now tell me about retirement.",
        ])
        #expect(state.messages.last?.delivery == .persisted)
        #expect(state.levelUp?.level == 2)
    }

    @Test
    func navigationUsesOnlyTheExactMobileAllowlist() {
        var state = FynReductionState()
        var reducer = FynEventReducer()

        reducer.reduce(.navigation(path: "/savings", section: "cash"), into: &state)
        #expect(state.pendingNavigation == FynNavigation(route: .savings(accountID: nil), section: "cash"))

        state.pendingNavigation = nil
        reducer.reduce(.navigation(path: "/admin", section: nil), into: &state)
        #expect(state.pendingNavigation == nil)
    }

    @Test
    func unknownFramesDoNotTerminateSubsequentText() {
        var state = FynReductionState()
        var reducer = FynEventReducer()

        reducer.reduce(.unknown("future_server_event"), into: &state)
        reducer.reduce(.text("Still streaming."), into: &state)

        #expect(state.unknownEventNames == ["future_server_event"])
        #expect(state.messages.last?.text == "Still streaming.")
    }

    private func eventFixture() throws -> [SSEEvent] {
        let url = URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/Fyn/events.ndjson")
        return try String(contentsOf: url, encoding: .utf8)
            .split(whereSeparator: \.isNewline)
            .map { SSEEvent(id: nil, event: nil, data: String($0)) }
    }
}
