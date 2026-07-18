import Foundation
import Testing
@testable import Fynla

@Suite("Fyn transcript contracts")
struct FynModelsTests {
    @Test
    func decodesConversationCreationUsingTheExistingEnvelope() throws {
        let response = try fixture(
            "conversation-created",
            as: APIEnvelope<FynConversationRecord>.self
        )

        #expect(response.data.id == "321")
        #expect(response.data.status == "active")
    }

    @Test
    func mapsPersistedTranscriptMessagesAndActionReplies() throws {
        let payload = try fixture(
            "transcript",
            as: APIEnvelope<FynTranscript>.self
        ).data
        let messages = payload.messages.map(\.fynMessage)

        #expect(payload.conversation.id == "321")
        #expect(messages.map(\.id) == ["9001", "9002"])
        #expect(messages.first?.role == .fyn)
        #expect(messages.first?.delivery == .persisted)
        #expect(messages.first?.replies.map(\.id) == ["continue", "something_else"])
        #expect(messages.first?.replies.allSatisfy(\.isAction) == true)
        #expect(messages.last?.role == .user)
    }

    private func fixture<Value: Decodable>(
        _ name: String,
        as type: Value.Type
    ) throws -> Value {
        let url = URL(fileURLWithPath: #filePath)
            .deletingLastPathComponent()
            .appending(path: "Fixtures/Fyn/\(name).json")
        return try JSONDecoder().decode(type, from: Data(contentsOf: url))
    }
}
