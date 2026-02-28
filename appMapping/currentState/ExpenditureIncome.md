# Expenditure & Income System Documentation

> Comprehensive documentation of how income and expenditure data flows across the entire Fynla application.
> Generated: 2026-02-18

---

## 1. System Overview

Income and expenditure are **not separate modules** — they are fields on the `users` table, managed through the User Profile section. There is no dedicated `Income` or `Expenditure` model for the primary data; instead:

- **Income**: 7 annual income fields stored directly on `users` table
- **Expenditure**: 20+ monthly expenditure category fields stored directly on `users` table
- **ExpenditureProfile**: A legacy/summary model (`expenditure_profiles` table) used only by IHT and Savings agents
- **No dedicated controller** for income or expenditure — both handled by `UserProfileController`

### Architecture Flow

```
                        ┌──────────────────────────┐
                        │     users table           │
                        │  (income + expenditure    │
                        │   fields directly)        │
                        └──────────┬───────────────┘
                                   │
        ┌──────────────────────────┼────────────────────────────┐
        │                          │                            │
   UserProfileController   UserProfileService          ExpenditureProfile
   (CRUD endpoints)        (tax calc, commitments)     (legacy summary)
        │                          │                            │
   ┌────┴─────┐           ┌───────┴──────┐            ┌───────┴──────┐
   │ Income   │           │ UKTax        │            │ IHT Service  │
   │ Update   │           │ Calculator   │            │ SavingsAgent │
   │ Expendit │           │ ChildBenefit │            │ EstateAsset  │
   │ Update   │           │ Service      │            │ Aggregator   │
   └──────────┘           └──────────────┘            └──────────────┘
```

---

## 2. Database Schema

### 2.1 Income Fields on `users` Table

All income is stored as **annual** amounts directly on the `users` table:

| Column | Type | Description |
|--------|------|-------------|
| `annual_employment_income` | float | PAYE salary |
| `annual_self_employment_income` | float | Self-employed earnings |
| `annual_rental_income` | float | **Auto-calculated** from BTL properties |
| `annual_dividend_income` | float | Dividend income |
| `annual_interest_income` | float | Savings interest |
| `annual_other_income` | float | Other income sources |
| `annual_trust_income` | float | Trust distributions |

**Additional income-related fields:**

| Column | Type | Description |
|--------|------|-------------|
| `occupation` | string | Job title |
| `employer` | string | Employer name |
| `industry` | string | Industry sector |
| `employment_status` | enum | `employed`, `part_time`, `self_employed`, `unemployed`, `retired`, `student`, `other` |
| `target_retirement_age` | integer | Min 30, max 100 |
| `retirement_date` | date | Specific retirement date |
| `payday_day_of_month` | integer | Day salary is received |
| `income_needs_update` | boolean | Flag when employment status changes |
| `previous_employment_status` | string | Previous status for banner display |

**Note**: `annual_pension_income` is NOT stored on the users table — it's **calculated dynamically** from DB pensions in payment + state pension if receiving.

### 2.2 Expenditure Fields on `users` Table

All expenditure is stored as **monthly** amounts on the `users` table:

| Column | Type | Description |
|--------|------|-------------|
| `monthly_expenditure` | float | Simple mode total |
| `annual_expenditure` | float | Annual equivalent |
| `expenditure_entry_mode` | enum | `simple` or `category` |
| `expenditure_sharing_mode` | enum | `joint` or `separate` |

**Category fields (all monthly, all float):**

| Essential | Communication | Lifestyle |
|-----------|--------------|-----------|
| `rent` | `mobile_phones` | `clothing_personal_care` |
| `utilities` | `internet_tv` | `entertainment_dining` |
| `food_groceries` | `subscriptions` | `holidays_travel` |
| `transport_fuel` | | `pets` |
| `healthcare_medical` | | |
| `insurance` | | |

| Children | Other |
|----------|-------|
| `childcare` | `gifts_charity` |
| `school_fees` | `regular_savings` |
| `school_lunches` | `other_expenditure` |
| `school_extras` | |
| `university_fees` | |
| `children_activities` | |

### 2.3 ExpenditureProfile Model (`expenditure_profiles` table)

A **legacy summary model** with broad categories (not the detailed per-field data):

