import SwiftUI

/// "See all actions" — transcribes /m's Actions.vue: every open action (the same
/// list web and /m show) and the done history. Each row opens its own card.
struct ActionsListView: View {
    let model: ActionsModel
    let onRoute: (AppRoute) -> Void

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 12) {
                MobilePageHero(title: "Your actions", subtitle: "Everything that's open, and what you've already done")
                switch model.list {
                case .idle, .loading:
                    ScreenStateView(state: .loading)
                case .failed, .notFound:
                    ScreenStateView(state: .failed(requestID: nil), retry: { Task { await model.loadList() } })
                case let .loaded(list):
                    section("Open", count: list.open.count) {
                        if list.open.isEmpty {
                            Text("Nothing outstanding. Add more of your details and Fyn will suggest the next step.")
                                .font(.system(size: 13))
                                .foregroundStyle(FynlaColor.Token.neutral600.color)
                        }
                        ForEach(Array(list.open.enumerated()), id: \.element.id) { index, item in
                            Button { onRoute(.actionCard(id: item.id)) } label: {
                                row(number: index + 1, title: item.title, meta: [item.moduleLabel, item.meta].compactMap { $0 }.joined(separator: " · "))
                            }
                            .buttonStyle(.plain)
                            .accessibilityIdentifier("actions.row.\(item.id)")
                        }
                    }
                    if !list.completed.isEmpty {
                        section("Done", count: list.completed.count) {
                            ForEach(list.completed) { item in
                                row(number: nil, title: item.text, meta: item.moduleLabel ?? "")
                            }
                        }
                    }
                }
                Color.clear.frame(height: MobileChromeMetrics.bottomClearance)
            }
        }
        .background(FynlaColor.pageBackground)
        .task { await model.loadList() }
        .accessibilityIdentifier("actions.screen")
    }

    private func section<Content: View>(_ title: String, count: Int, @ViewBuilder _ content: () -> Content) -> some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text(title).font(.system(size: 16, weight: .heavy)).foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer()
                Text("\(count)").font(.system(size: 12, weight: .heavy)).foregroundStyle(FynlaColor.Token.neutral600.color)
            }
            content()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }

    private func row(number: Int?, title: String, meta: String) -> some View {
        HStack(alignment: .top, spacing: 10) {
            if let number {
                Text("\(number)")
                    .font(.system(size: 11, weight: .heavy))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    .frame(width: 22, height: 22)
                    .background(FynlaColor.Token.raspberry500.color.opacity(0.1))
                    .clipShape(Circle())
            }
            VStack(alignment: .leading, spacing: 2) {
                Text(title).font(.system(size: 15, weight: .bold)).foregroundStyle(FynlaColor.Token.horizon500.color)
                if !meta.isEmpty {
                    Text(meta).font(.system(size: 12)).foregroundStyle(FynlaColor.Token.neutral600.color)
                }
            }
            Spacer(minLength: 0)
        }
        .contentShape(Rectangle())
    }
}
