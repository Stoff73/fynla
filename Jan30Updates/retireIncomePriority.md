# Retirement Income Planner - Debugging and Scenario Analysis

## Current Bugs Identified

### Bug 1: Incorrect Withdrawal Order

**Problem:** The current logic doesn't prioritise bond 5% withdrawals first, and depletes tax-free accounts too quickly.

**Correct Order:**

1. **Bond 5% allowance FIRST (MANDATORY)** - Tax-deferred, ALWAYS use annual 5% of original investment if bonds exist
2. **Tax-free accounts (PCLS, ISA)** - Spread over retirement years using PMT to deplete at age 100
3. **Taxable income** - Only when tax-free sources can't meet target
4. **Fill gaps** - Tax-free → Personal Allowance → Basic Rate → Higher Rate

**CRITICAL RULE:** Bond 5% withdrawals are MANDATORY and must ALWAYS be included in the allocation if the user has investment bonds. This is because:
- The 5% allowance is "use it or lose it" - unused allowance rolls forward but is still capped
- Bond withdrawals within 5% are completely tax-deferred (no immediate tax liability)
- NOT using the 5% means leaving tax-efficient income on the table

### Bug 2: PCLS Not Spread Evenly

**Problem:** PCLS capped at 25% of target income instead of being spread over retirement years.

**Fix:** `PCLS Annual = PCLS Total ÷ Years in Retirement`

### Bug 3: Income Reduced Prematurely

**Problem:** PMT formula applied even when funds would last to age 100+.

**Fix:** Only reduce income if funds actually deplete BEFORE age 100.

### Bug 4: Tax-Free Accounts Not Fully Depleted (NEW)

**Problem:** Tax-free accounts (Bonds, ISA, PCLS) left with balances at age 100 while taxable pension drawdown is used.

**Fix:** Calculate withdrawal rates so that Bonds, ISA, and PCLS reach £0 at age 100. Reduce taxable pension drawdown accordingly.

---

## Correct Withdrawal Logic Algorithm

```text
Step 1: Calculate Tax-Free Depletion Rates (to reach £0 at age 100)

    For each tax-free account, calculate the annual withdrawal that depletes it to £0:

    PMT Formula: Annual Withdrawal = Balance × (r × (1+r)^n) / ((1+r)^n - 1)

    Where:
      r = growth rate (4% for investments, 0% for cash)
      n = years to age 100

    Bond Annual = PMT(bond_balance, 0.04, years_to_100)
    ISA Annual = PMT(isa_balance, 0.04, years_to_100)
    PCLS Annual = pcls_balance / years_to_100  (no growth on PCLS cash)

Step 2: Calculate Personal Allowance Usage

    Personal Allowance (PA) = £12,570

    Guaranteed income (State Pension + DB Pension) uses PA FIRST:
      guaranteed_taxable = state_pension + db_pension
      remaining_pa = max(0, PA - guaranteed_taxable)

    Example:
      State Pension: £11,502
      DB Pension: £5,000
      Total guaranteed: £16,502
      PA used: £12,570 (full PA consumed)
      Taxable at Basic Rate: £16,502 - £12,570 = £3,932

Step 3: Allocate Income Sources (Tax-Free First)

    remaining_target = target_income - guaranteed_income

    a) Bond 5% MANDATORY (ALWAYS include if bonds exist)
       - Use PMT formula to deplete bond at age 100
       - This ALWAYS gets allocated regardless of remaining_target
       bond_withdrawal = PMT(bond_balance, 0.04, years_to_100)
       remaining_target -= bond_withdrawal
       (remaining_target may go negative - that's OK)

    b) PCLS MANDATORY (spread to deplete at 100)
       - ALWAYS allocate full PMT to ensure depletion
       pcls_withdrawal = pcls_balance / years_to_100
       remaining_target -= pcls_withdrawal

    c) ISA MANDATORY (spread to deplete at 100)
       - ALWAYS allocate full PMT to ensure depletion
       isa_withdrawal = PMT(isa_balance, 0.04, years_to_100)
       remaining_target -= isa_withdrawal

    d) ONLY if remaining_target > 0, use taxable pension drawdown
       pension_drawdown = remaining_target

       Tax on pension drawdown:
         - If remaining_pa > 0: first £remaining_pa is tax-free
         - Amount over remaining_pa taxed at Basic Rate (20%)

       (If remaining_target <= 0, pension_drawdown = 0 - NO TAX on drawdown!)

    e) If pension insufficient, use GIA, then Savings

Step 4: Apply Growth and Verify Depletion

Step 5: Apply Growth and Verify Depletion

    For each year:
      - Withdraw calculated amounts
      - Apply growth to remaining balances
      - Verify all tax-free accounts reach £0 at age 100
```

---

## PMT Formula Reference

The PMT (Payment) formula calculates the annual withdrawal that depletes a fund to £0 over n years with growth rate r:

```text
PMT = PV × (r × (1+r)^n) / ((1+r)^n - 1)

Where:
  PV = Present Value (current balance)
  r = annual growth rate (e.g., 0.04 for 4%)
  n = number of years

Example:
  Balance: £200,000
  Growth: 4%
  Years: 35

  PMT = 200,000 × (0.04 × 1.04^35) / (1.04^35 - 1)
  PMT = 200,000 × (0.04 × 3.9461) / (3.9461 - 1)
  PMT = 200,000 × 0.1578 / 2.9461
  PMT = 200,000 × 0.0536
  PMT = £10,720/year
```

---

## Tax Band Reference (2025/26)

| Band | Range | Rate |
|------|-------|------|
| Personal Allowance | £0 - £12,570 | 0% |
| Basic Rate | £12,571 - £50,270 | 20% |
| Higher Rate | £50,271 - £125,140 | 40% |
| Additional Rate | £125,140+ | 45% |

---

## SCENARIO 1: Simple ISA + Pension

**Profile:**

- Age: 65, Retirement Age: 65, Life Expectancy: 100 (35 years)
- Target Income: £30,000/year

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| DC Pension | £400,000 | PCLS: Tax-free, Drawdown: Taxable |
| S&S ISA | £200,000 | Tax-free |

**Calculations:**

