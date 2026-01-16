# January 16 Updates - Implementation Tasks

## Status: COMPLETED (16 Jan 2026)

---

## Part 1: New Personas

### Status: COMPLETED

Adding two new preview personas to better represent Fynla's target demographics:

1. **Young Adult Saver** (`young_saver`) - Alex Morgan, 24, single, renting, building savings
2. **Retired Couple** (`retired_couple`) - Patricia & Harold Bennett, 70/72, drawing pensions, gifting

---

## Tasks

### Phase 1: Create Persona JSON Files

- [x] **Create `young_saver.json`**
  - Location: `resources/js/data/personas/young_saver.json`
  - Key data:
    - Alex Morgan, 24, single, Junior Data Analyst, £32,000/year
    - Renting in Manchester (no properties)
    - Cash ISA (£3,200), LISA (£2,400), current account (£850)
    - Workplace pension with NEST (£4,800)
    - Student loan Plan 2 (£42,000)
    - No protection policies
    - Tight budget with focus on saving

- [x] **Create `retired_couple.json`**
  - Location: `resources/js/data/personas/retired_couple.json`
  - Key data:
    - Patricia (70) & Harold (72) Bennett, married, retired
    - Owned home in Tunbridge Wells (£550,000, no mortgage)
    - Patricia: NHS DB pension (£18,500) + State Pension (£11,500)
    - Harold: Civil Service DB pension (£22,000) + State Pension (£11,500)
    - Combined savings/investments ~£295,500
    - 5 grandchildren, active gifting strategy
    - No liabilities
    - IHT exposure focus

### Phase 2: Update Seeder

- [x] **Modify `PreviewUserSeeder.php`**
  - Add `young_saver` and `retired_couple` to persona array
  - Ensure handling for:
    - Single users (no spouse)
    - DB pensions in payment
    - Student loan liability type
    - LISA savings account type
    - Grandchildren as `other_dependent` relationship
  - **Additional changes made:**
    - Added `createGifts()` method for seeding gift records
    - Added support for `spouse_state_pension` in JSON files
    - Added `Gift` model import

- [x] **Update `PreviewController.php`**
  - Added `young_saver` and `retired_couple` to `VALID_PERSONAS`
  - Added metadata entries for both personas

### Phase 3: Testing

- [x] **Run seeder**
  ```bash
  php artisan db:seed --class=PreviewUserSeeder --force
  ```

- [x] **Test API login**
  - `POST /api/preview/login/young_saver` - Working
  - `POST /api/preview/login/retired_couple` - Working

- [x] **Verify data loads** for both personas
  - Savings accounts load correctly
  - Retirement pensions load correctly
  - Liabilities load correctly (student loan)
  - Estate/gifts load correctly
  - Properties load correctly

- [x] **Test each module**
  - Net Worth (properties, liabilities) - Verified
  - Savings (ISA, LISA, accounts) - Verified
  - Investment (portfolios) - Verified
  - Retirement (pensions) - Verified
  - Protection (policies or empty states) - N/A for these personas
  - Estate (IHT calculations, gifting) - Verified

### Phase 4: UI Updates

- [x] **Update Vuex store** (`resources/js/store/modules/preview.js`)
  - Added imports for `young_saver.json` and `retired_couple.json`
  - Added PERSONA_DATA entries for both
  - Added PERSONA_METADATA entries with netWorthRange, focus, description

- [x] **Update PersonaSelectionModal.vue** (landing page modal)
  - Added emoji mappings: young_saver (🎓), retired_couple (👴👵)
  - Added header gradient: cyan for young_saver, rose for retired_couple
  - Added focus badge classes

- [x] **Update PersonaSelector.vue** (dashboard dropdown)
  - Added button color classes for dark variant
  - Added avatar background colors
  - Added emoji mappings

- [x] **Update PersonaIntroModal.vue** (intro modal after selection)
  - Added emoji mappings
  - Added header gradient classes
  - Added key financial concerns for both personas

---

## Young Saver Persona Details

### Alex Morgan

| Field | Value |
|-------|-------|
| Age | 24 |
| Status | Single |
| Job | Junior Data Analyst |
| Employer | DataTech Solutions Ltd |
| Income | £32,000/year |
| Rent | £650/month (shared house) |
| Location | Manchester |

### Financial Summary

| Category | Value |
|----------|-------|
| Cash ISA | £3,200 |
| LISA | £2,400 |
| Current Account | £850 |
| Easy Access Savings | £1,500 |
| Workplace Pension | £4,800 |
| Student Loan | £42,000 |
| **Net Worth** | ~-£34,000 |

### Key Module States

- **Properties**: Empty (renting)
- **Savings**: 4 accounts (Cash ISA, LISA, Current, Easy Access)
- **Investments**: None
- **Retirement**: 1 DC pension (NEST)
- **Protection**: None (or employer death-in-service only)
- **Estate**: Minimal planning needed

