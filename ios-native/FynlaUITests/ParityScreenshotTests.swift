import XCTest

// Captures the rebuilt /m-parity surfaces as attachments so the shell,
// drawer, dashboard sections and Fyn overlay can be reviewed side-by-side
// against /m renders. Not a behavioural test — journeys are covered by
// FynlaUITests.
final class ParityScreenshotTests: XCTestCase {
    @MainActor
    func testCapturesParitySurfaces() throws {
        let app = XCUIApplication()
        app.launchArguments = ["-fynla-ui-test-mode", "unlocked"]
        app.launch()

        XCTAssertTrue(app.buttons["dashboard.level"].waitForExistence(timeout: 5))
        attach(app, name: "01-dashboard-top")

        app.swipeUp()
        attach(app, name: "02-dashboard-callout")

        app.swipeUp()
        app.swipeUp()
        attach(app, name: "03-dashboard-finances")

        app.swipeDown()
        app.swipeDown()
        app.swipeDown()

        let menu = app.buttons["navigation.open"]
        XCTAssertTrue(menu.waitForExistence(timeout: 3))
        menu.tap()
        XCTAssertTrue(app.buttons["navigation.close"].waitForExistence(timeout: 3))
        attach(app, name: "04-drawer")

        app.buttons["navigation.close"].tap()

        // Capture harness, not a behaviour test: the drawer-close and
        // fullScreenCover animations can briefly drop elements from the
        // accessibility snapshot, so waits here are best-effort. The journey
        // assertions live in FynlaUITests.
        let fyn = app.buttons["fyn.open"]
        _ = fyn.waitForExistence(timeout: 5)
        fyn.tap()
        _ = app.buttons["fyn.close"].waitForExistence(timeout: 5)
        attach(app, name: "05-fyn")

        app.buttons["fyn.close"].tap()

        _ = menu.waitForExistence(timeout: 5)
        menu.tap()
        let income = app.buttons["navigation.income"]
        XCTAssertTrue(income.waitForExistence(timeout: 3))
        income.tap()
        _ = app.otherElements["income.screen"].waitForExistence(timeout: 5)
        attach(app, name: "06-income")

        menu.tap()
        let expenditure = app.buttons["navigation.expenditure"]
        XCTAssertTrue(expenditure.waitForExistence(timeout: 3))
        expenditure.tap()
        _ = app.otherElements["expenditure.screen"].waitForExistence(timeout: 5)
        attach(app, name: "07-expenditure")

        menu.tap()
        let taxStrategy = app.buttons["navigation.tax-strategy"]
        XCTAssertTrue(taxStrategy.waitForExistence(timeout: 3))
        taxStrategy.tap()
        _ = app.staticTexts["tax-strategy.intro"].waitForExistence(timeout: 5)
        attach(app, name: "08-tax-strategy-top")

        app.swipeUp()
        attach(app, name: "09-tax-strategy-mid")

        // Precise part-screen drag: a full swipe leaves the dark headroom
        // hero inside the dock-occluded band between frames.
        let scroll = app.scrollViews.firstMatch
        scroll.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.75))
            .press(
                forDuration: 0.05,
                thenDragTo: scroll.coordinate(
                    withNormalizedOffset: CGVector(dx: 0.5, dy: 0.3)
                )
            )
        attach(app, name: "10-tax-strategy-hero-allowances")

        app.swipeUp()
        app.swipeUp()
        attach(app, name: "11-tax-strategy-bottom")
    }

    @MainActor
    private func attach(_ app: XCUIApplication, name: String) {
        let attachment = XCTAttachment(screenshot: app.screenshot())
        attachment.name = name
        attachment.lifetime = .keepAlways
        add(attachment)
    }
}