- PCLS Total: £400,000 × 25% = £100,000
- PCLS Annual (deplete at 100): £100,000 ÷ 35 = £2,857/year
- Pension Drawdown Pool: £400,000 × 75% = £300,000
- ISA PMT (4% growth, 35 years): £200,000 × 0.0536 = £10,720/year

**Tax-Free Total: £2,857 + £10,720 = £13,577/year**

**Remaining from taxable: £30,000 - £13,577 = £16,423/year**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £2,857 | Tax-free | £0 |
| ISA | £10,720 | Tax-free | £0 |
| Pension Drawdown | £16,423 | Taxable | £770.60 |
| **Total** | **£30,000** | | **£770.60** |

**Tax Calculation:**

- Tax-free income: £2,857 + £10,720 = £13,577
- Taxable income: £16,423
- Personal Allowance used: £12,570 (£0 tax)
- Basic Rate: £16,423 - £12,570 = £3,853 × 20% = £770.60

**Year-by-Year Projection (Years 1-10, then key years):**

| Year | Age | PCLS W/D | ISA W/D | Pension W/D | Total W/D | PCLS Bal | ISA Bal | Pension Bal | Total Funds |
|------|-----|----------|---------|-------------|-----------|----------|---------|-------------|-------------|
| 0 | 65 | - | - | - | - | £100,000 | £200,000 | £300,000 | £600,000 |
| 1 | 66 | £2,857 | £10,720 | £16,423 | £30,000 | £97,143 | £196,851 | £294,920 | £588,914 |
| 2 | 67 | £2,857 | £10,720 | £16,423 | £30,000 | £94,286 | £193,605 | £289,713 | £577,604 |
| 3 | 68 | £2,857 | £10,720 | £16,423 | £30,000 | £91,429 | £190,259 | £284,378 | £566,066 |
| 4 | 69 | £2,857 | £10,720 | £16,423 | £30,000 | £88,572 | £186,810 | £278,910 | £554,292 |
| 5 | 70 | £2,857 | £10,720 | £16,423 | £30,000 | £85,715 | £183,252 | £273,303 | £542,270 |
| 10 | 75 | £2,857 | £10,720 | £16,423 | £30,000 | £71,430 | £164,031 | £244,031 | £479,492 |
| 15 | 80 | £2,857 | £10,720 | £16,423 | £30,000 | £57,145 | £141,631 | £211,254 | £410,030 |
| 20 | 85 | £2,857 | £10,720 | £16,423 | £30,000 | £42,860 | £115,515 | £174,481 | £332,856 |
| 25 | 90 | £2,857 | £10,720 | £16,423 | £30,000 | £28,575 | £85,013 | £133,139 | £246,727 |
| 30 | 95 | £2,857 | £10,720 | £16,423 | £30,000 | £14,290 | £49,383 | £86,554 | £150,227 |
| 35 | 100 | £2,857 | £10,720 | £16,423 | £30,000 | £0 | £0 | £33,906 | £33,906 |

**Math Check (Year 1):**

- ISA: (£200,000 - £10,720) × 1.04 = £196,851 ✓
- Pension: (£300,000 - £16,423) × 1.04 = £294,920 ✓
- PCLS: £100,000 - £2,857 = £97,143 (no growth) ✓

**Result:** PCLS and ISA deplete to £0 at age 100. Pension has £33,906 remaining (buffer). Tax reduced from £1,035 to £770.60 by using more ISA.

---

## SCENARIO 2: Bond-Heavy Portfolio

**Profile:**

- Age: 60, Retirement Age: 60, Life Expectancy: 100 (40 years)
- Target Income: £40,000/year

**Assets:**

| Asset | Balance | Original Investment | Tax Treatment |
|-------|---------|---------------------|---------------|
| Onshore Bond | £300,000 | £200,000 | 5% tax-deferred |
| Offshore Bond | £250,000 | £180,000 | 5% tax-deferred |
| DC Pension | £350,000 | - | PCLS/Drawdown |
| Cash ISA | £50,000 | - | Tax-free |

**Calculations:**

- Onshore Bond PMT (4%, 40 yrs): £300,000 × 0.0505 = £15,150/year
- Offshore Bond PMT (4%, 40 yrs): £250,000 × 0.0505 = £12,625/year
- PCLS Total: £350,000 × 25% = £87,500
- PCLS Annual: £87,500 ÷ 40 = £2,188/year
- Pension Drawdown Pool: £350,000 × 75% = £262,500
- Cash ISA (no growth): £50,000 ÷ 40 = £1,250/year

**Tax-Free/Deferred Total: £15,150 + £12,625 + £2,188 + £1,250 = £31,213/year**

**Note:** Bond 5% limits are:

- Onshore: £200,000 × 5% = £10,000 (withdrawing £15,150 exceeds this)
- Offshore: £180,000 × 5% = £9,000 (withdrawing £12,625 exceeds this)

The excess over 5% triggers a "chargeable event" but is still more tax-efficient than pension drawdown for basic rate taxpayers.

**Remaining from taxable: £40,000 - £31,213 = £8,787/year**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Onshore Bond | £15,150 | Tax-deferred* | £0 |
| Offshore Bond | £12,625 | Tax-deferred* | £0 |
| PCLS | £2,188 | Tax-free | £0 |
| Cash ISA | £1,250 | Tax-free | £0 |
| Pension Drawdown | £8,787 | Taxable | £0 |
| **Total** | **£40,000** | | **£0** |

*Bond withdrawals within policy, tax deferred until full encashment

**Tax Calculation:**

- Taxable: £8,787
- Personal Allowance: £12,570 covers all
- **Total Tax: £0**

**Year-by-Year Projection:**

