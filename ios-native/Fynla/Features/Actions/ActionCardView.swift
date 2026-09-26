import SwiftUI

/// One action's detail card (design C) — transcribes /m's ActionCard.vue. Every
/// field is the server's (ActionCardService); this view computes nothing.
struct ActionCardView: View {
    let actionID: String
    let model: ActionsModel
    let onOpenFyn: (String) -> Void
    let onOpenContextualFyn: (FynContextualAction) -> Void
    let onRoute: (AppRoute) -> Void

    var body: some View {
        Group {
            switch model.card(actionID) {
            case .idle, .loading:
                framed(title: "Action") { ScreenStateView(state: .loading) }
            case let .loaded(card):
                content(card)
            case .notFound:
                framed(title: "Action") { ScreenStateView(state: .empty(message: "This action is not in your list any more.")) }
            case .failed:
                framed(title: "Action") {
                    ScreenStateView(state: .failed(requestID: nil), retry: { Task { await model.loadCard(actionID) } })
                }
            }
        }
        .background(FynlaColor.pageBackground)
        .task { await model.loadCard(actionID) }
        .accessibilityIdentifier("action-card.screen")
    }

    private func content(_ card: ActionCard) -> some View {
        framed(title: card.moduleLabel, subtitle: card.topic) {
            VStack(alignment: .leading, spacing: 10) {
                HStack(spacing: 8) {
                    if card.done {
                        chip("Done", foreground: FynlaColor.Token.spring600.color, background: FynlaColor.Token.spring100.color)
                    } else if let deadline = card.deadline {
                        chip(deadline.label, foreground: FynlaColor.Token.violet500.color, background: FynlaColor.Token.violet500.color.opacity(0.12))
                    }
                    Text(card.eyebrow)
                        .font(.system(size: 12, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.neutral600.color)
                }
                Text(card.title)
                    .font(.system(size: 20, weight: .heavy))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                    .accessibilityIdentifier("action-card.title")
                if !card.description.isEmpty {
                    Text(card.description)
                        .font(.system(size: 14))
                        .foregroundStyle(FynlaColor.Token.neutral600.color)
                }

                if !card.why.isEmpty { bulletSection("Why this matters for you", card.why) }
                if !card.whatThisChanges.isEmpty { bulletSection("What this changes", card.whatThisChanges) }

                if let figure = card.keyFigure {
                    VStack(alignment: .leading, spacing: 2) {
                        Text(figure.label).font(.system(size: 12)).foregroundStyle(FynlaColor.Token.neutral600.color)
                        Text(figure.value).font(.system(size: 20, weight: .heavy)).foregroundStyle(FynlaColor.Token.horizon500.color)
                        if let sub = figure.sub { Text(sub).font(.system(size: 12)).foregroundStyle(FynlaColor.Token.neutral600.color) }
                    }
                    .padding(12)
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .background(FynlaColor.Token.eggshell500.color)
                    .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                }

                if let funding = card.funding, !funding.accounts.isEmpty, !card.done {
                    sectionTitle("Fund from")
                    ForEach(funding.accounts) { account in
                        fundingRow(card, account: account, selected: account.accountID == funding.selectedID && account.type == funding.selectedType)
                    }
                }

                if !card.howTo.isEmpty {
                    sectionTitle("How to do it")
                    ForEach(Array(card.howTo.enumerated()), id: \.offset) { index, step in
                        Text("\(index + 1). \(step)")
                            .font(.system(size: 14))
                            .foregroundStyle(FynlaColor.Token.horizon500.color)
                    }
                }

                if let note = card.conflictNote {
                    Text(note).font(.system(size: 13)).foregroundStyle(FynlaColor.Token.neutral600.color)
                }
                if let disclaimer = card.disclaimer {
                    Text(disclaimer).font(.system(size: 12)).foregroundStyle(FynlaColor.Token.neutral600.color)
                }

                HStack(spacing: 10) {
                    Button("Ask Fyn about this") { askFyn(card) }
                        .font(.system(size: 14, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.raspberry500.color)
                        .accessibilityIdentifier("action-card.ask-fyn")
                    Spacer(minLength: 0)
                    primaryButton(card)
                }
                .padding(.top, 8)

                if model.actionFailed {
                    Text("We could not save that just now. Please try again.")
                        .font(.system(size: 13))
                        .foregroundStyle(FynlaColor.Token.raspberry500.color)
                }
            }
            .padding(16)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(Color.white)
            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
        }
    }

    @ViewBuilder
    private func primaryButton(_ card: ActionCard) -> some View {
        if !card.done, let primary = card.primary {
            switch primary {
            case .markDone:
                Button(model.markingID == card.id ? "Saving" : "Mark as done") {
                    Task { await model.markDone(card) }
                }
                .buttonStyle(ActionCardPrimaryStyle())
                .disabled(model.markingID != nil)
                .accessibilityIdentifier("action-card.mark-done")
            case let .capture(prompt):
                Button("Add it now") { onOpenFyn(prompt) }
                    .buttonStyle(ActionCardPrimaryStyle())
                    .accessibilityIdentifier("action-card.add-it-now")
            case let .navigate(destination, payload):
                Button("Go to it") {
                    onRoute(SemanticDestinationResolver.route(for: destination, legacyPath: payload))
                }
                .buttonStyle(ActionCardPrimaryStyle())
                .accessibilityIdentifier("action-card.go-to-it")
            }
        }
    }

    private func askFyn(_ card: ActionCard) {
        switch card.askFyn {
        case let .prompt(prompt): onOpenFyn(prompt)
        case let .contextual(request): onOpenContextualFyn(FynContextualAction(request: request))
        }
    }

    private func fundingRow(_ card: ActionCard, account: ActionCard.FundingAccount, selected: Bool) -> some View {
        Button {
            Task { await model.selectFunding(card, account: account) }
        } label: {
            HStack(alignment: .top, spacing: 10) {
                Circle()
                    .strokeBorder(FynlaColor.Token.horizon500.color, lineWidth: 1.5)
                    .background(Circle().fill(selected ? FynlaColor.Token.horizon500.color : Color.clear).padding(4))
                    .frame(width: 18, height: 18)
                VStack(alignment: .leading, spacing: 2) {
                    Text("\(account.name) (\(MoneyFormatter.gbpWhole(account.balance)))")
                        .font(.system(size: 14, weight: .bold))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                    if let warning = account.warning {
                        Text(warning).font(.system(size: 12)).foregroundStyle(FynlaColor.Token.violet500.color)
                    }
                }
                Spacer(minLength: 0)
            }
            .padding(10)
            .overlay(RoundedRectangle(cornerRadius: 10, style: .continuous).stroke(FynlaColor.Token.horizon200.color, lineWidth: 1))
        }
        .buttonStyle(.plain)
        .accessibilityAddTraits(selected ? .isSelected : [])
        .accessibilityIdentifier("action-card.fund-from.\(account.id)")
    }

    private func bulletSection(_ title: String, _ lines: [String]) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            sectionTitle(title)
            ForEach(lines, id: \.self) { line in
                // A drawn list marker, as /m's list-disc — no glyph (Rule 15).
                HStack(alignment: .firstTextBaseline, spacing: 8) {
                    Circle()
                        .fill(FynlaColor.Token.horizon500.color)
                        .frame(width: 4, height: 4)
                        .alignmentGuide(.firstTextBaseline) { $0[.bottom] + 4 }
                    Text(line)
                        .font(.system(size: 14))
                        .foregroundStyle(FynlaColor.Token.horizon500.color)
                }
            }
        }
    }

    private func sectionTitle(_ title: String) -> some View {
        Text(title.uppercased())
            .font(.system(size: 12, weight: .heavy))
            .foregroundStyle(FynlaColor.Token.neutral600.color)
            .padding(.top, 8)
    }

    private func chip(_ text: String, foreground: Color, background: Color) -> some View {
        Text(text)
            .font(.system(size: 12, weight: .bold))
            .foregroundStyle(foreground)
            .padding(.horizontal, 10)
            .padding(.vertical, 2)
            .background(background)
            .clipShape(Capsule())
    }

    private func framed<Content: View>(title: String, subtitle: String? = nil, @ViewBuilder _ content: () -> Content) -> some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: title, subtitle: subtitle ?? "One of your actions")
                content()
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
    }
}

private struct ActionCardPrimaryStyle: ButtonStyle {
    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .font(.system(size: 14, weight: .bold))
            .foregroundStyle(.white)
            .padding(.horizontal, 16)
            .padding(.vertical, 10)
            .background(FynlaColor.Token.raspberry500.color.opacity(configuration.isPressed ? 0.8 : 1))
            .clipShape(Capsule())
    }
}
