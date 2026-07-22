import Foundation
import Testing
@testable import Fynla

@Suite("Native login model")
struct LoginModelTests {
    @Test @MainActor
    func loginValidationAndServerFailurePreserveOnlyTheEmail() async {
        let recorder = LoginActionRecorder(
            loginError: AuthError.unauthenticated(message: "Invalid email or password.")
        )
        let model = LoginModel(actions: recorder.actions)

        let empty = await model.submitLogin(password: "")
        #expect(!empty.succeeded)
        #expect(!empty.clearInput)
        #expect(model.fieldErrors[.email] != nil)
        #expect(model.fieldErrors[.password] != nil)
        #expect(recorder.loginCount == 0)

        model.email = "  example@example.test  "
        let failed = await model.submitLogin(password: "Example1!")
        #expect(!failed.succeeded)
        #expect(failed.clearInput)
        #expect(model.email == "  example@example.test  ")
        #expect(model.message == "Invalid email or password.")
        #expect(recorder.lastEmail == "example@example.test")
        #expect(recorder.lastPassword == "Example1!")
    }

    @Test @MainActor
    func loginAdvancesThroughEveryServerBranch() async {
        let branches: [LoginStep] = [
            .verification(maskedEmail: "e***@example.test"),
            .multiFactor(maskedEmail: "e***@example.test"),
            .restoration(RestorationChallenge(
                token: "checked-restoration",
                deletedAt: "2026-07-16T12:00:00Z",
                deletionReason: "user_requested",
                deletionSource: "settings",
                firstName: "Example",
                requiresMFA: true
            )),
            .authenticated,
        ]

        for branch in branches {
            let recorder = LoginActionRecorder(loginStep: branch)
            let model = LoginModel(actions: recorder.actions)
            model.email = "example@example.test"

            let result = await model.submitLogin(password: "Example1!")

            #expect(result.succeeded)
            #expect(result.clearInput)
            #expect(model.step == branch)
        }
    }

    @Test @MainActor
    func lockoutShowsServerWordingCountsDownAndBlocksRepeatedSubmission() async {
        let now = MutableNow(Date(timeIntervalSince1970: 1_700_000_000))
        let recorder = LoginActionRecorder(
            loginError: AuthError.locked(
                message: "Account temporarily locked. Try again in 3 minute(s).",
                remainingSeconds: 180
            )
        )
        let model = LoginModel(actions: recorder.actions, now: { now.value })
        model.email = "example@example.test"

        _ = await model.submitLogin(password: "Example1!")
        #expect(model.message == "Account temporarily locked. Try again in 3 minute(s).")
        #expect(model.lockoutRemainingSeconds == 180)
        #expect(model.isLocked)

        _ = await model.submitLogin(password: "Example1!")
        #expect(recorder.loginCount == 1)

        now.value = now.value.addingTimeInterval(179)
        model.tickLockout()
        #expect(model.lockoutRemainingSeconds == 1)
        now.value = now.value.addingTimeInterval(1)
        model.tickLockout()
        #expect(!model.isLocked)

        recorder.loginError = nil
        _ = await model.submitLogin(password: "Example1!")
        #expect(recorder.loginCount == 2)
    }

    @Test @MainActor
    func lockoutWithoutCountdownStillBlocksRepeatedSubmission() async {
        let recorder = LoginActionRecorder(
            loginError: AuthError.locked(
                message: "Too many failed attempts from this location. Please try again later.",
                remainingSeconds: nil
            )
        )
        let model = LoginModel(actions: recorder.actions)
        model.email = "example@example.test"

        _ = await model.submitLogin(password: "Example1!")

        #expect(model.isLocked)
        #expect(model.lockoutRemainingSeconds == 0)
        #expect(model.message == "Too many failed attempts from this location. Please try again later.")

        _ = await model.submitLogin(password: "Example1!")
        #expect(recorder.loginCount == 1)
    }

