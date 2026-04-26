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
 * The next instance must read the S0.16c pre-flight block in full,
 * read the state machine + director + aiChat.js + AppLayout.vue, then
 * redrive BS-01 with `browser_click` only. Screenshots from session 94
 * (`s94-*.png`) should be deleted on the redo.
 */
it('BS-01 onboarding path-choice-to-done', function (): void {
    $this->markPendingInteractiveRun('BS-01');
});
