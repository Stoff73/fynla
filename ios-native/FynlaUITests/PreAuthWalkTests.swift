import XCTest

// Walks every screen reachable before a session exists, driving real taps on
// real accessibility identifiers. Needs no credentials, so it runs on any
// machine with a simulator — unlike LiveJourneyTests, which gates on
// FYNLA_LIVE_EMAIL and a code relay.
//
// Every state is attached as a screenshot so the run is reviewable, not just
// pass/fail.
final class PreAuthWalkTests: XCTestCase {
    @MainActor
    func testPreAuthSurfaceIsReachableAndReportsFailures() throws {
        let app = XCUIApplication()
        app.launch()

        // --- Sign in ---------------------------------------------------------
        let email = app.textFields["login.email"]
        XCTAssertTrue(email.waitForExistence(timeout: 30), "Login screen never appeared.")
        XCTAssertTrue(app.secureTextFields["login.password"].exists, "No password field.")
        XCTAssertTrue(app.buttons["login.submit"].exists, "No submit button.")
        attach(app, "01-login")

        // --- Create an account ----------------------------------------------
        let createAccount = app.buttons["registration.createAccount"]
        XCTAssertTrue(createAccount.exists, "No 'Create an account' control on the login screen.")
        createAccount.tap()
        XCTAssertTrue(
            app.textFields["registration.email"].waitForExistence(timeout: 20),
            "'Create an account' did not present the registration form."
        )
        attach(app, "02-create-account")

        // Every field the form promises must actually be there.
        for field in ["registration.firstName", "registration.middleName", "registration.surname", "registration.email"] {
            XCTAssertTrue(app.textFields[field].exists, "Registration form is missing \(field).")
        }
        XCTAssertTrue(app.secureTextFields["registration.password"].exists, "No password field.")
        XCTAssertTrue(app.secureTextFields["registration.passwordConfirmation"].exists, "No confirm-password field.")
        XCTAssertTrue(app.buttons["registration.submit"].exists, "No submit button.")
        // A signup form that cannot reach terms or privacy is a compliance problem.
        // Matched type-agnostically: SwiftUI renders these as Link, not Button.
        XCTAssertTrue(any(app, "registration.terms"), "No terms link on the signup form.")
        XCTAssertTrue(any(app, "registration.privacy"), "No privacy link on the signup form.")

        XCTAssertTrue(dismissToLogin(app), "No way back to sign-in from the registration form — dead end.")
        attach(app, "03-back-at-login")

        // --- Forgot password -------------------------------------------------
        let forgot = app.buttons["login.forgotPassword"]
        XCTAssertTrue(forgot.waitForExistence(timeout: 10), "'Forgot your password?' missing from the login screen.")
        forgot.tap()
        sleep(3)
        attach(app, "04-forgot-password")
        XCTAssertTrue(dismissToLogin(app), "No way back to sign-in from password reset — dead end.")

        // --- Rejected credentials must SAY so, not fail silently -------------
        let emailAgain = app.textFields["login.email"]
        guard emailAgain.waitForExistence(timeout: 15) else {
            XCTFail("Login screen did not come back for the bad-credential check.")
            return
        }
        emailAgain.tap()
        emailAgain.typeText("preauth-walk-no-such-user@example.invalid")
        let pw = app.secureTextFields["login.password"]
        pw.tap()
        pw.typeText("definitely-not-the-password")
        app.buttons["login.submit"].tap()

        // A rejected sign-in must surface a message. Silence would be the bug.
        let message = app.staticTexts["login.message"]
        let appeared = message.waitForExistence(timeout: 30)
        attach(app, "05-rejected-credentials")
        XCTAssertTrue(
            appeared,
            "Sign-in was rejected with no message shown — silent failure."
        )
        if appeared {
            XCTAssertFalse(
                message.label.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty,
                "login.message exists but is empty."
            )
        }
    }

    // MARK: - helpers

    /// Every dismissal control the pre-auth surfaces actually expose. Confirmed
    /// against the UI hierarchy dumps of a real run — `registration.cancel` is
    /// the one the create-account form uses, not a `chrome.back`.
    private static let dismissControls = [
        "registration.cancel",
        "passwordReset.cancel",
        "login.restore.cancel",
        "chrome.back",
    ]

    @MainActor
    @discardableResult
    private func dismissToLogin(_ app: XCUIApplication) -> Bool {
        for id in Self.dismissControls {
            let control = app.descendants(matching: .any).matching(identifier: id).firstMatch
            guard control.exists else { continue }
            // On a 667pt screen these controls sit below the fold (registration.cancel
            // is at y=755), so they exist but are not hittable until scrolled to.
            for _ in 0..<4 where !control.isHittable {
                app.swipeUp()
            }
            if control.isHittable {
                control.tap()
                if app.textFields["login.email"].waitForExistence(timeout: 10) { return true }
            }
        }
        // Sheet-presented flows may only be dismissible by gesture.
        app.swipeDown()
        return app.textFields["login.email"].waitForExistence(timeout: 10)
    }

    /// Existence check that ignores the element's automation type. SwiftUI
    /// reports the same control as Button or Link depending on which attribute
    /// set XCTest reads, and asking for the wrong one fails the assertion rather
    /// than the app.
    @MainActor
    private func any(_ app: XCUIApplication, _ identifier: String) -> Bool {
        app.descendants(matching: .any).matching(identifier: identifier).firstMatch.exists
    }

    @MainActor
    private func attach(_ app: XCUIApplication, _ name: String) {
        let shot = XCTAttachment(screenshot: app.screenshot())
        shot.name = name
        shot.lifetime = .keepAlways
        add(shot)
    }
}
