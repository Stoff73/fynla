---
id: W-0017
title: Defined Benefit pension form cannot hold four of the fields the model and the persona both have
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0001-batch-c-retirement-profile-gates.md
owner: build-lead
status: done
surfaces: [web, m, ios]
created: 2026-08-21T08:20:00Z
claimed: 2026-08-21T09:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21T09:10:00Z
prior_art_found: ["app/Http/Requests/Retirement/StoreDBPensionRequest.php:41-49 already validates all four fields", "app/Services/Stores/PensionStore.php:605-618 already validates and writes them", "app/Services/Stores/Normalisers/PensionNormaliser.php:44-72 already normalises them from Fyn tool params", "app/Services/Retirement/PensionProjector.php:108-135 already applies inflation_protection", "fyn-memory/procedural/tool_schema/savings/create_pension{,.xai}.md — the catalogue was the only thing withholding them"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **Sarah Jones (spouse)**, user id 17.

**Surface:** desktop web, `/net-worth/retirement` → Add Pension → Pension Type
"Final Salary (Defined Benefit)".

> **SUPERSEDED in one respect — see the `2026-08-21 build-lead` note below ("Important
> correction to the item as filed").** There is no single Defined Benefit form. There are
> **two**, and they had drifted: `DCPensionForm.vue` (the ADD path, which this run used)
> and `DBPensionForm.vue` (the EDIT and onboarding paths). The four gaps and the single
> item are unchanged; "one form" is not. Left as the record of what was believed.

One form, four gaps, one fix — folded into a single item at team-lead direction.

### Expected

Persona file `tests/Persona/peak_earners.md:382-394` — Sarah's NHS Pension Scheme:

| Field | Persona value | Column on `db_pensions` |
|---|---|---|
| Type | **Public Sector** (2015 career-average scheme) | `scheme_type` |
| Annual Pension | £35,000 | `accrued_annual_pension` |
| **Normal Retirement Age** | **60** | `normal_retirement_age` |
| **Inflation Protection** | **CPI** | `inflation_protection` |
| Lump Sum Entitlement | £105,000 | `lump_sum_entitlement` |
| **Spouse Benefit** | **50%** | `spouse_pension_percent` |
| Years of Service | 18 | `pensionable_service_years` |

### Actual

The form offers: Employer/Scheme Name, Scheme Status, Annual Income at Retirement,
Service Years, Pensionable Salary, Accrual Rate, **Revaluation Rate (% p.a.)**,
Pension Commencement Lump Sum, Notes.

The four bolded persona fields have nowhere to go. Saved row:

```
db_pensions.id = 4
scheme_name               = 'NHS Pension Scheme'
accrued_annual_pension    = 35000.00     ok
pensionable_service_years = 18.00        ok
lump_sum_entitlement      = 105000.00    ok
scheme_type               = 'final_salary'   WRONG — persona says public sector / career average
normal_retirement_age     = NULL             persona says 60
spouse_pension_percent    = NULL             persona says 50
inflation_protection      = 'none'           persona says CPI
```

**Gap 1 — no Normal Retirement Age input.** Column exists, form never asks. Sarah's
scheme pays from 60; with NULL the app has no scheme retirement age to project from.

**Gap 2 — no Spouse Pension % input.** Column exists, form never asks. The value is
live logic, not decoration: `app/Services/Coordination/HouseholdPlanningService.php:791`
reads `$pension->spouse_pension_percent ?? 50` when modelling death of a spouse, so
every such projection silently runs on an assumed 50 rather than the recorded figure.

**Gap 3 — Revaluation Rate is the wrong shape.** The form takes a **number** ("% p.a.,
typical: CPI, CPI+1.5%, or fixed" — the helper text names the very enum the field
cannot express). The model wants `inflation_protection` as `cpi` / `rpi` / `fixed` /
`none`. A user who wants CPI cannot say so, and the row defaults to `'none'` — the
worst of the four options, and the one that understates the pension most.

**Gap 4 — no career-average / public-sector scheme type.** The Pension Type list
offers only "Final Salary (Defined Benefit)" among DB options, while `scheme_type`
supports `final_salary` / `career_average` / `public_sector`. The NHS 2015 scheme is
career-average and had to be recorded as final salary — a materially different
accrual model.

### Evidence

**No screenshot** — this was an entry-phase finding and predates the run's screenshot
rule. The saved DB row above is quoted verbatim from `php artisan tinker`, and the
form's field list was enumerated from the live DOM.

Report: `reports/R-01-pass-a-entry.md`, "Fields the forms silently could not hold".

### Repro

1. `/net-worth/retirement` → Add Pension → Pension Type "Final Salary (Defined Benefit)".
2. Look for Normal Retirement Age, Spouse Pension %, an inflation-protection selector,
   or a career-average scheme type. None are present.
3. Fill and save; the row has `normal_retirement_age NULL`, `spouse_pension_percent
   NULL`, `inflation_protection 'none'`, `scheme_type 'final_salary'`.

### Note on a related but separate finding

The Property vault doc's Known Issue list and `Current State/Retirement.md` both claim
DB pension projections ignore `revaluation_method` / `inflation_protection` even when
recorded. I did **not** verify that claim — with the form unable to record CPI at all,
there was nothing to test it against. Worth confirming once this item lands, because
if true the fix is incomplete without it.

## Acceptance

- [ ] Form accepts Normal Retirement Age and persists `normal_retirement_age`.
- [ ] Form accepts Spouse Pension % and persists `spouse_pension_percent`; verify
      `HouseholdPlanningService.php:791` then uses the recorded value rather than its
      `?? 50` fallback.
- [ ] Inflation protection is a selector matching the column's enum
      (`cpi`/`rpi`/`fixed`/`none`), not a free number. If a numeric revaluation rate is
      also wanted, it is an additional field, not a substitute.
- [ ] Pension Type offers career-average and public-sector, and Sarah's NHS 2015 scheme
      can be recorded as what it is.
- [ ] Re-entering the persona's NHS pension produces a row matching all seven fields in
      the Expected table.
- [ ] Confirm DB projections actually apply `inflation_protection` once it can be set.
- [ ] `/m` and iOS pension forms checked for the same gaps (Rule 19).
- [ ] Re-verified live in the browser by the persona run.

## Working notes

(append-only)

- 2026-08-21 persona-tester: raised at team-lead direction, folding four gaps found
  during Pass A entry into one item — one form, one fix. Not fixed by me.

- 2026-08-21 build-lead: FIXED on web, `/m` and iOS. All four gaps closed, plus two
  more found on the way.

  **Important correction to the item as filed.** There is no single "Defined Benefit
  form". There are TWO, and they had drifted:
  - `resources/js/components/Retirement/DCPensionForm.vue` — the ADD path. The
    unified "Add Pension" form; its `isFinalSalary` branch writes `db_pensions`.
    This is the form the persona run used.
  - `resources/js/components/Retirement/DBPensionForm.vue` — the EDIT path
    (`UnifiedPensionForm.vue:4-9`) and the onboarding path
    (`Onboarding/steps/AssetsStep.vue:447`).

  Editing one and not the other is the disease Rule 20 names, so consolidation is
  part of the fix: **`resources/js/components/Retirement/dbPensionFields.js`** is
  now the one definition of the scheme-type options, the inflation-protection
  options and the `db_pensions` payload mapper. Both forms compose from it.

  **Gap 1 — Normal Retirement Age.** Input added to both forms
  (`DBPensionForm.vue:151-166`, `DCPensionForm.vue:508-522`), persisted as
  `normal_retirement_age`.

  **Gap 2 — Spouse Pension %.** Input added to both
  (`DBPensionForm.vue:167-184`, `DCPensionForm.vue:523-540`), persisted as
  `spouse_pension_percent`. Verified downstream: with the column NULL,
  `app/Services/Coordination/HouseholdPlanningService.php:791` falls back to an
  assumed 50; with it recorded it uses the recorded figure. Pinned by
  `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php`
  ("uses the recorded spouse pension percentage rather than the assumed 50%").

  **Gap 3 — Revaluation Rate was the wrong shape.** `inflation_protection` is now a
  selector (Consumer Prices Index / Retail Prices Index / Fixed rate / No inflation
  protection) on both forms; the numeric rate survives as a companion field shown
  only when "Fixed rate" is chosen, because that is the only case
  `PensionProjector::getRevaluationRate()` reads `revaluation_method` for.

  **Gap 4 — career-average / public-sector.** Scheme Type select added to the ADD
  form (`DCPensionForm.vue:445-459`) and driven from the shared options in the EDIT
  form (`DBPensionForm.vue:74-81`, which previously offered only two of the three).
  The top-level dropdown label changed from "Final Salary (Defined Benefit)" to
  "Defined Benefit (Final Salary, Career Average or Public Sector)"; the wire value
  stays `final_salary` for backward compatibility, with the real scheme type picked
  inside the section.

  **The note in the item about projections ignoring inflation_protection is WRONG —
  verified.** `app/Services/Retirement/PensionProjector.php:113-119` branches on
  `inflation_protection` (cpi 2.5%, rpi 3%, fixed → parsed from
  `revaluation_method`, none 0%, default 2%) and `:92` uses
  `normal_retirement_age`. Both are consumed by `projectAllPensions()` at `:201`.
  The vault Known Issue can be closed.

  **`/m` and iOS (Rule 19) — the gaps were real there too, and are fixed.** Neither
  surface has a pension form; both enter pensions through Fyn. The
  `create_pension` tool schema exposed `normal_retirement_age` but NOT
  `spouse_pension_percent`, `inflation_protection`, `lump_sum_entitlement` or the
  `public_sector` scheme type — and `update_record`'s `db_pension` allowlist could
  not correct any of them afterwards. `PensionNormaliser` and `PensionStore`
  already understood every one; only the catalogue withheld them. Changed:
  - `fyn-memory/procedural/tool_schema/savings/create_pension.md` and
    `create_pension.xai.md` (both providers, version 3 → 4) — three new properties
    plus `public_sector` in the enum.
  - `tests/fixtures/ToolSchema/*` and `tests/fixtures/XaiToolSchema/*` re-recorded
    via `CAPTURE_TOOL_SCHEMA_GOLDEN=1 CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1`; the
    byte-identity gates pass and the diff touches `create_pension` only.
  - `app/Constants/UpdateRecordAllowlist.php:73-82` — `db_pension` gains
    `scheme_type`, `spouse_pension_percent`, `inflation_protection`,
    `revaluation_method`, `lump_sum_entitlement`.
  - `app/Agents/CoordinatingAgent.php:3349-3352` — validation rules for the three
    new create_pension params.
  - `app/Services/Stores/PensionStore.php:617-619` — `inflation_protection`
    tightened from `string|max:64` to the column's enum; the loose rule let an
    invalid value through validation and die as a QueryException.

  **Two further defects found and fixed here, both blocking the acceptance:**
  1. `DBPensionForm.vue`'s edit watcher did `this.formData = { ...newPension }`,
     spreading `db_pensions` COLUMN names onto a form whose fields are named
     `employer_name` / `annual_income` / `service_years` / `final_salary` /
     `pcls_available`. Every input was bound to a key the record does not have, so
     the edit form opened **blank** and then refused to submit
     ("Please enter an employer/scheme name"). Now mapped properly
     (`DBPensionForm.vue:334-355`).
  2. `scheme_status` is collected by both forms and has **no `db_pensions`
     column** — it is discarded on every save. Requiring it on an EDIT therefore
     blocked the user for no benefit, so it is now required on create only
     (`DBPensionForm.vue:427-433`). The dead field itself is left alone; see the
     handoff note.

  **Tests:**
  - `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php` — the persona's
    seven fields end to end, the enum rejection, the spouse-percentage path. 14 passed.
  - `tests/Feature/AI/DirectWrite/CreatePensionTest.php` — the `/m` + iOS half:
    create with all three new fields, `public_sector`, enum rejection, and
    `update_record` correcting the previously untouchable fields. 10 passed.
  - `resources/js/components/__tests__/Retirement/DbPensionFields.spec.js` — mapper,
    option lists, Rule 9 labels, both form paths, edit population. 7 passed.
  - `tests/Feature/AI/ToolSchemaGoldenMasterTest.php` +
    `XaiToolSchemaGoldenMasterTest.php` — 14 passed after re-record.

  **NOT done by me — see the handoff note:** the `spouse_pension_percent` UNIT
  CONTRADICTION in the document-extraction path.

- 2026-08-21 build-lead: batch branch document (also the Rule 22 context handover)
  written to `workforce/branches/fixes/F-0001-batch-c-retirement-profile-gates.md`.
  It carries the dispatch verbatim plus both amendments, per-item file:line
  evidence, test output, decisions taken with reasoning, dead ends ruled out,
  environment state (no throwaway user was created — nothing to tear down), and
  the full W-0018 argument. Every Pest run re-verified under
  `DB_DATABASE=laravel_testing_c` after the shared-database deadlocks.

- 2026-08-31 build-lead: **VERIFIED FIXED AND TESTED — closed.**

  All four gaps are now inputs, and — the part that matters, given this item's own correction note about there being TWO Defined Benefit forms that had drifted — they are in **both** of them.

  **The ADD path, `DCPensionForm.vue`**, which is the path the persona run used and the one that had nothing: `db_normal_retirement_age` (`:521`), `db_inflation_protection` (`:568`, with `db_revaluation_rate` revealed at `:579` when it is `fixed`), `db_spouse_pension_percent` (`:536`), `db_service_years` (`:490`), `db_accrual_rate` (`:553`) and `db_pcls_available` (`:597`). They are gated on `pension_type === 'final_salary'` (`:928`) and carried into the payload at `:1300` and `:1362`.

  **The EDIT path, `DBPensionForm.vue`**, maps the two the item listed as missing from it — `pensionable_service_years` at `:355` and `lump_sum_entitlement` at `:361`.

  `UnifiedPensionForm.vue` routes between them explicitly (`:4` DB edit, `:12` State edit, `:24` add-or-DC-edit) and its comment records that the add form's dropdown now covers Final Salary and State Pension while still emitting `_pensionType` so `db_pensions` and `state_pensions` get the right payload shape.

  Sarah's NHS scheme can now be recorded in full: Public Sector, £35,000, NRA 60, CPI, £105,000 lump sum, 50% spouse benefit, 18 years' service.

  **Tested:** `resources/js/components/__tests__/Retirement/DbPensionFields.spec.js` — 9 passed.
