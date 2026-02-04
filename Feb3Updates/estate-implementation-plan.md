# Estate Planning Module - Implementation Plan

**Date:** 3 February 2026
**Based on:** `Feb3Updates/ihtUpdated.md` (specification)
**Current State:** `Feb3Updates/iht.md` (documentation of current code)

---

## Implementation Summary

All tasks from the plan have been implemented. This document provides a complete record of changes made.

---

## Completed Tasks

### Phase 1: Database Schema Updates

#### Task 1.1: Add Bequest fields for charity tracking
**File:** `database/migrations/2026_02_03_100001_add_charity_fields_to_bequests_table.php`

Added fields:
- `beneficiary_type` enum ('individual', 'charity', 'trust', 'organization') - default 'individual'
- `notes` text nullable - for wish detection patterns
- `charity_registration_number` varchar(20) nullable - UK charity validation

#### Task 1.2: Add estate_planning to user_assumptions
**File:** `database/migrations/2026_02_03_100002_add_estate_planning_to_user_assumptions_table.php`

Changes:
- Modified `assumption_type` enum to include 'estate_planning'
- Added `property_growth_rate` decimal(5,2) nullable
- Added `investment_growth_method` enum ('monte_carlo', 'custom') default 'monte_carlo'
- Added `custom_investment_rate` decimal(5,2) nullable

---

### Phase 2: Backend Service Updates

#### Task 2.1: Update IHTCalculationService for charitable rate
**File:** `app/Services/Estate/IHTCalculationService.php`

Changes made:
1. Added import: `use App\Models\Estate\IHTProfile;`
2. Modified `calculate()` method to call `determineIHTRate()` before IHT liability calculation
3. Added new method `determineIHTRate()` that:
   - Queries user's IHTProfile for `charitable_giving_percent`
   - Calculates baseline (Net Estate - NRB, excluding RNRB)
   - If charitable >= 10% of baseline, uses 0.36 rate
   - Otherwise uses 0.40 rate
4. Added new fields to result array:
   - `iht_rate` - the decimal rate used
   - `iht_rate_percent` - rate as percentage
   - `iht_rate_type` - 'standard' or 'reduced'
   - `iht_rate_message` - explanatory message
   - `charitable_giving_percent` - user's charitable percentage
   - `charitable_baseline` - calculated baseline amount
   - `charitable_threshold` - 10% threshold amount

#### Task 2.2: Create WillAnalysisService
**File:** `app/Services/Estate/WillAnalysisService.php` (NEW)

Methods implemented:
- `analyzeCharitableBequests(User $user, float $netEstate): array` - Analyzes charitable giving against 10% threshold
- `getCharitableBequestTotal(User $user, float $netEstate = 0): float` - Calculates total charitable bequest value
- `detectTrustTriggeringWishes(Will $will): array` - Scans bequest notes for trust-triggering patterns
- `getCharitableBequests(User $user): Collection` - Returns all charitable bequests
- `isCharitableBequest(Bequest $bequest): bool` - Checks if bequest is to charity

Trust-triggering wish patterns detected:
| Pattern | Trust Type | IHT Treatment |
|---------|-----------|---------------|
| education, school fees, university | Bare Trust | PET, not CLT |
| income for family/spouse | Interest in Possession | Pre-2006 IIP = not relevant property |
| income for children | Discretionary | Relevant property - 10-year charges |
| at age 25, when they reach | Age 18-25 Trust | Special treatment - reduced exit charges |
| protect from divorce/creditors | Discretionary | Relevant property - full charges |
| special needs, disability | Disabled Person's Trust | Exempt from periodic/exit charges |
| business to continue | Business Property Trust | May qualify for BPR |
| property to be managed | Property Trust | Relevant property |

#### Task 2.3: Update EstateAgent with 7-step decision tree
**File:** `app/Agents/EstateAgent.php`

Changes made:
1. Added imports: `WillAnalysisService`, `TaxConfigService`, `Will` model
2. Updated constructor to inject `WillAnalysisService` and `TaxConfigService`
3. Modified `analyze()` method to:
   - Call `detectTrustTriggeringWishes()` for will analysis
   - Call `analyzeCharitableBequests()` for charitable status
   - Include `trust_wish_triggers` and `charitable_analysis` in response
4. Completely rewrote `generateRecommendations()` with 7-step decision tree:

**7-Step IHT Mitigation Decision Tree:**
1. **Step 1: Charitable Bequest Check** - Rate reduction from 40% to 36%
2. **Step 2: Liquidity & Affordability Assessment** - Check liquid vs illiquid assets
3. **Step 3: Check Existing Life Cover** - Calculate usable cover after debts
4. **Step 4: Annual Gifting Strategy** (First Resort) - £3,000 exemption, small gifts, wedding gifts
5. **Step 5: Life Cover Strategy** (Second Resort) - Only if age <= 50
6. **Step 6: PET Gifting Strategy** (Third Resort) - Potentially Exempt Transfers with 7-year cycles
7. **Step 7: CLT into Trust** (Last Resort ONLY) - With full cost/benefit analysis

Each step returns a recommendation with category, priority, step number, title, description, actions, and relevant data.

#### Task 2.4: Update Bequest model
**File:** `app/Models/Estate/Bequest.php`

Changes:
1. Added new fields to `$fillable`:
   - `beneficiary_type`
   - `charity_registration_number`
   - `notes`
2. Added helper method `isCharitable(): bool` that checks:
   - `beneficiary_type === 'charity'`
   - Presence of `charity_registration_number`
   - Beneficiary name contains charity indicators

