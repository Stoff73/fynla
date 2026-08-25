---
id: W-0132
title: The Inheritance Tax rate shown to the user is not the rate the calculation used — the label reads 40% while the figure beside it is computed at 36%, because the label is driven by a user toggle that is never loaded back
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md
owner: build-lead
status: gated
severity: high
surfaces: [web]
created: 2026-08-21T19:05:00Z
claimed: 2026-08-22T07:35:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [W-0020, W-0046, W-0131]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **batch B regression pass**, local
`localhost:8000`, driven in Playwright as **Priya Raman, `users.id 20`**, premium,
married to `users.id 30`.

**Surface:** `/estate` (Inheritance Tax Summary card) and `/estate/inheritance-tax`
(the drill-down table). Both render `resources/js/components/Estate/IHTPlanning.vue`
— the drill-down is the same component with `:table-only="true"`
(`resources/js/views/Estate/InheritanceTaxDetail.vue:16`).

### Expected

W-0020 made the Inheritance Tax rate read the user's recorded charitable bequests:
record a qualifying legacy and the reduced 36% rate applies. The rate the user is
**shown** must be the rate the tax figure beside it was **calculated at**. Whatever
else differs between screens, a row cannot state one rate and display a number
computed at another.

### Actual

Priya has one recorded charitable bequest — Cancer Research UK, £10,000,
`bequest_type: specific_amount`. Her baseline is £65,000, so the 10% threshold is
£6,500 and the legacy clears it comfortably. The server agrees:

```
IHTCalculationService::calculate(20, 30, true)
  total_net_estate      715000     nrb_available       650000
  charitable_baseline    65000     charitable_threshold  6500
  charitable_deduction   10000     iht_rate_percent        36    iht_rate_type "reduced"
  projected_taxable_estate  1104584.81
  projected_iht_liability    397650.53
  message: "Reduced IHT rate of 36% applies. Your charitable giving of 1.4% (£10,000)
            meets the 10% threshold of £6,500 (10% of baseline £65,000)."
```

`WillAnalysisService::analyzeCharitableBequests()` independently agrees —
`status: "above"`, `charitable_total: 10000`, `effective_rate_percent: 36`.

**The screen says 40%.** Verbatim from `/estate/inheritance-tax`, screenshot
`46-web-priya-iht-label-40pct-server-says-36.png`:

```
Taxable Estate                      £0     £1,104,585
Inheritance Tax Liability (40%)     £0       £397,651
```

£397,651 is **36%** of £1,104,585 (£397,650.53). 40% would be **£441,834**. The row
labels its own figure with a rate that figure was not calculated at, and the two are
£44,183 apart.

**Root cause — the label reads a flag the client never receives.**

1. `IHTPlanning.vue:916-918` —
   `effectiveIHTRateLabel() { return this.charitableBequest ? '36%' : '40%'; }`.
   Nothing in that expression consults `iht_rate`, `iht_rate_percent` or
   `iht_rate_type`, all of which the API returns.
2. `this.charitableBequest` is loaded on mount by `loadCharitableBequest()`
   (`:1462` calls it, `:1534-1539` defines it) from `currentUser.charitable_bequest`.
3. **The user payload the client holds has no `charitable_bequest` key.** Read live
   from the running app:

   ```js
   JSON.parse(localStorage.getItem('fynla-state')).auth.user
   → hasOwnProperty('charitable_bequest') === false
   ```

   So `charitableBequest` is `undefined` on every fresh load → falsy → the label is
   **permanently 40%**, regardless of the will *and* regardless of the toggle.
4. Writing it works; reading it does not. Clicking **Yes** on the Charitable Bequest
   card issues `PUT /api/user/profile/personal → 200` and the column really is set —
   `users.charitable_bequest = true` in the database — but after a reload **neither
   radio is checked** and the label is back to 40%
   (`68-web-priya-estate-summary-reloaded-toggle-not-restored-40pct.png`). The user's
   saved answer is invisible to them and to the label that depends on it.

