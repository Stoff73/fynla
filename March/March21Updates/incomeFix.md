# Income Fix — 21 March 2026

## Problem

The Income page (`/valuable-info?section=income`, component `IncomeOccupation.vue`) had two issues:

### 1. "Other Income" Completely Missing

`IncomeOccupation.vue` — the **only** income view in use — had no support for Other Income at all:
- No display line item in view mode
- No input field in edit mode
- Not in the form data object
- Not included in `totalIncomeValue` computed
- Not sent to the backend on save

The User model has `annual_other_income` and the backend `ResolvesIncome` trait includes it, but the frontend component simply never wired it up.

### 2. Backend `UserProfileService` Also Missing Other Income

`UserProfileService::buildIncomeOccupation()` — the API that feeds the Income page — was also missing `annual_other_income`:
- Not included in the response array
- Not included in `$totalAnnualIncome` calculation
- Not passed to `UKTaxCalculator::calculateNetIncome()` for tax calculation

This meant Other Income was invisible across the entire income pipeline: not returned by the API, not displayed on the page, not included in tax calculations.

### 3. Orphaned `IncomeStatementTab.vue` with Hardcoded Tax

A separate component `IncomeStatementTab.vue` existed but was **never imported into any view** — completely orphaned. It had its own issues:
- Only showed 5 of 8 income types (missing Interest, Pension, Trust)
- Contained a 42-line hardcoded JavaScript tax calculator (violated CLAUDE.md Rule #3 — all tax values must come from `TaxConfigService`)
- Treated all income as earned income (wrong rates for dividends/savings/trust)

## Fix

### `IncomeOccupation.vue` — Added Other Income (the actual income view)

1. **View mode**: Added "Other Income" line item (only shown when > 0, matching Rental/Pension pattern)
2. **Edit mode**: Added Other Income input field with `£` prefix and helper text "Any other taxable income not listed above"
3. **Form data**: Added `annual_other_income: 0` to the form ref
4. **Total calculation**: Added `(form.value.annual_other_income || 0)` to `totalIncomeValue` computed
5. **Form initialisation**: Added `annual_other_income` to `initializeForm()` from backend data
6. **Save/submit**: Added `annual_other_income` to the `updateData` object in `handleSubmit()`

### `UserProfileService.php` — Added Other Income to API response and calculations

1. **Added `$otherIncome` variable**: `(float) ($user->annual_other_income ?? 0)`
2. **Added to `$totalAnnualIncome`**: Now includes `+ $otherIncome`
3. **Added to tax calculation**: `$trustIncome + $pensionIncome + $otherIncome` passed to `calculateNetIncome()`
4. **Added to API response**: `'annual_other_income' => $user->annual_other_income`

### `PersonalAccountsService.php` — Added missing income types + backend tax

This service feeds the orphaned `IncomeStatementTab.vue` but was also incomplete:

1. **Added 3 missing income types** to both `calculateProfitAndLoss()` and `calculateCashflow()`:
   - Interest Income (`$user->annual_interest_income`)
   - Pension Income (calculated from DB pensions in payment + state pension if receiving)
   - Trust Income (`$user->annual_trust_income`)

2. **Added `calculateAnnualPensionIncome()` private method** — same logic as `UserProfileService`: sums `accrued_annual_pension` from DB pensions and `state_pension_forecast_annual` from state pension (if `already_receiving`).

3. **Injected `UKTaxCalculator`** via constructor and added `tax` object to the P&L response:
   ```json
   {
     "tax": {
       "income_tax": 15460.00,
       "national_insurance": 3200.00,
       "total_deductions": 18660.00,
       "effective_tax_rate": 18.25
     }
   }
   ```

4. **Eager-loaded `dbPensions` and `statePension`** relationships in both P&L and cashflow methods.

### `IncomeStatementTab.vue` — Replaced hardcoded tax with backend data

Replaced the 42-line hardcoded tax calculator with:
```javascript
const taxData = computed(() => profitAndLossData.value?.tax || null);
const estimatedIncomeTax = computed(() => taxData.value?.income_tax || 0);
```

### `PersonalAccountsServiceTest.php` — Updated tests

- Updated income count assertion from 5 to 8
- Added assertions for Interest Income, Pension Income, Trust Income line items
- Added assertions for `tax` response structure
- Changed service instantiation to `app(PersonalAccountsService::class)` (constructor now requires `UKTaxCalculator`)
- Added explicit `TaxConfiguration::factory()->create(['is_active' => true])` in `beforeEach`
- All 46 tests passing (197 assertions)

## Income Types — Complete List (8)

| # | Line Item | Source | View Mode | Edit Mode |
|---|-----------|--------|-----------|-----------|
| 1 | Employment Income | `$user->annual_employment_income` | Only when > 0 | Always shown |
| 2 | Self-Employment Income | `$user->annual_self_employment_income` | Only when > 0 | Always shown |
| 3 | Rental Income | `$user->annual_rental_income` | Only when > 0 | Read-only (auto-calculated from properties) |
| 4 | Dividend Income | `$user->annual_dividend_income` | Only when > 0 | Always shown |
| 5 | Interest Income | `$user->annual_interest_income` | Only when > 0 | Always shown |
| 6 | Pension Income | DB pensions + state pension | Only when > 0 | Read-only (auto-calculated) |
| 7 | Trust Income | `$user->annual_trust_income` | Only when > 0 | Always shown |
| 8 | Other Income | `$user->annual_other_income` | Only when > 0 | Always shown |

All income line items use `v-if="form.value > 0"` in view mode to keep the page clean — only income types the user actually has are displayed. All editable fields remain visible in edit mode so users can enter any income type.

## Browser Testing

Tested with Playwright on two personas:

**David Mitchell (peak_earners):**
- Employment Income: £145,000, Rental Income: £14,290 — shown correctly
- Tax breakdown: PA £12,570, Basic 20%, Higher 40%, Additional 45%, Section 24 credit, NI Class 1
- Edit mode: Other Income field present with helper text
- Total: £159,290, Net: £108,152

**Patricia Bennett (retired_couple):**
- Pension Income: £30,000 — shown correctly (calculated from DB pensions)
- "No NI" badge — correct (pension income doesn't attract NI)
- Tax: PA £12,570, Basic £17,430 @ 20% = -£3,486
- Total: £30,000, Net: £26,514

Screenshots: `March/March21Updates/income-david-mitchell.png`, `income-edit-mode.png`, `income-patricia-bennett.png`

## Files Changed

| File | Change |
|------|--------|
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Added Other Income (view, edit, form data, total, init, submit); all view-mode lines hidden when zero |
| `app/Services/UserProfile/UserProfileService.php` | Added `annual_other_income` to API response, total income, and tax calculation |
| `app/Services/UserProfile/PersonalAccountsService.php` | Added 3 income types, injected UKTaxCalculator, added tax to P&L response |
| `resources/js/components/UserProfile/IncomeStatementTab.vue` | Replaced hardcoded tax calc with backend data |
| `tests/Unit/Services/PersonalAccountsServiceTest.php` | Updated assertions for 8 income types + tax structure |
