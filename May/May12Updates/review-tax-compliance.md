# UK Tax Compliance Review — Fynla

**Date:** 12 May 2026
**Reviewer:** UK Tax Compliance Reviewer agent
**Scope:** `app/Services/Tax/`, `app/Services/Estate/`, `app/Services/Investment/`, `app/Services/Retirement/`, `app/Services/Savings/`, `app/Services/Property/`, `app/Services/Business/`, `app/Services/Benefits/`, `app/Services/TaxConfigService.php`, `app/Services/UKTaxCalculator.php`, `app/Services/TaxBandTracker.php`, `app/Constants/TaxDefaults.php`, `resources/js/constants/taxConfig.js`, `database/seeders/TaxConfigurationSeeder.php`.

---

## 0. Headline finding — active tax year mismatch in project docs

**Severity:** Medium · **Confidence:** High

`CLAUDE.md` ("UK Tax Context" section) states the active tax year is **2025/26**, and the reviewer brief restates that. The codebase disagrees:

- `database/seeders/TaxConfigurationSeeder.php:18` — `private const ACTIVE_TAX_YEAR = '2026/27';`
- `app/Constants/TaxDefaults.php:17` — `// Last verified: 5 April 2026 (2026/27 tax year)`. Every constant is the 2026/27 value (e.g. `BADR_RATE = 0.18`, `DIVIDEND_BASIC_RATE = 0.1075`).
- `resources/js/constants/taxConfig.js:15,26` — `Tax Year: 2026/27`, `TAX_YEAR = '2026/27'`.
- The seeder file does still contain a complete 2025/26 config (`getTaxConfig202526()`), but it is not the active row.

This is not a code bug — but the rest of this review is written against the **2026/27** rates and rules that the live `TaxConfiguration` row actually contains. CLAUDE.md and the reviewer brief are stale; CLAUDE.md Rule #3 ("UK Tax Context" subsection) should be updated or removed. Findings flagged below that look like they are "wrong against 2025/26" are actually 2025/26-era fallback literals that have not been updated to track the rolled-forward active year.

The "Current Tax Year Reference (2025/26)" block in the reviewer brief is therefore being used as a sanity check only, with the seeder's 2026/27 config as the authoritative reference.

---

## 1. Hardcoded tax values — summary table

Format: `file:line | literal | what it represents | should source from`

### 1a. Stale fallback literals (`?? <value>`)

These are fallback values used if `TaxConfigService` returns null. Many are now stale because the 2026/27 active year shifted rates — even though the primary lookup still works, a single misconfiguration would silently downgrade to a wrong rate.

| File:line | Literal | Represents | Correct source |
|-----------|---------|------------|----------------|
| `app/Services/Investment/DividendTaxCalculator.php:39` | `0.0875` | Dividend basic rate (2025/26) — should now be **0.1075** | `TaxConfigService::getDividendTax()['basic_rate']` |
| `app/Services/Investment/DividendTaxCalculator.php:40` | `0.3375` | Dividend higher rate (2025/26) — should now be **0.3575** | `TaxConfigService::getDividendTax()['higher_rate']` |
| `app/Services/Investment/DividendTaxCalculator.php:41` | `0.3935` | Dividend additional rate — unchanged but still a literal | `TaxConfigService::getDividendTax()['additional_rate']` |
| `app/Services/Tax/TaxStrategyMath.php:298-300` | `0.3375`, `0.3935`, `0.0875` | Dividend higher/additional/basic rates | `TaxConfigService::getDividendTax()` |
| `app/Services/Tax/TaxOptimisationService.php:242` | `0.0875`, `0.3375` | Dividend basic and higher rates | Same |
| `app/Services/Investment/Tax/ISAAllowanceOptimizer.php:233,466` | `0.0875` | Dividend basic rate | Same |
| `app/Services/Investment/Tax/BedAndISACalculator.php:317` | `0.0875` | Dividend basic rate | Same |
| `app/Services/Investment/Tax/BedAndISACalculator.php:60` | `0.20` | CGT higher rate fallback — should now be **0.24** | `TaxConfigService::getCapitalGainsTax()['higher_rate']` |
| `app/Services/Investment/Tax/ISAAllowanceOptimizer.php:232` | `0.20` | CGT higher rate fallback | Same |
| `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php:325,331,419,445` | `0.0875` | Dividend basic rate | `TaxConfigService::getDividendTax()` |
| `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php:418,444` | `0.10` | CGT basic rate (pre-Oct 2024) — should now be **0.18** | `TaxConfigService::getCapitalGainsTax()['basic_rate']` |
| `app/Services/Business/BusinessInterestService.php:171` | `0.10` | BADR rate (2024/25) — should now be **0.18** | `TaxConfigService::getCapitalGainsTax()['business_asset_disposal_relief_rate']` |
| `app/Services/Business/BusinessInterestService.php:172` | `0.20` | CGT higher rate fallback — should now be **0.24** | `TaxConfigService::getCapitalGainsTax()['higher_rate']` |
| `app/Services/Business/BusinessInterestService.php:173` | `0.10` | CGT basic rate fallback — should now be **0.18** | `TaxConfigService::getCapitalGainsTax()['basic_rate']` |
| `app/Services/Tax/Strategies/BedAndIsaStrategy.php:48,50` | `0.18` | CGT basic rate — currently matches but should source from config | `TaxConfigService::getCapitalGainsTax()['basic_rate']` |
| `app/Services/Tax/Strategies/BedAndIsaStrategy.php:49` | `0.24` | CGT higher rate — currently matches but still literal | `TaxConfigService::getCapitalGainsTax()['higher_rate']` |
| `app/Services/Retirement/SalarySacrificeAnalyzer.php:307` | `0.138` | Employer NI rate (2024/25) — should now be **0.15** | `TaxConfigService::get('national_insurance.class_1.employer.rate')` |
| `app/Services/Retirement/PensionContributionOptimizer.php:432` | `60000` | Pension Annual Allowance | `TaxConfigService::getPensionAllowances()['annual_allowance']` |
| `app/Services/Tax/Strategies/TaperedAnnualAllowanceStrategy.php:48,51,52,54` | `200000`, `260000`, `10000`, `60000` | Pension AA taper thresholds | `TaxConfigService::getPensionAllowances()['tapered_annual_allowance']` |
| `app/Services/Tax/IncomeDefinitionsService.php:167-174` | `12570`, `100000`, `60000`, `200000`, `260000`, `10000` | Tax bands + pension limits | TaxConfigService getters |
| `app/Services/Tax/TaxStrategyMath.php:156` | `60000` | Pension AA | `TaxConfigService::getPensionAllowances()` |
| `app/Services/Tax/TaxStrategyCalculator.php:118,156,195,202,232,239` | `12570`, `60000`, `3000` | PA, pension AA, CGT AEA | TaxConfigService getters |
| `app/Services/Tax/TaxActionDefinitionService.php:341-343,366-368` | `12570`, `37700`, `125140` | Income tax band thresholds | TaxConfigService getters |
| `app/Services/Tax/TaxOptimisationService.php:477-479` | `12570`, `37700`, `125140` | Income tax band thresholds | Same |
| `app/Services/Savings/PSACalculator.php:84-86` | `12570`, `37700`, `125140` | Income tax band thresholds | Same |
| `app/Services/Savings/SavingsActionDefinitionService.php:2198-2199` | `37700`, `125140` | Income tax band widths/thresholds | Same |
| `app/Services/Retirement/PensionContributionOptimizer.php:256-257,424` | `50270`, `125140` | Higher rate thresholds | Same |
| `app/Services/Retirement/PensionContributionOptimizer.php:308` | `50270` | Auto-enrolment upper qualifying earnings | `TaxConfigService::get('pension.auto_enrolment.upper_qualifying_earnings')` |
| `app/Services/Retirement/DecumulationPlanner.php:302-303` | `12570`, `50270` | PA, higher rate threshold | Same |
| `app/Services/Investment/AssetLocation/AssetLocationOptimizer.php:105,149-151` | `12570`, `50270`, `125140` | PA, higher rate, additional rate | Same |
| `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php:80,140` | `12570`, `3000` | PA, CGT AEA | Same |
| `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php:81` | `5000` | Starting Rate for Savings band | `TaxConfigService::get('income_tax.starting_rate_for_savings.band')` |
| `app/Services/Tax/Strategies/SalarySacrificeNiStrategy.php:57` | `50270` | NI upper earnings limit | `TaxConfigService::get('national_insurance.class_1.employee.upper_earnings_limit')` |
| `app/Services/Tax/Strategies/IncomeBandStrategy.php:32-33` | `100000`, `125140` | PA taper / additional rate thresholds | Same |
| `app/Services/Tax/Strategies/BedAndIsaStrategy.php:36-37` | `20000`, `3000` | ISA allowance, CGT AEA | Same |
| `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php:40,459` | `3000` | CGT annual exempt amount | Same |
| `app/Services/Investment/AssetLocation/TaxDragCalculator.php:146,152` | `3000`, `500` | CGT AEA, dividend allowance | Same |
| `app/Services/Tax/TaxProductInfoService.php:149` | `3000` | CGT AEA | Same |
| `app/Services/Retirement/RetirementStrategyService.php:708` | `20000` | ISA allowance | `TaxConfigService::getISAAllowances()['annual_allowance']` |
| `app/Services/Estate/ComprehensiveEstatePlanService.php:957,1237` | `3000` | IHT annual gift exemption | `TaxConfigService::getGiftingExemptions()['annual_exemption']` |
| `app/Agents/EstateAgent.php` (multiple) | `TaxDefaults::NRB`, `TaxDefaults::IHT_RATE`, etc. | OK — uses `TaxDefaults` constants (fallback layer policy-compliant) | n/a |
| `app/Services/Estate/TrustService.php:171` | `0.0875` | IIP trust dividend rate | `TaxConfigService::getTrusts()['income_tax']['interest_in_possession']['dividend_rate']` (for 2026/27 should be 0.1075) |
| `app/Services/Estate/TrustService.php:168` | `0.20` | Basic rate fallback (literal) | `TaxConfigService::getIncomeTax()['bands'][0]['rate']` |
| `app/Services/Estate/TrustService.php:197` | `0.45` | Additional rate fallback | `TaxConfigService::getIncomeTax()['bands'][2]['rate']` |
| `app/Services/Investment/Recommendation/TransferRecommendationService.php:378` | `500` | Dividend allowance fallback | `TaxConfigService::getDividendTax()['allowance']` |
| `app/Services/Estate/SpouseNRBTrackerService.php:32,66,73,111,160` | `325000`, `175000` | Comment-only — actual code reads from config | n/a (comments) |
| `app/Services/Estate/IHTCalculationService.php:111,1148` | `325000`, `175000` | Comment-only — actual code reads from config | n/a (comments) |

