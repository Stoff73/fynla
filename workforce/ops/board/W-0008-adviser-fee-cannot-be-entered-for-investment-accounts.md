---
id: W-0008
title: Adviser fee cannot be entered for investment accounts — displayed and charged in projections, but always £0
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: handoff
surfaces: [web, m, ios]
created: 2026-08-20T22:26:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: REJECTED 2026-08-24 quality-lead — see ops/handoffs/quality-lead/cycle4-recertification-2026-08-24.md
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['DCPensionForm.vue advisor_fee_percent (proven pattern)', 'StoreDCPensionRequest advisor_fee_percent rule', 'InvestmentAccount model fillable + cast already present', 'InvestmentAccountNormaliser already casts advisor_fee_percent']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **Sarah Jones (spouse)**, user id 17 — and it applies to
David's three investment accounts equally.

**Surface:** desktop web, `/net-worth/investments` → Add Account (and the edit form),
all account types.

### Expected

Persona file `tests/Persona/peak_earners.md` gives an **Adviser Fee** for every
investment account:

| Account | Line | Platform Fee | Adviser Fee |
|---|---|---|---|
| David's Stocks & Shares ISA | :276-277 | 0.45% | **0.75%** |
| Sarah's Stocks & Shares ISA | :296-297 | 0.45% | **0.75%** |
| Joint General Investment Account | :314-315 | 0.25% | **0.75%** |
| Venture Capital Trust Holdings | :334 | — | **0.75%** |

Entering an account should let both fees be recorded, and
`investment_accounts.advisor_fee_percent` should hold `0.75`.

### Actual

There is **no adviser fee input** anywhere in the investment account form. The modal
offers Platform Fee only ("Platform Fee | % | £ | Monthly | Quarterly | Annually |
Platform fee as a percentage of assets per year"). Searching the rendered modal for
`/Advis/i` returns false.

After saving Sarah's Stocks & Shares ISA the DB row is:

```
investment_accounts.id = 13
provider          = Hargreaves Lansdown
current_value     = 85000.00
platform_fee_percent = 0.4500     <-- entered, correct
advisor_fee_percent  = 0.0000     <-- persona says 0.75, no way to enter it
```

So the persona's adviser fee is unenterable through the UI, for every investment
account.

### Why it matters — the value is consumed, not ignored

`advisor_fee_percent` is read by at least four display/calculation surfaces:

- `resources/js/components/Investment/FeeBreakdown.vue:27` renders an "Advisor Fees"
  line; `:168` computes it from `account.advisor_fee_percent`
- `resources/js/components/NetWorth/InvestmentList.vue:756`
- `resources/js/components/NetWorth/InvestmentProjections.vue:76`, `:286`, `:647` —
  adviser fee is shown on the projections screen and fed into the projection
- vault `Architecture/v083/09-MODULES.md` — `FeeAnalyzer` "breaks down by type (fund
  OCF, platform, advisor)"

Every one of those renders £0 / 0% permanently. Fee-drag figures and net-of-fee
projections are therefore understated by the adviser fee for all users — the
projection is not merely missing a label, it compounds a fee that was never deducted.

### The pension form already does this correctly

`resources/js/components/Retirement/DCPensionForm.vue:275` has
`v-model.number="formData.advisor_fee_percent"`, and
`app/Http/Requests/Retirement/StoreDCPensionRequest.php:68` validates
`'advisor_fee_percent' => ['nullable','numeric','min:0','max:10']`.

The investment side has neither: `app/Http/Requests/StoreInvestmentAccountRequest.php:56`
validates `platform_fee_percent` and stops there — there is no `advisor_fee_percent`
rule, so even a hand-crafted request would be stripped.

### Repro

1. `/net-worth/investments` → Add Account → any account type → "Show additional
   information".
2. Search the form for an adviser/advisor fee input — there is none.
3. Save the account; `investment_accounts.advisor_fee_percent` is `0.0000`.
4. Open the account's projections view — "Advisor Fee" displays 0%.

### Evidence

**No screenshot** — entry-phase finding, predates the screenshot rule. Verifiable in seconds: open the Add Account form and search it for an adviser-fee input.
Report: `reports/R-01-pass-a-entry.md`.

## Acceptance

- [x] The investment account form (add and edit) accepts an adviser fee, matching the
      pattern already used in `DCPensionForm.vue:275`.
- [x] `StoreInvestmentAccountRequest` and `UpdateInvestmentAccountRequest` validate
      `advisor_fee_percent` (same rule shape as `StoreDCPensionRequest.php:68`).
