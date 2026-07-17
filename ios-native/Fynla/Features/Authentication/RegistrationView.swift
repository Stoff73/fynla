import SwiftUI

struct RegistrationView: View {
    @Bindable var model: RegistrationModel

    @State private var password = ""
    @State private var passwordConfirmation = ""
    @FocusState private var focusedField: RegistrationField?

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: FynlaSpacing.large) {
                VStack(spacing: FynlaSpacing.small) {
                    Text("Create your Fynla account")
                        .font(FynlaTypography.sectionTitle)
                        .foregroundStyle(FynlaColor.primaryText)
                        .multilineTextAlignment(.center)
                        .accessibilityAddTraits(.isHeader)
                    Text("Start with a few details. You can build your financial plan after verifying your email.")
                        .font(FynlaTypography.body)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .multilineTextAlignment(.center)
                }
                .frame(maxWidth: .infinity)

                VStack(spacing: FynlaSpacing.standard) {
                    textField(
                        "First name",
                        text: $model.firstName,
                        field: .firstName,
                        identifier: "registration.firstName",
                        content: .givenName
                    )
                    textField(
                        "Middle name (optional)",
                        text: $model.middleName,
                        field: .middleName,
                        identifier: "registration.middleName",
                        content: .middleName
                    )
                    textField(
                        "Surname",
                        text: $model.surname,
                        field: .surname,
                        identifier: "registration.surname",
                        content: .familyName
                    )
                    emailField
                    secureField(
                        "Password",
                        text: $password,
                        field: .password,
                        identifier: "registration.password"
                    )
                    Text("Use at least 8 characters with lowercase, uppercase, a number and a special character.")
                        .font(FynlaTypography.caption)
                        .foregroundStyle(FynlaColor.secondaryText)
                    secureField(
                        "Confirm password",
                        text: $passwordConfirmation,
                        field: .passwordConfirmation,
                        identifier: "registration.passwordConfirmation"
                    )
                }

                if let message = model.message {
                    Text(message)
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(
                            model.messageIsError
                                ? FynlaColor.Token.raspberry700.color
                                : FynlaColor.secondaryText
                        )
                        .accessibilityLabel(
                            model.messageIsError ? "Error: \(message)" : "Status: \(message)"
                        )
                        .accessibilityIdentifier("registration.message")
                }

                VStack(spacing: FynlaSpacing.medium) {
                    FynlaButton(
                        "Create account",
                        isLoading: model.isSubmitting,
                        isDisabled: model.isSubmitting
                    ) {
                        submit()
                    }
                    .accessibilityIdentifier("registration.submit")

                    FynlaButton("Cancel", variant: .secondary) {
                        clearSecrets()
                        model.cancelFlow()
                    }
                    .accessibilityIdentifier("registration.cancel")
                }

                VStack(spacing: FynlaSpacing.small) {
                    Text("By creating an account, you agree to Fynla’s")
                        .font(FynlaTypography.bodySmall)
                        .foregroundStyle(FynlaColor.secondaryText)
                        .multilineTextAlignment(.center)
                    HStack(spacing: FynlaSpacing.standard) {
                        Link("Terms of Service", destination: model.termsURL)
                        Link("Privacy Policy", destination: model.privacyURL)
                    }
                    .font(FynlaTypography.bodySmall)
                    .tint(FynlaColor.Token.raspberry700.color)
                }
                .frame(maxWidth: .infinity)
            }
            .frame(maxWidth: 480)
            .padding(.horizontal, FynlaSpacing.standard)
            .padding(.vertical, FynlaSpacing.xLarge)
            .frame(maxWidth: .infinity)
        }
        .background(FynlaColor.pageBackground.ignoresSafeArea())
        .onDisappear(perform: clearSecrets)
    }

    private var emailField: some View {
        registrationField(
            "Email address",
            field: .email,
            identifier: "registration.email"
        ) {
            TextField("Email address", text: $model.email)
                .fynlaEmailInput()
                .focused($focusedField, equals: RegistrationField.email)
        }
    }

    private func textField(
        _ label: String,
        text: Binding<String>,
        field: RegistrationField,
        identifier: String,
        content: RegistrationTextContent
    ) -> some View {
        registrationField(label, field: field, identifier: identifier) {
            TextField(label, text: text)
                .fynlaTextContent(content)
                .focused($focusedField, equals: field)
        }
    }

    private func secureField(
        _ label: String,
        text: Binding<String>,
        field: RegistrationField,
        identifier: String
    ) -> some View {
        registrationField(label, field: field, identifier: identifier) {
            SecureField(label, text: text)
                .fynlaNewPasswordInput()
                .focused($focusedField, equals: field)
        }
    }

    private func registrationField<Field: View>(
        _ label: String,
        field: RegistrationField,
        identifier: String,
        @ViewBuilder content: () -> Field
    ) -> some View {
        VStack(alignment: .leading, spacing: FynlaSpacing.xSmall) {
            Text(label)
                .font(FynlaTypography.bodySmall.weight(.semibold))
                .foregroundStyle(FynlaColor.primaryText)
            content()
                .textFieldStyle(.plain)
                .font(FynlaTypography.body)
                .foregroundStyle(FynlaColor.primaryText)
                .padding(.horizontal, FynlaSpacing.medium)
                .frame(minHeight: FynlaSpacing.minimumInteractiveTarget)
                .background(FynlaColor.surface)
                .overlay {
                    RoundedRectangle(cornerRadius: 6)
                        .stroke(
                            borderColor(for: field),
                            lineWidth: focusedField == field ? 2 : 1
                        )
                }
                .clipShape(RoundedRectangle(cornerRadius: 6))
                .accessibilityIdentifier(identifier)
                .accessibilityHint(model.fieldErrors[field] ?? "")
            if let error = model.fieldErrors[field] {
                Text(error)
                    .font(FynlaTypography.caption)
                    .foregroundStyle(FynlaColor.Token.raspberry700.color)
                    .accessibilityLabel("\(label) error: \(error)")
                    .accessibilityIdentifier("\(identifier).error")
            }
        }
    }

    private func borderColor(for field: RegistrationField) -> Color {
        if model.fieldErrors[field] != nil {
            return FynlaColor.Token.raspberry600.color
        }
        if focusedField == field {
            return FynlaColor.focus
        }
        return FynlaColor.Token.horizon200.color
    }

    private func submit() {
        Task { @MainActor in
            let result = await model.submitRegistration(
                password: password,
                confirmation: passwordConfirmation
            )
            if result == .advanced(clearSecrets: true) {
                clearSecrets()
            }
        }
    }

    private func clearSecrets() {
        password = ""
        passwordConfirmation = ""
    }
}

private enum RegistrationTextContent {
    case givenName
    case middleName
    case familyName
}

extension View {
    @ViewBuilder
    func fynlaEmailInput() -> some View {
        #if os(iOS)
        self
            .textContentType(.emailAddress)
            .textInputAutocapitalization(.never)
            .autocorrectionDisabled()
            .keyboardType(.emailAddress)
        #else
        self
        #endif
    }

    @ViewBuilder
    fileprivate func fynlaTextContent(_ content: RegistrationTextContent) -> some View {
        #if os(iOS)
        switch content {
        case .givenName:
            self.textContentType(.givenName)
        case .middleName:
            self.textContentType(.middleName)
        case .familyName:
            self.textContentType(.familyName)
        }
        #else
        self
        #endif
    }

    @ViewBuilder
    func fynlaNewPasswordInput() -> some View {
        #if os(iOS)
        self.textContentType(.newPassword)
        #else
        self
        #endif
    }
}
