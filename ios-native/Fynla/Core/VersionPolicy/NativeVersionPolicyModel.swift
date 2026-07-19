import Foundation
import Observation

enum NativeVersionPolicyState: Sendable, Equatable {
    case checking
    case supported
    case updateRequired(
        requirement: NativeUpdateRequirement,
        appStoreURL: URL
    )
    case unavailable
}

@MainActor
@Observable
final class NativeVersionPolicyModel {
    private(set) var state: NativeVersionPolicyState = .checking
    private(set) var storeKitPurchaseEnabled = false

    private let client: any NativeVersionPolicyClient

    init(client: any NativeVersionPolicyClient) {
        self.client = client
    }

    @discardableResult
    func validate() async -> Bool {
        state = .checking
        storeKitPurchaseEnabled = false

        do {
            let policy = try await client.policy()
            storeKitPurchaseEnabled = policy.storeKitPurchaseEnabled
            state = .supported
            return true
        } catch let APIError.nativeUpdateRequired(requirement) {
            guard let url = validatedAppStoreURL(requirement.appStoreURL) else {
                state = .unavailable
                return false
            }
            state = .updateRequired(
                requirement: requirement,
                appStoreURL: url
            )
            return false
        } catch {
            state = .unavailable
            return false
        }
    }

    func reset() {
        storeKitPurchaseEnabled = false
        state = .checking
    }

    private func validatedAppStoreURL(_ value: String) -> URL? {
        guard let components = URLComponents(string: value),
              components.scheme == "https",
              components.host?.lowercased() == "apps.apple.com",
              components.user == nil,
              components.password == nil,
              components.port == nil,
              components.fragment == nil,
              let url = components.url
        else {
            return nil
        }
        return url
    }
}
