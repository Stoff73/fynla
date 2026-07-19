import Foundation
import Testing
@testable import Fynla

@Suite("Face ID privacy lock")
struct PrivacyLockControllerTests {
    @Test @MainActor
    func faceIDIsOfferedOnlyAfterCompleteAuthenticationOnAFaceIDDevice() async throws {
        let faceHarness = try await makeAuthenticatedHarness(biometry: .faceID)
        await faceHarness.controller.refreshFaceIDOffer()
        #expect(faceHarness.controller.shouldOfferFaceID)

        let touchHarness = try await makeAuthenticatedHarness(biometry: .touchID)
        await touchHarness.controller.refreshFaceIDOffer()
        #expect(!touchHarness.controller.shouldOfferFaceID)

        let signedOut = makeColdHarness(faceIDEnabled: false, biometry: .faceID)
        await signedOut.controller.completeLaunch()
        await signedOut.controller.refreshFaceIDOffer()
        #expect(!signedOut.controller.shouldOfferFaceID)
    }

    @Test @MainActor
    func optingInPersistsOnlyTheRefreshCredentialAndBooleanPreference() async throws {
        let harness = try await makeAuthenticatedHarness(biometry: .faceID)
        await harness.controller.refreshFaceIDOffer()

        try await harness.controller.enableFaceID()

        #expect(await harness.preference.isFaceIDEnabled())
        #expect(await harness.keychain.savedCredentials() == [credentials(1).refreshCredential])
        #expect(harness.coordinator.refreshPersistence == .protectedKeychain)
        #expect(!harness.controller.shouldOfferFaceID)
    }

