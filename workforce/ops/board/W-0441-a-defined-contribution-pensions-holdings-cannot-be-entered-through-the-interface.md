---
id: W-0441
title: A defined contribution pension's holdings cannot be entered through the interface — the Holdings tab was gated on already having holdings
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0032-cycle4-pension-holdings-entry-and-display.md
owner: build-lead
status: handoff
severity: high
surfaces: [web]
created: 2026-08-23T03:30:00Z
claimed: 2026-08-23T03:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0039, W-0121, W-0122, W-0126, W-0322, W-0324, W-0321, DCPensionHoldingsController, dcPensionHoldingsService.js, HoldingForm.vue]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

Found by `peak-earners-c4`, cycle 4, as D-17.

David's SIPP detail showed three tabs — Overview, Projections, Documents. No
Holdings tab, no Add Holding, no route to one. Three of the persona's ten holdings
(VHVG £160,018, SLXX £96,000, LGUKP £64,000) had nowhere to go, and a £320,000
pension reported a fund charge of 0.00%.

### The prior-art sweep changed the size of this substantially

`prior_art_outcome: route`, not `none`. Nearly all of it existed:

- `DCPensionHoldingsController` — `index/store/update/destroy/bulkUpdate`, routed at
  `/api/retirement/pensions/dc/{id}/holdings` (`routes/api.php:1042-1046`), every
  method already reading `App\Support\HoldingValuation` (W-0126).
- `resources/js/services/dcPensionHoldingsService.js` — `createHolding`,
  `updateHolding`, `deleteHolding` **already written**, with **zero consumers**. The
  write half of the service was dead code waiting for a caller.
- `HoldingForm.vue` already carried Units, Purchase Price, Current Price, Purchase
  Date and Ongoing Charge Figure inputs (W-0039).
- `PensionDetailInline.vue` already had a Holdings tab.

**The whole defect was one gate.** The tab was conditional on `hasHoldings`, so no
holdings meant no tab, no tab meant no way in, and no way in meant no holdings.

## Acceptance

1. [x] A defined contribution pension's holdings can be entered through the
   interface and persist, with their unit counts, prices, purchase dates and
   ongoing charge figures.
2. [x] Adding or editing a pension never destroys existing holdings — asserted with
   a fixture holding real rows.