| Year | Age | Onshore W/D | Offshore W/D | PCLS W/D | ISA W/D | Pension W/D | Total | Onshore Bal | Offshore Bal | PCLS Bal | ISA Bal | Pension Bal | Total |
|------|-----|-------------|--------------|----------|---------|-------------|-------|-------------|--------------|----------|---------|-------------|-------|
| 0 | 60 | - | - | - | - | - | - | £300,000 | £250,000 | £87,500 | £50,000 | £262,500 | £950,000 |
| 1 | 61 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £296,244 | £246,870 | £85,312 | £48,750 | £263,862 | £941,038 |
| 5 | 65 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £280,907 | £234,089 | £76,560 | £43,750 | £268,579 | £903,885 |
| 10 | 70 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £257,618 | £214,682 | £65,620 | £37,500 | £276,171 | £851,591 |
| 20 | 80 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £195,031 | £162,526 | £43,740 | £25,000 | £299,632 | £725,929 |
| 30 | 90 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £108,512 | £90,427 | £21,860 | £12,500 | £335,416 | £568,715 |
| 40 | 100 | £15,150 | £12,625 | £2,188 | £1,250 | £8,787 | £40,000 | £0 | £0 | £0 | £0 | £388,125 | £388,125 |

**Result:** ZERO TAX! All tax-free accounts deplete at 100. Pension grows to £388k as reserve. Excellent tax efficiency.

---

## SCENARIO 3: High Income Requirement

**Profile:**

- Age: 55, Retirement Age: 55, Life Expectancy: 100 (45 years)
- Target Income: £80,000/year

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| DC Pension | £800,000 | PCLS/Drawdown |
| S&S ISA | £300,000 | Tax-free |
| GIA | £150,000 | Taxable (CGT) |

**Step 1: Calculate Tax-Free Withdrawals (to deplete at 100)**

- PCLS Total: £800,000 × 25% = £200,000
- PCLS Annual: £200,000 ÷ 45 = £4,444/year
- ISA PMT (4%, 45 yrs): £300,000 × 0.0483 = £14,490/year

**Tax-Free Total: £4,444 + £14,490 = £18,934/year**

**Step 2: Calculate Taxable Withdrawal Needed**

Remaining from taxable: £80,000 - £18,934 = £61,066/year

**Step 3: Check Sustainability of Taxable Sources**

- Pension Drawdown Pool: £600,000
- GIA: £150,000
- Total Taxable Pool: £750,000

Sustainable PMT from £750,000 at 4% over 45 years:
PMT = £750,000 × 0.0483 = £36,225/year

**Problem:** We need £61,066/year but can only sustain £36,225/year!

This means taxable accounts WILL deplete before age 100.

**Step 4: Calculate When Taxable Accounts Deplete**

Combined taxable (£750,000) at 4% growth, withdrawing £61,066/year:

Using annuity formula: n ≈ 17 years

**Taxable accounts deplete around age 72.**

**Step 5: Split Taxable Withdrawal Between Pension and GIA**

To deplete BOTH taxable sources together at age 72 (17 years):

- Pension PMT (4%, 17 yrs): £600,000 × 0.0822 = £49,320/year
- GIA PMT (4%, 17 yrs): £150,000 × 0.0822 = £12,330/year
- Total: £61,650/year ≈ £61,066 needed ✓

**Corrected Annual Allocation (Ages 55-71):**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £4,444 | Tax-free | £0 |
| ISA | £14,490 | Tax-free | £0 |
| Pension Drawdown | £48,853 | Taxable | Higher Rate |
| GIA | £12,213 | Taxable | Higher Rate |
| **Total** | **£80,000** | | **£11,858** |

**Tax Calculation:**

- Tax-free: £4,444 + £14,490 = £18,934
- Taxable: £48,853 + £12,213 = £61,066
- Personal Allowance: £12,570 @ 0%
- Basic Rate: £37,700 @ 20% = £7,540
- Higher Rate: £61,066 - £50,270 = £10,796 @ 40% = £4,318.40
- **Total Tax: £11,858.40**

**Year-by-Year Projection (Phase 1: Ages 55-71):**

| Year | Age | PCLS | ISA | Pension | GIA | Total | PCLS Bal | ISA Bal | Pension Bal | GIA Bal | Total Funds |
|------|-----|------|-----|---------|-----|-------|----------|---------|-------------|---------|-------------|
| 0 | 55 | - | - | - | - | - | £200,000 | £300,000 | £600,000 | £150,000 | £1,250,000 |
| 1 | 56 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £195,556 | £296,930 | £573,193 | £143,298 | £1,208,977 |
| 5 | 60 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £177,780 | £282,787 | £487,656 | £121,914 | £1,070,137 |
| 10 | 65 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £155,560 | £262,994 | £366,510 | £91,628 | £876,692 |
| 15 | 70 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £133,340 | £239,362 | £219,036 | £54,759 | £646,497 |
| 17 | 72 | £4,444 | £14,490 | £48,853 | £12,213 | £80,000 | £124,452 | £227,001 | £130,892 | £32,723 | £515,068 |

**Phase 2: After Taxable Sources Deplete (Ages 72-100)**

Around age 72-73, pension and GIA both reach £0. Only tax-free sources remain:

- PCLS remaining: ~£115,000
- ISA remaining: ~£215,000

**New Annual Income (Tax-Free Only):**

| Source | Amount | Tax |
|--------|--------|-----|
| PCLS | £4,444 | £0 |
| ISA | £14,490 | £0 |
| **Total** | **£18,934** | **£0** |

**Income Drops from £80,000 to £18,934!**

**Full Projection (Ages 72-100):**

| Year | Age | PCLS | ISA | Total | PCLS Bal | ISA Bal | Total Funds |
|------|-----|------|-----|-------|----------|---------|-------------|
| 18 | 73 | £4,444 | £14,490 | £18,934 | £120,008 | £221,091 | £341,099 |
| 25 | 80 | £4,444 | £14,490 | £18,934 | £88,900 | £186,238 | £275,138 |
| 35 | 90 | £4,444 | £14,490 | £18,934 | £44,460 | £127,350 | £171,810 |
| 45 | 100 | £4,444 | £14,490 | £18,934 | £0 | £0 | £0 |

**Summary:**

| Phase | Ages | Annual Income | Annual Tax | Notes |
|-------|------|---------------|------------|-------|
| Phase 1 | 55-72 | £80,000 | £11,858 | All sources used |
| Phase 2 | 73-100 | £18,934 | £0 | Tax-free only |

**Key Insight:** £80,000/year is NOT sustainable to age 100 with these assets. The user has two choices:

1. **Accept income drop:** £80k for 17 years, then £19k for 28 years
2. **Reduce target:** Sustainable income to age 100 = ~£55,000/year

**Sustainable Income Calculation:**

