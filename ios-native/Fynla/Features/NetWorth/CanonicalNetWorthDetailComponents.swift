import SwiftUI

enum CanonicalNetWorthDetailFormatting {
    static func money(_ value: Decimal?) -> String {
        value.map(MoneyFormatter.gbpWhole) ?? "—"
    }

    static func percentage(_ value: Decimal?) -> String {
        guard let value else { return "—" }
        return "\(NSDecimalNumber(decimal: value).doubleValue.formatted(.number.precision(.fractionLength(0 ... 2))))%"
    }

    static func label(_ value: String?) -> String {
        guard let value, !value.isEmpty else { return "—" }
        return value.replacingOccurrences(of: "_", with: " ").capitalized
    }

    static func date(_ value: String?) -> String {
        guard let value, !value.isEmpty else { return "—" }
        let day = DateFormatter()
        day.locale = Locale(identifier: "en_US_POSIX")
        day.timeZone = TimeZone(secondsFromGMT: 0)
        day.dateFormat = "yyyy-MM-dd"
        guard let parsed = day.date(from: String(value.prefix(10))) else { return "—" }
        let output = DateFormatter()
        output.locale = Locale(identifier: "en_GB")
        output.timeZone = TimeZone(secondsFromGMT: 0)
        output.dateFormat = "dd/MM/yyyy"
        return output.string(from: parsed)
    }
}

struct CanonicalNetWorthDetailCard<Content: View>: View {
    let title: String
    @ViewBuilder let content: () -> Content

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            Text(title.uppercased())
                .font(.system(size: 12, weight: .bold))
                .kerning(0.5)
                .foregroundStyle(FynlaColor.Token.neutral500.color)
                .padding(.bottom, 6)
            content()
        }
        .padding(16)
        .frame(maxWidth: .infinity, alignment: .leading)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
    }
}

struct CanonicalNetWorthDetailRow: View {
    let key: String
    let value: String
    var debt = false

    var body: some View {
        HStack(alignment: .firstTextBaseline, spacing: 12) {
            Text(key)
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.secondaryText)
            Spacer()
            Text(value)
                .font(.system(size: 13, weight: .semibold))
                .foregroundStyle(debt ? FynlaColor.Token.raspberry500.color : FynlaColor.primaryText)
                .multilineTextAlignment(.trailing)
        }
        .padding(.vertical, 9)
        .overlay(alignment: .bottom) {
            FynlaColor.Token.horizon100.color.frame(height: 1)
        }
    }
}
