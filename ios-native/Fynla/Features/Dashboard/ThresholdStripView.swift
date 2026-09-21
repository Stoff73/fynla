import SwiftUI

// The end figures only: the headline, what the position costs, and — behind a
// toggle — the one lever that moves it with its downside (CSJ 2026-09-21).
// Web and /m show the range and the suppressed count; native does not.
struct ThresholdStripView: View {
    let line: ThresholdLine
    let onModel: (String) -> Void
    @State private var expanded = false

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text(line.headline)
                .font(.system(size: 16, weight: .bold))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            Text(line.body)
                .font(.system(size: 13))
                .foregroundStyle(FynlaColor.Token.horizon500.color)
            HStack {
                Text("Costs you")
                    .font(.system(size: 12))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Spacer()
                Text("\(MoneyFormatter.gbpWhole(line.costTotal)) a year")
                    .font(.system(size: 15, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
            }
            Button(expanded ? "Hide" : "See what moves it") { expanded.toggle() }
                .font(.system(size: 13, weight: .bold))
                .foregroundStyle(FynlaColor.Token.raspberry500.color)
                .frame(maxWidth: .infinity, minHeight: 44)
                .accessibilityIdentifier("dashboard.threshold-toggle")
            if expanded, let lever = line.lever {
                Text("\(lever.title). You recover \(MoneyFormatter.gbpWhole(lever.recovers)).")
                    .font(.system(size: 14, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.horizon500.color)
                Text(lever.downside)
                    .font(.system(size: 13))
                    .foregroundStyle(FynlaColor.Token.violet500.color)
                Button("Model this change") { onModel(lever.route) }
                    .font(.system(size: 13, weight: .bold))
                    .foregroundStyle(FynlaColor.Token.raspberry500.color)
                    .frame(minHeight: 44, alignment: .leading)
                    .accessibilityIdentifier("dashboard.threshold-model")
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(16)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .stroke(FynlaColor.Token.horizon200.color, lineWidth: 1)
        )
        .accessibilityIdentifier("dashboard.threshold-strip")
    }
}