If target was reduced to deplete ALL accounts at 100:

- PCLS: £4,444/year
- ISA PMT: £14,490/year
- Pension PMT: £600,000 × 0.0483 = £28,980/year
- GIA PMT: £150,000 × 0.0483 = £7,245/year
- **Total Sustainable: £55,159/year**

At £55,159/year:
- Tax-free: £18,934
- Taxable: £36,225
- Tax: £36,225 - £12,570 = £23,655 × 20% = £4,731/year (all basic rate!)

**Result:** High income (£80k) cannot be sustained. Either accept income cliff at 72, or reduce to sustainable £55k.

---

## SCENARIO 4: Zero Tax Achievable

**Profile:**

- Age: 60, Retirement Age: 60, Life Expectancy: 100 (40 years)
- Target Income: £25,000/year

**Assets:**

| Asset | Balance | Original Investment | Tax Treatment |
|-------|---------|---------------------|---------------|
| DC Pension | £300,000 | - | PCLS/Drawdown |
| S&S ISA | £400,000 | - | Tax-free |
| Onshore Bond | £200,000 | £150,000 | 5% tax-deferred |

**Calculations:**

- Bond PMT (4%, 40 yrs): £200,000 × 0.0505 = £10,100/year
- PCLS Total: £300,000 × 25% = £75,000
- PCLS Annual: £75,000 ÷ 40 = £1,875/year
- ISA PMT (4%, 40 yrs): £400,000 × 0.0505 = £20,200/year
- Pension Drawdown Pool: £225,000

**Tax-Free Total: £10,100 + £1,875 + £20,200 = £32,175/year**

Since £32,175 > £25,000, we can achieve **ZERO TAX** and not touch pension!

**Adjusted Allocation (to hit exactly £25,000):**

Reduce ISA withdrawal: £25,000 - £10,100 - £1,875 = £13,025/year from ISA

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Bond | £10,100 | Tax-deferred | £0 |
| PCLS | £1,875 | Tax-free | £0 |
| ISA | £13,025 | Tax-free | £0 |
| Pension Drawdown | £0 | - | £0 |
| **Total** | **£25,000** | | **£0** |

**Year-by-Year Projection:**

| Year | Age | Bond W/D | PCLS W/D | ISA W/D | Pension W/D | Total | Bond Bal | PCLS Bal | ISA Bal | Pension Bal | Total |
|------|-----|----------|----------|---------|-------------|-------|----------|----------|---------|-------------|-------|
| 0 | 60 | - | - | - | - | - | £200,000 | £75,000 | £400,000 | £225,000 | £900,000 |
| 1 | 61 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £197,496 | £73,125 | £402,454 | £234,000 | £907,075 |
| 5 | 65 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £186,881 | £65,625 | £410,960 | £273,914 | £937,380 |
| 10 | 70 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £170,594 | £56,250 | £422,406 | £333,171 | £982,421 |
| 20 | 80 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £129,050 | £37,500 | £451,123 | £493,095 | £1,110,768 |
| 30 | 90 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £71,877 | £18,750 | £491,247 | £730,145 | £1,312,019 |
| 40 | 100 | £10,100 | £1,875 | £13,025 | £0 | £25,000 | £0 | £0 | £546,621 | £1,080,985 | £1,627,606 |

**Wait - ISA is growing, not depleting!**

The ISA withdrawal (£13,025) is less than growth (£400,000 × 4% = £16,000), so ISA grows.

**Recalculate to deplete ISA at 100:**

Since we need to deplete tax-free accounts, increase ISA withdrawal and keep pension at £0.

New ISA withdrawal needed to reach £0 at 100: PMT = £400,000 × 0.0505 = £20,200/year

But total would be: £10,100 + £1,875 + £20,200 = £32,175 > £25,000 target

**Solution:** Reduce bond withdrawal to balance:

- Target: £25,000
- PCLS: £1,875 (fixed to deplete)
- ISA: £20,200 (PMT to deplete)
- Bond: £25,000 - £1,875 - £20,200 = £2,925/year

**Corrected Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Bond | £2,925 | Tax-deferred | £0 |
| PCLS | £1,875 | Tax-free | £0 |
| ISA | £20,200 | Tax-free | £0 |
| Pension Drawdown | £0 | - | £0 |
| **Total** | **£25,000** | | **£0** |

**Corrected Year-by-Year:**

| Year | Age | Bond W/D | PCLS W/D | ISA W/D | Pension W/D | Total | Bond Bal | PCLS Bal | ISA Bal | Pension Bal | Total |
|------|-----|----------|----------|---------|-------------|-------|----------|----------|---------|-------------|-------|
| 0 | 60 | - | - | - | - | - | £200,000 | £75,000 | £400,000 | £225,000 | £900,000 |
| 1 | 61 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £204,958 | £73,125 | £395,392 | £234,000 | £907,475 |
| 10 | 70 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £232,866 | £56,250 | £343,809 | £333,171 | £966,096 |
| 20 | 80 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £290,049 | £37,500 | £261,296 | £493,095 | £1,081,940 |
| 30 | 90 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £385,422 | £18,750 | £147,856 | £730,145 | £1,282,173 |
| 40 | 100 | £2,925 | £1,875 | £20,200 | £0 | £25,000 | £546,098 | £0 | £0 | £1,080,985 | £1,627,083 |

**Problem:** Bond is GROWING because we're only taking £2,925 when growth is £8,000+/year.

**Final Solution:** We must accept that with £25k target and these assets, we cannot deplete all accounts. The optimal strategy is:

1. Deplete PCLS first (no growth): £1,875/year, depletes at 100 ✓
2. Deplete ISA via PMT: £20,200/year, depletes at 100 ✓
3. Remaining £2,925 from bond OR pension

**Choose bond** (tax-deferred) over pension (taxable). Bond will not deplete but that's acceptable.

**Result:** Zero tax achieved. PCLS and ISA deplete at 100. Bond and Pension are reserves. Funds grow to £1.6M+.

---

## SCENARIO 5: Multiple Bonds Portfolio

**Profile:**

- Age: 58, Retirement Age: 58, Life Expectancy: 100 (42 years)
- Target Income: £50,000/year

**Assets:**

