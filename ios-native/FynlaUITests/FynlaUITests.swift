import XCTest

final class FynlaUITests: XCTestCase {
    override func setUpWithError() throws {
        continueAfterFailure = false
    }

    @MainActor
    func testAppShowsThePrivacySafeLaunchingShell() throws {
        let app = XCUIApplication()
        app.launch()

        XCTAssertTrue(
            element("app.launching", in: app).waitForExistence(timeout: 3)
        )
    }

    @MainActor
    func testSignedOutShellUsesTheOfflineUITestComposition() throws {
        let app = app(mode: "signed-out")
        app.launch()

        let shell = element("auth.signedOut", in: app)
        XCTAssertTrue(shell.waitForExistence(timeout: 3))
        XCTAssertTrue(shell.label.contains("Sign in to continue."))
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

        let submit = app.buttons["registration.submit"]
        XCTAssertTrue(submit.waitForExistence(timeout: 3))
        assertReachable(submit, in: app)
        XCTAssertGreaterThanOrEqual(submit.frame.height, 44)
        let privacy = app.links["Privacy Policy"]
        XCTAssertTrue(privacy.exists)
        assertReachable(privacy, in: app)
    }

    @MainActor
    private func assertReachable(_ element: XCUIElement, in app: XCUIApplication) {
        for _ in 0..<6 where !element.isHittable {
            app.swipeUp()
        }
        XCTAssertTrue(element.isHittable)
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
