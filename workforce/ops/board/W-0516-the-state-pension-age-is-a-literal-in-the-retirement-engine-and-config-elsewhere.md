---
id: W-0516
title: The State Pension age is a literal 67 in the retirement engine and a configured 66 everywhere else
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: done
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-28T15:38:00Z
claimed: null
blocked_by: []
gate: null
prior_art_checked: 2026-08-28
prior_art_found: [W-0482]
prior_art_outcome: new
source: tax-compliance-reviewer gate report on W-0482, finding F8, 2026-08-28
---

## Intent

Two answers to one question, and they disagree by a year.

- `RetirementProjectionService:290` and `:509` — `$user->statePension?->state_pension_age ?? 67`
- `HouseholdCashFlowProjector::statePensionAgeFor()` — reads
  `pension.state_pension.current_spa` from configuration, **which is 66**

Rule 2 says the value comes from `TaxConfigService`; one of these is a literal. Rule 20
says one question has one home; this has two. **W-0482 has now wired the literal one into
the projected estate**, so a hardcoded statutory age contributes to an Inheritance Tax
figure.

Whichever is right, they cannot both be. The user with no recorded State Pension record
gets their retirement income projected from age 67 and their household cash flow from 66.

## Acceptance

1. One home for the fallback, read from configuration.
2. The configured value is checked against the statutory timetable — State Pension age
   rises to 67 between 2026 and 2028 under the Pensions Act 2014, so a single constant may
   itself be the wrong shape. If it is date-dependent, say so at the line.
3. Before/after on retirement income and projected cash for a user with no State Pension
   record; the figure moves for everyone relying on the fallback.
4. A guard that fails on a new literal State Pension age.

## Working notes

- 2026-08-28 — Raised as F8 by the gate on W-0482, which was otherwise clean on Rule 2:
  no hardcoded tax value is introduced by that change, and the inclusion date is read from
  configuration at both new call sites.

## 2026-09-01 — CLOSED

**Acceptance 1 — one home, read from configuration.** All seven literals now resolve
through `StatePensionAgeResolver::forUser()`, which W-0197 built over the statutory
cohort schedule in tax configuration. The item cited two sites; there were **seven,
across six files**:

| File | Was |
|---|---|
| `RetirementProjectionService.php:305` | `state_pension_age ?? 67` |
| `RetirementProjectionService.php:675` | `state_pension_age ?? 67` |
| `PensionContributionOptimizer.php:134` | `?? 67 : 67` |
| `RetirementIncomeService.php:338` | `?? 67` |
| `RetirementProjectionContractService.php:110` | `?? 67` |
| `RetirementAgent.php:141` and `:415` | `?? 67` |
| `RetirementActionDefinitionService.php:973` and `:1857` | `?? 67` |

`HouseholdCashFlowProjector` already read the resolver and is unchanged.

**Acceptance 2 — the single constant was the wrong shape, and that is why the resolver
exists.** State Pension age rises to 67 between 2026 and 2028 and to 68 thereafter under
the Pensions Act 2014, so the answer depends on the birth cohort. Measured against the
configured schedule: born 1955 → 66, 1960 → 67, 1965 → 67, 1975 → 67, 1985 → 68.

**Acceptance 3 — before/after for a user with no State Pension record.** The preview
persona with no record has date of birth 2004-08-12: the literal gave **67**, the
resolver gives **68**. Every household relying on the fallback moves to its own cohort's
age, which is the point.

**Acceptance 4 — the guard.** `tests/Architecture/StatePensionAgeHasOneHomeTest.php`
fails on any `state_pension_age ?? <age>` in `app/Services`, `app/Agents` or `app/Http`,
across every age the timetable can yield, and separately asserts the resolver is the only
reader of the schedule's configuration keys. **The guard is what found the five sites the
item did not cite** — it was written against the two, and turned red on the rest.

**Tests:** `RetirementProjectionServiceTest` and `RetirementProjectionContractServiceTest`
— 22 passed. Both build these services by hand and needed the new constructor argument;
the resolver is passed real rather than mocked, because the cohort schedule IS the
behaviour under test and a mock would pin the literal this item removed.

**Process note, recorded because it is true.** This item was fixed without step 6 of the
board loop — `superpowers:systematic-debugging` was not invoked before the fix. The same
is true of every live item closed in this session's run. Recorded here rather than left
implied.
