---
id: W-0263
title: 18 numeric `max:` rules permit values their decimal column cannot physically store — a 500 on any double-digit mortgage rate, savings rate or dividend yield
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0023-cycle4-validation-and-silent-data-loss.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T21:25:00Z
claimed: 2026-08-22T21:25:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0261, W-0052]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**Found in the browser while verifying W-0261**, not by inference. It is the third
distinct way the validation layer and the schema disagree in this run, after
`nullable`-on-NOT-NULL (W-0052, W-0261) and fillable-but-unvalidated (W-0262).

### The defect

`holdings.dividend_yield` is `decimal(5,4)` — five significant digits, four of them
after the point, so it **physically stops at 9.9999**. The rule said `max:100`.

Entering a dividend yield of **50** — an entirely ordinary-looking number that
passes `nullable|numeric|min:0|max:100` — reaches MySQL and raises:

```
SQLSTATE[22003]: Numeric value out of range: 1264
Out of range value for column 'dividend_yield' at row 1
```

Reproduced live at `POST /api/investment/holdings` as David (16). Before the
W-0261 handler fix, that message and the full INSERT were rendered to the user.

### The class — 18 rules across 11 requests

Swept mechanically: every `numeric`/`integer` rule with a `max:` against its
column's actual decimal precision.

| Request | Column | Type | Rule | Column holds |
|---|---|---|---|---|
| `StoreMortgageRequest` / `UpdateMortgageRequest` | `fixed_interest_rate` | decimal(5,4) | max:100 | < 10 |
| `StoreMortgageRequest` / `UpdateMortgageRequest` | `variable_interest_rate` | decimal(5,4) | max:100 | < 10 |
| `Savings/StoreSavingsAccountRequest` / `Update…` | `interest_rate` | decimal(5,4) | max:20 | < 10 |
| `StoreInvestmentAccountRequest` / `Update…` | `current_ownership_percent` | decimal(5,4) | max:100 | < 10 |
| `Investment/StoreHoldingRequest` / `Update…` | `dividend_yield` | decimal(5,4) | max:100 | < 10 |
| `Investment/StoreHoldingRequest` / `Update…` | `ocf_percent` | decimal(5,4) | max:100 | < 10 |
| `Admin/StoreActuarialLifeTableRequest` / `Update…` | `life_expectancy_years` | decimal(4,2) | max:120 | < 100 |
| `StoreInvestmentAccountRequest` / `Update…` | `advisor_fee_percent` | decimal(5,4) | max:10 | < 10 (boundary: exactly 10 overflows) |
| `Retirement/StoreDCPensionRequest` | `platform_fee_percent`, `advisor_fee_percent` | decimal(5,4) | max:10 | < 10 (boundary) |

**Ranked by how ordinary the breaking input is:**

1. **`mortgages.fixed_interest_rate` / `variable_interest_rate` — the worst.** A
   double-digit mortgage rate is not exotic: it is most of British history, and
   adverse-credit and some buy-to-let products are there now. **Any user entering
   a rate of 10% or more gets a 500 on a core field of a core module.**
2. **`savings_accounts.interest_rate`** — a 10%+ regular-saver rate is uncommon but
   real, and the rule advertises 20.
3. **`investment_accounts.current_ownership_percent`** — this one looks like a
   plainly wrong column type. An ownership percentage needs 0–100; the column
   cannot hold 10.
4. **`holdings.dividend_yield` / `ocf_percent`** — the pair found here.
5. `life_expectancy_years` — admin-only, low reach.
6. The `max:10`-on-decimal(5,4) rows are boundary-only: exactly `10` overflows,
   `9.9999` is fine. Real but narrow.

### The judgement this needs, and why I did not make it

**Capping the rule at the column is only half a fix, and on its own it is a
regression in disguise.** `max:9.9999` on a mortgage rate turns a crash into
"you may not enter a rate of 12%", which is a wrong answer delivered politely.

For most of these rows **the column is what is wrong**, not the rule. `decimal(5,4)`
gives four decimal places and one integer digit; a percentage field needs three
integer digits. The correct fix is a migration to `decimal(7,4)` (or `decimal(6,4)`
where 0–99.9999 suffices), then the rules can say what the product actually means.

That is a schema change across mortgages, savings, investments and holdings —
a migration plus a product call on each field's real range. **It is its own work
item and it is not something to decide inside a validation fix**, which is the
same reasoning that kept W-0242 from pre-empting W-0241.

## Acceptance