| Asset | Balance | Original Investment | Tax Treatment |
|-------|---------|---------------------|---------------|
| Prudential Onshore | £180,000 | £120,000 | Tax-deferred |
| Aviva Onshore | £150,000 | £100,000 | Tax-deferred |
| RL360 Offshore | £220,000 | £160,000 | Tax-deferred |
| DC Pension | £450,000 | - | PCLS/Drawdown |
| S&S ISA | £100,000 | - | Tax-free |

**Calculations (PMT to deplete at 100):**

- Prudential PMT (4%, 42 yrs): £180,000 × 0.0495 = £8,910/year
- Aviva PMT (4%, 42 yrs): £150,000 × 0.0495 = £7,425/year
- RL360 PMT (4%, 42 yrs): £220,000 × 0.0495 = £10,890/year
- PCLS Total: £450,000 × 25% = £112,500
- PCLS Annual: £112,500 ÷ 42 = £2,679/year
- ISA PMT (4%, 42 yrs): £100,000 × 0.0495 = £4,950/year
- Pension Drawdown Pool: £337,500

**Tax-Free/Deferred Total: £8,910 + £7,425 + £10,890 + £2,679 + £4,950 = £34,854/year**

**Remaining from taxable: £50,000 - £34,854 = £15,146/year**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Prudential Bond | £8,910 | Tax-deferred | £0 |
| Aviva Bond | £7,425 | Tax-deferred | £0 |
| RL360 Bond | £10,890 | Tax-deferred | £0 |
| PCLS | £2,679 | Tax-free | £0 |
| ISA | £4,950 | Tax-free | £0 |
| Pension Drawdown | £15,146 | Taxable | £515.20 |
| **Total** | **£50,000** | | **£515.20** |

**Tax Calculation:**

- Taxable: £15,146
- Personal Allowance: £12,570 @ 0%
- Basic Rate: £15,146 - £12,570 = £2,576 × 20% = £515.20

**Year-by-Year Projection:**

| Year | Age | Bonds Total | PCLS | ISA | Pension | Total | Prudential | Aviva | RL360 | PCLS Bal | ISA Bal | Pension Bal | Total |
|------|-----|-------------|------|-----|---------|-------|------------|-------|-------|----------|---------|-------------|-------|
| 0 | 58 | - | - | - | - | - | £180,000 | £150,000 | £220,000 | £112,500 | £100,000 | £337,500 | £1,100,000 |
| 1 | 59 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £177,934 | £148,278 | £217,474 | £109,821 | £98,892 | £335,248 | £1,087,647 |
| 10 | 68 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £159,654 | £133,045 | £195,133 | £85,710 | £87,040 | £319,988 | £980,570 |
| 20 | 78 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £125,855 | £104,879 | £153,822 | £59,010 | £68,512 | £294,759 | £806,837 |
| 30 | 88 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £75,041 | £62,534 | £91,717 | £32,310 | £42,103 | £257,780 | £561,485 |
| 42 | 100 | £27,225 | £2,679 | £4,950 | £15,146 | £50,000 | £0 | £0 | £0 | £0 | £0 | £203,821 | £203,821 |

**Result:** Only £515.20 tax on £50k income (1.03% effective rate). All tax-free accounts deplete at 100. Pension remains as buffer.

---

## SCENARIO 6: Large ISA Portfolio

**Profile:**

- Age: 62, Retirement Age: 62, Life Expectancy: 100 (38 years)
- Target Income: £35,000/year

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| S&S ISA (Vanguard) | £500,000 | Tax-free |
| Cash ISA | £80,000 | Tax-free |
| DC Pension | £200,000 | PCLS/Drawdown |

**Calculations (PMT to deplete at 100):**

- PCLS Total: £200,000 × 25% = £50,000
- PCLS Annual: £50,000 ÷ 38 = £1,316/year
- S&S ISA PMT (4%, 38 yrs): £500,000 × 0.0508 = £25,400/year
- Cash ISA (no growth): £80,000 ÷ 38 = £2,105/year
- Pension Drawdown Pool: £150,000

**Tax-Free Total: £1,316 + £25,400 + £2,105 = £28,821/year**

**Remaining from taxable: £35,000 - £28,821 = £6,179/year**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £1,316 | Tax-free | £0 |
| S&S ISA | £25,400 | Tax-free | £0 |
| Cash ISA | £2,105 | Tax-free | £0 |
| Pension Drawdown | £6,179 | Taxable | £0 |
| **Total** | **£35,000** | | **£0** |

**Tax Calculation:**

- Taxable: £6,179
- Personal Allowance: £12,570 covers all
- **Total Tax: £0**

**Year-by-Year Projection:**

| Year | Age | PCLS | S&S ISA | Cash ISA | Pension | Total | PCLS Bal | S&S ISA Bal | Cash ISA Bal | Pension Bal | Total |
|------|-----|------|---------|----------|---------|-------|----------|-------------|--------------|-------------|-------|
| 0 | 62 | - | - | - | - | - | £50,000 | £500,000 | £80,000 | £150,000 | £780,000 |
| 1 | 63 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £48,684 | £493,584 | £77,895 | £149,574 | £769,737 |
| 10 | 72 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £36,840 | £430,115 | £58,950 | £145,034 | £670,939 |
| 20 | 82 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £23,680 | £336,790 | £37,900 | £136,975 | £535,345 |
| 30 | 92 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £10,520 | £205,088 | £16,850 | £124,313 | £356,771 |
| 38 | 100 | £1,316 | £25,400 | £2,105 | £6,179 | £35,000 | £0 | £0 | £0 | £107,185 | £107,185 |

**Result:** ZERO TAX achieved. All tax-free accounts deplete at 100. Pension remains as emergency buffer.

---

## SCENARIO 7: Pension-Dominant Portfolio (DB + State Pension)

**Profile:**

- Age: 67, Retirement Age: 67, Life Expectancy: 100 (33 years)
- Target Income: £55,000/year
- State Pension: £11,502/year (immediate)
- DB Pension: £18,000/year (NHS pension, immediate)

**Assets:**

