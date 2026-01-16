# Auto Risk Calculator - Implementation Tasks

## Status: COMPLETED (16 Jan 2026)

---

## Phase 1: Backend Service

### Task 1.1: Create AutoRiskCalculator Service
- [x] Create `app/Services/Risk/AutoRiskCalculator.php`
- [x] Implement `calculateRiskProfile(User $user): array` main method
- [x] Implement `calculateCapacityForLoss()` - uses NetWorthService
- [x] Implement `calculateTimeHorizon()` - uses user.target_retirement_age
- [x] Implement `calculateEducationFactor()` - uses user.education_level
- [x] Implement `calculateDependantsFactor()` - uses FamilyMember count
- [x] Implement `calculateEmploymentFactor()` - uses user.employment_status
- [x] Implement `calculateEmergencyCashFactor()` - uses EmergencyFundCalculator
- [x] Implement `calculateSurplusCashFactor()` - uses income - expenditure
- [x] Implement `determineFinalLevel()` - mode calculation

---

## Phase 2: Database Migration

### Task 2.1: Add factor_breakdown Column
- [x] Create migration `add_factor_breakdown_to_risk_profiles`
- [x] Add `factor_breakdown` JSON column to `risk_profiles` table
- [x] Run migration

---

## Phase 3: Service Integration

### Task 3.1: Update RiskPreferenceService
- [x] Add `calculateAndSetRiskLevel(int $userId): array` method
- [x] Inject AutoRiskCalculator dependency
- [x] Store factor breakdown in JSON column
- [x] Set `is_self_assessed = false` for auto-calculated profiles

---

## Phase 4: API Updates

### Task 4.1: Update RiskPreferenceController
- [x] Modify `getProfile()` to include `factor_breakdown` in response
- [x] Add `recalculate()` endpoint for manual recalculation
- [x] Add route for recalculate endpoint

---

## Phase 5: Remove Form Gates

### Task 5.1: Update AccountForm.vue
- [x] Remove redirect logic at lines 571-577
- [x] Keep RiskLevelSelector for product-level override
- [x] Add `:profile-level="mainRiskLevel"` prop to RiskLevelSelector

### Task 5.2: Update DCPensionForm.vue
- [x] Remove redirect logic in mounted()
- [x] Keep RiskLevelSelector for product-level override
- [x] Add `:profile-level="mainRiskLevel"` prop to RiskLevelSelector

---

## Phase 6: Frontend UI

### Task 6.1: Create FactorBreakdownCard Component
- [x] Create `resources/js/components/Risk/FactorBreakdownCard.vue`
- [x] Display factor name with icon
- [x] Display current value
- [x] Display resulting risk level badge
- [x] Display description

### Task 6.2: Update RiskProfilePage
- [x] Remove 5-level selector (replaced with auto-calculated display)
- [x] Add prominent calculated risk level display (clickable to levels page)
- [x] Add factor breakdown section with 7 cards (clickable to detail pages)
- [x] Add mode explanation text
- [x] Keep product override section
- [x] Keep educational content panels
- [x] Remove "Recalculate" button (auto-calculates on load)
- [x] Made risk level display clickable with hover effects

### Task 6.3: Update riskService.js
- [x] Add `recalculate()` method

### Task 6.4: Create Risk Levels Explained Page
- [x] Create `resources/js/views/Risk/RiskLevelsExplainedPage.vue`
- [x] Display all 5 risk levels with descriptions
- [x] Show asset allocation breakdown for each level
- [x] Show expected return ranges

### Task 6.5: Create Risk Factor Detail Page
- [x] Create `resources/js/views/Risk/RiskFactorDetailPage.vue`
- [x] Display individual factor explanation
- [x] Show how user's data maps to risk level

### Task 6.6: Add Routes for New Pages
- [x] Add route `/risk-profile/levels` for RiskLevelsExplainedPage
- [x] Add route `/risk-profile/factor/:factor` for RiskFactorDetailPage

### Task 6.7: Create RiskProfileSummary Component
- [x] Create `resources/js/components/Risk/RiskProfileSummary.vue`
- [x] Full risk profile display (not just summary)
- [x] Auto-calculates on load via `riskService.recalculate()`
- [x] Displays all sections from RiskProfilePage

### Task 6.8: Update ValuableInfo Page
- [x] Add "Risk Profile" tab to ValuableInfo.vue
- [x] Import and display RiskProfileSummary component
- [x] Add 'risk' to validTabIds for URL parameter support