### 1b. Truly hardcoded values (not via fallback)

These are HMRC rules baked into code with no config indirection. Some are policy-driven (e.g. PCLS = 25%) — but a tax engine should still keep them swappable.

| File:line | Literal | Represents | Correct approach |
|-----------|---------|------------|------------------|
| `app/Services/Retirement/RetirementIncomeService.php:256,1937` | `0.25` | PCLS / tax-free cash percentage | `TaxConfigService::get('pension.tax_free_cash_percentage', 0.25)` — and crucially, **cap by LSA £268,275** |
| `app/Services/Retirement/RetirementIncomeService.php:257,1937` | `0.75` | Taxable drawdown portion (complement of PCLS) | Derived from the above |
| `app/Services/Retirement/DecumulationPlanner.php:232,244` | `0.25`, `0.20` | PCLS percentage and basic-rate-saving | Same |
| `app/Services/Retirement/RetirementActionDefinitionService.php:1982` | `0.25` | Tax-free lump sum percentage | Same |
| `app/Services/Retirement/RetirementStrategyService.php:699,701,752,1261,1270,1273` | `0.25`, `0.20`, `0.80` | Relief-at-source factors | Should derive from basic rate via `TaxConfigService::getIncomeTax()['bands'][0]['rate']` |
| `app/Services/Property/PropertyService.php:144` | `0.20` | Section 24 (residential landlord interest) tax credit | `TaxConfigService::getIncomeTax()['bands'][0]['rate']` (basic rate by HMRC rule) |
| `app/Services/Goals/GoalAssignmentService.php:122` | `625000` | SDLT first-time-buyer max property value | `TaxConfigService::get('stamp_duty.residential.first_time_buyers.max_property_value')` — currently mis-keyed (see §3 below); also out of date — should be **£500,000** per active config |
| `app/Services/Investment/AssetLocation/TaxDragCalculator.php:176-178` | `0.0875`, `0.3375`, `0.3935` | Dividend rates band-by-band | All from `TaxConfigService::getDividendTax()` |
| `app/Services/Investment/AssetLocation/TaxDragCalculator.php:141,142,158,159` | `0.20`, `0.40` | Income tax rate band thresholds (used as match keys) | `TaxConfigService::getIncomeTax()['bands'][*]['rate']` |
| `app/Services/Investment/AssetLocation/TaxDragCalculator.php:208,211` | `0.20`, `0.75` | Future withdrawal tax rate, taxable portion | Latter linked to PCLS; former requires user marginal rate |
| `app/Services/Investment/Recommendation/SpouseOptimisationService.php:501,502,515` | `12570`, `0.10`, `0.20` | Marriage Allowance PA, transfer fraction, basic rate | Use `TaxConfigService::get('income_tax.marriage_allowance.amount')` (already £1,260 in config) directly instead of `PA × 0.10` |
| `app/Services/Investment/Recommendation/ContributionWaterfallService.php:333,440,470` | `0.20` | Pension allocation share / display percentage | Allocation share (0.20) is heuristic, not tax; the display string `× 20%` should not assert basic rate hardcoded |
| `app/Services/Investment/Rebalancing/TaxAwareRebalancer.php:287,300` | `0.20` | "Assume 20% rate" for tax-loss harvesting benefit | Should use user's actual marginal rate (or `TaxConfigService::getCapitalGainsTax()['basic_rate']` — currently 0.18) |
| `app/Services/Investment/PortfolioStrategyService.php:210` | `0.20` | "40% → ~20% = 20% saving" for offshore bond benefit | Should be `higherRate − basicRate` via config |
| `app/Services/Coordination/HouseholdPlanningService.php:558` | `0.20` | Estimated tax saving rate (basic rate) | `TaxConfigService::getIncomeTax()['bands'][0]['rate']` |
| `app/Services/Tax/TaxOptimisationService.php:415` | `0.10`, `0.20` | 10% of PA × 20% basic rate saving for Marriage Allowance | Use config-driven `marriage_allowance.amount × basic_rate` |
| `app/Services/Tax/Strategies/LifecycleStrategy.php:53` | `£450,000` (string) | LISA first-home property value cap | HMRC rule — but should live in config (`isa.lifetime_isa.max_property_value`) |
| `app/Services/Business/BusinessInterestService.php:189` | `"10%"` (string in warning) | BADR rate — wrong for 2026/27 (now 18%) | Generate from `business_asset_disposal_relief_rate` × 100 |
| `app/Services/Estate/GiftingStrategy.php:147` | `"£250"` (string in warning) | Small gifts limit user-facing label | Use config value in template |
| `app/Constants/FinancialPlanningKnowledge.php:110` | `"25% Tax-Free Lump Sum"` (educational copy) | Description text | Acceptable for educational content, but should be reviewed against LSA cap |

