import Foundation
import Testing
@testable import Fynla

@Suite("Native registration model")
struct RegistrationModelTests {
    @Test
    func validatesEveryCurrentRegistrationRule() {
        let cases: [(RegistrationDraft, String, String, RegistrationField)] = [
            (.valid(firstName: "   "), "Example1!", "Example1!", .firstName),
            (.valid(surname: "   "), "Example1!", "Example1!", .surname),
            (.valid(email: "not-an-email"), "Example1!", "Example1!", .email),
            (.valid(), "Example1!", "Different1!", .passwordConfirmation),
            (.valid(), "Ex1!", "Ex1!", .password),
            (.valid(), "EXAMPLE1!", "EXAMPLE1!", .password),
            (.valid(), "example1!", "example1!", .password),
            (.valid(), "Example!!", "Example!!", .password),
            (.valid(), "Example12", "Example12", .password),
        ]

        for (draft, password, confirmation, expectedField) in cases {
            let result = RegistrationValidator.validate(
                draft: draft,
                password: password,
                confirmation: confirmation
            )

            #expect(result.input == nil)
            #expect(result.fieldErrors[expectedField] != nil)
        }
    }

    @Test
    func trimsOnlyNonSecretRequestValues() throws {
        let result = RegistrationValidator.validate(
            draft: .valid(
                firstName: "  Example  ",
                middleName: "  Middle  ",
                surname: "  User  ",
                email: "  EXAMPLE@example.test  "
            ),
            password: " Example1! ",
            confirmation: " Example1! "
        )
        let input = try #require(result.input)

        #expect(input.firstName == "Example")
        #expect(input.middleName == "Middle")
        #expect(input.surname == "User")
        #expect(input.email == "EXAMPLE@example.test")
        #expect(input.password == " Example1! ")
        #expect(input.passwordConfirmation == " Example1! ")
    }