    @Test @MainActor
    func verificationResendMFARecoveryAndRestorationUseTransientInputs() async {
        let recorder = LoginActionRecorder(loginStep: .verification(maskedEmail: "e***@test"))
        let model = LoginModel(actions: recorder.actions)
        model.email = "example@example.test"
        _ = await model.submitLogin(password: "Example1!")

        let invalid = await model.submitVerification(code: "12a")
        #expect(!invalid.succeeded)
        #expect(invalid.clearInput)
        #expect(recorder.verifyCount == 0)

        recorder.verificationError = AuthError.validation(
            message: "Invalid or expired verification code",
            errors: [:]
        )
        let wrong = await model.submitVerification(code: "000000")
        #expect(!wrong.succeeded)
        #expect(wrong.clearInput)
        #expect(model.step == .verification(maskedEmail: "e***@test"))

        await model.resendVerification()
        #expect(model.message == "Verification code sent")
        #expect(model.canResendVerification)

        model.beginMultiFactor(maskedEmail: "e***@test")
        recorder.mfaError = AuthError.unauthenticated(
            message: "Invalid or expired verification request."
        )
        let mfa = await model.submitMFA(code: "123456")
        #expect(!mfa.succeeded)
        #expect(mfa.clearInput)
        #expect(model.step == .multiFactor(maskedEmail: "e***@test"))

        model.beginMultiFactor(maskedEmail: "e***@test")
        recorder.mfaError = nil
        let recovery = await model.submitRecovery(code: " RECOVERY-CODE ")
        #expect(recovery.succeeded)
        #expect(recovery.clearInput)
        #expect(recorder.lastRecoveryCode == "RECOVERY-CODE")

        model.beginRestoration(.example(requiresMFA: true))
        let restored = await model.submitRestoration(code: " RESTORE-CODE ")
        #expect(restored.succeeded)
        #expect(restored.clearInput)
        #expect(recorder.lastRestorationCode == "RESTORE-CODE")
    }

    @Test @MainActor
    func cancellingInvalidatesLateLoginAndClearsTheFlow() async {
        let gate = LoginModelGate()
        let recorder = LoginActionRecorder(loginGate: gate)
        let model = LoginModel(actions: recorder.actions)
        model.email = "example@example.test"

        let task = Task { @MainActor in
            await model.submitLogin(password: "Example1!")
        }
        while !model.isSubmitting { await Task.yield() }
        model.cancel()
        await gate.open()
        _ = await task.value

        #expect(model.step == .signIn)
        #expect(model.message == nil)
        #expect(!model.isSubmitting)
        #expect(recorder.cancelCount == 1)
    }

    @Test @MainActor
    func cancellingInvalidatesEveryLateContinuationBeforeItCanAuthenticate() async {
        let gate = LoginModelGate()
        let recorder = LoginActionRecorder(
            loginStep: .verification(maskedEmail: "e***@test"),
            verificationGate: gate
        )
        let model = LoginModel(actions: recorder.actions)
        model.email = "example@example.test"
        _ = await model.submitLogin(password: "Example1!")

        let task = Task { @MainActor in
            await model.submitVerification(code: "123456")
        }
        while !model.isSubmitting { await Task.yield() }
        model.cancel()
        await gate.open()
        _ = await task.value

        #expect(model.step == .signIn)
        #expect(!model.isSubmitting)
        #expect(recorder.cancelCount == 1)
    }

    @Test @MainActor
    func continuationLockoutSuppressesEveryFurtherNetworkSubmission() async {
        let now = MutableNow(Date(timeIntervalSince1970: 1_700_000_000))
        let recorder = LoginActionRecorder(
            loginStep: .verification(maskedEmail: "e***@test")
        )
        recorder.verificationError = AuthError.locked(
            message: "Account temporarily locked. Try again in 1 minute(s).",
            remainingSeconds: 60
        )
        let model = LoginModel(actions: recorder.actions, now: { now.value })
        model.email = "example@example.test"
        _ = await model.submitLogin(password: "Example1!")

        _ = await model.submitVerification(code: "123456")
        #expect(model.isLocked)
        #expect(recorder.verifyCount == 1)

        _ = await model.submitVerification(code: "123456")
        await model.resendVerification()
        #expect(recorder.verifyCount == 1)
        #expect(recorder.resendCount == 0)
    }

