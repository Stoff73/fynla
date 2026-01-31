# Plan: Unified Retirement Income Planner

## Summary

Merge `RequiredCapitalDetail.vue` functionality into `RetirementIncomeTab.vue` to create a single unified Retirement Income Planner. Fix the cash account bug where all savings accounts are included by default.

## Current State

| Component | Location | Purpose |
|-----------|----------|---------|
| `RetirementIncomeTab.vue` | `resources/js/components/Retirement/` | Income planner with drawdown strategy |
| `RequiredCapitalDetail.vue` | `resources/js/components/Retirement/` | Required capital with asset toggles |

**Navigation paths to remove:**
- `PensionList.vue` line 196: `required-capital` tab click
- `FutureValueTab.vue` line 46: Required Capital card click

## Unified View Structure

```
┌─────────────────────────────────────────────────────────────────┐
│ HEADER: "Retirement Income Planner"                             │
│ Subtitle: "Model your tax-optimised drawdown from age XX"       │
├─────────────────────────────────────────────────────────────────┤
│ INFO BANNERS (State Pension / Income Adjusted notices)          │
├─────────────────────────────────────────────────────────────────┤
│ SUMMARY CARDS (5 cards - no duplicates)                         │
│ [Target Income] [Net Income] [Pension Capital] [Other Assets]   │
│ [Gap/Surplus]                                                   │
├─────────────────────────────────────────────────────────────────┤
│ INCOME SOURCES                                                  │
│ - Pensions only (DC pot, DB pensions, State Pension)            │
│ - IncomeSourceSlider for each                                   │
├─────────────────────────────────────────────────────────────────┤
│ OTHER ASSETS (collapsible section)                              │
│ - Investment accounts with include/exclude toggles              │
│ - Savings accounts with include/exclude toggles                 │
│ - Toggle adds account to Income Sources above                   │
├─────────────────────────────────────────────────────────────────┤
│ PROGRESS + ASSUMPTIONS ROW                                      │
│ [Current Progress] [Forecasted Progress] │ [Assumptions Panel]  │
├─────────────────────────────────────────────────────────────────┤
│ FUND DEPLETION CHART + TABLE (FundDepletionChart component)     │
│ - Area chart showing fund depletion over time                   │
│ - TABLE 1: Withdrawal, Fund balances by type (PCLS, Drawdown,   │
│   ISA, Bond, GIA, Savings), Growth, Taxable Drawdown, Tax Paid  │
├─────────────────────────────────────────────────────────────────┤
│ YEAR-BY-YEAR PROJECTION TABLE (from RequiredCapital)            │
│ - TABLE 2: Year, Age, Projected Pot Value, Pot in Today's       │
│   Money, Target in Today's Money                                │
│ - Retirement year highlighted in teal                           │
└─────────────────────────────────────────────────────────────────┘
```

## Implementation Steps

### Phase 1: Database Migration

**New file:** `database/migrations/2026_01_31_add_include_in_retirement_to_savings_accounts.php`

```php
Schema::table('savings_accounts', function (Blueprint $table) {
    $table->boolean('include_in_retirement')->default(false)->after('beneficiary_dob');
});
```

**Update:** `app/Models/SavingsAccount.php`
- Add `include_in_retirement` to `$fillable`
- Add to `$casts` as boolean

### Phase 2: Backend Fix

**File:** `app/Services/Retirement/RetirementIncomeService.php`

Fix `getAvailableAccounts()` to filter savings accounts:

```php
// Lines ~341-365 (Cash ISAs) - ADD FILTER:
$isaAccounts = SavingsAccount::whereIn('user_id', $userIds)
    ->where('include_in_retirement', true)  // ADD THIS
    ->where('is_isa', true)
    ->get();

// Lines ~517-546 (Non-ISA Savings) - ADD FILTER:
$savingsAccounts = SavingsAccount::whereIn('user_id', $userIds)
    ->where('include_in_retirement', true)  // ADD THIS
    ->where(function ($query) {
        $query->where('is_isa', false)->orWhereNull('is_isa');
    })
    ->get();
```

**New endpoint:** Add toggle endpoint for savings accounts
- `PATCH /api/savings/accounts/{id}/toggle-retirement`

### Phase 3: Frontend - Extend RetirementIncomeTab.vue

