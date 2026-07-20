import XCTest

final class FynlaUITests: XCTestCase {
    override func setUpWithError() throws {
        continueAfterFailure = false
    }

    @MainActor
    func testLocalLaunchReachesCreateAccountWithoutAStoredSession() throws {
        let app = app(mode: "live-launch")
        app.launch()

        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
        XCTAssertTrue(app.buttons["login.submit"].isHittable)
        XCTAssertTrue(app.buttons["registration.createAccount"].isHittable)
    }

    @MainActor
    func testSignedOutShellUsesTheOfflineUITestComposition() throws {
        let app = app(mode: "signed-out")
        app.launch()

        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
        XCTAssertTrue(app.secureTextFields["login.password"].exists)
        XCTAssertTrue(app.buttons["login.submit"].isHittable)
        XCTAssertTrue(app.buttons["registration.createAccount"].isHittable)
    }

    @MainActor
    func testUnlockedShellUsesTheOfflineUITestComposition() throws {
        let app = app(mode: "unlocked")
        app.launch()

        let shell = element("app.unlocked", in: app)
        XCTAssertTrue(shell.waitForExistence(timeout: 3))
        XCTAssertTrue(element("dashboard.screen", in: app).waitForExistence(timeout: 3))
        XCTAssertTrue(app.staticTexts["Your financial plan"].waitForExistence(timeout: 3))
        XCTAssertTrue(element("dashboard.level", in: app).exists)
        XCTAssertTrue(element("dashboard.module.savings", in: app).exists)
    }