**A third set of figures appears in-session.** Before any reload, with the toggle set
to Yes and **no change to the will**, the frontend stops using the server's liability
and recomputes it itself (`IHTPlanning.vue:1328-1355`), deducting an *assumed* donation
of 10% of baseline rather than the will's actual legacy
(`66-web-priya-toggle-yes-36pct-assumed-donation-148444.png`):

| | Taxable estate (age 84) | Inheritance Tax | Where it comes from |
|---|---|---|---|
| Server, recorded £10,000 legacy | £1,104,585 | **£397,651** (36%) | `IHTCalculationService` |
| Screen, toggle unanswered | £1,104,585 | **£397,651** — labelled **40%** | server figure, wrong label |
| Screen, toggle = Yes (in-session only) | £956,141 | **£344,211** (36%) | frontend, assuming a £148,444 donation |

£148,444 is exactly 10% of the age-84 baseline (£2,134,437 − £650,000 = £1,484,437).
It is not a gift Priya has made, and it is not in her will.

**The two screens can disagree with each other at the same instant.** With
`users.charitable_bequest = true` in the database, `/estate` in-session showed
"Inheritance Tax Summary (36% rate) … £344,211" while its own drill-down
`/estate/inheritance-tax` showed "(40%) … £397,651"
(`67-web-priya-iht-drilldown-still-40pct-with-toggle-yes-in-db.png`).

**The card also asks a question it already has the answer to.** `/estate` renders
"Charitable Bequest — Leave £6,500+ to charity to reduce your Inheritance Tax rate?"
with Yes/No unanswered, while the £397,651 on the same card is the reduced-rate figure
produced by the £10,000 legacy she has already left
(`65-web-priya-estate-charitable-card-asks-despite-recorded-legacy.png`).

### Impact

This is W-0020's own journey failing at the last step. W-0020 exists so that a
recorded charitable legacy reaches the rate; the rate now moves in the calculation and
**the user is still told 40%**. Someone who left a legacy specifically to reach the
reduced rate is shown no evidence that it worked, and someone checking the arithmetic
finds the stated rate and the stated tax irreconcilable — the exact "two answers, never
speaking to each other" that Rule 20 forbids, and that W-0020's own docblock
(`IHTCalculationService.php:1327-1334`) describes as the bug it was fixing.

Three mechanisms currently answer "does the reduced rate apply":

| Mechanism | Reads | Says for Priya |
|---|---|---|
| `IHTCalculationService::determineIHTRate()` (`:1309`) | recorded `Bequest` rows | **36%** |
| `EstatePlanService:476-480` | `charitable_analysis.status` | 36% (not shown — see notes) |
| `IHTPlanning.vue:916-918` | `users.charitable_bequest`, never loaded | **40%, always** |

### Repro

1. Premium married account with estate assets above the combined Nil Rate Band. Priya:
   two properties totalling £715,000, no liabilities, spouse `users.id 30`.
2. `/estate/will-builder` → **Add Bequest** → `Cancer Research UK`, "A fixed sum of
   money", £10,000 → **Add Bequest**. `POST /api/estate/bequests → 201`.
3. `/estate/inheritance-tax`, wait for hydration (~10s).
4. Read the "Inheritance Tax Liability (40%)" row. Divide the figure by the taxable
   estate beside it: **0.36**.
5. `php artisan tinker` → `IHTCalculationService::calculate()` → `iht_rate_percent: 36`,
   `iht_rate_type: "reduced"`.
6. On `/estate`, answer **Yes** to the Charitable Bequest card. The heading becomes
   "(36% rate)" and the figures change. Reload. **Both radios are unchecked again and
   the label is 40%**, while `users.charitable_bequest` is `true` in the database.

### Acceptance

