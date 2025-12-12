# Preview Dashboard with Personas - Implementation Summary

## Overview

The Preview Dashboard allows visitors to explore Fynla with example financial data before registering. Four personas showcase different life stages and financial situations.

**Access URL**: `/preview` or `/preview?persona=young_family`

---

## What Was Implemented

### 1. Vuex Store Integration

**Central Preview Module** (`resources/js/store/modules/preview.js`):
- Manages preview mode state across the application
- Loads persona JSON data dynamically
- Tracks user edits to preview values
- Coordinates with all module stores
- Persists state to sessionStorage for page reload survival
- Supports URL-based persona selection

**Key Functions**:
| Function | Description |
|----------|-------------|
| `loadPersona(personaId)` | Loads a persona's JSON data and activates preview mode |
| `switchPersona(newPersonaId)` | Switches to a different persona |
| `updateValue({ path, value })` | Updates a single value and triggers recalculation |
| `exitPreview()` | Clears preview state and returns to normal mode |
| `initFromStorage()` | Restores preview state from sessionStorage |
| `saveToStorage()` | Persists preview state to sessionStorage |
| `clearEdits()` | Resets edited values to original persona data |

**Modified Module Stores**:
- `store/modules/netWorth.js` - Added `setPreviewMode` action
- `store/modules/protection.js` - Added `setPreviewMode` action
- `store/modules/estate.js` - Added `setPreviewMode` action
- `store/modules/retirement.js` - Added `setPreviewMode` action
- `store/modules/savings.js` - Added `setPreviewMode` action
- `store/modules/investment.js` - Added `setPreviewMode` action

---

### 2. Persona Data Files

Located in `resources/js/data/personas/`:

| File | Persona | Description | Net Worth |
|------|---------|-------------|-----------|
| `young_family.json` | Emily & James Carter | Young couple, 2 kids, mortgage, workplace pensions | ~£150k |
| `peak_earners.json` | David & Sarah Mitchell | Late 40s, BTL property, DB pensions, high income | ~£1.45m |
| `widow.json` | Margaret Thompson | Widowed retiree, IHT concerns, gifting history | ~£1.6m |
| `entrepreneur.json` | Alex Chen | Single, tech consultancy owner, SIPP, business interests | ~£1m |

**Data Structure** (matches database schema):
```javascript
{
  "id": "young_family",
  "user": { /* user profile data */ },
  "spouse": { /* spouse data or null */ },
  "family_members": [ /* dependents */ ],
  "properties": [ /* property records */ ],
  "mortgages": [ /* mortgage records */ ],
  "savings_accounts": [ /* savings accounts */ ],
  "investment_accounts": [ /* investment accounts with holdings */ ],
  "dc_pensions": [ /* defined contribution pensions */ ],
  "db_pensions": [ /* defined benefit pensions */ ],
  "state_pension": { /* state pension forecast */ },
  "life_insurance_policies": [ /* life policies */ ],
  "critical_illness_policies": [ /* CI policies */ ],
  "income_protection_policies": [ /* IP policies */ ],
  "liabilities": [ /* non-mortgage liabilities */ ],
  "gifts": [ /* IHT gift records */ ],
  "trusts": [ /* trust records */ ],
  "iht_profile": { /* IHT allowances */ },
  "expenditure": { /* monthly expenditure */ },
  "key_concerns": [ /* persona's key questions */ ]
}
```

---

### 3. Preview Service

**File**: `resources/js/services/previewService.js`

Client-side calculation service with fallback logic when backend endpoints unavailable.

| Method | Description | Backend Endpoint |
|--------|-------------|------------------|
| `calculateNetWorth(personaData)` | Calculates total assets, liabilities, net worth | Client-side only |
| `calculateIHT(personaData)` | Calculates IHT liability | `/api/preview/calculate-iht` (falls back to client) |
| `calculateProtectionGaps(personaData)` | Calculates protection adequacy | `/api/preview/calculate-protection` (falls back to client) |
| `calculateRetirement(personaData)` | Projects retirement income | `/api/preview/calculate-retirement` (falls back to client) |
| `calculateEmergencyFund(personaData)` | Calculates emergency fund status | Client-side only |

