import Foundation
import Observation

struct SettingsAccount: Sendable, Equatable {
    let name: String
    let email: String

    init(user: AuthenticatedUser) {
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
    private(set) var account: SettingsAccount?
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

    init(
        userProvider: @escaping @MainActor () -> AuthenticatedUser?,
        privacyLockController: PrivacyLockController?,
        webBaseURL: URL
    ) {
        self.userProvider = userProvider
        self.privacyLockController = privacyLockController
        privacyURL = webBaseURL.appending(path: "privacy")
        termsURL = webBaseURL.appending(path: "terms")
        supportURL = webBaseURL.appending(path: "contact")
        refreshSecurityState()
    }

    func refresh(subscription: SubscriptionUIState) {
        account = userProvider().map(SettingsAccount.init(user:))
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
        await privacyLockController?.signOut()
    }

    private func refreshSecurityState() {
        faceIDEnabled = privacyLockController?.canUnlockWithFaceID == true
        canEnableFaceID = privacyLockController?.shouldOfferFaceID == true
    }
}