**Add to RetirementIncomeTab.vue:**

1. **Import RequiredCapital data** - fetch in `loadData()`
2. **Additional summary cards:**
   - Pension Capital (80% confidence)
   - Other Assets at Retirement
   - Gap/Surplus (colour-coded)
3. **Other Assets section** with include/exclude toggles
4. **Progress bars** (Current and Forecasted)
5. **Assumptions panel** with link to settings
6. **Year-by-Year Projection table**

### Phase 4: Remove RequiredCapitalDetail Navigation

**File:** `resources/js/components/NetWorth/PensionList.vue`
- Remove `required-capital` tab from tab navigation
- Remove `RequiredCapitalDetail` import and component registration
- Remove the "Required Capital" clickable card (line 196)

**File:** `resources/js/components/Retirement/FutureValueTab.vue`
- Remove `showRequiredCapitalDetail` state
- Change "Required Capital" card to navigate to Income tab OR display inline summary
- Remove `RequiredCapitalDetail` import

### Phase 5: Vuex Store Updates

**File:** `resources/js/store/modules/retirement.js`

Add action for toggling savings account:
```javascript
async toggleIncludedCash({ commit, dispatch }, id) {
    await savingsService.toggleRetirementInclusion(id);
    await dispatch('fetchRetirementIncome');
}
```

## Files to Modify

| File | Changes |
|------|---------|
| `database/migrations/2026_01_31_*.php` | NEW - add include_in_retirement to savings_accounts |
| `app/Models/SavingsAccount.php` | Add field to $fillable and $casts |
| `app/Services/Retirement/RetirementIncomeService.php` | Filter savings accounts by include_in_retirement |
| `app/Http/Controllers/Api/SavingsController.php` | Add toggle-retirement endpoint |
| `resources/js/components/Retirement/RetirementIncomeTab.vue` | Extend with RequiredCapital features |
| `resources/js/components/NetWorth/PensionList.vue` | Remove required-capital tab |
| `resources/js/components/Retirement/FutureValueTab.vue` | Remove RequiredCapitalDetail navigation |
| `resources/js/store/modules/retirement.js` | Add toggleIncludedCash action |
| `resources/js/services/savingsService.js` | Add toggleRetirementInclusion method |

## Default Behaviour

- **Pensions:** Always included in Income Sources (DC pot, DB, State)
- **Investment accounts:** `include_in_retirement = false` by default (unchanged)
- **Savings accounts:** `include_in_retirement = false` by default (NEW - fixes bug)
- User must explicitly toggle other assets to include them

## Summary Cards Deduplication

| Card | Keep From | Notes |
|------|-----------|-------|
| Target Income | RetirementIncomeTab | Editable, blue |
| Projected Net Income | RetirementIncomeTab | After tax, colour-coded |
| Annual Tax | RetirementIncomeTab | With tax-free amount |
| Pension Capital | RequiredCapitalDetail | 80% confidence, teal |
| Other Assets | RequiredCapitalDetail | Projected total, indigo |
| Gap/Surplus | RequiredCapitalDetail | Colour-coded red/green |

## Two Tables Preserved

### Table 1: Fund Depletion Table (from RetirementIncomeTab)
Shows year-by-year fund balances during retirement drawdown:
- Age
- Withdrawal amount
- Fund balances by type (PCLS, Drawdown, ISA, Bond, GIA, Savings)
- Growth
- Taxable Drawdown
- Tax Paid
- Total Balance

### Table 2: Year-by-Year Projection Table (from RequiredCapitalDetail)
Shows accumulation phase projections to retirement:
- Year (calendar year)
- Age
- Projected Pot Value (future value)
- Pot in Today's Money (present value)
- Target in Today's Money
- Retirement year highlighted in teal

## Verification

1. Run migration: `php artisan migrate`
2. Start dev servers: `./dev.sh`
3. Navigate to Retirement > Income tab
4. Verify:
   - Only pensions show in Income Sources by default
   - Other Assets section shows investments and cash with toggles
   - Toggling an asset adds it to Income Sources
   - Summary cards show all metrics without duplicates
   - Both tables display (Fund Depletion + Year-by-Year Projection)
   - Progress bars and assumptions panel visible
5. Test personas: `peak_earners` (has multiple investments)
