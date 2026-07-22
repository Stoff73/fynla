import Foundation
import Observation

protocol FaceIDPreference: Sendable {
    func isFaceIDEnabled() async -> Bool
    func setFaceIDEnabled(_ enabled: Bool) async
}

actor UserDefaultsFaceIDPreference: FaceIDPreference {
    private static let key = "face-id.enabled"
    private let defaults: UserDefaults

    init(suiteName: String? = nil) {
        defaults = suiteName.flatMap(UserDefaults.init(suiteName:)) ?? .standard
    }

    func isFaceIDEnabled() -> Bool {
        defaults.bool(forKey: Self.key)
    }

    func setFaceIDEnabled(_ enabled: Bool) {
        defaults.set(enabled, forKey: Self.key)
    }
}

protocol PrivacyClock: Sendable {
    func now() -> Duration
}

struct ContinuousPrivacyClock: PrivacyClock {
    private let clock: ContinuousClock
    private let origin: ContinuousClock.Instant

    init() {
        let clock = ContinuousClock()
        self.clock = clock
        origin = clock.now
    }

    func now() -> Duration {
        origin.duration(to: clock.now)
    }
}

enum PrivacyUnlockFailure: Sendable, Equatable {
    case cancelled
    case authenticationFailed
    case retryRequired
    case fullLoginRequired
}

enum PrivacyLockControllerError: Error, Sendable, Equatable {
    case unavailable
    case fullLoginRequired
}

@MainActor
@Observable
final class PrivacyLockController: AccessTokenRefreshing {
    private static let unlockReason = "Unlock your Fynla session"
    private static let relockThreshold = Duration.seconds(60)

    private(set) var isPrivacyCovered = false
    private(set) var shouldOfferFaceID = false
    private(set) var canUnlockWithFaceID = false
    private(set) var isFaceIDChoiceInProgress = false
    private(set) var isUnlocking = false
    private(set) var lastUnlockFailure: PrivacyUnlockFailure?

    var canSignInAnotherWay: Bool {
        appSession.state == .authenticatedLocked
    }

    private let appSession: AppSession
    private let authenticationCoordinator: AuthenticationCoordinator
    private let biometricClient: any BiometricClient
    private let keychainClient: any KeychainClient
    private let nativeSessionClient: any NativeSessionClient
    private let currentUserClient: any CurrentUserClient
    private let preference: any FaceIDPreference
    private let clock: any PrivacyClock
    private let deviceLabel: String
    private var backgroundedAt: Duration?
    private var accessTokenRefreshTask: Task<String?, Error>?
    private var accessTokenRefreshID = 0
    private var activeAccessTokenRefreshID: Int?

    init(
        appSession: AppSession,
        authenticationCoordinator: AuthenticationCoordinator,
        biometricClient: any BiometricClient,
        keychainClient: any KeychainClient,
        nativeSessionClient: any NativeSessionClient,
        currentUserClient: any CurrentUserClient,
        preference: any FaceIDPreference,
        clock: any PrivacyClock,
        deviceLabel: String
    ) {
        self.appSession = appSession
        self.authenticationCoordinator = authenticationCoordinator
        self.biometricClient = biometricClient
        self.keychainClient = keychainClient
        self.nativeSessionClient = nativeSessionClient
        self.currentUserClient = currentUserClient
        self.preference = preference
        self.clock = clock
        self.deviceLabel = deviceLabel
    }

    func completeLaunch() async {
        let enabled = await preference.isFaceIDEnabled()
        let type = await biometricClient.biometryType()
        canUnlockWithFaceID = enabled
            && (type == .faceID || type == .faceIDUnavailable)
        appSession.completeLaunch(hasAuthenticatedSession: enabled)
    }

    func refreshFaceIDOffer() async {
        guard appSession.state == .authenticatedUnlocked,
              let snapshot = authenticationCoordinator.activeNativeSessionSnapshot()
        else {
            shouldOfferFaceID = false
            return
        }
        let enabled = await preference.isFaceIDEnabled()
        guard !enabled,
              authenticationCoordinator.isCurrentNativeSession(snapshot)
        else {
            shouldOfferFaceID = false
            return
        }
        let type = await biometricClient.biometryType()
        guard authenticationCoordinator.isCurrentNativeSession(snapshot),
              appSession.state == .authenticatedUnlocked
        else {
            shouldOfferFaceID = false
            return
        }
        shouldOfferFaceID = type == .faceID
    }