**Note**: Backend endpoints not yet implemented - all calculations currently use client-side fallbacks.

---

### 4. Preview Components

Located in `resources/js/components/Preview/`:

| Component | File | Purpose |
|-----------|------|---------|
| PreviewBanner | `PreviewBanner.vue` | Amber sticky banner with persona selector, edit count, and register CTA. Mobile responsive. |
| PersonaSelector | `PersonaSelector.vue` | Dropdown to switch between personas with confirmation when edits exist |
| PersonaIntroModal | `PersonaIntroModal.vue` | Modal introducing persona before loading with key concerns |
| EditablePreviewField | `EditablePreviewField.vue` | Inline editable field wrapper with modification tracking |
| PersonalInfoWarningModal | `PersonalInfoWarningModal.vue` | Warning when editing personal info fields |
| KeepDataOrFreshModal | `KeepDataOrFreshModal.vue` | Post-registration modal to keep persona data or start fresh |
| PreviewLoadingSkeleton | `PreviewLoadingSkeleton.vue` | Skeleton loading states for preview pages |

---

### 5. Guidance System Components

Located in `resources/js/components/Guidance/`:

| Component | File | Purpose |
|-----------|------|---------|
| GuidanceWelcomeModal | `GuidanceWelcomeModal.vue` | Welcome modal for new users introducing the guided setup |
| GuidanceTooltip | `GuidanceTooltip.vue` | Contextual tooltip that points to dashboard sections |

**Guidance Store Module** (`resources/js/store/modules/guidance.js`):
- Manages 8-step guidance flow for new users
- Tracks completed/skipped steps
- Persists progress to database via API
- Supports start/complete/skip/dismiss actions

---

### 6. Preview Routes

**File**: `resources/js/router/index.js`

Added preview routes accessible without authentication:

```javascript
// Preview routes - accessible without authentication
{
  path: '/preview',
  name: 'PreviewDashboard',
  component: Dashboard,
  meta: { public: true, previewMode: true },
  beforeEnter: async (to, from, next) => {
    const personaId = to.query.persona || 'young_family';
    await store.dispatch('preview/loadPersona', personaId);
    next();
  },
},
{
  path: '/preview/net-worth',
  name: 'PreviewNetWorth',
  component: NetWorthDashboard,
  meta: { public: true, previewMode: true },
  children: [...],
},
{
  path: '/preview/protection',
  name: 'PreviewProtection',
  component: ProtectionDashboard,
  meta: { public: true, previewMode: true },
},
// ... savings, investment, retirement, estate, profile
```

**URL State Management**:
- Persona persisted in URL query param: `/preview?persona=widow`
- URL updates when switching personas (shareable links)
- Navigation guard handles authenticated users accessing preview (redirects to authenticated version)

---

### 7. Backend Integration