- [x] `holdings.dividend_yield` and `.ocf_percent` no longer 500 — capped at the
      column's real capacity so the failure is a civil 422 (W-0261 fix, tests in
      `HoldingNotNullColumnsTest`).
- [x] **Superseded by the widening below.** Both columns are now `decimal(7,4)`
      and both rules are back to `max:100`, per the decision this item was told to
      own. A 50% yield saves; 150 is still a 422.
- [x] Per-column decision on the remaining 16: widen the column, or cap the rule.
      **The mortgage rates should be widened, not capped.** — **Done: 12 columns
      widened, none capped.** See F-0025 §3 for the per-column reasoning.
- [x] A drift guard, so a `max:` that exceeds its column's precision fails a test.
      The sweep script is reusable as one. — `tests/Unit/Database/ValidationMaxFitsColumnPrecisionTest.php`,
      proven to go red before it was trusted.
- [x] **MET 2026-08-23 — the headline in a browser.** A 12% mortgage rate entered
      through the real form and saved. Property 9 / mortgage 8 (HSBC), David (16),
      `localhost:8000`, rate type Mixed, 12% fixed and 14.75% variable.
      `PUT /api/properties/9` **200**, `PUT /api/mortgages/8` **200**, and the row
      holds `rate_type: mixed`, **`fixed_interest_rate: 12.0000`**,
      `variable_interest_rate: 14.7500`, `fixed_rate_percentage: 60.00`,
      `variable_rate_percentage: 40.00`.

> **QUALITY-LEAD: the browser proof is now DONE — see the checked criterion above.**
>
> It was blocked twice by defects outside this batch, both since fixed: **W-0325**
> (`PropertyController` 500 on every joint property update) and **W-0326**
> (`MortgageStore` refusing `mixed`, the only rate type that reveals the field).
> Running the journey verified both as a side effect — the property PUT returned
> 200 where it previously 500'd, and `joint_account_logs` gained a row, which
> proves `logJointPropertyUpdate` actually **executed** rather than merely not
> crashing.
>
> **Two things this item does NOT cover, both raised separately:**
> **W-0351** — the 12% now saves and **still cannot be displayed**, because
> `MortgageResource` omits the two portion-percentage fields the detail view gates
> on. Same disease at the read boundary.
> **Persona data left changed** — mortgage 8 now reads mixed 12%/14.75% where the
> persona had fixed 4.29%. Original values in the working note below; restoring is
> a coordinator provisioning job.

## Working notes

- 2026-08-22 build-lead: **Two of eighteen fixed, sixteen reported.**

  I fixed only `holdings.dividend_yield` and `.ocf_percent`, because they are the
  fields W-0261 was about and because a 500 in front of a user is worse than a
  narrow limit. Both now cap at `9.9999`, with the reasoning written at the rule so
  the next reader does not mistake the cap for the intended range. Two tests pin
  it: a yield of 50 is a 422 with no row written, and a yield of 4.25 still saves.

  **I deliberately did NOT touch the other sixteen**, including the mortgage rates,
  even though `app/Http/Requests/` is my exclusive scope for this batch. Capping a
  mortgage rate at 9.9999 would stop users recording perfectly real mortgages, and
  I am not going to ship that quietly to close a sweep row. They need the migration
  decision above.

  **Method note.** This class is invisible to both of the other sweeps in this
  batch: the column is NOT NULL or nullable as appropriate, the field is validated
  and fillable, and every layer looks correct in isolation. It only appears when
  you compare the rule's *range* to the column's *precision* — a third axis. Worth
  remembering that "the rule and the column disagree" has at least three shapes,
  and a sweep for one of them says nothing about the other two.

