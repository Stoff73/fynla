# Fix: Lump Sum Contributions Inflating Monthly Expenditure Totals

**Date:** 17 February 2026

## Problem

Investment lump sum contributions (one-off payments like David Mitchell's £5,000 ISA contribution) are being converted to a monthly equivalent and added to `monthly_amount` in the financial commitments API. This inflates the monthly expenditure total (showing £5,000/month instead of £0/month) and the annual total (£60,000 instead of £5,000).

## Files Changed

1. `app/Services/UserProfile/UserProfileService.php`
2. `resources/js/components/UserProfile/ExpenditureForm.vue`

## What Changed

### Backend: `UserProfileService.php`

- Removed lump sum from `$totalMonthly` — only regular contributions
- Changed inclusion guard to include lump-sum-only accounts
- Added `lump_sum_amount` field per investment item (raw one-off amount)
- Added `investments_lump_sum` and `annual_lump_sum` to totals

### Frontend: `ExpenditureForm.vue`

- Added `commitmentsLumpSumTotal` computed for annual-only lump sums
- Annual totals now = `(monthly * 12) + lumpSumTotal`
- Lump sum investments display as "one-off" not "/month"
- Same pattern applied for spouse and household variants

## Testing

| Persona | Expected |
|---|---|
| David Mitchell (peak_earners) | £5,000 ISA lump sum NOT in monthly total, appears once in annual |
| John Morgan (young_saver) | No investments, unaffected |
