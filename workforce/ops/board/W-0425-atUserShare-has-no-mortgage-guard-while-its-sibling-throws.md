---
id: W-0425
title: atUserShare will silently return the wrong share for a mortgage, while its sibling userShareFraction throws for exactly that case
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md
owner: build-lead
status: done
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T02:55:00Z
claimed: 2026-08-26
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0228, W-0238]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found while choosing a reader for W-0421. **Not currently reachable — raised
because the guard that would keep it unreachable already exists next door.**

`CalculatesOwnershipShare::userShareFraction` throws `FinancialCalculationException`
when handed a mortgage, with a docblock explaining why: a mortgage's share follows
the property securing it (W-0228), and a probe built from the mortgage's own
columns discards `property_id` and returns *"a confidently wrong fraction with
nothing to indicate it"*.

**`atUserShare`, in the same trait, has no such guard.** It routes to
`calculateUserShare`, which reads the record's own `ownership_type` /
`ownership_percentage`. Handed a mortgage it returns the pre-W-0228 answer.

On this household that is the difference the ruling was about: `mortgages.id=16`
says joint 50%, its property says tenants-in-common 40%, so `atUserShare` would
return **£60,000** where the correct share is **£48,000**.

**Callers today are safe:** `SavingsAgent:92` and `InvestmentAgent:116`, both
account collections. Nothing passes mortgages.

## Acceptance

`atUserShare` refuses a mortgage the way `userShareFraction` does, with the same
message pointing at `calculateUserMortgageShare`. A test passes it a mortgage and
asserts the throw — and asserts the two existing account callers still work,
since an over-broad guard would empty two agents' analyses.

## Notes

Trait is shared and several agents were live in it on 2026-08-23; sequence this
after the batch rather than during it.

---

## Fixed 2026-08-26

`atUserShare` now refuses a mortgage with the same message as `userShareFraction`,
pointing at `calculateUserMortgageShare`.

**The guard is extracted rather than copied.** Writing it twice would have left two
statements of one rule in the same trait — and the reason this item exists is that
the rule was stated in only one of two places that needed it. It is now
`refuseRecordWhoseShareFollowsAnother()`, called by both. The message is shared, so
the probe-specific wording moved into `userShareFraction`'s docblock where it
belongs.

Both halves of the acceptance are tested:

- A mortgage is refused by **both** methods, asserted in one loop so they cannot
  drift apart, checking the message names `calculateUserMortgageShare` and W-0228.
- `atUserShare` still answers for an account collection — 60/40 on a joint
  `InvestmentAccount`, plus an assertion that the underlying record is untouched,
  since these are presentation copies (W-0238). An over-broad guard would have
  emptied `SavingsAgent` and `InvestmentAgent`.

Verified against the live callers rather than the unit fixture alone:
`tests/Unit/Agents`, `tests/Feature/Savings`, `tests/Feature/Investment`,
`tests/Unit/Traits` — 205 passed. Plus 573 across Estate, NetWorth and Property,
and 177 Architecture. Pint clean.

Still not reachable — `SavingsAgent:92` and `InvestmentAgent:116` remain the only
callers and both pass account collections. The point was the guard, not a live bug.