    @MainActor
    func testNativeMenuOpensAchievementsWithoutLeavingTheApp() throws {
        let app = app(mode: "unlocked")
        app.launch()

        let menu = app.buttons["navigation.open"]
        XCTAssertTrue(menu.waitForExistence(timeout: 3))
        menu.tap()

        let achievements = app.buttons["navigation.achievements"]
        XCTAssertTrue(achievements.waitForExistence(timeout: 3))
        achievements.tap()

        XCTAssertTrue(
            element("achievements.screen", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.staticTexts["Achievements"].exists)
    }

    @MainActor
    func testNativeFynOpensThePersistedConversationAndSendsAReply() throws {
        let app = app(mode: "unlocked")
        app.launch()

        let open = app.buttons["fyn.open"]
        XCTAssertTrue(open.waitForExistence(timeout: 3))
        open.tap()

        XCTAssertTrue(element("fyn.screen", in: app).waitForExistence(timeout: 3))
        XCTAssertTrue(app.staticTexts["What would you like to focus on first?"].exists)

        let reply = app.buttons["fyn.reply.savings"]
        XCTAssertTrue(reply.isHittable)
        reply.tap()
        XCTAssertTrue(app.staticTexts["Let's work through savings."].waitForExistence(timeout: 3))
    }

    @MainActor
    func testNativeBugReportReviewsMetadataBeforeSubmitting() throws {
        let app = app(mode: "unlocked")
        app.launch()

        let menu = app.buttons["navigation.open"]
        XCTAssertTrue(menu.waitForExistence(timeout: 3))
        menu.tap()

        let reportProblem = app.buttons["navigation.report-a-problem"]
        XCTAssertTrue(reportProblem.waitForExistence(timeout: 3))
        reportProblem.tap()

        let description = app.textFields["bug-report.description"]
        XCTAssertTrue(description.waitForExistence(timeout: 3))
        description.tap()
        description.typeText("The native dashboard did not refresh.")
        app.buttons["bug-report.review"].tap()

        XCTAssertTrue(app.staticTexts["Technical details included"].waitForExistence(timeout: 3))
        XCTAssertTrue(app.staticTexts["Conversation text, financial values, network contents, passwords, tokens and purchase signatures are not attached."].exists)
        app.buttons["bug-report.submit"].tap()

        XCTAssertTrue(
            element("bug-report.submitted", in: app).waitForExistence(timeout: 3)
        )
    }

    @MainActor
    func testFreeSubscriptionShowsLocalizedStoreKitChoicesAndRestore() throws {
        let app = openSubscription(mode: "subscription-free")

        XCTAssertTrue(element("subscription.free", in: app).waitForExistence(timeout: 3))
        XCTAssertTrue(app.buttons["subscription.product.monthly"].label.contains("£6.99"))
        XCTAssertTrue(app.buttons["subscription.product.monthly"].label.contains("1 month"))
        XCTAssertTrue(app.buttons["subscription.product.annual"].label.contains("£59.99"))
        XCTAssertTrue(app.buttons["subscription.product.annual"].label.contains("1 year"))
        XCTAssertTrue(app.buttons["subscription.purchase"].isHittable)
        XCTAssertTrue(app.buttons["subscription.restore"].isHittable)
    }

    @MainActor
    func testApplePremiumSuppressesPurchaseAndOffersSystemManagement() throws {
        let app = openSubscription(mode: "subscription-apple-premium")

        XCTAssertTrue(
            element("subscription.apple-premium", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.buttons["subscription.manage-apple"].isHittable)
        XCTAssertFalse(app.buttons["subscription.purchase"].exists)
        XCTAssertFalse(app.buttons["subscription.restore"].exists)
    }

    @MainActor
    func testWebPremiumHasManagementInformationAndNoPurchaseCTA() throws {
        let app = openSubscription(mode: "subscription-web-premium")

        XCTAssertTrue(
            element("subscription.web-premium", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.staticTexts["Billing managed on the web"].exists)
        XCTAssertFalse(app.buttons["subscription.purchase"].exists)
        XCTAssertFalse(app.buttons["subscription.manage-apple"].exists)
    }

    @MainActor
    func testUnavailableSubscriptionCanRetrySafely() throws {
        let app = openSubscription(mode: "subscription-unavailable")

        XCTAssertTrue(
            element("subscription.unavailable", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.buttons["Try again"].isHittable)
    }

    @MainActor
    func testPendingPurchaseDoesNotOfferAnotherPurchaseTap() throws {
        let app = openSubscription(mode: "subscription-purchase-pending")

        app.buttons["subscription.purchase"].tap()
        XCTAssertTrue(
            element("subscription.pending", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertFalse(app.buttons["subscription.purchase"].exists)
        XCTAssertTrue(app.staticTexts["subscription.message"].exists)
    }

    @MainActor
    func testVerifiedPurchaseBecomesApplePremiumOnlyAfterServerAck() throws {
        let app = openSubscription(mode: "subscription-purchase-success")

        app.buttons["subscription.purchase"].tap()
        XCTAssertTrue(
            element("subscription.apple-premium", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertFalse(app.buttons["subscription.purchase"].exists)
    }

    @MainActor
    func testCancelledPurchaseRemainsFreeWithoutAnError() throws {
        let app = openSubscription(mode: "subscription-purchase-cancelled")

        app.buttons["subscription.purchase"].tap()
        XCTAssertTrue(element("subscription.free", in: app).waitForExistence(timeout: 3))
        XCTAssertFalse(app.staticTexts["subscription.message"].exists)
    }

    @MainActor
    func testRestoreReconcilesAndLoadsApplePremium() throws {
        let app = openSubscription(mode: "subscription-restore-success")

        app.buttons["subscription.restore"].tap()
        XCTAssertTrue(
            element("subscription.apple-premium", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertFalse(app.buttons["subscription.purchase"].exists)
    }

    @MainActor
    func testSettingsShowsAccountFreePlanAndFaceIDOff() throws {
        let app = openSettings(mode: "subscription-free")

        XCTAssertEqual(app.staticTexts["settings.account.name"].label, "Example User")
        XCTAssertEqual(app.staticTexts["settings.account.email"].label, "example@example.test")
        XCTAssertEqual(app.staticTexts["settings.plan.title"].label, "Free")
        XCTAssertEqual(app.switches["settings.face-id"].value as? String, "0")
        XCTAssertTrue(app.buttons["app.unlocked.lock"].isHittable)
        XCTAssertTrue(app.buttons["app.unlocked.sign-out"].isHittable)
    }

    @MainActor
    func testSettingsPreservesAppleAndWebBillingWording() throws {
        let apple = openSettings(mode: "subscription-apple-premium")
        XCTAssertTrue(
            apple.staticTexts["settings.plan.detail"].label.contains("App Store")
        )
        apple.terminate()

        let web = openSettings(mode: "subscription-web-premium")
        XCTAssertTrue(
            web.staticTexts["settings.plan.detail"].label.contains("website")
        )
    }

    @MainActor
    func testSettingsUnavailablePlanAndSignOutRemainUsable() throws {
        let app = openSettings(mode: "subscription-unavailable")

        XCTAssertEqual(app.staticTexts["settings.plan.title"].label, "Unavailable")
        app.buttons["app.unlocked.sign-out"].tap()
        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
    }

    @MainActor
    func testSettingsShowsEnabledFaceIDAfterProtectedUnlock() throws {
        let app = app(mode: "face-id-unlock-success")
        app.launch()
        XCTAssertTrue(app.buttons["app.locked.unlock"].waitForExistence(timeout: 3))
        app.buttons["app.locked.unlock"].tap()
        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
        app.buttons["app.unlocked.settings"].tap()

        XCTAssertEqual(app.switches["settings.face-id"].value as? String, "1")
    }

    @MainActor
    func testFaceIDOptInIsReachableAfterFullAuthentication() throws {
        let app = app(mode: "face-id-opt-in")
        app.launch()
        submitValidLogin(in: app)

        let enable = app.buttons["face-id.opt-in.enable"]
        let notNow = app.buttons["face-id.opt-in.not-now"]
        XCTAssertTrue(enable.waitForExistence(timeout: 3))
        XCTAssertTrue(notNow.exists)
        XCTAssertTrue(app.descendants(matching: .any)["face-id.opt-in.cover"].exists)
        notNow.tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
        XCTAssertFalse(enable.exists)
    }

    @MainActor
    func testDeterministicFaceIDSuccessUnlocksTheProtectedSession() throws {
        let app = app(mode: "face-id-unlock-success")
        app.launch()

        let unlock = app.buttons["app.locked.unlock"]
        XCTAssertTrue(unlock.waitForExistence(timeout: 3))
        unlock.tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testDeterministicFaceIDCancellationStaysLockedWithFallback() throws {
        assertRecoverableFaceIDFailure(
            mode: "face-id-cancelled",
            messageFragment: "cancelled"
        )
    }

    @MainActor
    func testDeterministicFaceIDFailureStaysLockedWithFallback() throws {
        assertRecoverableFaceIDFailure(
            mode: "face-id-failed",
            messageFragment: "did not recognise"
        )
    }

    @MainActor
    func testDeterministicFaceIDLockoutRequiresFullLogin() throws {
        assertTerminalFaceIDFailure(mode: "face-id-lockout")
    }

    @MainActor
    func testDeterministicProtectedItemInvalidationRequiresFullLogin() throws {
        assertTerminalFaceIDFailure(mode: "face-id-invalidated")
    }

    @MainActor
    func testSignedOutForgotPasswordNavigationReturnsToNativeLogin() throws {
        let app = app(mode: "signed-out")
        app.launch()

        let forgotPassword = app.buttons["login.forgotPassword"]
        XCTAssertTrue(forgotPassword.waitForExistence(timeout: 3))
        forgotPassword.tap()
        XCTAssertTrue(app.textFields["passwordReset.email"].waitForExistence(timeout: 3))
        app.buttons["passwordReset.cancel"].tap()
        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
    }

    @MainActor
    func testPrimaryControlRemainsReachableAtXXLDynamicType() throws {
        let app = app(mode: "design-system")
        app.launch()

        let window = app.windows.firstMatch
        let instructions = app.staticTexts["design-system.instructions"]
        let primaryButton = app.buttons["design-system.primary"]
        let secondaryButton = app.buttons["design-system.secondary"]
        let destructiveButton = app.buttons["design-system.destructive"]
        XCTAssertTrue(window.waitForExistence(timeout: 3))
        XCTAssertTrue(instructions.waitForExistence(timeout: 3))
        XCTAssertTrue(primaryButton.waitForExistence(timeout: 3))
        XCTAssertTrue(secondaryButton.waitForExistence(timeout: 3))
        XCTAssertTrue(destructiveButton.waitForExistence(timeout: 3))

        XCTAssertEqual(window.frame.width, 414, accuracy: 1)
        XCTAssertEqual(window.frame.height, 896, accuracy: 1)
        XCTAssertGreaterThanOrEqual(primaryButton.frame.width, 44)
        XCTAssertGreaterThan(primaryButton.frame.height, 44)
        XCTAssertGreaterThan(primaryButton.frame.height, secondaryButton.frame.height)
        XCTAssertLessThanOrEqual(instructions.frame.maxY, primaryButton.frame.minY)
        XCTAssertLessThanOrEqual(primaryButton.frame.maxY, secondaryButton.frame.minY)
        XCTAssertLessThanOrEqual(secondaryButton.frame.maxY, destructiveButton.frame.minY)
        XCTAssertEqual(
            primaryButton.label,
            "Continue to review your financial plan"
        )

        assertReachable(primaryButton, in: app)
        assertReachable(secondaryButton, in: app)
        assertReachable(destructiveButton, in: app)
    }

    @MainActor
    func testRegistrationSuccessReachesTheAuthenticatedShellOffline() throws {
        let app = app(mode: "registration-success")
        app.launch()
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()

        let code = app.textFields["registration.verification.code"]
        XCTAssertTrue(code.waitForExistence(timeout: 3))
        code.tap()
        code.typeText("123456")
        app.buttons["registration.verification.submit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testRegistrationServerFieldErrorsPreserveNonSecretValues() throws {
        let app = app(mode: "registration-field-errors")
        app.launch()
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()

        XCTAssertTrue(
            app.staticTexts["registration.firstName.error"].waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.staticTexts["registration.email.error"].exists)
        XCTAssertEqual(
            app.textFields["registration.email"].value as? String,
            "example@example.test"
        )
    }

    @MainActor
    func testRegistrationDuplicateEmailMessageIsVisible() throws {
        let app = app(mode: "registration-duplicate-email")
        app.launch()
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()

        let message = app.staticTexts["registration.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains("already exists"))
    }

    @MainActor
    func testWrongCodeClearsTheCodeForARecoverableRetry() throws {
        let app = app(mode: "registration-wrong-code")
        app.launch()
        reachVerification(in: app)

        let code = app.textFields["registration.verification.code"]
        code.tap()
        code.typeText("000000")
        app.buttons["registration.verification.submit"].tap()

        let message = app.staticTexts["registration.verification.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains("Invalid verification code"))
        XCTAssertTrue(
            ["", "Six-digit verification code"].contains(code.value as? String)
        )
    }

    @MainActor
    func testExpiredRegistrationStartsOverWithNonSecretDraftPreserved() throws {
        let app = app(mode: "registration-expired")
        app.launch()
        reachVerification(in: app)

        let code = app.textFields["registration.verification.code"]
        code.tap()
        code.typeText("123456")
        app.buttons["registration.verification.submit"].tap()

        let startOver = app.buttons["registration.verification.startOver"]
        XCTAssertTrue(startOver.waitForExistence(timeout: 3))
        startOver.tap()
        XCTAssertTrue(app.textFields["registration.firstName"].waitForExistence(timeout: 3))
        XCTAssertEqual(app.textFields["registration.firstName"].value as? String, "Example")
    }

    @MainActor
    func testRegistrationResendExhaustionDisablesFurtherResends() throws {
        let app = app(mode: "registration-resend-exhausted")
        app.launch()
        reachVerification(in: app)

        let resend = app.buttons["registration.verification.resend"]
        resend.tap()
        let message = app.staticTexts["registration.verification.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains("Maximum resend limit reached"))
        XCTAssertFalse(resend.isEnabled)
    }

    @MainActor
    func testRegistrationActionsRemainReachableAtAccessibilityXXXL() throws {
        let app = app(
            mode: "registration-large-text",
            additionalArguments: [
                "-UIPreferredContentSizeCategoryName",
                "UICTContentSizeCategoryAccessibilityExtraExtraExtraLarge",
            ]
        )
        app.launch()

        for identifier in [
            "registration.submit",
            "registration.cancel",
            "registration.terms",
            "registration.privacy",
        ] {
            let control = app.descendants(matching: .any)[identifier]
            XCTAssertTrue(control.waitForExistence(timeout: 3))
            assertReachable(control, in: app)
            XCTAssertGreaterThanOrEqual(control.frame.width, 44)
            XCTAssertGreaterThanOrEqual(control.frame.height, 44)
        }

        for _ in 0..<8 { app.swipeDown() }
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()

        let code = app.textFields["registration.verification.code"]
        XCTAssertTrue(code.waitForExistence(timeout: 3))
        for identifier in [
            "registration.verification.submit",
            "registration.verification.resend",
            "registration.verification.cancel",
        ] {
            let control = app.buttons[identifier]
            assertReachable(control, in: app)
            XCTAssertGreaterThanOrEqual(control.frame.height, 44)
        }

        let resend = app.buttons["registration.verification.resend"]
        resend.tap()
        XCTAssertTrue(
            app.staticTexts["registration.verification.message"]
                .waitForExistence(timeout: 3)
        )

        assertReachable(code, in: app)
        code.tap()
        code.typeText("123456")
        let verify = app.buttons["registration.verification.submit"]
        assertReachable(verify, in: app)
        verify.tap()

        let startOver = app.buttons["registration.verification.startOver"]
        XCTAssertTrue(startOver.waitForExistence(timeout: 3))
        assertReachable(startOver, in: app)
        XCTAssertGreaterThanOrEqual(startOver.frame.height, 44)
        startOver.tap()

        let cancel = app.buttons["registration.cancel"]
        XCTAssertTrue(cancel.waitForExistence(timeout: 3))
        assertReachable(cancel, in: app)
        cancel.tap()
        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
        XCTAssertTrue(app.buttons["login.submit"].isHittable)
    }

    @MainActor
    func testImmediateLoginSuccessUsesTheOfflineAuthenticatedGate() throws {
        let app = app(mode: "login-success")
        app.launch()
        submitValidLogin(in: app)

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginEmailVerificationResendAndSuccessBranches() throws {
        let app = app(mode: "login-verification")
        app.launch()
        submitValidLogin(in: app)

        XCTAssertTrue(
            app.otherElements["login.verification.modal"].waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.staticTexts["Enter Verification Code"].exists)
        XCTAssertTrue(app.images["login.verification.icon"].exists)
        XCTAssertTrue(
            app.staticTexts["Didn't receive the email? Check your spam folder."].exists
        )
        for index in 0..<6 {
            XCTAssertTrue(
                app.otherElements["login.verification.digit.\(index)"].exists,
                "Missing verification digit box \(index + 1)"
            )
        }

        let resend = app.buttons["login.verification.resend"]
        XCTAssertTrue(resend.waitForExistence(timeout: 3))
        resend.tap()
        XCTAssertTrue(app.staticTexts["login.message"].waitForExistence(timeout: 3))
        type("123456", into: "login.verification.code", in: app)

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginAuthenticatorBranch() throws {
        let app = app(mode: "login-mfa")
        app.launch()
        submitValidLogin(in: app)

        type("123456", into: "login.mfa.code", in: app)
        app.buttons["login.mfa.submit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginRecoveryCodeBranch() throws {
        let app = app(mode: "login-mfa")
        app.launch()
        submitValidLogin(in: app)

        let alternate = app.buttons["login.mfa.useRecovery"]
        XCTAssertTrue(alternate.waitForExistence(timeout: 3))
        alternate.tap()
        type("RECOVERY-CODE", into: "login.mfa.recoveryCode", in: app)
        app.buttons["login.mfa.recoverySubmit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginRestorationUsesServerConfirmedMetadataAndChallenge() throws {
        let app = app(mode: "login-restoration")
        app.launch()
        submitValidLogin(in: app)

        let code = app.textFields["login.restore.mfaCode"]
        XCTAssertTrue(code.waitForExistence(timeout: 3))
        XCTAssertTrue(app.staticTexts["Example, we found your retained account after your credentials were verified. Restore it to continue where you left off."].exists)
        code.tap()
        code.typeText("123456")
        app.buttons["login.restore.submit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginRestorationWithoutMultiFactorOmitsTheCodeField() throws {
        let app = app(mode: "login-restoration-no-mfa")
        app.launch()
        submitValidLogin(in: app)

        let restore = app.buttons["login.restore.submit"]
        XCTAssertTrue(restore.waitForExistence(timeout: 3))
        XCTAssertFalse(app.textFields["login.restore.mfaCode"].exists)
        XCTAssertTrue(restore.isEnabled)
        restore.tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
    }

    @MainActor
    func testLoginLockoutShowsServerWordingAndBlocksSubmission() throws {
        let app = app(mode: "login-lockout")
        app.launch()
        submitValidLogin(in: app)

        let message = app.staticTexts["login.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains("temporarily locked"))
        XCTAssertTrue(app.staticTexts["login.lockoutCountdown"].exists)
        XCTAssertFalse(app.buttons["login.submit"].isEnabled)
    }

    @MainActor
    func testPasswordResetWithoutMultiFactorIncludesResendAndCompletion() throws {
        let app = app(mode: "password-reset")
        app.launch()
        reachPasswordResetVerification(in: app)

        app.buttons["passwordReset.verification.resend"].tap()
        XCTAssertTrue(app.staticTexts["passwordReset.message"].waitForExistence(timeout: 3))
        type("123456", into: "passwordReset.verification.code", in: app)
        app.buttons["passwordReset.verification.submit"].tap()
        completePasswordReset(in: app)
    }

    @MainActor
    func testPasswordResetAuthenticatorBranchCannotBypassMultiFactor() throws {
        let app = app(mode: "password-reset-mfa")
        app.launch()
        reachPasswordResetMultiFactor(in: app)

        XCTAssertFalse(app.secureTextFields["passwordReset.newPassword"].exists)
        type("123456", into: "passwordReset.mfa.code", in: app)
        app.buttons["passwordReset.mfa.submit"].tap()
        completePasswordReset(in: app)
    }

    @MainActor
    func testPasswordResetRecoveryCodeBranchCannotBypassMultiFactor() throws {
        let app = app(mode: "password-reset-mfa")
        app.launch()
        reachPasswordResetMultiFactor(in: app)

        app.buttons["passwordReset.mfa.useRecovery"].tap()
        type("RECOVERY-CODE", into: "passwordReset.mfa.recoveryCode", in: app)
        app.buttons["passwordReset.mfa.recoverySubmit"].tap()
        completePasswordReset(in: app)
    }

    @MainActor
    private func assertReachable(_ element: XCUIElement, in app: XCUIApplication) {
        for _ in 0..<6 where !element.isHittable {
            app.swipeUp()
        }
        XCTAssertTrue(element.isHittable)
    }

    @MainActor
    private func assertRecoverableFaceIDFailure(
        mode: String,
        messageFragment: String
    ) {
        let app = app(mode: mode)
        app.launch()
        let unlock = app.buttons["app.locked.unlock"]
        XCTAssertTrue(unlock.waitForExistence(timeout: 3))
        unlock.tap()

        let message = app.staticTexts["app.locked.message"]
        XCTAssertTrue(message.waitForExistence(timeout: 3))
        XCTAssertTrue(message.label.contains(messageFragment))
        XCTAssertTrue(element("app.locked", in: app).exists)
        XCTAssertTrue(app.buttons["app.locked.sign-in-another-way"].isHittable)
    }

    @MainActor
    private func assertTerminalFaceIDFailure(mode: String) {
        let app = app(mode: mode)
        app.launch()
        let unlock = app.buttons["app.locked.unlock"]
        XCTAssertTrue(unlock.waitForExistence(timeout: 3))
        unlock.tap()

        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
        XCTAssertFalse(app.buttons["app.locked.unlock"].exists)
    }

    @MainActor
    private func fillValidRegistration(in app: XCUIApplication) {
        type("Example", into: "registration.firstName", in: app)
        type("Middle", into: "registration.middleName", in: app)
        type("User", into: "registration.surname", in: app)
        type("example@example.test", into: "registration.email", in: app)
        type("Example1!", into: "registration.password", in: app, secure: true)
        type(
            "Example1!",
            into: "registration.passwordConfirmation",
            in: app,
            secure: true
        )
    }

    @MainActor
    private func submitValidLogin(in app: XCUIApplication) {
        type("example@example.test", into: "login.email", in: app)
        type("Example1!", into: "login.password", in: app, secure: true)
        app.buttons["login.submit"].tap()
    }

    @MainActor
    private func reachPasswordResetVerification(in app: XCUIApplication) {
        type("example@example.test", into: "passwordReset.email", in: app)
        app.buttons["passwordReset.request"].tap()
        XCTAssertTrue(
            app.textFields["passwordReset.verification.code"]
                .waitForExistence(timeout: 3)
        )
    }

    @MainActor
    private func reachPasswordResetMultiFactor(in app: XCUIApplication) {
        reachPasswordResetVerification(in: app)
        type("123456", into: "passwordReset.verification.code", in: app)
        app.buttons["passwordReset.verification.submit"].tap()
        XCTAssertTrue(app.textFields["passwordReset.mfa.code"].waitForExistence(timeout: 3))
    }

    @MainActor
    private func completePasswordReset(in app: XCUIApplication) {
        type("Changed1!", into: "passwordReset.newPassword", in: app, secure: true)
        type(
            "Changed1!",
            into: "passwordReset.passwordConfirmation",
            in: app,
            secure: true
        )
        app.buttons["passwordReset.reset"].tap()
        XCTAssertTrue(
            app.buttons["passwordReset.complete.signIn"].waitForExistence(timeout: 3)
        )
    }

    @MainActor
    private func reachVerification(in app: XCUIApplication) {
        fillValidRegistration(in: app)
        app.buttons["registration.submit"].tap()
        XCTAssertTrue(
            app.textFields["registration.verification.code"].waitForExistence(timeout: 3)
        )
    }

    @MainActor
    private func type(
        _ value: String,
        into identifier: String,
        in app: XCUIApplication,
        secure: Bool = false
    ) {
        let field = secure
            ? app.secureTextFields[identifier]
            : app.textFields[identifier]
        XCTAssertTrue(field.waitForExistence(timeout: 3))
        assertReachable(field, in: app)
        field.tap()
        field.typeText(value)
    }

    @MainActor
    private func element(
        _ identifier: String,
        in app: XCUIApplication
    ) -> XCUIElement {
        app.descendants(matching: .any)[identifier]
    }

    @MainActor
    private func app(
        mode: String,
        additionalArguments: [String] = []
    ) -> XCUIApplication {
        let app = XCUIApplication()
        app.launchArguments = ["-fynla-ui-test-mode", mode] + additionalArguments
        return app
    }

    @MainActor
    private func openSubscription(mode: String) -> XCUIApplication {
        let app = openSettings(mode: mode)
        XCTAssertTrue(app.buttons["settings.premium"].waitForExistence(timeout: 3))
        app.buttons["settings.premium"].tap()
        XCTAssertTrue(element("subscription.screen", in: app).waitForExistence(timeout: 3))
        return app
    }

    @MainActor
    private func openSettings(mode: String) -> XCUIApplication {
        let app = app(mode: mode)
        app.launch()
        XCTAssertTrue(app.buttons["app.unlocked.settings"].waitForExistence(timeout: 3))
        app.buttons["app.unlocked.settings"].tap()
        XCTAssertTrue(element("settings.screen", in: app).waitForExistence(timeout: 3))
        return app
    }
}
