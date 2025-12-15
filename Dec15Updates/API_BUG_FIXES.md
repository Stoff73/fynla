# API Bug Fixes - December 15, 2025

## Overview

This update fixes multiple bugs related to editing functionality in the Net Worth module, including investment accounts, savings accounts, and pensions. Key fixes address preview mode edit persistence and various form/modal issues.

## Changes Made

### 1. Investment Edit Modal Fix
**File:** `resources/js/components/NetWorth/InvestmentDetailInline.vue`

- Added missing `:show="showEditModal"` prop to AccountForm component
- Fixed `handleUpdate` method to use correct parameter name (`accountData` instead of `data`)

### 2. Joint Investment Value Display Fix
**Files:**
- `resources/js/components/NetWorth/InvestmentDetailInline.vue`
- `resources/js/components/NetWorth/InvestmentList.vue`
- `resources/js/views/Investment/AccountSummaryPanel.vue`
- `resources/js/views/Investment/AccountDetailView.vue`
- `resources/js/components/Investment/PortfolioOverview.vue`
- `resources/js/components/Onboarding/steps/AssetsStep.vue`

**Issue:** Joint investment accounts were incorrectly doubling the `current_value` field.

**Root Cause:** Code assumed `current_value` stored only the user's 50% share and multiplied by 2 for the full value.

**Fix:** Investments use **Single-Record Architecture** where `current_value` IS the full account value. Removed all `* 2` multiplications. User's share is calculated as `current_value * (ownership_percentage / 100)`.

### 3. Savings Edit Modal Empty Fields Fix
**File:** `resources/js/views/Savings/SavingsAccountDetailInline.vue`

- Added missing `:is-editing="true"` prop to SaveAccountModal component

### 4. Savings Account Types Fix
**File:** `resources/js/components/Savings/SaveAccountModal.vue`

- Added missing product types: `cash_isa`, `junior_isa`, `premium_bonds`, `nsi`, `instant_access`
- Organised product types into grouped optgroups (Bank Accounts, ISAs, NS&I Products)
- Added watchers to auto-set ISA/country fields based on account type selection

### 5. Savings Edit Not Saving Fix
**File:** `resources/js/views/Savings/SavingsAccountDetailInline.vue`

- Added `updateAccount` to Vuex mapActions
- Fixed `handleAccountSaved` method to call the store's `updateAccount` action

### 6. Pension Edit Form Fix
**File:** `resources/js/components/Retirement/UnifiedPensionForm.vue`

- Added missing `initialPensionType` prop definition
- Fixed `mainPensionType` data property to use the prop value in edit mode

### 7. Preview Mode Edit Persistence Fix
**Files:**
- `resources/js/components/NetWorth/InvestmentDetailInline.vue`
- `resources/js/components/NetWorth/InvestmentList.vue`
- `resources/js/views/Savings/SavingsAccountDetailInline.vue`
- `resources/js/components/NetWorth/PensionDetailInline.vue`
- `resources/js/components/NetWorth/PensionList.vue`

**Issue:** In preview mode, edits appeared to not save. The UI would revert to original data after editing.

**Root Cause:** After a successful API call, components were reloading data from the database. In preview mode, `PreviewWriteInterceptor` returns a fake success response without actually saving to the database, so the reload would fetch the original (unchanged) data.

**Fix:** After updates in preview mode:
1. Skip the API reload (`fetchInvestmentData`, `loadAccount`, `fetchRetirementData`)
2. Update local component state with the submitted form data
3. Emit events to parent components so they can update their local state

**Key Pattern:**
```javascript
const isPreview = this.$store.getters['preview/isPreviewMode'];
if (isPreview) {
  // Update local state or emit to parent
  this.$emit('account-updated', { ...this.account, ...data });
} else {
  // Normal mode: reload from API
  await this.fetchData();
}
```

**Note:** Changes in preview mode persist only for the session. Refreshing the page will revert to original data (by design).

## Architecture Notes

### Single-Record vs Reciprocal Records

| Asset Type | Pattern | Description |
|------------|---------|-------------|
| Investments | Single-Record | ONE record stores FULL value in `current_value` |
| Properties | Reciprocal Records | TWO records - one per owner with their share |
| Savings | Reciprocal Records | TWO records - one per owner with their share |

### Preview Mode Data Flow

1. User submits edit form
2. API call made via Vuex action
3. Backend `PreviewWriteInterceptor` intercepts, returns fake success
4. Component checks `isPreviewMode`
5. If preview: update local state, skip API reload
6. If normal: reload from API to get fresh data

## Testing

1. Login as preview user (any persona)
2. Navigate to Net Worth > Investments/Cash/Retirement
3. Click on an account/pension to view details
4. Click Edit, change a value
5. Submit - value should persist in the UI
6. Navigate away and back - value should still show (session state)
7. Refresh page - value should revert to original (correct behaviour)
