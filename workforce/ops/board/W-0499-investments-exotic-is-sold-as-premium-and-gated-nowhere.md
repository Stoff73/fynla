---
id: W-0499
title: investments_exotic is advertised as a Premium feature and enforced nowhere, so a free user is not actually prevented from using it
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [product-lead, compliance-lead]
status: open
claimed_by: null
severity: medium
surfaces: [web, m, ios]
created: 2026-08-26T00:00:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-26
prior_art_found: [W-0054, W-0463, W-0498]
prior_art_outcome: extends
source: found while auditing the capability-gate family for W-0054, 2026-08-26
---

## Intent

`investments_exotic` is `none` on free and `full` on premium in the capability
matrix, and it is **sold**: `PaymentController:116` offers it to prospective
customers as *"Advanced investment types"* and `TierComparisonService:22` lists it in
the tier comparison as *"Alternative investments"*.

**Nothing enforces it.** Every mention in the repository:

| Location | What it does |
|---|---|
| `PaymentController.php:116` | advertises it in the upgrade copy |
| `TierComparisonService.php:22` | lists it in the tier comparison table |
| `TierConfigurationSeeder.php:44,87` | sets it `none` / `full` |

There is no route in `tierAccess.js`'s `ROUTE_CAPABILITY`, no entry in
`CheckSubscription`'s route map, no `TeaserGate` consumer, and no store check. A
free-tier user is not prevented from doing whatever this names.

## Why this is not W-0054

W-0054 is about **which shape** a gate takes — before entry or after submit. This
capability has **no gate of either shape**, so it is a different defect that the
W-0054 audit happened to walk into.

It is the shape of W-0463 and W-0498 — a configured rule nothing reads — with one
difference that makes it worse than either: **this one is quoted to customers in the
pricing comparison.** A feature named in a paid tier's differentiators and available
without paying is a commercial and arguably a fair-trading problem, not only a tidiness
one.

## The question this has to answer first

**What does `investments_exotic` actually mean?** Nothing in the code says. Candidates,
from the labels: an `account_type` or holding class on `InvestmentAccount` (VCT, EIS,
unlisted, crypto), or `business_interests`, or `chattels`. Until that is settled it
cannot be gated, and a guess would gate the wrong thing for paying and non-paying
users alike.

That is why `product-lead` is a reviewer here. `compliance-lead` too, because the
answer decides whether the pricing page currently describes the product accurately.

## Acceptance

1. `investments_exotic` is defined — which records or actions it covers, recorded
   where the next reader will find it.
2. Either it is enforced consistently with the family (route-level teaser via
   `tierAccess.js` where it is a destination; a before-entry check where it is an
   action), **or** it is removed from the capability matrix AND from both pieces of
   customer-facing copy. It must not keep being sold while ungated.
3. Whichever way, `ConfiguredRulesHaveConsumersTest` covers it afterwards — asserting
   a consumer exists, or listing it as a deliberate exception with the reason.
4. If it has been ungated in production, establish for how long and whether any free
   user relied on it, before anything is switched off under them.

## Related

- **W-0054** — the gating-philosophy audit that found this.
- **W-0463 / W-0498** — the same "configured and unread" shape, without the
  customer-facing half.