| Column | Type |
|--------|------|
| `user_id` | FK |
| `monthly_housing` | float |
| `monthly_utilities` | float |
| `monthly_food` | float |
| `monthly_transport` | float |
| `monthly_insurance` | float |
| `monthly_loans` | float |
| `monthly_discretionary` | float |
| `total_monthly_expenditure` | float |

**Usage**: Only consumed by `IHTCalculationService` and `SavingsAgent` for backward compatibility. Written to via `UserProfileController::updateExpenditure()` — sets `total_monthly_expenditure` and zeros out the broad categories.

### 2.4 Migration for Income Update Flag

`2026_01_28_100000_add_income_needs_update_to_users_table.php`:
- Adds `income_needs_update` (boolean, default false)
- Adds `previous_employment_status` (string, nullable)

---

## 3. Backend Architecture

### 3.1 UserProfileController (`app/Http/Controllers/Api/UserProfileController.php`)

**7 endpoints** related to income/expenditure:

| Method | Route | Handler | Description |
|--------|-------|---------|-------------|
| GET | `/api/user/profile` | `getProfile()` | Returns complete profile incl. income_occupation + expenditure |
| PUT | `/api/user/profile/income-occupation` | `updateIncomeOccupation()` | Updates income + occupation fields |
| PUT | `/api/user/profile/expenditure` | `updateExpenditure()` | Updates expenditure fields |
| PUT | `/api/users/{userId}/expenditure` | `updateSpouseExpenditure()` | Updates spouse's expenditure (auth-checked) |
| GET | `/api/user/financial-commitments` | `getFinancialCommitments()` | Auto-calculated commitments |
| GET | `/api/user/spouse/financial-commitments` | `getSpouseFinancialCommitments()` | Spouse's commitments |
| GET | `/api/users/{userId}` | `getUserById()` | Spouse data access (auth-checked) |

**Cache invalidation on income update:**
- Flushes `protection` + `user_{id}` cache tags for both user AND spouse
- Reason: Protection needs calculations depend on income

**Expenditure update logic:**
```
Frontend sends: use_simple_entry → mapped to expenditure_entry_mode ('simple'/'category')
Frontend sends: use_separate_expenditure → mapped to expenditure_sharing_mode ('joint'/'separate')
```

### 3.2 UserProfileService (`app/Services/UserProfile/UserProfileService.php`)

Central service orchestrating income/expenditure calculations:

**`buildIncomeOccupation()`** — Constructs the full income payload:
1. Calculates rental income from BTL properties (ownership-aware)
2. Calculates pension income from DB pensions in payment + state pension
3. Calculates employee pension contributions from workplace DC pensions
4. Gets primary trust type for correct tax treatment
5. Calls `UKTaxCalculator::calculateDetailedNetIncome()` for per-income-type tax breakdown
6. Calls `UKTaxCalculator::calculateNetIncome()` for backward-compatible simple calculation
7. Gets expenditure breakdown (manual + financial commitments)
8. Calculates Child Benefit and HICBC via `ChildBenefitService`
9. Returns 30+ fields including disposable income

**`getExpenditureBreakdown()`** — Determines total expenditure:
- If `expenditure_entry_mode === 'category'`: sums all 20 category fields
- If simple mode: uses `monthly_expenditure` field
- Adds financial commitments total
- Returns `monthly_manual`, `monthly_commitments`, `monthly`, `annual`

**`getFinancialCommitments()`** — Auto-pulls monthly obligations from 6 sources:
1. **DC Pension Contributions** — workplace pension employee contributions
2. **Property Expenses** — mortgage + council tax + utilities + insurance + service charge + maintenance (ownership-percentage-aware)
3. **Investment Contributions** — regular contributions + planned lump sums (frequency-normalized)
4. **Savings Contributions** — regular ISA/savings contributions (frequency-normalized)
5. **Protection Premiums** — life, critical illness, income protection, disability, sickness (frequency-normalized, 5 policy types queried)
6. **Liability Payments** — non-mortgage loans (ownership-percentage-aware)

**Ownership filter** parameter: `all` (default), `joint_only`, `individual_only`

**Frequency normalization**: All non-monthly frequencies converted:
- `quarterly` → divide by 3
- `annually` → divide by 12
- Default → monthly

