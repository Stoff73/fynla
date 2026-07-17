import XCTest

final class FynlaUITests: XCTestCase {
    override func setUpWithError() throws {
        continueAfterFailure = false
    }

    @MainActor
    func testLocalLaunchReachesCreateAccountWithoutAStoredSession() throws {
        let app = app(mode: "live-launch")
        app.launch()

        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
        XCTAssertTrue(app.buttons["login.submit"].isHittable)
        XCTAssertTrue(app.buttons["registration.createAccount"].isHittable)
    }

    @MainActor
    func testSignedOutShellUsesTheOfflineUITestComposition() throws {
        let app = app(mode: "signed-out")
        app.launch()

        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
        XCTAssertTrue(app.secureTextFields["login.password"].exists)
        XCTAssertTrue(app.buttons["login.submit"].isHittable)
        XCTAssertTrue(app.buttons["registration.createAccount"].isHittable)
    }

    @MainActor
    func testUnlockedShellUsesTheOfflineUITestComposition() throws {
        let app = app(mode: "unlocked")
        app.launch()

        let shell = element("app.unlocked", in: app)
        XCTAssertTrue(shell.waitForExistence(timeout: 3))
        XCTAssertTrue(shell.label.contains("Your secure workspace is ready."))
    }

    @MainActor
    func testFaceIDOptInIsReachableAfterFullAuthentication() throws {
        let app = app(mode: "face-id-opt-in")
        app.launch()
        submitValidLogin(in: app)

        let enable = app.buttons["face-id.opt-in.enable"]
        let notNow = app.buttons["face-id.opt-in.not-now"]
        XCTAssertTrue(enable.waitForExistence(timeout: 3))
        XCTAssertTrue(notNow.exists)
        XCTAssertTrue(app.descendants(matching: .any)["face-id.opt-in.cover"].exists)
        notNow.tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
        XCTAssertFalse(enable.exists)
    }

