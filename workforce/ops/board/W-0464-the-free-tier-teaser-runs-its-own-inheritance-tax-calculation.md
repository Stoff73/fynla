---
id: W-0464
title: The Free-tier estate teaser runs a second, independent Inheritance Tax calculation that ignores pooling, gifts, charitable exemption, the residence cap and the taper
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: main-inference
reviewers: [tax-compliance-reviewer, product-lead]
status: done
claimed_by: null
severity: high
surfaces: [m, web]
created: 2026-08-23T13:20:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0154, W-0463]
prior_art_outcome: extend
constitution_refs: [07-quality-bar, 05-perimeter]
source: found while verifying W-0154 reached /m — the /m estate screen shows an Inheritance Tax liability only in teaser mode, and tracing where that figure comes from surfaced a second calculation
---

## Intent

`app/Services/Tiers/EstateIhtExposureDetector::detect()` computes an Inheritance Tax
liability of its own:

```php
$threshold = $nrb + $rnrb;                                   // single person, always
$netWorth  = $this->netWorthService->calculateNetWorth($user);   // this user alone
$estimatedLiabilityGbp = max(0.0, ($netWorth - $threshold) * $ihtRate);
```

**This is a second mechanism answering the question `IHTCalculationService` exists to
answer (Rule 20).** It reads its rates from `TaxConfigService` correctly — Rule 2 is
not the problem here — but the model behind it shares nothing with the real one:

| | `IHTCalculationService` | `EstateIhtExposureDetector` |
|---|---|---|
| Household | pools both spouses' estates | the logged-in user alone |
| Allowances | doubled where the household pools | single person, always |
| Gifts in the last 7 years | reduce the band, per member, capped | ignored |
| Charitable legacies | exempt, and can move the rate to 36% | ignored |
| Residence band cap (s8E(2)) | capped at the net value of the home | ignored |
| £2m taper | applied | ignored |
| Business Property Relief | capped relief (W-0091) | ignored |

**Where it is seen.** The `/m` estate screen renders it as *"Estimated Inheritance Tax
liability"* — the headline figure — for every Free user
(`resources/mobile/views/modules/Estate.vue:15-16`). In full (Premium) mode `/m`
shows estate value and composition and **no liability at all**, so this is the only
Inheritance Tax number `/m` ever displays.

**It is not obviously wrong to have a rough teaser.** The figure is deliberately a
simplification for a tier that cannot see the full calculation, and W-0154's fix does
not reach it — a household that is now correctly quoted one figure on web can still
see a different one on `/m` Free. Whether that gap is acceptable is a **product
decision**, which is why this is filed rather than quietly consolidated.

## Acceptance

1. **A decision, recorded:** does the Free teaser show (a) the real household figure,
   (b) a deliberately simplified figure that is labelled as an estimate in terms a
   user can act on, or (c) no figure at all? Today it shows an unlabelled precise-
   looking number derived from a model the application does not otherwise use.
2. If (a) or (b), the arithmetic comes from `IHTCalculationService` — one mechanism,
   with the teaser choosing what to DISPLAY rather than recomputing (Rule 20).
3. Whatever is shown, a married user's teaser and their web figure must not disagree
   without the difference being explained on screen — that is W-0154's acceptance 1
   applied across surfaces.
4. `tax-compliance-reviewer` on the wording: an unqualified "Estimated Inheritance Tax
   liability" of £X is a figure a user may act on.

## Working notes

(append-only)

- 2026-08-23 — Raised while verifying W-0154 reached `/m` (Rule 19). Not fixed in that
  batch deliberately: consolidating it changes what a whole pricing tier is shown, and
  that is CSJ's call rather than a fix-batch decision.


## Resolution — 2026-08-23

**CSJ answered acceptance 1 and made it a standing rule, not a per-item decision:**

> the /m must not do anything different other than show in an iFrame for mobile.
> It MUST NEVER work anything out.

So option (a): `/m` shows the figure the engine computed. `EstateIhtExposureDetector`
now calls `IHTCalculationService::calculate()` with the spouse and sharing flag
resolved exactly as `IHTController` resolves them, and decides only what to display.

**The performance rationale the old code carried — *"intentionally avoids running the
full Estate engine"* — is answered by the engine's own cache:** `calculate()` returns
a stored result unless the assets or liabilities hash has moved, so the teaser costs
a full run once per data change rather than once per page view. That comment was the
reason nobody questioned the second model; it is replaced with what actually happens.

**A test had encoded the defect.** `EstateIhtExposureDetectorTest` asserted
`exposed=false` for £500,000 of savings, reasoning that £500,000 equals the nil rate
band plus the residence nil rate band. The residence band requires a main residence
passing to direct descendants, and that user has neither — so their allowance is
£325,000 and £175,000 is taxable. The old detector handed the residence band to
everyone by folding it into a threshold. The test now asserts the correct behaviour
and a second test covers the genuinely-covered case.

### The rule applied beyond this item

`/m` was working three other things out. Each is now computed once, server-side:

| Was computed in `/m` | Now |
|---|---|
| `ProtectionPolicy.vue` annualised the premium with its own `switch` | `App\Support\PremiumAnnualiser` — one mapping, used by `ComprehensiveProtectionPlanService` and published as `annual_premium` on all five policy resources |
| `SavingsAccount.vue` computed `balance × rate ÷ 100` and `÷ 12` | `annual_interest` / `monthly_interest` appended by the `SavingsAccount` model |
| `RetirementPensionDetail.vue` derived a monthly contribution from salary percentages | `monthly_contribution` appended by the `DCPension` model |

**The pension one was not just duplication — the two disagreed.** The backend takes
the flat monthly amount first and falls back to percentages; `/m` took the
**percentages first**. A pension recording both was described differently depending
which screen you were on. The model's accessor is now the single answer and
`RetirementStrategyService` reads it too.

### NOT done

- **The remaining `/m` arithmetic is presentational and was left**: percentage-of-total
  for progress bars in `Goals.vue`, `NetWorth.vue` and `Savings.vue`, and min/max for a
  sparkline's scale in `CanonicalPortfolio.vue`. None of them produces a financial
  figure a user could act on, and none has a backend counterpart to disagree with.
  **Named here rather than silently judged in scope** — if the rule is meant to cover
  those too, they are a small follow-up.
- Not browser-verified on `/m`: the teaser is a **Free-tier** surface and the personas
  are Premium, so the screen could not be reached without changing a tier. Covered by
  the service tests. **I COULD NOT BROWSER-TEST THE TEASER.**


## Tax-compliance verdict

`workforce/ops/handoffs/W-0463/tax-compliance-reviewer-verdict-2026-08-23.md` — two rounds,
26 findings, with legislation and HMRC manual citations. Recorded there because the
reviewer wrote nothing to disk; without that file both reviews would have been lost.

- 2026-08-31 build-lead: **CLOSED — verified against `dev`, and consolidated rather than aligned.**
  `EstateIhtExposureDetector` now injects `IHTCalculationService` (:35) and reads
  `$calculation['iht_liability']` (:54) — the one mechanism — instead of computing
  `(netWorth − NRB − RNRB) × 40%` on the logged-in user alone. `:56-58` records the second
  decision: exposure is "there is a bill to show", not a second threshold test that could disagree
  with the first. The docblock at :16-23 states the defect it replaced.
  **This also closes the `/m` half of W-0154's residual**, which was that surface's only
  inheritance tax figure being a second implementation.