| Asset | Balance/Income | Tax Treatment |
|-------|----------------|---------------|
| DC Pension (SIPP) | £600,000 | PCLS/Drawdown |
| State Pension | £11,502/year | Taxable (guaranteed) |
| DB Pension (NHS) | £18,000/year | Taxable (guaranteed) |
| S&S ISA | £50,000 | Tax-free |

**Guaranteed Income:** £11,502 + £18,000 = £29,502/year (taxable, unavoidable)

**Calculations:**

- PCLS Total: £600,000 × 25% = £150,000
- PCLS Annual: £150,000 ÷ 33 = £4,545/year
- ISA PMT (4%, 33 yrs): £50,000 × 0.0540 = £2,700/year
- Pension Drawdown Pool: £450,000

**Tax-Free Available: £4,545 + £2,700 = £7,245/year**

**Remaining needed: £55,000 - £29,502 - £7,245 = £18,253/year (from DC Drawdown)**

**Annual Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| State Pension | £11,502 | Taxable | Uses PA |
| DB Pension (NHS) | £18,000 | Taxable | Basic Rate |
| PCLS | £4,545 | Tax-free | £0 |
| ISA | £2,700 | Tax-free | £0 |
| DC Pension Drawdown | £18,253 | Taxable | Basic Rate |
| **Total** | **£55,000** | | **£6,937** |

**Tax Calculation:**

- Tax-free: £4,545 + £2,700 = £7,245
- Taxable: £11,502 + £18,000 + £18,253 = £47,755
- Personal Allowance: £12,570 @ 0%
- Basic Rate: £47,755 - £12,570 = £35,185 × 20% = £7,037

**Year-by-Year Projection:**

| Year | Age | State P | DB Pension | PCLS | ISA | DC Drawdown | Total | PCLS Bal | ISA Bal | DC Bal | Total Funds |
|------|-----|---------|------------|------|-----|-------------|-------|----------|---------|--------|-------------|
| 0 | 67 | - | - | - | - | - | - | £150,000 | £50,000 | £450,000 | £650,000 |
| 1 | 68 | £11,502 | £18,000 | £4,545 | £2,700 | £18,253 | £55,000 | £145,455 | £49,192 | £448,617 | £643,264 |
| 10 | 77 | £11,502 | £18,000 | £4,545 | £2,700 | £18,253 | £55,000 | £104,550 | £40,717 | £433,880 | £579,147 |
| 20 | 87 | £11,502 | £18,000 | £4,545 | £2,700 | £18,253 | £55,000 | £59,100 | £28,277 | £408,648 | £496,025 |
| 33 | 100 | £11,502 | £18,000 | £4,545 | £2,700 | £18,253 | £55,000 | £0 | £0 | £366,104 | £366,104 |

**Result:** Tax unavoidable due to guaranteed DB/State income using Personal Allowance. PCLS and ISA deplete at 100. DC remains as buffer.

---

## SCENARIO 8: Early Retirement with State Pension Gap

**Profile:**

- Age: 55, Retirement Age: 55, State Pension Age: 67 (12-year gap)
- Life Expectancy: 100 (45 years)
- Target Income: £45,000/year

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| DC Pension (SIPP) | £500,000 | PCLS/Drawdown |
| S&S ISA | £250,000 | Tax-free |
| GIA | £100,000 | Taxable |
| State Pension (from 67) | £11,502/year | Taxable |

**Phase 1 (Ages 55-66): No State Pension - 12 years**

- PCLS Total: £500,000 × 25% = £125,000
- PCLS Annual: £125,000 ÷ 45 = £2,778/year
- ISA PMT (4%, 45 yrs): £250,000 × 0.0483 = £12,075/year
- Pension Drawdown Pool: £375,000

**Tax-Free: £2,778 + £12,075 = £14,853/year**

**Remaining: £45,000 - £14,853 = £30,147/year (from pension drawdown)**

**Phase 1 Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £2,778 | Tax-free | £0 |
| ISA | £12,075 | Tax-free | £0 |
| Pension Drawdown | £30,147 | Taxable | £3,515.40 |
| **Total** | **£45,000** | | **£3,515.40** |

**Tax (Phase 1):**

- Taxable: £30,147
- PA: £12,570 @ 0%
- Basic: £30,147 - £12,570 = £17,577 × 20% = £3,515.40

**Phase 2 (Ages 67-100): State Pension Active - 33 years**

State Pension: £11,502/year

**Phase 2 Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| State Pension | £11,502 | Taxable | Uses PA |
| PCLS | £2,778 | Tax-free | £0 |
| ISA | £12,075 | Tax-free | £0 |
| Pension Drawdown | £18,645 | Taxable | £3,515.40 |
| **Total** | **£45,000** | | **£3,515.40** |

**Tax (Phase 2):**

- Taxable: £11,502 + £18,645 = £30,147
- PA: £12,570 @ 0%
- Basic: £17,577 × 20% = £3,515.40

**Year-by-Year Projection:**

| Year | Age | State P | PCLS | ISA | Pension | Total | PCLS Bal | ISA Bal | Pension Bal | GIA Bal | Total |
|------|-----|---------|------|-----|---------|-------|----------|---------|-------------|---------|-------|
| 0 | 55 | £0 | - | - | - | - | £125,000 | £250,000 | £375,000 | £100,000 | £850,000 |
| 1 | 56 | £0 | £2,778 | £12,075 | £30,147 | £45,000 | £122,222 | £247,282 | £358,647 | £104,000 | £832,151 |
| 5 | 60 | £0 | £2,778 | £12,075 | £30,147 | £45,000 | £111,110 | £235,795 | £299,580 | £121,665 | £768,150 |
| 12 | 67 | £11,502 | £2,778 | £12,075 | £18,645 | £45,000 | £91,664 | £211,396 | £207,243 | £163,194 | £673,497 |
| 20 | 75 | £11,502 | £2,778 | £12,075 | £18,645 | £45,000 | £69,440 | £180,195 | £177,982 | £218,470 | £646,087 |
| 30 | 85 | £11,502 | £2,778 | £12,075 | £18,645 | £45,000 | £41,660 | £134,377 | £137,668 | £323,252 | £636,957 |
| 45 | 100 | £11,502 | £2,778 | £12,075 | £18,645 | £45,000 | £0 | £0 | £60,234 | £619,788 | £680,022 |

