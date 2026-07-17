import SwiftUI

struct VerificationCodeView: View {
    @Bindable var model: RegistrationModel
    @State private var code = ""
    @State private var verificationTask: Task<Void, Never>?
    @State private var resendTask: Task<Void, Never>?
    @FocusState private var isCodeFocused: Bool

    var body: some View {
        ScrollView {
            VStack(spacing: FynlaSpacing.large) {
                VStack(spacing: FynlaSpacing.small) {
                    Text("Verify your email")
                        .font(FynlaTypography.sectionTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)
                    Text(instructions)
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .multilineTextAlignment(.center)
                }

                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    Text("Six-digit verification code")
                        .font(FynlaTypography.bodySmall.weight(.semibold))
                        .foregroundStyle(FynlaColor.primaryText)
                    TextField("Six-digit verification code", text: constrainedCode)
                        .textFieldStyle(.plain)
                        .font(.system(.title2, design: .monospaced, weight: .bold))
                        .multilineTextAlignment(.center)
                        .foregroundStyle(FynlaColor.primaryText)
                        .padding(.horizontal, FynlaSpacing.medium)
                        .frame(minHeight: 56)
                        .background(FynlaColor.surface)
                        .overlay {
                            RoundedRectangle(cornerRadius: 8)
                                .stroke(codeBorderColor, lineWidth: isCodeFocused ? 2 : 1)
                        }
                        .clipShape(RoundedRectangle(cornerRadius: 8))
                        .focused($isCodeFocused)
                        .fynlaVerificationKeyboard()
                        .disabled(model.isSubmitting)
                        .accessibilityIdentifier("registration.verification.code")
                        .accessibilityHint("Enter all six digits, then choose Verify email.")
                }

                if let message = model.message {
                    Text(message)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(
                            model.messageIsError
                                ? FynlaColor.Token.raspberry700.color
                                : FynlaColor.secondaryText
                        )
                        .multilineTextAlignment(.center)
                        .accessibilityLabel(
                            model.messageIsError ? "Error: \(message)" : "Status: \(message)"
                        )
                        .accessibilityIdentifier("registration.verification.message")
                }

                VStack(spacing: FynlaSpacing.medium) {
                    FynlaButton(
                        "Verify email",
                        isLoading: model.isSubmitting,
                        isDisabled: model.isSubmitting || code.isEmpty
                    ) {
                        submit()
                    }
                    .accessibilityIdentifier("registration.verification.submit")

                    FynlaButton(
                        "Send a new code",
                        variant: .secondary,
                        isDisabled: model.isSubmitting || !model.isResendAvailable
                    ) {
                        resend()
                    }
                    .accessibilityIdentifier("registration.verification.resend")

                    if model.requiresStartOver {
                        FynlaButton("Start registration again", variant: .secondary) {
                            startOver()
                        }
                        .accessibilityIdentifier("registration.verification.startOver")
                    }

                    Button("Cancel") {
                        cancelFlow()
                    }
                    .font(FynlaTypography.button)
                    .foregroundStyle(FynlaColor.Token.raspberry700.color)
                    .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                    .accessibilityIdentifier("registration.verification.cancel")
                }
            }
            .frame(maxWidth: 480)
            .padding(.horizontal, FynlaSpacing.standard)
            .padding(.vertical, FynlaSpacing.xLarge)
            .frame(maxWidth: .infinity)
        }
        .background(FynlaColor.pageBackground.ignoresSafeArea())
        .onDisappear(perform: cancelWorkAndClearCode)
    }

    private var instructions: String {
        if let maskedEmail = model.challenge?.maskedEmail {
            return "Enter the code sent to \(maskedEmail)."
        }
        return "Enter the code sent to your email address."
    }

    private var constrainedCode: Binding<String> {
        Binding(
            get: { code },
            set: { value in
                code = RegistrationValidator.constrainedVerificationCode(value)
            }
        )
    }

    private var codeBorderColor: Color {
        if model.messageIsError {
            return FynlaColor.Token.raspberry600.color
        }
        if isCodeFocused {
            return FynlaColor.focus
        }
        return FynlaColor.Token.horizon200.color
    }

    private func submit() {
        let submittedCode = code
        verificationTask = Task { @MainActor in
            defer { verificationTask = nil }
            let result = await model.submitVerification(code: submittedCode)
            guard !Task.isCancelled else { return }
            if case let .failed(clearCode) = result, clearCode {
                self.clearCode()
            }
        }
    }

    private func resend() {
        resendTask = Task { @MainActor in
            defer { resendTask = nil }
            await model.resendCode()
        }
    }

    private func startOver() {
        cancelWorkAndClearCode()
        model.startOver()
    }

    private func cancelFlow() {
        cancelWorkAndClearCode()
        model.cancelFlow()
    }

    private func cancelWorkAndClearCode() {
        verificationTask?.cancel()
        verificationTask = nil
        resendTask?.cancel()
        resendTask = nil
        clearCode()
    }

    private func clearCode() {
        code = ""
    }
}

extension View {
    @ViewBuilder
    func fynlaVerificationKeyboard() -> some View {
        #if os(iOS)
        self
            .keyboardType(.numberPad)
            .textContentType(.oneTimeCode)
        #else
        self
        #endif
    }
}