### Task 6.9: Enhance RiskLevelSelector Component
- [x] Add `profileLevel` prop to indicate user's main risk level
- [x] Add "Your Level" badge indicator above profile level button
- [x] Enhanced styling for profile level (stronger border, medium tint)
- [x] Improved hover effects on allowed levels

---

## Phase 7: Auto-Recalculation

### Task 7.1: Create Recalculation Triggers
- [x] Risk recalculates on page load (RiskProfilePage, RiskProfileSummary)
- [x] Create observer or event listener for User model changes
- [x] Trigger recalculation on relevant field changes
- [x] Debouncing via cache key (5 second window)

**Files Created:**
- `app/Jobs/RecalculateRiskProfileJob.php` - Queued job for recalculation
- `app/Observers/RiskRecalculationObserver.php` - Base observer class
- `app/Observers/UserRiskObserver.php` - Watches user profile changes
- `app/Observers/FamilyMemberRiskObserver.php` - Watches dependant changes
- `app/Observers/SavingsAccountRiskObserver.php` - Watches emergency fund changes
- `app/Observers/InvestmentAccountRiskObserver.php` - Watches investment value changes
- `app/Observers/DCPensionRiskObserver.php` - Watches pension value changes

**Modified:**
- `app/Providers/EventServiceProvider.php` - Registered all observers

---

## Phase 8: Testing

### Task 8.1: Unit Tests
- [x] Test each factor calculation method
- [x] Test mode determination logic
- [x] Test edge cases (missing data, retired users)

**File Created:** `tests/Unit/Services/Risk/AutoRiskCalculatorTest.php`

### Task 8.2: Integration Tests
- [x] Test API endpoint returns breakdown
- [x] Test recalculate endpoint
- [x] Test allowed levels endpoint
- [x] Test config endpoint
- [x] Test validate-product-level endpoint

**File Created:** `tests/Feature/Risk/RiskApiTest.php`

### Task 8.3: Manual Testing
- [x] Login as preview persona
- [x] Verify risk profile is calculated
- [x] Check factor breakdown display
- [ ] Add investment without redirect
- [ ] Add pension without redirect
- [ ] Test product-level override
- [ ] Change user data, verify recalculation

---

## Files Created/Modified

### Created
| File | Description |
|------|-------------|
| `app/Services/Risk/AutoRiskCalculator.php` | Core 7-factor calculation service |
| `app/Jobs/RecalculateRiskProfileJob.php` | Queued job for auto-recalculation |
| `app/Observers/RiskRecalculationObserver.php` | Base observer class with debouncing |
| `app/Observers/UserRiskObserver.php` | Watches user profile changes |
| `app/Observers/FamilyMemberRiskObserver.php` | Watches dependant changes |
| `app/Observers/SavingsAccountRiskObserver.php` | Watches emergency fund changes |
| `app/Observers/InvestmentAccountRiskObserver.php` | Watches investment value changes |
| `app/Observers/DCPensionRiskObserver.php` | Watches pension value changes |
| `resources/js/views/Risk/RiskLevelsExplainedPage.vue` | All 5 risk levels explained |
| `resources/js/views/Risk/RiskFactorDetailPage.vue` | Individual factor detail page |
| `resources/js/components/Risk/FactorBreakdownCard.vue` | Factor display card component |
| `resources/js/components/Risk/RiskProfileSummary.vue` | Full risk profile for Valuable Info tab |
| `database/migrations/xxxx_add_factor_breakdown_to_risk_profiles.php` | JSON column migration |
| `tests/Unit/Services/Risk/AutoRiskCalculatorTest.php` | Unit tests for factor calculations |
| `tests/Feature/Risk/RiskApiTest.php` | Integration tests for risk API |

### Modified
| File | Changes |
|------|---------|
| `app/Services/Risk/RiskPreferenceService.php` | Added `calculateAndSetRiskLevel()` method |
| `app/Http/Controllers/Api/RiskPreferenceController.php` | Added recalculate endpoint, updated getProfile |
| `app/Providers/EventServiceProvider.php` | Registered risk recalculation observers |
| `resources/js/views/Risk/RiskProfilePage.vue` | Complete UI overhaul - factor breakdown display |
| `resources/js/views/ValuableInfo.vue` | Added Risk Profile tab |
| `resources/js/components/Shared/RiskLevelSelector.vue` | Added profileLevel prop, "Your Level" badge |
| `resources/js/components/Investment/AccountForm.vue` | Removed redirect, added profile-level prop |
| `resources/js/components/Retirement/DCPensionForm.vue` | Removed redirect, added profile-level prop |
| `resources/js/services/riskService.js` | Added recalculate() method |
| `resources/js/router/index.js` | Added routes for risk pages |

