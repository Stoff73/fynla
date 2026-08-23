---
id: W-0012
title: Mortgage created via the property wizard hardcodes a 300-month term and drops the Rate Fix End Date
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: queued
surfaces: [web, m, ios]
created: 2026-08-20T23:35:00Z
claimed: 2026-08-21T10:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: REJECTED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21T10:30:00Z
prior_art_found: ['app/Services/Stores/Normalisers/MortgageNormaliser (canonical mortgage write path)', 'config/mortgage.php default_term_months', 'MortgageController::store term/maturity defaults', 'StorePropertyRequest mortgage_* rules']
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **David Jones (primary)**, user id 16.

**Surface:** desktop web, `/net-worth/property` → Add Property wizard, step 2
"Mortgage".

### Expected

Persona file `tests/Persona/peak_earners.md:154-166` — The Willows mortgage:

| Field | Value |
|---|---|
| Lender | HSBC |
| Outstanding Balance | £65,000 |
| Original Amount | £450,000 |
| Type | Repayment |
| Interest Rate | 4.29% (Fixed) |
| **Fixed Rate End** | **2027-04-01** |
| Monthly Payment | £550 |
| **Remaining Term** | **156 months** |
| Ownership | Joint (50%) |

### Actual

The property itself is **correct** and this is worth recording as the control: one
row, `ownership_type=joint`, `ownership_percentage=50.00`, `joint_owner_id=17`,
`current_value=850000.00` (full value, not split) — Rule 6 satisfied, and the card
renders "Full Property Value £850,000 / Your Share (50.00%) £425,000 / Mortgage
Outstanding £32,500 / Equity £392,500", all correct.

The **mortgage** row is not:

```
mortgages.id = 8
lender_name           = 'HSBC'          ok
mortgage_type         = 'repayment'     ok
outstanding_balance   = 65000.00        ok
original_loan_amount  = 450000.00       ok
interest_rate         = 4.2900          ok
rate_type             = 'fixed'         ok
monthly_payment       = 550.00          ok
ownership_type        = 'joint'         ok
ownership_percentage  = 50.00           ok
joint_owner_id        = 17              ok
maturity_date         = 2039-08-20      ok (entered; = 156 months from today)
remaining_term_months = 300             WRONG — persona says 156
rate_fix_end_date     = NULL            WRONG — entered 2027-04-01, dropped
```

Note the row is **internally inconsistent**: `maturity_date` 2039-08-20 is 156 months
away, while `remaining_term_months` claims 300 (25 years, i.e. 2051). Two fields on
the same record disagree about when the mortgage ends.

### Root cause — both faults in one map

`app/Services/Property/MortgageService.php:44-66`, `createFromPropertyData()`:

**:61** — the term is a literal:

```php
'remaining_term_months' => 300,
```

It is never derived from `maturity_date` (which the same method sets from input at
:60) and never read from the request. Every mortgage created through the property
wizard gets 300 regardless of what the user entered.

**:44-66** — the array has **no `rate_fix_end_date` key**. The wizard renders a
"Rate Fix End Date" input (it appears conditionally once Rate Type = Fixed is chosen,
and I confirmed the value 2027-04-01 was present in the DOM at submit), the column
exists on the `mortgages` table, and the value is simply never mapped.

`monthly_interest_portion` is likewise unmapped. The Property vault doc's Known Issue
6 notes Section 24 buy-to-let tax needs it and flags `has_interest_portion_missing`
when null — so the two buy-to-let mortgages in this persona will hit that too.

### Repro

1. `/net-worth/property` → Add Property.
2. Step 1: any property, Ownership Type = Joint Tenancy, tick "This property has a
   mortgage".
3. Step 2: Rate Type = Fixed (reveals Rate Fix End Date), enter a Rate Fix End Date
   and a Mortgage End Date that is clearly not 25 years away.
4. Save.
5. `mortgages.rate_fix_end_date` is NULL and `mortgages.remaining_term_months` is 300,
   whatever the maturity date says.

### Why it matters

`remaining_term_months` is what the amortisation schedule
(`GET /mortgages/{id}/amortization-schedule`) and the mortgage payoff projections run
on. A 300-month term against a 156-month mortgage nearly doubles the modelled
remaining life of the debt, which flows into equity projections, the estate
liabilities figure, and any "should I overpay the mortgage?" answer — one of this
persona's four stated key concerns (`peak_earners.md:512`).

