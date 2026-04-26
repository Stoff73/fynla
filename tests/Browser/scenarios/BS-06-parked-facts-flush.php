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
 */
it('BS-06 parked facts flush', function (): void {
    $this->markPendingInteractiveRun('BS-06');
});