- [x] Entering 0.75 persists `advisor_fee_percent = 0.7500` and the value appears in
      `FeeBreakdown.vue` and `InvestmentProjections.vue`.
- [x] Fee-drag and net-of-fee projections change accordingly (verify the projected
      value moves down, by roughly the right magnitude).
- [ ] `/m` and iOS equivalents accept and display it (Rule 19). **`/m` has no investment
      account form and no investment projection surface — nothing to display it on. iOS
      NOT checked.** See working note 2026-08-25.
- [ ] Re-verified live in the browser by the persona run. **Verified live in the browser
      by this session on both surfaces; the persona run itself has not re-run.**

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Not fixed by me — routed to
  build-lead. Entry for the four persona accounts is blocked on this: adviser fee
  will read 0% until it lands.

- 2026-08-21 build-lead: **FIXED — input verified present in the live modal.**

  - `resources/js/components/Investment/StandardInvestmentFields.vue:249-266` —
    an "Adviser Fee (% per year)" input, same shape as the proven
    `DCPensionForm.vue:275`, shown under "Show additional information" for every
    non-NS&I account type.
  - `StoreInvestmentAccountRequest:59` and `UpdateInvestmentAccountRequest:60` —
    `['nullable','numeric','min:0','max:10']`, the same rule as
    `StoreDCPensionRequest.php:68`.
  - `AccountForm.vue` — `advisor_fee_percent` added to `formData`, `resetForm()`,
    the `allowedFields` submit allowlist (without which it was stripped),
    `hasAdditionalInfoData()` so editing an account that has one auto-expands the
    panel, and the collapsed-clear block.

  No model or store change was needed: `InvestmentAccount` already had it
  fillable and cast, and `InvestmentAccountNormaliser` already cast it to float.
  The column and every display of it existed; only the way to enter it did not.

  **Live, `/net-worth/investments` → Add Account → ISA → Show additional
  information:** label "Adviser Fee (% per year)", `#advisor_fee_percent` present.
  Also confirmed on the EDIT form for Sarah's Hargreaves Lansdown ISA (rendered
  0.0000, the current stored value).

  Test: `tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` — POST
  with `advisor_fee_percent: 0.75` persists 0.75 alongside `platform_fee_percent`
  0.45, and a PUT to 0.50 persists.

  **GAPS:**
  - **I did not verify the fee-drag / net-of-fee projection figures move**
    (acceptance bullet 4). The value now reaches `FeeBreakdown.vue` and
    `InvestmentProjections.vue`, which already read `account.advisor_fee_percent`,
    but I did not measure the projected value before and after. Routed to Quality.
  - `/m` has no investment account create/edit form, so there is no input to add
    there. iOS outside this dispatch.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.

