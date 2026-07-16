import SwiftUI

struct ErrorView: View {
    let title: String
    let message: String
    let retryTitle: String?
    let onRetry: (@MainActor () -> Void)?

    init(
        title: String = "Something went wrong",
        message: String,
        retryTitle: String? = "Try again",
        onRetry: (@MainActor () -> Void)? = nil
    ) {
        self.title = title
        self.message = message
        self.retryTitle = retryTitle
        self.onRetry = onRetry
    }

    var body: some View {
        VStack(spacing: FynlaSpacing.standard) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.center)

            Text(message)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.center)

            if let retryTitle, let onRetry {
                FynlaButton(
                    retryTitle,
                    variant: .primary,
                    accessibilityLabel: retryTitle,
                    action: onRetry
                )
            }
        }
        .frame(maxWidth: .infinity)
        .padding(FynlaSpacing.large)
    }
}
