import SwiftUI

struct ConversationHistoryView: View {
    let model: ConversationHistoryModel
    let onOpenConversation: (String) -> Void
    let onRoute: (AppRoute) -> Void
    @Environment(\.dismiss) private var dismiss

    var body: some View {
        Group {
            switch model.state {
            case .idle, .loading:
                framed { DashboardLoadingView(message: "Loading your conversations…") }
            case .loaded:
                if model.conversations.isEmpty {
                    framed {
                        ScreenStateView(
                            state: .empty(
                                message: "No conversations yet. Start a chat with Fyn and it will appear here."
                            )
                        )
                    }
                } else {
                    content
                }
            case .offline:
                stateView(.offline)
            case .unauthenticated:
                stateView(.unauthenticated)
            case let .failed(requestID):
                stateView(.failed(requestID: requestID))
            }
        }
        .background(FynlaColor.pageBackground)
        .task { await model.load() }
        .accessibilityIdentifier("conversation-history.screen")
    }

    private var content: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                hero
                MobilePageActions(onBack: { dismiss() })

                ForEach(model.sections.filter { !$0.items.isEmpty }) { section in
                    sectionCard(section)
                }
                .padding(.horizontal, 16)

                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .refreshable { await model.load() }
    }

    private var hero: some View {
        MobilePageHero(
            title: "Conversation History",
            subtitle: "Return to your previous conversations with Fyn"
        )
    }

    private func sectionCard(_ section: ConversationHistorySection) -> some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(section.mode.title.uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)

            ForEach(section.items, id: \.id) { conversation in
                conversationRow(
                    conversation,
                    showsDivider: conversation.id != section.items.last?.id
                )
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        .accessibilityIdentifier("conversation-history.\(section.mode.rawValue)")
    }

    private func conversationRow(
        _ conversation: FynConversationListItem,
        showsDivider: Bool
    ) -> some View {
        VStack(alignment: .leading, spacing: 7) {
            HStack(alignment: .top, spacing: 12) {
                VStack(alignment: .leading, spacing: 3) {
                    Text(conversation.purpose ?? conversation.title ?? "Fyn conversation")
                        .font(.system(size: 15, weight: .heavy))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    if let title = conversation.title,
                       title != conversation.purpose
                    {
                        Text(title)
                            .font(.system(size: 12))
                            .foregroundStyle(FynlaColor.Token.neutral500.color)
                    }
                }
                Spacer(minLength: 8)
                Text(conversation.status == "paused" ? "Paused" : "Active")
                    .font(.system(size: 11, weight: .heavy))
                    .textCase(.uppercase)
                    .foregroundStyle(FynlaColor.Token.violet500.color)
                    .padding(.horizontal, 8)
                    .padding(.vertical, 3)
                    .background(FynlaColor.Token.violet500.color.opacity(0.12))
                    .clipShape(Capsule())
            }

            if conversation.relatedEntity?.available == true,
               let label = conversation.relatedEntity?.label
            {
                Text(label)
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
            } else if let explanation = conversation.relatedEntity?.explanation {
                Text(explanation)
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.raspberry600.color)
            }

            if let summary = conversation.lastMessageSummary, !summary.isEmpty {
                Text(summary)
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
                    .lineLimit(3)
            }

            if let time = formatted(conversation.lastMessageAt) {
                Text(time)
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.neutral500.color)
            }

            HStack(spacing: 12) {
                Button("Open conversation") {
                    onOpenConversation(conversation.id)
                }
                .buttonStyle(.borderedProminent)
                .accessibilityIdentifier("conversation-history.open.\(conversation.id)")

                if conversation.relatedEntity?.available == false {
                    Button("Return to \(fallbackLabel(conversation))") {
                        onRoute(model.fallbackRoute(for: conversation))
                    }
                    .font(.system(size: 12, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.raspberry600.color)
                    .accessibilityIdentifier("conversation-history.fallback.\(conversation.id)")
                }
            }
        }
        .padding(.vertical, 12)
        .overlay(alignment: .bottom) {
            if showsDivider {
                FynlaColor.Token.lightGray.color.frame(height: 1)
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("conversation-history.item.\(conversation.id)")
    }

    private func framed<Content: View>(
        @ViewBuilder _ content: () -> Content
    ) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                hero
                MobilePageActions(onBack: { dismiss() })
                content()
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }

    private func stateView(_ state: ScreenStatePresentation) -> some View {
        framed {
            ScreenStateView(
                state: state,
                retry: state.canRetry ? { Task { await model.load() } } : nil
            )
        }
    }

    private func fallbackLabel(_ conversation: FynConversationListItem) -> String {
        NavigationDestinationFactory.title(for: model.fallbackRoute(for: conversation))
    }

    private func formatted(_ value: String?) -> String? {
        guard let value else { return nil }
        let formatter = ISO8601DateFormatter()
        guard let date = formatter.date(from: value) else { return nil }
        return date.formatted(
            Date.FormatStyle()
                .day()
                .month(.abbreviated)
                .year()
                .hour()
                .minute()
                .locale(Locale(identifier: "en_GB"))
        )
    }
}
