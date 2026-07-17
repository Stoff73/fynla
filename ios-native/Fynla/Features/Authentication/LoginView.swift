import SwiftUI

struct LoginView: View {
    @Bindable var model: LoginModel
    let createAccount: @MainActor () -> Void
    let forgotPassword: @MainActor () -> Void

    @State private var password = ""
    @State private var code = ""
    @State private var submissionTask: Task<Void, Never>?
    @State private var resendTask: Task<Void, Never>?
    @State private var countdownTask: Task<Void, Never>?
    @FocusState private var focusedField: LoginField?

    var body: some View {
        Group {
            switch model.step {
            case let .verification(maskedEmail):
                verification(maskedEmail: maskedEmail)
            case .signIn:
                signIn
            case .multiFactor, .restoration, .authenticated:
                LoadingView(message: "Continuing securely")
            }
        }
        .background(FynlaColor.pageBackground.ignoresSafeArea())
        .onDisappear(perform: cancelWorkAndClearSecrets)
    }

    private var signIn: some View {
        ScrollView {
            VStack(spacing: FynlaSpacing.large) {
                authHeader(
                    title: "Sign in to Fynla",
                    detail: "Use your email and password. We will confirm your identity before opening your financial plan."
                )

                VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                    fieldLabel("Email address")
                    TextField("Email address", text: $model.email)
                        .fynlaEmailInput()
                        .fynlaAuthField(
                            identifier: "login.email",
                            isFocused: focusedField == .email,
                            isError: model.fieldErrors[.email] != nil
                        )
                        .focused($focusedField, equals: .email)
                    fieldError(model.fieldErrors[.email], identifier: "login.email.error")

                    fieldLabel("Password")
                    SecureField("Password", text: $password)
                        .fynlaCurrentPasswordInput()
                        .fynlaAuthField(
                            identifier: "login.password",
                            isFocused: focusedField == .password,
                            isError: model.fieldErrors[.password] != nil
                        )
                        .focused($focusedField, equals: .password)
                    fieldError(model.fieldErrors[.password], identifier: "login.password.error")
                }

                status

                if model.lockoutRemainingSeconds > 0 {
                    Text("Try again in \(model.lockoutRemainingSeconds) seconds.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.Token.raspberry700.color)
                        .accessibilityIdentifier("login.lockoutCountdown")
                }

                VStack(spacing: FynlaSpacing.medium) {
                    FynlaButton(
                        "Sign in",
                        isLoading: model.isSubmitting,
                        isDisabled: model.isSubmitting || model.isLocked
                    ) { submitLogin() }
                    .accessibilityIdentifier("login.submit")

                    Button("Forgot your password?") { forgotPassword() }
                        .fynlaAuthTextAction()
                        .accessibilityIdentifier("login.forgotPassword")

                    FynlaButton("Create account", variant: .secondary) {
                        createAccount()
                    }
                    .accessibilityIdentifier("registration.createAccount")
                }
            }
            .fynlaAuthPagePadding()
        }
    }