---

## Retired Couple Persona Details

### Patricia & Harold Bennett

| Field | Patricia | Harold |
|-------|----------|--------|
| Age | 70 | 72 |
| Former Job | NHS Nurse Manager | Civil Servant (HMRC) |
| DB Pension | £18,500/year | £22,000/year |
| State Pension | £11,500/year | £11,500/year |

### Financial Summary

| Asset | Value |
|-------|-------|
| Main Residence | £550,000 |
| Patricia's ISAs | £110,000 |
| Harold's ISAs | £127,000 |
| Premium Bonds | £50,000 |
| Current Account | £8,500 |
| **Gross Estate** | ~£845,500 |
| **Est. IHT** | ~£138,200 |

### Family

- **Children**: Mark (45), Susan (42)
- **Grandchildren**: Emma (16), Thomas (13), Lucy (11), Sophie (8), William (5)
- **Annual gifting**: £6,000 (£3,000 each) + £2,500 to grandchildren JISAs

### Key Module States

- **Properties**: 1 main residence (owned outright)
- **Savings**: 5 accounts (current, 2x Cash ISA, Premium Bonds, 2x S&S ISA)
- **Investments**: 2 S&S ISAs
- **Retirement**: 2 DB pensions (in payment), 2 State Pensions
- **Protection**: Minimal (spouse benefits from DB pensions)
- **Estate**: IHT planning focus, active gifting

---

## Research Notes

### Young Adult Saving Products (UK 2025)

1. **Lifetime ISA (LISA)** - 25% government bonus, max £4,000/year, for first home or retirement at 60
2. **Cash ISA** - Tax-free interest, £20,000 annual limit
3. **Workplace Pension** - Auto-enrolment minimum: employee 5%, employer 3%
4. **Help to Save** - For those on Universal Credit/Working Tax Credit (not applicable here)
5. **Student Loan Plan 2** - Repay 9% over £27,295, write-off after 30 years

### Retired Couple Considerations (UK 2025/26)

1. **DB Pension Security** - Index-linked (CPI), spouse benefits typically 50%
2. **State Pension** - Full rate £11,500/year (2025/26), triple lock
3. **IHT Changes** - April 2027: Pensions included in estate for IHT
4. **Gifting Rules** - £3,000 annual exemption per person, PET 7-year rule
5. **Care Costs** - Potential concern for couples in 70s
6. **Equity Release** - Option but typically last resort

---

## Part 2: Preview Mode Restrictions

### Status: COMPLETED

Implemented a system to disable editing actions in preview mode with informative tooltips encouraging users to register.

---

### Implementation

#### Vue Directive: `v-preview-disabled`

Created a custom Vue directive that:
- Disables buttons/links when in preview mode
- Shows a custom tooltip on hover with context-appropriate messages
- Greys out buttons with reduced opacity
- Prevents click actions from executing

**Location**: `resources/js/directives/previewDisabled.js`

**Usage**:
```vue
<button v-preview-disabled="'add'" @click="addItem">Add Item</button>
<button v-preview-disabled="'edit'" @click="editItem">Edit</button>
<button v-preview-disabled="'delete'" @click="deleteItem">Delete</button>
```

**Tooltip Messages**:
| Action Type | Tooltip Message |
|-------------|-----------------|
| `add` | Register to add data |
| `edit` | Register to edit data |
| `delete` | Register to delete data |
| `upload` | Register to upload data |
| `save` | Register to save data |
| default | Register to use this feature |

#### Custom Tooltip Features

- Appears immediately on hover (not delayed like native `title` attribute)
- Dark background with white text for visibility
- Positioned above the element with an arrow pointing down
- Smooth fade-in animation
- Auto-repositions to stay within viewport

---

### Components Updated

The following 23 components were updated with `v-preview-disabled`:

#### Net Worth Module
- `PropertyList.vue` - Add Property button
- `PropertyDetailInline.vue` - Edit/Delete buttons
- `BusinessInterestList.vue` - Add Business button
- `BusinessInterestDetailInline.vue` - Edit/Delete buttons
- `ChattelList.vue` - Add Chattel button
- `ChattelDetailInline.vue` - Edit/Delete buttons
- `CashList.vue` - Add Account button
- `CashDetailInline.vue` - Edit/Delete buttons
- `InvestmentList.vue` - Add Investment button
- `InvestmentDetailInline.vue` - Edit/Delete buttons
- `RetirementList.vue` - Add Pension button
- `RetirementDetailInline.vue` - Edit/Delete buttons

#### Savings Module
- `SavingsAccountList.vue` - Add Account button
- `AccountDetailInline.vue` - Edit/Delete buttons