    @MainActor
    func testDeterministicFaceIDSuccessUnlocksTheProtectedSession() throws {
        let app = app(mode: "face-id-unlock-success")
        app.launch()

        let unlock = app.buttons["app.locked.unlock"]
        XCTAssertTrue(unlock.waitForExistence(timeout: 3))
        unlock.tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testDeterministicFaceIDCancellationStaysLockedWithFallback() throws {
        assertRecoverableFaceIDFailure(
            mode: "face-id-cancelled",
            messageFragment: "cancelled"
        )
    }

    @MainActor
    func testDeterministicFaceIDFailureStaysLockedWithFallback() throws {
        assertRecoverableFaceIDFailure(
            mode: "face-id-failed",
            messageFragment: "did not recognise"
        )
    }

    @MainActor
    func testDeterministicFaceIDLockoutRequiresFullLogin() throws {
        assertTerminalFaceIDFailure(mode: "face-id-lockout")
    }

    @MainActor
    func testDeterministicProtectedItemInvalidationRequiresFullLogin() throws {
        assertTerminalFaceIDFailure(mode: "face-id-invalidated")
    }

    @MainActor
    func testSignedOutForgotPasswordNavigationReturnsToNativeLogin() throws {
        let app = app(mode: "signed-out")
        app.launch()

        let forgotPassword = app.buttons["login.forgotPassword"]
        XCTAssertTrue(forgotPassword.waitForExistence(timeout: 3))
        forgotPassword.tap()
        XCTAssertTrue(app.textFields["passwordReset.email"].waitForExistence(timeout: 3))
        app.buttons["passwordReset.cancel"].tap()
        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
    }

    @MainActor
    func testPrimaryControlRemainsReachableAtXXLDynamicType() throws {
        let app = app(mode: "design-system")
        app.launch()

        let window = app.windows.firstMatch
        let instructions = app.staticTexts["design-system.instructions"]
        let primaryButton = app.buttons["design-system.primary"]
        let secondaryButton = app.buttons["design-system.secondary"]
        let destructiveButton = app.buttons["design-system.destructive"]
        XCTAssertTrue(window.waitForExistence(timeout: 3))
        XCTAssertTrue(instructions.waitForExistence(timeout: 3))
        XCTAssertTrue(primaryButton.waitForExistence(timeout: 3))
        XCTAssertTrue(secondaryButton.waitForExistence(timeout: 3))
        XCTAssertTrue(destructiveButton.waitForExistence(timeout: 3))

        XCTAssertEqual(window.frame.width, 414, accuracy: 1)
        XCTAssertEqual(window.frame.height, 896, accuracy: 1)
        XCTAssertGreaterThanOrEqual(primaryButton.frame.width, 44)
        XCTAssertGreaterThan(primaryButton.frame.height, 44)
        XCTAssertGreaterThan(primaryButton.frame.height, secondaryButton.frame.height)
        XCTAssertLessThanOrEqual(instructions.frame.maxY, primaryButton.frame.minY)
        XCTAssertLessThanOrEqual(primaryButton.frame.maxY, secondaryButton.frame.minY)
        XCTAssertLessThanOrEqual(secondaryButton.frame.maxY, destructiveButton.frame.minY)
        XCTAssertEqual(
            primaryButton.label,
            "Continue to review your financial plan"
        )

        assertReachable(primaryButton, in: app)
        assertReachable(secondaryButton, in: app)
        assertReachable(destructiveButton, in: app)
    }

    @MainActor
    func testRegistrationSuccessReachesTheAuthenticatedShellOffline() throws {
        let app = app(mode: "registration-success")
        app.launch()
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()

        let code = app.textFields["registration.verification.code"]
        XCTAssertTrue(code.waitForExistence(timeout: 3))
        code.tap()
        code.typeText("123456")
        app.buttons["registration.verification.submit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testRegistrationServerFieldErrorsPreserveNonSecretValues() throws {
        let app = app(mode: "registration-field-errors")
        app.launch()
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()

        XCTAssertTrue(
            app.staticTexts["registration.firstName.error"].waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.staticTexts["registration.email.error"].exists)
        XCTAssertEqual(
            app.textFields["registration.email"].value as? String,
            "example@example.test"
        )
    }

    @MainActor
    func testRegistrationDuplicateEmailMessageIsVisible() throws {
        let app = app(mode: "registration-duplicate-email")
        app.launch()
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()

        let message = app.staticTexts["registration.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains("already exists"))
    }

    @MainActor
    func testWrongCodeClearsTheCodeForARecoverableRetry() throws {
        let app = app(mode: "registration-wrong-code")
        app.launch()
        reachVerification(in: app)

        let code = app.textFields["registration.verification.code"]
        code.tap()
        code.typeText("000000")
        app.buttons["registration.verification.submit"].tap()

        let message = app.staticTexts["registration.verification.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains("Invalid verification code"))
        XCTAssertEqual(code.value as? String, "")
    }

    @MainActor
    func testExpiredRegistrationStartsOverWithNonSecretDraftPreserved() throws {
        let app = app(mode: "registration-expired")
        app.launch()
        reachVerification(in: app)

        let code = app.textFields["registration.verification.code"]
        code.tap()
        code.typeText("123456")
        app.buttons["registration.verification.submit"].tap()

        let startOver = app.buttons["registration.verification.startOver"]
        XCTAssertTrue(startOver.waitForExistence(timeout: 3))
        startOver.tap()
        XCTAssertTrue(app.textFields["registration.firstName"].waitForExistence(timeout: 3))
        XCTAssertEqual(app.textFields["registration.firstName"].value as? String, "Example")
    }

    @MainActor
    func testRegistrationResendExhaustionDisablesFurtherResends() throws {
        let app = app(mode: "registration-resend-exhausted")
        app.launch()
        reachVerification(in: app)

        let resend = app.buttons["registration.verification.resend"]
        resend.tap()
        let message = app.staticTexts["registration.verification.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains("Maximum resend limit reached"))
        XCTAssertFalse(resend.isEnabled)
    }

    @MainActor
    func testRegistrationActionsRemainReachableAtAccessibilityXXXL() throws {
        let app = app(
            mode: "registration-large-text",
            additionalArguments: [
                "-UIPreferredContentSizeCategoryName",
                "UICTContentSizeCategoryAccessibilityExtraExtraExtraLarge",
            ]
        )
        app.launch()

        for identifier in [
            "registration.submit",
            "registration.cancel",
            "registration.terms",
            "registration.privacy",
        ] {
            let control = app.descendants(matching: .any)[identifier]
            XCTAssertTrue(control.waitForExistence(timeout: 3))
            assertReachable(control, in: app)
            XCTAssertGreaterThanOrEqual(control.frame.width, 44)
            XCTAssertGreaterThanOrEqual(control.frame.height, 44)
        }

        for _ in 0..<8 { app.swipeDown() }
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()

        let code = app.textFields["registration.verification.code"]
        XCTAssertTrue(code.waitForExistence(timeout: 3))
        for identifier in [
            "registration.verification.submit",
            "registration.verification.resend",
            "registration.verification.cancel",
        ] {
            let control = app.buttons[identifier]
            assertReachable(control, in: app)
            XCTAssertGreaterThanOrEqual(control.frame.height, 44)
        }

        let resend = app.buttons["registration.verification.resend"]
        resend.tap()
        XCTAssertTrue(
            app.staticTexts["registration.verification.message"]
                .waitForExistence(timeout: 3)
        )

        assertReachable(code, in: app)
        code.tap()
        code.typeText("123456")
        let verify = app.buttons["registration.verification.submit"]
        assertReachable(verify, in: app)
        verify.tap()

        let startOver = app.buttons["registration.verification.startOver"]
        XCTAssertTrue(startOver.waitForExistence(timeout: 3))
        assertReachable(startOver, in: app)
        XCTAssertGreaterThanOrEqual(startOver.frame.height, 44)
        startOver.tap()

        let cancel = app.buttons["registration.cancel"]
        XCTAssertTrue(cancel.waitForExistence(timeout: 3))
        assertReachable(cancel, in: app)
        cancel.tap()
        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
        XCTAssertTrue(app.buttons["login.submit"].isHittable)
    }

    @MainActor
    func testImmediateLoginSuccessUsesTheOfflineAuthenticatedGate() throws {
        let app = app(mode: "login-success")
        app.launch()
        submitValidLogin(in: app)

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginEmailVerificationResendAndSuccessBranches() throws {
        let app = app(mode: "login-verification")
        app.launch()
        submitValidLogin(in: app)

        let resend = app.buttons["login.verification.resend"]
        XCTAssertTrue(resend.waitForExistence(timeout: 3))
        resend.tap()
        XCTAssertTrue(app.staticTexts["login.message"].waitForExistence(timeout: 3))
        type("123456", into: "login.verification.code", in: app)
        app.buttons["login.verification.submit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginAuthenticatorBranch() throws {
        let app = app(mode: "login-mfa")
        app.launch()
        submitValidLogin(in: app)

        type("123456", into: "login.mfa.code", in: app)
        app.buttons["login.mfa.submit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginRecoveryCodeBranch() throws {
        let app = app(mode: "login-mfa")
        app.launch()
        submitValidLogin(in: app)

        let alternate = app.buttons["login.mfa.useRecovery"]
        XCTAssertTrue(alternate.waitForExistence(timeout: 3))
        alternate.tap()
        type("RECOVERY-CODE", into: "login.mfa.recoveryCode", in: app)
        app.buttons["login.mfa.recoverySubmit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginRestorationUsesServerConfirmedMetadataAndChallenge() throws {
        let app = app(mode: "login-restoration")
        app.launch()
        submitValidLogin(in: app)

        let code = app.textFields["login.restore.mfaCode"]
        XCTAssertTrue(code.waitForExistence(timeout: 3))
        XCTAssertTrue(app.staticTexts["Example, we found your retained account after your credentials were verified. Restore it to continue where you left off."].exists)
        code.tap()
        code.typeText("123456")
        app.buttons["login.restore.submit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginRestorationWithoutMultiFactorOmitsTheCodeField() throws {
        let app = app(mode: "login-restoration-no-mfa")
        app.launch()
        submitValidLogin(in: app)

        let restore = app.buttons["login.restore.submit"]
        XCTAssertTrue(restore.waitForExistence(timeout: 3))
        XCTAssertFalse(app.textFields["login.restore.mfaCode"].exists)
        XCTAssertTrue(restore.isEnabled)
        restore.tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginLockoutShowsServerWordingAndBlocksSubmission() throws {
        let app = app(mode: "login-lockout")
        app.launch()
        submitValidLogin(in: app)

        let message = app.staticTexts["login.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains("temporarily locked"))
        XCTAssertTrue(app.staticTexts["login.lockoutCountdown"].exists)
        XCTAssertFalse(app.buttons["login.submit"].isEnabled)
    }

    @MainActor
    func testPasswordResetWithoutMultiFactorIncludesResendAndCompletion() throws {
        let app = app(mode: "password-reset")
        app.launch()
        reachPasswordResetVerification(in: app)

        app.buttons["passwordReset.verification.resend"].tap()
        XCTAssertTrue(app.staticTexts["passwordReset.message"].waitForExistence(timeout: 3))
        type("123456", into: "passwordReset.verification.code", in: app)
        app.buttons["passwordReset.verification.submit"].tap()
        completePasswordReset(in: app)
    }

    @MainActor
    func testPasswordResetAuthenticatorBranchCannotBypassMultiFactor() throws {
        let app = app(mode: "password-reset-mfa")
        app.launch()
        reachPasswordResetMultiFactor(in: app)

        XCTAssertFalse(app.secureTextFields["passwordReset.newPassword"].exists)
        type("123456", into: "passwordReset.mfa.code", in: app)
        app.buttons["passwordReset.mfa.submit"].tap()
        completePasswordReset(in: app)
    }

    @MainActor
    func testPasswordResetRecoveryCodeBranchCannotBypassMultiFactor() throws {
        let app = app(mode: "password-reset-mfa")
        app.launch()
        reachPasswordResetMultiFactor(in: app)

        app.buttons["passwordReset.mfa.useRecovery"].tap()
        type("RECOVERY-CODE", into: "passwordReset.mfa.recoveryCode", in: app)
        app.buttons["passwordReset.mfa.recoverySubmit"].tap()
        completePasswordReset(in: app)
    }

    @MainActor
    private func assertReachable(_ element: XCUIElement, in app: XCUIApplication) {
        for _ in 0..<6 where !element.isHittable {
            app.swipeUp()
        }
        XCTAssertTrue(element.isHittable)
    }

    @MainActor
    private func assertRecoverableFaceIDFailure(
        mode: String,
        messageFragment: String
    ) {
        let app = app(mode: mode)
        app.launch()
        let unlock = app.buttons["app.locked.unlock"]
        XCTAssertTrue(unlock.waitForExistence(timeout: 3))
        unlock.tap()

        let message = app.staticTexts["app.locked.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains(messageFragment))
        XCTAssertTrue(element("app.locked", in: app).exists)
        XCTAssertTrue(app.buttons["app.locked.sign-in-another-way"].isHittable)
    }

    @MainActor
    private func assertTerminalFaceIDFailure(mode: String) {
        let app = app(mode: mode)
        app.launch()
        let unlock = app.buttons["app.locked.unlock"]
        XCTAssertTrue(unlock.waitForExistence(timeout: 3))
        unlock.tap()

        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
        XCTAssertFalse(app.buttons["app.locked.unlock"].exists)
    }

    @MainActor
    private func fillValidRegistration(in app: XCUIApplication) {
        type("Example", into: "registration.firstName", in: app)
        type("Middle", into: "registration.middleName", in: app)
        type("User", into: "registration.surname", in: app)
        type("example@example.test", into: "registration.email", in: app)
        type("Example1!", into: "registration.password", in: app, secure: true)
        type(
            "Example1!",
            into: "registration.passwordConfirmation",
            in: app,
            secure: true
        )
    }

    @MainActor
    private func submitValidLogin(in app: XCUIApplication) {
        type("example@example.test", into: "login.email", in: app)
        type("Example1!", into: "login.password", in: app, secure: true)
        app.buttons["login.submit"].tap()
    }

    @MainActor
    private func reachPasswordResetVerification(in app: XCUIApplication) {
        type("example@example.test", into: "passwordReset.email", in: app)
        app.buttons["passwordReset.request"].tap()
        XCTAssertTrue(
            app.textFields["passwordReset.verification.code"]
                .waitForExistence(timeout: 3)
        )
    }

    @MainActor
    private func reachPasswordResetMultiFactor(in app: XCUIApplication) {
        reachPasswordResetVerification(in: app)
        type("123456", into: "passwordReset.verification.code", in: app)
        app.buttons["passwordReset.verification.submit"].tap()
        XCTAssertTrue(app.textFields["passwordReset.mfa.code"].waitForExistence(timeout: 3))
    }

    @MainActor
    private func completePasswordReset(in app: XCUIApplication) {
        type("Changed1!", into: "passwordReset.newPassword", in: app, secure: true)
        type(
            "Changed1!",
            into: "passwordReset.passwordConfirmation",
            in: app,
            secure: true
        )
        app.buttons["passwordReset.reset"].tap()
        XCTAssertTrue(
            app.buttons["passwordReset.complete.signIn"].waitForExistence(timeout: 3)
        )
    }

    @MainActor
    private func reachVerification(in app: XCUIApplication) {
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()
        XCTAssertTrue(
            app.textFields["registration.verification.code"].waitForExistence(timeout: 3)
        )
    }

    @MainActor
    private func type(
        _ value: String,
        into identifier: String,
        in app: XCUIApplication,
        secure: Bool = false
    ) {
        let field = secure
            ? app.secureTextFields[identifier]
            : app.textFields[identifier]
        XCTAssertTrue(field.waitForExistence(timeout: 3))
        assertReachable(field, in: app)
        field.tap()
        field.typeText(value)
    }

    @MainActor
    private func element(
        _ identifier: String,
        in app: XCUIApplication
    ) -> XCUIElement {
        app.descendants(matching: .any)[identifier]
    }

    @MainActor
    private func app(
        mode: String,
        additionalArguments: [String] = []
    ) -> XCUIApplication {
        let app = XCUIApplication()
        app.launchArguments = ["-fynla-ui-test-mode", mode] + additionalArguments
        return app
    }
}