    @Test @MainActor
    func coldLaunchRemainsLockedUntilExplicitFaceIDUnlockRotatesAndFetchesUser() async {
        let harness = makeColdHarness(faceIDEnabled: true, biometry: .faceID)
        await harness.controller.completeLaunch()

        #expect(harness.session.state == .authenticatedLocked)
        #expect(await harness.events.values().isEmpty)

        await harness.controller.unlock()

        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.credentials == credentials(2))
        #expect(harness.coordinator.inMemoryRefreshCredential == credentials(2).refreshCredential)
        #expect(harness.coordinator.authenticatedUser?.id == 101)
        #expect(await harness.events.values() == [
            "biometric.authenticate",
            "keychain.read",
            "native.rotate:refresh-1",
            "keychain.save:refresh-2",
            "user:access-2",
        ])
    }

    @Test @MainActor
    func rotatedCredentialIsSavedBeforeUserFetchAndSurvivesTransientFailureForRetry() async {
        let harness = makeColdHarness(
            faceIDEnabled: true,
            biometry: .faceID,
            userError: AuthError.offline
        )
        await harness.controller.completeLaunch()

        await harness.controller.unlock()

        #expect(harness.session.state == .authenticatedLocked)
        #expect(harness.controller.lastUnlockFailure == .retryRequired)
        #expect(await harness.keychain.savedCredentials() == [credentials(2).refreshCredential])
        #expect(await harness.keychain.deleteCount() == 0)
        #expect(await harness.preference.isFaceIDEnabled())
        #expect(await harness.events.values() == [
            "biometric.authenticate",
            "keychain.read",
            "native.rotate:refresh-1",
            "keychain.save:refresh-2",
            "user:access-2",
        ])

        await harness.sessionClient.setUserError(nil)
        await harness.events.clear()
        await harness.controller.unlock()

        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.inMemoryRefreshCredential == credentials(3).refreshCredential)
        #expect(await harness.events.values() == [
            "biometric.authenticate",
            "keychain.read",
            "native.rotate:refresh-2",
            "keychain.save:refresh-3",
            "user:access-3",
        ])
    }

    @Test @MainActor
    func unlockedRefreshRotatesFromMemoryAndReplacesKeychainWithoutAnotherPrompt() async {
        let harness = makeColdHarness(faceIDEnabled: true, biometry: .faceID)
        await harness.controller.completeLaunch()
        await harness.controller.unlock()
        await harness.events.clear()

        let token = try? await harness.controller.refreshAccessToken()

        #expect(token == "access-3")
        #expect(harness.coordinator.inMemoryRefreshCredential == credentials(3).refreshCredential)
        #expect(await harness.events.values() == [
            "native.rotate:refresh-2",
            "keychain.save:refresh-3",
        ])
    }

    @Test @MainActor
    func serverRevocationOnNextRefreshSignsOutAndClearsEveryLocalCredential() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            rotationError: AuthError.unauthenticated(message: "Native session invalid.")
        )
        await harness.controller.refreshFaceIDOffer()
        try await harness.controller.enableFaceID()
        await harness.events.clear()

        await #expect(throws: PrivacyLockControllerError.fullLoginRequired) {
            _ = try await harness.controller.refreshAccessToken()
        }

        #expect(harness.session.state == .signedOut)
        #expect(harness.coordinator.credentials == nil)
        #expect(harness.coordinator.inMemoryRefreshCredential == nil)
        #expect(await harness.keychain.currentCredential() == nil)
        #expect(await harness.keychain.deleteCount() == 1)
        #expect(!(await harness.preference.isFaceIDEnabled()))
        #expect(await harness.events.values() == [
            "native.rotate:refresh-1",
            "keychain.delete",
        ])
    }

    @Test @MainActor
    func concurrentAccessTokenRefreshesShareOneRotationOfTheOneTimeCredential() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            rotationDelay: .milliseconds(50)
        )
        await harness.controller.refreshFaceIDOffer()
        try await harness.controller.enableFaceID()
        await harness.events.clear()

        async let firstRefresh = harness.controller.refreshAccessToken()
        async let secondRefresh = harness.controller.refreshAccessToken()
        let (firstToken, secondToken) = try await (firstRefresh, secondRefresh)

        #expect(firstToken == "access-2")
        #expect(secondToken == "access-2")
        #expect(await harness.events.values() == [
            "native.rotate:refresh-1",
            "keychain.save:refresh-2",
        ])
    }

    @Test @MainActor
    func delayedRefreshCannotInstallThePreviousAccountCredentialsAfterSignOutAndNewLogin() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            rotationDelay: .milliseconds(100),
            rotationIgnoresCancellation: true
        )
        await harness.controller.refreshFaceIDOffer()
        try await harness.controller.enableFaceID()
        await harness.events.clear()

        let staleRefresh = Task { try? await harness.controller.refreshAccessToken() }
        await waitForEvent("native.rotate:refresh-1", in: harness.events)
        let signOut = Task { await harness.controller.signOut() }
        await waitForSessionState(.signedOut, in: harness.session)
        await waitForEvent("keychain.delete", in: harness.events)
        #expect(!(await harness.preference.isFaceIDEnabled()))
        try await harness.coordinator.login(
            email: "second@example.test",
            password: "Example1!",
            deviceLabel: "Fynla iPhone"
        )
        await signOut.value
        _ = await staleRefresh.value

        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.credentials == credentials(1))
        #expect(harness.coordinator.authenticatedUser?.email == "second@example.test")
        let events = await harness.events.values()
        #expect(events.contains("native.revoke:access-1"))
        #expect(events.contains("native.revoke:access-2"))
    }

    @Test @MainActor
    func manualLockFencesAnInFlightRefreshAndPreservesItsRotatedProtectedCredential() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            rotationDelay: .milliseconds(100)
        )
        await harness.controller.refreshFaceIDOffer()
        try await harness.controller.enableFaceID()
        await harness.events.clear()

        let refresh = Task { try? await harness.controller.refreshAccessToken() }
        await waitForEvent("native.rotate:refresh-1", in: harness.events)
        harness.controller.lock()
        _ = await refresh.value

        #expect(harness.session.state == .authenticatedLocked)
        #expect(harness.coordinator.credentials == nil)
        #expect(await harness.keychain.currentCredential() == credentials(2).refreshCredential)

        await harness.controller.unlock()

        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.credentials == credentials(3))
    }

    @Test @MainActor
    func sixtySecondRelockFencesAnInFlightRefreshWithoutRevokingTheProtectedSession() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            rotationDelay: .milliseconds(100)
        )
        await harness.controller.refreshFaceIDOffer()
        try await harness.controller.enableFaceID()
        await harness.events.clear()

        let refresh = Task { try? await harness.controller.refreshAccessToken() }
        await waitForEvent("native.rotate:refresh-1", in: harness.events)
        harness.controller.didEnterBackground()
        harness.clock.advance(by: .seconds(60))
        harness.controller.didBecomeActive()
        _ = await refresh.value

        #expect(harness.session.state == .authenticatedLocked)
        #expect(await harness.preference.isFaceIDEnabled())
        #expect(await harness.keychain.currentCredential() == credentials(2).refreshCredential)
    }

    @Test @MainActor
    func staleFaceIDCapabilityResultCannotOfferOptInAfterSignOut() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            biometryDelay: .milliseconds(100)
        )

        let offer = Task { await harness.controller.refreshFaceIDOffer() }
        await waitForEvent("biometric.type", in: harness.events)
        await harness.controller.signOut()
        await offer.value

        #expect(harness.session.state == .signedOut)
        #expect(!harness.controller.shouldOfferFaceID)
    }

    @Test @MainActor
    func decliningWhileRefreshIsInFlightWaitsForTheRotatedCredential() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            rotationDelay: .milliseconds(100)
        )
        await harness.controller.refreshFaceIDOffer()
        await harness.events.clear()

        let refresh = Task { try? await harness.controller.refreshAccessToken() }
        await waitForEvent("native.rotate:refresh-1", in: harness.events)
        let decline = Task { await harness.controller.declineFaceID() }
        _ = await refresh.value
        await decline.value

        #expect(harness.coordinator.credentials == credentials(2))
        #expect(harness.coordinator.refreshPersistence == .memoryOnly)
        #expect(!(await harness.preference.isFaceIDEnabled()))
        #expect(await harness.keychain.currentCredential() == nil)
    }

    @Test @MainActor
    func optInChoiceCannotBeReversedDuringItsKeychainMutation() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            keychainSaveDelay: .milliseconds(100)
        )
        await harness.controller.refreshFaceIDOffer()

        let enable = Task { try? await harness.controller.enableFaceID() }
        await waitForEvent("keychain.save:refresh-1", in: harness.events)
        #expect(harness.controller.isFaceIDChoiceInProgress)
        await harness.controller.declineFaceID()
        _ = await enable.value

        #expect(await harness.preference.isFaceIDEnabled())
        #expect(harness.coordinator.refreshPersistence == .protectedKeychain)
        #expect(await harness.keychain.currentCredential() == credentials(1).refreshCredential)
    }

    @Test @MainActor
    func lockedFaceIDActionIsAvailableOnlyForAnEnabledFaceIDDevice() async {
        let enabledFaceID = makeColdHarness(faceIDEnabled: true, biometry: .faceID)
        await enabledFaceID.controller.completeLaunch()
        #expect(enabledFaceID.controller.canUnlockWithFaceID)

        let disabled = makeColdHarness(faceIDEnabled: false, biometry: .faceID)
        await disabled.controller.completeLaunch()
        #expect(!disabled.controller.canUnlockWithFaceID)

        let touchID = makeColdHarness(faceIDEnabled: true, biometry: .touchID)
        await touchID.controller.completeLaunch()
        #expect(!touchID.controller.canUnlockWithFaceID)
    }

    @Test @MainActor
    func foregroundBeforeSixtySecondsRemovesOnlyThePrivacyCover() async throws {
        let harness = try await makeAuthenticatedHarness(biometry: .faceID)
        let before = harness.coordinator.credentials

        harness.controller.didEnterBackground()
        #expect(harness.controller.isPrivacyCovered)
        harness.clock.advance(by: .seconds(59))
        harness.controller.didBecomeActive()

        #expect(!harness.controller.isPrivacyCovered)
        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.credentials == before)
    }

    @Test @MainActor
    func inactiveCoversImmediatelyWithoutStartingTheBackgroundRelockTimer() async throws {
        let harness = try await makeAuthenticatedHarness(biometry: .faceID)

        harness.controller.willBecomeInactive()
        #expect(harness.controller.isPrivacyCovered)
        harness.clock.advance(by: .seconds(120))
        harness.controller.didBecomeActive()

        #expect(!harness.controller.isPrivacyCovered)
        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.credentials == credentials(1))
    }

    @Test @MainActor
    func foregroundAtSixtySecondsLocksAndClearsAccessAndRefreshMemory() async throws {
        let harness = try await makeAuthenticatedHarness(biometry: .faceID)

        harness.controller.didEnterBackground()
        harness.clock.advance(by: .seconds(60))
        harness.controller.didBecomeActive()

        #expect(!harness.controller.isPrivacyCovered)
        #expect(harness.session.state == .authenticatedLocked)
        #expect(harness.coordinator.credentials == nil)
        #expect(harness.coordinator.inMemoryRefreshCredential == nil)
    }

    @Test @MainActor
    func cancellationStaysLockedAndOffersFullSignInWithoutDeletingCredential() async {
        let harness = makeColdHarness(
            faceIDEnabled: true,
            biometry: .faceID,
            biometricError: .cancelled
        )
        await harness.controller.completeLaunch()

        await harness.controller.unlock()

        #expect(harness.session.state == .authenticatedLocked)
        #expect(harness.controller.lastUnlockFailure == .cancelled)
        #expect(harness.controller.canSignInAnotherWay)
        #expect(await harness.keychain.deleteCount() == 0)
        #expect(await harness.preference.isFaceIDEnabled())
    }

    @Test @MainActor
    func failedFaceIDStaysLockedAndCanRetry() async {
        let harness = makeColdHarness(
            faceIDEnabled: true,
            biometry: .faceID,
            biometricError: .authenticationFailed
        )
        await harness.controller.completeLaunch()

        await harness.controller.unlock()

        #expect(harness.session.state == .authenticatedLocked)
        #expect(harness.controller.lastUnlockFailure == .authenticationFailed)
        #expect(await harness.keychain.deleteCount() == 0)
    }

    @Test @MainActor
    func biometricLockoutDeletesLocalCredentialAndRequiresFullLogin() async {
        let harness = makeColdHarness(
            faceIDEnabled: true,
            biometry: .faceIDUnavailable,
            biometricError: .lockout
        )
        await harness.controller.completeLaunch()

        await harness.controller.unlock()

        #expect(harness.session.state == .signedOut)
        #expect(harness.coordinator.state == .fullLoginRequired)
        #expect(harness.controller.lastUnlockFailure == .fullLoginRequired)
        #expect(await harness.keychain.deleteCount() == 1)
        #expect(!(await harness.preference.isFaceIDEnabled()))
    }

    @Test @MainActor
    func unavailableProtectedItemIncludesSystemReportedEnrolmentInvalidation() async {
        let harness = makeColdHarness(
            faceIDEnabled: true,
            biometry: .faceID,
            keychainReadError: KeychainError.itemNotFound
        )
        await harness.controller.completeLaunch()

        await harness.controller.unlock()

        #expect(harness.session.state == .signedOut)
        #expect(harness.coordinator.state == .fullLoginRequired)
        #expect(await harness.keychain.deleteCount() == 1)
        #expect(!(await harness.preference.isFaceIDEnabled()))
    }

    @Test @MainActor
    func localLockNeverCallsTheServerAndRetainsProtectedCredential() async throws {
        let harness = try await makeAuthenticatedHarness(biometry: .faceID)
        await harness.controller.refreshFaceIDOffer()
        try await harness.controller.enableFaceID()
        await harness.events.clear()

        harness.controller.lock()

        #expect(harness.session.state == .authenticatedLocked)
        #expect(harness.coordinator.credentials == nil)
        #expect(await harness.events.values().isEmpty)
        #expect(await harness.keychain.deleteCount() == 0)
        #expect(await harness.preference.isFaceIDEnabled())
    }

    @Test @MainActor
    func disablingFaceIDDeletesOnlyLocalPersistenceAndKeepsTheServerSession() async throws {
        let harness = try await makeAuthenticatedHarness(biometry: .faceID)
        await harness.controller.refreshFaceIDOffer()
        try await harness.controller.enableFaceID()
        await harness.events.clear()

        try await harness.controller.disableFaceID()

        #expect(await harness.events.values() == ["keychain.delete"])
        #expect(harness.session.state == .authenticatedUnlocked)
        #expect(harness.coordinator.credentials == credentials(1))
        #expect(harness.coordinator.refreshPersistence == .memoryOnly)
        #expect(!(await harness.preference.isFaceIDEnabled()))
        #expect(!harness.controller.canUnlockWithFaceID)
    }

    @Test @MainActor
    func signOutIsBestEffortAndAlwaysClearsServerLocalAndFeatureState() async throws {
        let harness = try await makeAuthenticatedHarness(
            biometry: .faceID,
            revokeError: Task8TestError.expected
        )
        await harness.controller.refreshFaceIDOffer()
        try await harness.controller.enableFaceID()
        await harness.events.clear()

        await harness.controller.signOut()

        #expect(await harness.events.values() == [
            "keychain.delete",
            "native.revoke:access-1",
        ])
        #expect(harness.session.state == .signedOut)
        #expect(harness.coordinator.credentials == nil)
        #expect(harness.coordinator.authenticatedUser == nil)
        #expect(!(await harness.preference.isFaceIDEnabled()))
        #expect(!harness.controller.shouldOfferFaceID)
    }

    @Test @MainActor
    func signingInAnotherWayClearsUnavailableLocalUnlockStateWithoutServerLogout() async {
        let harness = makeColdHarness(faceIDEnabled: true, biometry: .faceID)
        await harness.controller.completeLaunch()

        await harness.controller.signInAnotherWay()

        #expect(harness.session.state == .signedOut)
        #expect(await harness.events.values() == ["keychain.delete"])
        #expect(!(await harness.preference.isFaceIDEnabled()))
    }

    @Test
    func taskEightViewsExposeStableAccessibleControlsAndOpaquePrivacyCover() throws {
        let testDirectory = URL(fileURLWithPath: #filePath).deletingLastPathComponent()
        let repositoryRoot = testDirectory.deletingLastPathComponent()
        let repositoryFeatureRoot = repositoryRoot
            .appending(path: "Fynla/Features/Authentication")
        let hostRoot = testDirectory
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .appending(path: "Sources/Fynla")
        let featureRoot = FileManager.default.fileExists(atPath: repositoryFeatureRoot.path)
            ? repositoryFeatureRoot
            : hostRoot

        let optIn = try String(
            contentsOf: featureRoot.appending(path: "FaceIDOptInView.swift"),
            encoding: .utf8
        )
        let locked = try String(
            contentsOf: featureRoot.appending(path: "LockedView.swift"),
            encoding: .utf8
        )
        let appRoot = try String(
            contentsOf: (FileManager.default.fileExists(atPath: repositoryFeatureRoot.path)
                ? repositoryRoot.appending(path: "Fynla/App/AppRootView.swift")
                : hostRoot.appending(path: "AppRootView.swift")),
            encoding: .utf8
        )

        #expect(optIn.contains("face-id.opt-in.enable"))
        #expect(optIn.contains("face-id.opt-in.not-now"))
        #expect(locked.contains("app.locked.unlock"))
        #expect(locked.contains("app.locked.sign-in-another-way"))
        #expect(appRoot.contains("privacy-lock.cover"))
        #expect(appRoot.contains("FynlaColor.Token.horizon500"))
        #expect(appRoot.contains("app.unlocked.lock"))
        #expect(appRoot.contains("app.unlocked.sign-out"))
        #expect(appRoot.contains("privacyLockController.lock()"))
        #expect(appRoot.contains("await privacyLockController.signOut()"))
        #expect(appRoot.contains("accessibilityElement(children: .contain)"))
    }

    @MainActor
    private func makeAuthenticatedHarness(
        biometry: BiometryType,
        revokeError: (any Error)? = nil,
        rotationError: (any Error)? = nil,
        rotationDelay: Duration? = nil,
        biometryDelay: Duration? = nil,
        keychainSaveDelay: Duration? = nil,
        rotationIgnoresCancellation: Bool = false
    ) async throws -> Task8Harness {
        let harness = makeHarness(
            sessionState: .signedOut,
            faceIDEnabled: false,
            biometry: biometry,
            initialKeychainCredential: nil,
            revokeError: revokeError,
            rotationError: rotationError,
            rotationDelay: rotationDelay,
            biometryDelay: biometryDelay,
            keychainSaveDelay: keychainSaveDelay,
            rotationIgnoresCancellation: rotationIgnoresCancellation
        )
        try await harness.coordinator.login(
            email: "example@example.test",
            password: "Example1!",
            deviceLabel: "Fynla iPhone"
        )
        await harness.events.clear()
        return harness
    }

    @MainActor
    private func makeColdHarness(
        faceIDEnabled: Bool,
        biometry: BiometryType,
        biometricError: BiometricError? = nil,
        keychainReadError: (any Error)? = nil,
        userError: (any Error)? = nil
    ) -> Task8Harness {
        makeHarness(
            sessionState: .launching,
            faceIDEnabled: faceIDEnabled,
            biometry: biometry,
            biometricError: biometricError,
            initialKeychainCredential: credentials(1).refreshCredential,
            keychainReadError: keychainReadError,
            userError: userError
        )
    }

    @MainActor
    private func makeHarness(
        sessionState: AppSession.State,
        faceIDEnabled: Bool,
        biometry: BiometryType,
        biometricError: BiometricError? = nil,
        initialKeychainCredential: NativeRefreshCredential?,
        keychainReadError: (any Error)? = nil,
        revokeError: (any Error)? = nil,
        rotationError: (any Error)? = nil,
        userError: (any Error)? = nil,
        rotationDelay: Duration? = nil,
        biometryDelay: Duration? = nil,
        keychainSaveDelay: Duration? = nil,
        rotationIgnoresCancellation: Bool = false
    ) -> Task8Harness {
        let events = Task8EventLog()
        let client = Task8SessionClient(
            events: events,
            revokeError: revokeError,
            rotationError: rotationError,
            userError: userError,
            rotationDelay: rotationDelay,
            rotationIgnoresCancellation: rotationIgnoresCancellation
        )
        let session = AppSession(state: sessionState)
        let coordinator = AuthenticationCoordinator(
            appSession: session,
            authClient: client,
            currentUserClient: client
        )
        let biometric = Task8BiometricClient(
            type: biometry,
            error: biometricError,
            typeDelay: biometryDelay,
            events: events
        )
        let keychain = Task8KeychainClient(
            credential: initialKeychainCredential,
            readError: keychainReadError,
            saveDelay: keychainSaveDelay,
            events: events
        )
        let preference = Task8FaceIDPreference(enabled: faceIDEnabled)
        let clock = Task8PrivacyClock()
        let controller = PrivacyLockController(
            appSession: session,
            authenticationCoordinator: coordinator,
            biometricClient: biometric,
            keychainClient: keychain,
            nativeSessionClient: client,
            currentUserClient: client,
            preference: preference,
            clock: clock,
            deviceLabel: "Fynla iPhone"
        )
        return Task8Harness(
            session: session,
            coordinator: coordinator,
            controller: controller,
            keychain: keychain,
            preference: preference,
            clock: clock,
            events: events,
            sessionClient: client
        )
    }

    private func waitForEvent(
        _ expected: String,
        in events: Task8EventLog
    ) async {
        for _ in 0..<100 {
            if await events.values().contains(expected) { return }
            try? await Task.sleep(for: .milliseconds(1))
        }
        Issue.record("Timed out waiting for \(expected)")
    }

    @MainActor
    private func waitForSessionState(
        _ expected: AppSession.State,
        in session: AppSession
    ) async {
        for _ in 0..<100 {
            if session.state == expected { return }
            try? await Task.sleep(for: .milliseconds(1))
        }
        Issue.record("Timed out waiting for session state \(expected)")
    }

    private func credentials(_ generation: Int) -> NativeCredentials {
        NativeCredentials(
            accessToken: "access-\(generation)",
            accessExpiresAt: "2026-07-17T12:\(generation)0:00Z",
            refreshToken: "refresh-\(generation)",
            refreshExpiresAt: "2026-08-16T12:00:00Z",
            absoluteExpiresAt: "2026-10-15T12:00:00Z",
            sessionID: "11111111-2222-3333-4444-555555555555"
        )
    }
}