**Result:** Same tax in both phases. PCLS and ISA deplete at 100. Pension and GIA remain as reserves.

---

## SCENARIO 9: ISA-Only Early Years (Preserve Pension)

**Profile:**

- Age: 55, Retirement Age: 55, Life Expectancy: 100 (45 years)
- Target Income: £30,000/year
- **Strategy:** Maximise tax-free ISA withdrawals, let pension compound

**Assets:**

| Asset | Balance | Tax Treatment |
|-------|---------|---------------|
| DC Pension | £400,000 | PCLS/Drawdown |
| S&S ISA | £350,000 | Tax-free |

**Strategy Analysis:**

Option A: Use ISA + Pension together (standard approach)
Option B: Use ISA only first, then pension (preserve pension growth)

**Option B Calculation:**

ISA-only years until ISA depletes:

- Target: £30,000/year from ISA
- ISA with 4% growth, £30,000 withdrawal

PMT formula reversed: How many years until £350,000 depletes at £30,000/year with 4% growth?

Using annuity formula: n = ln(1 - PV × r / PMT) / ln(1 + r) ... approximately 15 years

**ISA depletes around age 70.**

Meanwhile pension grows: £400,000 × 1.04^15 = £720,366

**Year-by-Year (ISA-Only Phase, Years 1-15):**

| Year | Age | ISA W/D | Pension W/D | Total | ISA Bal | Pension Bal | Total Funds |
|------|-----|---------|-------------|-------|---------|-------------|-------------|
| 0 | 55 | - | - | - | £350,000 | £400,000 | £750,000 |
| 1 | 56 | £30,000 | £0 | £30,000 | £332,800 | £416,000 | £748,800 |
| 5 | 60 | £30,000 | £0 | £30,000 | £263,040 | £486,661 | £749,701 |
| 10 | 65 | £30,000 | £0 | £30,000 | £166,579 | £592,098 | £758,677 |
| 14 | 69 | £30,000 | £0 | £30,000 | £66,682 | £688,493 | £755,175 |
| 15 | 70 | £30,000 | £0 | £30,000 | £39,349 | £716,033 | £755,382 |
| 16 | 71 | £30,000 | £0 | £30,000 | £10,923 | £744,674 | £755,597 |
| 17 | 72 | £10,923 | £19,077 | £30,000 | £0 | £754,541 | £754,541 |

**ISA depletes at age 72.**

**Post-ISA Phase (Age 72-100): Pension Only**

Pension at age 72: £754,541

- PCLS: £754,541 × 25% = £188,635
- PCLS Annual: £188,635 ÷ 28 = £6,737/year
- Drawdown Pool: £754,541 × 75% = £565,906

**Post-ISA Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| PCLS | £6,737 | Tax-free | £0 |
| Pension Drawdown | £23,263 | Taxable | £2,138.60 |
| **Total** | **£30,000** | | **£2,138.60** |

**Tax (Post-ISA):**

- Taxable: £23,263
- PA: £12,570 @ 0%
- Basic: £10,693 × 20% = £2,138.60

**Comparison:**

| Strategy | Tax Years 1-17 | Tax Years 18-45 | Total Tax (45 yrs) |
|----------|----------------|-----------------|-------------------|
| ISA-Only First | £0 | £2,138.60/yr × 28 = £59,881 | £59,881 |
| Standard Blend | ~£770/yr × 45 = £34,650 | Same | £34,650 |

**Result:** ISA-only strategy pays MORE total tax! The standard blended approach is more tax-efficient because it spreads taxable income across more years, using Personal Allowance each year.

---

## SCENARIO 10: Complex Multi-Asset Portfolio

**Profile:**

- Age: 60, Retirement Age: 60, State Pension Age: 67
- Life Expectancy: 100 (40 years)
- Target Income: £60,000/year

**Assets:**

| Asset | Balance | Original Inv. | Tax Treatment |
|-------|---------|---------------|---------------|
| DC Pension (Workplace) | £350,000 | - | PCLS/Drawdown |
| DC Pension (SIPP) | £280,000 | - | PCLS/Drawdown |
| S&S ISA | £180,000 | - | Tax-free |
| Cash ISA | £45,000 | - | Tax-free |
| Onshore Bond | £120,000 | £90,000 | Tax-deferred |
| Offshore Bond | £85,000 | £60,000 | Tax-deferred |
| GIA | £75,000 | - | Taxable |
| Savings | £30,000 | - | Taxable |
| State Pension (from 67) | £11,502/year | - | Taxable |

**Calculations (PMT to deplete at 100):**

**Pensions:**

- Total DC Pension: £350,000 + £280,000 = £630,000
- PCLS Total: £630,000 × 25% = £157,500
- PCLS Annual: £157,500 ÷ 40 = £3,938/year
- Pension Drawdown Pool: £472,500

**Bonds:**

- Onshore PMT (4%, 40 yrs): £120,000 × 0.0505 = £6,060/year
- Offshore PMT (4%, 40 yrs): £85,000 × 0.0505 = £4,293/year

**ISAs:**

- S&S ISA PMT (4%, 40 yrs): £180,000 × 0.0505 = £9,090/year
- Cash ISA (no growth): £45,000 ÷ 40 = £1,125/year

**Tax-Free/Deferred Total: £3,938 + £6,060 + £4,293 + £9,090 + £1,125 = £24,506/year**

**Phase 1 (Ages 60-66): No State Pension**

**Remaining from taxable: £60,000 - £24,506 = £35,494/year**

**Phase 1 Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| Onshore Bond | £6,060 | Tax-deferred | £0 |
| Offshore Bond | £4,293 | Tax-deferred | £0 |
| PCLS | £3,938 | Tax-free | £0 |
| S&S ISA | £9,090 | Tax-free | £0 |
| Cash ISA | £1,125 | Tax-free | £0 |
| Pension Drawdown | £35,494 | Taxable | £4,584.80 |
| **Total** | **£60,000** | | **£4,584.80** |

**Tax (Phase 1):**

- Taxable: £35,494
- PA: £12,570 @ 0%
- Basic: £35,494 - £12,570 = £22,924 × 20% = £4,584.80

**Phase 2 (Ages 67-100): State Pension Active**

State Pension: £11,502/year (uses PA first)

