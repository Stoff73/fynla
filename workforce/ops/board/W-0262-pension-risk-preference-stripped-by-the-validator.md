---
id: W-0262
title: The per-pension risk control is silently discarded — six fields the canonical store accepts have no rule in the form request, and the flag every reader gates on has never been written by any client
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0023-cycle4-validation-and-silent-data-loss.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:40:00Z
claimed: 2026-08-22T20:45:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0009, W-0026, W-0032]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, cycle 4 batch 6 (`R-25`), local
`localhost:8000`, David (16), `dc_pensions.id=9`.

### The defect

Selecting **Upper-Medium** on "Risk Level for This Pension" saves nothing. The
tester proved it with a control, which rules out a missed click:

| | Field set in the same submit | Result |
|---|---|---|
| `dc_pensions.9` | `platform_fee_percent` → 0.35 | **saved** |
| `dc_pensions.9` | `risk_preference` → `upper_medium` | **still `medium`** |
| `investment_accounts.26` | `risk_preference` → `high` (different form) | **saved** |

`updated_at` moved, so the request succeeded and the row was written — this one
field was dropped from it. Silent data loss with a success response.

`DCPensionForm.vue:323` binds it → `RetirementController::updateDCPension:444-447`
takes a `StoreDCPensionRequest` and calls `validated()` → **the request has no
`risk_preference` rule** (it validates `platform_fee_percent` at `:64`, which is
exactly why the fee survived the same submit) → `validated()` strips it.
`DCPension::$fillable:61` includes it and `RetirementController:865-866` consumes
it. **Fillable, offered, sent, consumed — and unsettable.**

## Acceptance

- [x] A pension's risk preference set to Upper-Medium persists in
      `dc_pensions.risk_preference`.
- [x] The sibling fields on the same form with the same fault are fixed too, not
      left latent.
- [x] A test asserting the value **moved**, not that a save succeeded
      (`tests/CLAUDE.md` §4).
- [x] A drift guard so the next field added to one validator and forgotten in the
      other fails a test.
- [x] The full fillable-vs-rules sweep, reported whether or not fixed.
- [x] **Re-verified live in the browser** — pension 9, as David (16).

## Working notes

