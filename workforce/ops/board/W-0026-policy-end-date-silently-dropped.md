---
id: W-0026
title: Policy end date is validated, accepted, 201'd and silently discarded on 4 of 5 protection policy types
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0006-batch-d-protection-goals.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-21T09:55:00Z
claimed: 2026-08-21T11:00:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21
prior_art_found: [BasePolicyRequest::commonRules, UpdateRecordAllowlist (W-0017/BUG-02 precedent)]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **`/m` discovery sweep** (entry phase), local
`localhost:8000`, premium. Account **David Jones (16)**.

**Surface:** desktop web, `/protection` → Add Protection. Not touched by Batch A, B or C.

### Expected

Persona `tests/Persona/peak_earners.md:412-441`:

| Policy | Start | End |
|---|---|---|
| Joint Level Term (Vitality, £500,000) | 2020-01-01 | 2040-01-01 |
| Standalone Critical Illness (Legal & General, £200,000) | 2020-01-01 | 2040-01-01 |

Entering an end date should persist it.

### Actual — two independent faults, both losing policy dates

**Fault 1 — backend silently drops `policy_end_date` (4 of 5 policy types).**

The Critical Illness form has a "Policy End Date" field. I entered 2040-01-01 and
captured the request:

```
POST /api/protection/policies/critical-illness   →  201
{"provider":"TESTCO","premium_amount":10,"premium_frequency":"monthly",
 "policy_type":"standalone","sum_assured":1000,
 "policy_start_date":"2020-01-01",
 "policy_end_date":"2040-01-01",          <-- sent
 "policy_term_years":null,"conditions_covered":[]}
```

Result: `policy_start_date = '2020-01-01'`, **`policy_end_date = NULL`**. Reproduced
twice (rows 2 and 3).

The column exists on every table:

```
critical_illness_policies    policy_end_date: YES
life_insurance_policies      policy_end_date: YES
income_protection_policies   policy_end_date: YES
disability_policies          policy_end_date: YES
sickness_illness_policies    policy_end_date: YES
```

Validation accepts it — `app/Http/Requests/Protection/BasePolicyRequest.php:37`:
`'policy_end_date' => ['nullable','date','after:today']`.

But `$fillable` does not:

| Model | `policy_start_date` fillable | `policy_end_date` fillable |
|---|---|---|
| `LifeInsurancePolicy` | yes | **yes** |
| `CriticalIllnessPolicy` | yes | **NO** |
| `IncomeProtectionPolicy` | yes | **NO** |
| `DisabilityPolicy` | yes | **NO** |
| `SicknessIllnessPolicy` | yes | **NO** |

Eloquent mass assignment discards it. The API still returns 201, so nothing surfaces.
`CriticalIllnessPolicy.php:49` also casts only `policy_start_date`, not the end date.

**Fault 2 — the Life Insurance form has no date fields at all.**

`LifeInsurancePolicy` *can* store both dates, but choosing Policy Type = "Life
Insurance" replaces Start Date / Policy End Date with a single **"Policy Term
(years)"** field. The persona's start 2020-01-01 and end 2040-01-01 have nowhere to go:

```
life_insurance_policies.id 7  Vitality  level_term  sum=500000.00  premium=85.00/monthly
  in_trust = true          policy_number = 'VIT-LT-456789'
  policy_start_date = NULL    policy_end_date = NULL    policy_term_years = 20
```

So between the two faults: **Critical Illness offers the field and drops it; Life
Insurance stores the field and never offers it.** Neither policy type can hold the
persona's dates.

### Why it matters

Policy end dates drive coverage-expiry modelling — `CoverageTimelineChart` and the
protection gap analysis both depend on knowing when cover ends. A NULL end date makes
a 20-year term policy indistinguishable from one running to death.

### Evidence

Captured request/response quoted above from an `XMLHttpRequest` hook in the live page;
DB rows quoted from tinker. **No screenshot** — the failure is invisible on screen,
which is the point: the UI shows a successfully saved policy.

Report: `reports/R-07-m-sweep.md`.

### Repro

1. `/protection` → Add Protection → Policy Type "Critical Illness".
2. Fill provider, sum assured, premium, Start Date and **Policy End Date**.
3. Save — succeeds, 201.
4. `critical_illness_policies.policy_end_date` is NULL.
5. Repeat with Policy Type "Life Insurance" — there is no date field to fill at all.

## Acceptance

- [ ] `policy_end_date` added to `$fillable` (and the date cast) on
      `CriticalIllnessPolicy`, `IncomeProtectionPolicy`, `DisabilityPolicy`,
      `SicknessIllnessPolicy`.
- [ ] The Life Insurance form exposes Start Date and Policy End Date, as the other
      policy types already do — or term and dates are reconciled into one consistent
      model across all five types (Rule 20).