    @Test @MainActor
    func registrationSuccessAdvancesAndRequestsSecretClearing() async {
        let recorder = RegistrationActionRecorder()
        let model = makeModel(recorder: recorder)
        model.firstName = "  Example "
        model.middleName = ""
        model.surname = " User  "
        model.email = " example@example.test "

        let result = await model.submitRegistration(
            password: "Example1!",
            confirmation: "Example1!"
        )

        #expect(result == .advanced(clearSecrets: true))
        #expect(model.challenge == RegistrationChallenge(
            pendingID: 321,
            maskedEmail: "e***@example.test"
        ))
        #expect(model.firstName == "  Example ")
        #expect(model.email == " example@example.test ")
        #expect(recorder.registeredInput?.middleName == nil)
    }

    @Test @MainActor
    func serverFieldErrorsPreserveNonSecretFieldsAndDuplicateEmailWording() async {
        let recorder = RegistrationActionRecorder(
            registrationError: AuthError.validation(
                message: "The given data was invalid.",
                errors: [
                    "email": ["The email has already been taken."],
                    "password": ["The password format is invalid."],
                ]
            )
        )
        let model = makeModel(recorder: recorder)
        model.firstName = "Example"
        model.middleName = "Middle"
        model.surname = "User"
        model.email = "existing@example.test"

        let result = await model.submitRegistration(
            password: "Example1!",
            confirmation: "Example1!"
        )

        #expect(result == .failed(clearSecrets: false))
        #expect(model.firstName == "Example")
        #expect(model.middleName == "Middle")
        #expect(model.surname == "User")
        #expect(model.email == "existing@example.test")
        #expect(model.fieldErrors[.email] == "The email has already been taken.")
        #expect(model.fieldErrors[.password] == "The password format is invalid.")
        #expect(model.message == "The given data was invalid.")
    }

    @Test @MainActor
    func wrongVerificationCodeKeepsChallengeAndRequestsCodeClearing() async {
        let recorder = RegistrationActionRecorder(
            verificationError: AuthError.validation(
                message: "Invalid verification code",
                errors: [:]
            )
        )
        let model = makeVerificationModel(recorder: recorder)

        let result = await model.submitVerification(code: "000000")

        #expect(result == .failed(clearCode: true))
        #expect(model.challenge?.pendingID == 321)
        #expect(model.message == "Invalid verification code")
        #expect(!model.requiresStartOver)
    }

    @Test @MainActor
    func unavailableRegistrationsOfferStartOverAndPreserveOnlyNonSecretDraft() async {
        for error in [
            AuthError.validation(
                message: "Verification code has expired. Please register again.",
                errors: [:]
            ),
            .notFound(message: "Registration not found. Please start over."),
            .validation(message: "Registration has already been consumed.", errors: [:]),
        ] {
            let recorder = RegistrationActionRecorder(verificationError: error)
            let model = makeVerificationModel(recorder: recorder)
            model.firstName = "Example"
            model.email = "example@example.test"

            let result = await model.submitVerification(code: "123456")
            #expect(result == .failed(clearCode: true))
            #expect(model.requiresStartOver)

            model.startOver()
            #expect(recorder.startOverCount == 1)
            #expect(model.challenge == nil)
            #expect(model.firstName == "Example")
            #expect(model.email == "example@example.test")
        }
    }

    @Test @MainActor
    func resendSuccessAndExhaustionHaveDistinctPresentation() async {
        let successRecorder = RegistrationActionRecorder()
        let success = makeVerificationModel(recorder: successRecorder)
        await success.resendCode()
        #expect(success.message == "Verification code sent")
        #expect(success.isResendAvailable)

        let exhaustedRecorder = RegistrationActionRecorder(
            resendError: AuthError.resendExhausted(
                message: "Maximum resend limit reached. Please refresh and try again."
            )
        )
        let exhausted = makeVerificationModel(recorder: exhaustedRecorder)
        await exhausted.resendCode()
        #expect(
            exhausted.message
                == "Maximum resend limit reached. Please refresh and try again."
        )
        #expect(!exhausted.isResendAvailable)
    }

    @Test
    func verificationRequiresExactlySixNumericDigits() {
        #expect(RegistrationValidator.isValidVerificationCode("123456"))
        for invalid in ["", "12345", "1234567", "12345a", " 123456", "１２３４５６"] {
            #expect(!RegistrationValidator.isValidVerificationCode(invalid))
        }
    }

    @Test
    func verificationInputKeepsOnlyTheFirstSixASCIIDigits() {
        #expect(
            RegistrationValidator.constrainedVerificationCode("12a3 456789")
                == "123456"
        )
        #expect(
            RegistrationValidator.constrainedVerificationCode("１２3٤56")
                == "356"
        )
    }

    @Test @MainActor
    func leavingFlowInvalidatesLateRegistrationResult() async {
        let gate = RegistrationGate()
        let recorder = RegistrationActionRecorder(registrationGate: gate)
        let model = makeModel(recorder: recorder)
        model.firstName = "Example"
        model.surname = "User"
        model.email = "example@example.test"

        let submission = Task { @MainActor in
            await model.submitRegistration(
                password: "Example1!",
                confirmation: "Example1!"
            )
        }
        while !model.isSubmitting { await Task.yield() }
        model.cancelFlow()
        await gate.open()
        _ = await submission.value

        #expect(model.challenge == nil)
        #expect(model.message == nil)
        #expect(!model.isSubmitting)
        #expect(recorder.startOverCount == 1)
    }

    @Test @MainActor
    func legalURLsComeFromTheInjectedStagingWebBaseURL() {
        let recorder = RegistrationActionRecorder()
        let model = makeModel(recorder: recorder)

        #expect(model.termsURL.absoluteString == "https://staging.example.test/fynla/terms")
        #expect(model.privacyURL.absoluteString == "https://staging.example.test/fynla/privacy")
    }

    @Test
    func registrationSourcesKeepSecretsEphemeralAndExposeAccessibleScrollableControls() throws {
        let sourceRoot = sourceDirectory()
        let modelSource = try String(
            contentsOf: sourceRoot.appending(path: "RegistrationModel.swift"),
            encoding: .utf8
        )
        #expect(!modelSource.contains("UserDefaults"))
        #expect(!modelSource.contains("DiagnosticsClient"))
        #expect(!modelSource.contains("var password"))
        #expect(!modelSource.contains("var confirmation"))
        #expect(!modelSource.contains("var code"))

        let registrationSource = try String(
            contentsOf: sourceRoot.appending(path: "RegistrationView.swift"),
            encoding: .utf8
        )
        let verificationSource = try String(
            contentsOf: sourceRoot.appending(path: "VerificationCodeView.swift"),
            encoding: .utf8
        )
        for identifier in [
            "registration.firstName",
            "registration.middleName",
            "registration.surname",
            "registration.email",
            "registration.password",
            "registration.passwordConfirmation",
            "registration.submit",
            "registration.cancel",
        ] {
            #expect(registrationSource.contains(identifier))
        }
        for identifier in [
            "registration.verification.code",
            "registration.verification.submit",
            "registration.verification.resend",
            "registration.verification.startOver",
        ] {
            #expect(verificationSource.contains(identifier))
        }
        #expect(registrationSource.contains("ScrollView"))
        #expect(verificationSource.contains("ScrollView"))
        #expect(verificationSource.contains(".oneTimeCode"))
        #expect(verificationSource.contains(".numberPad"))
    }

    @Test
    func appRootWiresTheSignedOutRegistrationAndVerificationPath() throws {
        let testDirectory = URL(fileURLWithPath: #filePath).deletingLastPathComponent()
        let repositoryRoot = testDirectory.deletingLastPathComponent()
        let repositorySource = repositoryRoot.appending(path: "Fynla/App/AppRootView.swift")
        let hostSource = repositoryRoot
            .deletingLastPathComponent()
            .appending(path: "Sources/Fynla/AppRootView.swift")
        let source = try String(
            contentsOf: FileManager.default.fileExists(atPath: repositorySource.path)
                ? repositorySource
                : hostSource,
            encoding: .utf8
        )

        #expect(source.contains("RegistrationView(model: registrationModel)"))
        #expect(source.contains("VerificationCodeView(model: registrationModel)"))
        #expect(source.contains("registrationModel.presentRegistration()"))
    }

    @MainActor
    private func makeModel(recorder: RegistrationActionRecorder) -> RegistrationModel {
        RegistrationModel(
            actions: recorder.actions,
            webBaseURL: URL(string: "https://staging.example.test/fynla")!,
            isPresentingRegistration: true
        )
    }

    @MainActor
    private func makeVerificationModel(
        recorder: RegistrationActionRecorder
    ) -> RegistrationModel {
        let model = makeModel(recorder: recorder)
        model.beginVerification(
            RegistrationChallenge(pendingID: 321, maskedEmail: "e***@example.test")
        )
        return model
    }

    private func sourceDirectory() -> URL {
        let testDirectory = URL(fileURLWithPath: #filePath).deletingLastPathComponent()
        let repositorySource = testDirectory
            .deletingLastPathComponent()
            .appending(path: "Fynla/Features/Authentication")
        if FileManager.default.fileExists(atPath: repositorySource.path) {
            return repositorySource
        }
        return testDirectory
            .deletingLastPathComponent()
            .deletingLastPathComponent()
            .appending(path: "Sources/Fynla")
    }
}

private extension RegistrationDraft {
    static func valid(
        firstName: String = "Example",
        middleName: String = "Middle",
        surname: String = "User",
        email: String = "example@example.test"
    ) -> Self {
        Self(
            firstName: firstName,
            middleName: middleName,
            surname: surname,
            email: email
        )
    }
}

@MainActor
private final class RegistrationActionRecorder {
    var registeredInput: RegistrationInput?
    var startOverCount = 0

    private let registrationError: AuthError?
    private let verificationError: AuthError?
    private let resendError: AuthError?
    private let registrationGate: RegistrationGate?

    init(
        registrationError: AuthError? = nil,
        verificationError: AuthError? = nil,
        resendError: AuthError? = nil,
        registrationGate: RegistrationGate? = nil
    ) {
        self.registrationError = registrationError
        self.verificationError = verificationError
        self.resendError = resendError
        self.registrationGate = registrationGate
    }

    var actions: RegistrationActions {
        RegistrationActions(
            register: { [weak self] input in
                guard let self else { throw CancellationError() }
                self.registeredInput = input
                if let registrationGate = self.registrationGate {
                    await registrationGate.wait()
                }
                if let registrationError = self.registrationError {
                    throw registrationError
                }
                return RegistrationChallenge(
                    pendingID: 321,
                    maskedEmail: "e***@example.test"
                )
            },
            verify: { [weak self] _ in
                guard let self else { throw CancellationError() }
                if let verificationError = self.verificationError {
                    throw verificationError
                }
            },
            resend: { [weak self] _ in
                guard let self else { throw CancellationError() }
                if let resendError = self.resendError {
                    throw resendError
                }
                return "Verification code sent"
            },
            startOver: { [weak self] in
                self?.startOverCount += 1
            }
        )
    }
}

private actor RegistrationGate {
    private var isOpen = false
    private var continuations: [CheckedContinuation<Void, Never>] = []

    func wait() async {
        if isOpen { return }
        await withCheckedContinuation { continuation in
            continuations.append(continuation)
        }
    }

    func open() {
        isOpen = true
        let waiting = continuations
        continuations.removeAll()
        for continuation in waiting { continuation.resume() }
    }
}