#### Investment Module
- `InvestmentDashboard.vue` - Add Investment/Portfolio buttons
- `InvestmentAccountDetailInline.vue` - Edit/Delete buttons

#### Protection Module
- `PolicyList.vue` - Add Policy button
- `PolicyDetailInline.vue` - Edit/Delete buttons

#### Estate Module
- `AssetOverview.vue` - Edit buttons
- `GiftList.vue` - Add Gift button
- `GiftDetailInline.vue` - Edit/Delete buttons

#### Retirement Module
- `RetirementDashboard.vue` - Add Pension button
- `PensionDetailInline.vue` - Edit/Delete buttons

#### Liabilities
- `LiabilityDetailInline.vue` - Edit/Delete buttons

---

### Technical Details

**Directive Registration** (`resources/js/app.js`):
```javascript
import { previewDisabled } from '@/directives/previewDisabled';
app.directive('preview-disabled', previewDisabled);
```

**Preview Mode Detection**:
- Uses Vuex store: `$store.getters['preview/isPreviewMode']`
- Checks if user is a preview user (database flag `is_preview_user`)

**CSS Styling**:
- Disabled buttons: `opacity: 0.5`, `cursor: not-allowed`
- Tooltip: Dark background (#1f2937), white text, 8px border-radius
- Smooth transitions for better UX

---

### Git Commits

1. **ec65857** - `feat(preview): Disable editing actions in preview mode with tooltips`
   - Added v-preview-disabled directive with custom tooltip
   - Updated 23 components across all modules

2. **6e93037** - `Merge branch 'persona'`
   - Merged persona branch into main
   - Includes both new personas and preview mode restrictions

---

### Testing

- [x] Logged in as preview persona (young_family)
- [x] Verified buttons show grey/disabled state
- [x] Verified tooltip appears on hover with correct message
- [x] Verified clicking disabled buttons does nothing
- [x] Verified regular users (non-preview) can still use all buttons normally
- [x] Tested across all modules (Net Worth, Savings, Investment, Protection, Estate, Retirement)

---

## Part 3: Automated Risk Profile Calculator

### Status: COMPLETED

Replacing manual risk profile selection with automatic calculation based on 7 financial factors. Users no longer need to manually set a risk profile - it calculates automatically from their data.

---

### The 7 Factors

| Factor | Data Source | Risk Level Assignment |
|--------|-------------|----------------------|
| **1. Capacity for Loss** | (investments + pensions) / net worth | <30% = HIGH, 30-75% = MEDIUM, >75% = LOWER_MEDIUM |
| **2. Time Horizon** | Years to retirement | Retired-3y = LOWER_MEDIUM, 3-15y = MEDIUM, 15-20y = UPPER_MEDIUM, 20+y = HIGH |
| **3. Education** | `user.education_level` | No degree = LOWER_MEDIUM, Degree+ = MEDIUM |
| **4. Dependants** | Count of `is_dependent=true` | 0 = UPPER_MEDIUM, 1 = MEDIUM, 2+ = LOWER_MEDIUM |
| **5. Employment** | `user.employment_status` | employed/self_employed = MEDIUM, retired = LOWER_MEDIUM |
| **6. Emergency Cash** | Emergency fund runway months | 0-3mo = LOWER_MEDIUM, 3-6mo = MEDIUM, 6+mo = UPPER_MEDIUM |
| **7. Surplus Cash** | Monthly income - expenditure | Negative-0 = LOWER_MEDIUM, 0-500 = MEDIUM, 501+ = UPPER_MEDIUM |

**Final Risk Level** = Most recurring level across all 7 factors (mode)

---

### Completed Work

#### Backend
- [x] Created `AutoRiskCalculator.php` service with all 7 factor calculations
- [x] Added `factor_breakdown` JSON column to risk_profiles table
- [x] Updated `RiskPreferenceService` with `calculateAndSetRiskLevel()` method
- [x] Added `recalculate()` API endpoint

#### Frontend - Risk Profile Pages
- [x] Created `RiskLevelsExplainedPage.vue` - explains all 5 risk levels
- [x] Created `RiskFactorDetailPage.vue` - individual factor deep-dive
- [x] Created `FactorBreakdownCard.vue` - factor display component
- [x] Updated `RiskProfilePage.vue` with factor breakdown display
- [x] Made risk level card clickable with hover effects
- [x] Factor cards link to detail pages
- [x] Removed manual recalculate button (auto-calculates on load)
- [x] Added routes for new pages

#### Frontend - Valuable Info Integration
- [x] Created `RiskProfileSummary.vue` with full risk profile content
- [x] Added "Risk Profile" tab to `ValuableInfo.vue`
- [x] Auto-calculates risk on tab load

#### Frontend - Form Enhancements
- [x] Enhanced `RiskLevelSelector.vue` with `profileLevel` prop
- [x] Profile level shows bold solid color (active state)
- [x] Adjacent allowed levels show grey by default, reveal color on hover
- [x] Disabled levels greyed out with opacity
- [x] Smooth 150ms transition on hover
- [x] Fixed `isLevelAllowed()` to handle API response objects (not just strings)
- [x] Updated `AccountForm.vue` to pass profile-level
- [x] Updated `DCPensionForm.vue` to pass profile-level

---

### Auto-Recalculation Observers

Created model observers that automatically recalculate risk profile when relevant data changes:

| Observer | Watches | Triggers On |
|----------|---------|-------------|
| `UserRiskObserver` | User model | Income, education, employment, retirement age changes |
| `FamilyMemberRiskObserver` | FamilyMember | Dependant status changes |
| `SavingsAccountRiskObserver` | SavingsAccount | Emergency fund balance changes |
| `InvestmentAccountRiskObserver` | InvestmentAccount | Current value changes |
| `DCPensionRiskObserver` | DCPension | Fund value changes |

**Implementation Details:**
- Uses `RecalculateRiskProfileJob` (queued) for async recalculation
- Debouncing via cache key (5 second window) to prevent rapid recalculations
- Registered in `EventServiceProvider.php`

---

### Tests Created

**Unit Tests:** `tests/Unit/Services/Risk/AutoRiskCalculatorTest.php`
- Tests all 7 factor calculations
- Tests mode determination logic
- Tests edge cases (retired users, missing data)

**Integration Tests:** `tests/Feature/Risk/RiskApiTest.php`
- Tests GET `/api/investment/risk/profile`
- Tests POST `/api/investment/risk/recalculate`
- Tests GET `/api/investment/risk/levels`
- Tests GET `/api/investment/risk/allowed-levels`
- Tests GET `/api/investment/risk/config/{level}`
- Tests POST `/api/investment/risk/validate-product-level`

---

### Remaining Work

- [x] Auto-recalculation triggers (observers for user data changes)
- [x] Unit tests for factor calculations
- [x] Integration tests for API endpoints
- [x] Full manual testing flow
- [x] RiskLevelSelector hover effects (grey → color on hover)

---

### Key Files

| File | Purpose |
|------|---------|
| `app/Services/Risk/AutoRiskCalculator.php` | Core 7-factor calculation |
| `app/Jobs/RecalculateRiskProfileJob.php` | Queued job for auto-recalculation |
| `app/Observers/UserRiskObserver.php` | User profile change observer |
| `app/Observers/FamilyMemberRiskObserver.php` | Dependant change observer |
| `app/Observers/SavingsAccountRiskObserver.php` | Emergency fund observer |
| `app/Observers/InvestmentAccountRiskObserver.php` | Investment value observer |
| `app/Observers/DCPensionRiskObserver.php` | Pension value observer |
| `resources/js/views/Risk/RiskProfilePage.vue` | Main risk profile display |
| `resources/js/views/Risk/RiskLevelsExplainedPage.vue` | Risk levels explanation |
| `resources/js/views/Risk/RiskFactorDetailPage.vue` | Factor detail page |
| `resources/js/components/Risk/RiskProfileSummary.vue` | Full profile for Valuable Info |
| `resources/js/components/Shared/RiskLevelSelector.vue` | Enhanced selector with profile level |
| `tests/Unit/Services/Risk/AutoRiskCalculatorTest.php` | Unit tests |
| `tests/Feature/Risk/RiskApiTest.php` | Integration tests |

See `autoRiskTasks.md` for detailed task tracking.

---

### Bug Fix: RetirementStrategyService Division by Zero

**Issue:** 500 error when loading retirement page for users without target retirement income set.

**Root Cause:** `RetirementStrategyService.php:671` - Division by zero when `$targetIncome = 0`:
```php
$incomeCoverage = ($totalIncome / $targetIncome) * 100;
```

**Fix:** Added early return when target income is not set:
```php
$targetIncome = $currentStatus['target_income'] ?? 0;
if ($targetIncome <= 0) {
    return null;
}
```

**Location:** `app/Services/Retirement/RetirementStrategyService.php`

---

### Git Commit

**7adbca2** - `feat(risk): Implement automated risk profile calculator with 7-factor analysis`

- Add AutoRiskCalculator service with 7 financial factors
- Add factor_breakdown JSON column to risk_profiles table
- Create model observers for auto-recalculation on data changes
- Add recalculate API endpoint
- Update RiskProfilePage to show factor breakdown display
- Add RiskLevelsExplainedPage and RiskFactorDetailPage
- Add RiskProfileSummary component to ValuableInfo tab
- Enhance RiskLevelSelector with hover color effects
- Remove form redirect gates (risk auto-calculates now)
- Add unit and integration tests
- Fix division by zero in RetirementStrategyService