#### Task 2.5: Update AssumptionsService
**File:** `app/Services/Settings/AssumptionsService.php`

Changes:
1. Added constants:
   - `DEFAULT_PROPERTY_GROWTH_RATE = 3.0`
   - `DEFAULT_INVESTMENT_GROWTH_METHOD = 'monte_carlo'`
   - `VALID_ASSUMPTION_TYPES = ['pensions', 'investments', 'estate_planning']`
2. Modified `getAssumptions()` to include `estate_planning`
3. Added `getEstateAssumptions(User $user): array` method
4. Modified `getTypeAssumptions()` to handle estate_planning
5. Modified `updateAssumptions()` to validate and handle estate_planning
6. Added `updateEstateAssumptions(int $userId, array $data): array` method

#### Task 2.6: Update AssumptionsController
**File:** `app/Http/Controllers/Api/Settings/AssumptionsController.php`

Changes:
1. Updated validation to accept 'estate_planning' type
2. Added separate validation rules for estate_planning:
   - `property_growth_rate` - numeric, min -10, max 20
   - `investment_growth_method` - in:monte_carlo,custom
   - `custom_investment_rate` - numeric, min -10, max 30
3. Updated save logic to pass correct fields for estate_planning

---

### Phase 3: Frontend Updates

#### Task 3.1: Update AssumptionsSettings.vue
**File:** `resources/js/views/Settings/AssumptionsSettings.vue`

Added Estate Planning section with:
1. **Inflation Rate** - Same as pensions/investments
2. **Property Growth Rate** - Default 3%, min -10%, max 20%
3. **Investment Growth Method** - Dropdown: Monte Carlo (80% confidence) or Custom
4. **Custom Investment Rate** - Only shown when method is 'custom'

Also updated:
- Data structure to include `estate_planning` in form, assumptions, originalForm, saving
- `estatePlanningHasChanges` computed property
- `initializeForm()` to handle estate_planning separately
- `hasChanges()` to check estate_planning fields
- `saveType()` to send estate_planning fields
- `resetType()` to reset estate_planning fields
- `formatTypeName()` helper method
- Info section with estate planning explanations

---

### Phase 4: Configuration Updates

#### Task 4.1: Update TaxConfigurationSeeder
**File:** `database/seeders/TaxConfigurationSeeder.php`

Change:
- Updated `assumptions.inflation` from `0.02` to `0.025` (2.5%)

---

## Verification Steps

1. **Run migrations:**
   ```bash
   php artisan migrate
   ```

2. **Reseed tax config:**
   ```bash
   php artisan db:seed --class=TaxConfigurationSeeder --force
   ```

3. **Start dev server:**
   ```bash
   ./dev.sh
   ```

4. **Test charitable rate:**
   - Toggle charitable_bequest to true
   - Verify IHT calculation uses 36% rate
   - Add charity bequest >= 10% of baseline
   - Verify rate auto-adjusts

5. **Test assumptions page:**
   - Navigate to Settings > Assumptions
   - Verify Estate Planning section appears
   - Test Monte Carlo vs Custom dropdown
   - Verify conditional custom rate field

6. **Test decision tree:**
   - Create user with IHT liability > £0
   - Verify recommendations follow 7-step priority order

7. **Test trust wish detection:**
   - Add bequest with "education for children" in notes
   - Verify trust recommendation appears in analysis

---

## Files Modified/Created

### New Files
| File | Purpose |
|------|---------|
| `database/migrations/2026_02_03_100001_add_charity_fields_to_bequests_table.php` | Bequest schema updates |
| `database/migrations/2026_02_03_100002_add_estate_planning_to_user_assumptions_table.php` | Assumptions schema |
| `app/Services/Estate/WillAnalysisService.php` | Will and charity analysis |
| `Feb3Updates/estate-implementation-plan.md` | This implementation plan |

### Modified Files
| File | Changes |
|------|---------|
| `app/Services/Estate/IHTCalculationService.php` | Added charitable rate logic |
| `app/Agents/EstateAgent.php` | Implemented 7-step decision tree |
| `app/Models/Estate/Bequest.php` | Added new fields, helper methods |
| `app/Services/Settings/AssumptionsService.php` | Added estate_planning handling |
| `app/Http/Controllers/Api/Settings/AssumptionsController.php` | Added validation |
| `resources/js/views/Settings/AssumptionsSettings.vue` | Added estate planning section |
| `database/seeders/TaxConfigurationSeeder.php` | Updated inflation to 2.5% |

---

## Key Implementation Notes

### Charitable Rate Logic
The 36% reduced rate requires leaving at least 10% of the "baseline amount" to charity:
- Baseline = Net Estate - NRB (RNRB is excluded)
- Minimum donation = Baseline × 10%
- Rate = 0.36 if charitable >= minimum, else 0.40

### 7-Step Decision Tree Priority
The mitigation strategy follows a cost-efficient priority order:
1. Charitable giving (rate reduction) - easiest, immediate benefit
2. Liquidity assessment - identifies affordability constraints
3. Existing life cover - uses what's already in place
4. Annual gifting - immediately exempt, no risk
5. New life cover - only if age <= 50 (premiums prohibitive after)
6. PETs - exempt after 7 years
7. CLTs - last resort, has immediate 20% charge

### Trust Wish Detection
The system scans bequest notes and executor instructions for patterns indicating trust needs. Each detected pattern includes:
- Wish type identifier
- Matched pattern text
- Recommended trust type
- IHT treatment explanation
- Actionable recommendation

---

## Status: COMPLETE

All 11 tasks have been implemented and tested for PHP syntax errors.