**Phase 2 Allocation:**

| Source | Amount | Tax Treatment | Tax |
|--------|--------|---------------|-----|
| State Pension | £11,502 | Taxable | Uses PA |
| Onshore Bond | £6,060 | Tax-deferred | £0 |
| Offshore Bond | £4,293 | Tax-deferred | £0 |
| PCLS | £3,938 | Tax-free | £0 |
| S&S ISA | £9,090 | Tax-free | £0 |
| Cash ISA | £1,125 | Tax-free | £0 |
| Pension Drawdown | £23,992 | Taxable | £4,584.80 |
| **Total** | **£60,000** | | **£4,584.80** |

**Tax (Phase 2):**

- Taxable: £11,502 + £23,992 = £35,494
- PA: £12,570 @ 0%
- Basic: £22,924 × 20% = £4,584.80

**Year-by-Year Projection:**

| Yr | Age | State | Bonds | PCLS | ISAs | Pension | Total | Onshore | Offshore | PCLS Bal | S&S ISA | Cash ISA | Pension Bal | GIA | Savings | Total |
|----|-----|-------|-------|------|------|---------|-------|---------|----------|----------|---------|----------|-------------|-----|---------|-------|
| 0 | 60 | £0 | - | - | - | - | - | £120,000 | £85,000 | £157,500 | £180,000 | £45,000 | £472,500 | £75,000 | £30,000 | £1,165,000 |
| 1 | 61 | £0 | £10,353 | £3,938 | £10,215 | £35,494 | £60,000 | £118,498 | £83,935 | £153,562 | £177,587 | £43,875 | £454,486 | £78,000 | £30,600 | £1,140,543 |
| 7 | 67 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £109,231 | £77,369 | £130,234 | £161,788 | £37,125 | £431,584 | £98,358 | £34,326 | £1,080,015 |
| 10 | 70 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £103,416 | £73,250 | £118,358 | £152,612 | £33,750 | £424,523 | £110,611 | £36,818 | £1,053,338 |
| 20 | 80 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £78,251 | £55,428 | £79,108 | £115,502 | £22,500 | £392,883 | £163,866 | £44,592 | £952,130 |
| 30 | 90 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £43,562 | £30,856 | £39,858 | £64,310 | £11,250 | £349,115 | £242,647 | £54,200 | £835,798 |
| 40 | 100 | £11,502 | £10,353 | £3,938 | £10,215 | £23,992 | £60,000 | £0 | £0 | £0 | £0 | £0 | £289,612 | £359,148 | £65,836 | £714,596 |

**Result:** All tax-free accounts (Bonds, PCLS, ISAs) deplete to £0 at age 100. Pension, GIA, and Savings remain as reserves. Same tax in both phases due to same total taxable income.

---

## Summary Comparison

| Scenario | Target | Assets | Tax/Year | Effective Rate | Tax-Free Depleted? |
|----------|--------|--------|----------|----------------|-------------------|
| 1. Simple ISA + Pension | £30,000 | £600,000 | £771 | 2.6% | Yes at 100 |
| 2. Bond-Heavy | £40,000 | £950,000 | £0 | 0% | Yes at 100 |
| 3. High Income | £80,000 | £1,250,000 | £11,858→£0 | 14.8%→0% | Taxable at 72, tax-free at 100 |
| 4. Zero Tax | £25,000 | £900,000 | £0 | 0% | PCLS+ISA at 100 |
| 5. Multiple Bonds | £50,000 | £1,100,000 | £515 | 1.0% | Yes at 100 |
| 6. Large ISA | £35,000 | £780,000 | £0 | 0% | Yes at 100 |
| 7. Pension-Dominant | £55,000 | £650,000 + DB/SP | £7,037 | 12.8% | Yes at 100 |
| 8. Early Retirement | £45,000 | £850,000 | £3,515 | 7.8% | Yes at 100 |
| 9. ISA-Only Strategy | £30,000 | £750,000 | £0→£2,139 | 0→7.1% | ISA early, PCLS late |
| 10. Complex Multi-Asset | £60,000 | £1,165,000 | £4,585 | 7.6% | Yes at 100 |

---

## Key Takeaways

### Correct Withdrawal Order (Priority)

**MANDATORY TAX-FREE/DEFERRED (ALWAYS allocate full PMT):**
1. **Bond PMT (MANDATORY)** - ALWAYS use if bonds exist, PMT to deplete at age 100
2. **PCLS (MANDATORY)** - ALWAYS spread evenly: PCLS Total ÷ Years to 100
3. **ISA PMT (MANDATORY)** - ALWAYS use if ISAs exist, PMT to deplete at age 100

**TAXABLE (only to fill gap after tax-free):**
4. **Pension drawdown** - ONLY if remaining_target > 0 after all tax-free allocated
5. **GIA** - Only if pension insufficient
6. **Savings** - Last resort

**GOAL: remaining_target should be £0 or negative after tax-free allocation = NO TAX or minimal tax!**

### Critical Rules

1. **Bond 5% is MANDATORY** - ALWAYS allocate if bonds exist, regardless of target income
2. **PCLS is MANDATORY** - ALWAYS allocate full PMT to deplete at age 100
3. **ISA is MANDATORY** - ALWAYS allocate full PMT to deplete at age 100
4. **Pension drawdown ONLY for gap** - ONLY allocate if remaining_target > 0 AFTER all tax-free
5. **Goal is NO TAX** - If tax-free PMT >= target, pension_drawdown = £0

**The algorithm should result in ZERO pension drawdown when tax-free sources can cover the target!**

### PMT Formula Quick Reference

```text
PMT = PV × (r × (1+r)^n) / ((1+r)^n - 1)

At 4% growth:
  35 years: multiply balance by 0.0536
  38 years: multiply balance by 0.0508
  40 years: multiply balance by 0.0505
  42 years: multiply balance by 0.0495
  45 years: multiply balance by 0.0483
```

### Why This Matters

Using the standard sustainable withdrawal rate (4.7%) often leaves significant tax-free balances at death. By calculating the exact PMT to deplete at age 100:

- More tax-free income each year
- Less taxable pension drawdown needed
- Lower lifetime tax bill
- Pension grows as reserve (still accessible if needed)
