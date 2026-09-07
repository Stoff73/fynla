---
id: W-0424
title: A pension contribution recorded as a percentage never becomes a financial commitment, so it never reaches expenditure and disposable income is overstated by it
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0030-cycle4-letter-and-income-labels.md
owner: unassigned — expenditure path
status: done
severity: medium
surfaces: [web, m]
created: 2026-08-23T02:55:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0422]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

Found while fixing W-0422, and **deliberately not folded into it** — the fix
belongs in the expenditure path, which another agent holds in this batch.

Two mechanisms answer "what does this person pay into their pension each month",
and only one of them reaches the spending side:

| Mechanism | Reads | Reaches expenditure? |
|---|---|---|
| `UserProfileService::calculateAnnualPensionContributions` | `employee_contribution_percent × annual_salary`, workplace/occupational/auto-enrolment | no — tax only |
| `UserProfileService::getFinancialCommitments()` → `retirement` | `dc_pensions.monthly_contribution_amount` | **yes**, via `getExpenditureBreakdown` → `annual_expenditure` |

David (16) records his contribution as **8% of £145,000 = £11,600** with
`monthly_contribution_amount = NULL`. So £11,600 leaves his pay before it reaches
his bank account, and **nothing in the app deducts it from what he has available
to spend.** Disposable Income reads £64,501.28 where roughly £52,901 is spendable.

Sarah (17) is unaffected — her pension carries neither field.

## The fix, and the trap in the obvious alternative

**Bridge the two: derive the `retirement` commitment from the percentage when
`monthly_contribution_amount` is absent, from the same resolver the tax path
uses.** One home for the fact, per Rule 20.

**Do NOT instead deduct `calculateAnnualPensionContributions` from `net_income`.**
That fixes David and charges twice anyone who records the same contribution as a
monthly amount. Verified 2026-08-23: **zero** seeded pensions populate both
fields, so no existing test can go red on it and it would ship invisible.

## Consumers of disposable income, enumerated

Whoever takes this must re-check these; the figure moves for every user whose
pension is recorded as a percentage.

**One reader, several presenters:** `Plans\DisposableIncomeAccessor` reads
`income_occupation.disposable_income` / `monthly_disposable` and every plan
service below goes through it.

| Consumer | Use |
|---|---|
| `Plans\DisposableIncomeAccessor` | the accessor all plan services read |
| `Plans\GoalPlanService:282` | allocates 20% / 50% / 10% of monthly disposable |
| `Plans\RetirementPlanService:313,378,465,661` | 30% allocations + plan summary |
| `Plans\InvestmentPlanService:511,583,641` | 20% / 30% allocations + summary |
| `Plans\ProtectionPlanService:242` | plan summary |
| `Plans\EstatePlanService:135,632` | **affordability** — premium ≤ 15% of monthly disposable, and an affordability ratio |
| `Coordination\CashFlowCoordinator:43` | monthly disposable less committed contributions |
| `Investment\Recommendation\UserContextBuilder:52,145,218,299` | computes its own `max(0, net − expenditure)` for the recommendation context |
| `Investment\Recommendation\ContributionWaterfallService:817` | **gates venture capital allocation** on a percentage of disposable income, and quotes the figure in its skip reason |
| `Investment\Recommendation\SafetyCheckService:41` | safety gate |
| `Investment\Recommendation\RecommendationOutputFormatter:193` | published in recommendation output |
| `Retirement\RetirementStrategyService:437` | strategy affordability |
| `UserProfile\UserProfileService` (`buildIncomeOccupation`) | the published `disposable_income` / `monthly_disposable` |
| `IncomeOccupation.vue:554` | **recomputes it client-side** as `net_income − annual_expenditure` — a second mechanism for the same figure, worth folding into the same work |

**`UserContextBuilder` and `IncomeOccupation.vue` do not go through the accessor** —
they each re-derive `net − expenditure`. Any change to the definition has to reach
all three or the surfaces will disagree.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed, and there were THREE faults, of which the item named one.**

  **Measured before and after on the live persona, not reasoned about:** David's contributions read **£0** against an 8%-of-£145,000 record. After the fix, **£11,600**.

  1. **The item's fault.** `getFinancialCommitments()` gated on `monthly_contribution_amount > 0`, so a percentage-only record never reached the spending side.
  2. **The tax side was broken too, and the item assumed it worked.** `calculateAnnualPensionContributions()` filtered on `in_array($scheme_type, ['workplace', 'occupational', 'auto_enrolment'])` — but the column is `enum('workplace','sipp','personal')`, so **two of the three permitted values could never match**, and the live data also carries NULL. David's `scheme_type` is null, so the tax mechanism returned £0 as well. **Both mechanisms failed on the same record, for different reasons** — which is why the money vanished completely rather than being counted once.
  3. Same class as W-0481: an allowlist containing values the column cannot hold.

  **The consolidation (Rule 20).** `monthlyEmployeeContribution(DCPension)` is now the one answer, used by both callers. The stated monthly amount wins where set — it is what the member actually told us — and the percentage is the fallback, not the reverse. `isSalaryDeductedPension()` is stated as an EXCLUSION (`sipp`, `personal`) rather than an allowlist, because those are funded from money already received; anything with a salary and a percentage on it is a salary deduction by construction, since a personal pension has no employer salary basis to compute against.

  **The resulting arithmetic reconciles, which is the check that it is right rather than merely different:** expenditure rose £11,600 and net income rose £5,220 as the relief now applies, so disposable income fell **£6,380** — exactly the after-tax cost of an £11,600 contribution at higher rate. If only one side had moved, that number would have been £11,600.

  **Tested:** `PercentagePensionReachesExpenditureTest` — 4 passed, covering the percentage path, a SIPP correctly excluded, a stated amount beating the percentage, and **the null `scheme_type` that defeated the old allowlist**. 567 profile/expenditure/income tests pass (1,729 assertions); the 7 persona estate locks unmoved.