### 3.3 UKTaxCalculator (`app/Services/UKTaxCalculator.php`)

Calculates tax for all income types using `TaxConfigService` rates.

**`calculateDetailedNetIncome()`** parameters:
- employment, self-employment, rental, pension, trust, interest, dividend income
- Trust type (for tax treatment: discretionary, interest_in_possession, bare)
- Pension contributions (deducted before tax)
- Section 24 credit (rental mortgage interest relief)

**Tax stacking order**: Employment income consumes Personal Allowance first, then other earned income, then savings, then dividends (each at remaining band position).

Uses `TaxBandTracker` to track PA and band consumption across income sources.

### 3.4 PersonalAccountsService (`app/Services/UserProfile/PersonalAccountsService.php`)

Generates financial statements:

**Profit & Loss**: Income line items vs expense line items (mortgage, property, living expenses)
**Cashflow**: Inflows vs outflows including pension contributions
**Balance Sheet**: All assets (cash, investment, property, business, chattels, pensions) vs liabilities (mortgages, loans) — uses `CalculatesOwnershipShare` trait for joint assets

### 3.5 ChildBenefitService (`app/Services/Benefits/ChildBenefitService.php`)

Calculates UK Child Benefit entitlement and HICBC:
- Eldest child: ~£1,354.60/year
- Additional children: ~£897/year
- HICBC threshold: £60,000
- Full clawback at £80,000
- Rate: 1% per £200 over threshold

### 3.6 UpdateIncomeOccupationRequest (`app/Http/Requests/UpdateIncomeOccupationRequest.php`)

Validation rules:
- Income fields: `nullable|numeric|min:0|max:9999999999.99`
- Employment status: enum validation
- Retirement age: `integer|min:30|max:100`
- All fields use `sometimes` (partial updates allowed)

---

## 4. Frontend Architecture

### 4.1 Vue Components — Income

**`IncomeOccupation.vue`** (`resources/js/components/UserProfile/IncomeOccupation.vue`)

Two-column layout:
- **Left card**: Income form (view/edit modes)
  - 7 income types (employment, self-employment, rental, dividend, interest, pension, trust)
  - Rental income: read-only, auto-calculated from properties
  - Pension income: read-only, calculated from DB/state pensions in payment
  - Child Benefit display with HICBC warning
  - Total annual income
  - Disposable income section (net income - annual expenditure)
  - Income needs update banner (when employment status changes)
- **Right card**: Tax & NI breakdown
  - Uses `TaxIncomeCard` sub-component for per-income-type breakdowns
  - Tax year note

**On save**:
1. Preserves existing occupation fields
2. Dispatches `userProfile/updateIncomeOccupation`
3. Refreshes profile data for updated tax calculations
4. Triggers `protection/fetchProtectionData` refresh
5. Clears `income_needs_update` flag

**`IncomeStatementTab.vue`** (`resources/js/components/UserProfile/IncomeStatementTab.vue`)

Cash-based income statement:
- Monthly + Forecast Annual columns
- Income section: all income line items
- Outflows section: expenses + cashflow outflows (pension contributions)
- Cash flow before tax → estimated income tax → cash flow after tax
- Uses `PersonalAccountsService` via `calculatePersonalAccounts` API

