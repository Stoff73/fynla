import XCTest

/// The post-save link, on native, against the staging backend.
///
/// SPEC-crud-handler-contract §5.4/§5.5. `entity_created` carried the page the
/// record lives on for a long time before native could use it: `FynEvent`
/// decoded `name` alone and threw `entity_type`, `entity_id` and `route` away at
/// parse time, so the app could not have offered the link even in principle
/// (BUG-03, 2026-08-17). The decoder is widened and the reducer turns the event
/// into a tappable reply; this is the run that shows a user getting it.
///
/// Skipped unless FYNLA_LIVE_EMAIL / FYNLA_LIVE_PASSWORD / FYNLA_LIVE_CODE_FILE
/// are provided — the sign-in code is fetched out of band and dropped into that
/// file mid-run, the same relay LiveJourneyTests uses.
final class FynCaptureLinkTests: XCTestCase {
    @MainActor
    func testCaptureOffersALinkToTheRecordsPage() throws {
        let env = ProcessInfo.processInfo.environment
        guard let email = env["FYNLA_LIVE_EMAIL"],
              let password = env["FYNLA_LIVE_PASSWORD"],
              let codePath = env["FYNLA_LIVE_CODE_FILE"]
        else {
            throw XCTSkip("Live credentials not provided.")
        }
        let message = env["FYNLA_CAPTURE_MESSAGE"]
            ?? "I have a Shepherds Friendly pension worth 12000"

        let app = XCUIApplication()
        app.launch()

        signIn(app, email: email, password: password, codePath: codePath)

        XCTAssertTrue(
            app.buttons["dashboard.level"].waitForExistence(timeout: 60),
            "Dashboard did not appear after sign in."
        )

        let keepGoing = app.buttons["achievements.celebration.continue"]
        if keepGoing.waitForExistence(timeout: 8) {
            keepGoing.tap()
            sleep(1)
        }

        // The dashboard nudge pill sits over the Fyn dock — tapping the dock
        // with it up hits the pill instead, and the chat never opens (seen live
        // 2026-08-18, screenshot native-00-composer-missing).
        for nudge in ["dashboard.unlock-nudge.dismiss", "dashboard.fyn-nudge.dismiss"] {
            let dismiss = app.buttons[nudge]
            if dismiss.waitForExistence(timeout: 3) {
                dismiss.tap()
                sleep(1)
            }
        }

        let openFyn = app.buttons["fyn.open"]
        XCTAssertTrue(openFyn.waitForExistence(timeout: 20), "Fyn could not be opened.")
        openFyn.tap()

        // The composer is a vertical-axis TextField, which XCUITest exposes as a
        // textView on some runtimes and a textField on others — match on the
        // identifier alone rather than guessing the type (this cost a run).
        let composer = app.descendants(matching: .any)
            .matching(identifier: "fyn.composer")
            .firstMatch
        if !composer.waitForExistence(timeout: 30) {
            attach(app, name: "native-00-composer-missing")
            XCTFail("Fyn composer never appeared.")
            return
        }
        composer.tap()
        composer.typeText(message)
        app.buttons["fyn.send"].tap()

        // The capture writes, then the confirmation carries the page. Streaming
        // plus a live model call is slow — give it room rather than flaking.
        let viewRecord = app.buttons["fyn.reply.view_record"]
        XCTAssertTrue(
            viewRecord.waitForExistence(timeout: 120),
            "No View link on the capture confirmation — the record was saved with no way to see it."
        )
        attach(app, name: "native-01-capture-confirmation-with-link")

        viewRecord.tap()

        // GateRoutes sends the pension capture to retirement; a different
        // FYNLA_CAPTURE_MESSAGE lands elsewhere, so accept any module screen and
        // assert we left the chat.
        var landed = false
        let landingDeadline = Date().addingTimeInterval(30)
        while Date() < landingDeadline, !landed {
            for screen in ["goals.screen", "retirement.screen", "savings.screen", "estate.screen"] {
                if app.descendants(matching: .any).matching(identifier: screen).firstMatch.exists {
                    landed = true
                    break
                }
            }
            usleep(500_000)
        }
        XCTAssertTrue(landed, "The link did not navigate to the record's page.")
        attach(app, name: "native-02-landed-on-the-records-page")
    }

    @MainActor
    private func signIn(_ app: XCUIApplication, email: String, password: String, codePath: String) {
        let emailField = app.textFields["login.email"]
        XCTAssertTrue(emailField.waitForExistence(timeout: 30))
        emailField.tap()
        emailField.typeText(email)
        let passwordField = app.secureTextFields["login.password"]
        passwordField.tap()
        passwordField.typeText(password)
        app.buttons["login.submit"].tap()

        let verificationField = app.textFields["login.verification.code"]
        let mfaField = app.textFields["login.mfa.code"]
        let stepDeadline = Date().addingTimeInterval(30)
        while Date() < stepDeadline, !verificationField.exists, !mfaField.exists {
            usleep(500_000)
        }

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
        guard let liveCode = code else {
            XCTFail("No 6-digit code arrived in \(codePath).")
            return
        }

        if verificationField.exists {
            verificationField.tap()
            verificationField.typeText(liveCode)
            tapWhenEnabled(app.buttons["login.verification.submit"])
        } else {
            mfaField.tap()
            mfaField.typeText(liveCode)
            tapWhenEnabled(app.buttons["login.mfa.submit"])
        }

        let notNow = app.buttons["face-id.opt-in.not-now"]
        if notNow.waitForExistence(timeout: 10) {
            notNow.tap()
        }
    }

    /// SwiftUI enables submit a beat after the last digit lands; an immediate
    /// tap no-ops against a still-disabled button (LiveJourneyTests, 2026-07-23).
    @MainActor
    private func tapWhenEnabled(_ button: XCUIElement, timeout: TimeInterval = 10) {
        let deadline = Date().addingTimeInterval(timeout)
        while Date() < deadline, !(button.exists && button.isEnabled && button.isHittable) {
            usleep(250_000)
        }
        XCTAssertTrue(button.exists && button.isEnabled, "\(button.identifier) never became enabled.")
        button.tap()
    }

    @MainActor
    private func attach(_ app: XCUIApplication, name: String) {
        let shot = XCTAttachment(screenshot: app.screenshot())
        shot.name = name
        shot.lifetime = .keepAlways
        add(shot)
    }
}
