---
id: W-0511
title: The Blind Person's Allowance is configured, editable and displayed — and never applied to anybody's tax
mission: M-0002-persona-fidelity
branch: fix/w-0485-blind-persons-allowance-not-in-adjusted-net-income
owner: null
reviewers: [tax-compliance-reviewer]
status: done
closed: 2026-08-29
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

## Resolution — 2026-08-28

**Shipped alongside W-0485 on CSJ's decision**, so no registered-blind user's tax moves the
wrong way in between. W-0485 alone removed the unearned Personal Allowance uplift and left
nothing in its place; the two land together.

**The allowance is given in one place, and both calculator paths reach it.**
`IncomeTaxBands::withBlindPersonsAllowance()` raises the Personal Allowance and carries the
basic-rate and additional-rate limits up with it by the same amount. The band width does
not change — it starts higher, because taxable income is lower by the allowance. The
additional-rate threshold moves too: ITA 2007 s10 states it against **taxable** income, and
the class comment that holds it still only holds while the Personal Allowance is fully
withdrawn, which the Blind Person's Allowance never is.

It is applied strictly **after** the taper, so it cannot leak into adjusted net income —
that was W-0485, and doing it there produced relief nobody was entitled to instead of the
relief they were. `UKTaxCalculatorTaperedBandTest` and a dedicated case both hold the
Personal Allowance at £7,570 for a registered-blind user on £110,000.

**Entitlement is answered once.** `TaxConfigService::blindPersonsAllowanceFor(?User)` is the
only place `is_registered_blind` is turned into a figure. The five production callers pass
it: `UserProfileService` (both the detailed and the simple path), `PersonalAccountsService`,
`CoverageGapAnalyzer` (the user's own entitlement and the spouse's, separately), and
`ResolvesIncome`. `IncomeDefinitionsService` reads the same seam, so the figure the panel
prints is the figure the calculator gave.

**Acceptance 2 — the fallback.** `?? 2870` is gone. It was a stale year's figure, so an
unconfigured year granted the wrong allowance silently, and it was a hardcoded tax value
(Rule 2). Zero replaces it: an unconfigured year under-grants visibly rather than
over-granting invisibly, and every seeded year sets the key. A test asserts the returned
figure IS the configured one.

**Acceptance 3 — transferable surplus: NOT modelled, deliberately.** ITA 2007 s39 allows a
registered-blind person to transfer unused allowance to a spouse or civil partner. The
decision not to model it is stated at the line in `blindPersonsAllowanceFor()`. It
under-relieves only a household whose registered-blind member has income below the
allowance, and modelling it needs a spouse's computation the seam has no access to.

**Acceptance 4 — before and after, income tax, all three rates.** Each is a test asserting
the size of the movement, not merely its direction:

| Income | Relief | Why that figure |
|---|---|---|
| £30,000 | allowance × 20% | the allowance leaves the basic-rate slice untaxed |
| £70,000 | allowance × 40% | allowance and both limits move together, so relief is at the marginal rate rather than 20% |
| £200,000 | allowance × 45% | the additional-rate threshold moves too, so none is clawed back at the band edge |

At the 2026/27 figure of £3,250 that is £650, £1,300 and £1,462.50 a year. The figure moves
**down** in every case.

**Acceptance 5 — a registered-blind fixture reaching the calculator**, not only the
definitions service. `registeredBlindUser()` now feeds `UKTaxCalculator` directly.

**Rule 19.** The tax engine is shared by architecture, so every surface gets the corrected
figure. The income-definitions panel is web-only — there is no `/m` counterpart to update
(no match for `blind` or `IncomeDefinitions` under `resources/mobile/`).

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`in_review`.

- **Delivered by:** Stoff73
- **Evidence:** commit `9e304da01` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
