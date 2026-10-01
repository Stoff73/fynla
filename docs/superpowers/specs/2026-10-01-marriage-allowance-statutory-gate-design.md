# Marriage Allowance tested against the law

**Item:** `todoCurrent/TODO.md` item 2. APPROVED, CSJ 2026-09-30: "widen to law".
**Date:** 2026-10-01

## 1. The law

ITA 2007 Part 3 Chapter 3A, read on legislation.gov.uk on 2026-10-01.

- **Who can give it (the transferor): s55C(1)** ([s55C](https://www.legislation.gov.uk/ukpga/2007/3/section/55C)).
  - (a) Married or in a civil partnership.
  - (b) Entitled to a Personal Allowance under s35.
  - (c) "assuming the individual's personal allowance was reduced as set out in section 55B(6), the individual would not for that year be liable to tax at a rate other than the basic rate, … the savings basic rate, the dividend nil rate, … the dividend ordinary rate, the savings nil rate or the starting rate for savings".
  - (ca) If any dividends fall in the dividend nil rate, they would still not be liable at the dividend upper or additional rate "if section 13A (dividend nil rate) were omitted". In other words, dividends are counted in full.
  - (d) Only for someone who meets the s56 residence test through s56(3), the non-resident route: the s55C(2) condition, "hypothetical net income … less than the amount of the personal allowance".
- **Who can receive it (the recipient): s55B(2)(b), (ba)** ([s55B](https://www.legislation.gov.uk/ukpga/2007/3/section/55B)). This is the same rate test, applied to their own income, and the code already applies it.
- **What it is worth.**
  - s55B(1), (3): a tax reduction of the basic rate × the transferable amount.
  - s55B(4)(b), (5): the transferable amount is 10% of the s35(1) allowance, rounded up to £10 (`income_tax.marriage_allowance.amount`, £1,260 for 2026/27).
  - s55B(6): the transferor's allowance falls by that amount.
  - s23 Step 6 and s26: a reduction cannot exceed the recipient's tax.

GOV.UK ([marriage-allowance](https://www.gov.uk/marriage-allowance)) summarises the transferor's test as income "below your Personal Allowance". In the statute that is s55C(2), and it binds only through s55C(1)(d). The app models UK residents (`TaxStrategyMath`, rest of the UK rates), so the operative test is s55C(1)(c), (ca).

## 2. What changes

### 2.1 The gate: `TaxStrategyMath::marriageAllowance`

**Today:** the transferor must have net income below the Personal Allowance (`TaxStrategyMath.php:586`, `:593`).

**New:** the transferor's net income, plus the transferable amount, must not exceed the higher-rate threshold, with dividends counted in full. Their Gift Aid and relief-at-source band extension counts (`bandFromIncomeFor`), as it does for the recipient.

Why this is s55C(1)(c): taxable income after the reduced allowance is net income − (Personal Allowance − transferable amount). It stays inside the basic-rate band exactly when net income + transferable amount ≤ Personal Allowance + basic-rate band, which is the higher-rate threshold (`income_tax.bands`, "Higher Rate" `lower_limit`). The transferor is then below the s35 taper threshold, so the allowance is not tapered.

**The saving does not change in form:** the recipient's reduction (capped at their tax) less the transferor's extra tax (`extraTaxFromLosingAllowance`, priced by `UKTaxCalculator`). The extra tax is priced by the one tax engine, which applies:
- the starting rate for savings (ITA 2007 s12);
- the Personal Savings Allowance (s12B);
- the dividend allowance (s13A).

A transferor whose income above the reduced allowance is non-savings income pays exactly the reduction in extra tax. The saving nets to £0 and is not shown, as today.

### 2.2 Worked examples (2026/27 configuration)

The recipient in each example earns £35,000 from employment.

| Transferor's income | Today | New | Why |
|---|---|---|---|
| £14,000 savings interest | not shown | **£252** | After the transfer, £2,690 is taxable and all of it falls in the £5,000 starting rate for savings (s12). The transferor pays £0 more. |
| £14,000 dividends | not shown | **£116.55** | The dividend allowance (£500) is unchanged. The extra £1,260 of taxable dividends costs £135.45 at 10.75%: £252 − £135.45. |
| £12,000 salary | £114 | £114 | Unchanged: £690 taxed at 20% = £138 extra, £252 − £138. |
| £20,000 salary | not shown | not shown | £1,260 more taxed at 20% costs exactly the £252 reduction. |

### 2.3 Every other place that decides Marriage Allowance

There must be one rule (Rules 20 and 23). There are four engines and two stored flags:

| Where | Reaches | Change |
|---|---|---|
| `TaxStrategyMath::marriageAllowance` | Tax Strategy plan, cards, allowance grid, Fyn's composed plan; web, /m, iOS | The canonical rule (2.1) |
| `TaxOptimisationService::buildSpousalStrategy` (`:421-438`) | `TaxOptimisationAgent` → Fyn's module analysis block, `/api/v1/mobile` module summary, `/api/tax/strategies` | Uses the canonical rule and saving. Today it adds the full £252 whenever the lower earner is below the Personal Allowance, ignoring the recipient's tax and the transferor's extra tax. Its band-difference early return no longer hides Marriage Allowance. |
| same, `:466-470` | same | Remove the invented "£200 conservative estimate" (Rule 23: no source) |
| `HouseholdPlanningService::generateMarriageAllowanceRecommendation` (`:811`) | `GET /api/household/optimisations`. Its component `SpousalOptimisations.vue` is imported nowhere. | Uses the canonical rule. Today it uses `floor(PA × 0.10)` (£1,257, against s55B(5)'s £1,260). |
| `SpouseOptimisationService::strategyMarriageAllowance` (`:492`) | Nowhere: `InvestmentPlanService.php:192` filters `marriage_allowance` out of the investment plan | Deleted. Dead, and it hardcodes `TaxDefaults::PERSONAL_ALLOWANCE`, `× 0.10` and `× 0.20` (Rule 2). |
| `SaveTaxEstimateService::marriageAllowanceRecipient` (public `/savetax`) | Public estimate | Unchanged. The funnel's income bands are earnings only ("no income" or a band), and for non-savings income the law and GOV.UK give the same answer (2.2, £20,000 row). |
| `users.marriage_allowance_eligible`, written by `FunnelAnswersMapper` and the `capture_spouse_work_status` / `capture_spouse_household_data` handlers | Gates nothing. The `capture_spouse_work_status` tool result echoes it to Fyn. | The tool result stops reporting it: "spouse works → not eligible" is wrong under the law and GOV.UK alike (a partner earning £8,000 qualifies). The column writes are left alone (inert; listed as a Found line). |

### 2.4 Words the user sees

`MarriageAllowanceStrategy` and the `marriage_allowance_transfer` how-to (`database/seeders/data/action-how-to/tax.md`) both assume the giver's allowance is "unused" and their income is "below the Personal Allowance". That is no longer always so.

- **Card:** "transfer £1,260 of their unused Personal Allowance" becomes "transfer £1,260 of their Personal Allowance". This is true in every case.
- **How-to:** a branch for a giver whose income is at or above the Personal Allowance. The eligibility note and the `why` line now state the s55C(1)(c) test. A new line gives the extra tax the giver pays, when there is any, so the household figure is explained. This is a gap today as well: a £12,000 earner pays £138 more and is never told. The new figure `transferor_extra_tax` comes from the engine.
- **Fyn corpus:** `fyn-memory/semantic/house_view/marriage-allowance-transfer.md` gets the same correction.

**These wording changes go to CSJ for approval before release.** The how-to is an approved entry, and the corpus is global.

## 3. Out of scope

- Scottish and Welsh rates: not modelled (parked by CSJ). The Scottish recipient caveat stays.
- Married Couple's Allowance (s45, s46, s55B(2)(d)): the how-to's existing `mca_possible` line stays.
- Non-residents (s55C(1)(d)): the app does not model residence.

## 4. Tests

These are named files only (`tests/Unit/Services/Tax/Strategies/MarriageAllowanceStrategyTest.php`, `PlanBAccuracyTest.php`, `TaxOptimisationServiceTest.php`):

- A linked spouse with £14,000 of interest gives £252. This fails before the change.
- A household spouse with £14,000 of dividends gives £116.55. This fails before the change.
- A giver at £20,000 of salary is still not shown.
- The old engine reports the canonical saving: £114 for a £12,000 spouse, not £252. It reports no invented £200.
- The how-to renders the above-allowance branch and the extra-tax line.
