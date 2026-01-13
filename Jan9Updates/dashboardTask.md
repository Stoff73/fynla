# Dynamic Dashboard Redesign Task

**Date:** January 9, 2026
**Branch:** dynamicDashboard
**Status:** Planning Complete - Ready for Implementation

---

## Objective

Redesign the Fynla dashboard to:
1. Show only information relevant to the user (conditional card display)
2. Display useful daily financial planning information
3. Replace pastel colors with solid colors for clear indicators
4. Add a "Financial Planning" navbar dropdown for quick data entry

---

## Requirements Summary

### 1. Conditional Card Display
Cards should only appear when the user has relevant data entered:

| Card | Show Condition |
|------|----------------|
| Net Worth | Always shown |
| Estate Planning | Only if `ihtLiability > 0` |
| Protection | Only if user has any policies entered |
| Trusts | Only if user has trusts entered |

### 2. New Dashboard Cards

| Card | Purpose | Data Source |
|------|---------|-------------|
| **Affordability** | Income vs expenditure, surplus/deficit | User profile + spouse if linked |
| **Retirement** | Projected income at user's chosen age, capital value | Retirement module |
| **Investments** | Portfolio YTD, risk levels, diversification | Investment module |
| **Tax Optimisation** | Allowances used/unused/expiring | Multiple modules |

**Tax Allowances to Track:**
- ISA Annual Allowance (£20,000)
- Pension Annual Allowance (£60,000)
- CGT Allowance (£3,000)
- Dividend Allowance (£500)

### 3. Color Changes
Replace all pastel backgrounds with solid alternatives:
- Pastel backgrounds → White background + solid colored borders
- Status badges → Solid colored backgrounds
- Icon backgrounds → Solid colors (e.g., `bg-blue-600` instead of `bg-blue-100`)

### 4. Navbar "Add Data" Dropdown
Add dropdown menu between Feedback and Complete Setup buttons with quick-add options:
- Protection: Life Insurance, Critical Illness, Income Protection
- Pensions: DC Pension, DB Pension
- Assets: Property, Investment Account, Savings Account
- Estate: Trust

---

## Implementation Plan

### Step 1: Conditional Display Logic
**File:** `resources/js/views/Dashboard.vue`

Add computed properties:
```javascript
shouldShowEstateCard() {
  return this.estateData.ihtLiability > 0;
},
hasAnyProtectionPolicies() {
  return (
    this.protectionData.lifePolicies.length > 0 ||
    this.protectionData.criticalIllnessPolicies.length > 0 ||
    this.protectionData.incomeProtectionPolicies.length > 0 ||
    this.protectionData.disabilityPolicies.length > 0
  );
},
shouldShowTrustsCard() {
  return this.$store.state.trusts.trusts?.length > 0;
}
```

### Step 2: Create New Card Components

**Files to create:**
- `resources/js/components/Dashboard/AffordabilityOverviewCard.vue`
- `resources/js/components/Dashboard/RetirementOverviewCard.vue`
- `resources/js/components/Dashboard/InvestmentsOverviewCard.vue`
- `resources/js/components/Dashboard/TaxOptimisationCard.vue`

### Step 3: Navbar Dropdown
**File:** `resources/js/components/Navbar.vue`

Add "Add Data" dropdown with categorized quick-add options.

### Step 4: Update badges.css
**File:** `resources/css/badges.css`

Replace pastel backgrounds with solid colors:
```css
/* Status Badges - solid backgrounds */
.badge-active { @apply bg-green-600 text-white border-0; }
.badge-pending { @apply bg-amber-500 text-white border-0; }
.badge-expired { @apply bg-red-600 text-white border-0; }

/* Account Type Badges - solid borders */
.badge-isa { @apply bg-white text-blue-700 border-2 border-blue-500 font-medium; }
```

### Step 5: Update Component Colors
Replace pastel patterns across dashboard cards:
- `bg-blue-50` → `bg-white border-2 border-blue-500`
- `bg-green-50` → `bg-white border-2 border-green-600`
- `bg-red-50` → `bg-white border-2 border-red-600`
- `bg-amber-50` → `bg-white border-2 border-amber-500`

---

## Files to Modify

| File | Changes |
|------|---------|
| `resources/js/views/Dashboard.vue` | Conditionals, new card imports, expanded data loading |
| `resources/js/components/Navbar.vue` | Add "Add Data" dropdown |
| `resources/css/badges.css` | Replace pastel with solid colors |
| `resources/js/components/Dashboard/NetWorthOverviewCard.vue` | Update colors |
| `resources/js/components/Protection/ProtectionOverviewCard.vue` | Update colors |
| `resources/js/components/Estate/EstateOverviewCard.vue` | Update colors |
| `resources/js/components/Trusts/TrustsOverviewCard.vue` | Update colors |

## New Files to Create

| File | Purpose |
|------|---------|
| `resources/js/components/Dashboard/AffordabilityOverviewCard.vue` | Income/expenditure card |
| `resources/js/components/Dashboard/RetirementOverviewCard.vue` | Retirement projections card |
| `resources/js/components/Dashboard/InvestmentsOverviewCard.vue` | Portfolio overview card |
| `resources/js/components/Dashboard/TaxOptimisationCard.vue` | Tax allowance tracker |

---

## Verification Checklist

### Conditional Display
- [ ] User with no policies → Protection card hidden
- [ ] User with no IHT liability → Estate card hidden
- [ ] User with no trusts → Trusts card hidden
- [ ] User with data → Appropriate cards shown

### New Cards
- [ ] Affordability shows correct income/expenditure
- [ ] Retirement shows projections at user's chosen age
- [ ] Investments shows portfolio YTD, risk, diversification
- [ ] Tax Optimisation shows allowance progress bars

### Navbar
- [ ] "Add Data" dropdown appears on click
- [ ] Categories displayed correctly
- [ ] Links navigate to correct routes with query params

### Visual
- [ ] No pastel backgrounds (no -50 or -100 color variants)
- [ ] Clear borders and solid color accents
- [ ] Good contrast and readability

### Testing
- [ ] `./vendor/bin/pest` passes
- [ ] No Vue console errors
- [ ] Mobile responsive

---

## Notes

- This is a fetch and display task - no new calculations required
- Data already exists in Vuex stores from existing modules
- Dashboard updates dynamically as user enters/removes data
- User's retirement age preference used for retirement projections
- Spouse data included if accounts are linked

---

## Full Plan Reference

See `/Users/Chris/.claude/plans/enchanted-percolating-dragon.md` for complete implementation details.