    func enableFaceID() async throws {
        guard shouldOfferFaceID, !isFaceIDChoiceInProgress
        else {
            throw PrivacyLockControllerError.unavailable
        }
        isFaceIDChoiceInProgress = true
        defer { isFaceIDChoiceInProgress = false }

        if let accessTokenRefreshTask {
            _ = try? await accessTokenRefreshTask.value
        }
        guard await biometricClient.biometryType() == .faceID,
              let snapshot = authenticationCoordinator.activeNativeSessionSnapshot()
        else {
            throw PrivacyLockControllerError.unavailable
        }

        try await keychainClient.save(snapshot.refreshCredential)
        guard authenticationCoordinator.protectRefreshCredential(replacing: snapshot)
                || authenticationCoordinator.protectLockedSession(replacing: snapshot)
        else {
            throw PrivacyLockControllerError.fullLoginRequired
        }
        await preference.setFaceIDEnabled(true)
        canUnlockWithFaceID = true
        shouldOfferFaceID = false
    }

    func declineFaceID() async {
        guard shouldOfferFaceID, !isFaceIDChoiceInProgress else { return }
        isFaceIDChoiceInProgress = true
        defer { isFaceIDChoiceInProgress = false }
        if let accessTokenRefreshTask {
            _ = try? await accessTokenRefreshTask.value
        }
        authenticationCoordinator.declineBiometricPersistence()
        await preference.setFaceIDEnabled(false)
        canUnlockWithFaceID = false
        shouldOfferFaceID = false
    }

    func disableFaceID() async throws {
        guard canUnlockWithFaceID, !isFaceIDChoiceInProgress else {
            throw PrivacyLockControllerError.unavailable
        }
        isFaceIDChoiceInProgress = true
        defer { isFaceIDChoiceInProgress = false }

        if let accessTokenRefreshTask {
            _ = try? await accessTokenRefreshTask.value
        }

        do {
            try await keychainClient.delete()
        } catch KeychainError.itemNotFound {
            // The local outcome is already the requested disabled state.
        }

        authenticationCoordinator.declineBiometricPersistence()
        await preference.setFaceIDEnabled(false)
        canUnlockWithFaceID = false
        shouldOfferFaceID = false
        lastUnlockFailure = nil
    }

    func unlock() async {
        guard appSession.state == .authenticatedLocked,
              canUnlockWithFaceID,
              !isUnlocking
        else {
            return
        }

        isUnlocking = true
        lastUnlockFailure = nil
        var didRotate = false
        var didPersistRotatedCredential = false
        defer { isUnlocking = false }

        do {
            if let accessTokenRefreshTask {
                _ = try? await accessTokenRefreshTask.value
            }
            guard appSession.state == .authenticatedLocked,
                  canUnlockWithFaceID,
                  await preference.isFaceIDEnabled()
            else {
                return
            }
            let authorization = try await biometricClient.authenticate(
                reason: Self.unlockReason
            )
            let protectedCredential = try await keychainClient.read(
                authentication: authorization
            )
            let credentials = try await nativeSessionClient.rotate(
                refreshCredential: protectedCredential,
                deviceLabel: deviceLabel
            )
            didRotate = true
            try await keychainClient.save(credentials.refreshCredential)
            didPersistRotatedCredential = true
            let user = try await currentUserClient.currentUser(
                accessToken: credentials.accessToken
            )
            guard authenticationCoordinator.resumeProtectedSession(
                credentials: credentials,
                user: user
            ) else {
                await invalidateProtectedSession()
                return
            }
        } catch let error as BiometricError {
            switch error {
            case .cancelled:
                lastUnlockFailure = .cancelled
            case .authenticationFailed:
                lastUnlockFailure = .authenticationFailed
            case .lockout, .unavailable:
                await invalidateProtectedSession()
            }
        } catch let error as KeychainError {
            if didRotate {
                await invalidateProtectedSession()
                return
            }
            switch error {
            case .interactionCancelled:
                lastUnlockFailure = .cancelled
            case .authenticationFailed:
                lastUnlockFailure = .authenticationFailed
            case .itemNotFound, .invalidStoredCredential, .unexpectedStatus:
                await invalidateProtectedSession()
            }
        } catch {
            if (didRotate && !didPersistRotatedCredential)
                || Self.requiresFullLogin(error)
            {
                await invalidateProtectedSession()
            } else {
                lastUnlockFailure = .retryRequired
            }
        }
    }

    func refreshAccessToken() async throws -> String? {
        guard !isFaceIDChoiceInProgress else { return nil }
        if let accessTokenRefreshTask {
            return try await accessTokenRefreshTask.value
        }
        guard let snapshot = authenticationCoordinator.activeNativeSessionSnapshot() else {
            return nil
        }

        accessTokenRefreshID += 1
        let refreshID = accessTokenRefreshID
        let task = Task { @MainActor [weak self] () throws -> String? in
            guard let self else { return nil }
            return try await performAccessTokenRefresh(snapshot: snapshot)
        }
        accessTokenRefreshTask = task
        activeAccessTokenRefreshID = refreshID
        defer {
            if activeAccessTokenRefreshID == refreshID {
                accessTokenRefreshTask = nil
                activeAccessTokenRefreshID = nil
            }
        }
        return try await task.value
    }

