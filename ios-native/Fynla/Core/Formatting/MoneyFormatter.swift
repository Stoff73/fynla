import Foundation

enum MoneyFormatter {
    private static let ukLocale = Locale(identifier: "en_GB")

    static func gbp(_ value: Decimal) -> String {
        let formatter = NumberFormatter()
        formatter.locale = ukLocale
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        formatter.minimumFractionDigits = 2
        formatter.maximumFractionDigits = 2
        return formatter.string(from: value as NSDecimalNumber) ?? "Unavailable"
    }

    // Whole-pound format matching /m's dashboard fmt(): '£' + Math.round(n)
    // .toLocaleString('en-GB') — no pence.
    static func gbpWhole(_ value: Decimal) -> String {
        let formatter = NumberFormatter()
        formatter.locale = ukLocale
        formatter.numberStyle = .currency
        formatter.currencyCode = "GBP"
        formatter.minimumFractionDigits = 0
        formatter.maximumFractionDigits = 0
        formatter.roundingMode = .halfUp
        return formatter.string(from: value as NSDecimalNumber) ?? "Unavailable"
    }

    static func percentage(_ value: Decimal) -> String {
        let formatter = NumberFormatter()
        formatter.locale = ukLocale
        formatter.numberStyle = .decimal
        formatter.minimumFractionDigits = 0
        formatter.maximumFractionDigits = 2
        let number = formatter.string(from: value as NSDecimalNumber) ?? ""
        return number.isEmpty ? "Unavailable" : "\(number)%"
    }

    static func ukDate(_ value: Date) -> String {
        value.formatted(
            Date.FormatStyle(
                date: .abbreviated,
                time: .omitted,
                locale: ukLocale,
                timeZone: TimeZone(secondsFromGMT: 0)!
            )
        )
    }
}
