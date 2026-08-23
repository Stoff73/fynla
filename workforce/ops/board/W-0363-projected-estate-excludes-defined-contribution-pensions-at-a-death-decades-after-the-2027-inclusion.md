---
id: W-0363
title: The projected estate excludes defined contribution pensions at a death decades after they become chargeable in April 2027
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333.

`projected_iht_liability` models the **second death** — 36 years away for this
household, i.e. **2062**. From **6 April 2027** unused defined contribution pension
funds and death benefits form part of the estate (Autumn Budget 2024; the date is
already in config as `inheritance_tax.pension_iht_inclusion.effective_date`).

The projected estate excludes them entirely — `is_iht_exempt = true` is set at
`EstateAssetAggregatorService:172` with no date test — so **the headline projected
figure understates the liability by roughly 40% of the projected pot for every user in
the application.** This household holds £500,000 of pension.

The `pension_amendment` block bolted on at `IHTCalculationService:373` addresses only
the **current** estate, and its own arithmetic is defective (**W-0364**).

The configuration carries `effective_date` precisely so this can be decided by date.

## Acceptance

1. A projection whose modelled death falls after the effective date includes defined
   contribution pensions in the projected estate.
2. The date comes from `TaxConfigService`, never a literal (Rule 2).
3. If inclusion is deferred as a product decision, it is published as a **stated
   assumption** the user can see — not a silent exclusion.
4. **`tax-compliance-reviewer` on the fix.**