**`TaxIncomeCard.vue`** — Per-income-type tax card showing:
- Income components (employment, rental, pension etc.)
- Tax band allocation
- NI badge (applies / doesn't apply)
- Section 24 credit display for rental income

### 4.2 Vue Components — Expenditure

**`ExpenditureOverview.vue`** — Parent container:
- Manages spouse data fetching
- Handles joint vs separate save logic
- Dispatches save for both user AND spouse if separate mode

**`ExpenditureForm.vue`** (~1500 lines) — The main form with **3 budget tabs**:

1. **Current Budget** tab:
   - View mode: collapsible grid with User / Spouse / Household columns
   - Edit mode: category cards for input
   - Entry mode toggle: Simple (single total) vs Detailed (20 categories)
   - Sharing mode toggle: Joint (50/50) vs Separate (per-person)
   - Financial Commitments section (auto-pulled, read-only)
   - Grand total = manual expenditure + financial commitments

2. **Budget at Retirement** tab:
   - Auto-adjusts current budget with retirement-specific changes
   - Removes: pension contributions, property expenses (mortgage paid off), loan repayments
   - Keeps: investment contributions, savings, protection premiums
   - Shows "Monthly Savings in Retirement" (difference from current)
   - User can override auto-adjusted values (marked as "Custom")
   - Reset button returns to auto-calculated value

3. **Budget if Widowed** tab (married users only):
   - Single-person household projection
   - Auto-adjusts for reduced costs (single occupancy)
   - Combines property expenses (survivor responsible for 100%)
   - Shows "Monthly Reduction from Current"
   - User can override auto-adjusted values

**Category fields (5 groups):**

| Group | Fields |
|-------|--------|
| Essential Living | rent, utilities, food_groceries, transport_fuel, healthcare_medical, insurance |
| Communication & Technology | mobile_phones, internet_tv, subscriptions |
| Personal & Lifestyle | clothing_personal_care, entertainment_dining, holidays_travel, pets |
| Children & Dependents | childcare, school_fees, school_lunches, school_extras, university_fees, children_activities |
| Other | gifts_charity, other_expenditure |

**Smart field filtering**: If user owns main residence, rent and utilities fields are hidden (they're captured in Property module). If user only has buy-to-let properties, utilities hint changes.

**Sub-components:**
- `ExpenditureSection.vue` — Collapsible section header with user/spouse/household totals
- `ExpenditureGridRow.vue` — Single row showing value per person + household + change indicators
- `ExpenditureExpandableGridRow.vue` — Expandable row for commitment categories (e.g. "Property Expenses" → individual properties → mortgage/council tax/utilities breakdown)
- `ExpenditureCategoryCard.vue` — Input card for edit mode with user/spouse tabs

### 4.3 Onboarding Steps

**`IncomeStep.vue`** — Employment & Income onboarding:
- Employment status, occupation (with autocomplete), employer, industry
- Retirement age
- Income fields (employment, self-employment, dividend)
- Conditional: shows employer/occupation only when employed

**`ExpenditureStep.vue`** — Household Expenditure onboarding:
- Reuses `ExpenditureForm` component (shared with profile)
- Props: `start-in-edit-mode`, `show-budget-tabs=false`, `is-onboarding=true`
- Skip modal: warns what analysis will be affected without expenditure data

### 4.4 Vuex Store — `userProfile.js`

State: `profile`, `incomeOccupation`, `personalAccounts`, `spouseAccounts`

Key actions:
- `fetchProfile()` → sets profile + income_occupation + family members
- `updateIncomeOccupation()` → computes `total_annual_income` client-side
- `updateExpenditure()` → refreshes full profile after save
- `updateSpouseExpenditure()` → saves spouse data, refreshes profile
- `calculatePersonalAccounts()` → P&L, cashflow, balance sheet (supports spouse data)

Key getter:
- `totalAnnualIncome` — computed from `incomeOccupation.total_annual_income`

**Security**: Financial data stored in memory only, never persisted to localStorage.

### 4.5 API Service — `userProfileService.js`

Endpoints:
- `getProfile()` → GET `/user/profile`
- `updateIncomeOccupation(data)` → PUT `/user/profile/income-occupation`
- `updateExpenditure(data)` → PUT `/user/profile/expenditure`
- `updateSpouseExpenditure(spouseId, data)` → PUT `/users/{spouseId}/expenditure`
- `getFinancialCommitments()` → GET `/user/financial-commitments`
- `getSpouseFinancialCommitments()` → GET `/user/spouse/financial-commitments`
- `calculatePersonalAccounts(params)` → POST `/user/personal-accounts/calculate`

---

## 5. Cross-Module Income Usage

### 5.1 Protection Module

**ProtectionAgent** (`app/Agents/ProtectionAgent.php`):
- Reads `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income` to calculate `totalAnnualIncome`
- Reads `monthly_expenditure` from protection profile

**CoverageGapAnalyzer** (`app/Services/Protection/CoverageGapAnalyzer.php`):
- Uses `annual_employment_income` for tax calculation
- Uses spouse's `annual_employment_income` for joint coverage analysis
- Calculates net income via `UKTaxCalculator`

**ScenarioBuilder** (`app/Services/Protection/ScenarioBuilder.php`):
- Uses `monthly_expenditure` for:
  - Emergency fund calculation (6 months)
  - Months of support calculation
  - Income replacement ratio
  - Death/illness/disability scenario modelling

**ComprehensiveProtectionPlanService**:
- Calculates affordability: `totalEstimatedCost / (monthly_expenditure * 0.1)`
- Uses all income fields for income replacement analysis

### 5.2 Estate Planning Module

**IHTCalculationService** (`app/Services/Estate/IHTCalculationService.php`):
- `getTotalAnnualIncome()`: Sums all 7 income fields for user + spouse
- `getCurrentAnnualExpenses()`: Uses `expenditureProfile.total_monthly_expenditure * 12`
- Fallback: 70% of combined income if no expenditure profile
- Pre-retirement: Uses current income - current expenses for cash surplus
- Post-retirement: Uses retirement income (target + state pension) - retirement expenses
- Cash surplus feeds into cash account projections for IHT calculations

**EstateAssetAggregatorService**:
- Returns expenditure data from ExpenditureProfile or ProtectionProfile as fallback

**GiftingStrategyOptimizer**:
- Checks `annual_employment_income` + `annual_self_employment_income` for regular gift capacity
- Uses income to calculate affordable annual gifting

**ComprehensiveEstatePlanService**:
- Includes `monthly_expenditure` and `annual_income` in user/spouse/household data packages

### 5.3 Savings Module

**SavingsAgent** (`app/Agents/SavingsAgent.php`):
- Cascading expenditure source:
  1. `ExpenditureProfile.total_monthly_expenditure`
  2. `User.monthly_expenditure`
  3. `User.annual_expenditure / 12`
- Uses `monthly_expenditure` for:
  - Emergency fund target (3-6 months of expenditure)
  - Monthly top-up calculation for shortfall

### 5.4 Retirement Module

**Retirement Income components** (`resources/js/components/Retirement/`):
- `IncomeDrawdownChart.vue` — ApexCharts bar chart showing yearly income sources by age
- `IncomeProjectionChart.vue` — ApexCharts area chart showing income projection to life expectancy
- `IncomeSourceSlider.vue` — Interactive slider for drawdown allocation

**Retirement Income API**:
- GET `/api/retirement/income` — current retirement income analysis
- POST `/api/retirement/income/calculate` — tax-optimized drawdown calculation
- GET `/api/retirement/income/accounts` — eligible drawdown accounts

### 5.5 Investment Module

Income impacts tax efficiency calculations:
- `TaxEfficiencyCalculator` — uses income level to determine marginal tax rate
- `TaxDragCalculator` — calculates tax drag based on income position in bands
- `TaxAwareRebalancer` — considers income for CGT-aware rebalancing

### 5.6 Coordinating Agent

Uses `monthly_income` as a baseline metric in coordinated analysis.

---

## 6. Spouse Handling

### 6.1 Income

- Each spouse has their own income fields on their own `users` record
- Income is displayed side-by-side in components when spouse is linked
- Cache invalidation on income change clears BOTH user and spouse protection cache
- IHT calculations sum both users' income when `dataSharingEnabled`

### 6.2 Expenditure

**Two sharing modes:**

1. **Joint mode** (`expenditure_sharing_mode = 'joint'`):
   - Single set of expenditure values entered
   - Assumed to be split 50/50
   - Displayed as household total

2. **Separate mode** (`expenditure_sharing_mode = 'separate'`):
   - Each spouse has their own expenditure values
   - User/Spouse tabs in edit mode
   - Three columns in view mode: User / Spouse / Household
   - Saves separately via `updateExpenditure` and `updateSpouseExpenditure`

**Spouse expenditure update**:
- Route: `PUT /api/users/{userId}/expenditure`
- Auth check: `currentUser.spouse_id === userId`
- Validates same fields as user expenditure (minus `use_separate_expenditure`)

### 6.3 Financial Commitments

- Fetched separately for user and spouse via different API endpoints
- Joint assets appear in both users' commitments (with ownership percentage split)
- View mode merges items by ID, showing user amount + spouse amount + household total
- Property breakdown shows ownership percentage for joint properties

### 6.4 Widowed Budget

- Specific to married users
- Projects single-person household expenses
- Combines all household property expenses (survivor takes 100%)
- Removes spouse-specific commitments
- Auto-adjusts costs (e.g., reduced food, transport)

---

## 7. Rental Income Auto-Calculation

Rental income is **never manually entered** — it's calculated from BTL properties:

```
UserProfileService::calculateAnnualRentalIncome()
  → Query BTL properties (user_id OR joint_owner_id)
  → For each property:
    → PropertyService::calculateTaxPosition(property, userId)
    → Returns: annual_taxable_income, section_24_annual_credit, ownership_percentage
  → Sum totals
  → Override users.annual_rental_income with calculated total
```

This runs on every `updateIncomeOccupation()` call, ensuring rental income stays in sync with property data.

---

## 8. Tax Integration

### 8.1 Income Tax Calculation Pipeline

```
UserProfileService::buildIncomeOccupation()
  │
  ├─ calculateAnnualRentalIncome() → rental + section 24 credit
  ├─ calculateAnnualPensionIncome() → DB pensions + state pension
  ├─ calculateAnnualPensionContributions() → workplace DC pensions
  │
  └─ UKTaxCalculator::calculateDetailedNetIncome()
       │
       ├─ TaxBandTracker (stacking allocation)
       │   ├─ Earned income → PA → 20% → 40% → 45%
       │   ├─ Interest income → PSA → remaining bands (0%/20%/40%)
       │   ├─ Dividend income → dividend allowance → 8.75%/33.75%/39.35%
       │   └─ Trust income → per trust type treatment
       │
       ├─ Class 1 NI (employment)
       ├─ Class 4 NI (self-employment)
       └─ Section 24 tax credit application
```

### 8.2 Tax-Affected Calculations

| Calculation | How Income/Tax Affects It |
|-------------|--------------------------|
| Net income | Gross - tax - NI - pension contributions |
| Disposable income | Net income - annual expenditure |
| HICBC | Triggered at £60k+ income |
| Protection affordability | Premiums as % of expenditure |
| Emergency fund target | 3-6 months of expenditure |
| IHT cash projections | Income - expenses = annual surplus |
| Gifting capacity | Based on income surplus |

---

## 9. Profile Completeness

`ProfileCompletenessChecker` tracks income and expenditure as separate sections:

**Income check** (`hasIncome()`):
```php
return ($user->annual_employment_income ?? 0) > 0
    || ($user->annual_self_employment_income ?? 0) > 0
    || ($user->annual_rental_income ?? 0) > 0
    || ($user->annual_dividend_income ?? 0) > 0
    || ($user->annual_other_income ?? 0) > 0;
```

**Expenditure check** (`hasExpenditure()`):
```php
return ($user->monthly_expenditure ?? 0) > 0
    || ($user->annual_expenditure ?? 0) > 0;
```

**Note**: Interest income and trust income are NOT checked for profile completeness — only the 5 main income types.

---

## 10. Employment Status Change Flow

When employment status changes:

1. **Frontend** detects change in personal info form
2. Sets `income_needs_update = true` and `previous_employment_status = oldValue`
3. `IncomeOccupation.vue` shows blue banner: "Employment Status Changed"
4. User clicks "Update income now" → enters edit mode
5. On save, clears: `income_needs_update = false`, `previous_employment_status = null`

**Status options**: employed, part_time, self_employed, unemployed, retired, student, other

---

## 11. Data Flow Diagrams

### Income Save Flow

```
User edits income in IncomeOccupation.vue
  │
  ├─ Preserves occupation fields (job title, employer, industry)
  ├─ Sends all income fields via updateIncomeOccupation action
  │
  └─ PUT /api/user/profile/income-occupation
       │
       ├─ UserProfileService::updateIncomeOccupation()
       │   ├─ calculateAnnualRentalIncome() → overrides rental_income
       │   └─ $user->update($data)
       │
       ├─ Flush protection cache (user + spouse)
       │
       └─ Response: updated user object
            │
            ├─ Vuex: setIncomeOccupation (with computed total)
            ├─ Vuex: auth/setUser (sync user object)
            ├─ Dispatch: userProfile/fetchProfile (refresh tax calc)
            └─ Dispatch: protection/fetchProtectionData (refresh)
```

### Expenditure Save Flow

```
User edits expenditure in ExpenditureForm.vue
  │
  ├─ Maps frontend fields to DB columns:
  │   use_simple_entry → expenditure_entry_mode
  │   use_separate_expenditure → expenditure_sharing_mode
  │
  ├─ Joint mode: emits single formData
  │  OR
  ├─ Separate mode: emits { userData, spouseData }
  │
  └─ ExpenditureOverview::handleSave()
       │
       ├─ dispatch('userProfile/updateExpenditure', userData)
       │   → PUT /api/user/profile/expenditure
       │     → $user->update($updateData)
       │     → ExpenditureProfile::updateOrCreate (legacy sync)
       │
       ├─ If separate + spouse:
       │   dispatch('userProfile/updateSpouseExpenditure', { spouseId, data })
       │   → PUT /api/users/{spouseId}/expenditure
       │     → Auth check: currentUser.spouse_id === spouseId
       │     → $spouse->update($updateData)
       │     → ExpenditureProfile::updateOrCreate (spouse)
       │
       └─ Refresh: auth/fetchUser + userProfile/fetchProfile + fetchSpouseData
```

---

## 12. Vulnerabilities & Issues

### Critical

1. **ExpenditureProfile is semi-orphaned**: `updateExpenditure()` creates/updates ExpenditureProfile with `total_monthly_expenditure` but zeros out all broad category fields. IHT and Savings agents read from this model. If user uses category mode, the detailed breakdown is lost in the ExpenditureProfile (only total is synced).

2. **Spouse expenditure route missing permission check**: `updateSpouseExpenditure` only checks `spouse_id === userId` but doesn't verify `hasAcceptedSpousePermission()`. Any linked spouse can modify the other's expenditure.

3. **No validation on `expenditure_entry_mode`/`expenditure_sharing_mode`**: These are mapped from boolean frontend values but the DB columns accept enum values. No explicit enum validation in the controller.

### High

4. **Rental income overwrite on every income save**: `updateIncomeOccupation()` always recalculates and overwrites `annual_rental_income`. If PropertyService has an error, rental income could be incorrectly zeroed.

5. **Interest income not included in profile completeness**: `hasIncome()` checks 5 income types but omits `annual_interest_income` and `annual_trust_income`.

6. **Pension income not displayed in onboarding**: The `IncomeStep.vue` only collects employment/self-employment/dividend income, not pension/trust/interest. Users must navigate to profile to add these.

7. **ExpenditureProfile redundancy**: Two parallel storage locations for expenditure data (users table categories + expenditure_profiles total). Category-level data only exists on users table.

### Medium

8. **IncomeStatementTab calculates tax client-side**: The Income Statement component has its own local tax calculation using hardcoded UK tax bands (2025/26), not via the server's `TaxConfigService`. These could drift.

9. **PersonalAccountsService uses simple expenditure only**: `calculateProfitAndLoss()` uses `$user->monthly_expenditure * 12` for living expenses, ignoring category mode.

10. **Financial commitments not cached**: `getFinancialCommitments()` queries 6+ tables on every call (DC pensions, properties, investments, savings, 5 protection policy types, liabilities). No caching.

11. **Spouse financial commitments endpoint lacks permission check**: `getSpouseFinancialCommitments()` only checks for spouse existence, not data sharing permission.

12. **Category sum could diverge from saved total**: In category mode, total is computed from field sum. If individual fields are updated but monthly_expenditure isn't re-synced, the simple total and category sum could diverge.

---

## 13. Improvement Recommendations

1. **Consolidate expenditure storage**: Eliminate ExpenditureProfile model, have IHT/Savings agents read directly from users table (category sum or monthly_expenditure based on entry mode).

2. **Add data sharing permission checks**: Spouse expenditure and financial commitment endpoints should verify `hasAcceptedSpousePermission()`.

3. **Cache financial commitments**: Add short-lived cache (5 min) for `getFinancialCommitments()` to avoid 6+ table queries per page load. Invalidate on relevant data changes.

4. **Server-side tax in IncomeStatement**: Replace client-side tax calculation with API call to `UKTaxCalculator` for consistency.

5. **Add `annual_interest_income` and `annual_trust_income` to completeness check**: These are legitimate income sources that should count.

6. **Expand onboarding income collection**: Include interest, trust, and pension income fields in IncomeStep.

7. **Add enum validation**: Explicitly validate `expenditure_entry_mode` and `expenditure_sharing_mode` in controller.

8. **Guard rental income override**: Only overwrite `annual_rental_income` if calculation succeeds, preserve existing value on error.

9. **Sync PersonalAccountsService with entry mode**: Use `getExpenditureBreakdown()` instead of raw `monthly_expenditure` field.

10. **Add expenditure history tracking**: Currently no audit trail for expenditure changes. Consider adding timestamps or versioning for financial planning accuracy.

---

## 14. Complete File Reference Index

### Backend

| File | Role |
|------|------|
| `app/Http/Controllers/Api/UserProfileController.php` | All income/expenditure CRUD endpoints |
| `app/Http/Requests/UpdateIncomeOccupationRequest.php` | Income validation rules |
| `app/Services/UserProfile/UserProfileService.php` | Core service: tax calc, commitments, profile building |
| `app/Services/UserProfile/PersonalAccountsService.php` | P&L, cashflow, balance sheet |
| `app/Services/UserProfile/ProfileCompletenessChecker.php` | Income/expenditure completeness checks |
| `app/Services/UKTaxCalculator.php` | Detailed tax + NI calculation |
| `app/Services/TaxBandTracker.php` | Tax band consumption tracking |
| `app/Services/TaxConfigService.php` | UK tax rates from DB |
| `app/Services/Benefits/ChildBenefitService.php` | Child Benefit + HICBC |
| `app/Models/ExpenditureProfile.php` | Legacy expenditure summary model |
| `app/Models/User.php` | Income + expenditure fields, casts, relationships |
| `app/Agents/ProtectionAgent.php` | Uses income + expenditure |
| `app/Agents/SavingsAgent.php` | Uses expenditure for emergency fund |
| `app/Services/Protection/CoverageGapAnalyzer.php` | Income for coverage analysis |
| `app/Services/Protection/ScenarioBuilder.php` | Expenditure for scenarios |
| `app/Services/Protection/ComprehensiveProtectionPlanService.php` | Full protection + income analysis |
| `app/Services/Estate/IHTCalculationService.php` | Income/expenditure for cash projections |
| `app/Services/Estate/EstateAssetAggregatorService.php` | Expenditure data aggregation |
| `app/Services/Estate/GiftingStrategyOptimizer.php` | Income for gifting capacity |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | Income/expenditure in estate plan |
| `database/migrations/2026_01_28_100000_add_income_needs_update_to_users_table.php` | Income update flag migration |

### Frontend

| File | Role |
|------|------|
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Income edit/view + tax display |
| `resources/js/components/UserProfile/IncomeStatementTab.vue` | P&L / cashflow statement |
| `resources/js/components/UserProfile/TaxIncomeCard.vue` | Per-income-type tax breakdown |
| `resources/js/components/UserProfile/ExpenditureOverview.vue` | Expenditure parent container |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Main expenditure form (3 budget tabs) |
| `resources/js/components/UserProfile/ExpenditureSection.vue` | Collapsible section header |
| `resources/js/components/UserProfile/ExpenditureGridRow.vue` | Single data row |
| `resources/js/components/UserProfile/ExpenditureExpandableGridRow.vue` | Expandable commitment row |
| `resources/js/components/UserProfile/ExpenditureCategoryCard.vue` | Category input card |
| `resources/js/components/Onboarding/steps/IncomeStep.vue` | Onboarding income collection |
| `resources/js/components/Onboarding/steps/ExpenditureStep.vue` | Onboarding expenditure collection |
| `resources/js/components/Retirement/IncomeDrawdownChart.vue` | Retirement income bar chart |
| `resources/js/components/Retirement/IncomeProjectionChart.vue` | Retirement income area chart |
| `resources/js/components/Retirement/IncomeSourceSlider.vue` | Drawdown allocation slider |
| `resources/js/store/modules/userProfile.js` | Vuex store for income/expenditure state |
| `resources/js/services/userProfileService.js` | API service wrapper |
