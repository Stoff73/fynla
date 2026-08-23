---
id: W-0254
title: A projection compounded £1,667 a month of ISA subscriptions the user never entered, while the same card printed "Monthly Contribution —"
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0024-cycle4-investment-projection.md
owner: build-lead
status: done
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:00:00Z
claimed: 2026-08-22T20:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CERTIFIED 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [ContributionEstimatorService, F-0018-recorded-figure-wins, InvestmentProjections.vue]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing W-0251; it is the other half of W-0217.

`ContributionEstimatorService` never read `monthly_contribution_amount`. For an ISA with no
recorded subscription it returned the **full ISA allowance ÷ 12 — £1,667 a month, every
month, for thirty years.** For a general investment account it invented 5% of the balance
annually, and did so on the **full** value of a joint account while the projection started
from the user's **half**.

**This was not an assumption being overridden — it was one the same card already
contradicted.** `InvestmentProjections.vue:601-609` printed "Monthly Contribution —" from
`monthly_contribution_amount` while the chart eight pixels away compounded £1,667 a month.
Two mechanisms, one question, opposite answers.

F-0018 settled the principle in this same run: *a rule of thumb is evidence about people in
general; a recorded figure is evidence about this person, and it wins.*

Impact: Sarah's £85,000 with no contributions recorded projected to **£1,577,731** over 36
years. £720,144 of that was money she never said she would save.

## Acceptance

1. A projection contains no pound the user did not record. ✓
2. The card's contribution figure and the projection's are one number. ✓
3. The estimate does not move with the account balance. ✓

## Working notes

- Chain is now: what-if override → recorded regular contribution at its stated frequency →
  contributions already made this tax year, annualised → **nothing**.
- The frontend copy is deleted; the card reads the projection's own
  `estimated_monthly_contribution`.
- `TaxConfigService` dependency and the ISA-allowance and percent-of-value fabrications are
  removed with it.
- **The old test suite asserted the defect** ("estimates ISA contribution from allowance
  when no subscription data ... expect ~1666") and is rewritten.
- Sarah's 36-year p20: £1,577,731 → **£261,740**.
