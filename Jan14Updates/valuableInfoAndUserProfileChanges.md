# Valuable Info Page & User Profile Changes - January 14, 2025

## Summary

Created a new "Valuable Info" page accessible from the user dropdown menu, and streamlined the User Profile page by moving and removing tabs.

---

## New Page: Valuable Info

### Location
- Route: `/valuable-info`
- Menu: User dropdown (top-right) → "Valuable Info"
- File: `resources/js/views/ValuableInfo.vue`

### Tabs
1. **Letter to Spouse / Expression of Wishes** - Emergency instructions for loved ones
2. **Will** - Will planning and configuration
3. **Financial Statements** - Personal accounts and statements

### Dynamic Tab Label
The first tab changes based on marital status:
- **Married/Civil Partnership/Cohabiting**: "Letter to Spouse"
- **Single/Divorced/Widowed**: "Expression of Wishes"

The content adapts accordingly, with appropriate messaging for users without a spouse.

---

## User Profile Changes

### Tabs Removed
The following tabs were moved to Valuable Info or removed:

| Tab | Action |
|-----|--------|
| Letter to Spouse | Moved to Valuable Info |
| Will | Moved to Valuable Info |
| Financial Statements | Moved to Valuable Info |
| Assets | Removed |
| Liabilities | Removed |

### Remaining Tabs (6 total)
1. Personal Info
2. Domicile Status
3. Health
4. Family
5. Income & Occupation
6. Expenditure

---

## Files Modified

### New Files
| File | Description |
|------|-------------|
| `resources/js/views/ValuableInfo.vue` | New Valuable Info page with 3 tabs |

### Modified Files
| File | Changes |
|------|---------|
| `resources/js/components/Navbar.vue` | Added "Valuable Info" menu option to user dropdown (desktop + mobile) |
| `resources/js/router/index.js` | Added `/valuable-info` route |
| `resources/js/views/UserProfile.vue` | Removed 5 tabs (Letter to Spouse, Will, Financial Statements, Assets, Liabilities) |
| `resources/js/components/UserProfile/LetterToSpouse.vue` | Added dynamic title/content for "Expression of Wishes" mode |
| `resources/js/components/UserProfile/PersonalAccounts.vue` | Auto-calculate on load, date change triggers recalculation, prevent future dates |

---

## Navbar Menu Changes

### User Dropdown (Desktop)
```
User Name ▼
├── Valuable Info  ← NEW (top position)
├── User Profile
├── Settings
└── Logout
```

### Mobile Menu
Same structure as desktop, with "Valuable Info" at the top of the menu.

---

## Route Configuration

```javascript
{
  path: '/valuable-info',
  name: 'ValuableInfo',
  component: ValuableInfo,
  meta: {
    requiresAuth: true,
    breadcrumb: [
      { label: 'Home', path: '/dashboard' },
      { label: 'Valuable Info', path: '/valuable-info' },
    ],
  },
}
```

---

## Expression of Wishes Feature

### Overview
For users without a spouse (single, divorced, widowed), the "Letter to Spouse" tab transforms into "Expression of Wishes" with adapted content.

### Changes by Marital Status

| Marital Status | Tab Label | Page Title | Content Focus |
|----------------|-----------|------------|---------------|
| Married | Letter to Spouse | Letter to Spouse | Spouse-focused messaging |
| Civil Partnership | Letter to Spouse | Letter to Spouse | Spouse-focused messaging |
| Cohabiting | Letter to Spouse | Letter to Spouse | Spouse-focused messaging |
| Single | Expression of Wishes | Expression of Wishes | "Loved ones" messaging |
| Divorced | Expression of Wishes | Expression of Wishes | "Loved ones" messaging |
| Widowed | Expression of Wishes | Expression of Wishes | "Loved ones" messaging |

### UI Differences
- **Letter to Spouse mode**: Shows spouse toggle buttons ("My Letter" / "{SpouseName}'s Letter")
- **Expression of Wishes mode**: No spouse toggle, single view only

### Implementation
- `ValuableInfo.vue`: Dynamic tab label based on `user.marital_status`
- `LetterToSpouse.vue`: Computed properties for title, description, and info banner text