- 2026-08-25 (Brett, working alone per CSJ's 2026-08-24 standing instruction):
  **the rejected criterion is now met — the fee reaches the projection, and it is
  worth £8,329 on David's ISA at ten years.**

  **Root cause — it was never only the adviser fee.** `InvestmentProjectionService`
  contained no reference to a fee of any kind in 522 lines. All four of its Monte
  Carlo call sites drove the simulation with `$riskParams['expected_return_typical']
  / 100`, the gross risk-derived return:

  - `:190` `calculatePortfolioProjection`
  - `:258` `calculateAccountProjection`
  - `:403` `getAccountProjectedValue80`
  - `:462` `buildAccountProjection`

  So the platform fee and the fund OCF were equally uncharged. The frontend's
  `totalFeePercent` (`InvestmentProjections.vue:658`) is display-only — the chart's
  numbers come from the backend — which is how the account screen could print
  "Total Fees 1.72%" directly above a chart compounding the full 8%.

  **Proved before fixing, on account 125 (Sarah's HL ISA, the persona case):**
  setting `advisor_fee_percent` to 0.75 or to 0 produced projections identical to
  the penny — `p20 = 209,917.60` and `p50 = 259,191.05` both ways, delta £0.00.

  **CSJ decision needed? No — asked and answered by Brett 2026-08-25:** deduct
  **total fees** (platform + adviser + weighted OCF), not the adviser fee alone.
  Deducting only the adviser fee would have left the projection contradicting the
  fee card on the same screen.

  **Fix — one home, four consumers.** `InvestmentProjectionService::annualFeePercent()`
  is the single deduction; all four call sites read it, so a fifth cannot be added
  that forgets a fee. Helpers `platformFeePercent()` (converts a fixed charge against
  the account it is charged on, mirroring the Vue computed of the same name) and
  `weightedOcfPercent()`. `weightedPortfolioFeePercent()` weights each account's fee
  by that account's share, weighted exactly as the portfolio's risk parameters are
  directly below it — so the existing "single-account portfolio equals that account"
  test still holds. Pattern taken from `PensionProjector.php:53-67`, which has charged
  its fees this way all along.

  **`ocf_percent`, not `ocf`.** The `CalculatesOCF` trait reads `ocf` and *estimates*
  from the asset type when it is null — right for the fee analysis it serves, wrong
  here. On account 125 `ocf` is NULL while `ocf_percent` is 0.2200, so the trait would
  have charged an estimate the user never saw. The trait is untouched.

  **The caption.** `expected_return` now carries the return the projection was
  actually run at, with `gross_expected_return` and `fee_drag_percent` beside it.
  D-21 was a caption that moved while the figure did not; a caption stating the gross
  return over a chart compounding the net one is that fault inverted. The chart
  caption now reads "Using High risk profile (7.59% expected return, less 1.40% in
  charges)" — `InvestmentProjectionChart.vue` gained a `feeDragPercent` prop so the
  deduction cannot appear to be blamed on the risk profile.

  **Measured — live browser, preview persona `peak_earners`, David's view:**

  | Surface | Before (fees ignored) | After (fees charged) | Movement |
  |---|---|---|---|
  | Portfolio, 10y p20 | £404,771 @ 7.59% | **£382,833** @ 6.19% | −£21,938 (−5.42%) |
  | David's HL ISA, 10y p20 | £202,339 @ 8.00% | **£182,938** @ 6.28% | −£19,400 |

  Both "after" figures are the ones **rendered on screen**, not computed alongside it.
  The account screen's fee card reconciles with the deduction: 0.45 platform + 0.75
  adviser + 0.52 OCF = **1.72%**, and the portfolio's value-weighted drag is 1.3981%,
  displayed as 1.40%. The "before" figures were measured inside a rolled-back
  transaction; persona data verified restored afterwards.

  **Isolating this item's own figure:** removing just the 0.75% adviser fee from
  David's ISA moves 10y p20 from £182,938 to £191,267 — **the adviser fee is worth
  £8,329.**

  **Blast radius, deliberate and stated.** `getAccountProjectedValue80` feeds
  `RetirementIncomeService` at 8 sites and `getPortfolioProjections` feeds
  `IHTCalculationService:1412`. Both are now net of fees, which is correct and matches
  what the pension side already did — but every user's investment projection drops.
  This is a visible change to a financial figure across the product.

  **Tests.** New `tests/Feature/Investment/ProjectionIsNetOfFeesTest.php` — 10 tests,
  movement/ordering/magnitude assertions only, no literals, per the convention in
  `PortfolioProjectionRespondsToInputsTest`. Confirmed RED before the fix (the
  portfolio case failed "159019.86 is less than 159019.86"). Green after, together
  with Investment (322), Retirement (176), Estate (495) and every other suite
  referencing `expected_return` (77) — **1,080 tests, 3,634 assertions.** Pint clean.

  **GAPS — carried forward honestly:**
  - **iOS NOT checked.** Not built, not launched, not looked at.
  - **`/m`: nothing to verify.** It has no investment account form and no investment
    projection consumer (`grep` over `resources/mobile/` returns zero). The backend is
    shared, so if a projection surface is ever built there it inherits the fix.
  - **The full suite was not run** — targeted suites only, per Rule 17.
  - **Adjacent, NOT fixed (reported, not touched):** "total fee percent" is now defined
    twice — `InvestmentProjections.vue:658` for display and `annualFeePercent()` for the
    projection. They agree today and are pinned by tests on the backend side only.
    Rule 20 would want one home with the backend sending the figure; that is a
    frontend refactor beyond this item.
  - **Environment, unrelated:** `php artisan route:list` throws
    `AppleVerificationException: invalid_configuration` on this machine (missing
    `.venv/apple-store`). Does not affect the running app.

- 2026-08-25 quality-lead certification — **CORRECTION to the note above. Read this
  before citing any figure in it.**

  **The £8,329 "the adviser fee is worth" figure does not reproduce, and the reason
  invalidates the method rather than just the number.**

  quality-lead measured **£3,847** through two independent call sites. Root cause:
  **`MonteCarloEngine` seeds from its inputs.** Changing a fee changes the expected
  return, which changes the seed, which **re-rolls the sample**. Measured sampling
  noise on p20 is **3.9% peak-to-peak — comparable in size to the effect being
  attributed to the fee.**

  So **"the adviser fee is worth £X" is not a well-defined quantity in this engine.**
  Subtracting one simulated percentile from another is differencing two draws, not
  measuring a difference. I reported that subtraction as a measurement.

  **What does still hold:**
  - The **"after" figures reproduce to the penny** — portfolio 10y p20 **£382,833**,
    David's HL ISA **£182,938**. Those are the figures on screen and they are right.
  - `expected_return` moving 4.33% → 3.58% is **exact arithmetic, not a sample** —
    the fee is subtracted from the rate deterministically. That is the sound way to
    state this fix's effect.
  - The defect and the fix are unaffected. The projection was gross of all fees and
    now is not.

  **What to say instead of a pound figure:** the drag is stated as a rate
  (0.45 platform + 0.75 adviser + 0.52 OCF = 1.72% on that account), and the
  projection is run at gross minus that. Any before/after pound comparison on this
  engine needs a fixed seed or many draws, and this session used neither.

  The larger movements reported above (−£21,938 and −£19,400) rest on drags of 1.40%
  and 1.72% — far enough above the noise floor to show direction and rough magnitude,
  but they carry the same caveat and should not be quoted to the pound.

  **The commit message `67c96e73b` also carries the £8,329 figure and cannot be
  amended — it is pushed history. This note is the correction of record.**

  **Two undeclared defects quality-lead found in this work:**

  1. **`getAccountProjectedValue80()` has zero test coverage** — 8 production call
     sites in `RetirementIncomeService`. The entire Retirement suite stayed green
     through a change that moves retirement income for every user. Nothing in the
     repo pins it.
  2. **The fee is now an unbounded, unclamped input to a compounding simulation.**
     `platform_fee_amount` has no `max:` rule. Validation-legal maxima give a
     ten-year projection of **39 pence**; a plausible typo gives **negative zero**;
     both travel into retirement income and the projected estate. **This is new — before
     this change no fee reached the simulation**, so it was introduced here. Not
     present in live data (max observed drag 1.95%), so latent rather than live.

  **Certification: CANNOT CERTIFY**, pending the `tax-compliance-reviewer` gate that
  quality-lead is barred from running. Not blocked on any code defect.

- 2026-08-25 tax-compliance-reviewer gate — **CLEARED WITH CONDITIONS. None block.**

  **Rule 2 clean** — zero tax values in `InvestmentProjectionService`.

  **The methodology is sound.** Deducting an ad-valorem charge from the expected return
  *is* the correct model for it, and including the fund OCF alongside platform and
  adviser fees is right. Two non-blocking caveats: a fixed platform fee is modelled as
  a constant rate, and the five expected-return assumptions carry **no stated
  gross/net provenance** — which this change makes load-bearing for the first time,
  since a return that was already net of fund charges would now be double-deducted.

  **Consumer Duty: the "before" state is the indefensible one, and not close.** A fee
  card reading "Total Fees 1.72%" above a chart compounding the full gross return is
  worse than the corrected state. A short disclosure to users whose projections fall is
  worth considering, but that is product's call, and the FCA perimeter question belongs
  to `compliance-lead`.

  **The most important finding is not about this item's arithmetic.** The reviewer
  measured `MonteCarloEngine`'s sampling behaviour directly:

  - **10 of 20** £10 increases in monthly contribution **LOWERED** the projected 20th percentile
  - **11 of 20** fee **increases RAISED** it
  - cutting an adviser fee 0.75% → 0.70% shows **£3,640 less**
  - sampling range on p20 is **7.49%** against a full 1.00pp fee worth **6.88%** — **signal ≈ noise**

  The level is fine (p50 within ±2.6% of closed form) and there is **no run-to-run
  flicker** — identical inputs give identical output. But **every comparison drawn
  across two projections is unreliable, including the exact comparison this item exists
  to make.** Cause is `MonteCarloEngine::seedFromInputs()`; fix is common random
  numbers — seed from identity, not economics. Pre-existing, but this item makes it
  product-facing. Raised as **W-0486**, on the reviewer's raise-today instruction.

  That also explains the £8,329 retraction above: it was not a slip in the arithmetic,
  **the quantity is not well defined in this engine.**