@MainActor
private struct Task8Harness {
    let session: AppSession
    let coordinator: AuthenticationCoordinator
    let controller: PrivacyLockController
    let keychain: Task8KeychainClient
    let preference: Task8FaceIDPreference
    let clock: Task8PrivacyClock
    let events: Task8EventLog
    let sessionClient: Task8SessionClient
}

private enum Task8TestError: Error {
    case expected
}

private actor Task8EventLog {
    private var events: [String] = []

    func append(_ event: String) {
        events.append(event)
    }

    func values() -> [String] {
        events
    }

    func clear() {
        events.removeAll(keepingCapacity: false)
    }
}

private actor Task8BiometricClient: BiometricClient {
    private let type: BiometryType
    private let error: BiometricError?
    private let typeDelay: Duration?
    private let events: Task8EventLog

    init(
        type: BiometryType,
        error: BiometricError?,
        typeDelay: Duration?,
        events: Task8EventLog
    ) {
        self.type = type
        self.error = error
        self.typeDelay = typeDelay
        self.events = events
    }

    func biometryType() async -> BiometryType {
        if let typeDelay {
            await events.append("biometric.type")
            try? await Task.sleep(for: typeDelay)
        }
        return type
    }

    func authenticate(reason: String) async throws -> LocalAuthenticationAuthorization {
        await events.append("biometric.authenticate")
        if let error { throw error }
        return .testing
    }
}