---

## 2. Income tax — specific findings

### 2.1 Income tax band ceiling formula bug (CRITICAL — latent)

**Severity:** Critical · **Confidence:** High · **Files:** `app/Services/UKTaxCalculator.php:644`, `app/Services/TaxBandTracker.php:38`, `app/Services/Property/PropertyTaxService.php:218`

The seeder stores income-tax band data with inconsistent semantics. For 2025/26 and 2026/27:

```php
'bands' => [
    ['name'=>'Basic Rate',      'min'=>0,      'max'=>37700,  'rate'=>0.20],   // max = WIDTH
    ['name'=>'Higher Rate',     'min'=>37700,  'max'=>125140, 'rate'=>0.40],   // max = ABSOLUTE
    ['name'=>'Additional Rate', 'min'=>125140, 'max'=>null,   'rate'=>0.45],
],
```

For the Basic Rate band, `max = 37700` is the band *width* — adding it to the personal allowance (£12,570) gives the correct higher-rate threshold of £50,270.

For the Higher Rate band, `max = 125140` looks like the *absolute* additional-rate threshold — but the code at three places adds it to the personal allowance again:

```php
$higherRateLimit = $personalAllowance + $bands[1]['max']; // 12570 + 125140 = 137710 (WRONG)
```

**Expected:** Additional rate kicks in at £125,140 (absolute).
**Actual:** With full PA, additional rate kicks in at £137,710 — a £12,570 over-extension of the 40% band.

**Why this doesn't blow up in production:** The PA taper at £100k income reduces PA by £1 per £2 above £100k. By the time income reaches £125,140, PA has tapered to £0, so the formula `0 + 125140 = 125140` is correct. The bug is **fully masked by the PA taper coincidence** for all incomes ≥ £125,140 (where PA=0). For incomes 100k–125,140, PA is partial but income hasn't crossed the additional rate threshold anyway, so no impact. For incomes < £100k, no taper, but income also doesn't reach 50,270+125,140=£137,710 anyway.

**Why it's still critical:**
1. Any tax-year config where the PA taper formula changes (Scotland — currently disabled but plumbed; future budgets removing taper) would surface the bug immediately.
2. The 2021/22 fallback path in the seeder sets `bands[1]['max'] = 150000` (absolute additional rate threshold of that year). Same bug, same self-correction.
3. Code reviewers reading this will see `personalAllowance + bands[1]['max']` and reasonably infer "max is band width", causing future refactors to compound the issue.

**Correct fix:** Decide one convention and apply everywhere. Recommended: switch all band entries to `'upper_limit'` (absolute) and remove `'max'`. Then read `$higherRateLimit = $bands[1]['upper_limit']`. The seeder already has `upper_limit` populated correctly (e.g. 2025/26 `bands[1]['upper_limit'] = 125140`).

**HMRC reference:** Income Tax (Earnings and Pensions) Act 2003; HMRC Personal Allowance and tax rates page.

### 2.2 PA taper applied to total income including dividends/interest — defensible but worth flagging

**Severity:** Low · **Confidence:** High · **File:** `app/Services/UKTaxCalculator.php:629-635`, `app/Services/Investment/DividendTaxCalculator.php:43-49`

Both calculators compute PA taper from `total income including dividends and interest`. HMRC's actual rule is "adjusted net income" — total taxable income LESS allowable deductions (pension contributions on net-pay basis, Gift Aid grossed up, trading losses). The codebase uses gross income everywhere.

This **over-tapers** PA for anyone making pension contributions or Gift Aid donations above £100k. For a £110k earner contributing £10k gross to pension, true ANI is £100k → PA preserved at £12,570; the code calculates ANI=£110k → PA reduced to £7,570 → £5,000 PA lost × 40% marginal = £2,000 of extra tax computed.

**Compliance impact:** Over-estimates tax for users with pensions in the £100–£125,140 band. Recommendations may suggest larger pension contributions than necessary. The 60% effective rate strategy in `IncomeBandStrategy.php` is correctly identifying the band but mis-sizing the saving.

**Fix:** Compute adjusted net income properly: subtract gross pension contributions (relief at source) and grossed-up Gift Aid before applying the taper formula.

### 2.3 Order of taxation — appears correct but stacks dividends after interest

**Severity:** Low · **Confidence:** Medium · **File:** `app/Services/UKTaxCalculator.php:619-768`

HMRC order: (1) non-savings/non-dividend earned income → (2) savings interest → (3) dividends. The code correctly stacks `non-dividend non-interest income` first, then interest, then dividends. ✓

However, the Starting Rate for Savings (£5,000 at 0%) is referenced in `TaxStrategyCalculator.php` but **not actually applied** to interest tax calculations in `UKTaxCalculator.php`. For a user with £5k employment and £6k interest, current code applies PSA (£1,000) and taxes £5,000 of interest at 20% (£1,000). Correct treatment: SRS covers up to £5,000 of interest at 0% if non-savings income ≤ PA. Result: £5k @ 0% + £0 left over after PSA → £0 tax. **The code over-taxes low-income savers.**

**HMRC reference:** ITTOIA 2005 s.18 (starting rate for savings).

### 2.4 Scottish Rate of Income Tax — not implemented

**Severity:** Medium · **Confidence:** High

The seeder has a `'scotland' => ['enabled' => false, 'bands' => []]` placeholder for 2025/26 and 2026/27, but no service code reads it. `WillDocumentService.php:247` is the only place that even mentions Scotland (a will-builder disclaimer). Scottish taxpayers will be charged at rUK rates by the engine, which is wrong for:

- Starter 19% (£12,571–£14,876)
- Basic 20% (£14,877–£26,561)
- Intermediate 21% (£26,562–£43,662)
- Higher 42% (£43,663–£75,000)
- Advanced 45% (£75,001–£125,140)
- Top 48% (£125,140+)

The tax difference for a Scottish higher-rate taxpayer at £75k is significant. Pension tax-relief calculations (`RetirementStrategyService` etc.) also assume rUK basic rate 20% for relief-at-source — but a Scottish starter-rate taxpayer (19%) still gets 20% relief at source under the HMRC rule, so this would only matter for higher-rate reclaim calculations.

**Fix:** Either explicitly document that Fynla supports rUK only (and validate user residency), or implement Scottish bands. At minimum, the `Users` table needs a residency flag and reads to `scotland.bands` when set.

### 2.5 Marriage Allowance — hardcoded basic-rate × 10% formula

**Severity:** Low · **Confidence:** High · **Files:** `app/Services/Investment/Recommendation/SpouseOptimisationService.php:501-515`, `app/Services/Tax/TaxOptimisationService.php:415`

Code computes Marriage Allowance saving as `(PERSONAL_ALLOWANCE × 0.10) × 0.20 = £251.40`. The exact rule for 2025/26 onward is `£1,260 × 20% = £252` (HMRC publishes the £1,260 figure directly, which is 10% of the PA rounded). The seeder already stores `income_tax.marriage_allowance.amount = 1260` (line 132 of 2025/26 config) — the code should read that key.

Negligible numerical impact (£0.60), but the wrong pattern: a future PA change without a proportional MA change would silently desynchronize.

---

## 3. Capital Gains Tax — specific findings

### 3.1 CGT rates aligned correctly in seeder, stale in fallbacks

**Severity:** High · **Confidence:** High

The seeder correctly reflects the post-Oct 2024 alignment: residential and non-residential rates both 18%/24%. However:

- `BedAndISACalculator.php:60` falls back to `0.20` (pre-Oct 2024).
- `TaxOptimizationAnalyzer.php:418,444` and `ISAAllowanceOptimizer.php:232` fall back to `0.20` / `0.10`.
- `BusinessInterestService.php:172-173` fall back to `0.20` / `0.10`.

