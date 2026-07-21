import SwiftUI

struct FynlaButton: View {
    nonisolated static let minimumSize = CGSize(
        width: FynlaSpacing.minimumInteractiveTarget,
        height: FynlaSpacing.minimumInteractiveTarget
    )

    enum Variant: CaseIterable, Sendable {
        case primary
        case secondary
        case destructive

        var backgroundToken: FynlaColor.Token {
            switch self {
            case .primary:
                // Raspberry 600 keeps white button text above WCAG AA contrast.
                .raspberry600
            case .secondary:
                .eggshell50
            case .destructive:
                .raspberry600
            }
        }

        var pressedBackgroundToken: FynlaColor.Token {
            switch self {
            case .primary:
                .raspberry700
            case .secondary:
                .savannah100
            case .destructive:
                .raspberry800
            }
        }

        var foregroundToken: FynlaColor.Token {
            switch self {
            case .primary,
                 .destructive:
                .eggshell50
            case .secondary:
                .horizon500
            }
        }

        var borderToken: FynlaColor.Token? {
            self == .secondary ? .horizon200 : nil
        }
    }

    private let title: String
    private let accessibilityLabel: String
    private let variant: Variant
    private let isLoading: Bool
    private let isDisabled: Bool
    private let action: @MainActor () -> Void

    init(
        _ title: String,
        variant: Variant = .primary,
        accessibilityLabel: String? = nil,
        isLoading: Bool = false,
        isDisabled: Bool = false,
        action: @escaping @MainActor () -> Void
    ) {
        precondition(!title.isEmpty)
        precondition(!(accessibilityLabel ?? title).isEmpty)
        self.title = title
        self.accessibilityLabel = accessibilityLabel ?? title
        self.variant = variant
        self.isLoading = isLoading
        self.isDisabled = isDisabled
        self.action = action
    }

    var body: some View {
        Button(action: action) {
            Text(isLoading ? "Please wait…" : title)
                .font(FynlaTypography.button)
                .multilineTextAlignment(.center)
                .frame(
                    minWidth: Self.minimumSize.width,
                    maxWidth: .infinity,
                    minHeight: Self.minimumSize.height
                )
                .padding(.horizontal, FynlaSpacing.standard)
                .contentShape(Rectangle())
        }
        .buttonStyle(FynlaButtonStyle(variant: variant))
        .disabled(isDisabled || isLoading)
        .accessibilityLabel(accessibilityLabel)
        .accessibilityValue(isLoading ? "Loading" : "")
    }
}

private struct FynlaButtonStyle: ButtonStyle {
    let variant: FynlaButton.Variant

    @Environment(\.isEnabled) private var isEnabled
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    func makeBody(configuration: Configuration) -> some View {
        configuration.label
            .foregroundStyle(variant.foregroundToken.color)
            .background(
                configuration.isPressed
                    ? variant.pressedBackgroundToken.color
                    : variant.backgroundToken.color
            )
            .overlay {
                if let borderToken = variant.borderToken {
                    RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius)
                        .stroke(borderToken.color, lineWidth: 1)
                }
            }
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius))
            .opacity(isEnabled ? 1 : 0.5)
            .animation(
                reduceMotion ? nil : .easeInOut(duration: 0.15),
                value: configuration.isPressed
            )
    }
}
