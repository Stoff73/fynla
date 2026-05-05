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
 *
 * ---------------------------------------------------------------------
 * Delivery note (2026-04-26, session 86) — BS-05 USER-VISIBLE FLOW DEFERRED.
 *
 * Driving BS-05 via the canonical Quick start with Fyn real-user flow
 * surfaced that the 4 entry-source CTAs the script assumes (Starting Out
 * → from=budgeting, Building Foundations → from=goals, Protecting What
 * Matters → from=protection, Planning Your Future → from=retirement)
 * do not exist on the landing page. The current product has only the
 * unified `/register?from=fyn` CTA on the landing page; the existing
 * `/stage/*` SEO pages route to `/register?stage={career_stage}` and feed
 * the legacy Onboarding flow, not the journey-map one. Frontend plumbing
 * to forward `from` for known journey-map keys is also missing
 * (Register.vue at lines 341-352 only handles `from === 'fyn'`;
 * aiChatService.startOnboardingStream sends body '{}' with no `from`).
 *
 * Backend half of INV-2.2.5 is complete and Pest-verified end-to-end by
 * `tests/Feature/Onboarding/EntrySourceJourneyMapTest.php` (S0.15.2):
 * the controller's `request->input('from')` lookup, the journey_map
 * config, the runtime-additive contract, the unknown/missing fallthrough
 * to STATE_PATH_CHOICE, and the per-known-key state advancement.
 *
 * CSJ direction (2026-04-26): BS-05 user-visible flow is part of the
 * Lifestyle Landing Pages workstream queued in
 * `April/April24Updates/plan/15-post-sprint-priorities-plan.md` §PSP-LS,
 * which starts only after Sprints 0-4 are GREEN. Both lifestyle and
 * campaign landing pages are built together at that point. BS-05 stays
 * markPendingInteractiveRun until the lifestyle plumbing ships.
 *
 * Required reading before re-driving BS-05: §PSP-LS + §PSP-S of
 * 15-post-sprint-priorities-plan.md. The stub script's per-page CTA
 * labels above will need updating during PSP-LS implementation to match
 * whatever lifestyle-page UX ships.
 */
it('BS-05 journey map by entry source', function (): void {
    $this->markPendingInteractiveRun('BS-05');
});