1. The rate shown to the user is the rate the displayed figure was calculated at, on
   `/estate` and `/estate/inheritance-tax`. The obvious fix is to render the API's
   `iht_rate_percent` / `iht_rate_type` / `iht_rate_message`, which the endpoint
   already returns and which `/plans/estate` already consumes — one source, per Rule 20.
2. The frontend stops recomputing the liability from an assumed donation. If a
   "what if I left 10%?" projection is wanted it is a clearly-labelled scenario, not a
   silent substitution for the user's real position.
3. `charitable_bequest` either round-trips to the client, or is removed along with the
   toggle. A column that is written and never read back is not a setting, and the card
   must stop asking a user who has already recorded a qualifying legacy.
4. Verified in a browser against a persona whose legacy actually crosses the threshold,
   with the rate and the arithmetic agreeing on both screens.
5. Re-verify on `/m` and native. `/m`'s estate screens do not currently render a rate;
   confirm before assuming parity is free.

### Notes

- **Not a `TaxConfigService` problem.** `getCharitableReducedRate()` and
  `getCharitableThresholdPercent()` both return correct values and the server uses
  them. The strings `'36%'` and `'40%'` in `IHTPlanning.vue:916-918`, and the default
  `'40%'` at `IHTCalculationTable.vue:558`, are hardcoded presentation values that
  bypass the configured rates entirely — a Rule 2 breach in its own right, currently
  masked by the numbers happening to match this tax year.
- `/plans/estate` shows the correct message via `iht_rate_message` but was
  **"Estate Plan Not Applicable"** for Priya (her current liability is £0), so that
  surface could not be used as a cross-check on this account
  (`47-web-priya-plans-estate-not-applicable.png`).
- **A tri-state column behind a two-state control.** `users.charitable_bequest` holds
  `NULL` / `true` / `false`, and `NULL` — "not yet answered" — is the state every user
  starts in. The card offers only **Yes** and **No**, so once either is pressed the user
  can never return to unanswered, and there is no third control that would let them.
  That matters beyond tidiness: `NULL` and `false` are indistinguishable to
  `effectiveIHTRateLabel()` (both falsy) but mean entirely different things to a
  reader — "we have not asked you yet" versus "you told us no" — and the card renders
  identically in both. Whatever replaces this mechanism should either lose the third
  state or render it.
- Priya's `users.charitable_bequest` was `NULL` when this run started and is `true`
  now, set through the interface while evidencing this item. For the reason above it is
  not restorable through the interface, and the tester does not patch database rows.
  It has no server-side effect — `determineIHTRate()` reads
  `IHTProfile.charitable_giving_percent`, not this column.

## Working notes
(append-only)

