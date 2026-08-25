import SwiftUI

struct FynView: View {
    let model: FynConversationModel
    let onStart: () async -> Void
    let onClose: () -> Void
    let onRoute: (AppRoute) -> Void
    let onRefreshCurrentScreen: () -> Void
    let onReportProblem: () -> Void
    let onAckLevelUp: () -> Void
    @State private var announcedMessageID: String?
    @State private var dismissedLevelUp: FynLevelUp?

    var body: some View {
        VStack(spacing: 0) {
            header
            Divider()
            transcript
            FynComposerView(model: model)
        }
        .background(FynlaColor.pageBackground)
        .task { await onStart() }
        .onDisappear { model.stop() }
        .onChange(of: model.navigationRequests) { _, _ in
            settleNavigation()
        }
        .onChange(of: model.phase) { _, phase in
            guard !phase.isBusy else { return }
            settleNavigation()
            announcedMessageID = model.messages.last(where: { $0.role == .fyn })?.id
        }
        .onChange(of: model.shouldCloseAndRefresh) { _, shouldRefresh in
            guard shouldRefresh else { return }
            onRefreshCurrentScreen()
            model.clearCloseAndRefresh()
            onClose()
        }
        .overlay {
            // /m: a level_up frame after Fyn's reply triggers the shared
            // fireworks takeover over the chat (queueCelebration → z-60 over
            // the overlay). Dismiss acks the server flag as store.ack() does.
            if let levelUp = model.levelUp,
               !model.phase.isBusy,
               dismissedLevelUp != levelUp
            {
                GamificationCelebrationView(
                    level: levelUp.level,
                    levelName: levelUp.levelName,
                    nextActions: levelUp.nextActions,
                    onDismiss: {
                        dismissedLevelUp = levelUp
                        onAckLevelUp()
                    }
                )
            }
        }
    }

    // Transcribes /m's md-fyn__head: avatar + name/status left, "Report a
    // problem" text button + circular close right, on a white bar.
    private var header: some View {
        HStack(spacing: 10) {
            Image("FynAvatar")
                .resizable()
                .scaledToFill()
                .frame(width: 36, height: 36)
                .clipShape(Circle())
                .accessibilityHidden(true)

            VStack(alignment: .leading, spacing: 2) {
                Text("Fyn")
                    .font(.system(size: 15, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon600.color)
                Text("Your financial companion")
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.horizon400.color)
                    .lineLimit(2)
            }
            .frame(maxWidth: .infinity, alignment: .leading)

            Button("Report a problem", action: onReportProblem)
                .font(.system(size: 12, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon400.color)
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("fyn.report")

            Button(action: onClose) {
                Image(systemName: "xmark")
                    .font(.system(size: 16, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon600.color)
                    .frame(width: 44, height: 44)
                    .background(FynlaColor.Token.horizon100.color)
                    .clipShape(Circle())
            }
            .accessibilityLabel("Close Fyn chat")
            .accessibilityIdentifier("fyn.close")
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 10)
        .background(Color.white)
    }

    private var transcript: some View {
        ScrollViewReader { proxy in
            ScrollView {
                LazyVStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                    if model.messages.isEmpty && model.phase == .idle {
                        Text("Ask Fyn about your plan or continue setting it up.")
                            .font(FynlaTypography.body)
                            .foregroundStyle(FynlaColor.secondaryText)
                            .frame(maxWidth: .infinity, alignment: .leading)
                    }

                    ForEach(model.messages) { message in
                        FynMessageView(message: message) { reply in
                            Task { await model.chooseReply(reply) }
                        }
                        .id(message.id)
                        .accessibilitySortPriority(
                            message.id == announcedMessageID ? 1 : 0
                        )
                    }

                    statusView
                }
                .padding(FynlaSpacing.standard)
            }
            .onChange(of: model.messages.last?.text) { _, _ in
                if let id = model.messages.last?.id {
                    withAnimation(.easeOut(duration: 0.2)) {
                        proxy.scrollTo(id, anchor: .bottom)
                    }
                }
            }
            // Screen identifier lives on the scroll container — the pattern
            // that keeps sibling header buttons' identifiers resolvable.
            .accessibilityIdentifier("fyn.screen")
        }
    }

    @ViewBuilder
    private var statusView: some View {
        switch model.phase {
        case .loading:
            status("Loading your conversation…", id: "loading")
        case .accepting:
            status("Sending securely…", id: "accepting")
        case .streaming:
            status("Fyn is responding.", id: "streaming")
        case let .queued(position):
            status(
                position.map { "Your message is queued at position \($0)." }
                    ?? "Your message is queued.",
                id: "queued"
            )
        case .stillAnswering:
            status(
                "Fyn is still answering your previous message. Reopen this conversation in a moment.",
                id: "still-answering"
            )
        case let .rateLimited(retryAfter):
            status(rateLimitText(retryAfter), id: "rate-limited")
        case .offline:
            status("You're offline. Your message has not been queued or sent.", id: "offline")
        case .acceptanceUncertain:
            VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                status(
                    "The connection ended before Fyn confirmed your message. Check the conversation before sending it again.",
                    id: "uncertain"
                )
                Button("Check and retry") {
                    Task { await model.retryLastMessage() }
                }
                .buttonStyle(.borderedProminent)
                .accessibilityIdentifier("fyn.retry")
            }
        case .consentRequired:
            status("Fyn chat consent is required before you can continue.", id: "consent")
        case let .tokenLimited(message):
            status(message, id: "token-limit")
        case let .contextualResourceUnavailable(destination):
            VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                status(
                    "This related item is no longer available. Return to its overview to continue.",
                    id: "contextual-unavailable"
                )
                Button("Return to overview") {
                    onRoute(SemanticDestinationResolver.route(for: destination, legacyPath: nil))
                }
                .buttonStyle(.borderedProminent)
                .accessibilityIdentifier("fyn.contextual-unavailable.return")
            }
        case let .failed(message):
            VStack(alignment: .leading, spacing: FynlaSpacing.small) {
                status(message, id: "failure")
                Button("Try again") {
                    Task { await onStart() }
                }
                .buttonStyle(.borderedProminent)
                .accessibilityIdentifier("fyn.start.retry")
            }
        case .sessionExpired:
            status(
                "Your session expired — please sign in again.",
                id: "session-expired"
            )
        case .idle:
            EmptyView()
        }
    }

    private func status(_ text: String, id: String) -> some View {
        Text(text)
            .font(FynlaTypography.bodySmall)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(FynlaSpacing.medium)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.savannah100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
            .accessibilityIdentifier("fyn.status.\(id)")
    }

    private func settleNavigation() {
        guard let navigation = model.takeNavigation() else { return }
        onClose()
        onRoute(navigation.route)
    }

    private func rateLimitText(_ duration: Duration?) -> String {
        guard let duration else {
            return "Fyn is receiving too many messages. Please try again shortly."
        }
        let seconds = max(1, Int(duration.components.seconds))
        return "Fyn is receiving too many messages. Try again in \(seconds) seconds."
    }
}
