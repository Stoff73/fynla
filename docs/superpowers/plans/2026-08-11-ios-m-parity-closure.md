# iOS and `/m` parity closure implementation plan

> **Execution requirement:** Follow this plan test-first, record every failed user loop and its repair, and do not merge until the installed-Google-Chrome journey and the same native journey in a fresh iPhone-simulator CI host are green on the canonical contract. A local run may remain explicitly pending only when Xcode diagnostics prove that CoreSimulator failed before Fynla launched; that host failure must never be reported as a passing native journey.

**Goal:** Close PR7 by reconciling M-01 through M-34 across Laravel, `/m`, native iOS, and the relevant desktop handoff boundaries, replacing the stale migration ledger with current, machine-checked evidence.

**Architecture:** Laravel remains the authority for existing financial facts, calculations, ownership, entitlements, contextual rehydration, and semantic destinations. The clients render server contracts and submit identifiers or proposed changes only. PR7 adds a durable traceability gate plus current user-style route and high-risk calculation journeys; defects discovered by either journey are fixed at their owning boundary and protected by a focused regression.

**Surfaces:** Laravel/Pest, Vue/Vitest, installed Google Chrome at 390x844, Swift/XCTest/XCUITest, visible iPhone 16 Pro simulator.

---

## Task 1: Replace the stale package ledger with the approved M-01–M-34 contract

**Files:**
- Modify: `tests/Architecture/ClientParityLedgerTest.php`
- Modify: `docs/architecture/client-parity-ledger.md`

1. Change the architecture test first so it requires exactly M-01 through M-34, complete Laravel/`/m`/iOS evidence cells, explicit status, and the shared authority invariants. Run it and retain the expected failure against the July ledger.
2. Replace the obsolete package-status matrix with the current 34-item closure matrix. Keep platform-specific boundaries explicit rather than inventing equivalence where a feature is intentionally native-only or a secure web handoff.
3. Run `./vendor/bin/pest tests/Architecture/ClientParityLedgerTest.php` and require green.

## Task 2: Add the current installed-Chrome `/m` closure journey

**Files:**
- Create: `tests/E2E/mobile/parity-closure.spec.js`
- Modify if a failure proves it necessary: `app/Http/Controllers/TestSupport/E2EController.php`
- Modify only the owning production component/navigation/service exposed by a failing assertion.

1. Add a failing mobile-Chrome test that creates one Premium E2E identity with the projection and achievement contracts enabled, enters `/m/app/dashboard`, and traverses every shared drawer destination through user-visible controls.
2. On every route assert the canonical heading/landmark, no horizontal clipping at 390x844, no failed 5xx API response, and no browser runtime error.
3. Reconcile high-risk values within the same journey: personalised achievement states, retirement age bands and 4.7% withdrawal assumption, recorded-versus-projected Net Worth copy, persisted forecast edit/reset, and semantic action routing.
4. Run only through installed Google Chrome: `PLAYWRIGHT_CHROME_CHANNEL=chrome npx playwright test tests/E2E/mobile/parity-closure.spec.js --project=mobile-chrome`.
5. For every red result, identify the owning layer, add the smallest focused regression, implement the fix, and rerun the exact journey until green.

## Task 3: Add the current visible-simulator closure journey

**Files:**
- Modify: `ios-native/FynlaUITests/FynlaUITests.swift`
- Modify only the owning Swift model/view/navigation implementation exposed by a failing assertion.

1. Add a failing `testPR7ParityClosureJourney` that launches the established unlocked server-shaped test composition and navigates every shared drawer destination on the iPhone-sized UI.
2. Assert each destination's stable screen identity, the shared drawer order/labels, canonical dashboard recommendation routing, contextual/history behavior, achievements, portfolio comparators, retirement projections, recorded/projected Net Worth distinction, Settings/Subscription, and native bug reporting.
3. Run the focused journey on the already-visible iPhone 16 Pro simulator. Repeat at the largest accessibility Dynamic Type size and restore the user's original `large` setting. If the local CoreSimulator transport fails before app launch on both a clean current-runtime device and a previously known-good device, retain the exact diagnostics and require both the normal and largest-Dynamic-Type journeys on fresh macOS CI before merge.
4. For every red result, add a focused unit/UI regression, implement at the owning boundary, and rerun until green.

## Task 4: Reconcile server authority, calculations, security, and client rendering

**Files:**
- Existing focused Laravel suites under `tests/Feature/AI`, `tests/Feature/Mobile`, module API tests, portfolio/projection service tests, and architecture boundaries.
- Existing `/m` suites under `resources/mobile/**/__tests__`.
- Existing Swift suites under `ios-native/FynlaTests`.
- Add or modify a focused regression only when the closure audit finds an uncovered defect.

1. Run the contextual-Fyn ownership/rehydration/identifier-only/security tests, semantic destination contracts, freemium store boundaries, joint-owner Net Worth/detail contracts, ISA contribution and allowance contracts, protection presentation, canonical portfolio look-through/drift contracts, and retirement/forecast/history contracts.
2. Run the matching Vue and Swift presentation/model/navigation suites and compare their copy, unavailable states, provenance, and values to the Laravel contract.
3. Treat any mismatch as red. Fix it test-first in the authoritative service or the client renderer, then rerun both clients' affected suites.

## Task 5: Record evidence and run the complete regression gate

**Files:**
- Create: `docs/superpowers/evidence/2026-08-11-pr7-ios-m-parity-closure.md`
- Update: `docs/architecture/client-parity-ledger.md` with final commands/result references only after fresh runs.

1. Record the exact persona, route, command, failure, root cause, fix, rerun, screenshot/result-bundle path, and M-ID mapping for every loop.
2. Run the PR7 Laravel selection and architecture gate, all `resources/mobile` Vitest tests, `npm run build:mobile`, native unit/UI gates excluding only the documented unavailable StoreKit system-session fixture, and `Fynla-Production` with Swift warnings as errors.
3. Run `git diff --check`, range lint/format checks, and independent whole-branch review. Resolve every Critical/Important finding and assess Minor findings explicitly.
4. Commit, push, open PR7, monitor every GitHub check to green, merge, and verify the merge commit on `dev`.
