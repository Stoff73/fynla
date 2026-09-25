# Save Tax campaign — every possible outcome, by household

Traced from code on `dev` (76c941898), 2026-09-25. Thresholds quoted are the 2026/27 values the engine reads from `TaxConfigService`.

**How the plan is built.** The walk ends with Fyn's "Here's your tax plan" message, which lists every item in `ComposedTaxPlanService::forUser()['items']` (the same list /m and iOS show on Tax Strategy). There are 13 strategy classes producing 21 possible items (`app/Services/Tax/Strategies/`). There is no cap on how many surface. Items are sorted by saving, highest first.

**What the walk asks** (funnel page, then Fyn): employment, income band, "Do you have a spouse?", spouse income band, which assets you hold, then Fyn captures income, ISAs, bank savings, investments, property, date of birth, pensions, spouse finances, and childcare / charitable donations / Gift Aid.

**It never asks about children.** The campaign starts at the work step (`config/onboarding.php:80`) and skips the personal, spouse and dependants steps. So "with children" households get exactly the same plan as their childless versions (see section 5).

---

## 1. Available to every household

| # | Item as the user sees it | When it appears |
|---|---|---|
| 1 | Reclaim your Personal Allowance with a pension contribution | Adjusted net income between £100,000 and £125,140, and pension allowance left |
| 2 | Shift income out of the 45% additional-rate band | Taxable income over £125,140, and pension allowance left. Conflicts with #1 (only one counts in the total) |
| 3 | Your Pension Annual Allowance is tapered to £X | Threshold income over £200,000 **and** adjusted income over £260,000 |
| 4 | Save around £X a year by moving your pension contributions to salary sacrifice | Employed / full-time / part-time, with a defined contribution pension not already on salary sacrifice. **See bug B1** |
| 5 | Wrap £X of cash savings inside an ISA before 5 April | ISA allowance left, savings interest above your Personal Savings Allowance (£1,000 basic / £500 higher / £0 additional), and over £1,000 to move |
| 6 | You have £X of unused Dividend Allowance | Holds a non-ISA investment account, dividends below £500 |
| 7 | Reclaim £X on your Gift Aid donations via Self Assessment | Higher- or additional-rate taxpayer, Gift Aid donations entered |
| 8 | Open a Lifetime ISA for a £X government bonus every year | Aged 18 to 39 |
| 9 | Bed & ISA — potentially shelter £X of gains | **Cannot fire from this walk** (bug B3) |
| 10 | Top up your pension by up to £X using carry-forward | **Cannot fire from this walk** (bug B2) |

## 2. Single

Items 1–8 above, with their conditions. Nothing else.

## 3. Married / partnered — spouse earns nothing

Items 1–8, plus:

| # | Item | When it appears |
|---|---|---|
| 11 | Top up your spouse's pension by £2,880 — instant £720 | Always, unless the spouse is known to be 75+. **See bug B5** |
| 12 | Claim Marriage Allowance | You are a basic-rate taxpayer. **See bug B6** |
| 13 | Gift £X of savings to your spouse | Spouse has no savings recorded; you hold cash in your sole name over £1,000 |
| 14 | Consider sharing savings equally | Same trigger as #13; conflicts with #13 (only one counts in the total) |
| 15 | Open or top up an ISA in your spouse's name | Spouse ISA balance is £0 |
| 16 | Hold non-ISA investments in your spouse's name | You hold any non-ISA investment account |

## 4. Married / partnered — spouse earns

Items 1–8, plus:

| # | Item | When it appears |
|---|---|---|
| 17 | Max out your spouse's pension on their £X earnings | Spouse earns above £0 and below £25,140 |
| 18 | Consider holding eligible non-ISA investments in your spouse's name | You are higher/additional rate, spouse is basic rate, you hold a non-ISA account |
| 19 | Use your spouse's ISA allowance | You have used the full £20,000 ISA allowance this year; spouse ISA is £0 |

Marriage Allowance **never** appears here, even when the spouse earns under the Personal Allowance (bug B7).

## 5. Married with children / single with children

**Identical to sections 3/4 and 2.** The two child items —

| # | Item | Needs |
|---|---|---|
| 20 | You have N child(ren) under 18 — Junior ISA capacity | Child records with a date of birth |
| 21 | Open a pension for each child — instant £720 a year | Child records with a date of birth |

— only appear if child records already exist from somewhere else (a profile edit). The walk never writes them. The childcare figure the walk asks for feeds no plan item; High Income Child Benefit Charge and Tax-Free Childcare exist only as threshold lines on the Actions page, which also need child records.

---

## Bugs found while tracing (for your check, not yet fixed)

Most likely to show a user something wrong first.

- **B1 Salary sacrifice fires on the wrong pension.** It checks the raw monthly contribution column. The workplace form stores percentages, so a workplace pension can never trigger it; a personal pension / SIPP with a contribution does — and a SIPP cannot be salary-sacrificed. (`SalarySacrificeNiStrategy.php:43-46`)
- **B5 Spouse pension top-up ignores what's already paid.** Recommends £2,880 even when the user said the spouse already pays the maximum. (`NonEarnerSpousePensionStrategy.php`)
- **B6 Marriage Allowance for people with no taxable income.** "Basic rate" includes £0 income, so a non-working user with a non-working spouse is told they save £252.
- **B8 Partners treated as spouses.** "Do you have a spouse?" yes is stored as married, so unmarried partners get Marriage Allowance and spouse-transfer advice they cannot legally use. (`FunnelAnswersMapper.php:47-50`)
- **B4 Headline total double-counts.** ISA top-up and gift-to-spouse shelter the same interest; Marriage Allowance and gift-to-spouse both use the spouse's Personal Allowance; unused Dividend Allowance is counted as "tax saved" when no tax is being paid; the Lifetime ISA bonus, junior pension uplift and tapered-allowance "charge avoided" are all labelled tax saved.
- **B7 Marriage Allowance blocked whenever the spouse earns**, even below the Personal Allowance.
- **B9 The funnel promises a pension saving the plan can't deliver.** The estimate page shows a pension contribution saving for anyone without a pension; the engine only has 60% and 45% band pension items, so a £60,000 earner never gets one.
- **B2 Carry-forward unreachable** — needs three years of pension contribution history the walk never asks for.
- **B3 Bed & ISA unreachable** — needs holdings with a purchase cost; the investment form captures only value and dividends.
- **B11–B14 smaller:** step-children counted by childcare but not by Junior ISA; unknown spouse income treated as £0 (basic rate); Gift Aid 25% / 31.25% factors and the £2,880 / £720 pension figures hardcoded rather than from `TaxConfigService`; property answers feed no item.
