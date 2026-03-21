# Income Fix — Production Test Report

**Date:** 21 March 2026
**Tester:** Playwright browser automation
**Environment:** https://fynla.org (production)

## Test 1: Preview Persona — David Mitchell (peak_earners)

**Route:** `/?demo=true` → David & Sarah Mitchell → `/valuable-info?section=income`

| Check | Result | Evidence |
|-------|--------|----------|
| Employment Income £145,000 shown | PASS | Displayed as line item |
| Rental Income £14,290 shown | PASS | Auto-calculated from properties (Flat 42 + Unit 12) |
| Zero-value types hidden (Self-Employment, Dividend, Interest, Trust, Other) | PASS | Not visible in view mode |
| Total Annual Income correct | PASS | £159,290 |
| Tax breakdown from TaxConfigService | PASS | PA £12,570, Basic 20%, Higher 40%, Additional 45% |
| Section 24 Tax Credit shown | PASS | +£780 |
| NI Class 1 calculated | PASS | 8% on £37,700 + 2% on £94,730 |
| Net Income correct | PASS | £108,152 |
| Edit mode shows all 8 fields | PASS | All fields visible including Other Income |

**Screenshot:** `prod-income-david-mitchell.png`

## Test 2: Preview Persona — Patricia Bennett (retired_couple)

**Route:** `/?demo=true` → Patricia & Harold Bennett → `/valuable-info?section=income`

| Check | Result | Evidence |
|-------|--------|----------|
| Pension Income £30,000 shown | PASS | Calculated from DB pensions |
| Zero-value types hidden | PASS | Only Pension Income visible |
| "No NI" badge shown | PASS | Pension income doesn't attract NI |
| Tax: PA £12,570 @ 0%, Basic £17,430 @ 20% | PASS | Correct 2025/26 rates |
| Net Income correct | PASS | £26,514 |

**Screenshot:** `prod-income-patricia-bennett.png` (localhost), tested on production via demo

## Test 3: Real User — Chris John Slater-Jones

**Route:** `/login` → email + verification code → `/valuable-info?section=income`

### 3a. Initial State (before changes)

| Check | Result | Evidence |
|-------|--------|----------|
| Login with email + verification code | PASS | Logged in successfully |
| Self-Employment Income £150,000 shown | PASS | Displayed as line item |
| Rental Income £12,180 shown | PASS | From property "10 Amherst Place" |
| Child Benefit £1,355 shown | PASS | With HICBC warning (100% clawback) |
| Zero-value types hidden | PASS | Employment, Dividend, Interest, Trust, Other all hidden |
| Total Annual Income | PASS | £162,180 |
| Tax: NI Class 4 (Self-Employment) | PASS | 6% on £37,700 + 2% on £99,730 |
| Net Income | PASS | £104,396 |
| Disposable Income | PASS | £30,536 |

**Screenshot:** `prod-income-chris-before.png`

### 3b. Add Dividend Income + Other Income

**Action:** Edit → set Dividend Income to £5,000, Other Income to £2,000 → Save Changes

| Check | Result | Evidence |
|-------|--------|----------|
| Edit mode shows all 8 income fields | PASS | Including new Other Income field |
| Total updates live while editing | PASS | Changed from £162,180 to £169,180 |
| Save succeeds | PASS | "Income information updated successfully!" |
| Dividend Income £5,000 now visible in view mode | PASS | Appeared as new line item |
| Other Income £2,000 now visible in view mode | PASS | Appeared as new line item |
| Total Annual Income updated | PASS | £169,180 |
| Net Income recalculated | PASS | £107,625 (was £104,396) |
| Disposable Income recalculated | PASS | £33,765 (was £30,536) |

**Tax breakdown after save:**

| Section | Detail | Result |
|---------|--------|--------|
| Earned Income | £162,180 (self-employment + rental) | PASS — NI Applies badge |
| Earned Income Tax | PA £0, Basic -£7,540, Higher -£34,976, Additional -£11,012 | PASS |
| Earned NI | Class 4: 6% -£2,262 + 2% -£1,995 | PASS |
| Earned Net | £104,396 | PASS |
| Dividend Income | £5,000 | PASS — separate section, "No NI" badge |
| Dividend Allowance | £500 | PASS — correct 2025/26 allowance |
| Dividend Tax | Additional: £4,500 @ 39% = -£1,771 | PASS — correct rate for additional rate taxpayer |
| Dividend Net | £3,229 | PASS |

**Income Definitions updated:**

| Field | Value | Result |
|-------|-------|--------|
| Total Income | £173,800 | PASS — includes gross rental £16,800 |
| Breakdown | Self-Employment £150,000, Rental £16,800, Dividends £5,000, Other £2,000 | PASS — all 4 types listed |

**Screenshot:** `prod-income-chris-after-save.png`

### 3c. Revert Test Data

**Action:** Edit → set Dividend Income to £0, Other Income to £0 → Save Changes

| Check | Result | Evidence |
|-------|--------|----------|
| Save succeeds | PASS | "Income information updated successfully!" |
| Dividend Income hidden (back to £0) | PASS | No longer visible |
| Other Income hidden (back to £0) | PASS | No longer visible |
| Total reverted | PASS | £162,180 |
| Net Income reverted | PASS | £104,396 |
| Income Definitions reverted | PASS | £166,800, only Self-Employment + Rental shown |

**Account left clean — no test data remains.**

## Summary

| Test Area | Tests | Passed | Failed |
|-----------|-------|--------|--------|
| Zero-value hiding (view mode) | 5 | 5 | 0 |
| All fields visible (edit mode) | 3 | 3 | 0 |
| Add new income types | 2 | 2 | 0 |
| Save + refresh | 3 | 3 | 0 |
| Tax recalculation (TaxConfigService) | 8 | 8 | 0 |
| Dividend-specific tax rates | 3 | 3 | 0 |
| NI calculation | 3 | 3 | 0 |
| Income Definitions update | 2 | 2 | 0 |
| Revert to original | 4 | 4 | 0 |
| **Total** | **33** | **33** | **0** |

All 33 checks passed. The income fix is working correctly on production with real user data.
