---
id: W-0367
title: Gift values are taken gross, so none of the lifetime exemptions that reduce a chargeable transfer are applied, overstating tax in every case
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: review
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

- 2026-08-31 build-lead: **MECHANISM BUILT AND PROVEN; NOT SWITCHED ON. Left at `review` deliberately — this is a partial, and calling it done would be false.**

  **Confirmed live:** `FailedGiftTaxCalculator:120` takes `(float) $gift->gift_value` gross. No lifetime exemption reduces a chargeable transfer, so a donor giving exactly the annual allowance in a tax year — nothing chargeable at all — has the whole amount cumulated against their nil rate band.

  **Built: `app/Services/Estate/GiftAnnualExemption.php`**, IHTA 1984 s19, with 7 passing tests. **No new data was needed**, which is why this could be built rather than deferred like W-0527: s19 allocates chronologically within a tax year, so `gift_date` and `gift_value` are sufficient. A stored allocation would go stale the moment a gift was edited.

  Two rules that are easy to get subtly wrong, both configured and both tested:
  - **The year starts 6 April.** 5 and 6 April are different tax years; treating it as calendar gives a donor two allowances in one year or one across two.
  - **One year carries forward and the CURRENT year is spent first** — the configured note says so, and the order matters: spending the brought-forward allowance first would let it survive into a year it has already expired in.

  Not handled, each because the application does not record the fact it turns on: small gifts (s20 — who the recipient is), gifts in consideration of marriage (s22), normal expenditure out of income (s21 — whether a regular pattern exists; that is W-0525).

  **Why it is NOT wired in.** Switching it on changes the chargeable value of every gift, and therefore every household's cumulation and band. It turned **ten assertions in `FailedGiftTaperReliefTest` red** — each derived from a specific statutory figure. I tried grossing the fixtures up by the allowance; that works for single-gift cases and breaks the multi-gift ones, because the allowance is SHARED across gifts in a year. Re-deriving ten Inheritance Tax figures at speed is how a wrong bill ships, and **acceptance 4 requires a `tax-compliance-reviewer` pass that has not happened.**

  **The remaining work, precisely:** re-derive that suite against net values; wire the service in **BEFORE the window filter** — an out-of-window gift still consumed its year's allowance, so excluding it would hand that allowance to a later gift twice; take the review. The instruction is recorded at `FailedGiftTaxCalculator:113-130` so the next reader finds it at the site, not only here.