If `TaxConfigService` returns null for any reason (seeder unrun, config corruption), these calculators silently apply old rates. Given the 6pp rate increase, a £10k gain mis-calculated would understate CGT by £600 — large enough to surface in user-facing recommendation text.

**Fix:** Either (a) raise an exception when the config is missing, or (b) update all fallbacks to current rates AND add a CI test that diffs `TaxDefaults` against the active seeder config.

### 3.2 Business Asset Disposal Relief — fallback stale, user-facing message stale

**Severity:** High · **Confidence:** High · **File:** `app/Services/Business/BusinessInterestService.php:171,189`

- Line 171: `$badrRate = $cgtConfig['business_asset_disposal_relief_rate'] ?? 0.10` — fallback 10% is 2023/24 era. 2025/26 is 14%, 2026/27 is **18%**.
- Line 189: User-facing warning hardcoded as `'The 10% rate requires 2+ years ownership and trading business status.'` This is **factually incorrect for 2026/27** — BADR rate is now 18%.

**HMRC reference:** Finance Act 2024 (BADR rate increased to 14% from April 2025) and Autumn Budget 2024 (further increase to 18% from April 2026).

**Fix:** Replace the hardcoded string with `sprintf('The %.0f%% rate requires 2+ years ownership and trading business status.', $badrRate * 100)`.

### 3.3 No Investors' Relief handling

**Severity:** Low · **Confidence:** High

The seeder has nothing for Investors' Relief (£10m lifetime cap, paralleling BADR rate trajectory). For most users this is irrelevant, but high-net-worth disposal recommendations should mention it. Not a defect, but a coverage gap.

### 3.4 Trust CGT rate uses individual higher-rate fallback in places

**Severity:** Low · **Confidence:** Medium

`TaxConfigService::getCapitalGainsTax()['trust_rate'] = 0.24` is correctly seeded but no code path in `app/Services/` actually reads it. Estate/trust calculations default to individual rates. For discretionary trusts paying CGT on disposals, this would apply individual basic rate (18%) instead of trust 24% — a 6pp under-statement.

### 3.5 60-day residential CGT reporting — not modeled

**Severity:** Info · **Confidence:** High

CGT on residential property requires reporting to HMRC within 60 days of completion (HMRC manual CG-APP18). No code surfaces this deadline. Not a calculation issue, but the IHT/CGT recommendation outputs should include this reminder when a property sale is being modeled.

---

## 4. Inheritance Tax — specific findings

### 4.1 NRB / RNRB calculation in `IHTCalculationService` — clean

**Severity:** n/a · **Confidence:** High

`app/Services/Estate/IHTCalculationService.php` correctly:
- Reads NRB and RNRB from `TaxConfigService::getInheritanceTax()` (lines 111, 1148).
- Implements RNRB taper at £1/£2 over £2m (line 1201-1202).
- Doubles NRB and RNRB for married couples and handles transferable allowances for widows.
- Implements the 36% reduced rate for 10%+ charitable giving (line 1237-1289).
- The "baseline" for 10% charity check correctly excludes RNRB (line 1247).

**This is the cleanest tax-calculation code in the audit.** All thresholds source from config; doubling/transferred logic is explicit; user-facing messages quote actual values from the config.

### 4.2 PET 7-year and 14-year rules — implemented but worth verifying

**Severity:** Medium · **Confidence:** Medium · **File:** `app/Services/Estate/SpouseNRBTrackerService.php:38-58`

The spouse-NRB-tracking code applies the 14-year rule but only to **CLTs**, not failed PETs. The 14-year rule actually applies to *failed PETs that affect later CLT NRB calculations* (see seeder `fourteen_year_rule` definition at line 359-371 — which the code at `SpouseNRBTrackerService.php:48-54` does not faithfully implement).

The PET-vs-CLT chronological ordering is also simplified. HMRC's IHT400 manual requires gifts to be ordered chronologically and NRB allocated against them in order — failed PETs from years 7-14 reduce NRB for CLTs but **not** for the death estate. The current code mixes them.

**Compliance impact:** Wealthy users with both lifetime trusts and large PETs may receive incorrect NRB availability.

### 4.3 Taper relief schedule — correct

**Severity:** n/a · **Confidence:** High

`TaxConfigService::getTaperRelief('pet')` returns the canonical schedule (0-3y = 100% tax, 3-4y = 80%, 4-5y = 60%, 5-6y = 40%, 6-7y = 20%, 7y+ = 0%). The `getGiftTaxRate()` helper applies this correctly. ✓

### 4.4 BPR / APR — handles 2026 reform but rate semantics worth reviewing

**Severity:** Medium · **Confidence:** Medium · **File:** `database/seeders/TaxConfigurationSeeder.php:425-468, 1278-1295`

The 2026/27 config introduces `allowance_cap = 2500000` for both APR and BPR with `relief_above_cap = 0.5`. **However, looking at the announcement timeline**:
- The reform announced in Autumn Budget 2024 set the cap at **£1m** (not £2.5m) for combined APR/BPR over £1m at 50%.
- The cap was raised to £2.5m at a later announcement (the seeder code comment says "Dec 2025 announcement raised from £1m"). This is plausible but needs verification against actual published HMRC guidance — I cannot confirm a £2.5m cap from the Autumn 2024 reform.

**Action required:** Verify the £2.5m cap exists in current HMRC guidance. If the original £1m cap is still in force, this is a significant numerical defect.

Additionally: AIM shares dropping from 100% to 50% from April 2026 (seeder line 1288: `aim_shares = 0.5`) matches Autumn 2024 Budget. ✓

### 4.5 Pension IHT inclusion (April 2027) — partially implemented

**Severity:** Low · **Confidence:** High · **File:** `app/Services/Estate/IHTCalculationService.php:1546-1635`

`calculatePensionAmendmentScenario()` correctly models the dual-scenario (pre-2027 vs post-2027) projection. The seeder has `pension_iht_inclusion.effective_date = '2027-04-06'`. The display logic shows additional IHT if pension included.

However: the post-2027 calculation appears to include the **full** DC pension pot in the taxable estate, but HMRC's published consultation (Budget 2024) had specific carve-outs (e.g., death benefits paid to a spouse remain exempt under spouse exemption rules; pre-2027 lump-sum allowance consumption). The implementation may overstate IHT for pensions inherited by spouses.

### 4.6 Hardcoded `£250` in user-facing warning text

**Severity:** Low · **Confidence:** High · **File:** `app/Services/Estate/GiftingStrategy.php:147`

```php
'warning' => $isValid ? null : "Exceeds £250 limit for recipient: {$recipient}",
```

Reads the limit from config above but hardcodes the display value. If the small gifts exemption ever changes, this string will lie.

### 4.7 Wedding gift relationship matching loses cousin / sibling cases

**Severity:** Low · **Confidence:** Medium · **File:** `app/Services/Estate/GiftingStrategy.php:170-174`

The `match()` only handles 'child', 'son', 'daughter', 'grandchild', 'grandson', 'granddaughter', 'great_grandchild', defaulting everything else to "other" (£1,000 cap). HMRC allows £5,000 between parents-to-children but the alias list omits 'stepchild' / 'adopted_child' — both qualify as children under HMRC rules.

---

## 5. Pensions — specific findings

### 5.1 PCLS (25% tax-free lump sum) — no Lump Sum Allowance (LSA) cap

**Severity:** Critical · **Confidence:** High · **Files:** `app/Services/Retirement/RetirementIncomeService.php:256,1937`, `app/Services/Retirement/DecumulationPlanner.php:232`, `app/Services/Retirement/RetirementActionDefinitionService.php:1982`

