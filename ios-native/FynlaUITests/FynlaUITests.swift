import XCTest

final class FynlaUITests: XCTestCase {
    override func setUpWithError() throws {
        continueAfterFailure = false
    }

    @MainActor
    func testAppShowsThePrivacySafeLaunchingShell() throws {
        let app = XCUIApplication()
        app.launch()

        XCTAssertTrue(app.otherElements["app.launching"].waitForExistence(timeout: 3))
    }
}
