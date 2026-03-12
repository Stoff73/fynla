# Mobile Module Detail Screens — Data Fix + Expandable Accounts

**Date:** 12 March 2026
**Branch:** `mobileImprovement`
**Commits:** `713a3ae`, `9caa774`

## Problem

All mobile module detail screens (Savings, Investment, Retirement, Estate, Protection) showed blank/zero data despite the dashboard showing correct totals. Additionally, account cards were not tappable — users couldn't view details.

## Root Cause

Detail views were only dispatching basic data-fetch actions but not the analysis/calculation actions that populate derived metrics. Field name mismatches also caused some values to display as zero.

## Fixes

### 1. Savings — ISA Used Total (`SavingsDetail.vue`)
- `isaUsed` computed only showed `cash_isa_used` (0) but user had stocks & shares ISA contributions
- Fixed to sum both: `cash_isa_used + stocks_shares_isa_used`

### 2. Account Balance Field (`MobileAccountCard.vue`)
- Savings accounts use `current_balance` but card only checked `balance`, `current_value`, `value`
- Added `current_balance` as first fallback in the chain

### 3. Retirement — Missing Analysis Dispatch (`RetirementDetail.vue`)
- Only dispatched `fetchRetirementData` (gets raw pensions)
- Added `analyseRetirement` dispatch (calculates projected income, target, gap)
- Added `fetchAnnualAllowance` dispatch in parallel

### 4. Investment — Missing Analysis Dispatch (`InvestmentDetail.vue`)
- Only dispatched `fetchAccounts`
- Added `analyseInvestment` dispatch (calculates fees, allocation, unrealised gains)

### 5. Estate — Missing IHT + Wrong Assets (`EstateDetail.vue`)
- Only dispatched `fetchEstateData`, not IHT calculation
- Added `calculateIHTPlanning` dispatch (populates ihtLiability, taxableEstate, grossEstate)
- Changed from raw `assets` state (manual only) to `allAssets` getter (manual + investment accounts)

### 6. Protection — Object/Array Mismatch + Policy Type (`ProtectionDetail.vue`)
- `coverageGaps` getter returns an object `{ life_cover: {...} }` but template used `.length` (array method)
- Added computed that converts object to array with `Object.entries().filter().map()`
- Fixed `policy._type` → `policy.policy_type`

### 7. Expandable Account Cards (`MobileAccountCard.vue`)
- Made cards tappable with expand/collapse chevron
- Savings: shows interest rate, access type, emergency fund, ISA status, maturity date, ownership
- Investment: shows risk level, annual fee, ownership, and holdings list with values

## Files Changed

| File | Change |
|------|--------|
| `resources/js/mobile/components/MobileAccountCard.vue` | Balance field fix + expandable details |
| `resources/js/mobile/views/SavingsDetail.vue` | ISA used total fix |
| `resources/js/mobile/views/RetirementDetail.vue` | Added analysis + allowance dispatches |
| `resources/js/mobile/views/InvestmentDetail.vue` | Added analysis dispatch |
| `resources/js/mobile/views/EstateDetail.vue` | Added IHT dispatch + allAssets getter |
| `resources/js/mobile/views/ProtectionDetail.vue` | Coverage gaps object→array + policy_type fix |

## Testing

1. Build: `./deploy/mobile/build-ios.sh`
2. Xcode: Clean Build (Cmd+Shift+K) → Run (Cmd+R)
3. Login → Navigate to each module detail screen
4. Verify: Savings shows account balances, ISA used/remaining correct
5. Verify: Retirement shows projected income, target, gap, allowance
6. Verify: Investment shows fees, allocation, holdings, unrealised gains
7. Verify: Estate shows all assets (manual + investment), IHT liability
8. Verify: Protection shows coverage gaps, policy types correct
9. Verify: Tap any account card → expands to show details
