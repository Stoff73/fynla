import Foundation
import Observation

enum ConversationHistoryState: Sendable, Equatable {
    case idle
    case loading
    case loaded
    case offline
    case unauthenticated
    case failed(requestID: String?)
}

struct ConversationHistorySection: Identifiable, Sendable, Equatable {
    enum Mode: String, Sendable, Equatable, CaseIterable {
        case onboarding
        case contextual
        case general

        var title: String {
            switch self {
            case .onboarding: "Onboarding"
            case .contextual: "Contextual"
            case .general: "General Fyn"
            }
        }
    }

    let mode: Mode
    let items: [FynConversationListItem]

    var id: Mode { mode }
}

@MainActor
@Observable
final class ConversationHistoryModel {
    typealias Loader = @MainActor () async throws -> [FynConversationListItem]

    private let loadConversations: Loader
    private(set) var state: ConversationHistoryState = .idle
    private(set) var conversations: [FynConversationListItem] = []

    init(loadConversations: @escaping Loader) {
        self.loadConversations = loadConversations
    }

    var sections: [ConversationHistorySection] {
        ConversationHistorySection.Mode.allCases.map { mode in
            ConversationHistorySection(
                mode: mode,
                items: conversations.filter { ($0.mode ?? "general") == mode.rawValue }
            )
        }
    }

    func load() async {
        state = .loading
        conversations = []
        do {
            conversations = try await loadConversations()
            state = .loaded
        } catch is CancellationError {
            state = .idle
        } catch APIError.offline {
            state = .offline
        } catch APIError.unauthenticated {
            state = .unauthenticated
        } catch let APIError.server(_, requestID),
                let APIError.decoding(requestID)
        {
            state = .failed(requestID: requestID)
        } catch {
            state = .failed(requestID: nil)
        }
    }

    func fallbackRoute(for conversation: FynConversationListItem) -> AppRoute {
        SemanticDestinationResolver.route(
            for: conversation.fallbackDestination,
            legacyPath: nil
        )
    }
}
