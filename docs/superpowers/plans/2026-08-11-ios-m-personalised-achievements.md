# Personalised Achievements Across `/m` and iOS Implementation Plan

> **For Codex:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task, with test-first RED/GREEN evidence and review after every task.

**Goal:** Replace the flat achievement catalogue with one canonical, server-owned, per-user achievement and milestone contract that explains what was earned and when, exposes meaningful progress only when verified inputs exist, and provides the same safe next action to `/m` and native iOS.

**Architecture:** Laravel owns achievement state, milestone provenance, progress calculations, and semantic navigation. The clients render that contract without recreating eligibility or route logic. Existing response fields remain additive-compatible during rollout, while both clients switch to `state`, `provenance`, `progress`, and `next_action.destination`. Locked or inapplicable items carry no invented progress. Existing financial values stay presentation-only and never enter semantic destination parameters.

**Tech Stack:** Laravel 11/PHP/Pest, Vue 3/Vitest, SwiftUI/Swift Testing/XCUITest, installed Google Chrome through the Chrome connector, Xcode iPhone 16 Pro iOS 18.6 simulator.

**Approved scope:** This is the presentation half of the approved final phase in `docs/superpowers/specs/2026-08-09-ios-m-parity-debugging-design.md`. The remaining cross-client parity and closure audit is PR7, not part of this branch.

---

## Contract invariants

- The server is the only authority for `earned`, `in_progress`, `locked`, and `inapplicable` state.
- Earned achievements expose a verified ledger or milestone provenance and the canonical event timestamp.
- In-progress achievements expose a progress object only when current and target values can be calculated from canonical records.
- Locked and inapplicable achievements expose `progress: null`; clients must never infer a percentage.
- Every contextual action uses `App\Constants\GateRoutes::destination()` and carries identifiers/enums only, never financial values.
- `/m` resolves `next_action.destination` through `resources/mobile/navigation/semanticDestinations.js`.
- iOS resolves the same destination through `SemanticDestinationResolver`; legacy `route` remains a temporary fallback only.
- The same authenticated response fixture must decode and render on both clients.

### Canonical additive shapes

```json
{
  "key": "data_savings_account",
  "title": "Added savings details",
  "description": "You started building your savings picture.",
  "state": "earned",
  "earned": true,
  "earned_at": "2026-08-11T09:00:00Z",
  "provenance": {
    "kind": "point_award",
    "event": "data:savings_account:first",
    "occurred_at": "2026-08-11T09:00:00Z"
  },
  "progress": null,
  "next_action": null
}
```

```json
{
  "key": "net_worth:25000",
  "group": "Wealth",
  "title": "Net worth £25,000",
  "steps": "Add to your savings, investments or pension — you’re £5,000 away.",
  "state": "in_progress",
  "progress": {
    "current": 20000,
    "target": 25000,
    "percent": 80,
    "label": "£20,000 of £25,000"
  },
  "next_action": {
    "label": "Review net worth",
    "destination": {
      "screen": "net_worth",
      "params": {},
      "fallback": "dashboard"
    }
  },
  "route": "m-net-worth"
}
```

## Task 1: Build the canonical Laravel presentation contract

**Files:**

- Create: `app/Services/Mobile/AchievementPresentationService.php`
- Modify: `app/Http/Controllers/Api/V1/Mobile/MobileAchievementsController.php`
- Modify: `app/Services/Mobile/MilestoneDetectionService.php`
- Modify: `tests/Feature/Mobile/MobileAchievementsTest.php`
- Modify: `tests/Feature/Mobile/MilestoneDetectionServiceTest.php`
- Create: `tests/Unit/Services/Mobile/AchievementPresentationServiceTest.php`

- [ ] **Step 1: Write failing contract tests**

Cover separate users with different ledgers, earned provenance/timestamps, a canonical in-progress net-worth item, nullable progress when aggregates are unavailable, locked/inapplicable no-progress states, and semantic destinations free of financial parameters. Assert legacy `earned`, `achieved`, and `route` keys remain compatible.

- [ ] **Step 2: Run the focused tests and capture RED**

Run:

```bash
./vendor/bin/pest tests/Feature/Mobile/MobileAchievementsTest.php tests/Feature/Mobile/MilestoneDetectionServiceTest.php tests/Unit/Services/Mobile/AchievementPresentationServiceTest.php
```

Expected: failure because canonical state, provenance, progress, and destination fields do not exist.

- [ ] **Step 3: Implement the smallest server-owned presentation layer**

