# December 18, 2025 Updates

## Overview
This update includes improvements to the Net Worth Overview page and a complete redesign of the Letter to Spouse component to match the site's card-based styling.

---

## 1. Net Worth Overview - Business Interest & Chattels Cards

### Changes Made
Added two new asset category cards to the Net Worth Overview page:

**Files Modified:**
- `app/Services/NetWorth/NetWorthService.php`
- `resources/js/store/modules/netWorth.js`
- `resources/js/components/NetWorth/NetWorthOverview.vue`

### Backend Changes (`NetWorthService.php`)
Added business interests and chattels to the `getAssetsSummaryWithDetails()` method:
- Returns items with id, name, type, value, and ownership info
- Calculates totals for each category

### Vuex Store Changes (`netWorth.js`)
Added `business` and `chattels` to the `assetsSummaryDetailed` state with items arrays.

### Frontend Changes (`NetWorthOverview.vue`)
- Added Business Interest card (purple theme: `bg-purple-50`, `text-purple-600`)
- Added Chattels card (pink theme: `bg-pink-50`, `text-pink-600`)
- Changed grid layout from 3 rows x 2 columns to **2 rows x 3 columns**
- Updated responsive breakpoints for mobile/tablet

---

## 2. User Menu Name Display Fix

### Problem
The user menu in the top right was not showing the logged-in user's name in preview persona mode.

### Solution
**File Modified:** `app/Models/User.php`

Added `'name'` to the `$appends` array so the `getNameAttribute` accessor is included in JSON serialization:

```php
protected $appends = ['name'];
```

---

## 3. Letter to Spouse - Complete Redesign

### Problem
The Letter to Spouse component was using flat form layouts with editable textareas for auto-populated financial data, which was inconsistent with the rest of the site's card-based styling.

### Solution
Complete rewrite of the component to match Protection and Net Worth module styling.

**File Modified:** `resources/js/components/UserProfile/LetterToSpouse.vue`

### Key Changes

#### Part 2: Financial Overview (Auto-populated)
Now displays real profile data fetched from APIs as **read-only card grids**:

| Section | API Endpoint | Display |
|---------|--------------|---------|
| Bank Accounts & Savings | `/api/savings` | Card grid with ISA badges |
| Investments | `/api/investment` | Card grid with ISA/SIPP badges |
| Properties | `/api/properties` | Card grid with ownership badges |
| Life Insurance & Protection | `/api/protection` | Card grid showing sum assured |
| Liabilities & Debts | `/api/estate` | Card grid with balances in red |

#### Styling Pattern
- White card containers: `bg-white rounded-lg shadow border border-gray-200`
- Data cards: `bg-gray-50 rounded-lg p-4 border border-gray-200`
- Grid layouts: `grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3`
- Totals displayed for each section with currency formatting
- Coloured badges for account types and ownership

#### Data Extraction
```javascript
// Savings accounts from nested structure
this.profileData.savings = savingsRes.data.data?.accounts || [];

// Investments
this.profileData.investments = investmentsRes.data.data?.accounts || [];

// Properties (direct array)
this.profileData.properties = propertiesRes.data.data || [];

// Protection policies (nested in policies object)
const policies = protection.policies || protection;
this.profileData.policies = [
  ...policies.life_insurance?.map(p => ({ ...p, policy_type: 'life' })),
  ...policies.critical_illness?.map(p => ({ ...p, policy_type: 'critical_illness' })),
  ...policies.income_protection?.map(p => ({ ...p, policy_type: 'income_protection' })),
];

// Liabilities from estate endpoint
this.profileData.liabilities = estate.liabilities || [];
```

#### Parts 1, 3, 4 (Manual Entry)
Remain as editable forms within white card sections:
- Part 1: What to Do Immediately (key contacts in card grid)
- Part 3: Additional Information (password manager, documents, vehicles, etc.)
- Part 4: Funeral and Final Wishes

---

## Files Changed Summary

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Models/User.php` | Modified | Added `name` to `$appends` array |
| `app/Services/NetWorth/NetWorthService.php` | Modified | Added business/chattels to asset summary |
| `resources/js/store/modules/netWorth.js` | Modified | Added business/chattels state |
| `resources/js/components/NetWorth/NetWorthOverview.vue` | Modified | Added 2 cards, changed grid to 2x3 |
| `resources/js/components/UserProfile/LetterToSpouse.vue` | Rewritten | Card-based auto-populated financial data |

---

## Testing

1. **Net Worth Overview**: Navigate to Net Worth page and verify Business Interest and Chattels cards appear in 2x3 grid layout
2. **User Menu**: Login as preview persona and verify name displays in top-right menu
3. **Letter to Spouse**: Navigate to User Profile > Letter to Spouse tab and verify:
   - Part 2 shows auto-populated financial data as read-only cards
   - Totals display correctly for each category
   - Badges show for ISA accounts, ownership types
   - Parts 1, 3, 4 remain editable
