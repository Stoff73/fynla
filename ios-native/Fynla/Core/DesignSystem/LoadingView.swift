import SwiftUI

struct LoadingView: View {
    let message: String

    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    nonisolated static func showsAnimatedIndicator(reduceMotion: Bool) -> Bool {
        !reduceMotion
    }

    var body: some View {
        VStack(spacing: FynlaSpacing.standard) {
            if Self.showsAnimatedIndicator(reduceMotion: reduceMotion) {
                ProgressView()
                    .tint(FynlaColor.primaryAction)
                    .accessibilityHidden(true)
            }

            Text(message)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.center)
        }
        .frame(maxWidth: .infinity)
        .padding(FynlaSpacing.large)
        .accessibilityElement(children: .combine)
        .accessibilityLabel(message)
    }
}