Move badge shaping out of the controller into `AchievementPresentationService`. Query only the authenticated user's `UserGamification`, `PointAward`, and `UserMilestone` records. Preserve current copy unless new state/progress wording requires a precise label. Extend upcoming milestone shaping with stable keys, explicit state, verified progress, semantic next actions, and legacy route fallback. Reuse `GateRoutes::destination()`; do not send balances or targets in its `params`.

- [ ] **Step 4: Re-run focused tests and capture GREEN**

Run the same Pest command. Expected: all focused tests pass.

- [ ] **Step 5: Run architecture/security regressions**

Run:

```bash
./vendor/bin/pest tests/Architecture/GateRoutesTest.php tests/Feature/AI/ContextualConversationContractTest.php tests/Feature/Contracts/ClientCompatibilityContractTest.php
```

Expected: all pass; the new contract does not weaken destination allowlisting or client authority rules.

- [ ] **Step 6: Review Task 1 against this brief**

Reject duplicated client-facing eligibility logic, cross-user leakage, unbounded queries, fabricated progress, or financial values inside destinations. Fix findings under failing tests and repeat focused verification.

## Task 2: Render the canonical contract on `/m`

**Files:**

- Modify: `resources/mobile/views/Achievements.vue`
- Create: `resources/mobile/views/__tests__/Achievements.spec.js`
- Modify: `resources/mobile/navigation/__tests__/semanticDestinations.spec.js` only if the canonical destination exposes a previously untested existing screen

- [ ] **Step 1: Write failing `/m` component tests**

Mount the real view with mocked API responses. Assert badge-shaped presentation, earned reason/date, in-progress bar and accessible value, contextual next-action navigation through `resolveMobileDestination`, no progress for locked/inapplicable entries, and no fallback to raw route-name inference when a semantic destination exists.

- [ ] **Step 2: Run the focused Vitest suite and capture RED**

Run:

```bash
npx vitest run resources/mobile/views/__tests__/Achievements.spec.js resources/mobile/navigation/__tests__/semanticDestinations.spec.js
```

Expected: failure because the current view renders flat cards, omits progress/provenance, and pushes legacy route names.

- [ ] **Step 3: Implement the `/m` consumer**

Render earned badges with an explicit badge emblem and accessible state label. Render progress only for `state === 'in_progress'` with a non-null progress object. Render the server action label and resolve its destination with the shared semantic resolver. Retain a safe legacy route fallback during additive rollout. Do not calculate state, eligibility, balances, targets, or percentages in Vue.

- [ ] **Step 4: Re-run Vitest and mobile build**

Run:

```bash
npx vitest run resources/mobile/views/__tests__/Achievements.spec.js resources/mobile/navigation/__tests__/semanticDestinations.spec.js
npm run build:mobile
```

Expected: focused tests and production mobile build pass.

- [ ] **Step 5: Review Task 2 against this brief**

Reject duplicated state rules, direct trust in arbitrary paths, card-only achievement visuals, inaccessible progress, or hidden action labels. Fix under failing component tests and repeat verification.

## Task 3: Render the identical contract in native iOS

**Files:**

- Modify: `ios-native/Fynla/Features/Achievements/AchievementsModels.swift`
- Modify: `ios-native/Fynla/Features/Achievements/AchievementsView.swift`
- Modify: `ios-native/FynlaTests/AchievementsModelsTests.swift`
- Modify: `ios-native/FynlaTests/AchievementsModelTests.swift` only if model behavior needs coverage
- Modify: `ios-native/FynlaTests/Fixtures/Achievements/summary.json`

- [ ] **Step 1: Write failing Swift contract tests**

Extend the shared summary fixture with earned, in-progress, locked, and inapplicable states. Assert provenance, nullable progress, and semantic destination decoding. Assert unknown destinations fall back safely through the existing resolver.

- [ ] **Step 2: Run focused iOS tests and capture RED**

Run:

```bash
xcodebuild test \
  -project ios-native/Fynla.xcodeproj \
  -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' \
  -only-testing:FynlaTests/AchievementsModelsTests \
  -only-testing:FynlaTests/AchievementsModelTests \
  CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO
```

Expected: decoding/assertion failure because native models do not expose the new contract.

- [ ] **Step 3: Implement the native consumer**

Add strongly typed state, provenance, progress, and next-action models. Render a native badge emblem, earned reason/date, accessible `ProgressView` only for canonical in-progress data, and the server-provided contextual action. Route through `SemanticDestinationResolver.route(for:legacyPath:)`; delete the view-local route-name switch once the resolver covers the contract.

- [ ] **Step 4: Re-run focused tests and production build**

Run the focused test command, then:

```bash
xcodebuild build \
  -project ios-native/Fynla.xcodeproj \
  -scheme Fynla-Production \
  -destination 'generic/platform=iOS Simulator' \
  CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO SWIFT_TREAT_WARNINGS_AS_ERRORS=YES
```