    private func verification(maskedEmail: String) -> some View {
        ScrollView {
            VStack(spacing: FynlaSpacing.large) {
                authHeader(
                    title: "Verify your sign in",
                    detail: "Enter the code sent to \(maskedEmail)."
                )

                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    fieldLabel("Six-digit verification code")
                    TextField("Six-digit verification code", text: constrainedCode)
                        .font(.system(.title2, design: .monospaced, weight: .bold))
                        .multilineTextAlignment(.center)
                        .fynlaVerificationKeyboard()
                        .fynlaAuthField(
                            identifier: "login.verification.code",
                            isFocused: false,
                            isError: model.messageIsError
                        )
                        .disabled(model.isSubmitting || model.isLocked)
                }

                status

                if model.lockoutRemainingSeconds > 0 {
                    Text("Try again in \(model.lockoutRemainingSeconds) seconds.")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.Token.raspberry700.color)
                        .accessibilityIdentifier("login.lockoutCountdown")
                }

                VStack(spacing: FynlaSpacing.medium) {
                    FynlaButton(
                        "Verify sign in",
                        isLoading: model.isSubmitting,
                        isDisabled: model.isSubmitting || model.isLocked || code.isEmpty
                    ) { submitVerification() }
                    .accessibilityIdentifier("login.verification.submit")

                    FynlaButton(
                        "Send a new code",
                        variant: .secondary,
                        isDisabled: model.isSubmitting || model.isLocked || !model.canResendVerification
                    ) { resendVerification() }
                    .accessibilityIdentifier("login.verification.resend")

                    Button("Cancel and sign in again") { cancel() }
                        .fynlaAuthTextAction()
                        .accessibilityIdentifier("login.verification.cancel")
                }
            }
            .fynlaAuthPagePadding()
        }
    }

    @ViewBuilder
    private var status: some View {
        if let message = model.message {
            Text(message)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(
                    model.messageIsError
                        ? FynlaColor.Token.raspberry700.color
                        : FynlaColor.secondaryText
                )
                .multilineTextAlignment(.center)
                .accessibilityLabel(model.messageIsError ? "Error: \(message)" : message)
                .accessibilityIdentifier("login.message")
        }
    }

    private var constrainedCode: Binding<String> {
        Binding(
            get: { code },
            set: { code = Self.asciiDigits($0) }
        )
    }

    private func submitLogin() {
        let submittedPassword = password
        submissionTask?.cancel()
        submissionTask = Task { @MainActor in
            defer { submissionTask = nil }
            let result = await model.submitLogin(password: submittedPassword)
            guard !Task.isCancelled else { return }
            if result.clearInput { password = "" }
            startCountdownIfNeeded()
        }
    }

    private func submitVerification() {
        let submittedCode = code
        submissionTask?.cancel()
        submissionTask = Task { @MainActor in
            defer { submissionTask = nil }
            let result = await model.submitVerification(code: submittedCode)
            guard !Task.isCancelled else { return }
            if result.clearInput { code = "" }
            startCountdownIfNeeded()
        }
    }

    private func resendVerification() {
        resendTask?.cancel()
        resendTask = Task { @MainActor in
            defer { resendTask = nil }
            await model.resendVerification()
            guard !Task.isCancelled else { return }
            code = ""
            startCountdownIfNeeded()
        }
    }

    private func startCountdownIfNeeded() {
        countdownTask?.cancel()
        guard model.lockoutRemainingSeconds > 0 else { return }
        countdownTask = Task { @MainActor in
            while !Task.isCancelled, model.lockoutRemainingSeconds > 0 {
                try? await Task.sleep(for: .seconds(1))
                guard !Task.isCancelled else { return }
                model.tickLockout()
            }
        }
    }

    private func cancel() {
        cancelWorkAndClearSecrets()
        model.cancel()
    }

    private func cancelWorkAndClearSecrets() {
        submissionTask?.cancel()
        submissionTask = nil
        resendTask?.cancel()
        resendTask = nil
        countdownTask?.cancel()
        countdownTask = nil
        password = ""
        code = ""
    }

    private func authHeader(title: String, detail: String) -> some View {
        VStack(spacing: FynlaSpacing.small) {
            Text(title)
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .multilineTextAlignment(.center)
                .accessibilityAddTraits(.isHeader)
            Text(detail)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.secondaryText)
                .multilineTextAlignment(.center)
        }
    }

    private func fieldLabel(_ value: String) -> some View {
        Text(value)
            .font(FynlaTypography.bodySmall.weight(.semibold))
            .foregroundStyle(FynlaColor.primaryText)
    }

    @ViewBuilder
    private func fieldError(_ value: String?, identifier: String) -> some View {
        if let value {
            Text(value)
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.Token.raspberry700.color)
                .accessibilityIdentifier(identifier)
        }
    }

    private static func asciiDigits(_ value: String) -> String {
        String(
            decoding: value.utf8.filter { (48...57).contains($0) }.prefix(6),
            as: UTF8.self
        )
    }
}

extension View {
    func fynlaAuthField(
        identifier: String,
        isFocused: Bool,
        isError: Bool
    ) -> some View {
        self
            .textFieldStyle(.plain)
            .font(FynlaTypography.body)
            .foregroundStyle(FynlaColor.primaryText)
            .padding(.horizontal, FynlaSpacing.medium)
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
            .background(FynlaColor.surface)
            .overlay {
                RoundedRectangle(cornerRadius: 6)
                    .stroke(
                        isError
                            ? FynlaColor.Token.raspberry600.color
                            : (isFocused ? FynlaColor.focus : FynlaColor.Token.horizon200.color),
                        lineWidth: isFocused ? 2 : 1
                    )
            }
            .clipShape(RoundedRectangle(cornerRadius: 6))
            .accessibilityIdentifier(identifier)
    }

    func fynlaAuthTextAction() -> some View {
        self
            .font(FynlaTypography.button)
            .foregroundStyle(FynlaColor.Token.raspberry700.color)
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
    }

    func fynlaAuthPagePadding() -> some View {
        self
            .frame(maxWidth: 480)
            .padding(.horizontal, FynlaSpacing.standard)
            .padding(.vertical, FynlaSpacing.xLarge)
            .frame(maxWidth: .infinity)
    }

    @ViewBuilder
    func fynlaCurrentPasswordInput() -> some View {
        #if os(iOS)
        self.textContentType(.password)
        #else
        self
        #endif
    }
}
