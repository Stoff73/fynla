---
id: W-0372
title: April 2027 is hardcoded in the pension amendment copy while the same date is read from configuration four lines above
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333.

`IHTCalculationService:1912` and the `impact_summary` beside it print *"From **April
2027**, unused defined contribution pension pots will be included…"*

`$effectiveDate` is read from `inheritance_tax.pension_iht_inclusion.effective_date`
(`2027-04-06`) at `:1873` and returned as a separate field — and then the description
the user actually reads restates it as a literal.

Same family as **W-0371**; filed separately because it is a date rather than a rate and
will be missed by a sweep for `%`.

## Acceptance

1. The copy interpolates `$effectiveDate`.
2. Related: **W-0363** — the date should also *drive* whether the projected estate
   includes pensions, not merely describe it.