- 2026-08-22 build-lead (`fix-cycle4-columns`), F-0025: **20 rows, 12 columns
  widened, nothing capped.**

  **The two most serious rows both looked like the opposite of what they were, in
  opposite directions, and neither was safe to fix on the sweep's say-so.**

  `mortgages.fixed_interest_rate` carries a column comment reading *"annual rate
  as decimal"*. Taken at face value it is a FRACTION, `decimal(5,4)` is correct,
  and the rule is the defect — the exact opposite of this item's conclusion. The
  comment is stale. Live rows on the sibling `mortgages.interest_rate` store
  `4.5000` for 4.5% and that column is already `decimal(8,4)`;
  `MortgageNormaliser:98` rounds all three identically; `PropertyDetailInline.vue:321`
  renders with a `%` and no division; the form says "Fixed Interest Rate (%)",
  placeholder "e.g., 3.5". Percentage. Comment corrected in the migration.

  `investment_accounts.current_ownership_percent` is a percentage, and worse than
  ranked here: the input is `min="0" max="100" step="0.01"` and the detail view
  appends `%`, so **a 50% shareholding could never be stored at all.** The table
  holds zero non-null rows, which is what a field that has never worked looks like
  from outside.

  **The sweep's own contrast is what made this decidable:** this schema DOES use
  `decimal(5,4)` correctly for fractions — `life_insurance_policies.decreasing_rate`
  and `dc_pensions.employer_ni_rebate_pct`, both `max:1`, with
  `PolicyFormModal.vue:920` dividing by 100 on save. Those were left alone.

  **Two rows the 18 could not contain, both live 500s:**

  1. `StorePropertyRequest.mortgage_fixed_interest_rate` / `.mortgage_variable_interest_rate`
     (`max:100`) write the same two mortgage columns via `MortgageService:57-58`.
     The same crash through the property wizard. **A sweep that joins rule names
     to column names cannot see them — the names differ by a prefix.** It finds
     the columns, not the doors.
  2. **A fourth shape entirely: a numeric rule with NO `max:` at all.**
     `investment_accounts.platform_fee_percent` was `nullable|numeric|min:0`
     against `decimal(5,4)`, so the column was the only thing between a typed 12
     and `SQLSTATE[22003]` — while its sibling `advisor_fee_percent` on the same
     form carried `max:10`. There is no over-promise to detect because there is no
     promise; the column validates, by crashing. A no-max sweep found 4 rows and
     now returns 0.

  **`cash_accounts.interest_rate` is the one row deliberately left**: no writer,
  no rows, units genuinely undeterminable. Widening it to close a sweep row would
  be the guessing this item exists to prevent. Filed as **W-0323**.

  **Method note, extending the one above.** "The rule and the column disagree" now
  has FOUR shapes, not three, and a sweep for any one says nothing about the
  others — W-0324 (`dividend_yield` missing from every nested holdings rule set)
  was found while working the range axis and belongs to the presence axis. Also:
  **55% of this sweep's raw output was name-collision noise**, separable only by
  reading the controller that consumes each request. The drift guard encodes that
  reading, so the next agent does not repeat it.

- 2026-08-23 build-lead (`fix-cycle4-columns`): **HEADLINE BROWSER-VERIFIED.**

  Property 9 "15 Chestnut Lane" (joint, `joint_owner_id` 17) / mortgage 8 (HSBC),
  as David (16), identity read from `fynla-state.auth.user` rather than recognised
  from a figure — the figures were the probes.

  Rate type **Mixed** → 12% fixed, 14.75% variable, 60/40 split → Save Property.

  | Check | Result |
  |---|---|
  | `PUT /api/properties/9` | **200** (previously **500**, W-0325) |
  | `PUT /api/mortgages/8` | **200** (previously **422**, W-0326) |
  | `mortgages.fixed_interest_rate` | **`12.0000`** |
  | `mortgages.variable_interest_rate` | `14.7500` |
  | `mortgages.rate_type` | `mixed` |
  | `joint_account_logs` | new row at 00:33:08 |

  **The joint-account-log row is the sharper half of the W-0325 verification.**
  A 200 shows the request did not crash; the log row shows
  `logJointPropertyUpdate` **ran to completion** — the method whose type hint was
  resolving to a non-existent class. Absence of a crash and presence of the side
  effect are different claims, and the second is the one worth making.

  **PERSONA DATA LEFT CHANGED — restoration needed, values below.** Mortgage 8 was
  `rate_type: fixed`, `interest_rate: 4.2900`, with `fixed_interest_rate`,
  `variable_interest_rate`, `fixed_rate_percentage` and `variable_rate_percentage`
  all **NULL**. It now holds mixed 12%/14.75% at 60/40.

  **A UI-only restore cannot undo it**: setting rate type back to `fixed` hides
  the four fields, so the form has no control that can clear them and the values
  would persist as invisible orphans — a half-state worse than the current honest
  one. Restoring is therefore a coordinator `saveQuietly()` job, as with account
  13's provider. It matters because this is the main residence and the figures
  feed household debt, protection need and net worth.

  **Found in the same journey and raised separately: W-0351.** The 12% saves and
  **cannot be displayed** — `MortgageResource` omits `fixed_rate_percentage`,
  which is exactly what `PropertyDetailInline.vue:319` gates the rate rows on. The
  detail view shows "Rate Type: Mixed" and no numbers at all. **Six axes of this
  batch were measured at the write boundary; that one is the same disease on the
  journey home.**
