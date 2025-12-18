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

## 4. Landing Page Persona Selection Modal

### Problem
When clicking "Try the Demo" or "Interactive Demo" buttons on the landing page, the modal only showed one persona (young_family) with no option to choose from all 4 available personas.

### Solution
Created a new persona selection modal that displays all 4 personas in a 2x2 grid, allowing users to choose which scenario they want to explore.

**Files Created:**
- `resources/js/components/Preview/PersonaSelectionModal.vue`

**Files Modified:**
- `resources/js/views/Public/LandingPage.vue`
- `CLAUDE.md` (added "Keep It Simple" as Critical Rule #1)

### New Component (`PersonaSelectionModal.vue`)
A modal displaying all 4 personas in a responsive 2x2 grid:

| Persona | Name | Net Worth | Focus |
|---------|------|-----------|-------|
| young_family | Emily & James Carter | £80k-£120k | Protection gaps |
| peak_earners | David & Sarah Mitchell | £1.5m-£2m | Tax efficiency |
| widow | Margaret Thompson | £1.4m-£1.6m | IHT planning |
| entrepreneur | Alex Chen | £800k-£1m | Business protection |

**Features:**
- Colour-coded gradient headers (blue/green/purple/orange)
- Emoji avatars for each persona
- Net worth and focus badges
- Loading spinner on selected card
- Escape key and backdrop click to close

### LandingPage Changes
- Replaced `PersonaIntroModal` with `PersonaSelectionModal`
- Added `mapGetters` for `availablePersonas` from preview store
- Simplified `handlePersonaSelect()` method - loads selected persona and navigates to dashboard

### Flow
1. User clicks "Try the Demo" button
2. PersonaSelectionModal opens showing all 4 personas
3. User clicks a persona card
4. Loading spinner appears, persona loads via API
5. User navigates to dashboard with that persona

### Existing Behaviour Unchanged
- PersonaSelector dropdown in dashboard still works as before
- PersonaIntroModal still appears when switching personas inside the app

### Bug Fix: Persona Authentication Race Condition
Fixed an issue where some personas would redirect to login instead of dashboard after selection.

**Root Cause:** Vue reactivity timing issue where the router navigation occurred before the Vuex store state had fully propagated.

**Solution:**
1. Clear existing auth state before making the preview login API call
2. Set token before user in the store commits
3. Add `nextTick()` after dispatch to ensure Vue reactivity has propagated before navigation

**Files Modified:**
- `resources/js/store/modules/preview.js` - Clear auth state before API call, reorder commits
- `resources/js/views/Public/LandingPage.vue` - Add nextTick before router.push

---

## Files Changed Summary

| File | Change Type | Description |
|------|-------------|-------------|
| `app/Models/User.php` | Modified | Added `name` to `$appends` array |
| `app/Services/NetWorth/NetWorthService.php` | Modified | Added business/chattels to asset summary |
| `resources/js/store/modules/netWorth.js` | Modified | Added business/chattels state |
| `resources/js/components/NetWorth/NetWorthOverview.vue` | Modified | Added 2 cards, changed grid to 2x3 |
| `resources/js/components/UserProfile/LetterToSpouse.vue` | Rewritten | Card-based auto-populated financial data |
| `resources/js/components/Preview/PersonaSelectionModal.vue` | Created | New 2x2 grid persona selector |
| `resources/js/views/Public/LandingPage.vue` | Modified | Use new PersonaSelectionModal, fix auth timing |
| `resources/js/store/modules/preview.js` | Modified | Fix auth race condition in enterPreviewMode |
| `CLAUDE.md` | Modified | Added "Keep It Simple" as Critical Rule #1 |

---

## Testing

1. **Net Worth Overview**: Navigate to Net Worth page and verify Business Interest and Chattels cards appear in 2x3 grid layout
2. **User Menu**: Login as preview persona and verify name displays in top-right menu
3. **Letter to Spouse**: Navigate to User Profile > Letter to Spouse tab and verify:
   - Part 2 shows auto-populated financial data as read-only cards
   - Totals display correctly for each category
   - Badges show for ISA accounts, ownership types
   - Parts 1, 3, 4 remain editable
4. **Landing Page Persona Selection**: Go to landing page, click "Try the Demo" and verify:
   - Modal shows all 4 personas in a 2x2 grid
   - Each card has correct colours, emoji, and info
   - Clicking a card loads that persona and navigates to dashboard