Every PCLS calculation is `$pensionPot * 0.25` with no cap. The Lump Sum Allowance (LSA) caps lifetime tax-free PCLS at **£268,275**. For a user with a £1.5m pension pot:
- Current code: `£1,500,000 × 0.25 = £375,000 tax-free`
- HMRC rule: capped at `£268,275 tax-free`; the remaining £106,725 of would-be PCLS is taxable as pension income (or excluded).

This is the largest single defect in the audit. It affects:
- `RetirementIncomeService::buildAvailableAccounts()` building the retirement income waterfall.
- `DecumulationPlanner::calculatePCLSStrategy()` directly exposed via API endpoint `/api/retirement/decumulation/pcls-strategy`.
- Strategy generation that quotes a PCLS figure ("Take £375,000 tax-free…").

**Fix:**
```php
$lsa = (float) $this->taxConfig->get('pension.lump_sum_allowance', 268275);
$pclsAvailable = min($pensionPot * 0.25, $lsa);
```
And surface `pension.lump_sum_allowance` AND `pension.lump_sum_and_death_benefit_allowance` (£1,073,100) in the seeder (currently absent).

**HMRC reference:** Finance Act 2024 abolishing the Lifetime Allowance and introducing LSA / LSDBA.

### 5.2 Lifetime Allowance / LSA / LSDBA not in config

**Severity:** High · **Confidence:** High · **File:** `database/seeders/TaxConfigurationSeeder.php:234-282`

The pension section has `'lifetime_allowance_abolished' => true` but no `'lump_sum_allowance'` or `'lump_sum_and_death_benefit_allowance'` keys. The frontend has `PENSION_TAX_FREE_LUMP_SUM_LIMIT = 268275` in `taxConfig.js`. Backend should add:

```php
'pension' => [
    // ...
    'lifetime_allowance_abolished' => true,
    'lump_sum_allowance' => 268275,                    // LSA (PCLS lifetime cap)
    'lump_sum_and_death_benefit_allowance' => 1073100, // LSDBA (death-benefit cap, age < 75)
    // ...
],
```

`TaxConfigService` should expose `getLumpSumAllowance()` and `getLumpSumAndDeathBenefitAllowance()` helpers.

### 5.3 Tapered Annual Allowance — implemented correctly

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/Retirement/AnnualAllowanceChecker.php:117-178`

Threshold income £200k, adjusted income £260k, £1 reduction per £2, minimum £10k — all correctly read from config and applied (lines 123, 164-178). ✓

### 5.4 Carry-forward — implementation depends on user-entered data

**Severity:** Low · **Confidence:** High · **File:** `app/Services/Retirement/AnnualAllowanceChecker.php:189-207`

Carry forward returns 0 unless the user has manually populated `RetirementProfile::prior_year_unused_allowance`. HMRC's actual rule requires the user to have been a member of a registered pension scheme in each of the prior 3 years; the code does not check membership.

Acceptable for a planning tool (user is invited to enter the data themselves), but documentation should be clearer that this is user-driven, not auto-calculated.

### 5.5 MPAA — correctly modelled

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/Retirement/AnnualAllowanceChecker.php:231-254`

MPAA flag from `DCPension::has_flexibly_accessed`, amount from config (£10,000). ✓

### 5.6 Pension tax relief via salary sacrifice — employer NI fallback stale

**Severity:** Medium · **Confidence:** High · **File:** `app/Services/Retirement/SalarySacrificeAnalyzer.php:305-308`

```php
$employerRate = (float) $this->taxConfig->get(
    'national_insurance.class_1.employer.rate',
    0.138
);
```

Fallback `0.138` is the pre-April-2025 rate. Active rate is **0.15**. If config lookup fails, employer NI saving from salary sacrifice is under-stated by ~12%.

### 5.7 State Pension — correctly read from config

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/Retirement/PensionProjector.php:145-151`

Reads `state_pension_forecast_annual` from user record, falls back to `pension.state_pension.full_new_state_pension` from config (£12,547.60 for 2026/27). ✓

### 5.8 State Pension Age — hardcoded to 67

**Severity:** Low · **Confidence:** High · **File:** `app/Services/Retirement/DecumulationPlanner.php:269,279-280`

Decumulation phasing strategy hardcodes "State Pension Age 67" in age-range strings. SPA is actually 66 currently (rising to 67 between April 2026 and April 2028). The seeder has `pension.state_pension.current_spa = 66` and `future_spa = 67`. The hardcoded string in `modelIncomePhasing()` should template these in.

### 5.9 Pension contribution tax relief — uses 20%/40%/45% hardcoded relief rates

**Severity:** Medium · **Confidence:** High · **File:** `app/Services/Retirement/RetirementStrategyService.php:699-702,1261,1270,1273`

Pension relief calculations use:
- `$grossContribution * 0.20` for "HMRC adds" (relief at source)
- `$grossContribution * 0.20` self-assessment refund for higher-rate
- `$grossContribution * 0.25` self-assessment refund for additional-rate
- `$grossContribution * 0.80` net upfront cost

These hardcoded factors assume 20% basic rate. If basic rate changes (or for Scottish taxpayers where Scottish basic is 20% but starter is 19%), the relief at source AND the self-assessment top-up math break.

**Fix:** Derive `$basicRate = $bands[0]['rate']`, then `$reliefAtSourceFactor = $basicRate / (1 - $basicRate) = 0.25` and `$netCostFactor = 1 - $basicRate = 0.80` — but read basic rate from config.

### 5.10 Auto-enrolment — qualifying earnings band correct

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/Retirement/PensionContributionOptimizer.php:306-308`

Reads `pension.auto_enrolment.upper_qualifying_earnings` correctly. Fallback £50,270 ✓.

---

## 6. ISA / Savings — specific findings

### 6.1 ISA allowance enforcement — correct in form requests

**Severity:** n/a · **Confidence:** High · **Files:** `app/Http/Requests/StoreInvestmentAccountRequest.php:63`, `app/Http/Requests/UpdateInvestmentAccountRequest.php:69`, `app/Http/Controllers/Api/Investment/AssetLocationController.php:46`

Validation rules `max:'.TaxDefaults::ISA_ALLOWANCE` correctly cap ISA subscriptions at £20,000. ✓

### 6.2 ISA tracking — covers Cash, S&S, LISA cleanly

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/Savings/ISATracker.php`

The ISA tracker pulls usage from both `SavingsAccount` and `InvestmentAccount` tables, handles the "current tax year" vs "calendar tax year" question for projected vs explicit subscriptions, and falls back gracefully when explicit subscription tracking is missing. ✓

### 6.3 LISA — no enforcement of £450k property cap on goal data

**Severity:** Low · **Confidence:** High

Hardcoded in `LifecycleStrategy.php:53` user-facing text but not enforced in goal/property purchase planning. A user setting a £600k house-purchase goal with LISA funding gets no warning that LISA bonus is not available for properties over £450k. **The property cap should live in `TaxConfigService` as `isa.lifetime_isa.max_property_value = 450000`** and be checked in `GoalAssignmentService::calculatePropertyCosts()`.

### 6.4 LISA withdrawal penalty — correct in seeder, not surfaced in calculations

**Severity:** Low · **Confidence:** High

Seeder line 213 has `'withdrawal_penalty' => 0.25` (25% penalty on non-qualifying withdrawals). No service calculates this for users considering early withdrawal scenarios. The 25% penalty actually exceeds the 25% bonus (it's 25% of grossed-up amount, so the user loses money) — this is a critical user-facing rule that the planning engine should warn about.

### 6.5 PSA — correctly band-based

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/Savings/PSACalculator.php`

Determines tax band by stored income, returns correct PSA from config (£1,000 / £500 / £0 / unlimited for non-taxpayer). ✓