private actor Task8KeychainClient: KeychainClient {
    private var credential: NativeRefreshCredential?
    private let readError: (any Error)?
    private let saveDelay: Duration?
    private let events: Task8EventLog
    private var saved: [NativeRefreshCredential] = []
    private var deletes = 0

    init(
        credential: NativeRefreshCredential?,
        readError: (any Error)?,
        saveDelay: Duration?,
        events: Task8EventLog
    ) {
        self.credential = credential
        self.readError = readError
        self.saveDelay = saveDelay
        self.events = events
    }

    func save(_ credential: NativeRefreshCredential) async throws {
        await events.append("keychain.save:\(credential.token)")
        if let saveDelay { try? await Task.sleep(for: saveDelay) }
        self.credential = credential
        saved.append(credential)
    }

    func read() async throws -> NativeRefreshCredential {
        await events.append("keychain.read")
        if let readError { throw readError }
        guard let credential else { throw KeychainError.itemNotFound }
        return credential
    }

    func read(
        authentication: LocalAuthenticationAuthorization
    ) async throws -> NativeRefreshCredential {
        guard authentication == .testing else {
            throw KeychainError.authenticationFailed
        }
        return try await read()
    }

    func delete() async throws {
        await events.append("keychain.delete")
        deletes += 1
        credential = nil
    }

    func savedCredentials() -> [NativeRefreshCredential] {
        saved
    }

    func deleteCount() -> Int {
        deletes
    }

    func currentCredential() -> NativeRefreshCredential? {
        credential
    }
}

