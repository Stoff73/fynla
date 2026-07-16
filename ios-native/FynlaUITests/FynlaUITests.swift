import XCTest

final class FynlaUITests: XCTestCase {
    override func setUpWithError() throws {
        continueAfterFailure = false
    }

    @MainActor
    func testAppLaunches() throws {
        let app = XCUIApplication()
        app.launch()

        XCTAssertTrue(app.otherElements["app.foundation"].waitForExistence(timeout: 3))
    }
}