- 2026-08-22 build-lead: **FIXED. It was six fields, and storing the value alone
  would not have made the control work.**

  ### Reading the enforcing layer settles it

  `PensionStore::validateDcCanonical` — the inner validator every write actually
  passes through — **explicitly accepts** `risk_preference`, `has_custom_risk`,
  `expected_return_percent`, `salary_sacrifice`, `employer_matching_limit` and
  `employer_ni_rebate_pct`. Its own comment reads *"Mirrors StoreDCPensionRequest"*.
  **It did not.** Three of the six are `v-model`-bound on the form today
  (`risk_preference:323`, `expected_return_percent:221`, `salary_sacrifice:360`), so
  three fields were being dropped, not one.

  `PensionThreeIngestParityTest`'s docblock confirms this is restoring the intended
  design rather than inventing one — it lists these as **"Form-only DC fields"**.
  Hence prior-art outcome `extend`: the mechanism existed and the outer layer had
  drifted out of step with it.

  ### The half that would have looked fixed and done nothing

  Every reader gates on the PAIR — `RetirementController:865`,
  `PensionProjector:291` (the one that changes the projection) and
  `PortfolioPresentationService:204` all test
  `has_custom_risk && risk_preference`.

  **`has_custom_risk` has never been written by any client.** A grep of the whole
  repository finds exactly three writers, **all seeders** (`PreviewUserSeeder:935,996`,
  `ChrisUserSeeder:219,253`). For every real user it has sat at its column default
  of `0` since the column was created — **on investment accounts as well as
  pensions** — so the per-product risk override has never done anything for anyone
  who was not seeded.

  Fixing only the validation would have produced a field that saves, displays and
  changes no behaviour: the worse of the two failures, because it looks fixed.
  `PensionNormaliser::fromFormDc` now derives the flag — choosing a level IS the act
  of overriding, so a second control saying "and mean it" would be a mechanism the
  user must operate to make the first one work. Only when the key was sent, so an
  edit that omits it leaves the stored flag alone (the discipline `fromFormDb`
  already applies to `scheme_status`).

  **The investment-account half is still inert** — same missing writer, different
  agent's scope. Raised for whoever owns it; see F-0023 §3.3.

  ### A latent 500 closed on the way past

  The store's rule was `risk_preference => 'sometimes|nullable|string|max:64'`, but
  the column is `enum('low','lower_medium','medium','upper_medium','high')`. Any
  other string passed validation and would have died as a `QueryException` at the
  column — **the same failure `inflation_protection` is documented as, eight lines
  below it in the same method.** Tightened to the enum.

  The vocabulary was already retyped inline in four places
  (`RiskPreferenceController` ×2, `AccountProjectionsRequest`,
  `AutoRiskCalculator::$riskOrder`). Rather than adding a fifth,
  `InvestmentDefaults::RISK_PREFERENCES` is the one home and both new rules read it.
  Deliberately NOT derived from `RISK_LEVEL_MAP`, which also carries legacy aliases
  (`cautious`, `balanced`, `growth`, `aggressive`) the columns reject — a validator
  reading that map's keys would admit five values the database refuses. The four
  existing copies are left alone (live scope elsewhere) and recorded in F-0023 §7.

  ### Tests — 7 cases, all verified RED first

  `tests/Feature/Retirement/DCPensionRiskPreferenceTest.php`.

  Asserting the request returned 200 is exactly the assertion that passed
  throughout the bug, so every case asserts the stored value **moved**, with the
  starting value set explicitly so "moved" cannot be confused with "was already
  that".

  - Removing the six rules: **6 of 7 fail.**
  - Keeping the rules but disabling the `has_custom_risk` derivation: **2 of 7
    fail.** So both halves are independently load-bearing — which is the whole
    point above.

  The 7th case is the drift guard: every key `validateDcCanonical` accepts must
  survive `StoreDCPensionRequest`. One-directional deliberately — the inner
  validator is the stricter, enforcing layer, and the request legitimately
  validates form-only keys (`holdings`) the store never sees.

  ### The sweep — the deliverable

  **95 rows**, filtered to fields actually `v-model`-bound in `resources/js` or
  `resources/mobile` (a fillable field nothing offers is not a defect). Full list in
  `F-0023` §6.2.

  **Look at these first:** `liabilities.ownership_type`, `.ownership_percentage`
  and `mortgages.ownership_percentage` are all fillable, `v-model`-bound and absent
  from their rules. That is the ownership family this run has already spent three
  items on (W-0226, W-0228, W-0015), and **a discarded ownership field is a wrong
  figure, not a missing one.** Also unvalidated and offered: 7 `savings_accounts`
  beneficiary/contribution fields, 3 `goals` display/status fields, 11
  `investment_accounts` BADR/bond fields.

  Caveat: the iOS column in that table is a substring match over
  `ios-native/*.swift` — a lead, not a finding. The `v-model` columns are exact.

  ### Browser-verified GREEN — and it caught a regression this fix introduced

  Pension 9 through the real form: `medium -> upper_medium`, `has_custom_risk`
  `false -> true`, `updated_at` moved, `current_fund_value` intact at 180000.00.

  **The first attempt 422'd, and that was my fault.** Giving the six fields rules
  stopped `validated()` stripping them — the fix — and **that exposed them to the
  canonical store for the first time**, where `salary_sacrifice` was
  `sometimes|boolean` with no `nullable` against a column that IS nullable.
  `DCPensionForm` serialises its whole model and sends `salary_sacrifice: null` on
  every save, so a legitimate save was rejected.

  Fixed the store rule to `sometimes|nullable|boolean`, and added a case that posts
  the browser's payload **verbatim, nulls and all**. Also added
  `PensionNormaliser::DC_NOT_NULL_WITH_DEFAULT` — the third table to need the
  W-0052 drop — which closes the latent `current_fund_value` 500 the sweep had
  already flagged.

  **Why no test caught it:** every case sent the one or two fields under test; the
  real form sends thirty. `tests/CLAUDE.md` §4's fixture variant, pointed at my own
  work — **a payload narrower than the real one cannot enter the branch that
  breaks.** 10 cases now, all green.

  Backend-only, so web, `/m` and native are covered by architecture. No rebuild
  needed for this item.

  **The `has_custom_risk` finding is now its own item, W-0264** — whole-repo scope,
  and very likely the explanation for the D-21 CRITICAL.