- [ ] Entering the persona's two policies persists start 2020-01-01 and end
      2040-01-01 on both.
- [ ] Audit the other `$fillable` arrays in `app/Models/*Policy.php` for further
      validated-but-unfillable fields — this is a silent class of bug and there may be
      siblings.
- [ ] Consider whether a validated field that is not fillable should fail loudly
      rather than 201 — that is what let this ship.
- [ ] `/m` and iOS protection entry checked (Rule 19).
- [ ] Re-verified live in the browser by the persona run.

## Working notes

(append-only)

- 2026-08-21 persona-tester: found while entering the persona's two protection
  policies for the `/m` sweep. Not fixed by me — routed to build-lead.
- A throwaway `TESTCO` critical illness row was created to isolate fault 1 and has
  been **deleted** via the API (`DELETE /api/protection/policies/critical-illness/3`,
  200). The persona's two real policies remain.

- 2026-08-21 build-lead: **fixed**, three faults, one root cause each.

  **Fault 1 — mass assignment.** `BasePolicyRequest::commonRules()` is the one home
  for the shared policy fields, but each of the five models repeated the dates in
  `$fillable`/`$casts` and four had dropped `policy_end_date`. Replaced the five
  copies with one declaration: `app/Models/Concerns/RecordsPolicyDates.php`, merged
  in via `initializeRecordsPolicyDates()` on all five models. `policy_term_years` is
  deliberately NOT in the trait — `income_protection_policies` has no such column
  (that column asymmetry is why one path could not cover all five fields).

  **Fault 2 — the Life Insurance form.** `PolicyFormModal.vue` hid Start Date and
  Policy End Date behind `showStartDate`/`showEndDate`, which required a
  `life_policy_type` to have been chosen and excluded `family_income_benefit` and
  `whole_of_life` outright. Both computeds are gone; every policy type now shows
  both dates. `preparePolicyData()` assigned the three date fields in three separate
  branches that disagreed about nulling — now assigned once, before the branch.

  **Fault 3 — the stored date never displayed.** `PolicyDetail.vue:426` *derived*
  `policyEndDate` from start + term and ignored the stored column, so even a saved
  end date was invisible (and `isActive` said "Active" for expired cover). The
  recorded date is now the source of truth, term is the fallback.

  **Fyn / `/m` reach (Rule 19/20).** `CoordinatingAgent` parsed `policy_end_date`
  then dropped it from the critical-illness and income-protection payloads, and its
  read-back records for those two types omitted it; all four sites now read
  `RecordsPolicyDates::$policyDateFields`. `UpdateRecordAllowlist` allowed
  `policy_end_date` on `life_insurance` only — added to `critical_illness` and
  `income_protection`, following the W-0017/BUG-02 precedent in that file, so Fyn
  (the only protection entry route `/m` and native have) can set it.

  **Adjacent fix required to meet acceptance:** `UpdateCriticalIllnessPolicyRequest`
  was the only one of the five missing `nullable` on `policy_term_years`, so editing
  a critical illness policy with a blank term answered 422 and the end date could
  never be added to the persona's existing policy. Fixed.

  **Live verification (localhost:8000, David Jones 16):**
  - New critical illness policy via the form with end date 2040-01-01 →
    `critical_illness_policies.policy_end_date = 2040-01-01` (was NULL). Throwaway
    row deleted afterwards.
  - Persona critical illness policy 2 edited → `start=2020-01-01 end=2040-01-01`.
  - Persona life policy 7 edited → `start=2020-01-01 end=2040-01-01 term=20`.
  - Detail view: "End Date: 01/01/2040, Remaining Term: 14 years".
  - `/m` `/m/app/protection/policy/life/7` and `/criticalIllness/2` both show
    "End date 01/01/2040".

  **Tests:** `tests/Feature/Protection/PolicyDatesPersistTest.php` — 18 pass, and the
  critical-illness case was confirmed RED against HEAD's model before the fix. The
  first test is a standing guard: for each of the five models, every field
  `commonRules()` validates that exists as a column must be fillable.

  **Not done / flagged:** the Fyn create tool schema
  (`fyn-memory/procedural/tool_schema/protection/create_protection_policy.xai.md`)
  has no `policy_end_date` property, so on `/m` the date must be added in a second
  turn via `update_record`. Adding it to the corpus is a reviewed tool-catalogue
  change and would force regeneration of the Phase-4b "immutable" golden fixtures —
  left to the lead. Deleting a policy from its detail page is broken independently
  (`PolicyDetail.vue:589` maps to `/api/protection/life-insurance`, which is not a
  route, and `this.policyType` is undefined there) — reported, not fixed.
