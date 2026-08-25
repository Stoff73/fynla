import Foundation
import Observation

struct SettingsAccount: Sendable, Equatable {
    let name: String
    let email: String
    let isAdmin: Bool

    init(user: AuthenticatedUser) {
        isAdmin = user.isAdmin ?? false
        let components = [user.firstName, user.surname]
            .compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }
            .filter { !$0.isEmpty }
        let fallback = user.name?.trimmingCharacters(in: .whitespacesAndNewlines)
        name = components.isEmpty
            ? ((fallback?.isEmpty == false ? fallback : nil) ?? user.email)
            : components.joined(separator: " ")
        email = user.email
    }
}

enum SettingsPlan: Sendable, Equatable {
    case loading
    case free
    case paymentPending
    case applePremium
    case webPremium
    case unavailable

    var title: String {
        switch self {
        case .loading: "Loading"
        case .free: "Free"
        case .paymentPending: "Payment pending"
        case .applePremium, .webPremium: "Premium"
        case .unavailable: "Unavailable"
        }
    }

    var detail: String {
        switch self {
        case .loading: "Loading your current plan"
        case .free: "Core planning with Free limits"
        case .paymentPending: "Free remains active while the App Store verifies payment"
        case .applePremium: "Managed through the App Store"
        case .webPremium: "Managed on the Fynla website"
        case .unavailable: "Plan details are unavailable right now"
        }
    }
}

@MainActor
@Observable
final class SettingsModel {
    // Derive the drawer identity directly from the authenticated coordinator.
    // The drawer is reachable before SettingsView ever appears, so a stored
    // value populated only by the settings refresh can hide admin navigation.
    var account: SettingsAccount? {
        userProvider().map(SettingsAccount.init(user:))
    }

    // First name for the dashboard greeting, mirroring /m's store.user usage.
    var greetingFirstName: String? {
        let user = userProvider()
        if let first = user?.firstName?.trimmingCharacters(in: .whitespacesAndNewlines),
           !first.isEmpty {
            return first
        }
        let name = user?.name?.trimmingCharacters(in: .whitespacesAndNewlines)
        return name?.split(separator: " ").first.map(String.init)
    }

    // Mirrors /m's store.user.onboarding_completed (Tax Strategy intro gate).
    var onboardingCompleted: Bool {
        userProvider()?.onboardingCompleted == true
    }

    // /m onboardingChat.onboardingActive: explicitly-incomplete or
    // campaign-re-entry users with a non-null onboarding step.
    var onboardingActive: Bool {
        guard let user = userProvider() else { return false }
        return (user.onboardingCompleted == false || user.activeCampaign != nil)
            && user.onboardingFynStep != nil
    }

    private(set) var plan: SettingsPlan = .loading
    private(set) var faceIDEnabled = false
    private(set) var canEnableFaceID = false
    private(set) var isChangingFaceID = false
    private(set) var securityError: String?

    let privacyURL: URL
    let termsURL: URL
    let supportURL: URL

    private let userProvider: @MainActor () -> AuthenticatedUser?
    private let privacyLockController: PrivacyLockController?
    private let beforeSignOut: @MainActor @Sendable () async -> Void

    init(
        userProvider: @escaping @MainActor () -> AuthenticatedUser?,
        privacyLockController: PrivacyLockController?,
        webBaseURL: URL,
        beforeSignOut: @escaping @MainActor @Sendable () async -> Void = {}
    ) {
        self.userProvider = userProvider
        self.privacyLockController = privacyLockController
        self.beforeSignOut = beforeSignOut
        privacyURL = Self.requiredPublicURL(path: "privacy", relativeTo: webBaseURL)
        termsURL = Self.requiredPublicURL(path: "terms", relativeTo: webBaseURL)
        supportURL = Self.requiredPublicURL(path: "help", relativeTo: webBaseURL)
        refreshSecurityState()
    }

    nonisolated static func isTrustedPublicURL(
        _ candidate: URL,
        relativeTo baseURL: URL
    ) -> Bool {
        guard let candidateComponents = URLComponents(
            url: candidate,
            resolvingAgainstBaseURL: false
        ),
        let baseComponents = URLComponents(
            url: baseURL,
            resolvingAgainstBaseURL: false
        )
        else {
            return false
        }

        return candidateComponents.scheme?.lowercased() == "https"
            && candidateComponents.scheme?.lowercased() == baseComponents.scheme?.lowercased()
            && candidateComponents.host?.lowercased() == baseComponents.host?.lowercased()
            && candidateComponents.port == baseComponents.port
            && candidateComponents.user == nil
            && candidateComponents.password == nil
    }

    private nonisolated static func requiredPublicURL(
        path: String,
        relativeTo baseURL: URL
    ) -> URL {
        let candidate = baseURL.appending(path: path)
        guard isTrustedPublicURL(candidate, relativeTo: baseURL) else {
            preconditionFailure("Invalid trusted public web URL")
        }
        return candidate
    }

    func refresh(subscription: SubscriptionUIState) {
        plan = switch subscription {
        case .loading:
            .loading
        case let .free(_, _, isPending):
            isPending ? .paymentPending : .free
        case .applePremium:
            .applePremium
        case .webPremium:
            .webPremium
        case .unavailable:
            .unavailable
        }
        refreshSecurityState()
    }

    func enableFaceID() async {
        guard let privacyLockController else { return }
        isChangingFaceID = true
        securityError = nil
        defer {
            isChangingFaceID = false
            refreshSecurityState()
        }
        do {
            try await privacyLockController.enableFaceID()
        } catch {
            securityError = "Face ID could not be enabled. Please try again."
        }
    }

    func disableFaceID() async {
        guard let privacyLockController else { return }
        isChangingFaceID = true
        securityError = nil
        defer {
            isChangingFaceID = false
            refreshSecurityState()
        }
        do {
            try await privacyLockController.disableFaceID()
        } catch {
            securityError = "Face ID could not be disabled. Please try again."
        }
    }

    func lock() {
        privacyLockController?.lock()
    }

    func signOut() async {
        await beforeSignOut()
        await privacyLockController?.signOut()
    }

    private func refreshSecurityState() {
        faceIDEnabled = privacyLockController?.canUnlockWithFaceID == true
        canEnableFaceID = privacyLockController?.shouldOfferFaceID == true
    }
}