    @Test @MainActor
    func cancelledContinuationConvergesBackToSignIn() async {
        for error in [
            AuthenticationCoordinatorError.cancelled as any Error,
            CancellationError() as any Error,
        ] {
            let recorder = LoginActionRecorder(
                loginStep: .multiFactor(maskedEmail: "e***@test")
            )
            recorder.mfaError = error
            let model = LoginModel(actions: recorder.actions)
            model.email = "example@example.test"
            _ = await model.submitLogin(password: "Example1!")

            _ = await model.submitMFA(code: "123456")

            #expect(model.step == .signIn)
            #expect(model.message == nil)
            #expect(!model.isSubmitting)
        }
    }

    @Test @MainActor
    func wrongStepCannotInvokeAnyLoginBranchAction() async {
        let recorder = LoginActionRecorder(
            loginStep: .verification(maskedEmail: "e***@test")
        )
        let model = LoginModel(actions: recorder.actions)

        _ = await model.submitVerification(code: "123456")
        _ = await model.submitMFA(code: "123456")
        _ = await model.submitRecovery(code: "RECOVERY")
        #expect(recorder.verifyCount == 0)
        #expect(recorder.mfaCount == 0)
        #expect(recorder.recoveryCount == 0)

        model.email = "example@example.test"
        _ = await model.submitLogin(password: "Example1!")
        #expect(recorder.loginCount == 1)
        _ = await model.submitLogin(password: "Example1!")
        #expect(recorder.loginCount == 1)
    }

    @Test
    func loginSourcesAreScrollableAccessibleAndDoNotOwnSecretsInTheModel() throws {
        let root = sourceDirectory()
        let model = try source("LoginModel.swift", from: root)
        #expect(!model.contains("var password"))
        #expect(!model.contains("var code"))
        #expect(!model.contains("UserDefaults"))
        #expect(!model.contains("DiagnosticsClient"))

        let login = try source("LoginView.swift", from: root)
        let mfa = try source("MultiFactorView.swift", from: root)
        let restore = try source("RestoreAccountFlow.swift", from: root)
        for identifier in [
            "login.email", "login.password", "login.submit",
            "login.verification.code", "login.verification.submit",
            "login.verification.resend", "login.forgotPassword",
        ] {
            #expect(login.contains(identifier))
        }
        for identifier in [
            "login.mfa.code", "login.mfa.submit", "login.mfa.useRecovery",
            "login.mfa.recoveryCode", "login.mfa.recoverySubmit",
            "login.mfa.lockoutCountdown",
        ] {
            #expect(mfa.contains(identifier))
        }
        for identifier in [
            "login.restore.mfaCode", "login.restore.submit", "login.restore.cancel",
            "login.restore.lockoutCountdown",
        ] {
            #expect(restore.contains(identifier))
        }
        #expect(mfa.contains("model.tickLockout()"))
        #expect(restore.contains("model.tickLockout()"))
        #expect(login.contains("if model.lockoutRemainingSeconds > 0"))
        #expect(mfa.contains("if model.lockoutRemainingSeconds > 0"))
        #expect(restore.contains("if model.lockoutRemainingSeconds > 0"))
        #expect(mfa.contains("countdownTask?.cancel()"))
        #expect(restore.contains("countdownTask?.cancel()"))
        #expect(!restore.contains("Color.clear"))
        #expect(mfa.contains(".disabled(model.isSubmitting || model.isLocked)"))
        let passwordReset = try source("PasswordResetFlow.swift", from: root)
        #expect(passwordReset.contains(".disabled(model.isSubmitting)"))
        #expect(login.contains("ScrollView"))
        #expect(login.contains(".onDisappear(perform: clearSecrets)"))
        #expect(!login.contains(".onDisappear(perform: cancelWorkAndClearSecrets)"))
        #expect(login.components(separatedBy: "leaveLoginFlow(").count == 4)
        #expect(mfa.contains("ScrollView"))
        #expect(restore.contains("ScrollView"))
        #expect(!login.localizedCaseInsensitiveContains("face id replaces"))
        #expect(!mfa.localizedCaseInsensitiveContains("face id replaces"))
        #expect(!restore.localizedCaseInsensitiveContains("face id replaces"))

        let repositoryAppRoot = root
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .appending(path: "App/AppRootView.swift")
        let appRoot = try String(
            contentsOf: FileManager.default.fileExists(atPath: repositoryAppRoot.path)
                ? repositoryAppRoot
                : root.appending(path: "AppRootView.swift"),
            encoding: .utf8
        )
        #expect(appRoot.contains("LoginView("))
        #expect(appRoot.contains("MultiFactorView(model: loginModel)"))
        #expect(appRoot.contains("RestoreAccountFlow(model: loginModel)"))
        #expect(appRoot.contains("PasswordResetFlow("))
    }

