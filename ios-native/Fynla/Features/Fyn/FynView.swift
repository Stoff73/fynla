import SwiftUI

struct FynView: View {
    let model: FynConversationModel
    let onClose: () -> Void
    let onRoute: (AppRoute) -> Void
    let onRefreshCurrentScreen: () -> Void
    @State private var announcedMessageID: String?

    var body: some View {
        VStack(spacing: 0) {
            header
            Divider()
            transcript
            Divider()
            FynComposerView(model: model)
        }
        .background(FynlaColor.pageBackground)
        .task { await model.open() }
        .onDisappear { model.stop() }
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
        .accessibilityIdentifier("fyn.screen")
    }

    private var header: some View {
        HStack(spacing: FynlaSpacing.standard) {
            VStack(alignment: .leading, spacing: FynlaSpacing.micro) {
                Text("Fyn")
                    .font(FynlaTypography.sectionTitle)
                    .foregroundStyle(FynlaColor.primaryText)
                Text("Your financial planning assistant")
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.secondaryText)
            }
            Spacer()
            Button("Close", action: onClose)
                .font(FynlaTypography.button)
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                .accessibilityIdentifier("fyn.close")
        }
        .padding(.horizontal, FynlaSpacing.standard)
        .padding(.vertical, FynlaSpacing.small)
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

                    if let levelUp = model.levelUp, !model.phase.isBusy {
                        VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                            Text("Level \(levelUp.level)")
                                .font(FynlaTypography.heading)
                            Text("You've reached \(levelUp.levelName).")
                                .font(FynlaTypography.bodySmall)
                        }
                        .foregroundStyle(FynlaColor.primaryText)
                        .padding(FynlaSpacing.medium)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .background(FynlaColor.Token.horizon200.color.opacity(0.25))
                        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
                        .accessibilityIdentifier("fyn.level-up")
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
        case let .failed(message):
            status(message, id: "failure")
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
