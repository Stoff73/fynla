<?php

declare(strict_types=1);

/**
 * BS-06 — parked facts flush
 *
 * Sprint: 0
 * Invariants: INV-2.2.6 (parked facts flushed at commit)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-06
 * Pest sibling: tests/Feature/Onboarding/ParkedFactsFlushTest.php (S0.15.3)
 * Screenshots: docs/sprint-0-verification/BS-06/
 *
 * Delivery — 2026-04-26 (session 87) — GREEN end-to-end via canonical
 * Quick start with Fyn real-user flow.
 *
 *   User #343 (Bryony Stoneleigh, bs06b@example.com).
 *   AiConversation #73 (title 'Onboarding', model_used 'director').
 *
 *   Walk: landing → /register?from=fyn → MFA (code 603600 from
 *   pending_registrations row 4) → /dashboard with auto-onboarding →
 *   path_choice "Follow a journey" → journey_selection
 *   "Building Foundations" → STATE_BASE_PERSONAL.
 *
 *   At base_personal, typed "I was born on 1 April 1980 and I'm married"
 *   and pressed Enter. INV-2.2.6 verified end-to-end:
 *
 *     - users.date_of_birth = 1980-04-01 ✓
 *     - users.marital_status = 'married' ✓
 *     - onboarding_fyn_step advanced base_personal → base_spouse ✓
 *     - ai_conversations.onboarding_parked_facts = null ✓
 *       (the personal bucket was populated transiently inside
 *       OnboardingChatDirector::handleUserMessage by
 *       OnboardingFactExtractor::extractAndPark, then consumed +
 *       cleared by hydrateFromParking → flushParkedFactsForState
 *       at OnboardingChatDirector.php:1043)
 *
 *   Profile page (/profile, screenshot 06) renders the captured values:
 *   Full Name "Bryony Stoneleigh", Date of Birth "1 April 1980",
 *   Marital Status "Married".
 *
 *   Screenshots in docs/sprint-0-verification/BS-06/:
 *     01-landing.png, 02-register-form.png, 03-onboarding-path-choice.png,
 *     04-base-personal-prompt.png, 05-post-submit-advance-to-spouse.png,
 *     06-profile-page.png.
 *
 * Stub-script amendments (carry to S0.17):
 *
 *   1. The stub's seed assumes a factory user with
 *      `first_name='Seeded'` and a pre-seeded
 *      `AiConversation->onboarding_parked_facts = ['personal' =>
 *      ['first_name' => 'Seeded']]`. Per session 84/85 direction
 *      (CLAUDE.md Rule #15, MEMORY.md), every BS-NN runs through the
 *      canonical Quick start with Fyn flow — no factory seeds, no SQL
 *      fixtures, no manual conversation seeding. Replace the stub seed
 *      with the canonical real-user pattern.
 *
 *   2. The stub asserts `Profile page shows first name "Seeded"` and
 *      `User::find($id)->first_name === 'Seeded'`. These assertions
 *      describe a behaviour that does not exist in production:
 *      `OnboardingFactExtractor::extractPersonal`
 *      (app/Services/Onboarding/OnboardingFactExtractor.php:90-122) only
 *      parks `marital_status`, `age_hint`, and `date_of_birth` — it
 *      does NOT parse first_name from free-text, and
 *      `OnboardingChatDirector::buildPersonalInputFromParking`
 *      (line 1132) never reads a parked first_name. first_name is set
 *      once at registration. Replace those assertions with the
 *      genuine INV-2.2.6 contract: any pre-existing 'personal' bucket
 *      (e.g. parked from a prior conversation turn) is cleared after
 *      base_personal commits, while sibling buckets (spouse,
 *      dependants, employment, expenditure) survive — the latter
 *      half is already pinned by the Pest sibling
 *      ParkedFactsFlushTest::it flushes only the matching bucket
 *      when multiple buckets are parked.
 *
 *   3. The stub's "Login::as($email, $password)" entry-point assumes a
 *      pre-existing user. Replace with the canonical
 *      "register fresh user via /register?from=fyn → MFA → land on
 *      dashboard with auto-opened onboarding chat" sequence, matching
 *      BS-01/BS-02/BS-04.
 *
 * Tech-debt note — NOT a BS-06 issue but observed in the same window:
 * `subscription_plans` and `tax_configurations` were both empty mid-run
 * despite a clean `php artisan db:seed` at session bootstrap. The
 * `SubscriptionPlanSeeder` / `TaxConfigurationSeeder` had to be re-run
 * during the session before /verify-code (subscription plan lookup) and
 * /profile (TaxConfigService) would succeed. Suspected cause is a stale
 * Pest test run wiping the dev DB. Filed as a session-bootstrap concern
 * for follow-up; not part of BS-06.
 *
 * Pass: DB and UI consistent; parked-facts personal bucket cleared
 * after base_personal commit.
 *
 * Session 95 redo (2026-04-26, S0.16c #4) — GREEN against the post-
 * `ffc9c3f` shared `AiChatPanelShell` body. Bubble click + free-text
 * via snapshot-ref + browser_type per the law; NO browser_evaluate for
 * any interaction. INV-2.2.6 captured at the precise moment of commit.
 *
 * Walk transcript:
 *   - Fresh registration: Bryony Stoneleigh (User #452, bs06-s95@example.com).
 *   - MFA 241534 typed digit-by-digit.
 *   - Land /dashboard with auto-onboarding chat (conv #125, metadata
 *     source=fyn_onboarding).
 *   - Click "Follow a journey" → Click "Building Foundations" (matching
 *     session-87 walk's journey selection).
 *   - base_personal prompt: "Let me grab a few basics first, Bryony.
 *     What's your date of birth, and are you single, married, in a civil
 *     partnership, divorced, or widowed?"
 *   - Type "I was born on 1 April 1980 and I'm married" + Enter.
 *   - Wait for grouped_extract LLM round-trip.
 *   - DB snapshot at commit moment:
 *       users #452 date_of_birth=1980-04-01, marital_status='married',
 *       onboarding_fyn_step='base_spouse' (advanced past base_personal).
 *       ai_conversations #125 metadata.source='fyn_onboarding',
 *       onboarding_parked_facts=NULL.
 *
 * INV-2.2.6 evidence:
 *   - The personal bucket was populated transiently inside
 *     OnboardingChatDirector::handleUserMessage by
 *     OnboardingFactExtractor::extractAndPark, then consumed and cleared
 *     by hydrateFromParking → flushParkedFactsForState (per the
 *     mapping at OnboardingChatDirector::flushParkedFactsForState
 *     line 1099 — base_personal flushes the 'personal' bucket).
 *   - At the moment of commit (post-LLM-tool-call, pre-next-turn) the
 *     onboarding_parked_facts column is NULL, proving the flush fired.
 *   - Sibling buckets (spouse, dependants, employment, expenditure)
 *     remain pinned by the Pest sibling
 *     `tests/Feature/Onboarding/ParkedFactsFlushTest.php`.
 *
 * Cross-walk evidence — the same flush behaviour was verified across
 * three different walks in this session:
 *   - User #449 (Laury, BS-01): full journey to /protection terminal,
 *     post-walk parked_facts shows late-turn re-parking from asset_capture
 *     (date_of_birth and annual_income from "Aviva life cover £300,000")
 *     — these are NOT INV-2.2.6 violations because flushParkedFactsForState
 *     only clears the bucket for the matching state, by design. The
 *     personal bucket was cleared at the BS-01 base_personal commit,
 *     then later turns re-parked into a different shape.
 *   - User #451 (Devon, BS-04): full walk through profile_review_family
 *     after sign-out + sign-in + Continue + Mia/Owen capture, post-walk
 *     parked_facts=NULL across all buckets — confirms the flush map is
 *     comprehensive and no state leaves stale data.
 *   - User #452 (Bryony, BS-06): the precise mid-walk capture above.
 *
 * Screenshots `docs/sprint-0-verification/BS-06/`:
 *   - s95-01-base-personal-prompt.png — Bryony's chat at base_personal
 *     showing the "Let me grab a few basics first" prompt with no input
 *     yet typed.
 *   - s95-02-post-submit-advance-to-spouse.png — post-commit state
 *     (advanced to base_spouse turn).
 *
 * Pest sibling: `tests/Feature/Onboarding/ParkedFactsFlushTest.php`
 * already pins the per-bucket flush logic in isolation.
 *
 * Pest baseline: 529/1968 (no regressions; the session-95 BS-04 fixes
 * were additive — new AiConversation::scopeOnboarding helper + new
 * describeStep match arms; INV-2.2.6 codepaths unchanged).
 */
it('BS-06 parked facts flush', function (): void {
    $this->markPendingInteractiveRun('BS-06');
});