### 6.6 Starting Rate for Savings (£5,000 @ 0%) — not applied in main tax calculation

**Severity:** Medium · **Confidence:** High · **Files:** `app/Services/UKTaxCalculator.php:687-740`, `app/Services/Savings/PSACalculator.php`

The SRS exists in `TaxConfigService` (`income_tax.starting_rate_for_savings`) and is referenced in `TaxStrategyCalculator.php`, but the main `UKTaxCalculator::calculateIncomeTax()` doesn't apply it. A non-earner with £20,000 of interest:
- HMRC rule: PA £12,570 + SRS £5,000 + PSA £1,000 = £18,570 tax-free; £1,430 @ 20% = £286 tax.
- Current code: PA £12,570 + PSA £1,000 = £13,570 tax-free; £6,430 @ 20% = £1,286 tax.

A **£1,000 over-statement** for a basic taxpayer pensioner relying mainly on savings. This is a substantive UK tax engine defect.

**Fix:** In `calculateInterestTaxDetailed()` and `calculateIncomeTax()` (interest section), the starting rate band must be allocated to interest before PSA, up to `max(0, £5,000 - (non-savings non-dividend income above PA))`.

---

## 7. National Insurance — specific findings

### 7.1 Class 1 employee, Class 4 self-employed — correct

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/UKTaxCalculator.php:777-843`

Reads thresholds and rates from config. Class 1 main 8% / additional 2%, Class 4 main 6% / additional 2%, primary threshold £12,570, UEL £50,270. ✓

### 7.2 Class 1 employer — rate available but rarely used; secondary threshold £5,000 in config

**Severity:** Low · **Confidence:** High

Seeder correctly reflects the April 2025 Autumn Budget 2024 reforms: employer rate 15%, secondary threshold £5,000 (down from £9,100). Code reads these via `getNationalInsurance()`. Only `SalarySacrificeAnalyzer` and a few strategies consume employer NI; nothing else simulates employment cost. ✓

### 7.3 Class 2 — correctly flagged abolished

**Severity:** n/a · **Confidence:** High

`class_2.abolished = true` in seeder; no code references Class 2 contributions for income calculation. ✓

### 7.4 No NI on pension income

**Severity:** Info · **Confidence:** High

Code correctly avoids charging NI on pension income (the income tax calculator's `pension` slot is treated alongside earned but no Class 1/4 is applied to it). ✓ HMRC rule satisfied.

---

## 8. Savings & Dividends — specific findings

### 8.1 Dividend tax rates — stale fallbacks everywhere

**Severity:** High · **Confidence:** High

See §1a above. Numerous files fall back to 2025/26 rates (8.75% / 33.75% / 39.35%) when the active year is 2026/27 (10.75% / 35.75% / 39.35%). The most concerning are:

- `TaxDragCalculator.php:176-178` — entirely hardcoded match expression with no config lookup.
- `DividendTaxCalculator.php` — uses config but with 2025/26 fallbacks.

If config lookup succeeds (the normal case), output is correct. But any test environment without the seeded config will silently produce 2pp-too-low dividend tax for basic and higher-rate taxpayers.

### 8.2 Dividend allowance £500 — applied correctly

**Severity:** n/a · **Confidence:** High

Allowance reads from `dividend_tax.allowance`. The £500 fallback is current. ✓

---

## 9. Property / SDLT — specific findings

### 9.1 SDLT calculation — config-driven, but key-naming inconsistency

**Severity:** Medium · **Confidence:** High · **Files:** `app/Services/Property/PropertyTaxService.php`, `app/Services/Goals/GoalAssignmentService.php:122-123`

`PropertyTaxService::calculateSDLT()` correctly reads `residential.first_time_buyers` (plural) per seeder.

`GoalAssignmentService::calculateSDLT()` at line 123 reads `residential.first_time_buyer` (singular). The seeder only has plural `first_time_buyers`. **First-time-buyer relief will not load in goal planning**; the fallback `?? $bands` returns standard bands. First-time buyers planning a property purchase via the Goals module are quoted full SDLT.

**Numerical impact:** On a £400k FTB property:
- Correct: 0% on first £300k, 5% on £100k = £5,000 SDLT.
- Current (Goals module): 0% on first £125k, 2% on £125k, 5% on £150k = £10,000 SDLT.
- **Double-charged** by £5,000.

**Fix:** Rename to `first_time_buyers` in `GoalAssignmentService.php:123` OR rename in seeder. Pick a convention.

### 9.2 FTB property value threshold mismatch

**Severity:** High · **Confidence:** High · **File:** `app/Services/Goals/GoalAssignmentService.php:122`

```php
if ($isFirstTimeBuyer && $propertyPrice <= 625000) {
```

The seeder for 2025/26 and 2026/27 has `first_time_buyers.max_property_value = 500000` (lowered from £625,000 in April 2025 Budget). The Goals SDLT code uses the old £625,000 threshold, so FTBs buying £500–625k properties will be incorrectly treated as eligible and computed with FTB bands — and given that those bands don't have a tier above £300k for properties up to £500k, the calc would just stop. **Plus the wrong-key bug from §9.1 means FTB bands aren't even being loaded.**

This is two stacked bugs cancelling differently depending on price. Critical for the FTB user journey.

### 9.3 SDLT additional property surcharge — config and code in sync

**Severity:** n/a · **Confidence:** High · **File:** `PropertyTaxService.php:48-57`

Reads `additional_properties.bands` which has the 5% surcharge baked into each band (5/7/10/15/17%). Matches the Oct 2024 Autumn Budget rates. ✓

### 9.4 Rental income — no property allowance / rent-a-room

**Severity:** Medium · **Confidence:** High · **File:** `app/Services/Property/PropertyTaxService.php:170-266`

`calculateRentalIncomeTax()` deducts allowable expenses + mortgage interest tax credit. Missing:

- **£1,000 property allowance** (ITTOIA 2005 s.783A). For users earning < £1,000/year in rental, no tax is due and no return needed. Code charges them at marginal rate.
- **£7,500 rent-a-room relief**. For users renting a room in their main residence (typically lodgers), the first £7,500/year is tax-free. Code does not surface this.

For a user with £5,000/year of lodger income, current code charges 20%/40% on the full £5,000; correct treatment is £0 tax (rent-a-room). For users with £900 of rent from a small driveway / parking space, correct treatment is £0 tax (property allowance).

**Fix:** Add allowances to seeder (`property.property_allowance = 1000`, `property.rent_a_room_relief = 7500`) and check in `calculateRentalIncomeTax()` before applying expense deduction.

### 9.5 Section 24 mortgage interest credit hardcoded to 20%

**Severity:** Low · **Confidence:** High · **Files:** `app/Services/Property/PropertyTaxService.php:198-200`, `app/Services/Property/PropertyService.php:144`

Section 24 mandates basic rate (20%) relief. Hardcoding 20% is HMRC-accurate today, but should derive from `bands[0]['rate']` for consistency with the rest of the engine.

### 9.6 LBTT / LTT (Scotland, Wales) — frontend only

**Severity:** Low · **Confidence:** High

Frontend `taxConfig.js` has LBTT and LTT bands (lines 92-113) but no backend service computes these. Scottish/Welsh property purchases through the Goals module use SDLT (English) rates.

---

## 10. Child Benefit / HICBC — specific findings

### 10.1 HICBC calculation — correctly uses £60k / £80k bands

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/Benefits/ChildBenefitService.php:99-137`

Reads `high_income_charge_threshold` (£60k) and `high_income_full_clawback` (£80k) and applies 1% per £200 clawback. ✓

### 10.2 Adjusted net income calculation — over-simplified

**Severity:** Medium · **Confidence:** High · **File:** `app/Services/Benefits/ChildBenefitService.php:213-230`

`calculateAdjustedNetIncome()` sums gross income only; does not deduct pension contributions or grossed-up Gift Aid (the code comment acknowledges this). For a £75k earner contributing £20k gross to pension, true ANI is £55k → no HICBC; current code calculates ANI £75k → HICBC clawback (75-60)/20 × 1% = 7.5% × £1,354.60 = £101.60 incorrectly charged.

This is the same defect as §2.2 but specifically affects HICBC users. Together they comprise a class-of-bug: the engine treats gross income as ANI throughout.

### 10.3 Child Benefit rates — correctly seeded

**Severity:** n/a · **Confidence:** High

2026/27 rates `27.05` / `17.90` correctly seeded (lines 1301-1304). Frontend may still display 2025/26 rates if cached.

---

## 11. Trust taxation — specific findings

### 11.1 Trust income tax — correctly handles discretionary vs IIP

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/UKTaxCalculator.php:424-527`

Reads trust rates from `trusts.income_tax.discretionary.standard_rate` and `trusts.income_tax.interest_in_possession.standard_rate`. Handles bare and settlor-interested trusts (taxed at beneficiary/settlor marginal rate). ✓

### 11.2 Trust dividend rate — hardcoded fallback in `TrustService`

**Severity:** Low · **Confidence:** High · **File:** `app/Services/Estate/TrustService.php:171`

IIP trust dividend rate fallback `0.0875` is 2025/26 (2026/27 raises it to 0.1075 per Autumn Budget 2024 plan). Seeder `trusts.income_tax.interest_in_possession.dividend_rate` is correctly set to 0.1075 in 2026/27 (line 1362), so the config path is fine — but the fallback is stale.

### 11.3 Trust £500 de minimis allowance — handled in calculator

**Severity:** n/a · **Confidence:** Medium · **File:** `app/Services/UKTaxCalculator.php:424-527`

The trust tax calculator applies trust rates to the full trust income but does not currently subtract the £500 de minimis allowance (or split between trusts sharing it). Trust returns of £450 should pay 0%; the code charges 45% (discretionary) on the full £450 = £202.50 incorrect tax.

**HMRC reference:** Standard rate band for trusts, IHTM 5119A.

### 11.4 Trust periodic / exit charges — present in config, no UI service exposes calculation

**Severity:** Info · **Confidence:** High

The 10-year periodic charge (6% max) and exit charge are fully specified in `inheritance_tax.trust_charges` and `trusts.inheritance_tax` config — but there is no service that runs the calculation for an existing trust. Calculator-style endpoints not in scope of this review.

---

## 12. Tax year handling — specific findings

### 12.1 `getCurrentTaxYear()` — backend reads active config, frontend uses date math

**Severity:** Low · **Confidence:** High

Backend: `TaxConfigService::getTaxYear()` returns the active configured tax year (which an admin can override).

Frontend: `taxConfig.js` exports a constant `TAX_YEAR = '2026/27'`. There is no function — components either use the constant or call an API endpoint.

**Inconsistency:** If an admin switches active tax year (via Tax Settings admin UI), backend recalculates against the new year, but frontend hardcoded constants don't change until app rebuild. Users would see mixed tax years in the UI.

**Fix:** Frontend should expose `getCurrentTaxYear()` that reads from a fetched tax-settings endpoint or Vuex store, falling back to `TAX_YEAR` constant only when offline.

### 12.2 Tax year roll-over on 6 April — `AnnualAllowanceChecker::getCalendarTaxYear()` correct

**Severity:** n/a · **Confidence:** High · **File:** `app/Services/Retirement/AnnualAllowanceChecker.php:82-89`

Correctly determines whether the current date is before or after 6 April. ✓

### 12.3 Historical tax year support — partial

**Severity:** Low · **Confidence:** Medium

Seeder supports 2021/22 through 2026/27 (6 years). However:

- The 2021/22 fallback only adjusts `bands[1]` and CGT/Blind Person allowance. It does **not** adjust dividend rates, pension AA (was £40k in 2022/23), or NI rates (Class 1 main was 13.25% in mid-22/23). Using `is_active = true` on a historical year would produce mixed-era calculations.
- This matters for IHT 7-year gift cumulation: a 2019 PET against a 2026 death uses the 2026 NRB (correct per HMRC) but if any calculations cite "rate at time of gift", that data is incomplete.

---

## 13. Rounding & precision — specific findings

### 13.1 Currency uses `float` throughout

**Severity:** Low · **Confidence:** High

All tax calculations use PHP `float`. For amounts under £100m, IEEE 754 precision is more than sufficient — but the codebase rounds with `round($value, 2)` only at output time. Intermediate arithmetic accumulates floating-point errors. For very precise self-assessment outputs this could matter; for planning recommendations it does not.

**Note:** Casting to float via `(float)` is rampant (e.g. `(float) ($cgt['annual_exempt_amount'] ?? 3000)`). No `bcmath` or `Brick\Money` is used. Acceptable for a planning tool but documented for awareness.

### 13.2 HMRC rounding convention — not enforced

**Severity:** Low · **Confidence:** High

HMRC rounds income tax DOWN to the nearest pound (favourable to taxpayer). The code uses `round($x, 2)` (standard rounding, can round up). For typical recommendation outputs this is fine. For published self-assessment-style values, it would diverge from HMRC's calculation.

---

## 14. Frontend tax constants — specific findings

### 14.1 Hardcoded `£325,000` and `£175,000` throughout content/learn pages

**Severity:** Low · **Confidence:** High · **Files:** `resources/js/views/Public/learn/*`, `views/Public/insights/InheritanceTaxExplainedPage.vue`, etc.

Public learn / insights pages have NRB and RNRB values hardcoded into HTML. NRB has been £325k since 2009 so this is unlikely to change soon — but the frozen-until dates have been pushed multiple times.

**Fix:** Bind learn content to `TAX_CONFIG` constants from `taxConfig.js`, or load from a tax-settings endpoint.

### 14.2 Personal allowance and band thresholds in Vue components

**Severity:** Low · **Confidence:** High

- `resources/js/components/Retirement/SalarySacrificeDisplay.vue:245` — `this.analysis?.personal_allowance || 12570` (fallback OK; backend should send the value).
- `resources/js/constants/lifeStageConfig.js:244,784` — hardcoded `£12,570` in `quickStat.value` (display only).
- `resources/js/views/Public/learn/tax/TaxYearChecklistPage.vue:91` — `£12,570 / £1,260 / £252` Marriage Allowance numbers hardcoded.

These are public-facing content, not calculation engines, so impact is informational rather than financial.

### 14.3 `taxConfig.js` mostly accurate for 2026/27

**Severity:** n/a · **Confidence:** High

`taxConfig.js` values match the seeder 2026/27 config (BADR 18%, dividend basic 10.75%, etc.). One subtle issue:

- Line 145: `PENSION_TAX_FREE_LUMP_SUM_LIMIT = 268275` with comment "Lifetime limit". This is actually the LSA, **not the lifetime cap on pension value** (which is abolished). The name is misleading and may cause confusion in calculator code at `CalculatorsPage.vue:2375`. The `Math.min(... PENSION_TAX_FREE_LUMP_SUM_LIMIT)` correctly caps the lump sum at LSA. ✓

---

## 15. Edge cases — specific findings

### 15.1 Negative income / losses — not handled

**Severity:** Low · **Confidence:** Medium

`UKTaxCalculator::calculateDetailedNetIncome()` assumes all income inputs are ≥ 0. A user with rental losses or trading losses would either error or charge tax on the negative figure. No explicit guard.

### 15.2 £0 income — handled correctly

**Severity:** n/a · **Confidence:** High

PA is preserved at £12,570; no tax computed. ✓ Calculator paths return zero arrays.

### 15.3 Very high incomes — protected by `max(0, …)` patterns

**Severity:** n/a · **Confidence:** High

Income £10m: PA fully tapered to 0 at £125k+; additional rate applies cleanly to all income above £125,140. No overflow risk for floats under £10^15.

### 15.4 Foreign income / non-domiciled regime change (April 2025 FIG)

**Severity:** Info · **Confidence:** High

The April 2025 abolition of the non-dom regime, replaced by the Foreign Income and Gains (FIG) regime with a 4-year exemption for new UK residents, is **not modelled at all**. Seeder still has the old `domicile.non_uk_domiciled.deemed_domicile_years = 15`. For a niche but high-stakes user group, this would generate incorrect IHT recommendations.

---

## 16. Projection logic — specific findings

### 16.1 Inflation assumption hardcoded at 2.5%

**Severity:** Low · **Confidence:** High · **File:** Seeder `assumptions.inflation = 0.025`

Single source of truth for inflation across projections. Reasonable.

### 16.2 Frozen thresholds modelled? Mixed

**Severity:** Medium · **Confidence:** Medium

PA frozen until April 2028 (recently extended in Budget), NRB/RNRB frozen until April 2031, pension AA frozen indefinitely. The seeder reflects these frozen values across multiple tax-year configs (2021/22 through 2026/27 all have the same PA £12,570). But projection code (e.g. `IHTCalculationService::calculateProjectedValues()`) doesn't model future thaw — assumes thresholds stay frozen forever.

For a user projecting IHT in 2040, the NRB of £325k is unrealistic. **No "real" growth projection of thresholds.** Defensible (HMRC has no published plan) but a transparency gap.

### 16.3 Risk-based growth rates — sourced from config

**Severity:** n/a · **Confidence:** High · **File:** Seeder `assumptions.growth_by_risk`

`very_low: 0.02, low: 0.035, low_medium: 0.04, medium: 0.05, medium_high: 0.06, high: 0.07` — reasonable. Used consistently via `TaxConfigService::getAssumptions()`. ✓

---

## 17. Areas confirmed clean

The following code paths read every tax value from `TaxConfigService`, handle thresholds correctly, and require no remediation:

- **`app/Services/Estate/IHTCalculationService.php`** — NRB, RNRB, RNRB taper, transferable allowances, 36% charity rate, baseline calculation, 2027 pension amendment scenario. Fully config-driven.
- **`app/Services/TaxConfigService.php`** — Service is well-structured, single-source-of-truth, request-scoped caching, helper methods for each domain. The blueprint other services should follow.
- **`app/Services/Retirement/AnnualAllowanceChecker.php`** — AA, tapered AA, MPAA, carry-forward all config-driven.
- **`app/Services/Benefits/ChildBenefitService.php`** (except adjusted net income calculation — see §10.2) — HICBC bands and rates correct.
- **`app/Services/Savings/ISATracker.php`** — ISA usage tracking across savings and investment accounts.
- **`app/Services/Savings/PSACalculator.php`** — Personal Savings Allowance band determination.
- **`app/Services/Property/PropertyTaxService.php::calculateSDLT()`** — SDLT calculator (but see §9.1 for the sister `GoalAssignmentService::calculateSDLT()` which is broken).
- **`app/Constants/TaxDefaults.php`** — Constants are 2026/27-accurate and used appropriately as fallbacks.
- **`app/Services/UKTaxCalculator.php`** — Main income tax / NI calculator, with exceptions noted in §2 and §6.
- **`app/Services/TaxBandTracker.php`** — Stack-allocation logic correct, with the caveat noted in §2.1.

---

## 18. Recommended remediation priorities

### P0 — fix immediately

1. **§5.1 PCLS uncapped at LSA £268,275.** Any retirement user with > £1.073m DC pot is being told they can take more tax-free than HMRC allows.
2. **§9.1 + §9.2 SDLT FTB key-naming + threshold mismatch in `GoalAssignmentService`.** First-time buyers planning a property purchase get a £5,000+ over-statement of stamp duty.
3. **§2.1 Band-ceiling formula bug (latent).** Add architecture test: `$bands[1]['upper_limit'] === $personalAllowance + $bands[1]['max'] - $personalAllowance` is wrong — switch to `upper_limit` everywhere.

### P1 — fix before next deploy

4. **§6.6 Starting Rate for Savings not applied.** Pensioners with savings interest are over-charged tax.
5. **§4.4 BPR / APR £2.5m cap — verify against HMRC.** If cap is actually £1m, every business owner gets a wrong IHT estimate.
6. **§3.2 BADR rate stale literal and stale user-facing message ("10% rate") in `BusinessInterestService`.**
7. **§2.2 + §10.2 Adjusted Net Income calculation simplification.** Affects PA taper, HICBC, and any income-dependent recommendation engine.

### P2 — fix in the next review cycle

8. **§1a Replace stale fallback literals** with current values (or raise an exception when config missing).
9. **§9.4 Add property allowance (£1,000) and rent-a-room relief (£7,500).**
10. **§11.3 Apply £500 de minimis allowance to trust income.**
11. **§6.4 Surface LISA 25% withdrawal penalty in calculations.**
12. **§5.2 Add LSA / LSDBA keys to seeder pension config.**
13. **§2.4 Scotland support — decide and document, or implement.**

### P3 — quality

14. **§4.6, §3.2, §1b Hardcoded user-facing values in text strings.** Replace with templated values from config.
15. **§14 Frontend hardcoded tax content.** Bind learn / insights pages to taxConfig constants.
16. **§5.9 Pension relief factors derived from basic rate.** Centralise the `0.20 / 0.80 / 0.25` factor math.
17. **§5.8 SPA hardcoded to 67 in phasing strategy.**
18. **§0 Update CLAUDE.md "UK Tax Context" to reflect active 2026/27 year, or rename to "UK Tax Context (illustrative)".**

---

## 19. Suggested architecture-test guards

Add Pest architecture tests in `tests/Architecture/` to prevent regression:

```php
test('no hardcoded NRB / RNRB / pension AA outside TaxDefaults and TaxConfigService')
    ->expect('App\Services')
    ->not->toHaveLiteral([325000, 175000, 268275, 1073100])
    ->ignoring(['App\Services\TaxConfigService']);

test('all band ceilings derived from upper_limit, not max')
    ->expect('App\Services')
    ->not->toUseCode('personalAllowance + $bands[1][\'max\']');

test('fallback rates match active tax year')
    // diff TaxDefaults constants vs $taxConfigService->getAll() for the active year
```

The seeder/`TaxDefaults` sync test alone would have caught most of §1a.

---

## 20. Summary

- **1 critical bug** that would cause real user-facing over-statement of tax-free pension cash (§5.1 PCLS / LSA).
- **1 critical bug** affecting first-time home buyers' SDLT estimate (§9.1 / §9.2).
- **1 critical latent bug** in income tax band ceiling math, currently masked by PA taper (§2.1).
- **1 high-severity factual omission**: Starting Rate for Savings not applied (§6.6).
- **30+ stale fallback literals** (`?? 0.0875`, `?? 0.10`, etc.) that will silently produce wrong-year output if config lookup fails.
- **Foundational gaps**: no LSA/LSDBA keys, no Scotland support, no property allowance / rent-a-room, no LISA property cap enforcement, no full FIG-regime modelling.
- **Calculation cleanliness**: the IHT engine, AA checker, ISA tracker, and PSA calculator are the strongest pieces of code reviewed. The dividend tax calculator and bed-and-ISA engine have the highest concentration of stale fallbacks.

The codebase enforces "no hardcoded tax values" as a rule but enforcement has drifted as new code was added. A single architecture test plus a periodic sync between `TaxDefaults`, `TaxConfigurationSeeder`, and `taxConfig.js` would prevent most of the findings in this review.

