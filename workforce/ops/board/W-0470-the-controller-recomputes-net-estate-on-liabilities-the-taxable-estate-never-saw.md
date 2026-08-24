---
id: W-0470
title: The controller recomputes the projected net estate on a liabilities figure the projected taxable estate was never struck on, so the two rows disagree on screen
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: null
reviewers: [tax-compliance-reviewer]
status: handoff
claimed_by: null
severity: medium
surfaces: [web]
created: 2026-08-23T20:40:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0465, W-0154]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found while browser-verifying W-0465, 2026-08-23 — reported rather than silently folded into that fix
---

## Intent

`IHTController::calculateIHT()` overwrites a figure the service computed:

```php
$calculation['projected_net_estate'] = $calculation['projected_gross_assets']
    - $projectedLiabilities
    - $calculation['projected_business_relief_deduction'];
```

with a comment saying to "let the service's projected_taxable_estate and
projected_iht_liability stand". **Those two are then struck on different
liabilities.** `$projectedLiabilities` comes from
`formatLiabilitiesBreakdown()` (mortgages paid off by age 70); the service's
own `projected_liabilities` comes from `projectLiabilities()`. Measured on
chris@fynla.org with a £6,000,000 business: **£3,500 against £0.**

On screen the projected column reads gross £10,669,753, liabilities −£3,500,
relief −£4,250,000, **Net Estate £6,416,253** — which reconciles — then
allowances −£217,000 and **Taxable Estate £6,202,753**, which is £3,500 out.
The user is shown a subtraction that does not work, on the row the whole page
exists to produce.

**This is a Rule 20 defect, not an arithmetic one.** There are two
implementations of "project this household's liabilities" and two of "net
estate", and the response mixes their outputs.

## Acceptance

1. ONE projected-liabilities mechanism, read by both the net estate and the
   taxable estate. Whichever is correct — the service's or the breakdown's —
   the other goes.
2. The controller's `projected_net_estate` overwrite is deleted, not corrected:
   the service publishes the figure and nothing downstream recomputes it.
3. Every row of the projected column reconciles with the row above it, verified
   on screen with a business over the relief cap (no persona can produce one —
   see W-0465).
4. `tax-compliance-reviewer` on the change: the liabilities figure feeds the
   £2,000,000 residence-band taper base, so choosing the wrong one moves tax.

## Working notes

- 2026-08-23 — Found browser-verifying W-0465, whose fix put the relief back
  into the overwrite. That made the Net Estate row reconcile and left this
  £3,500 gap visible one row down. **W-0465 deliberately did not widen into
  this** — changing which liabilities the projection uses is a change to the
  projection's liability model, and it moves the taper base.

- 2026-08-24 — **Ruled on by `tax-compliance-reviewer` (round four, F5): the SERVICE's
  figure is correct, and adopting the breakdown's would UNDERSTATE tax.** That ruling
  decided the fix, and the direction is why:

  The breakdown does not project at all. Mortgages get
  `($ageAtDeath >= 70) ? 0 : $userShare` — a binary cliff on a hardcoded age, off a
  hardcoded horizon of 85, reading no maturity date. Every other liability gets
  `'projected_balance' => $userShare`, **never amortised** — the source of the £3,500.
  `projectMemberLiabilities()` reads the real maturity date or estimates a payoff,
  amortises, returns zero for a debt ending before death, and runs on the household
  horizon the assets are grown to. That is the deductible liability at death
  (IHTA 1984 s5(3), s162, s175A).

  A larger liability means a smaller taper base, less taper, more residence band
  surviving — **less tax**. Making the column reconcile the other way would have moved
  tax in the one direction a compliance surface must not lean.

- 2026-08-24 — **Fixed, at BOTH call sites.** The overwrites of `projected_net_estate`,
  `projected_liabilities` **and** `total_liabilities` are deleted from
  `IHTController` and from `EstatePlanService`. The second site is W-0465 F2: the
  identical overwrite, on the other surface, which W-0465 had left behind — two of
  three implementations fixed is not a Rule 20 fix.

- 2026-08-24 — **F6 taken with it.** `total_liabilities` was overwritten with a
  breakdown total read from `Liability::where('user_id')` — one leg — where the engine
  reads `forUserOrJoint()`. A debt the user is joint owner of was inside the net estate
  and missing from the Liabilities row beside it, so the CURRENT column did not add up
  either.

- 2026-08-24 — **Browser-verified on a £6,000,000 business.** The projected column now
  reads 10,669,753 − 0 − 4,250,000 = **6,419,753** Net Estate, then −217,000 =
  **6,202,753** Taxable Estate. Every row follows from the one above it. Before this it
  was £10,666,253 against a taxable estate of £6,202,753 — out by the relief, then out
  again by £3,500.

- 2026-08-24 — **REMAINING, and it is visible on screen:** the per-liability detail rows
  still come from the breakdown, so the panel shows "Chris's Liabilities −£3,500" above
  a Total Liabilities of £0. The totals are right and the detail beneath them is not.
  Rebuilding `IHTFormattingService::formatLiabilitiesBreakdown()` on
  `projectMemberLiabilities()` is the other half of this item and is **not done**.
