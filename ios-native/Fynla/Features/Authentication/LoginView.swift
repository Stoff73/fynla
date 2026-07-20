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
    @FocusState private var verificationCodeIsFocused: Bool

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
        .onDisappear(perform: clearSecrets)
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

                    Button("Forgot your password?") {
                        leaveLoginFlow(forgotPassword)
                    }
                        .fynlaAuthTextAction()
                        .accessibilityIdentifier("login.forgotPassword")

                    FynlaButton("Create account", variant: .secondary) {
                        leaveLoginFlow(createAccount)
                    }
                    .accessibilityIdentifier("registration.createAccount")
                }
            }
            .fynlaAuthPagePadding()
        }
    }

    private func verification(maskedEmail: String) -> some View {
        ZStack {
            signIn
                .blur(radius: 4)
                .scaleEffect(1.02)
                .allowsHitTesting(false)
                .accessibilityHidden(true)

            Color.black.opacity(0.52)
                .ignoresSafeArea()

            GeometryReader { proxy in
                ScrollView {
                    VStack {
                        verificationCard(maskedEmail: maskedEmail)
                    }
                    .frame(maxWidth: .infinity, minHeight: proxy.size.height)
                    .padding(FynlaSpacing.standard)
                }
            }
        }
        .onAppear { verificationCodeIsFocused = true }
        .onChange(of: code) { oldValue, newValue in
            guard oldValue != newValue, newValue.count == 6 else { return }
            submitVerification()
        }
    }

    private func verificationCard(maskedEmail: String) -> some View {
        VStack(spacing: FynlaSpacing.large) {
            VStack(spacing: FynlaSpacing.standard) {
                Image(systemName: "envelope")
                    .font(.system(size: 30, weight: .medium))
                    .foregroundStyle(FynlaColor.primaryAction)
                    .frame(width: 64, height: 64)
                    .background(FynlaColor.Token.raspberry100.color)
                    .clipShape(Circle())
                    .accessibilityLabel("Verification email")
                    .accessibilityIdentifier("login.verification.icon")

                VStack(spacing: FynlaSpacing.small) {
                    Text("Enter Verification Code")
                        .font(FynlaTypography.sectionTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)

                    Text("We sent a code to \(maskedEmail)")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .multilineTextAlignment(.center)
                }
            }

            verificationCodeInput

            verificationStatus

            if model.lockoutRemainingSeconds > 0 {
                Text("Try again in \(model.lockoutRemainingSeconds) seconds.")
                    .font(FynlaTypography.bodySmall)
                    .foregroundStyle(FynlaColor.Token.raspberry700.color)
                    .accessibilityIdentifier("login.lockoutCountdown")
            }

            VStack(spacing: FynlaSpacing.large) {
                Button(resendTask == nil ? "Resend Code" : "Sending...") {
                    resendVerification()
                }
                .font(FynlaTypography.button)
                .foregroundStyle(
                    model.canResendVerification
                        ? FynlaColor.primaryAction
                        : FynlaColor.secondaryText
                )
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                .disabled(
                    model.isSubmitting
                        || model.isLocked
                        || !model.canResendVerification
                )
                .accessibilityIdentifier("login.verification.resend")

                if model.canResendVerification {
                    Text("Didn't receive the email? Check your spam folder.")
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .multilineTextAlignment(.center)
                } else {
                    Text("Close this dialogue and sign in again to receive a new code.")
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .multilineTextAlignment(.center)
                }
            }
        }
        .padding(.horizontal, 20)
        .padding(.vertical, FynlaSpacing.large)
        .frame(maxWidth: 440)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
        .shadow(color: .black.opacity(0.24), radius: 24, y: 12)
        .overlay(alignment: .topTrailing) {
            Button(action: cancel) {
                Image(systemName: "xmark")
                    .font(.system(size: 20, weight: .medium))
                    .foregroundStyle(FynlaColor.Token.horizon200.color)
                    .frame(
                        width: FynlaSpacing.minimumInteractiveTarget,
                        height: FynlaSpacing.minimumInteractiveTarget
                    )
                    .contentShape(Rectangle())
            }
            .accessibilityLabel("Cancel verification")
            .accessibilityIdentifier("login.verification.cancel")
            .padding(FynlaSpacing.xSmall)
        }
        .overlay {
            if submissionTask != nil {
                ZStack {
                    Color.white.opacity(0.9)

                    VStack(spacing: FynlaSpacing.medium) {
                        ProgressView()
                            .tint(FynlaColor.primaryAction)
                        Text("Verifying...")
                            .font(FynlaTypography.bodySmall)
                            .foregroundStyle(FynlaColor.secondaryText)
                    }
                }
                .clipShape(RoundedRectangle(cornerRadius: 20, style: .continuous))
            }
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("login.verification.modal")
    }

    private var verificationCodeInput: some View {
        ZStack {
            HStack(spacing: FynlaSpacing.small) {
                ForEach(0..<6, id: \.self) { index in
                    verificationDigit(at: index)
                }
            }

            TextField("Verification code", text: constrainedCode)
                .fynlaVerificationKeyboard()
                .focused($verificationCodeIsFocused)
                .foregroundStyle(Color.clear)
                .tint(.clear)
                .opacity(0.02)
                .disabled(model.isSubmitting || model.isLocked)
                .accessibilityLabel("Six-digit verification code")
                .accessibilityIdentifier("login.verification.code")
        }
        .frame(minHeight: 56)
        .contentShape(Rectangle())
        .onTapGesture { verificationCodeIsFocused = true }
    }

    private func verificationDigit(at index: Int) -> some View {
        let digits = Array(code)
        let value = index < digits.count ? String(digits[index]) : ""
        let isCurrent = verificationCodeIsFocused && index == min(code.count, 5)
        let borderColor = model.messageIsError
            ? FynlaColor.Token.raspberry500.color
            : (isCurrent || !value.isEmpty
                ? FynlaColor.focus
                : FynlaColor.Token.horizon200.color)

        return Text(value)
            .font(.system(.title2, design: .default, weight: .bold))
            .foregroundStyle(FynlaColor.primaryText)
            .frame(maxWidth: .infinity, minHeight: 56)
            .background(model.messageIsError ? FynlaColor.Token.raspberry100.color : Color.white)
            .overlay {
                RoundedRectangle(cornerRadius: 10, style: .continuous)
                    .stroke(borderColor, lineWidth: isCurrent ? 2.5 : 2)
            }
            .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("Digit \(index + 1)")
            .accessibilityValue(value.isEmpty ? "Empty" : value)
            .accessibilityIdentifier("login.verification.digit.\(index)")
    }

    @ViewBuilder
    private var verificationStatus: some View {
        if let message = model.message {
            Text(message)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(
                    model.messageIsError
                        ? FynlaColor.Token.raspberry700.color
                        : FynlaColor.secondaryText
                )
                .multilineTextAlignment(.center)
                .padding(FynlaSpacing.medium)
                .frame(maxWidth: .infinity)
                .background(
                    model.messageIsError
                        ? FynlaColor.Token.raspberry100.color
                        : FynlaColor.pageBackground
                )
                .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
                .accessibilityLabel(model.messageIsError ? "Error: \(message)" : message)
                .accessibilityIdentifier("login.message")
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
        verificationCodeIsFocused = false
        model.cancel()
    }

    private func leaveLoginFlow(_ action: @MainActor () -> Void) {
        cancelWorkAndClearSecrets()
        action()
    }

    private func cancelWorkAndClearSecrets() {
        submissionTask?.cancel()
        submissionTask = nil
        resendTask?.cancel()
        resendTask = nil
        countdownTask?.cancel()
        countdownTask = nil
        clearSecrets()
    }

    private func clearSecrets() {
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