**File**: `app/Http/Controllers/Api/PreviewController.php`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/user/seed-persona-data` | POST | Seeds persona data to authenticated user's account |
| `/api/user/guidance-status` | GET | Get user's guidance progress |
| `/api/user/guidance-status` | POST | Update guidance progress |

**Data Seeding Features**:
- Seeds all entity types: properties, mortgages, savings, investments, pensions, protection policies, liabilities, family members
- Updates user profile fields from persona
- Optional spouse account creation for married personas
- Tracks `preview_persona_kept` for analytics

**Database Migration** (`2025_12_12_103752_add_guidance_columns_to_users_table.php`):
```php
$table->boolean('guidance_active')->default(false);
$table->boolean('guidance_completed')->default(false);
$table->unsignedTinyInteger('guidance_current_step')->default(0);
$table->json('guidance_completed_steps')->nullable();
$table->json('guidance_skipped_steps')->nullable();
$table->string('guidance_version', 10)->nullable();
$table->string('registration_source', 50)->nullable();
$table->string('preview_persona_kept', 50)->nullable();
```

---

### 8. Registration Flow Updates

**File**: `resources/js/views/Register.vue`

Changes:
- Shows preview mode indicator when registering from preview
- After registration from preview, shows KeepDataOrFreshModal
- Options to keep persona data or start fresh
- For married personas, checkbox to create spouse account
- Triggers guidance welcome modal for new users
- Clears preview mode after registration

---

### 9. Landing Page Updates

**File**: `resources/js/views/Public/LandingPage.vue`

Changes:
- Added "Try the Demo" button (amber, prominent) linking to `/preview`
- Updated badge to "Interactive Demo - No Sign-up Required" with link to `/preview`
- Badge has animated pulse indicator

---

## Implementation Status

| Phase | Status | Description |
|-------|--------|-------------|
| Phase 1: Vuex Integration | ✅ Complete | Preview store module with all module integrations |
| Phase 2: Persona Data | ✅ Complete | 4 persona JSON files matching database schema |
| Phase 3: Preview Routes | ✅ Complete | Public preview routes reusing authenticated components |
| Phase 4: Preview Service | ✅ Complete | Client-side calculation fallbacks |
| Phase 5: Preview Components | ✅ Complete | Banner, selectors, modals |
| Phase 6: Interactive Editing | ✅ Framework Complete | EditablePreviewField component ready (integration deferred) |
| Phase 7: Registration Flow | ✅ Complete | KeepDataOrFreshModal, guidance system, backend seeding |
| Phase 8: Polish | ✅ Complete | URL state, loading skeletons, mobile responsive |

---

## E2E Test Checklist

### Landing Page Tests

- [ ] **Navigate to `/`** - Landing page loads
- [ ] **Click badge "Interactive Demo"** - Navigates to `/preview`
- [ ] **Click "Try the Demo" button** - Navigates to `/preview`
- [ ] **"Get Started Free" button** - Still navigates to `/register`
- [ ] **"Sign In" button** - Still navigates to `/login`

### Preview Dashboard Tests

- [ ] **Navigate to `/preview`** - Preview dashboard loads
- [ ] **Amber banner visible** - Shows "Preview Mode" with persona selector
- [ ] **Default persona loads** - Young Family (Emily & James Carter) loads by default
- [ ] **Persona selector visible** - Dropdown shows 4 personas
- [ ] **Net Worth card** - Shows calculated net worth value
- [ ] **Protection card** - Shows adequacy percentage with progress bar
- [ ] **Retirement card** - Shows pension value and years to retirement
- [ ] **Estate card** - Shows IHT liability estimate
- [ ] **Savings card** - Shows total savings and emergency fund months
- [ ] **Investment card** - Shows portfolio value and account count

### Persona Switching Tests

- [ ] **Click persona selector** - Dropdown opens with 4 options
- [ ] **Select "Peak Earners"** - Intro modal appears for David & Sarah Mitchell
- [ ] **Click "Explore Dashboard"** - Modal closes, data updates to Peak Earners
- [ ] **Verify values changed** - Net worth should be ~£1.45m instead of ~£150k
- [ ] **Switch to "Widow"** - Margaret Thompson loads with ~£1.6m net worth
- [ ] **Switch to "Entrepreneur"** - Alex Chen loads with ~£1m net worth
- [ ] **Switch back to "Young Family"** - Original data restored
- [ ] **URL updates with persona** - `/preview?persona=widow` shows in address bar

### URL State Tests

- [ ] **Direct URL with persona** - `/preview?persona=peak_earners` loads Peak Earners directly
- [ ] **Invalid persona ID** - Falls back to young_family
- [ ] **Share URL** - Copied URL loads correct persona for others
- [ ] **Page refresh** - Preview state persists via sessionStorage

### Registration Flow Tests

- [ ] **Click "Register to Save Your Data"** - Navigates to `/register`
- [ ] **Preview indicator visible** - Shows "Registering from preview mode"
- [ ] **Complete registration** - KeepDataOrFreshModal appears
- [ ] **Select "Keep data"** - Seeds persona data to account
- [ ] **Select "Start fresh"** - Empty account created
- [ ] **Spouse checkbox** - Creates spouse account for married personas
- [ ] **Guidance modal** - Welcome modal appears after registration

### Mobile Responsiveness Tests

- [ ] **Mobile preview banner** - Compact 2-row layout
- [ ] **Mobile persona selector** - Works with small size prop
- [ ] **Touch targets** - Buttons appropriately sized
- [ ] **Scroll behavior** - Banner stays visible

---

## Known Limitations

1. **Backend Preview Calculation Endpoints**: The API endpoints for `/api/preview/calculate-*` are not implemented. All calculations use client-side fallbacks.

2. **EditablePreviewField Integration**: The framework is complete but not yet integrated into existing components. Values can be tracked in the store but inline editing is not wired up in Dashboard components yet.

3. **DB Pension Valuation**: DB pensions use a simplified 20x annual pension proxy for net worth, which may not be accurate.

4. **Guidance Tooltip Positioning**: May need refinement for edge cases where target elements are near viewport edges.

---

## Files Created/Modified

### New Files (30+ files)

```
# Store Modules
resources/js/store/modules/preview.js
resources/js/store/modules/guidance.js

