import XCTest

// Live end-to-end verification against the staging backend (csjones): signs
// in as a real user, completes the emailed-code step with a code the operator
// drops into a file mid-run, then screenshots the dashboard and key module
// screens so pulled-through amounts can be verified against the onboarding
// answers. Skipped unless FYNLA_LIVE_EMAIL is provided.
final class LiveJourneyTests: XCTestCase {
    @MainActor
    func testLiveLoginPullsThroughOnboardingData() throws {
        let env = ProcessInfo.processInfo.environment
        guard let email = env["FYNLA_LIVE_EMAIL"],
              let password = env["FYNLA_LIVE_PASSWORD"],
              let codePath = env["FYNLA_LIVE_CODE_FILE"]
        else {
            throw XCTSkip("Live credentials not provided.")
        }

        let app = XCUIApplication()
        app.launch()

        let emailField = app.textFields["login.email"]
        XCTAssertTrue(emailField.waitForExistence(timeout: 30))
        emailField.tap()
        emailField.typeText(email)
        let passwordField = app.secureTextFields["login.password"]
        passwordField.tap()
        passwordField.typeText(password)
        app.buttons["login.submit"].tap()

        // Code step arrives as either the emailed-verification step (hidden
        // aggregate field behind the digit boxes) or the multi-factor field.
        let verificationField = app.textFields["login.verification.code"]
        let mfaField = app.textFields["login.mfa.code"]
        let stepDeadline = Date().addingTimeInterval(30)
        while Date() < stepDeadline, !verificationField.exists, !mfaField.exists {
            usleep(500_000)
        }
        XCTAssertTrue(
            verificationField.exists || mfaField.exists,
            "No code step appeared after sign in."
        )

        // The backend code is fetched out-of-band and dropped into codePath —
        // poll until it lands.
        var code: String?
        let codeDeadline = Date().addingTimeInterval(180)
        while Date() < codeDeadline {
            if let contents = try? String(contentsOfFile: codePath, encoding: .utf8) {
                let trimmed = contents.trimmingCharacters(in: .whitespacesAndNewlines)
                if trimmed.count == 6, trimmed.allSatisfy(\.isNumber) {
                    code = trimmed
                    break
                }
            }
            usleep(1_000_000)
        }
        let liveCode = try XCTUnwrap(code, "No 6-digit code arrived in \(codePath).")

        if verificationField.exists {
            verificationField.tap()
            verificationField.typeText(liveCode)
            app.buttons["login.verification.submit"].tap()
        } else {
            mfaField.tap()
            mfaField.typeText(liveCode)
            app.buttons["login.mfa.submit"].tap()
        }

        // Face ID opt-in may interpose after a successful live sign-in.
        let notNow = app.buttons["face-id.opt-in.not-now"]
        if notNow.waitForExistence(timeout: 10) {
            notNow.tap()
        }

        XCTAssertTrue(
            app.buttons["dashboard.level"].waitForExistence(timeout: 60),
            "Dashboard did not appear after sign in."
        )

        // A missed level-up celebration delivered by the shell's status fetch
        // takes over the screen — capture it as live evidence, then dismiss.
        let keepGoing = app.buttons["achievements.celebration.continue"]
        if keepGoing.waitForExistence(timeout: 8) {
            sleep(1)
            attach(app, name: "live-00-level-up-fireworks")
            keepGoing.tap()
            sleep(1)
        }

        sleep(3)
        attach(app, name: "live-01-dashboard-top")
        app.swipeUp()
        app.swipeUp()
        attach(app, name: "live-02-dashboard-finances")

        openMenuItem(app, "navigation.savings")
        sleep(3)
        attach(app, name: "live-03-savings")

        openMenuItem(app, "navigation.retirement")
        sleep(3)
        attach(app, name: "live-04-retirement")

        openMenuItem(app, "navigation.tax-strategy")
        sleep(3)
        attach(app, name: "live-05-tax-strategy")
        app.swipeUp()
        attach(app, name: "live-06-tax-strategy-allowances")
    }

    @MainActor
    private func openMenuItem(_ app: XCUIApplication, _ identifier: String) {
        let menu = app.buttons["navigation.open"]
        XCTAssertTrue(menu.waitForExistence(timeout: 10))
        menu.tap()
        let item = app.buttons[identifier]
        XCTAssertTrue(item.waitForExistence(timeout: 5))
        item.tap()
    }

    @MainActor
    private func attach(_ app: XCUIApplication, name: String) {
        let attachment = XCTAttachment(screenshot: app.screenshot())
        attachment.name = name
        attachment.lifetime = .keepAlways
        add(attachment)
    }
}
