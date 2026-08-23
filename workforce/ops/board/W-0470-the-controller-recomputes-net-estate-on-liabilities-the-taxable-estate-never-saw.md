---
id: W-0470
title: The controller recomputes the projected net estate on a liabilities figure the projected taxable estate was never struck on, so the two rows disagree on screen
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
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