# Services
resources/js/services/previewService.js

# Persona Data
resources/js/data/personas/young_family.json
resources/js/data/personas/peak_earners.json
resources/js/data/personas/widow.json
resources/js/data/personas/entrepreneur.json

# Preview Components
resources/js/components/Preview/PreviewBanner.vue
resources/js/components/Preview/PersonaSelector.vue
resources/js/components/Preview/PersonaIntroModal.vue
resources/js/components/Preview/EditablePreviewField.vue
resources/js/components/Preview/PersonalInfoWarningModal.vue
resources/js/components/Preview/KeepDataOrFreshModal.vue
resources/js/components/Preview/PreviewLoadingSkeleton.vue

# Guidance Components
resources/js/components/Guidance/GuidanceWelcomeModal.vue
resources/js/components/Guidance/GuidanceTooltip.vue

# Utilities
resources/js/utils/previewFieldConfig.js

# Backend
app/Http/Controllers/Api/PreviewController.php
database/migrations/2025_12_12_103752_add_guidance_columns_to_users_table.php
```

### Modified Files (10+ files)

```
resources/js/store/index.js (registered preview + guidance modules)
resources/js/store/modules/netWorth.js (added preview mode)
resources/js/store/modules/protection.js (added preview mode)
resources/js/store/modules/estate.js (added preview mode)
resources/js/store/modules/retirement.js (added preview mode)
resources/js/store/modules/savings.js (added preview mode)
resources/js/store/modules/investment.js (added preview mode)
resources/js/router/index.js (added preview routes + URL state)
resources/js/views/Public/LandingPage.vue (added Try Demo CTA)
resources/js/views/Register.vue (added preview flow handling)
resources/js/app.js (added preview init from sessionStorage)
routes/api.php (added preview controller routes)
app/Models/User.php (added guidance field casts)
```

---

## Quick Start Testing

1. Start development server:
   ```bash
   ./dev.sh
   ```

2. Navigate to `http://localhost:8000/`

3. Click "Try the Demo" button or the amber badge

4. Verify:
   - Preview dashboard loads at `/preview`
   - Young Family persona is pre-selected
   - All 6 module cards display calculated values
   - Persona selector allows switching between 4 personas
   - URL updates when switching personas
   - Each module detail page loads correctly

5. Test registration flow:
   - Click "Register to Save Your Data"
   - Complete registration form
   - KeepDataOrFreshModal should appear
   - Select keep/fresh option
   - Guidance welcome modal should appear

---

## Future Enhancements

1. **Deep EditablePreviewField Integration**: Wire up inline editing in Dashboard/Protection/Estate etc. components

2. **Backend Calculation Endpoints**: Implement `/api/preview/calculate-*` endpoints for server-side calculations

3. **Analytics Integration**:
   - Track which personas are most popular
   - Track which pages users visit in preview
   - Track conversion rate from preview to registration

4. **SEO/Meta Tags**:
   - OpenGraph tags for preview pages
   - Meta descriptions for each persona

5. **Accessibility**:
   - ARIA labels on preview controls
   - Keyboard navigation for persona selector
   - Screen reader announcements for data changes

---

*Document updated: December 12, 2025*
*Implementation Status: All Phases Complete (Phases 1-8)*
