---
id: W-0531
title: The emergency runway divides cash by a household total that omits the mortgage, council tax and utilities — overstated up to 4.7x for every mortgaged user
mission: board-verification-31-august
owner: build-lead
status: done
severity: high
surfaces: [web, m]
source: found while working W-0488, 2026-09-01
prior_art_checked: 2026-09-01
prior_art_found: [W-0488, W-0495]
prior_art_outcome: none — W-0495 fixed the "no expenditure" case, not the basis
constitution_refs: [07-quality-bar]
---

## Intent

Two figures answer "what does this household spend each month", and one of them is
wrong wherever there is a mortgage.

`users.monthly_expenditure` and the manual expenditure columns hold **no housing
line at all** — there is no mortgage, council tax, utilities or maintenance column
on `users`. Those live on `mortgages.monthly_payment` and the property record, and
are only reachable through `UserProfileService::getFinancialCommitments()`.

- **Right:** `UserProfileService::getExpenditureBreakdown():283-323` sums manual
  expenditure **plus** commitments, and is documented as matching the Expenditure
  tab the user reads.
- **Wrong:** `ResolvesExpenditure::resolveMonthlyExpenditure():22` returns the
  manual column and stops. `SavingsAgent:104` feeds that into
  `EmergencyFundCalculator::calculateRunway():26`, which divides cash by it.

A second, quieter disagreement sits inside the manual half: the trait always reads
`users.monthly_expenditure`, where the breakdown respects
`users.expenditure_entry_mode` and sums the category columns in `category` mode.
Every preview persona is in `category` mode, so the two disagree even before
commitments are counted (peak_earners: 1,385 vs 1,225).

## Measured, all preview personas, 2026-09-01

| persona | manual (categories) | commitments | `users.monthly_expenditure` |
|---|---:|---:|---:|
| entrepreneur | 4,500 | 10,213 | 5,500 |
| peak_earners | 1,225 | 4,548.87 | 1,385 |
| retired_couple | 1,065 | 1,648 | 1,330 |
| student | 340 | 55 | 750 |
| young_family | 1,951 | 2,414.50 | 2,400 |
| young_saver | 1,033 | 578.33 | 1,833 |

Commitments exceed manual expenditure for four of the six. Runway shipped vs
correct, from W-0488's measurement: peak_earners 83.27 → 17.67 months;
retired_couple 97.18 → 38.15; young_family 8.18 → 3.65.

## Reach

`ResolvesExpenditure` has around twenty consumers, so the basis change is not
confined to the runway: risk scoring (`AutoRiskCalculator:469`), life-event
affordability (`LifeEventAllocationService:586`), goal affordability, the plan
services, the Fyn advice prompt and the KYC gate all read it. That reach is the
reason it is one item and not twenty — Rule 20.

## Acceptance

1. `resolveMonthlyExpenditure()` returns the same household total the Expenditure
   tab shows, commitments included, and respects `expenditure_entry_mode`.
2. The addition lives in **one** place — the trait delegates to the breakdown
   rather than re-summing.
3. **W-0495 is not undone:** a user who has recorded no expenditure still resolves
   `source: 'none'`, `amount: 0.0`, so the runway stays `null` — "cannot be
   calculated", not a figure derived from a mortgage alone.
4. A guard that reads the source and fails if the trait goes back to the bare
   column, mutation-verified.
5. Runway re-measured on the personas after the fix.

## Outcome — done, 2026-09-01

`ResolvesExpenditure::resolveMonthlyExpenditure()` now delegates to
`UserProfileService::getExpenditureBreakdown()` (made public at
`app/Services/UserProfile/UserProfileService.php:288`) and adds the commitments the
breakdown already computes. `app/Traits/ResolvesExpenditure.php:53-79`.

Three things fell out of routing through the breakdown rather than re-summing:

- `expenditure_entry_mode` is now respected. The chain used to read
  `users.monthly_expenditure` even for a user in `category` mode, so it disagreed
  with the tab on the manual half as well (peak_earners 1,385 vs 1,225).
- A user in `category` mode with nothing in the categories keeps the figure on the
  column — `:57-63`. Without that, switching mode without filling the form in would
  have turned a recorded figure into "nothing recorded".
- `source` still answers only "has the user recorded their expenditure", so
  acceptance 3 holds: mortgage alone still resolves `none` / `0.0`.

Re-measured, all preview personas, after the fix:

| persona | resolved monthly | cash | runway |
|---|---:|---:|---:|
| entrepreneur | 14,713.00 | 169,180 | 11.5 |
| peak_earners | 5,773.87 | 74,750 | 12.95 |
| retired_couple | 2,713.00 | 74,250 | 27.37 |
| student | 395.00 | 1,200 | 3.04 |
| young_family | 4,365.50 | 11,700 | 2.68 |
| young_saver | 1,611.33 | 10,700 | 6.64 |

Tests: `tests/Unit/Traits/ResolvesExpenditureIncludesCommitmentsTest.php` — 6 passed.
Two of the six read the trait file itself, and the pair was mutation-verified:
restoring `'amount' => (float) $user->monthly_expenditure` turned 3 of 6 red,
including the source guard. Consumer suites re-run green: Savings, Risk, Goals,
Coordination, Plans, UserProfile, Mobile, Agents — 626 passed across the three runs.

**Not done:** no browser verification on web or `/m`. The change is entirely
behind the shared API — `/m` reads the same `SavingsAgent` payload — so there is no
per-surface frontend work, but neither surface was driven.
