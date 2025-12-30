# Information Guide Feature - Implementation Plan

## Overview

A floating help button that opens a panel showing users:
1. **What data is needed** for the current module (educational)
2. **What they've already provided** (completed items with checkmarks)
3. **What's still missing** (personalised action items)

This serves two purposes:
- **For regular users:** Guides them on what to add to unlock features
- **For demo/persona viewers:** Explains WHY certain information is collected and how it powers the views

Non-intrusive, uses plain language (no acronyms), and can be toggled on/off.

---

## Requirements Summary

| Requirement | Solution |
|-------------|----------|
| Scope | All financial modules (Protection, Savings, Investment, Retirement, Estate) |
| Access | Floating help button in bottom-right corner |
| Content | **Educational + Personalised** - shows ALL data requirements AND highlights what this user is missing |
| Language | Plain English, no acronyms |
| Toggle | Persists in database for registered users, ignored for preview users |
| Non-intrusive | Small button, slide-out panel (doesn't overlay main content) |

---

## Implementation Phases

### Phase 1: Backend

#### 1.1 Database Migration
**File:** `database/migrations/xxxx_add_info_guide_enabled_to_users.php`

Add `info_guide_enabled` boolean column to users table (default: true).

#### 1.2 ModuleDataRequirementsService
**File:** `app/Services/UserProfile/ModuleDataRequirementsService.php`

Service defining what data each module needs with plain-language explanations:

```php
private const MODULE_REQUIREMENTS = [
    'protection' => [
        'fields' => [
            'date_of_birth' => [
                'label' => 'Your date of birth',
                'why' => 'Used to calculate life expectancy and insurance term lengths',
                'link' => '/profile#personal',
            ],
            'annual_employment_income' => [
                'label' => 'Your annual income',
                'why' => 'Determines how much income protection cover you need if you cannot work',
                'link' => '/profile#income-occupation',
            ],
            // ... more fields
        ],
        'relationships' => [
            'family_members' => [
                'label' => 'Your children or dependants',
                'why' => 'Dependants need financial protection if something happens to you',
                'link' => '/profile#family',
            ],
        ],
    ],
    // ... other modules
];
```

Methods:
- `getRequirementsForModule(User $user, string $module)` - Returns ALL requirements with status:
  - `all_requirements[]` - Complete list with label, why, link, and status (filled/missing)
  - `filled[]` - Items user has provided
  - `missing[]` - Items still needed
  - `completion_percentage` - Progress indicator
- `getModuleFromRoute(string $routePath)` - Maps routes to modules

#### 1.3 InfoGuideController
**File:** `app/Http/Controllers/Api/InfoGuideController.php`

Endpoints:
- `GET /api/info-guide/requirements?module=protection` - Get requirements for module
- `GET /api/info-guide/preference` - Get toggle state
- `PUT /api/info-guide/preference` - Update toggle state

#### 1.4 API Routes
**File:** `routes/api.php` (add to authenticated routes)

```php
Route::prefix('info-guide')->group(function () {
    Route::get('/requirements', [InfoGuideController::class, 'getRequirements']);
    Route::get('/preference', [InfoGuideController::class, 'getPreference']);
    Route::put('/preference', [InfoGuideController::class, 'updatePreference']);
});
```

---

### Phase 2: Frontend

#### 2.1 Vuex Store Module
**File:** `resources/js/store/modules/infoGuide.js`

State:
```javascript
{
    isOpen: false,
    isEnabled: true,
    currentModule: null,
    requirements: null,
    loading: false,
}
```

Actions:
- `fetchRequirements(module)` - Load requirements for current module
- `fetchPreference()` - Load toggle state from API
- `updatePreference(enabled)` - Save toggle state to API
- `toggle()` / `close()` - Open/close panel

Register in `resources/js/store/index.js`.

#### 2.2 InfoGuideButton Component
**File:** `resources/js/components/Shared/InfoGuideButton.vue`

- Fixed position bottom-right (56x56px blue circular button)
- Question mark icon when closed, X when open
- Badge showing count of missing items
- Uses `<Teleport to="body">` for proper z-index
- Hidden when:
  - User has disabled the guide (unless preview mode)
  - On public/login pages (check route meta)

#### 2.3 InfoGuidePanel Component
**File:** `resources/js/components/Shared/InfoGuidePanel.vue`

Slide-out panel from right edge (384px width):
- **Header:** "What powers this view?" + module-specific description
- **Progress bar:** Data completeness percentage
- **Section: "Data that drives this view"** - Shows ALL requirements for this module:
  - **Completed items (green):** Data user has provided, with checkmark and "why" explanation
  - **Missing items (amber):** Data still needed, with link to add it and "why" explanation
- **Educational framing:** Explains how each piece of data contributes to the view/calculations
- **Footer:** Toggle checkbox "Show this help button"

**Panel Layout Example:**
```
+-------------------------------------+
| What powers this view?              |
| Protection analysis uses your       |
| personal data to calculate coverage |
+-------------------------------------+
| Data completeness: ########-- 80%   |
+-------------------------------------+
| Data that drives this view:         |
|                                     |
| [check] Your date of birth          |
|   Used to calculate life expectancy |
|   and insurance term lengths        |
|                                     |
| [check] Your annual income          |
|   Determines how much income        |
|   protection you need               |
|                                     |
| [!] Your monthly spending (missing) |
|   -> Add this to see how long your  |
|   emergency fund would last         |
|   [Add now ->]                      |
+-------------------------------------+
| [ ] Show this help button           |
+-------------------------------------+
```

#### 2.4 Router Integration
**File:** `resources/js/router/index.js`

Add `afterEach` hook to detect module from route and fetch requirements:

```javascript
router.afterEach((to) => {
    const moduleMap = {
        '/protection': 'protection',
        '/savings': 'savings',
        '/investment': 'investment',
        '/retirement': 'retirement',
        '/estate': 'estate',
        '/net-worth': 'net_worth',
        '/dashboard': 'dashboard',
    };

    const module = Object.entries(moduleMap)
        .find(([path]) => to.path.startsWith(path))?.[1] || 'dashboard';

    store.dispatch('infoGuide/fetchRequirements', module);
});
```

#### 2.5 AppLayout Integration
**File:** `resources/js/layouts/AppLayout.vue`

Add components after `<Footer />`:

```vue
<Footer />

<!-- Information Guide -->
<InfoGuideButton />
<InfoGuidePanel />
```

---

### Phase 3: Content Definition

Module-specific data requirements with plain-language explanations:

| Module | Key Fields | Example "Why" Statement |
|--------|-----------|-------------------------|
| **Protection** | DOB, income, marital status, dependants, mortgages | "Your income determines how much cover you need if you cannot work" |
| **Retirement** | DOB, target retirement age, income, pensions, state pension | "Calculates how many years until you can access your pension" |
| **Savings** | Monthly expenditure, savings accounts | "Calculates how many months of expenses your emergency fund covers" |
| **Investment** | DOB, investment accounts, risk profile | "Your age affects recommended asset allocation" |
| **Estate** | DOB, domicile status, marital status, properties, spouse | "Determines which inheritance tax rules apply to your estate" |

---

## Files to Create

| File | Purpose |
|------|---------|
| `database/migrations/xxxx_add_info_guide_enabled_to_users.php` | Add toggle column |
| `app/Services/UserProfile/ModuleDataRequirementsService.php` | Data requirements logic |
| `app/Http/Controllers/Api/InfoGuideController.php` | API endpoints |
| `resources/js/store/modules/infoGuide.js` | Vuex store |
| `resources/js/components/Shared/InfoGuideButton.vue` | Floating button |
| `resources/js/components/Shared/InfoGuidePanel.vue` | Slide-out panel |

---

## Files to Modify

| File | Change |
|------|--------|
| `routes/api.php` | Add info-guide routes |
| `resources/js/store/index.js` | Register infoGuide module |
| `resources/js/router/index.js` | Add afterEach hook |
| `resources/js/layouts/AppLayout.vue` | Add button + panel components |
| `app/Models/User.php` | Add `info_guide_enabled` to fillable (if not already cast) |

---

## Design Notes

1. **Educational + Personalised** - Shows ALL data requirements with explanations, clearly marking what's filled vs missing. Demo users can see WHY information is collected; regular users can see what to add.

2. **Plain English throughout** - Use "tax-free inheritance allowance" not "NRB", "workplace pension" not "DC pension"

3. **Non-intrusive positioning** - Button is 56x56px in bottom-right corner, panel slides from right edge and doesn't overlay main content

4. **Context-aware** - Requirements update automatically when user navigates between modules

5. **"What powers this view" framing** - Each requirement explains its purpose in the current view's calculations

6. **Follows existing patterns** - Same Vuex/API architecture as `guidance.js`, same UI patterns as `ProfileCompletenessAlert.vue`

7. **Preview mode handling** - Always show guide for preview users (ignore their toggle preference) - especially useful for demos
