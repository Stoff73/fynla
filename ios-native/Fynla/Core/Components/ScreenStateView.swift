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

struct ScreenStateView: View {
    let state: ScreenStatePresentation
    var retry: (() -> Void)?
    var openSubscription: (() -> Void)?

    var body: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            switch state {
            case .loading:
                LoadingView(message: "Loading")
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
                Button("Try again", action: retry)
                    .buttonStyle(.borderedProminent)
            }
            if state.canUpgrade, let openSubscription {
                Button("View Premium", action: openSubscription)
                    .buttonStyle(.borderedProminent)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(FynlaSpacing.standard)
        .accessibilityIdentifier("financial.screen-state")
    }

    @ViewBuilder
    private func messageView(title: String, message: String) -> some View {
        Text(title)
            .font(FynlaTypography.sectionTitle)
            .foregroundStyle(FynlaColor.primaryText)
        Text(message)
            .font(FynlaTypography.body)
            .foregroundStyle(FynlaColor.secondaryText)
    }
}
