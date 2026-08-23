---
id: W-0328
title: Product question — should Fynla support capped and offset mortgage rate types? They were in a validation rule but the column has never been able to store them
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: product-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T00:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0326]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

**Raised deliberately so that removing two values is not mistaken for deciding
against them.** W-0326 aligned `MortgageStore`'s `rate_type` list to the column
enum, which removed `capped` and `offset`.

**That removal was not a product decision and must not be read as one.**
`mortgages.rate_type` is `enum('fixed','variable','tracker','discount','mixed')`
and has never contained `capped` or `offset`, so a rule accepting them could only
ever produce a write that fails. They were unreachable whatever anyone wants —
removing them changed nothing a user could do.

**The genuine question is separate: should the application support them?**

Capped-rate and offset mortgages are real UK products:

- **Capped rate** — variable, but with a ceiling it cannot pass.
- **Offset** — savings balances net off the mortgage balance for interest.

Neither can be recorded today. A user with an offset mortgage must currently
choose `variable` or `tracker` and lose the distinction, which matters because an
offset changes the interest actually paid and a cap changes the rate-shock
exposure the alerts key off.

## Acceptance

1. A decision from CSJ: supported, or explicitly not.
2. If supported, the work is a migration widening the enum, the option in the
   property form's rate-type select, the value added to all three form requests
   AND `MortgageStore`, and whatever the interest calculation should do
   differently for an offset. **Five layers, per W-0329 — not one.**
3. If not supported, record it here so the next sweep does not re-raise it.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-columns`): raised on team-lead's explicit
  instruction not to conflate "currently unstorable" with "unwanted". The
  distinction is the point of the item.
