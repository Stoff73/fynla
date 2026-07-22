import SwiftUI

enum ScreenStatePresentation: Sendable, Equatable {
    case loading
    case empty(message: String)
    case offline
    case unauthenticated
    case upgradeRequired(message: String)
    case failed(requestID: String?)

    var canRetry: Bool {
        switch self {
        case .offline, .failed:
            true
        case .loading, .empty, .unauthenticated, .upgradeRequired:
            false
        }
    }

    var canUpgrade: Bool {
        if case .upgradeRequired = self { return true }
        return false
    }
}

// Transcribes /m's module state card (m-card m-state): a white card with the
// error copy and a raspberry "Try again" pill (m-btn), instead of stock
// system-blue buttons.
struct ScreenStateView: View {
    let state: ScreenStatePresentation
    var retry: (() -> Void)?
    var openSubscription: (() -> Void)?

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            switch state {
            case .loading:
                DashboardLoadingView(message: "Loading…")
            case let .empty(message):
                messageView(title: "Nothing to show yet", message: message)
            case .offline:
                messageView(
                    title: "You're offline",
                    message: "Reconnect and try again. Your saved information has not changed."
                )
            case .unauthenticated:
                messageView(
                    title: "Sign in again",
                    message: "Your secure session has ended."
                )
            case let .upgradeRequired(message):
                messageView(title: "Premium feature", message: message)
            case let .failed(requestID):
                messageView(
                    title: "We couldn't load this screen",
                    message: requestID.map { "Reference: \($0)" } ?? "Please try again."
                )
            }

            if state.canRetry, let retry {
                pillButton("Try again", action: retry)
            }
            if state.canUpgrade, let openSubscription {
                pillButton("View Premium", action: openSubscription)
            }
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background {
            if state != .loading {
                RoundedRectangle(cornerRadius: 12, style: .continuous)
                    .fill(Color.white)
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 16)
        .frame(maxWidth: .infinity, alignment: .top)
        .accessibilityIdentifier("financial.screen-state")
    }

    @ViewBuilder
    private func messageView(title: String, message: String) -> some View {
        Text(title)
            .font(.system(size: 16, weight: .bold))
            .foregroundStyle(FynlaColor.Token.horizon600.color)
        Text(message)
            .font(.system(size: 14))
            .foregroundStyle(FynlaColor.Token.neutral600.color)
    }

    // /m's m-btn: raspberry pill.
    private func pillButton(_ title: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Text(title)
                .font(.system(size: 14, weight: .bold))
                .foregroundStyle(.white)
                .padding(.horizontal, 20)
                .padding(.vertical, 9)
                .background(FynlaColor.Token.raspberry500.color)
                .clipShape(Capsule())
        }
        .accessibilityIdentifier("financial.screen-state.action")
    }
}