    private func performAccessTokenRefresh(
        snapshot: AuthenticationCoordinator.NativeSessionSnapshot
    ) async throws -> String? {
        do {
            let credentials = try await nativeSessionClient.rotate(
                refreshCredential: snapshot.refreshCredential,
                deviceLabel: deviceLabel
            )

            if authenticationCoordinator.isCurrentNativeSession(snapshot) {
                if snapshot.persistence == .protectedKeychain {
                    try await keychainClient.save(credentials.refreshCredential)
                }
                if authenticationCoordinator.replaceNativeCredentials(
                    credentials,
                    replacing: snapshot
                ) {
                    return credentials.accessToken
                }
            }

            if authenticationCoordinator.canPersistRotationAfterLock(snapshot) {
                try await keychainClient.save(credentials.refreshCredential)
                guard authenticationCoordinator.finishProtectedRotationAfterLock(
                    replacing: snapshot
                ) else {
                    try? await nativeSessionClient.revoke(
                        accessToken: credentials.accessToken
                    )
                    throw CancellationError()
                }
                throw CancellationError()
            }

            try? await nativeSessionClient.revoke(accessToken: credentials.accessToken)
            throw CancellationError()
        } catch let error as PrivacyLockControllerError {
            throw error
        } catch is CancellationError {
            throw CancellationError()
        } catch {
            guard authenticationCoordinator.invalidateNativeSession(ifCurrent: snapshot) else {
                throw CancellationError()
            }
            await clearInvalidatedProtectedCredential()
            throw PrivacyLockControllerError.fullLoginRequired
        }
    }

    func didEnterBackground() {
        isPrivacyCovered = true
        backgroundedAt = clock.now()
    }

    func willBecomeInactive() {
        isPrivacyCovered = true
    }

    func didBecomeActive() {
        defer {
            backgroundedAt = nil
            isPrivacyCovered = false
        }
        guard let backgroundedAt else { return }
        let elapsed = clock.now() - backgroundedAt
        if elapsed >= Self.relockThreshold {
            lock()
        }
    }

    func lock() {
        guard authenticationCoordinator.lockAndClearCredentials() else { return }
        shouldOfferFaceID = false
        lastUnlockFailure = nil
    }

    func signOut() async {
        isFaceIDChoiceInProgress = true
        let refreshTask = accessTokenRefreshTask
        let accessToken = authenticationCoordinator.takeAccessTokenAndSignOut()
        canUnlockWithFaceID = false
        clearPresentationState()
        try? await keychainClient.delete()
        await preference.setFaceIDEnabled(false)
        if let accessToken {
            try? await nativeSessionClient.revoke(accessToken: accessToken)
        }
        if let refreshTask {
            _ = try? await refreshTask.value
            try? await keychainClient.delete()
            await preference.setFaceIDEnabled(false)
        }
        isFaceIDChoiceInProgress = false
    }

    func signInAnotherWay() async {
        isFaceIDChoiceInProgress = true
        let refreshTask = accessTokenRefreshTask
        _ = authenticationCoordinator.takeAccessTokenAndSignOut()
        canUnlockWithFaceID = false
        clearPresentationState()
        try? await keychainClient.delete()
        await preference.setFaceIDEnabled(false)
        if let refreshTask {
            _ = try? await refreshTask.value
            try? await keychainClient.delete()
            await preference.setFaceIDEnabled(false)
        }
        isFaceIDChoiceInProgress = false
    }

    private func invalidateProtectedSession() async {
        authenticationCoordinator.requireFullLogin()
        await clearInvalidatedProtectedCredential()
    }

    private func clearInvalidatedProtectedCredential() async {
        try? await keychainClient.delete()
        await preference.setFaceIDEnabled(false)
        canUnlockWithFaceID = false
        shouldOfferFaceID = false
        lastUnlockFailure = .fullLoginRequired
    }

    private func clearPresentationState() {
        shouldOfferFaceID = false
        lastUnlockFailure = nil
        isPrivacyCovered = false
        backgroundedAt = nil
    }

    private static func requiresFullLogin(_ error: any Error) -> Bool {
        guard let authError = error as? AuthError else { return false }
        return switch authError {
        case .unauthenticated, .notFound:
            true
        case let .requestRejected(_, status):
            status == 401 || status == 403 || status == 404
        case .decoding,
             .locked,
             .validation,
             .registrationUnavailable,
             .resendExhausted,
             .rateLimited,
             .offline,
             .transport,
             .server:
            false
        }
    }
}
