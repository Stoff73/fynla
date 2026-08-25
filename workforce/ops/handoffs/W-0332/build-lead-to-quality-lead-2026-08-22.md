# W-0332 — build-lead → quality-lead

## Done

- `resources/mobile/views/modules/Savings.vue`: `totalCash` routed to
  `calculateTotalUserShare` from the shared `resources/js/utils/ownership.js`.
- Account rows now show the viewer's share as the headline with "Your 30.00% of
  £20,000" beneath, mirroring the `/m` investment list exactly.
- `tests/frontend/mobile/SavingsOwnershipShare.test.js` — 4 tests, green, all four
  red under the restored defect.

## Not done, and why

- **No browser verification on `/m`.** Two reasons, both external: the shared
  Playwright tab was held by another agent, and `/m` serves `public/m-build/` and
  never Vite, so the change is invisible until the bundle is rebuilt. **Team-lead
  owns the rebuild** — asked, not built.

## What you need that isn't obvious from the artefacts

- This is the **mirror image** of W-0274, not a copy of it. Web charged the co-owner
  the PRIMARY owner's share; `/m` charged both spouses the WHOLE balance. A reviewer
  who checks `/m` for the web symptom will find nothing wrong.
- `/m` is an isolated bundle, but it **does** reach `resources/js/utils/ownership.js`
  by relative path — four other `/m` screens already do (investment list, property
  detail, savings account detail, investment account detail). This is not a new
  cross-bundle dependency.
- `/m`'s savings account DETAIL screen was already correct, so before this fix `/m`
  contradicted itself one tap apart. Worth checking both screens together.
- The persona's joint accounts are all 50/50, so **`/m` will look right on this
  household either way** for a single account. The test fixture is 70/30 precisely
  because of that.

## Assumptions I made

- **Assumption:** the row headline should be the viewer's share and the full balance
  should appear as context beneath, because that is what the `/m` investment list
  does. Nobody specified it for savings. If the intended `/m` design is
  full-balance-headline, the total must still be the share — that part is not
  optional.
- **Assumption:** `emergency_fund_target.target_amount` continues to come from the
  server and is not affected by the share change. I did not touch it.

## Surfaces covered / not covered

- **Covered:** `/m` only.
- **Not covered:** iOS native. `ios-native/` has its own savings screen and was not
  examined; if it sums a balance client-side it may carry the same fault.
