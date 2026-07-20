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
            case .signIn, .verification:
                brandPage
            case .multiFactor, .restoration, .authenticated:
                LoadingView(message: "Continuing securely")
            }
        }
        .onDisappear(perform: clearSecrets)
    }

    // Mirrors the /m login (resources/mobile/views/Login.vue): gradient brand
    // page, logo, one white card whose form swaps between the credentials and
    // verification steps.
    private var brandPage: some View {
        ScrollView {
            VStack(spacing: 0) {
                Image("LogoFynlaLight")
                    .resizable()
                    .scaledToFit()
                    .frame(height: 40)
                    .padding(.top, FynlaSpacing.xLarge)
                    .padding(.bottom, FynlaSpacing.xLarge)
                    .accessibilityLabel("Fynla")

                card
            }
            .frame(maxWidth: .infinity)
            .padding(.horizontal, FynlaSpacing.standard)
            .padding(.bottom, FynlaSpacing.xLarge)
        }
        .background(loginGradient.ignoresSafeArea())
        .scrollDismissesKeyboard(.interactively)
    }

    private var loginGradient: LinearGradient {
        LinearGradient(
            stops: [
                .init(color: FynlaColor.Token.horizon500.color, location: 0),
                .init(color: FynlaColor.Token.loginGradientMid.color, location: 0.55),
                .init(color: FynlaColor.Token.loginGradientBottom.color, location: 1),
            ],
            startPoint: UnitPoint(x: 0.33, y: 0),
            endPoint: UnitPoint(x: 0.67, y: 1)
        )
    }

    @ViewBuilder
    private var card: some View {
        Group {
            if case let .verification(maskedEmail) = model.step {
                verificationForm(maskedEmail: maskedEmail)
            } else {
                credentialsForm
            }
        }
        .padding(FynlaSpacing.large)
        .frame(maxWidth: 416)
        .background(FynlaColor.surface)
        .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.cardCornerRadius, style: .continuous))
        .shadow(
            color: FynlaColor.Token.horizon500.color.opacity(0.32),
            radius: 20,
            y: 18
        )
    }

    // Step 1 — email + password, matching /m copy and layout.
    private var credentialsForm: some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Sign in")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .accessibilityAddTraits(.isHeader)

            Text("Welcome back. Sign in to your Fynla account.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)

            VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    fieldLabel("Email address")
                    TextField("you@example.com", text: $model.email)
                        .fynlaEmailInput()
                        .fynlaAuthField(
                            identifier: "login.email",
                            isFocused: focusedField == .email,
                            isError: model.fieldErrors[.email] != nil
                        )
                        .focused($focusedField, equals: .email)
                    fieldError(model.fieldErrors[.email], identifier: "login.email.error")
                }

                VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
                    fieldLabel("Password")
                    SecureField("••••••••", text: $password)
                        .fynlaCurrentPasswordInput()
                        .fynlaAuthField(
                            identifier: "login.password",
                            isFocused: focusedField == .password,
                            isError: model.fieldErrors[.password] != nil
                        )
                        .focused($focusedField, equals: .password)
                    fieldError(model.fieldErrors[.password], identifier: "login.password.error")
                }
            }

            messageBanner

            lockoutCountdown

            FynlaButton(
                "Sign in",
                isLoading: model.isSubmitting,
                loadingTitle: "Signing in…",
                isDisabled: model.isSubmitting || model.isLocked
            ) { submitLogin() }
            .accessibilityIdentifier("login.submit")

            VStack(spacing: FynlaSpacing.small) {
                HStack(spacing: FynlaSpacing.xSmall) {
                    Text("New to Fynla?")
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                    Button("Create an account") {
                        leaveLoginFlow(createAccount)
                    }
                    .font(FynlaTypography.caption.weight(.semibold))
                    .foregroundStyle(FynlaColor.primaryAction)
                    .accessibilityIdentifier("registration.createAccount")
                }

                Button("Forgot your password?") {
                    leaveLoginFlow(forgotPassword)
                }
                .font(FynlaTypography.caption.weight(.semibold))
                .foregroundStyle(FynlaColor.primaryAction)
                .accessibilityIdentifier("login.forgotPassword")
            }
            .frame(maxWidth: .infinity)
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
        }
    }

    // Step 2 — verification code, matching /m: info banner, six digit boxes,
    // explicit submit (no auto-submit), resend + switch-account actions.
    private func verificationForm(maskedEmail: String) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.standard) {
            Text("Enter verification code")
                .font(FynlaTypography.sectionTitle)
                .foregroundStyle(FynlaColor.primaryText)
                .accessibilityAddTraits(.isHeader)

            HStack(alignment: .top, spacing: FynlaSpacing.medium) {
                // Envelope glyph mirrors the /m verification banner
                // (CSJ-directed /m design match, 2026-07-20).
                Image(systemName: "envelope")
                    .font(.system(size: 18, weight: .medium))
                    .foregroundStyle(FynlaColor.primaryAction)
                    .frame(width: 36, height: 36)
                    .background(FynlaColor.surface)
                    .clipShape(Circle())
                    .accessibilityHidden(true)

                (
                    Text("We sent a 6-digit code to ")
                    + Text(maskedEmail).fontWeight(.bold)
                    + Text(". Enter it below to continue.")
                )
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.secondaryText)
            }
            .padding(FynlaSpacing.standard)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(FynlaColor.Token.raspberry100.color)
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous))

            verificationCodeInput

            messageBanner

            lockoutCountdown

            FynlaButton(
                "Verify and continue",
                isLoading: model.isSubmitting,
                loadingTitle: "Verifying…",
                isDisabled: model.isSubmitting || model.isLocked || code.count != 6
            ) { submitVerification() }
            .accessibilityIdentifier("login.verification.submit")

            HStack {
                Button(resendTask == nil ? "Resend code" : "Sending…") {
                    resendVerification()
                }
                .font(FynlaTypography.caption.weight(.semibold))
                .foregroundStyle(
                    model.canResendVerification
                        ? FynlaColor.primaryAction
                        : FynlaColor.secondaryText
                )
                .disabled(
                    model.isSubmitting
                        || model.isLocked
                        || !model.canResendVerification
                )
                .accessibilityIdentifier("login.verification.resend")

                Spacer()

                Button("Use a different account", action: cancel)
                    .font(FynlaTypography.caption.weight(.semibold))
                    .foregroundStyle(FynlaColor.primaryAction)
                    .accessibilityIdentifier("login.verification.cancel")
            }
            .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)

            Text("Didn't receive the email? Check your spam folder.")
                .font(FynlaTypography.caption)
                .foregroundStyle(FynlaColor.secondaryText)
                .frame(maxWidth: .infinity)
                .multilineTextAlignment(.center)
        }
        .accessibilityElement(children: .contain)
        .accessibilityIdentifier("login.verification.step")
        .onAppear { verificationCodeIsFocused = true }
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
            .background(
                model.messageIsError
                    ? FynlaColor.Token.raspberry100.color
                    : FynlaColor.pageBackground
            )
            .overlay {
                RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous)
                    .stroke(borderColor, lineWidth: isCurrent ? 2.5 : 2)
            }
            .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous))
            .accessibilityElement(children: .ignore)
            .accessibilityLabel("Digit \(index + 1)")
            .accessibilityValue(value.isEmpty ? "Empty" : value)
            .accessibilityIdentifier("login.verification.digit.\(index)")
    }

    // /m renders errors as a tinted banner on both steps.
    @ViewBuilder
    private var messageBanner: some View {
        if let message = model.message {
            Text(message)
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(
                    model.messageIsError
                        ? FynlaColor.Token.raspberry700.color
                        : FynlaColor.secondaryText
                )
                .multilineTextAlignment(.leading)
                .padding(FynlaSpacing.medium)
                .frame(maxWidth: .infinity, alignment: .leading)
                .background(
                    model.messageIsError
                        ? FynlaColor.Token.raspberry100.color
                        : FynlaColor.pageBackground
                )
                .clipShape(RoundedRectangle(cornerRadius: FynlaSpacing.buttonCornerRadius, style: .continuous))
                .accessibilityLabel(model.messageIsError ? "Error: \(message)" : message)
                .accessibilityIdentifier("login.message")
        }
    }

    // Lockout feedback is a native extra: the server enforces lockouts on
    // every client and /m only surfaces the error text.
    @ViewBuilder
    private var lockoutCountdown: some View {
        if model.lockoutRemainingSeconds > 0 {
            Text("Try again in \(model.lockoutRemainingSeconds) seconds.")
                .font(FynlaTypography.bodySmall)
                .foregroundStyle(FynlaColor.Token.raspberry700.color)
                .accessibilityIdentifier("login.lockoutCountdown")
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
