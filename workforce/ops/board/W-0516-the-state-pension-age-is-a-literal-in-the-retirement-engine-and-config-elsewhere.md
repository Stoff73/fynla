---
id: W-0516
title: The State Pension age is a literal 67 in the retirement engine and a configured 66 everywhere else
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
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
