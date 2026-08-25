# W-0035 — build-lead (fix-batch-E) → quality-lead

## Done

The one entry point for `retirement_profiles.target_retirement_income`, reaching web
and `/m`, with Fyn consolidated onto the same store.

- **Backend (inherited from an unreported predecessor, then corrected):**
  `RetirementProfileStore`, `UpdateRetirementGoalsRequest`,
  `RetirementController::updateRetirementGoals` (`:572-608`),
  `PUT /api/retirement/goals` (`routes/api.php:1048-1050`).
- **Corrected:** the store now mirrors the retirement age onto
  `users.target_retirement_age` — previously Fyn-only, and load-bearing for
  `RetirementProjectionService`, the "When you want to retire" data requirement and
  `ModuleAvailabilityProvider`.
- **Consolidated (Rule 20):** `CoordinatingAgent::handleCaptureRetirementGoals`
  (`:5758-5789`) writes through the store instead of creating the row itself.
- **Web:** `RetirementTargetCard.vue` (new), wired into `PensionList.vue`, plus the
  service method and Vuex action.
- **`/m`:** the same card and the same endpoint in
  `resources/mobile/views/modules/Retirement.vue`, following batch C's inline-edit
  pattern; `/m` now also fetches `/api/retirement/required-capital`.
- **Hardcoded `35000` removed** from `CapitalAdequacyTab.vue:323` and
  `PensionList.vue:593`; both surfaces now say when a figure was derived rather than
  chosen.

Tests: `RetirementGoalsTest` 15 passed / 42 assertions; `RetirementTargetCard.spec.js`
10 passed; `RetirementTarget.spec.js` 7 passed. Wider families 245 passed / 754
assertions, plus 101 passed / 235 assertions across onboarding and campaign. Pint clean.

## Not done, and why

- **Native (SwiftUI) does not show the derived figure or say it is derived.**
  `ios-native/Fynla/Features/Retirement/RetirementModels.swift:52-56` resolves
  `analysis.targetIncome → profile.targetRetirementIncome → nil` — exactly what `/m`
  did before this batch. Native displays a *stated* target correctly today and sets one
  through Fyn, which now writes through the shared store. The missing piece is the
  derived-figure caption. Left because native is a separate codebase with its own
  release cadence and the change needs a new client call, model and view that cannot be
  built or run from this environment. Recommend a separate board item.
- **No live browser verification, no live Monte Carlo test.** Per the dispatch, Rule
  14's loop is the persona tester's.

## What you need that isn't obvious from the artefacts

- The **whole backend was already in the tree, unreported and untested by anyone**,
  when this batch started. It was verified green before being extended (11 passed). If
  you are auditing "who wrote what", the store, the request, the controller method, the
  route and the original 11 tests are the predecessor's; every frontend line, both
  backend corrections and the four added tests are this batch's.
- **`routes/api.php` is modified** and was not on the dispatch's file list.
- **The propagation to required capital / projections / decumulation / capital adequacy
  / Monte Carlo is structural, not re-tested here.** They all read
  `RequiredCapitalCalculator`'s `required_income`, which *is* tested
  (`calculated`/90000 → `profile`/55000). Consumers:
  `RetirementProjectionService.php:255,380`, `RetirementIncomeService.php:74,162`,
  `RetirementAgent.php:121`.
- **A behaviour change worth your attention:** Fyn no longer fabricates a `current_age`
  of 30 for a user with no date of birth — it returns a validation error and asks.
  `PensionProjector::getCurrentAge()` prefers `current_age` over the date of birth, so
  the old default silently shifted every projection. No test covered that path before;
  one does now.
- **`resources/js/components/__tests__/UserProfile/FamilyMembers.spec.js` has 3
  failures in this tree.** They are another batch's uncommitted work
  (`FamilyMembers.vue`, `FamilyMemberFormModal.vue`, `FamilyMembersController.php`,
  `StoreFamilyMemberRequest.php`, `FamilyMember.php` are all modified). Untouched here.

## Assumptions I made

- **Assumption:** the retirement module screen (`/net-worth/retirement`) is the intended
  "module screen" in the acceptance, and `/settings/personal` is deliberately excluded —
  adding a `users.target_retirement_income` column or a second profile-page form would
  be the second entry point the acceptance forbids.
- **Assumption:** `/m` getting a real inline edit is correct rather than deferring to
  Fyn. Based on batch C having landed exactly that pattern at
  `PersonalInformation.vue:95-140`, writing to the same endpoint and validator as
  desktop. If CSJ intends `/m` to stay read-only for module data, this is the change to
  revisit.
- **Assumption:** suppressing the green/red verdict colour on "Projected Gross Income"
  when no target is set is an improvement, not a regression. It previously meant "beats
  the invented £35,000".

## Surfaces covered / not covered

- **web** — covered: entry point, display, derived-figure caption.
- **/m** — covered: entry point, display, derived-figure caption.
- **ios** — display of a stated target already works and is unchanged; setting works via
  Fyn onto the shared store; the derived-figure caption is **not** covered. Named above.
