<?php

declare(strict_types=1);

/**
 * BS-05 — journey map by entry source
 *
 * Sprint: 0
 * Invariants: INV-2.2.5 (journey mapping is config-driven)
 * Spec: April/April24Updates/spec/03-test-strategy.md §BS-05
 * Pest sibling: tests/Feature/Onboarding/EntrySourceJourneyMapTest.php (S0.15.2)
 * Screenshots: docs/sprint-0-verification/BS-05/
 *
 * Seed: none (factory users created inline per sub-scenario).
 *
 * Script (parameterised over 5 sub-cases — Claude walks all 5):
 *
 *   Sub-case 1 — protection / "Protecting What Matters":
 *     1. browser_navigate('http://localhost:8000')
 *     2. browser_click "Protecting What Matters" CTA on the landing page
 *     3. browser_wait_for signup form; complete signup (factory user).
 *     4. browser_wait_for first onboarding turn.
 *     5. browser_snapshot → docs/sprint-0-verification/BS-05/01-protection.png
 *
 *   Sub-case 2 — retirement / "Planning Your Future":
 *     repeat with the "Planning Your Future" CTA.
 *
 *   Sub-case 3 — budgeting / "Starting Out":
 *     repeat with the "Starting Out" CTA.
 *
 *   Sub-case 4 — goals / "Building Foundations":
 *     repeat with the "Building Foundations" CTA.
 *
 *   Sub-case 5 — no `from` (fallthrough):
 *     1. browser_navigate('http://localhost:8000')
 *     2. Bypass life-stage CTAs, click "Sign up" header link directly.
 *     3. Complete signup; first onboarding turn must be path_choice.
 *     4. browser_snapshot → docs/sprint-0-verification/BS-05/05-fallthrough.png
 *
 * Assertions (per sub-case 1-4):
 *   - Pest-side: User::find($id) has onboarding_fyn_path='journey' AND
 *     onboarding_fyn_selection equals the matching journey id
 *     (protection / retirement / budgeting / goals).
 *   - Pest-side: User::find($id)->onboarding_fyn_step === 'base_personal'.
 *   - Visible chat does NOT show the "Follow a journey vs Pick a focus"
 *     bubbles (those are STATE_PATH_CHOICE — skipped).
 *
 * Assertions (sub-case 5):
 *   - User::find($id)->onboarding_fyn_step === 'path_choice'.
 *   - Visible chat shows the two path-choice bubbles.
 *
 * Pass: 5 sub-scenarios all produce the correct initial state and the
 *       expected user.* columns.
 */
it('BS-05 journey map by entry source', function (): void {
    $this->markPendingInteractiveRun('BS-05');
});
