import SwiftUI

struct AppRootView: View {
    let session: AppSession

    var body: some View {
        Group {
            switch session.state {
            case .launching:
                LaunchingView()
            case .signedOut,
                 .authenticating,
                 .verificationRequired,
                 .multiFactorRequired:
                SignedOutView(state: session.state)
            case .authenticatedLocked:
                LockedView(message: "Unlock to view your financial plan.")
            case .authenticatedUnlocked:
                UnlockedView()
            case .deletingAccount:
                LockedView(message: "Updating your account securely…")
            }
        }
        .preferredColorScheme(.light)
    }
}

private struct LaunchingView: View {
    var body: some View {
        VStack(spacing: 16) {
            Text("Fynla")
                .font(.largeTitle.bold())
            ProgressView()
                .accessibilityHidden(true)
        }
        .accessibilityElement(children: .combine)
        .accessibilityLabel("Fynla is starting")
        .accessibilityIdentifier("app.launching")
    }
}

private struct SignedOutView: View {
    let state: AppSession.State

    var body: some View {
        VStack(spacing: 12) {
            Image(systemName: "lock.shield")
                .font(.largeTitle)
                .accessibilityHidden(true)
            Text("Fynla")
                .font(.title.bold())
            Text(message)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
        }
        .padding()
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("auth.signedOut")
    }

    private var message: String {
        switch state {
        case .signedOut:
            "Sign in to continue."
        case .authenticating:
            "Signing in securely…"
        case .verificationRequired:
            "Verify your email to continue."
        case .multiFactorRequired:
            "Confirm your identity to continue."
        case .launching,
             .authenticatedLocked,
             .authenticatedUnlocked,
             .deletingAccount:
            "Sign in to continue."
        }
    }
}

private struct LockedView: View {
    let message: String

    var body: some View {
        VStack(spacing: 12) {
            Image(systemName: "lock.fill")
                .font(.largeTitle)
                .accessibilityHidden(true)
            Text("Fynla is locked")
                .font(.title.bold())
            Text(message)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
        }
        .padding()
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("app.locked")
    }
}

private struct UnlockedView: View {
    var body: some View {
        VStack(spacing: 12) {
            Image(systemName: "checkmark.shield")
                .font(.largeTitle)
                .accessibilityHidden(true)
            Text("Fynla")
                .font(.title.bold())
            Text("Your secure workspace is ready.")
                .foregroundStyle(.secondary)
        }
        .padding()
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("app.unlocked")
    }
}