- 2026-08-22 cycle2-audit (build-lead): **PARTIAL. The `/settings/family` half is
  fixed; the `/estate` half is untouched and still needs an owner. Branch document
  `F-0020`. Status left `queued` deliberately — this item is NOT done.**

  ### Done — the Family settings card no longer asks a question it has the answer to

  The card read `store.state.auth.user?.charitable_bequest`, so it was a **fourth**
  answer to "is this person leaving money to charity", and the only one that was
  wrong. It said "Not set" for Priya, who has a recorded £10,000 legacy the estate
  calculation was already using.

  It now reads the will, through the class that already owns the question:

  - `WillAnalysisService::charitableBequestSummary()` — **new method, existing
    home.** It sits beside `getCharitableBequestTotal()` and
    `hasUnvaluedCharitableGifts()`, both of which read `Bequest::isCharitable()`,
    whose docblock already declares itself "The ONE home for this decision (Rule
    20)". **No new heuristic. The count of mechanisms goes down.**
  - `UserProfileService::getCompleteProfile()` publishes it as an additive
    `charitable_bequests` block, following the `income_summary` precedent. **No new
    endpoint and no new request** — `/settings/family` already loads this profile.
  - `FamilyMembers.vue` renders "Yes — Your will records one charitable gift,
    totalling £10,000."

  **On the tri-state in the notes above: `NULL` vs `false` did not need rendering, it
  needed removing.** With the toggle out of the read path there is no third state —
  the will either records a gift or it does not. A test asserts "Not set" never
  renders, for a recorded gift, for no gift, and for a missing summary.

  **Rejected, deliberately:** appending `is_charitable` to the `Bequest` model and
  letting the Vue card filter and total the rows. That puts a second copy of the
  totalling rule in the frontend, including the deliberate exclusion of unvalued
  gifts at `WillAnalysisService.php:145-153`. Rule 20 forbids it.

  **Estate-dependent gifts are named, never totalled.** A percentage or residuary
  gift is worth nothing until an estate is valued, and a settings page must not run
  an estate calculation to render a card. `has_estate_share` flags it and the card
  says "given as a share of your estate" — counting one as £0 inside a printed total
  would be the same disease this cycle is treating.

  **Pinned by 8 Pest tests** (`WillAnalysisCharitableBequestTest`, 22 passed), **4
  feature tests** (`UserProfileCharitableBequestsTest`, new) and **6 frontend tests**
  (`FamilyMembers.spec.js`, 24 passed). **Every one sets `users.charitable_bequest`
  to whichever value gives the WRONG answer** — NULL with a legacy, false with a
  legacy, true with none — so a mechanism still consulting the column cannot pass.

  Verified read-only: users 16, 17 and 20 each return
  `has_bequests: true, count: 1, fixed_total: 10000`.

  ### NOT done — everything on `/estate` and `/estate/inheritance-tax`

  **Acceptance 1, 2 and 4 are untouched.** `IHTPlanning.vue` still carries the whole
  mechanism this item was raised about:

  - `:943` — `effectiveIHTRateLabel()` returns hardcoded `'36%' / '40%'` from the
    toggle and never consults `iht_rate_percent` / `iht_rate_type` / `iht_rate_message`,
    all of which the endpoint returns. Also a Rule 2 breach in its own right.
  - `:1354-1377` and `:1481-1504` — the frontend still recomputes the liability from
    an **assumed** 10%-of-baseline donation instead of the will's actual legacy.
  - `:1600-1616` — the toggle read/write, and `IHTCalculationTable.vue:558`'s default
    `'40%'`.

  **I stayed out of that file on purpose: it was modified at 07:03 today, minutes
  before I would have opened it.** Another agent is live in a 1,700-line component
  and editing underneath them would have been reckless. Acceptance 3 is now half
  satisfiable — `charitable_bequest` still does not round-trip, but the settings card
  no longer depends on it, so removing the column and the toggle is a smaller job
  than it was.

  ### Assumption made

  I read the brief's scope as the `/settings/family` card, on the basis that the
  calculation side is already correct and the display side is what disagrees. **If
  the whole item was meant to land in one pass, the estate half is still open and I
  have capacity for it** once its current editor is clear.