Expected: tests and warning-as-error build pass.

- [ ] **Step 5: Review Task 3 against this brief**

Reject native eligibility/progress calculations, route duplication, inaccessible controls, or visual semantics that diverge from `/m`. Fix under failing Swift tests and repeat verification.

## Task 4: Prove parity with seeded user journeys and closure evidence

**Files:**

- Modify: `ios-native/Fynla/Testing/AchievementsUITestSupport.swift`
- Modify: the existing achievements journey in `ios-native/FynlaUITests/FynlaUITests.swift` or create a focused PR6 journey there
- Create: `tests/E2E/mobile/achievements-personalisation.spec.js`
- Modify: `tests/E2E/helpers` or seed helpers only where required for a deterministic authenticated achievements persona
- Create: `docs/superpowers/evidence/2026-08-11-pr6-personalised-achievements.md`

- [ ] **Step 1: Write failing user-journey assertions**

Use a deterministic persona with at least one earned ledger badge, one earned milestone, one verified in-progress milestone, and one locked/inapplicable item. Assert equivalent visible copy/state/action on both surfaces. Browser config must launch installed Google Chrome (`channel: 'chrome'`); never bundled Chromium.

- [ ] **Step 2: Run the isolated journeys and capture RED**

Run the focused XCUITest and the focused Chrome-channel E2E. Expected: failure until support fixtures/selectors and canonical rendering are complete.

- [ ] **Step 3: Complete only the acceptance support required by the journeys**

Add deterministic fixtures/seed setup and stable accessibility/test identifiers. Do not add production-only shortcuts or bypass authentication/authorization.

- [ ] **Step 4: Run the visible iOS Simulator loop**

Build and launch `Fynla-Staging` in the already configured iPhone 16 Pro iOS 18.6 simulator. Navigate as the seeded user through Achievements and Milestones, open a next action, return, inspect state/date/progress, rotate or exercise Dynamic Type where practical, record any issue, add a failing regression test, fix it, and repeat until green.

- [ ] **Step 5: Run the installed-Google-Chrome `/m` loop**

Use the Chrome connector and the same seeded user. Sign in through the established local `/m` route, repeat the equivalent achievement/milestone/action journey, inspect mobile viewport and accessibility semantics, record every issue, add a failing regression test, fix it, and repeat until green. Chrome evidence is required; `curl` or bundled browser output is not acceptance evidence.

- [ ] **Step 6: Run PR6 regression gates**

Run:

```bash
./vendor/bin/pest tests/Feature/Mobile tests/Unit/Services/Mobile tests/Architecture/GateRoutesTest.php tests/Feature/AI/ContextualConversationContractTest.php tests/Feature/Contracts/ClientCompatibilityContractTest.php
npx vitest run resources/mobile/views/__tests__/Achievements.spec.js resources/mobile/navigation/__tests__/semanticDestinations.spec.js
npm run build:mobile
xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' -skip-testing:FynlaTests/StoreKitTestTests CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO
xcodebuild build -project ios-native/Fynla.xcodeproj -scheme Fynla-Production -destination 'generic/platform=iOS Simulator' CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO SWIFT_TREAT_WARNINGS_AS_ERRORS=YES
git diff --check
```

Expected: all pass. Capture command, result, surface, persona, route, and any fixed-loop issue in the evidence file.

- [ ] **Step 7: Run final whole-branch review**

Review the complete diff against the approved design and this plan. Confirm same user/state/action on Laravel, `/m`, and iOS; no authoritative financial context from clients; no unrelated changes; and explicit PR7 deferral for the full parity audit.

## Task 5: Publish and merge PR6, then start PR7

- [ ] **Step 1: Verify worktree and diff**

Run `git status --short --branch`, `git diff --check`, and inspect every changed file.

- [ ] **Step 2: Commit intentionally**

Use a single coherent commit message such as `feat: personalise achievements across mobile clients` unless review fixes require a clearly separate follow-up commit.

- [ ] **Step 3: Push and open the ready PR**

Include RED/GREEN evidence, backend/frontend/iOS commands, installed-Chrome and simulator journeys, compatibility notes, and the explicit PR7 follow-on.

- [ ] **Step 4: Wait for every required check**

Do not merge on partial green. Diagnose any failure, add a regression test where applicable, push the fix, and wait for the complete check set again.

- [ ] **Step 5: Merge PR6 and immediately create the isolated PR7 worktree/branch**

Fast-forward/merge only after all required checks pass. Start PR7 from the new `origin/dev`; PR7 owns the complete cross-client route, calculation, copy, accessibility, regression, and traceability closure audit.