3. [x] The fee figures stop reading 0.00% once holdings exist.
4. [x] **Browser verification on David (16).** Done — see the note below.
   **Sarah (17) has no defined contribution pension**, so there is nothing on her
   account for this item to exercise. Stated rather than claimed.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-retirement`): **Fixed, not browser-verified.**

  `PensionDetailInline.vue` — Holdings tab unconditional for defined contribution
  pensions, with an empty state, Add Holding, and per-row Edit and Delete. Writes
  per holding through the existing service, **no arrays**, so W-0322's
  `holdings: []` hazard cannot arise on this path.

  `HoldingForm.vue` — takes an optional `owner` prop and serves an investment
  account or a pension. **One holding form** (Rule 20), not a pension-shaped copy.
  Dividend Yield is hidden in pension context because the endpoint has no rule for
  it and **W-0324 says in terms that the gap becomes live the moment anything adds
  a yield input** — this does not make it live.

  **The hazard this created, and closed.** `DCPensionForm.vue:1021-1027` maps stored
  holdings into the form as five keys, and `RetirementController` deleted every row
  and rebuilt from that payload — so units, prices and dates were dropped on the way
  in and annihilated on the way out. `hasAdditionalInfoData()` auto-expands that
  section whenever holdings exist, so once a pension had holdings **the destructive
  path was the default one**. `seedHoldingsForDcPension` is now
  `syncHoldingsForDcPension`: named rows are updated in place, unnamed rows deleted,
  ids preserved.

  **W-0322's acceptance 3 and 4 are untouched.** An empty array still names nothing
  and still clears everything, pinned by a test so whoever settles that question
  changes it deliberately.

  **Extended beyond the named scope, declared:** `sub_type` had no rule on the
  pension endpoint, so a fund type the form REQUIRES the user to choose was dropped
  by `validated()`. Adding it would have made a third copy of the vocabulary, so it
  moved to `app/Constants/HoldingSubTypes.php` and all three consumers read it. It
  is **accepted, not `required_if`** — written as `required_if` first, it turned an
  existing green 201 into a 422.

  **Files:** `app/Http/Controllers/Api/RetirementController.php` (sync) ·
  `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php` ·
  `app/Constants/HoldingSubTypes.php` (NEW) ·
  `app/Http/Requests/Investment/{Store,Update}HoldingRequest.php` ·
  `resources/js/components/NetWorth/PensionDetailInline.vue` ·
  `resources/js/components/Investment/HoldingForm.vue` ·
  `resources/js/services/dcPensionHoldingsService.js`

  **Evidence:** `tests/Feature/Retirement/DCPensionHoldingsSyncTest.php` (6),
  `DCPensionHoldingFieldsTest.php` (2), `tests/frontend/components/NetWorth/PensionDetailInline.test.js` (12).
  Backend 367 passed, frontend 257 passed. Mutation-tested in both directions —
  full table in `F-0032`.

  **Rule 19:** the sync and the endpoint fix reach `/m` for free (one shared
  endpoint). The entry control has **no `/m` counterpart by architecture** —
  `resources/mobile/api.js` has no post, put or patch helper anywhere, so `/m`
  cannot write at all.

- 2026-08-23 build-lead (`fix-cycle4-retirement`): **BROWSER VERIFIED on David.**

  Account established on the token, not on a relay: both stores were empty at the
  start (`sessionStorage.auth_token` and `localStorage.m_scaffold_token` absent, app
  redirected to `/login`), then signed in and `GET /api/auth/user` on the token in
  use returned **id 16, david.jones@example.com**. The `/m` store stayed empty
  throughout.

  **Worth recording, because it explains the reported 67 exactly:**
  `GET /api/auth/user` does **not** return `target_retirement_age` at all. So the
  Vuex `auth.user` never carried it, `this.user?.target_retirement_age` was always
  undefined, and the old expression fell through to its hardcoded `|| 67` every
  time — for every user, not just this one.

  **Pension 10, David's SIPP.** Tabs before any holdings existed: **Overview,
  Holdings, Projections, Documents** — the Holdings tab is there on a pension with
  none, which is the defect closed. Retirement Age row read **60**. Fees read
  **"Platform Fee: Not recorded"** with "No charges recorded for this pension yet",
  not the 0.00% that was reported.

  The holding form opened with **no account select** and **no Dividend Yield field**
  — the pension context behaving as designed, and W-0324's gap left un-live.

  All three of the persona's holdings entered through the interface and persisted
  exactly (`holdings` 73, 74, 75):

  | Row | Units | Purchase | Current | Value | Charge | Sub-type |
  |---|---|---|---|---|---|---|
  | Vanguard Global Equity (VHVG) | 4,211 | £32.50 | £38.00 | **£160,018.00** | 0.23% | `equity_fund` |
  | BlackRock Corporate Bond (SLXX) | 800 | £125.00 | £120.00 | £96,000.00 | 0.18% | — |
  | L&G UK Property (LGUKP) | 50,000 | £1.35 | £1.28 | £64,000.00 | 0.68% | — |

  `sub_type` stored — the field that had no rule and was silently dropped.
  `cost_basis` derived at £136,857.50 (4,211 x £32.50). Purchase date 2019-03-11 held.

  **The fee figures moved off zero, which was the acceptance:** Average Fund Charge
  **0.31%**, Total Annual Cost **0.31%**, Annual Fee Impact **£976/year** — against
  "0.00% / 0.00% / £0/year" before.

  **Edit and Delete exercised on a throwaway row rather than on persona data.**
  Added 77 units at £3.19 (£246), edited the units to 142 and saved — stored
  **£452.98**, the server re-deriving from units x price rather than from the 1%
  allocation (which would have been £3,200). Deleted it: rows 73, 74 and 75 live,
  only 76 trashed.

- 2026-08-23 build-lead: **The destruction test, run in the browser, and it holds.**

  The hazard confirmed live first: opening Edit on a pension that now has holdings
  **auto-expanded "Additional information"**, with the inline editor showing all
  three rows as allocation-only. That is the default path, not an unusual one.

  Changed the provider — an edit with nothing to do with holdings — and pressed
  Update. Afterwards:

  - provider updated, so the save landed;
  - **holdings 73, 74, 75 still live, same ids, `max(holdings.id)` still 75** —
    nothing deleted, nothing recreated;
  - units 4,211 / 800 / 50,000 intact; purchase and current prices intact;
    `sub_type` and purchase date intact;
  - **`current_value` still 160018.00** — not revalued to the £160,000 the
    allocation would give, which is the `$allocationMoved` guard doing its job;
  - **zero soft-deleted rows.**

  Against the pre-fix code this exact interaction deleted all three and recreated
  them with null units, null prices, null dates and allocation-derived values.

  **Test data restored:** the provider was set back to `AJ Bell` (the persona's
  value) with `saveQuietly()` after the test. The three holdings are left in place —
  they are the persona's real figures and were missing before.
