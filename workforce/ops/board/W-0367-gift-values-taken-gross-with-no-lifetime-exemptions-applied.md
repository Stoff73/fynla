---
id: W-0367
title: Gift values are taken gross, so none of the lifetime exemptions that reduce a chargeable transfer are applied, overstating tax in every case
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: extend
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333.

`IHTCalculationService:1749-1768` takes `Gift::where(...)->sum('gift_value')` — the raw
figure. None of the exemptions that reduce a chargeable transfer are applied:

| Statute | Exemption |
|---|---|
| IHTA 1984 s19 | annual exemption £3,000, plus one year's carry-forward |
| s21 | normal expenditure out of income |
| s20 | small gifts, £250 per donee |
| s22 | gifts in consideration of marriage — £5,000 / £2,500 / £1,000 |
| s18 / s23 | spouse and charity exemptions on lifetime gifts |

**Every one of these reduces the band consumed, so the omission overstates tax in
every case.**

`TaxConfigService::getGiftingExemptions()` and `getNormalExpenditureFromIncome()` are
both populated and **neither is called from this path.**

## Acceptance

1. A gift's chargeable value is net of the exemptions that apply to it.
2. Every threshold from `TaxConfigService` (Rule 2).
3. Before/after on a household with gifts spanning several exemption types.
4. **`tax-compliance-reviewer` on the fix.**