- 2026-08-22 cycle2-audit (build-lead): **estate half now done too. Item COMPLETE and
  handed to quality-lead. Branch document `F-0020`. Not browser-verified — Quality's
  loop.** `IHTPlanning.vue` was assigned to this branch by team-lead at 07:35, once
  `cycle2-projection` had been routed off it.

  **Acceptance 1 — the rate shown is the rate the figure was calculated at.**
  `effectiveIHTRateLabel()` returned `charitableBequest ? '36%' : '40%'` — two
  hardcoded literals decided by a column the client is never sent, so it read 40%
  permanently. Replaced by `ihtRateLabel`, built from the server's
  `iht_rate_percent`. Both screens are the same component
  (`InheritanceTaxDetail.vue` passes `:table-only="true"`), so they now cannot
  disagree with each other again.

  **The mapping was dropping the rate, and substituting a different quantity.**
  `loadIHTCalculation()` set `iht_rate: effective_rate / 100`. `effective_rate` is
  the liability as a percentage of the WHOLE net estate — nearer 12% than 40% — and
  `iht_rate_percent`, `iht_rate_type` and `iht_rate_message` were dropped entirely.
  Same family as W-0134's dropped allowance fields: published by the server,
  discarded by a hand-written mapping, then re-derived wrongly downstream. The
  summary block also had to start publishing the type and the message
  (`IHTController`), which is why `/plans/estate` could state the rate correctly and
  this screen could not.

  **Acceptance 2 — the recomputation is deleted, not corrected.** Pointing the label
  at the right field and stopping would have left the worse half standing. Gone:
  the `charitableBequest ? ... : ...` branches in `taxableEstate` and `ihtLiability`
  in BOTH `secondDeathTableProps` and `standardTableProps`; the entire toggle-driven
  alternate table layout in `IHTCalculationTable.vue` (~105 lines); the
  `charitableDonation` prop and row; and the three computeds that existed only to
  size an assumed donation. The frontend renders what the API returns and computes
  nothing. The what-if survives as a **clearly labelled scenario** on the card —
  "If you left £6,500 or more … A scenario only — nothing above changes until the
  gift is in your will" — which changes no figure on the page.

  **Acceptance 3 — the toggle is removed, not made to round-trip.** A column that is
  written and never read is not a setting, and nothing server-side consults it. The
  card now states what the will records and links to the will builder, so the user
  still has a way to express the thing — the correct one. `users.charitable_bequest`
  is now read by NOTHING in the application; dropping the column is a migration and
  its own item.

  **Rule 2 breach closed.** `'36%'`, `'40%'` and the `effectiveIHTRateLabel` prop
  default of `'40%'` were hardcoded presentation values bypassing the configured
  rates — masked only by this year's values happening to match. The new prop has no
  default: no rate, no label.

  **A finding worth recording: Priya's two columns have different rates.** Current
  36%, projected **40%** — the projection re-runs the 10% test against a much larger
  estate where her £10,000 no longer clears the threshold
  (£472,662 ÷ £1,181,656 = 40%, read-only 2026-08-22). The old single label was
  therefore **accidentally right for the projected column and wrong for the current
  one.** The label now states both when they differ: "36% today, 40% at age 84".
  Note also her `charitable_deduction` is now £20,000, not the £10,000 in this
  item's Intent — W-0154 pooled the household's legacies for the s23 exemption while
  keeping the rate test on the survivor's will alone, which is why the message still
  cites £10,000.

  **Pinned by 19 tests.** 6 appended to
  `tests/frontend/components/Estate/IHTCalculationTable.test.js` (15 passed) which
  **divide the printed liability by the printed taxable estate and require the answer
  to be the rate in the label**; 8 in the new
  `tests/frontend/components/Estate/IHTPlanningRateLabel.test.js`, exercising the
  computed directly because the defect was WHICH FIELD it read; and 5 in the new
  `tests/Feature/Api/IhtRateIsPublishedWithItsFigureTest.php`, which do the same
  division inside one response body and reach the reduced rate **on a user whose
  `charitable_bequest` column is `false`** — so a mechanism still consulting it
  cannot pass. One test covers a £500 legacy that is deducted under s23 and does NOT
  qualify for the reduced rate, because "has a legacy" is not "qualifies".

  Regression: `tests/Unit/Services/Estate/` + the two Api tests + `EstateTeaserGateTest`
  **310 passed, 993 assertions**; frontend Estate + UserProfile **319 passed across 25
  files**.

  **Acceptance 4 and 5 are Quality's** — browser verification on Priya with the rate
  and the arithmetic agreeing on both screens. **Acceptance 5 (`/m`, native) has
  nothing to verify against:** neither renders an Inheritance Tax rate or figure —
  zero hits for `iht_rate` in `resources/mobile` and `ios-native`, consistent with
  W-0138.
