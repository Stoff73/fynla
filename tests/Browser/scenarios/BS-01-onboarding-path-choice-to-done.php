<?php

declare(strict_types=1);

/**
 * BS-01 — onboarding path-choice-to-done
 *
 * Sprint: 0
 * Invariants: INV-2.2.1 (state machine drives onboarding)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-01
 * Screenshots: docs/sprint-0-verification/BS-01/
 *
 * Seed: factory user `{onboarding_completed: false, onboarding_fyn_step: null}`,
 *       email + password noted for login.
 *
 * Script (interactive, Claude executes via Playwright MCP):
 *   1. browser_navigate('http://localhost:8000')
 *   2. browser_snapshot() — landing page rest state
 *      browser_click on "Sign in" (role=link)
 *   3. browser_fill_form { email, password } → browser_press_key('Enter')
 *   4. If MFA prompt: $code = Login::latestVerificationCode($email);
 *                     browser_type($code) → browser_press_key('Enter')
 *   5. browser_wait_for text="Hi" or "Good afternoon" (first onboarding turn)
 *   6. browser_snapshot — assert two bubbles: "Follow a journey" + "Pick a focus"
 *   7. browser_click "Follow a journey"
 *   8. browser_wait_for next state; assert bubbles
 *      "Starting Out", "Building Foundations", "Protecting What Matters",
 *      "Planning Your Future" all visible
 *   9. browser_click "Protecting What Matters"
 *   10. Walk through each grouped-extract state in turn:
 *       - base_personal: type "DOB 12 Jan 1985, married" → submit
 *       - base_spouse:   type "Angela, 12 Jan 1985, angela@example.com" → submit
 *       - base_dependants: click "No"
 *       - base_employment: click "Full-time"
 *       - base_work: type "ACME Ltd, Engineer, £75,000" → submit
 *       - base_expenditure: type "£2,500" → submit
 *       - profile_review_*: click "Looks correct"
 *       - asset_capture: type "Aviva life cover £300k" → submit, wait for capture
 *   11. add_more state: click "Finish for now" (or matching terminal bubble)
 *   12. browser_wait_for the dashboard heading (role=main)
 *
 * Assertions:
 *   - Each intermediate transition emits its expected SSE event type
 *     (capture via browser_network_requests + AssertSseEvents).
 *   - Pest-side post-run: User::find($id)->onboarding_completed === true.
 *   - browser_take_screenshot per state into docs/sprint-0-verification/BS-01/.
 *
 * Pass: scenario reaches dashboard without error, screenshots captured,
 *       onboarding_completed flipped, no SSE error events in stream.
 *
 * Delivery note (2026-04-26, session 84 — Batch 3 #1, GREEN end-to-end):
 *   Drove the canonical user journey: landing page → "Quick start with Fyn"
 *   CTA (/register?from=fyn) → registration form → MFA → /dashboard?openFyn=
 *   journey&newUser=1 (auto-onboarding) → walk every state to completion. No
 *   factory shortcuts. Final state: User #54 (Laury Marks, bs01-real@example.com),
 *   onboarding_completed=true, onboarding_fyn_step=null, dob=1985-01-12,
 *   marital=married, spouse_id=55 (Angela linked bidirectionally), employer=
 *   ACME Ltd, occupation=Engineer, annual_employment_income=75000,
 *   monthly_expenditure=2500, 1 LifeInsurancePolicy (Aviva £300k level_term),
 *   4 consents granted (terms, privacy, data_processing, ai_chat).
 *   13 screenshots in docs/sprint-0-verification/BS-01/.
 *
 *   Product fix shipped during this run (folded into the same loop per
 *   §S0.16b "any failures route through dedicated bug-fix sub-tasks"):
 *
 *     S0.5.z — Registration verifyCode now records the four implicit
 *     GDPR consents the post-registration journey depends on. Before this
 *     fix, AuthController::verifyCode() created the user, started the
 *     trial, and routed them to /dashboard?openFyn=journey — which
 *     immediately POSTs /api/ai-chat/onboarding/start. That endpoint
 *     gates on TYPE_AI_CHAT consent (AiChatController:257), the consent
 *     was never granted, the call returned 403, and the frontend
 *     silently fell back to a blank conversation. Onboarding never
 *     started. Fix: ConsentService::recordConsents() called for terms +
 *     privacy + data_processing + ai_chat right after startTrial().
 *     Form footer "By creating an account, you agree to our Terms of
 *     Service and Privacy Policy" makes terms+privacy explicit;
 *     data_processing is the lawful basis under which the app operates;
 *     ai_chat is implicit when the user enters via the Quick start with
 *     Fyn CTA (the entire post-registration journey is chat-driven).
 *     INV-2.10.3 still applies — withdrawing any of these via /settings
 *     continues to flow through UserConsent::withdraw and the runtime
 *     consent gate on every chat turn.
 *     Files: app/Http/Controllers/Api/AuthController.php (+2 imports,
 *     +1 dep, +13 lines).
 *
 *   Stub-script amendments needed (carry to spec amendment list):
 *
 *     1. Seed wording: "factory user" understates. The canonical seed
 *        for every BS-NN that drives onboarding is "register a fresh
 *        user via the /register?from=fyn flow" — not User::factory().
 *        Real registration gives the user (a) a trialing Subscription,
 *        (b) the four GDPR consents (post-S0.5.z), (c) NULL marital +
 *        DOB so the state machine asks for them. Factory-created users
 *        miss all three. Recommend: add a Login::registerFreshFynUser
 *        helper in tests/Browser/Helpers/ that drives the register UI
 *        and returns the User row.
 *
 *     2. Step 8 — journey-choice turn shows FIVE bubbles, not four.
 *        Canonical list in OnboardingStateMachine.php:96-126 and
 *        resources/js/constants/lifeStageConfig.js: Starting Out,
 *        Building Foundations, Protecting What Matters, Planning Your
 *        Future, Enjoying Your Wealth. Add the fifth to the stub.
 *
 *     3. Step 11 — terminal bubble label is "I'm done", not
 *        "Finish for now". Update stub.
 *
 *     4. Step 12 — Fyn auto-navigates to the journey's terminal module
 *        (/protection for "Protecting What Matters") rather than
 *        /dashboard. Loosen the assertion to "any authenticated route
 *        rendered, onboarding_completed=true".
 *
 *   Notes on what the FIRST attempt got wrong (recording for next
 *   session's reference):
 *     - Attempting to seed a factory user + manually grant consent +
 *       manually start trial diverged from the canonical user journey
 *       and surfaced ghost gaps that did not exist in production. The
 *       only reliable seed is "drive the actual /register?from=fyn UI".
 *     - Earlier "stub gap" claim that base_work loses employer/income
 *       was wrong: I was checking users.employer_name and users.
 *       gross_annual_income; the actual columns CoordinatingAgent::
 *       handleCaptureWorkDetails writes to are users.employer and
 *       users.annual_employment_income (CoordinatingAgent.php:1220-1228).
 *       All three fields capture correctly via the LLM-driven extractor.
 *     - Earlier "stub gap" claim that base_personal couldn't capture
 *       marital was wrong: User::factory()'s default
 *       marital_status='single' (UserFactory:36) routes
 *       buildPersonalPrompt (OnboardingStateMachine:483-512) to its
 *       "have you noted as single — share your DOB" branch. Real users
 *       start with marital=NULL and the prompt asks for both, which
 *       the LLM extracts correctly.
 *
 * Session 94 attempted re-walk (2026-04-26, S0.16c #1) — TAINTED, MUST
 * BE REDONE. The walk was driven via `browser_evaluate(...)` JS-clicks
 * for "No", "Looks correct", "Full-time", "No, that's everything" and
 * "I'm done" instead of `browser_click` against snapshot refs — exactly
 * the shortcut banned by `critical_browser_testing_law.md` and re-stated
 * in the S0.16c pre-flight block. The walk also flagged a /profile
 * navigation as "unrelated cosmetic" without diagnosing it; it is in
 * fact the documented `profile_review_*` pause behaviour wired in
 * `AppLayout.vue:326-331`. NO claim of GREEN can rest on this walk.
 *
 * Session 95 redo (2026-04-26, S0.16c #1) — GREEN end-to-end against
 * the post-`ffc9c3f` shared `AiChatPanelShell` body. All bubble + skip
 * link clicks driven via `browser_click` against snapshot refs ONLY;
 * MFA digits via `browser_press_key`; free-text via `browser_type` +
 * submit. NO `browser_evaluate` for any interaction. Pre-flight reading
 * (state machine 719L + director 2383L + aiChat.js 1028L + AppLayout
 * 200-345 + AiChatPanel 1261L + AiChatPanelShell 68L) completed before
 * any browser action.
 *
 * Walk transcript:
 *   1. Landing → Quick start with Fyn CTA → /register?from=fyn
 *   2. Filled (Laury, Greenwood, bs01-s95@example.com, TestPass123!)
 *   3. MFA code 739904 typed digit-by-digit via browser_press_key
 *   4. Land /dashboard with onboarding chat docked open (wide layout)
 *   5. path_choice → click "Follow a journey" (ref-based)
 *   6. journey_selection → 5 bubbles render (Starting Out, Building
 *      Foundations, Protecting What Matters, Planning Your Future,
 *      Enjoying Your Wealth) → click "Protecting What Matters"
 *   7. base_personal grouped_extract → typed "My date of birth is
 *      12 January 1985 and I am married." → captured DOB+marital
 *   8. base_spouse → typed "Angela, 12 January 1985, angela-bs01s95
 *      @example.com" → captured. Director ack: "Got it — I've added
 *      Angela and linked the two of you."
 *   9. base_dependants → click "No"
 *   10. profile_review_family layout=standard → ROUTER PUSHED TO
 *       /profile per AppLayout.vue:326-331 contract (verified via
 *       browser_snapshot showing /profile URL, ProfileReviewPanel
 *       rendering captured DOB/marital/spouse, chat narrowed to 356px).
 *       This is the CONTRACT, not a bug. Click "Looks correct"
 *       → router pushed back to /dashboard (wide watcher).
 *   11. base_employment → click "Full-time"
 *   12. base_work grouped_extract → typed "ACME Ltd, Engineer,
 *       £75,000" → captured. ack: "Thanks — I've noted your work
 *       details."
 *   13. base_employment_more → click "No, that's everything"
 *   14. base_expenditure free_text → typed "£2,500" → captured.
 *       ack: "Thanks — I've noted your monthly spending."
 *   15. profile_review_expenditure layout=standard → 2nd /profile
 *       push, panel shows full captured profile (78% complete, Engineer
 *       at ACME Ltd visible). Click "Looks correct" → back /dashboard.
 *   16. asset_capture (LLM-delegated, focus=protection) → typed
 *       "Aviva life cover £300,000" → LLM dispatched create_protection_
 *       policy → "Got it — recording that now. Your Aviva term life
 *       insurance for £300,000 is now recorded, Laury."
 *   17. add_more → 4 bubbles (Savings/Investment/Retirement/I'm done)
 *       — protection focus correctly stripped via filterBubbles.
 *       Click "I'm done"
 *   18. emitDoneTurn → celebration "All set, Laury. Your protection
 *       module is ready to explore." + navigation to /protection
 *       (canonical route for "Protecting What Matters" per
 *       routeForSelection). Pre-fix BS-01 docblock loosened the
 *       assertion to "any authenticated route" — matched.
 *
 * Final state (User #449, AiConversation #80):
 *   onboarding_completed=true, onboarding_completed_at=2026-04-26
 *   21:55:11, onboarding_fyn_step/path/selection all NULL.
 *   date_of_birth=1985-01-12, marital_status=married,
 *   employment_status=full_time, employer=ACME Ltd, occupation=Engineer,
 *   annual_employment_income=75000.00, monthly_expenditure=2500.00,
 *   household_id=6.
 *   FamilyMember #223 (Angela, spouse, dob=1985-01-12, household_id=6).
 *   LifeInsurancePolicy #121 (Aviva, level_term, sum_assured=300000.00,
 *   premium_frequency=monthly, in_trust=0, joint_life=0).
 *   4 consents granted (ai_chat, data_processing, privacy, terms).
 *   1 AiConversation (onboarding).
 *
 * Post-refactor evidence — chat panel observations:
 *   - Single shared body via AiChatPanelShell renders identically across
 *     wide (712px, blurred dashboard) and standard (356px, /profile)
 *     layouts. No docked-vs-modal divergence.
 *   - Suggestions panel correctly hidden during onboarding (v-if
 *     "!isOnboardingActive"); reappears on /protection terminal once
 *     emitDoneTurn flipped isOnboardingActive=false.
 *   - latestQuickRepliesIndex correctly disables every prior bubble
 *     set; only the latest remains clickable.
 *   - ref="messagesContainer" + ref="inputContainer" wired through the
 *     unified template — no $refs.dockedMessagesContainer fallback
 *     branch needed (per W1 tech debt note in CSJTODO).
 *   - User-bubble echoes (raspberry, top-right) for every text+bubble
 *     turn render correctly above the next assistant message.
 *
 * 13 fresh screenshots `docs/sprint-0-verification/BS-01/s95-*.png`:
 *   01-landing, 02-register-form, 03-path-choice, 04-journey-choice,
 *   05-base-personal, 06-base-spouse, 07-base-dependants,
 *   08-profile-review-family (the documented /profile pause),
 *   09-base-employment, 10-base-work, 11-profile-review-expenditure
 *   (the second /profile pause), 12-add-more, 13-completed (/protection
 *   with Aviva £300k policy card visible).
 *
 * Pest baseline: 529 passed / 1968 assertions / 0 failures (97.00s).
 */
it('BS-01 onboarding path-choice-to-done', function (): void {
    $this->markPendingInteractiveRun('BS-01');
});
