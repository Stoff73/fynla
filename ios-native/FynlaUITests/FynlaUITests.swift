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
    func testLiveDevLoginVerificationReachesDashboard() throws {
        let environment = ProcessInfo.processInfo.environment
        guard let email = environment["FYNLA_LIVE_EMAIL"],
              let password = environment["FYNLA_LIVE_PASSWORD"],
              let verificationCode = environment["FYNLA_LIVE_VERIFICATION_CODE"]
        else {
            throw XCTSkip("Live dev acceptance credentials were not supplied.")
        }

        let app = XCUIApplication()
        app.launch()
        type(email, into: "login.email", in: app)
        type(password, into: "login.password", in: app, secure: true)
        app.buttons["login.submit"].tap()

        XCTAssertTrue(
            app.otherElements["login.verification.step"]
                .waitForExistence(timeout: 20)
        )
        Thread.sleep(forTimeInterval: 15)
        type(verificationCode, into: "login.verification.code", in: app)
        app.buttons["login.verification.submit"].tap()

        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 30))
        XCTAssertTrue(element("dashboard.screen", in: app).waitForExistence(timeout: 30))
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
        XCTAssertTrue(element("dashboard.greeting", in: app).waitForExistence(timeout: 3))
        XCTAssertTrue(element("dashboard.level", in: app).exists)
        XCTAssertTrue(element("dashboard.panel.savings", in: app).exists)
    }

    @MainActor
    func testDashboardFirstDrawerShowsAdminForAnAuthenticatedAdmin() throws {
        let app = app(mode: "unlocked", additionalArguments: ["-fynla-admin"])
        app.launch()

        XCTAssertTrue(app.buttons["navigation.open"].waitForExistence(timeout: 3))
        app.buttons["navigation.open"].tap()
        let admin = app.buttons["navigation.admin"]
        assertReachable(admin, in: app)
        XCTAssertTrue(admin.exists)
    }

    @MainActor
    func testDrawerOpensPersonalInformationWithTrustedContextualEdit() throws {
        let app = app(mode: "unlocked")
        app.launch()

        XCTAssertTrue(app.buttons["navigation.open"].waitForExistence(timeout: 3))
        app.buttons["navigation.open"].tap()
        let personalInformation = app.buttons["navigation.personal-information"]
        assertReachable(personalInformation, in: app)
        personalInformation.tap()

        XCTAssertTrue(
            element("personal-information.screen", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.buttons["Edit details"].exists)
    }

    @MainActor
    func testContextualEditCreatesFreshConversationsAndHistoryReopensExactID() throws {
        let app = app(mode: "unlocked")
        app.launch()

        openDrawerItem("navigation.bank-accounts", in: app)
        XCTAssertTrue(element("savings.screen", in: app).waitForExistence(timeout: 3))
        let account = app.buttons["savings.account.12"]
        assertReachable(account, in: app)
        account.tap()
        XCTAssertTrue(element("savings.account.screen", in: app).waitForExistence(timeout: 3))

        app.buttons["Edit details"].tap()
        XCTAssertTrue(app.staticTexts["Trusted contextual opening 401."].waitForExistence(timeout: 3))
        attachAcceptance(app, name: "PR2-01-contextual-edit-first")
        app.buttons["fyn.close"].tap()
        XCTAssertTrue(element("savings.account.screen", in: app).waitForExistence(timeout: 3))

        app.buttons["Edit details"].tap()
        XCTAssertTrue(app.staticTexts["Trusted contextual opening 402."].waitForExistence(timeout: 3))
        attachAcceptance(app, name: "PR2-02-contextual-edit-fresh")
        app.buttons["fyn.close"].tap()

        openDrawerItem("navigation.conversation-history", in: app)
        XCTAssertTrue(element("conversation-history.screen", in: app).waitForExistence(timeout: 3))
        attachAcceptance(app, name: "PR2-03-conversation-history")
        XCTAssertFalse(app.buttons["conversation-history.open.499"].exists)
        let firstConversation = app.buttons["conversation-history.open.401"]
        assertReachable(firstConversation, in: app)
        firstConversation.tap()
        XCTAssertTrue(app.staticTexts["Trusted contextual opening 401."].waitForExistence(timeout: 3))
        XCTAssertFalse(app.staticTexts["Trusted contextual opening 402."].exists)
        attachAcceptance(app, name: "PR2-04-history-exact-transcript")
        app.buttons["fyn.close"].tap()

        let fallback = app.buttons["conversation-history.fallback.499"]
        assertReachable(fallback, in: app)
        fallback.tap()
        XCTAssertTrue(element("savings.screen", in: app).waitForExistence(timeout: 3))
        attachAcceptance(app, name: "PR2-05-unavailable-resource-fallback")
    }

    @MainActor
    func testDashboardShowsFullRecommendationAndUsesSemanticDestination() throws {
        let app = app(mode: "unlocked")
        app.launch()

        let action = element("dashboard.action.retirement-1.open", in: app)
        // The recommendation sits below the level and milestone cards on an
        // iPhone-sized screen. Exercise the same scroll a user needs before
        // asserting the row's complete accessible label.
        for _ in 0..<4 where !action.exists {
            app.swipeUp()
        }
        XCTAssertTrue(action.waitForExistence(timeout: 3))
        XCTAssertTrue(action.label.contains(
            "Review whether increasing your workplace pension contributions could improve your retirement outcome"
        ))
        XCTAssertTrue(action.label.contains(
            "This explanation must remain readable across multiple lines on a narrow mobile screen."
        ))

        action.tap()

        XCTAssertTrue(element("retirement.screen", in: app).waitForExistence(timeout: 3))
        XCTAssertFalse(element("tax-strategy.screen", in: app).exists)
    }

    @MainActor
    func testLevelWheelOpensAchievementsWithoutLeavingTheApp() throws {
        // Mirrors /m: achievements open from the level wheel — the drawer has
        // no Achievements entry.
        let app = app(mode: "unlocked")
        app.launch()

        let level = app.buttons["dashboard.level"]
        XCTAssertTrue(level.waitForExistence(timeout: 3))
        // Tap the wheel zone: the card's centre is covered by the overlapping
        // milestone nudge (faithful to /m's -9rem layout).
        level.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.25)).tap()

        XCTAssertTrue(
            element("achievements.screen", in: app).waitForExistence(timeout: 3)
        )
        // /m titles this page "Your progress" (Achievements.vue).
        XCTAssertTrue(app.staticTexts["Your progress"].waitForExistence(timeout: 3))
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

        // Mirrors /m: Report a problem lives in the Fyn chat header, not the
        // drawer.
        let openFyn = app.buttons["fyn.open"]
        XCTAssertTrue(openFyn.waitForExistence(timeout: 3))
        openFyn.tap()

        let reportProblem = app.buttons["fyn.report"]
        XCTAssertTrue(reportProblem.waitForExistence(timeout: 3))
        reportProblem.tap()

        let fynScreen = element("fyn.screen", in: app)
        let bugReportScreen = element("bug-report.screen", in: app)
        XCTAssertTrue(bugReportScreen.waitForExistence(timeout: 3))
        XCTAssertFalse(fynScreen.exists)
        XCTAssertFalse(reportProblem.exists)

        // A vertical-axis SwiftUI TextField is exposed by XCTest as a
        // TextField on the supported iOS 18.6 simulator, not a TextView.
        let description = app.textFields["bug-report.description"]
        XCTAssertTrue(description.waitForExistence(timeout: 3))
        description.tap()
        description.typeText("The native dashboard did not refresh.")
        // Dismiss the keyboard (it covers the review button on small devices).
        app.buttons["bug-report.keyboard-done"].tap()
        app.buttons["bug-report.review"].tap()

        XCTAssertTrue(app.staticTexts["Technical details included"].waitForExistence(timeout: 3))
        XCTAssertTrue(app.staticTexts["Conversation text, financial values, network contents, passwords, tokens and purchase signatures are not attached."].exists)
        // The review metadata rows push the submit button below the fold on
        // small devices — scroll until it is genuinely hittable (not covered
        // by the Fyn dock).
        let submit = app.buttons["bug-report.submit"]
        var submitScrolls = 0
        while !submit.isHittable, submitScrolls < 4 {
            app.swipeUp()
            submitScrolls += 1
        }
        submit.tap()

        XCTAssertTrue(
            element("bug-report.submitted", in: app).waitForExistence(timeout: 3)
        )
    }

    @MainActor
    func testFreeSubscriptionShowsLocalizedStoreKitChoicesAndRestore() throws {
        let app = openSubscription(mode: "subscription-free")

        XCTAssertTrue(
            element("subscription.comparison", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertTrue(element("subscription.plan.free", in: app).exists)
        XCTAssertTrue(element("subscription.plan.premium", in: app).exists)
        XCTAssertTrue(
            element("subscription.feature.free.savings_account", in: app)
                .label.contains("Up to 2 bank accounts")
        )
        XCTAssertTrue(element("subscription.free", in: app).waitForExistence(timeout: 3))
        XCTAssertTrue(app.buttons["subscription.product.monthly"].label.contains("£6.99"))
        XCTAssertTrue(app.buttons["subscription.product.monthly"].label.contains("1 month"))
        XCTAssertTrue(app.buttons["subscription.product.annual"].label.contains("£59.99"))
        XCTAssertTrue(app.buttons["subscription.product.annual"].label.contains("1 year"))

        assertReachable(app.buttons["subscription.purchase"], in: app)
        assertReachable(app.buttons["subscription.restore"], in: app)
    }

    @MainActor
    func testApplePremiumSuppressesPurchaseAndOffersSystemManagement() throws {
        let app = openSubscription(mode: "subscription-apple-premium")

        XCTAssertTrue(
            element("subscription.apple-premium", in: app).waitForExistence(timeout: 3)
        )
        assertReachable(app.buttons["subscription.manage-apple"], in: app)
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
        assertReachable(app.buttons["Try again"], in: app)
    }

    @MainActor
    func testPendingPurchaseDoesNotOfferAnotherPurchaseTap() throws {
        let app = openSubscription(mode: "subscription-purchase-pending")

        let purchase = app.buttons["subscription.purchase"]
        assertReachable(purchase, in: app)
        purchase.tap()
        XCTAssertTrue(
            element("subscription.pending", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertFalse(app.buttons["subscription.purchase"].exists)
        XCTAssertTrue(app.staticTexts["subscription.message"].exists)
    }

    @MainActor
    func testVerifiedPurchaseBecomesApplePremiumOnlyAfterServerAck() throws {
        let app = openSubscription(mode: "subscription-purchase-success")

        let purchase = app.buttons["subscription.purchase"]
        assertReachable(purchase, in: app)
        purchase.tap()
        XCTAssertTrue(
            element("subscription.apple-premium", in: app).waitForExistence(timeout: 3)
        )
        XCTAssertFalse(app.buttons["subscription.purchase"].exists)
    }

    @MainActor
    func testCancelledPurchaseRemainsFreeWithoutAnError() throws {
        let app = openSubscription(mode: "subscription-purchase-cancelled")

        let purchase = app.buttons["subscription.purchase"]
        assertReachable(purchase, in: app)
        purchase.tap()
        XCTAssertTrue(element("subscription.free", in: app).waitForExistence(timeout: 3))
        XCTAssertFalse(app.staticTexts["subscription.message"].exists)
    }

    @MainActor
    func testRestoreReconcilesAndLoadsApplePremium() throws {
        let app = openSubscription(mode: "subscription-restore-success")

        let restore = app.buttons["subscription.restore"]
        assertReachable(restore, in: app)
        restore.tap()
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
        // The security card pushes Lock/Sign out below the fold on small
        // devices — scroll until each is genuinely hittable (not covered by
        // the Fyn dock). Quarter-screen drags rather than full swipes so the
        // mid-page Lock button is not scrolled straight past.
        let scroll = app.scrollViews.firstMatch
        for identifier in ["app.unlocked.lock", "app.unlocked.sign-out"] {
            let button = app.buttons[identifier]
            var scrolls = 0
            while !button.isHittable, scrolls < 8 {
                scroll.coordinate(withNormalizedOffset: CGVector(dx: 0.5, dy: 0.7))
                    .press(
                        forDuration: 0.05,
                        thenDragTo: scroll.coordinate(
                            withNormalizedOffset: CGVector(dx: 0.5, dy: 0.45)
                        )
                    )
                scrolls += 1
            }
            XCTAssertTrue(button.isHittable)
        }
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
        let signOut = app.buttons["app.unlocked.sign-out"]
        assertReachable(signOut, in: app)
        signOut.tap()
        XCTAssertTrue(app.textFields["login.email"].waitForExistence(timeout: 3))
    }

    @MainActor
    func testSettingsShowsEnabledFaceIDAfterProtectedUnlock() throws {
        let app = app(mode: "face-id-unlock-success")
        app.launch()
        XCTAssertTrue(app.buttons["app.locked.unlock"].waitForExistence(timeout: 3))
        app.buttons["app.locked.unlock"].tap()
        XCTAssertTrue(element("app.unlocked", in: app).waitForExistence(timeout: 3))
        app.buttons["navigation.open"].tap()
        XCTAssertTrue(app.buttons["navigation.settings"].waitForExistence(timeout: 3))
        app.buttons["navigation.settings"].tap()

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

        // Layout reachability must hold on every supported iPhone rather
        // than encoding the dimensions of the former iPhone 11 runner.
        for button in [primaryButton, secondaryButton, destructiveButton] {
            XCTAssertGreaterThanOrEqual(button.frame.minX, window.frame.minX)
            XCTAssertLessThanOrEqual(button.frame.maxX, window.frame.maxX)
        }
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

        // Submitting triggers a network round-trip before the server-side
        // field errors render; 3s is tight under CI/loaded-machine conditions.
        XCTAssertTrue(
            app.staticTexts["registration.firstName.error"].waitForExistence(timeout: 8)
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
            app.otherElements["login.verification.step"].waitForExistence(timeout: 3)
        )
        XCTAssertTrue(app.staticTexts["Enter verification code"].exists)
        XCTAssertTrue(app.buttons["login.verification.submit"].exists)
        XCTAssertTrue(app.buttons["login.verification.cancel"].exists)
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
        app.buttons["login.verification.submit"].tap()

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
        let viewport = app.windows.firstMatch.frame
        for _ in 0..<8 where !element.isHittable {
            if element.frame.midY < viewport.midY {
                app.swipeDown()
            } else {
                app.swipeUp()
            }
        }
        XCTAssertTrue(element.isHittable)
    }

    @MainActor
    private func openDrawerItem(_ identifier: String, in app: XCUIApplication) {
        let menu = app.buttons["navigation.open"]
        XCTAssertTrue(menu.waitForExistence(timeout: 3))
        menu.tap()
        let item = app.buttons[identifier]
        assertReachable(item, in: app)
        item.tap()
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
        // Submitting triggers a network round-trip before the verification
        // step renders; 3s is tight under CI/loaded-machine conditions.
        XCTAssertTrue(
            app.textFields["registration.verification.code"].waitForExistence(timeout: 8)
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
        XCTAssertTrue(field.waitForExistence(timeout: 5))

        // The CI simulator (GitHub's macos-26 runner) settles keyboard focus
        // noticeably slower than local hardware, especially immediately
        // after a screen transition (cold launch, in-card step swap,
        // multi-factor hand-off). Poll for the condition we actually care
        // about — hasKeyboardFocus — re-tapping between attempts in case the
        // first tap landed mid-transition. Always use `field.tap()` (never a
        // blind `.coordinate(...)` tap): once a keyboard is already on
        // screen from a previous field, a stale normalized-offset coordinate
        // can land on the keyboard itself instead of the field, silently
        // typing stray letters into whichever field is still focused rather
        // than advancing focus at all.
        //
        // `isHittable` can also false-positive here: a field can be clear of
        // every view in the app's own hierarchy yet still be visually
        // covered by the system keyboard, which XCUITest's hit-testing does
        // not treat as an occluding element since the keyboard belongs to a
        // different process. That leaves `assertReachable` satisfied on the
        // very first check — no swipe happens — while the field's on-screen
        // position is actually still behind the keyboard, so the tap lands
        // on dead keyboard chrome and does nothing (no error, no focus
        // change). Force an extra swipe on every retry (not just the first
        // attempt) so a field sitting right at the keyboard's edge gets
        // scrolled unambiguously clear of it before the next tap.
        let hasKeyboardFocus = NSPredicate(format: "hasKeyboardFocus == true")
        var focused = false
        for attempt in 0..<5 where !focused {
            if attempt > 0 {
                let keyboard = app.keyboards.firstMatch
                if keyboard.exists, field.frame.intersects(keyboard.frame) {
                    app.swipeUp()
                }
            }
            assertReachable(field, in: app)
            field.tap()
            let focusExpectation = XCTNSPredicateExpectation(
                predicate: hasKeyboardFocus,
                object: field
            )
            focused = XCTWaiter.wait(for: [focusExpectation], timeout: 2) == .completed
        }
        XCTAssertTrue(focused, "\(identifier) never gained keyboard focus")

        if secure {
            // Simulator's transient "Automatic Strong Password" AutoFill
            // overlay for adjacent password / confirm-password fields can
            // still be settling in immediately after focus is gained,
            // silently swallowing most synthesized keystrokes even though
            // the field is genuinely focused (confirmed via the
            // accessibility dump: the real field shows `Keyboard Focused`
            // with only a fragment of the typed value). Typing the whole
            // string in one synthesized burst appears to race whatever is
            // settling; type one character at a time instead. The masked
            // bullet count is the only observable signal for a secure
            // field — verify it matches what was typed and clear-and-retry
            // if characters still went missing.
            for _ in 0..<5 {
                for character in value {
                    field.typeText(String(character))
                }
                if ((field.value as? String)?.count ?? 0) == value.count { return }
                field.typeText(String(repeating: "\u{8}", count: value.count + 10))
            }
            XCTFail("\(identifier) did not accept the typed value")
        } else {
            field.typeText(value)
        }
    }

    @MainActor
    private func element(
        _ identifier: String,
        in app: XCUIApplication
    ) -> XCUIElement {
        app.descendants(matching: .any)[identifier]
    }

    @MainActor
    private func attachAcceptance(_ app: XCUIApplication, name: String) {
        let attachment = XCTAttachment(screenshot: app.screenshot())
        attachment.name = name
        attachment.lifetime = .keepAlways
        add(attachment)
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
        XCTAssertTrue(app.buttons["navigation.open"].waitForExistence(timeout: 3))
        app.buttons["navigation.open"].tap()
        XCTAssertTrue(app.buttons["navigation.settings"].waitForExistence(timeout: 3))
        app.buttons["navigation.settings"].tap()
        XCTAssertTrue(element("settings.screen", in: app).waitForExistence(timeout: 3))
        return app
    }
}