    private func sourceDirectory() -> URL {
        let testDirectory = URL(fileURLWithPath: #filePath).deletingLastPathComponent()
        let repository = testDirectory
            .deletingLastPathComponent()
            .appending(path: "Fynla/Features/Authentication")
        if FileManager.default.fileExists(atPath: repository.path) { return repository }
        return testDirectory.deletingLastPathComponent().deletingLastPathComponent()
            .appending(path: "Sources/Fynla")
    }

    private func source(_ name: String, from root: URL) throws -> String {
        try String(contentsOf: root.appending(path: name), encoding: .utf8)
    }
}

@MainActor
private final class MutableNow {
    var value: Date
    init(_ value: Date) { self.value = value }
}

@MainActor
private final class LoginActionRecorder {
    var loginError: AuthError?
    var verificationError: AuthError?
    var mfaError: (any Error)?
    var loginCount = 0
    var verifyCount = 0
    var resendCount = 0
    var mfaCount = 0
    var recoveryCount = 0
    var cancelCount = 0
    var lastEmail: String?
    var lastPassword: String?
    var lastRecoveryCode: String?
    var lastRestorationCode: String?

    private let loginStep: LoginStep
    private let loginGate: LoginModelGate?
    private let verificationGate: LoginModelGate?

    init(
        loginStep: LoginStep = .authenticated,
        loginError: AuthError? = nil,
        loginGate: LoginModelGate? = nil,
        verificationGate: LoginModelGate? = nil
    ) {
        self.loginStep = loginStep
        self.loginError = loginError
        self.loginGate = loginGate
        self.verificationGate = verificationGate
    }

    var actions: LoginActions {
        LoginActions(
            login: { [weak self] email, password in
                guard let self else { throw CancellationError() }
                self.loginCount += 1
                self.lastEmail = email
                self.lastPassword = password
                if let loginGate = self.loginGate { await loginGate.wait() }
                if let loginError = self.loginError { throw loginError }
                return self.loginStep
            },
            verifyLogin: { [weak self] _ in
                guard let self else { throw CancellationError() }
                self.verifyCount += 1
                if let verificationGate = self.verificationGate {
                    await verificationGate.wait()
                }
                if let verificationError = self.verificationError { throw verificationError }
            },
            resendLogin: { [weak self] in
                guard let self else { throw CancellationError() }
                self.resendCount += 1
                return LoginResendResult(
                    message: "Verification code sent",
                    canResend: true,
                    remainingResends: 1
                )
            },
            verifyMFA: { [weak self] _ in
                guard let self else { throw CancellationError() }
                self.mfaCount += 1
                if let mfaError = self.mfaError { throw mfaError }
            },
            useRecoveryCode: { [weak self] code in
                guard let self else { throw CancellationError() }
                self.recoveryCount += 1
                self.lastRecoveryCode = code
            },
            restoreAccount: { [weak self] code in
                guard let self else { throw CancellationError() }
                self.lastRestorationCode = code
            },
            cancel: { [weak self] in self?.cancelCount += 1 }
        )
    }
}

private actor LoginModelGate {
    private var isOpen = false
    private var continuations: [CheckedContinuation<Void, Never>] = []

    func wait() async {
        if isOpen { return }
        await withCheckedContinuation { continuations.append($0) }
    }

    func open() {
        isOpen = true
        let waiting = continuations
        continuations.removeAll()
        for continuation in waiting { continuation.resume() }
    }
}

private extension RestorationChallenge {
    static func example(requiresMFA: Bool) -> Self {
        Self(
            token: "restoration-example",
            deletedAt: "2026-07-16T12:00:00Z",
            deletionReason: "user_requested",
            deletionSource: "settings",
            firstName: "Example",
            requiresMFA: requiresMFA
        )
    }
}