`rate_fix_end_date` is what any remortgage/rate-shock messaging keys off; with it
NULL there is nothing to warn against.

### Evidence

**No screenshot** — entry-phase finding. The DB row is quoted in full above, including the internal contradiction between `maturity_date` and `remaining_term_months`.
Report: `reports/R-01-pass-a-entry.md`.

## Acceptance

- [ ] `remaining_term_months` is derived from `maturity_date` (or accepted from
      input), not hardcoded — `MortgageService.php:61`.
- [ ] `rate_fix_end_date` is mapped in `createFromPropertyData()` and persists from
      the wizard.
- [ ] `monthly_interest_portion` is mapped, or its absence is deliberately documented
      given the Section 24 dependency.
- [ ] No mortgage row can be saved where `remaining_term_months` and `maturity_date`
      disagree.
- [ ] Same check applied to the standalone mortgage create/update path
      (`MortgageController::store` / `update`), not just the wizard.
- [ ] `/m` and iOS mortgage entry checked (Rule 19).
- [ ] Re-verified live in the browser by the persona run, for all three of this
      persona's mortgages.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Root cause diagnosed to file:line
  above; not fixed by me — routed to build-lead.
- The property record itself is correct — joint 50/50 single-row rendering verified
  end to end. This item is mortgage-only.

- 2026-08-21 build-lead: **FIXED — verified through the HTTP layer.**

  The term/maturity invariant now lives in ONE place, `MortgageNormaliser`, which
  is the canonical write path for every mortgage ingest (form, Fyn, upload) —
  not in `MortgageService`, so the standalone `MortgageController::store` path
  gets it too, which was an acceptance bullet.

  `MortgageNormaliser::reconcileTerm()`:
  - a usable `maturity_date` → `remaining_term_months` is DERIVED from it (the
    date is the fact the user entered);
  - no date but a term → `maturity_date` is derived from the term;
  - neither, on a partial update → both left alone;
  - neither, on a create → the configured default (`config('mortgage.default_term_months')`),
    because `mortgages.remaining_term_months` is NOT NULL. This last branch was
    added after `MortgageThreeIngestParityTest` caught a null-column violation
    from the Fyn ingest path — Fyn maps optional fields to explicit nulls.

  So a row where the two disagree can no longer be written by any path.

  `MortgageService::createFromPropertyData()`:
  - `'remaining_term_months' => 300` literal **removed** (the normaliser derives it);
  - `rate_fix_end_date` **mapped** (it had no key at all);
  - `monthly_interest_portion` **mapped** — the Section 24 dependency the item
    flagged is now satisfiable;
  - the ownership block's own 100→50 copy routed through `SharedOwnership`;
  - `now()->addYears(25)` literal replaced with `config('mortgage.default_term_years')`.

  Two upstream leaks had to be fixed for the wizard values to arrive at all:
  - `resources/js/components/NetWorth/PropertyList.vue:266-280` hand-copied a
    **subset** of mortgage fields onto the property payload. It now prefixes every
    mortgage field, so `rate_fix_end_date`, `remaining_term_months` and
    `monthly_interest_portion` stop being dropped in the browser.
  - `StorePropertyRequest` had no rules for those three keys, so `validated()`
    stripped them. Added.

  **Verified** by `tests/Feature/Stores/PropertyHttpIntegrationTest.php`: a
  wizard POST with maturity = today+156 months, `rate_fix_end_date` 2027-04-01 and
  `monthly_interest_portion` 232.38 writes `remaining_term_months = 156` (not
  300), the rate fix end date persists, and the joint property + mortgage both
  land at 50.00. Plus unit coverage in `MortgageNormaliserTest` (4 new cases) and
  `MortgageServiceOwnershipTest` (2 new cases).

  **GAPS:**
  - **Not re-verified for this persona's three mortgages in the live browser** —
    only one property can be entered on the free tier. Covered at the HTTP layer
    instead. Routed to the persona re-run.
  - **`/m` and iOS mortgage entry not checked.** `/m` has no mortgage create form
    (`MortgageDetail.vue` is read-only), so there is nothing to fix there; the
    read side benefits automatically. iOS outside this dispatch.

- 2026-08-21 build-lead: batch handover (CLAUDE.md Rule 22) — `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md`. Carries the dispatch verbatim, the joint-share consolidation reasoning, decisions taken, dead ends ruled out, and environment state.
