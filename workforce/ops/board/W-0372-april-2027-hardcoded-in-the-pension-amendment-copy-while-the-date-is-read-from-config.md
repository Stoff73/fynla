---
id: W-0372
title: April 2027 is hardcoded in the pension amendment copy while the same date is read from configuration four lines above
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: 2026-08-26
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

---

## Fixed 2026-08-26

`calculatePensionAmendmentScenario()` reads `$effectiveDate` from
`inheritance_tax.pension_iht_inclusion.effective_date` and publishes it as its own
field. Three pieces of prose beside it restated it as a literal and now interpolate
it instead:

| Site | Was | Now |
|---|---|---|
| `post_2027_rules.description` | "From April 2027, …" | `$effectiveDate->format('F Y')` |
| `impact_summary`, additional tax | "The 2027 pension amendment …" | `$effectiveDate->format('Y')` |
| `impact_summary`, no additional tax | "The 2027 pension amendment …" | `$effectiveDate->format('Y')` |

**`post_2027_rules` is deliberately NOT renamed.** It is a published API key that
web, `/m` and native all read, so it is an identifier rather than copy. Renaming it
would break three clients to fix a sentence. Said in the method docblock so the next
reader does not "finish the job".

Pinned by `PensionAmendmentCopyReadsTheConfiguredDateTest`: move the configured date
to 2028-04-06 and the copy follows it, with an explicit assertion that "April 2027"
is gone. Before the fix the published field moved and the sentence did not, which is
the whole defect.

514 passed across Estate, 177 Architecture, Pint clean.

## Residual, reported not fixed

`AssetLiquidityAnalyzer.php:148` — *"Pensions are currently NOT part of your taxable
estate for Inheritance Tax (this may change from April 2027)"*. Same drift risk,
different shape: that class has **no constructor and no dependencies**, and is a flat
lookup table of constant advisory strings. Interpolating there means injecting
`TaxConfigService` into a dependency-free class, which is a design change rather than
the copy edit this item asks for. It also hedges — "may change from" — so it does not
assert an effective date the way the fixed strings did.

Acceptance 2 (**W-0363** — the date should also *drive* pension inclusion, not merely
describe it) is untouched and remains W-0363's.
