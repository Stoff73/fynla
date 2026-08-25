---
id: W-0425
title: atUserShare will silently return the wrong share for a mortgage, while its sibling userShareFraction throws for exactly that case
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md
owner: unassigned
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T02:55:00Z
claimed: null
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
