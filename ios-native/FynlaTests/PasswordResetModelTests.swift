import Foundation
import Testing
@testable import Fynla

@Suite("Native password reset model")
struct PasswordResetModelTests {
    @Test @MainActor
    func genericRequestNeverRevealsWhetherAnAccountExists() async {
        let recorder = PasswordResetRecorder(resetToken: nil)
        let model = PasswordResetModel(actions: recorder.actions)
        model.email = " absent@example.test "

        let result = await model.requestReset()

        #expect(result.succeeded)
        #expect(model.step == .email)
        #expect(
            model.message
                == "If an account exists with this email, you will receive a verification code."
        )
        #expect(model.email == " absent@example.test ")
        #expect(recorder.lastEmail == "absent@example.test")
    }

    @Test @MainActor
    func emailVerificationCannotBypassRequiredMFA() async {
        let recorder = PasswordResetRecorder(
            resetToken: "reset-token",
            emailVerification: PasswordResetEmailVerification(
                requiresMFA: true,
                canResetPassword: false
            )
        )
        let model = PasswordResetModel(actions: recorder.actions)
        model.email = "example@example.test"
        _ = await model.requestReset()
        #expect(model.step == .emailVerification)

        let invalid = await model.verifyEmail(code: "12x")
        #expect(!invalid.succeeded)
        #expect(invalid.clearInput)
        #expect(recorder.emailVerificationCount == 0)

        let verified = await model.verifyEmail(code: "123456")
        #expect(verified.succeeded)
        #expect(verified.clearInput)
        #expect(model.step == .multiFactor)

        let bypass = await model.resetPassword(
            password: "Example1!",
            confirmation: "Example1!"
        )
        #expect(!bypass.succeeded)
        #expect(recorder.resetCount == 0)

        let mfa = await model.verifyMFA(code: "234567")
        #expect(mfa.succeeded)
        #expect(model.step == .newPassword)

        let reset = await model.resetPassword(
            password: "Example1!",
            confirmation: "Example1!"
        )
        #expect(reset.succeeded)
        #expect(reset.clearInput)
        #expect(model.step == .complete)
        #expect(recorder.resetCount == 1)
    }

    @Test @MainActor
    func recoveryCodeAuthorizesResetAndIsTrimmed() async {
        let recorder = PasswordResetRecorder(
            resetToken: "reset-token",
            emailVerification: PasswordResetEmailVerification(
                requiresMFA: true,
                canResetPassword: false
            )
        )
        let model = PasswordResetModel(actions: recorder.actions)
        model.email = "example@example.test"
        _ = await model.requestReset()
        _ = await model.verifyEmail(code: "123456")

        let result = await model.useRecoveryCode(" RECOVERY-CODE ")

        #expect(result.succeeded)
        #expect(result.clearInput)
        #expect(model.step == .newPassword)
        #expect(recorder.lastRecoveryCode == "RECOVERY-CODE")
    }

    @Test @MainActor
    func resendTracksTheServerRemainingCountAndDisablesAtZero() async {
        let recorder = PasswordResetRecorder(resetToken: "reset-token")
        recorder.resendResult = PasswordResetResendResult(
            message: "A new verification code has been sent to your email.",
            remainingResends: 0
        )
        let model = PasswordResetModel(actions: recorder.actions)
        model.email = "example@example.test"
        _ = await model.requestReset()

        await model.resendEmailCode()

        #expect(model.message == "A new verification code has been sent to your email.")
        #expect(model.remainingResends == 0)
        #expect(!model.canResend)
        await model.resendEmailCode()
        #expect(recorder.resendCount == 1)
    }

    @Test @MainActor
    func passwordRulesMatchTheCurrentLaravelASCIIContract() async {
        let recorder = PasswordResetRecorder(resetToken: "reset-token")
        let model = PasswordResetModel(actions: recorder.actions)
        model.email = "example@example.test"
        _ = await model.requestReset()
        _ = await model.verifyEmail(code: "123456")

        for password in ["Sh1!", "Éxample1!", "EXAMPLE1!", "example1!", "Example!!"] {
            let result = await model.resetPassword(
                password: password,
                confirmation: password
            )
            #expect(!result.succeeded)
        }
        let mismatch = await model.resetPassword(
            password: "Example1!",
            confirmation: "Different1!"
        )
        #expect(!mismatch.succeeded)
        #expect(recorder.resetCount == 0)

        let valid = await model.resetPassword(
            password: "Example1é",
            confirmation: "Example1é"
        )
        #expect(valid.succeeded)
        #expect(recorder.resetCount == 1)
    }

    @Test @MainActor
    func cancelInvalidatesLateResetRequestAndClearsTheSession() async {
        let gate = PasswordResetGate()
        let recorder = PasswordResetRecorder(resetToken: "reset-token", requestGate: gate)
        let model = PasswordResetModel(actions: recorder.actions)
        model.email = "example@example.test"

        let task = Task { @MainActor in await model.requestReset() }
        while !model.isSubmitting { await Task.yield() }
        model.cancel()
        await gate.open()
        _ = await task.value

        #expect(model.step == .email)
        #expect(model.message == nil)
        #expect(!model.isSubmitting)
        #expect(recorder.cancelCount == 1)
    }

    @Test @MainActor
    func cancelInvalidatesLateResetContinuationBeforeItCanAdvance() async {
        let gate = PasswordResetGate()
        let recorder = PasswordResetRecorder(
            resetToken: "reset-token",
            verificationGate: gate
        )
        let model = PasswordResetModel(actions: recorder.actions)
        model.email = "example@example.test"
        _ = await model.requestReset()

        let task = Task { @MainActor in
            await model.verifyEmail(code: "123456")
        }
        while !model.isSubmitting { await Task.yield() }
        model.cancel()
        await gate.open()
        _ = await task.value

        #expect(model.step == .email)
        #expect(!model.isSubmitting)
        #expect(recorder.cancelCount == 1)
    }

    @Test
    func passwordResetSourceOwnsNoPasswordOrCodesAndExposesEveryControl() throws {
        let root = sourceDirectory()
        let source = try String(
            contentsOf: root.appending(path: "PasswordResetFlow.swift"),
            encoding: .utf8
        )
        #expect(!source.contains("UserDefaults"))
        #expect(!source.contains("DiagnosticsClient"))
        #expect(source.contains("@State private var password"))
        #expect(source.contains("@State private var code"))
        for identifier in [
            "passwordReset.email", "passwordReset.request",
            "passwordReset.verification.code", "passwordReset.verification.submit",
            "passwordReset.verification.resend", "passwordReset.mfa.code",
            "passwordReset.mfa.submit", "passwordReset.mfa.useRecovery",
            "passwordReset.mfa.recoveryCode", "passwordReset.mfa.recoverySubmit",
            "passwordReset.newPassword", "passwordReset.passwordConfirmation",
            "passwordReset.reset", "passwordReset.cancel",
        ] {
            #expect(source.contains(identifier))
        }
        #expect(source.contains("ScrollView"))
        #expect(source.contains("@State private var"))
        #expect(source.contains("Task<Void, Never>?"))
        #expect(source.contains(".cancel()"))
        #expect(!source.localizedCaseInsensitiveContains("face id replaces"))
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
}

