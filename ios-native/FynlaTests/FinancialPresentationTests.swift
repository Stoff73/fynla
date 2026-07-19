import Foundation
import Testing
@testable import Fynla

@MainActor
@Suite("Financial presentation primitives")
struct FinancialPresentationTests {
    @Test
    func formatsUKMoneyWithoutConflatingZeroAndUnavailable() {
        #expect(MoneyFormatter.gbp(Decimal.zero) == "£0.00")
        #expect(MoneyFormatter.gbp(Decimal(string: "1234567.89")!) == "£1,234,567.89")
        #expect(MoneyFormatter.gbp(Decimal(string: "-42.5")!) == "-£42.50")

        #expect(FinancialValueView.money(nil).displayText == "Unavailable")
        #expect(FinancialValueView.money(.zero).displayText == "£0.00")
    }

    @Test
    func formatsServerSuppliedDatesAndPercentagesForTheUK() throws {
        let date = try #require(
            ISO8601DateFormatter().date(from: "2026-07-18T12:00:00Z")
        )

        #expect(MoneyFormatter.ukDate(date) == "18 Jul 2026")
        #expect(MoneyFormatter.percentage(Decimal(string: "12.5")!) == "12.5%")
    }

    @Test
    func fynEditIntentMatchesTheExistingMobileWording() {
        #expect(
            FynEditIntent.message(
                updateScope: "savings",
                addPhrase: "I'd like to add savings.",
                names: [nil, " ", "Cash ISA", "Emergency fund"]
            ) == "I'd like to update my savings. I currently have: Cash ISA, Emergency fund."
        )
        #expect(
            FynEditIntent.message(
                updateScope: "savings",
                addPhrase: "I'd like to add savings.",
                names: [nil, " "]
            ) == "I'd like to add savings."
        )
    }

    @Test
    func screenStateKeepsRetryAndUpgradeActionsExplicit() {
        #expect(ScreenStatePresentation.loading.canRetry == false)
        #expect(ScreenStatePresentation.offline.canRetry)
        #expect(ScreenStatePresentation.failed(requestID: "request-42").canRetry)
        #expect(ScreenStatePresentation.upgradeRequired(message: "Premium required").canUpgrade)
        #expect(ScreenStatePresentation.unauthenticated.canRetry == false)
    }
}