private actor Task8FaceIDPreference: FaceIDPreference {
    private var enabled: Bool

    init(enabled: Bool) {
        self.enabled = enabled
    }

    func isFaceIDEnabled() -> Bool {
        enabled
    }

    func setFaceIDEnabled(_ enabled: Bool) {
        self.enabled = enabled
    }
}

private final class Task8PrivacyClock: PrivacyClock, @unchecked Sendable {
    private let lock = NSLock()
    private var instant: Duration = .zero

    func now() -> Duration {
        lock.withLock { instant }
    }

    func advance(by duration: Duration) {
        lock.withLock {
            instant += duration
        }
    }
}

private actor Task8SessionClient: AuthCompletionClient, CurrentUserClient, NativeSessionClient {
    private let events: Task8EventLog
    private let revokeError: (any Error)?
    private let rotationError: (any Error)?
    private let rotationDelay: Duration?
    private let rotationIgnoresCancellation: Bool
    private var userError: (any Error)?
    private var rotation = 1
    private var currentEmail = "example@example.test"

    init(
        events: Task8EventLog,
        revokeError: (any Error)?,
        rotationError: (any Error)?,
        userError: (any Error)?,
        rotationDelay: Duration?,
        rotationIgnoresCancellation: Bool
    ) {
        self.events = events
        self.revokeError = revokeError
        self.rotationError = rotationError
        self.userError = userError
        self.rotationDelay = rotationDelay
        self.rotationIgnoresCancellation = rotationIgnoresCancellation
    }

    func register(_ input: RegistrationInput) async throws -> RegistrationChallenge {
        throw Task8TestError.expected
    }

    func verifyRegistrationCompletion(
        _ input: RegistrationVerificationInput
    ) async throws -> BootstrapAuthentication {
        throw Task8TestError.expected
    }

    func resendRegistration(pendingID: Int) async throws -> String {
        throw Task8TestError.expected
    }

    func loginCompletion(email: String, password: String) async throws -> LoginCompletion {
        currentEmail = email
        return LoginCompletion(
            outcome: .authenticated(bootstrapAccessToken: "bootstrap"),
            mustChangePassword: false
        )
    }

    func verifyLoginCompletion(
        code: String,
        challengeToken: String
    ) async throws -> BootstrapAuthentication {
        throw Task8TestError.expected
    }

    func verifyMFACompletion(
        code: String,
        token: String
    ) async throws -> BootstrapAuthentication {
        throw Task8TestError.expected
    }

    func useRecoveryCodeCompletion(
        _ code: String,
        token: String
    ) async throws -> BootstrapAuthentication {
        throw Task8TestError.expected
    }

    func resendLogin(challengeToken: String) async throws -> LoginResendResult {
        throw Task8TestError.expected
    }

    func checkRestoration(email: String, password: String) async throws -> RestorationSession {
        throw Task8TestError.expected
    }

    func restoreAccount(
        token: String,
        mfaCode: String?
    ) async throws -> BootstrapAuthentication {
        throw Task8TestError.expected
    }

    func exchange(
        bootstrapToken: String,
        deviceLabel: String
    ) async throws -> NativeCredentials {
        Task8Credentials.make(1)
    }

    func rotate(
        refreshCredential: NativeRefreshCredential,
        deviceLabel: String
    ) async throws -> NativeCredentials {
        await events.append("native.rotate:\(refreshCredential.token)")
        if let rotationError { throw rotationError }
        if let rotationDelay {
            if rotationIgnoresCancellation {
                await Task.detached {
                    try? await Task.sleep(for: rotationDelay)
                }.value
            } else {
                try await Task.sleep(for: rotationDelay)
            }
        }
        rotation += 1
        return Task8Credentials.make(rotation)
    }

    func revoke(accessToken: String) async throws {
        await events.append("native.revoke:\(accessToken)")
        if let revokeError { throw revokeError }
    }

    func currentUser(accessToken: String) async throws -> AuthenticatedUser {
        await events.append("user:\(accessToken)")
        if let userError { throw userError }
        return AuthenticatedUser(
            id: 101,
            firstName: "Example",
            surname: "User",
            name: "Example User",
            email: currentEmail
        )
    }

    func setUserError(_ error: (any Error)?) {
        userError = error
    }
}

private enum Task8Credentials {
    static func make(_ generation: Int) -> NativeCredentials {
        NativeCredentials(
            accessToken: "access-\(generation)",
            accessExpiresAt: "2026-07-17T12:\(generation)0:00Z",
            refreshToken: "refresh-\(generation)",
            refreshExpiresAt: "2026-08-16T12:00:00Z",
            absoluteExpiresAt: "2026-10-15T12:00:00Z",
            sessionID: "11111111-2222-3333-4444-555555555555"
        )
    }
}
