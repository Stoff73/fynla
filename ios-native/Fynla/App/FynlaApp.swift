import SwiftUI

@main
struct FynlaApp: App {
    var body: some Scene {
        WindowGroup {
            FoundationPlaceholderView()
        }
    }
}

private struct FoundationPlaceholderView: View {
    var body: some View {
        VStack(spacing: 12) {
            Image(systemName: "chart.line.uptrend.xyaxis")
                .font(.largeTitle)
                .accessibilityHidden(true)

            Text("Fynla")
                .font(.title.bold())

            Text("Native foundation")
                .font(.body)
                .foregroundStyle(.secondary)
        }
        .accessibilityElement(children: .combine)
        .accessibilityIdentifier("app.foundation")
    }
}
