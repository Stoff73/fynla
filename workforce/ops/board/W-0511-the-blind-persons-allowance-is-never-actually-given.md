---
id: W-0511
title: The Blind Person's Allowance is configured, editable and displayed — and never applied to anybody's tax
mission: M-0002-persona-fidelity
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-28T14:55:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-28
prior_art_found: [W-0485, W-0205]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found while closing W-0485 — the sweep for other consumers returned none
---

## Intent

`users.is_registered_blind` is read in **one place in the application**:
`IncomeDefinitionsService`, where W-0485 has just removed it because ITA 2007 s58 does
not deduct it. Sweeping for any other consumer returns:

- `app/Models/User.php:147` — a cast
- `app/Http/Resources/UserResource.php:102` — published
- `app/Http/Requests/UpdatePersonalInfoRequest.php:85` — validated
- `app/Services/Onboarding/OnboardingService.php:405` — captured at onboarding
- `TaxConfigService::getBlindPersonsAllowance()` — a getter with no caller left
- `Admin/TaxSettings.vue` — an admin can edit the figure
- `IncomeDefinitionsPanel.vue` — it is shown to the user

**Nothing computes tax with it.** `UKTaxCalculator` has never heard of it.

So the app asks whether the user is registered blind, stores it, publishes it, lets an
administrator maintain the rate, prints the amount on the income page — and then taxes
them as though they had no allowance at all. **ITA 2007 s38 gives the allowance against
income; s23 Step 3 is where it is deducted.** At the 2026/27 figure of £3,250 the
under-relief is £650 at the basic rate, £1,300 at the higher rate and £1,462.50 at the
additional rate, every year, for every registered-blind user.

**W-0485 did not cause this, and does not fix it.** Before W-0485 the allowance was
applied in the wrong place — it reduced adjusted net income, which raised the tapered
Personal Allowance and suppressed the High Income Child Benefit Charge. That was two
wrong answers, not relief. W-0485 removes the wrong application; this item supplies the
right one.

**Ship them together if the net effect on a registered-blind user's tax figure matters.**
W-0485 alone moves their computed tax UP: they lose the unearned Personal Allowance uplift
(worth about £650 at £110,000) and still get no allowance. That is a defensible interim —
the Personal Allowance and the Child Benefit charge become correct, which is what W-0485
was raised for — but it is not the whole answer and should not be read as one.

## Acceptance

1. The allowance reduces the income charged to tax at s23 Step 3, in whatever computes a
   user's income tax — not adjusted net income, not threshold income, not adjusted income.
2. The rate comes from `TaxConfigService` (Rule 2). The `?? 2870` fallback in
   `getBlindPersonsAllowance()` is a stale year's figure and should be reviewed with it.
3. Surplus allowance transferable to a spouse or civil partner where the recipient's own
   income cannot use it (ITA 2007 s39) — or a stated decision not to model it, at the line.
4. Before/after income tax for a registered-blind user at the basic, higher and additional
   rates. The figure moves DOWN in every case; state by how much.
5. A registered-blind fixture reaching the tax calculator, not only the definitions
   service. W-0485 added one for `IncomeDefinitionsService`; this needs the calculator's.
6. `tax-compliance-reviewer` — it moves tax for every registered-blind household.

## Working notes

- 2026-08-28 — Found by asking the W-0485 gate the question its own change raised: if the
  allowance is no longer deducted at s58, where IS it given? The answer was nowhere.
- 2026-08-28 — `TaxConfigService::getBlindPersonsAllowance()` has **no caller** once
  W-0485 lands. Do not delete it as dead code; it is the seam this item needs.