---

## Factor Calculation Details

### 1. Capacity for Loss
```
Data needed:
- Investment totals: sum(investment_accounts.current_value)
- Pension totals: sum(dc_pensions.current_fund_value)
- Net worth: NetWorthService::calculateNetWorth()

Calculation:
- average_percent = (investments + pensions) / net_worth * 100
- <30% = HIGH
- 30-75% = MEDIUM
- >75% = LOWER_MEDIUM
```

### 2. Time Horizon
```
Data needed:
- user.target_retirement_age
- user.date_of_birth (to calculate current age)
- user.employment_status (to check if retired)

Calculation:
- years_to_retirement = target_retirement_age - current_age
- If employment_status = 'retired': years = 0
- Retired to 3y = LOWER_MEDIUM
- 3-15y = MEDIUM
- 15-20y = UPPER_MEDIUM
- 20+y = HIGH
```

### 3. Education
```
Data needed:
- user.education_level

Calculation:
- 'secondary', 'a_level' = LOWER_MEDIUM (no degree)
- 'undergraduate', 'postgraduate', 'professional' = MEDIUM (degree+)
- null/other = MEDIUM (default)
```

### 4. Dependants
```
Data needed:
- FamilyMember::where('user_id', $user->id)->where('is_dependent', true)->count()

Calculation:
- 0 dependants = UPPER_MEDIUM
- 1 dependant = MEDIUM
- 2+ dependants = LOWER_MEDIUM
```

### 5. Employment
```
Data needed:
- user.employment_status

Calculation:
- 'employed', 'self_employed' = MEDIUM
- 'retired' = LOWER_MEDIUM
- 'unemployed', 'student', other = LOWER_MEDIUM
```

### 6. Emergency Cash
```
Data needed:
- Emergency fund total: sum(savings_accounts.current_balance WHERE is_emergency_fund = true)
- Monthly expenditure: user.monthly_expenditure

Calculation:
- months_runway = emergency_fund / monthly_expenditure
- 0-3 months = LOWER_MEDIUM
- 3-6 months = MEDIUM
- 6+ months = UPPER_MEDIUM
```

### 7. Surplus Cash
```
Data needed:
- Monthly income: sum(user.annual_*_income) / 12
- Monthly expenditure: user.monthly_expenditure

Calculation:
- monthly_surplus = monthly_income - monthly_expenditure
- Negative to £0 = LOWER_MEDIUM
- £0 to £500 = MEDIUM
- £501+ = UPPER_MEDIUM
```

---

## Remaining Work

1. ~~**Auto-recalculation triggers**~~ - COMPLETED: Created observers for all relevant models
2. ~~**Unit tests**~~ - COMPLETED: `tests/Unit/Services/Risk/AutoRiskCalculatorTest.php`
3. ~~**Integration tests**~~ - COMPLETED: `tests/Feature/Risk/RiskApiTest.php`
4. ~~**Manual testing**~~ - COMPLETED: Full flow tested in browser
   - [x] Add investment without redirect
   - [x] Add pension without redirect
   - [x] Test product-level override
   - [x] Change user data, verify recalculation
5. ~~**RiskLevelSelector hover effects**~~ - COMPLETED: Grey → color on hover

---

## Additional Work Completed

### RiskLevelSelector Hover Effects

Updated component to show adjacent allowed levels as grey by default, revealing their color on hover:

- **Profile level**: Bold solid color (active state)
- **Adjacent allowed levels**: Grey (`#f3f4f6`) by default, bold color on hover
- **Disabled levels**: Grey with 60% opacity
- **Transition**: Smooth 150ms color transition

**Technical Implementation:**
- Added `hoveredLevel` data property to track hover state
- Added `@mouseenter` and `@mouseleave` events on buttons
- Updated `getButtonStyle()` to return bold color when `isHovered && isAllowed`
- Fixed `isLevelAllowed()` to handle API response objects with `.some()` instead of `.includes()`

### Bug Fix: RetirementStrategyService Division by Zero

Fixed 500 error when loading retirement page for users without target retirement income:
- Added guard: `if ($targetIncome <= 0) return null;`
- Location: `app/Services/Retirement/RetirementStrategyService.php:655`

---

## Git Commit

**7adbca2** - `feat(risk): Implement automated risk profile calculator with 7-factor analysis`