@MainActor
private final class PasswordResetRecorder {
    var resendResult = PasswordResetResendResult(
        message: "A new verification code has been sent to your email.",
        remainingResends: 1
    )
    var emailVerificationCount = 0
    var resendCount = 0
    var resetCount = 0
    var cancelCount = 0
    var lastEmail: String?
    var lastRecoveryCode: String?

    private let resetToken: String?
    private let emailVerification: PasswordResetEmailVerification
    private let requestGate: PasswordResetGate?
    private let verificationGate: PasswordResetGate?

    init(
        resetToken: String?,
        emailVerification: PasswordResetEmailVerification = PasswordResetEmailVerification(
            requiresMFA: false,
            canResetPassword: true
        ),
        requestGate: PasswordResetGate? = nil,
        verificationGate: PasswordResetGate? = nil
    ) {
        self.resetToken = resetToken
        self.emailVerification = emailVerification
        self.requestGate = requestGate
        self.verificationGate = verificationGate
    }

    var actions: PasswordResetActions {
        PasswordResetActions(
            request: { [weak self] email in
                guard let self else { throw CancellationError() }
                self.lastEmail = email
                if let requestGate = self.requestGate { await requestGate.wait() }
                return PasswordResetRequestResult(
                    message: "If an account exists with this email, you will receive a verification code.",
                    resetToken: self.resetToken
                )
            },
            verifyEmail: { [weak self] _, _ in
                guard let self else { throw CancellationError() }
                self.emailVerificationCount += 1
                if let verificationGate = self.verificationGate {
                    await verificationGate.wait()
                }
                return self.emailVerification
            },
            resend: { [weak self] _ in
                guard let self else { throw CancellationError() }
                self.resendCount += 1
                return self.resendResult
            },
            verifyMFA: { _, _ in
                PasswordResetAuthorization(canResetPassword: true)
            },
            useRecoveryCode: { [weak self] _, code in
                guard let self else { throw CancellationError() }
                self.lastRecoveryCode = code
                return PasswordResetAuthorization(canResetPassword: true)
            },
            reset: { [weak self] _, _, _ in
                guard let self else { throw CancellationError() }
                self.resetCount += 1
                return "Password has been reset successfully. Please log in with your new password."
            },
            cancel: { [weak self] in self?.cancelCount += 1 }
        )
    }
}

private actor PasswordResetGate {
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
