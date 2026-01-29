# Deployment Notes - January 29, 2026

---

## ISA Account Forms - Simplified UI

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Simplified the ISA subscription section in both Cash ISA (Savings) and Stocks & Shares ISA (Investment) add/edit forms by removing the ISA Type dropdown and improving helper text clarity.

### Changes Made

| Change | Description |
|--------|-------------|
| Removed ISA Type dropdown | ISA Type selector removed from blue ISA Subscription box (both forms) |
| Already Subscribed helper text | Added "This includes regular contributions." to clarify what the subscription amount covers |
| Regular Contribution helper text | Changed to "As of {date}, you have {no} contributions remaining for the {tax year} tax year." |
| Added `todaysDate` computed property | Displays current date in UK format (e.g., "29 January 2026") |
| Added `paymentsMadeThisTaxYear` computed | Calculates number of regular payments made since April 6 |
| Added `paymentsRemainingThisTaxYear` computed | Calculates remaining payments for the tax year |
| Fixed ISA allowance calculation | "Planned" contributions now only count remaining payments for rest of tax year, avoiding double-counting with "Already Subscribed" |

### ISA Allowance Calculation Logic

The "Planned" amount in the ISA Allowance tracker now correctly calculates remaining contributions:

1. **Months elapsed** = Current date - April 6 (start of tax year)
2. **Payments made** = Based on contribution frequency:
   - Monthly: months elapsed
   - Quarterly: months elapsed ÷ 3
   - Annually: 1 if 12+ months elapsed, else 0
3. **Payments remaining** = Total payments per year - payments made
4. **Planned amount** = (Payments remaining × contribution amount) + planned lump sum

**Example (January 29, 2026):**
- Tax year started April 6, 2025 → 9 months elapsed
- Monthly £500 contribution → 9 payments already made (included in "Already Subscribed")
- Remaining: 12 - 9 = 3 payments × £500 = £1,500 planned
- Plus any lump sum = total "Planned" amount

### Files Changed (2 files - Included in Build)

**Savings Module:**
```text
resources/js/components/Savings/SaveAccountModal.vue
```

**Investment Module:**
```text
resources/js/components/Investment/AccountForm.vue
```

---

## Expenditure Form - UI Improvements

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Redesigned the expenditure form with improved layout, new segmented control toggles, and separate tabs for user/spouse entry. This applies to both onboarding and the Valuable Info edit mode.

### Changes Made

| Change | Description |
|--------|-------------|
| Inline options cards | "Spouse Expenditure" and "Entry Method" cards now display side by side |
| Improved spacing | Added consistent gaps between info boxes and options cards |
| Segmented control toggles | Replaced checkbox with "Joint/Separate" toggle for spouse expenditure |
| Entry method toggle | Replaced buttons with "Detailed/Simple" segmented control |
| Green active state | Toggle buttons use green background on white container when active |
| Person tabs | When "Separate" is selected, shows tabs for user and spouse instead of side-by-side inputs |
| Tab displays name | Each tab shows the person's name (or linked account name if available) |
| Single input per field | Only one input field shown at a time based on active tab selection |

### Files Changed (1 file - Included in Build)

**User Profile Module:**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

---

## Private Company & Crowdfunding Investments

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Added Private Company and Crowdfunding investment types to the Investment module with comprehensive tracking for company details, investment terms, UK tax relief (EIS/SEIS/SITR/VCT), ownership structure, and exit tracking.

### Changes Made

| Change | Description |
|--------|-------------|
| New account types | Added "Private Company" and "Crowdfunding Investment" to account type dropdown |
| Company details section | Legal name, trading name, registration number, sector, website, crowdfunding platform |
| Investment details section | Investment date, amount, funding round, instrument type, shares, valuations |
| Ownership & legal section | Share class, voting/dividend rights, nominee structure, anti-dilution |
| UK tax relief section | EIS/SEIS/SITR/VCT tracking, certificate numbers, HMRC reference, relief claimed |
| Status & valuation section | Company status, latest valuation, ownership percentage |
| Exit details section | Exit type, date, proceeds, fees, loss relief tracking |
| Auto-calculated fields | Disposal restriction date (3 years from investment for EIS/SEIS) |

### Database Migration

New migration: `2026_01_29_082107_add_private_investment_fields_to_investment_accounts_table.php`

Adds 40+ new columns to `investment_accounts` table for private investment tracking.

### Files Changed (4 files)

**Database:**
```text
database/migrations/2026_01_29_082107_add_private_investment_fields_to_investment_accounts_table.php
```

**Backend:**
```text
app/Models/Investment/InvestmentAccount.php
app/Http/Controllers/Api/InvestmentController.php
```

**Frontend:**
```text
resources/js/components/Investment/AccountForm.vue
```

---

## Validation Fix - Private Investments & Employee Share Schemes

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Fixed validation errors when creating Private Company, Crowdfunding, and Employee Share Scheme accounts. Two issues fixed:

1. **Backend:** The `current_value` field was required for all account types, but these new account types don't display that field in the form.
2. **Frontend:** The `provider` field validation was required unconditionally, but this field is hidden for private investments and employee share schemes.

### Changes Made

| Change | Description |
|--------|-------------|
| `current_value` validation (backend) | Changed from `required` to `required_unless` for private investments and employee share schemes |
| `provider` validation (frontend) | Added conditional validation - only required when not a private investment or employee share scheme |

### Files Changed (2 files)

**Backend:**
```text
app/Http/Controllers/Api/InvestmentController.php
```

**Frontend:**
```text
resources/js/components/Investment/AccountForm.vue
```

---

## Employee Share Scheme Tracking

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Added comprehensive UK employee share scheme tracking to the Investment module with support for 5 scheme types: SAYE (Sharesave), CSOP (Company Share Option Plan), EMI (Enterprise Management Incentives), Unapproved Options, and RSUs (Restricted Stock Units).

### New Account Types

| Type | Description | Tax Treatment |
|------|-------------|---------------|
| `saye` | SAYE / Sharesave | Tax-advantaged (no IT/NIC on exercise) |
| `csop` | Company Share Option Plan | Tax-advantaged (3-10 year window) |
| `emi` | Enterprise Management Incentives | Tax-advantaged (startup options) |
| `unapproved_options` | Non-tax-advantaged Options | IT/NIC on exercise gain |
| `rsu` | RSUs (Restricted Stock Units) | IT/NIC at vesting |

### Changes Made

| Change | Description |
|--------|-------------|
| 5 new account types | SAYE, CSOP, EMI, Unapproved Options, RSU added to account type dropdown |
| Employer details section | Employer name, registration, ticker, listed status, parent company, ERS reference |
| Grant details section | Grant date, reference, units granted, exercise price, market value at grant |
| SAYE-specific section | Monthly savings (max £500), savings balance, contract duration (3/5 years), maturity date |
| Vesting schedule section | Vesting type (cliff/monthly/quarterly/annual/performance), dates, performance conditions |
| Current status section | Units vested/unvested/exercised/forfeited/expired, scheme status, current share price |
| Exercise & expiry section | Exercise window dates, proceeds, cost (options only) |
| Tax treatment section | Tax-advantaged/unapproved, RCA status, PAYE via payroll, CSOP 3-year date |
| Leaver terms section | Leaver category, post-termination exercise period, termination date |
| Auto-calculated fields | CSOP 3-year date, SAYE maturity date, intrinsic value, unvested value |
| Model helper methods | `isEmployeeShareScheme()`, `isOptionsScheme()`, `isTaxAdvantagedScheme()`, `isInCsopTaxAdvantageWindow()` |

### Database Migration

New migration: `2026_01_29_140000_add_employee_share_scheme_fields_to_investment_accounts_table.php`

Adds 61 new columns to `investment_accounts` table organised into 8 groups:
- Group 1: Employer Details (8 columns)
- Group 2: Grant Details (10 columns)
- Group 3: Vesting Schedule (12 columns)
- Group 4: Current Status (8 columns)
- Group 5: Exercise & Expiry (6 columns)
- Group 6: Tax Treatment (8 columns)
- Group 7: SAYE-Specific (5 columns)
- Group 8: Leaver Terms (4 columns)

### Files Changed (4 files)

**Database:**
```text
database/migrations/2026_01_29_140000_add_employee_share_scheme_fields_to_investment_accounts_table.php
```

**Backend:**
```text
app/Models/Investment/InvestmentAccount.php
app/Http/Controllers/Api/InvestmentController.php
```

**Frontend:**
```text
resources/js/components/Investment/AccountForm.vue
resources/js/components/NetWorth/InvestmentList.vue
```

---

## Form UI Improvements

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Added red asterisks (*) to all required fields in investment account forms, and updated the investment dashboard cards to display company/employer names for new account types.

### Changes Made

| Change | Description |
|--------|-------------|
| Required field asterisks | Added red asterisks to: Account Type, Specify Account Type (other), Provider, Current Value |
| Hide fields for new types | Current Value field now hidden for private investments and employee share schemes |
| Dashboard card display | Cards now show company name (private investments) or employer name (share schemes) instead of provider |
| Value display | Cards show "Latest Valuation" or "Intrinsic Value" with appropriate calculated values |
| Hide risk badge | Risk badge hidden for VCT, EIS, Private Company, Crowdfunding, SAYE, CSOP, EMI, Unapproved Options, RSUs, Other |
| Rose badge | Private Company and Crowdfunding use rose-colored badge |
| Teal badge | Employee share schemes (SAYE, CSOP, EMI, Unapproved, RSUs) use teal-colored badge |
| Slate badge | "Other" account type uses slate instead of gray |

### Files Changed (2 files)

**Frontend:**
```text
resources/js/components/Investment/AccountForm.vue
resources/js/components/NetWorth/InvestmentList.vue
```

---

## Amber → Orange Color Replacement

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Replaced all instances of the amber color (`amber-*`) with orange (`orange-*`) throughout the entire application. This includes Tailwind CSS classes, hex color codes, and status level names.

### Changes Made

| Change | Description |
|--------|-------------|
| CSS files | Updated `app.css` and `badges.css` to use orange classes |
| Tailwind config | Updated safelist and warning colors to use orange |
| Vue components | Replaced amber with orange in 140+ component files |
| Vue views | Updated 13 view files |
| PHP services | Updated `RiskPreferenceService`, `GoalProgressAnalyzer`, `AdequacyScorer` |
| JS services | Updated `riskService.js` |
| Test files | Updated 14 test files |
| Agent configs | Updated `premium-ui-designer.md` |
| Design docs | Updated `designStyle.md` and `CLAUDE.md` |

### Color Mapping

| Old (Amber) | New (Orange) |
|-------------|--------------|
| `amber-50` | `orange-50` |
| `amber-100` | `orange-100` |
| `amber-200` | `orange-200` |
| `amber-500` | `orange-500` |
| `amber-600` | `orange-600` |
| `amber-700` | `orange-700` |
| `amber-800` | `orange-800` |
| `#F59E0B` | `#F97316` |
| `#D97706` | `#EA580C` |

### Files Changed (150+ files)

Key files include:
```text
resources/css/app.css
resources/css/badges.css
tailwind.config.js
resources/js/services/riskService.js
app/Services/Risk/RiskPreferenceService.php
app/Services/Investment/Goals/GoalProgressAnalyzer.php
app/Services/Protection/AdequacyScorer.php
.claude/agents/premium-ui-designer.md
designStyle.md
CLAUDE.md
```

Plus 140+ Vue component and view files, and 14 test files.

---

## Bug Fixes - Employee Share Schemes & Form Reset

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Fixed two bugs in the Investment module:

1. **Value display bug**: Employee share scheme cards showed "£0" instead of calculated value
2. **Form data retention bug**: When opening Add Account form after previously adding an account, old data persisted

### Changes Made

| Change | Description |
|--------|-------------|
| `getDisplayValue()` method | Calculates value from form inputs (units × price) |
| `getValueLabel()` method | Returns appropriate label based on account type |
| Form reset on open | Added `resetForm()` call in `show` watcher when opening in add mode |

### Value Calculation

| Account Type | Calculation | Label |
|--------------|-------------|-------|
| SAYE, CSOP, EMI, Unapproved | `units_granted × exercise_price` | Exercise Value |
| RSUs | `units_granted × market_value_at_grant` | Grant Value |
| Private Co, Crowdfunding | `latest_valuation` or `investment_amount` | Valuation |
| Standard accounts | `current_value` | Current Value |

### Files Changed (2 files)

**Frontend:**
```text
resources/js/components/Investment/AccountForm.vue
resources/js/components/NetWorth/InvestmentList.vue
```

---

## Account-Type-Specific Detail Views

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Created separate detail panel components for Employee Share Schemes and Private Investments that display relevant information for each account type, replacing the generic tabbed view (Holdings, Performance, Fees, etc.) which doesn't apply to these account types.

### Routing Logic

| Account Types | Detail Component |
|---------------|------------------|
| `saye`, `csop`, `emi`, `unapproved_options`, `rsu` | EmployeeShareSchemeDetail |
| `private_company`, `crowdfunding` | PrivateInvestmentDetail |
| `isa`, `gia`, `sipp`, `pension`, `nsi`, `vct`, `eis`, bonds | Existing tabbed view |

### EmployeeShareSchemeDetail.vue

**Sections:**
1. **Key Metrics Header** (4 cards) - Exercise/Grant Value, Vested Value, Vesting Progress %, Days to Exercise Window
2. **Employer Details** - Employer name, ticker, listed status, ERS reference
3. **Grant Details** - Grant date, units granted, exercise price, market value at grant
4. **Vesting Schedule** - Vesting type, progress bar, cliff date, full vest date
5. **Current Status** - Scheme status, units vested/unvested/exercised, current share price
6. **Exercise & Expiry** (options only) - Window dates, exercise window status alert
7. **Tax Treatment** - Tax-advantaged badge, CSOP 3-year date, PAYE info
8. **SAYE Savings** (SAYE only) - Monthly savings, balance, maturity date
9. **Leaver Terms** - Leaver category, post-termination exercise period

### PrivateInvestmentDetail.vue

**Sections:**
1. **Key Metrics Header** (4 cards) - Latest Valuation, Return Multiple (MOIC), Tax Relief Status, Disposal Countdown
2. **Company Details** - Legal name, trading name, registration, sector, website, platform
3. **Investment Details** - Date, amount, funding round, instrument type, shares, valuations
4. **Ownership & Legal** - Share class, voting/dividend rights, holding structure
5. **UK Tax Relief** (if applicable) - Relief type, certificate, holding period countdown, clawback warning
6. **Status & Valuation** - Company status, latest valuation, MOIC, unrealised gain/loss
7. **Exit Details** (if exited) - Exit type, date, proceeds, fees, exit MOIC

### Files Created (2 files)

```text
resources/js/views/Investment/EmployeeShareSchemeDetail.vue
resources/js/views/Investment/PrivateInvestmentDetail.vue
```

### Files Changed (1 file)

```text
resources/js/components/NetWorth/InvestmentDetailInline.vue
```

---

## Risk Level Flexibility - Remove One-Step Restriction

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Removed the restriction that limited users to only adjusting their product risk level by one step from their main profile. Users can now choose any risk level for their investments and pensions.

### Before

- Users could only select adjacent risk levels (e.g., if main profile is "Medium", only "Lower-Medium", "Medium", and "Upper-Medium" were available)
- Helper text: "You can adjust this account within one level of your main preference"

### After

- Users can select any of the 5 risk levels for any product
- Helper text: "You can choose a different risk level for this account if needed"

### Files Changed (5 files)

**Backend:**
```text
app/Services/Risk/RiskPreferenceService.php
```

**Frontend:**
```text
resources/js/components/Investment/AccountForm.vue
resources/js/components/Retirement/DCPensionForm.vue
resources/js/views/Risk/RiskProfilePage.vue
resources/js/components/Risk/RiskProfileSummary.vue
```

---

## Move Charitable Bequest to Estate Planning

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Moved the charitable bequest toggle from the onboarding Family Info step to the Estate Planning IHT Planning dashboard. This makes more sense contextually as charitable bequests directly relate to inheritance tax planning.

### Before

- Charitable bequest toggle was in the Family & Dependents step of onboarding
- Users had to complete onboarding to set this preference

### After

- Charitable bequest toggle is now a card in the "IHT Mitigation Strategies" section of Estate Planning
- Users can toggle this at any time from the Estate Planning module
- Shows potential IHT savings when enabled (rate reduces from 40% to 36%)
- Value saved via the existing user profile API

### Changes Made

| Change | Description |
|--------|-------------|
| New strategy card | Added "Charitable Bequest" card to IHT Planning strategies section with Yes/No radio toggle |
| IHT savings display | Shows potential savings calculation when "Yes" is selected |
| Loading indicator | Spinner shown while saving preference |
| Auto-save | Changes saved immediately on toggle via API |
| Removed from onboarding | Charitable bequest toggle removed from FamilyInfoStep.vue |

### API Changes

Added `charitable_bequest` to `UpdatePersonalInfoRequest` validation rules to allow updating via the `/user/profile/personal` endpoint.

### Files Changed (4 files)

**Backend (upload separately):**
```text
app/Http/Requests/UpdatePersonalInfoRequest.php
```

**Frontend (included in build):**
```text
resources/js/services/userProfileService.js
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Onboarding/steps/FamilyInfoStep.vue
```

---

## Auto-Fill Property Address from User Profile

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

When adding a new property and selecting "Main Residence" as the property type, the address fields are now automatically populated from the user's profile address (entered during onboarding).

### Behaviour

- Only triggers when adding a **new** property (not when editing)
- Only triggers when property type is "Main Residence"
- Only fills if address fields are currently empty
- Only fills if user has address data in their profile

### Implementation

The PropertyForm component already had the auto-fill logic, but the `userAddress` prop wasn't being passed from PropertyList.vue. Now:

1. PropertyList.vue computes `userAddress` from the auth store
2. Passes `userAddress` prop to PropertyForm
3. PropertyForm watcher triggers auto-fill when main_residence is selected

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/NetWorth/PropertyList.vue
```

*Note: PropertyForm.vue already had the auto-fill logic. AssetsStep.vue (onboarding) was already passing userAddress correctly.*

---

## British Spelling Fix - Optimise

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Fixed American spelling "Optimize" to British spelling "Optimise" in protection recommendation text displayed on the dashboard.

### Changes Made

| Before | After |
|--------|-------|
| Policy Optimization | Policy Optimisation |
| Review and optimize existing policies | Review and optimise existing policies |
| Optimized Protection Strategy | Optimised Protection Strategy |

### Files Changed (2 files)

**Backend (upload separately):**
```text
app/Services/Protection/RecommendationEngine.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
```

---

## Onboarding Progress Indicator Redesign

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Replaced the basic progress bar in onboarding with a step-based progress indicator similar to the Add Property form. Shows all sections with visual indicators for completed, skipped, current, and pending steps.

### Before

- Simple progress bar with percentage
- "Step X of Y" text
- No visibility into which steps were completed or skipped

### After

- Circle indicators for each step with step number or icon
- Short labels below each circle
- Connecting lines between steps
- **Green** circles with checkmark for completed steps
- **Orange** circles with X for skipped steps
- **Blue** circles for current step
- **Gray** circles for pending steps
- Horizontally scrollable on mobile

### Step Labels

| Internal Name | Display Label |
|---------------|---------------|
| personal_info | Personal |
| family_info | Family |
| domicile_info | Domicile |
| income | Income |
| expenditure | Expenses |
| assets | Assets |
| liabilities | Debts |
| protection_policies | Protection |
| will_info | Will |
| trust_info | Trusts |
| completion | Complete |

### State Transitions

- When a step is skipped, it's added to `skippedSteps` array and shows orange X
- When user goes back and completes a previously skipped step, it's removed from `skippedSteps` and shows green tick
- The `saveStepData` action now calls `REMOVE_SKIPPED_STEP` mutation to handle this transition

### Files Changed (2 files)

**Frontend (included in build):**
```text
resources/js/components/Onboarding/OnboardingWizard.vue
resources/js/store/modules/onboarding.js
```

---

## Risk Factor Detail Views - Remove Explanation Cards

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Removed the "What, Why and How" explanation cards from the bottom of all risk factor detail views in Valuable Info > Risk tab. These cards were redundant as the information is already conveyed in the threshold levels.

### Before

- Each risk factor detail page had a card at the bottom with "What", "Why", and "How" explanations
- For capacity_for_loss, included formula in "How" section
- For other factors, included "What" and "Why" sections

### After

- Explanation cards removed from all risk factor detail views
- Pages now end with the "Threshold Levels" card

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/views/Risk/RiskFactorDetailPage.vue
```

---

## Persona Name Change - Young Saver

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Renamed the young_saver persona from "Alex Morgan" to "John Morgan" to avoid confusion with the entrepreneur persona "Alex Chen".

### Changes Made

| Before | After |
|--------|-------|
| Alex Morgan | John Morgan |
| alex.morgan@example.com | john.morgan@example.com |
| Alex's Current Account | John's Current Account |

### Seeder Update

The PreviewUserSeeder was also updated to **delete and recreate** existing preview users instead of skipping them. This ensures persona data changes are reflected in the database when re-seeding.

After deployment, run:
```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

### Files Changed (5 files)

**Frontend (included in build):**
```text
resources/js/data/personas/young_saver.json
resources/js/store/modules/preview.js
resources/js/views/Version.vue
```

**Backend (upload separately):**
```text
app/Http/Controllers/Api/PreviewController.php
database/seeders/PreviewUserSeeder.php
```

---

## Expenditure Form - Hide Totals Column for Single Users

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

For single users (no spouse), the "Total/Household" column in the expenditure form is now hidden as it would just duplicate the user's values.

### Before

- Single users saw 3 columns: Category, User Name, Total
- The Total column just showed the same values as the user column

### After

- Single users see 2 columns: Category, User Name
- Married users still see 4 columns: Category, User, Spouse, Household

### Changes Made

- Updated CSS grid for single users from 3 columns to 2 columns
- Added `v-if="isMarried"` to all col-total elements in view mode
- Applies to all expenditure categories and financial commitments

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

---

## Preview Banner - Missing Persona Colors

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Added missing persona colors to the preview banner. Previously only 4 of 6 personas had color mappings, causing young_saver and retired_couple to fall back to orange.

### Before

- young_saver showed orange banner (same as entrepreneur)
- retired_couple showed orange banner (same as entrepreneur)

### After

Each persona now has a unique banner color:

| Persona | Color |
|---------|-------|
| young_family | Blue |
| peak_earners | Green |
| widow | Purple |
| entrepreneur | Orange |
| young_saver | Cyan |
| retired_couple | Rose |

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/Preview/PreviewBanner.vue
```

---

## Expenditure Form - UI Polish

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Multiple UI improvements to the expenditure form for better visual consistency and readability.

### Changes Made

| Change | Description |
|--------|-------------|
| Right-aligned values | All value columns (user, spouse, household) are now right-aligned for currency |
| Tab label changes | "Retired Budget" → "Budget at Retirement", "Widowed Budget" → "Widowed" |
| Consistent total sizing | "Total Monthly Expenditure" row now uses same `text-body font-semibold` across all tabs |
| Consistent savings sizing | "Monthly Savings in Retirement" and "Monthly Reduction from Current" rows match total sizing |

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/UserProfile/ExpenditureForm.vue
```

---

## Income Tab - Dynamic Net Income Label

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Made the Net Income label in Valuable Info > Income dynamically describe what deductions are included based on the user's actual situation.

### Before

- Static label: "Net Income (after tax)"

### After

- Dynamic label based on which deductions apply:
  - "Net Income (after tax)" - if only tax deducted
  - "Net Income (after tax and pension contributions)" - if has pension contributions
  - "Net Income (after tax and tax credits)" - if has tax credits (e.g., Section 24 BTL relief)
  - "Net Income (after tax, pension contributions and tax credits)" - if has both

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/UserProfile/IncomeOccupation.vue
```

---

## Income Tab - Tax & NI Heading Update

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Changed the Tax & NI section heading to indicate these are estimated values.

### Before

- Heading: "Tax & NI"

### After

- Heading: "Estimated Tax and NI"

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/UserProfile/IncomeOccupation.vue
```

---

## Peak Earners Persona - Positive Disposable Income

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Fixed the Mitchell's (peak_earners) persona data to ensure positive disposable income. The Annual Expenditure was exceeding Net Income because discretionary savings contributions were too high.

### Changes Made

| Field | Before | After |
|-------|--------|-------|
| Monthly expenditure | £4,300 | £2,500 |
| SIPP monthly contribution | £2,000 | £0 |
| David's ISA monthly | £833 | £0 |
| Sarah's ISA monthly | £833 | £0 |
| Joint GIA monthly | £1,000 | £0 |

### Expenditure Category Adjustments

| Category | Before | After |
|----------|--------|-------|
| food_groceries | £550 | £450 |
| transport_fuel | £200 | £150 |
| healthcare_medical | £60 | £50 |
| insurance | £120 | £100 |
| mobile_phones | £60 | £50 |
| internet_tv | £50 | £40 |
| subscriptions | £35 | £30 |
| clothing_personal_care | £150 | £100 |
| entertainment_dining | £200 | £100 |
| holidays_travel | £250 | £100 |
| school_fees | £1,800 | £1,000 |
| school_lunches | £70 | £50 |
| school_extras | £100 | £80 |
| children_activities | £120 | £100 |
| gifts_charity | £100 | £50 |
| regular_savings | £300 | £0 |
| other_expenditure | £135 | £0 |

### Result

- Removed ~£56,000/year in discretionary savings (SIPP + ISAs + GIA)
- Reduced manual expenditure by ~£22,000/year (£4,300 → £2,500)
- Total annual savings: ~£78,000

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/data/personas/peak_earners.json
```

After deployment, re-seed:
```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

---

## Risk Level Color System Update

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Updated the risk level color system to use a new consistent color scheme across all risk-related components. Removed all orange/amber colors from the risk module (amber is banned from the application).

### New Risk Level Colors

| Risk Level | Color | Tailwind Classes |
|------------|-------|------------------|
| Low | Yellow | `bg-yellow-100 text-yellow-800` |
| Lower-Medium | Pink | `bg-pink-100 text-pink-800` |
| Medium | Green | `bg-green-100 text-green-800` |
| Upper-Medium | Teal | `bg-teal-100 text-teal-800` |
| High | Blue | `bg-blue-100 text-blue-800` |

### Investment Types Accordion Updates

| Asset Type | Risk Level | Old Color | New Color |
|------------|------------|-----------|-----------|
| Cash & Cash Equivalents | Low | Green | Yellow |
| Bonds (Fixed Income) | Lower-Medium | Teal | Pink |
| Commercial Property | Medium | Blue | Green |
| Equities (Shares) | Medium-High | Orange | Teal |
| Alternative Investments | High | Red | Blue |

### Other Changes

| Component | Change |
|-----------|--------|
| RiskBadge.vue | Custom risk ring changed from orange/purple to blue (`ring-blue-300`) |
| RiskProfilePage.vue | Product-Level Overrides box changed from orange to green |
| RiskProfileSummary.vue | Product-Level Overrides box changed from orange to green |
| TimeHorizonSection.vue | Important note box changed from orange to blue |
| RiskLevelsExplainedPage.vue | Volatility stat color changed from orange to red |
| RiskFactorsPanel.vue | "Cannot afford to withstand fall" card changed from orange to teal |
| InvestmentTypesAccordion.vue | Warning boxes changed from orange/red to blue |

### Files Changed (12 files)

**Frontend (included in build):**
```text
resources/js/components/Shared/RiskBadge.vue
resources/js/components/Shared/RiskLevelSelector.vue
resources/js/components/Risk/RiskFactorsPanel.vue
resources/js/components/Risk/InvestmentTypesAccordion.vue
resources/js/components/Risk/RiskProfileSummary.vue
resources/js/components/Risk/FactorBreakdownCard.vue
resources/js/components/Risk/TimeHorizonSection.vue
resources/js/views/Risk/RiskProfilePage.vue
resources/js/views/Risk/RiskFactorDetailPage.vue
resources/js/views/Risk/RiskLevelsExplainedPage.vue
resources/js/services/riskService.js
resources/js/constants/designSystem.js
```

---

## IHT Calculation Table - Gray Color Scheme & UI Cleanup

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Updated the IHT calculation table in Estate Planning to use a consistent gray color scheme matching the expenditure tab. Previously the table used multiple colors (blue, purple, green, orange, red, indigo) to differentiate sections, making it visually cluttered.

Also cleaned up the Tax Allowances info boxes at the bottom by removing icons and making heading/text colors consistent.

### Before

- David's Assets: Blue border and text
- Sarah's Assets: Green/Purple border and text
- Total Gross Assets: Indigo border and text
- Liabilities: Red/Orange border and text
- Net Estate/IHT Liability: Various colored text

### After

- All table rows: Gray border (`border-gray-400`)
- All text: Gray variants (`text-gray-600`, `text-gray-700`, `text-gray-900`)
- Hover states: Gray (`hover:bg-gray-50`, `hover:bg-gray-100`)
- Toggle buttons: Gray hover (`hover:bg-gray-100`)

### Colors Changed

| Element | Old Colors | New Color |
|---------|------------|-----------|
| Left borders | `blue-500`, `purple-500`, `green-500`, `orange-500`, `red-500`, `indigo-500` | `gray-400` |
| Section headers | `blue-900`, `purple-900`, `green-900`, `orange-900`, `red-900`, `indigo-900` | `gray-900` |
| Row values | `blue-600`, `purple-600`, `green-600`, `orange-600`, `red-600` | `gray-600` |
| Bold totals | `blue-700`, `purple-700`, `orange-700`, `red-700` | `gray-900` |
| Subtitles | `blue-500`, `purple-500`, `green-500`, `orange-500`, `red-500` | `gray-500` |
| Hover states | `blue-50`, `purple-50`, `green-50`, `orange-50`, `red-50` | `gray-50` |

### Tax Allowances Info Boxes

| Change | Description |
|--------|-------------|
| Tax-Free Allowance | Removed icon, heading and text both `text-blue-800` |
| Home Allowance | Removed icon, heading and text both conditional (`text-green-800` when full, `text-gray-800` otherwise) |

### Note

The summary cards at the top of the page (headline figures) retain their colored borders to provide visual distinction. Only the calculation table itself was changed.

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/Estate/IHTPlanning.vue
```

---

## Monte Carlo Charts - Centralized Styling

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Centralized the chart card styling for Monte Carlo graphs in `app.css` instead of having duplicate scoped styles in multiple components.

### Changes Made

| Change | Description |
|--------|-------------|
| Updated `.chart-card` in app.css | Added hover animation with border color change |
| Removed scoped styles | Removed duplicate `.chart-card` styles from 3 components |

### CSS in app.css

```css
.chart-card {
  @apply bg-white rounded-lg border border-gray-200 p-6 cursor-pointer transition-all duration-200;
}

.chart-card:hover {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
  @apply border-primary-500;
}
```

### Files Changed (4 files)

**CSS:**
```text
resources/css/app.css
```

**Frontend (removed scoped styles):**
```text
resources/js/components/Retirement/FutureValueTab.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/InvestmentProjections.vue
```

---

## Investment Cards - Remove Risk Badges, Fix Joint Badge Position

**Branch:** genBits

**Status:** ✅ Deployed to production

### Description

Removed risk badges from all investment account cards and moved the Joint ownership badge to the top-right corner for a cleaner layout.

### Before

- Risk badges (High, U-Med, etc.) displayed in top-right corner
- Joint badge displayed inline with account type badge in header
- Cards looked cluttered with multiple badges

### After

- No risk badges on investment cards
- Joint badge positioned in top-right corner (where risk badge was)
- Cleaner card layout with only account type badge in header

### Changes Made

| Change | Description |
|--------|-------------|
| Removed RiskBadge | Removed RiskBadge component from investment cards |
| Joint badge position | Moved Joint badge to absolute top-right corner |
| Removed unused code | Removed `shouldShowRiskBadge`, `formatOwnershipType`, `getOwnershipBadgeClass` methods |
| New CSS class | Added `.joint-badge-corner` for purple badge styling in corner |

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/NetWorth/InvestmentList.vue
```

---

## IHT Summary & Strategies - Inline Layout

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Combined the IHT summary cards (Taxable Estate and Inheritance Tax Liability) into a single compact card and formatted the strategies cards inline with it for a cleaner, more efficient layout. Removed the duplicate "Inheritance Tax Mitigation Strategies" section for married users.

### Before

- Two separate summary cards side by side (Taxable Estate, Total Inheritance Tax Liability)
- Strategies section below with separate heading "Inheritance Tax Mitigation Strategies"
- Duplicate strategies cards for married users

### After

- Single combined "IHT Summary" card with both metrics in a 2-column grid
- Strategies cards (Will, Gifting, Life Policy, Charitable Bequest, Trust) displayed inline on same row as summary card
- Grid layout: lg:grid-cols-5 (summary + 4 strategies on one row, Trust conditional)
- Removed duplicate strategies section for married users (they only see summary cards at top)

### Summary Card Structure

```
IHT Summary
├── Taxable Estate
│   ├── Now: £X
│   └── Age Y: £X
└── IHT Liability
    ├── Now: £X
    └── Age Y: £X
```

### Changes Made

| Change | Description |
|--------|-------------|
| Combined summary cards | Single "IHT Summary" card with both metrics |
| Inline strategies | Strategies display on same row as summary (lg:grid-cols-5) |
| Compact layout | Reduced vertical space usage |
| Removed duplicate section | Married users no longer see separate "Inheritance Tax Mitigation Strategies" section |

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/Estate/IHTPlanning.vue
```

---

## Charitable Bequest - Dynamic IHT Calculation Update

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

When the charitable bequest toggle is changed, the IHT calculation table now dynamically updates to show:
1. The reduced IHT rate (36% instead of 40%)
2. The charitable donation amount (minimum 10% of baseline)
3. The recalculated IHT liability

### Before

- Clicking charitable bequest toggle saved preference but did not update IHT calculation table
- Table always showed "40%" rate
- No indication of how charitable bequest affects the calculation

### After

- Toggle now reloads IHT calculation to reflect changes
- IHT rate dynamically shows "36%" or "40%" based on charitable bequest setting
- New row appears showing "Less: Charitable Bequest (10% minimum)" with green styling
- IHT liability is recalculated using the appropriate rate
- Summary card also updates to show adjusted liability

### Changes Made

| Change | Description |
|--------|-------------|
| `toggleCharitableBequest` | Now calls `loadIHTCalculation()` after saving preference |
| New computed properties | `charitableBaseline`, `charitableDonationAmount`, `effectiveIHTRate`, `effectiveIHTRateLabel`, `adjustedIHTLiability` (multiple variants for projections) |
| Charitable Bequest row | New green-styled row showing minimum 10% donation amount when enabled |
| IHT Liability row | Now shows dynamic rate (36%/40%) and uses adjusted calculation when charitable bequest enabled |
| Summary card | Shows adjusted liability with green styling when charitable bequest enabled |
| Fallback list view | Also updated to show charitable bequest impact |

### IHT Calculation With Charitable Bequest

```
Baseline = Net Estate - NRB (excluding RNRB)
Charitable Donation = Baseline × 10% (minimum)

Standard (40% rate):
Taxable Estate × 40% = IHT Liability

With Charitable Bequest (36% rate):
Taxable Estate × 36% = Reduced IHT Liability
```

### Visual Changes

- Charitable bequest row: Green background (`bg-green-50`), green border (`border-green-400`), green text (`text-green-800`)
- IHT rate label: "(Reduced rate)" note in green when charitable bequest enabled
- Summary card: Green IHT liability value when charitable bequest enabled

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/Estate/IHTPlanning.vue
```

---

## IHT Calculation Table - Collapsible Allowances & Conditional Baseline Row

**Branch:** techDebt

**Status:** ✅ Deployed to production

### Description

Enhanced the IHT calculation table with collapsible allowances section and made the "Estate after Tax-Free Allowances" row conditional on charitable bequest being enabled.

### Changes Made

| Change | Description |
|--------|-------------|
| Collapsible allowances | NRB and RNRB rows now collapse into a single "Tax-Free Allowances" header row |
| Allowances header shows total | Combined total of NRB + RNRB displayed in header when collapsed |
| Chevron toggle | Click the allowances header row to expand/collapse individual NRB and RNRB rows |
| RNRB details inside collapsed section | Taper warning and not-available messages now appear within expanded section |
| Conditional baseline row | "Estate after Tax-Free Allowances" row only shows when charitable bequest is enabled |

### Visual Behaviour

**Collapsed State (default):**
```
Tax-Free Allowances          ▶  £500,000
```

**Expanded State:**
```
Tax-Free Allowances          ▼  £500,000
  Tax-Free Allowance (NRB)      £325,000
  Home Allowance (RNRB)         £175,000
```

### Conditional Row Logic

- **Charitable bequest OFF:** "Estate after Tax-Free Allowances" row is hidden (not needed for reconciliation)
- **Charitable bequest ON:** Row appears with blue styling, showing the baseline used for 10% charitable donation calculation

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/Estate/IHTCalculationTable.vue
```

---

## IHT Calculation Table - Restructured for Charitable Bequest Reconciliation

**Branch:** investUpdate

**Status:** ✅ Deployed to production

### Description

Restructured the IHT calculation table to clearly show the charitable bequest baseline. The new structure separates NRB (Tax-Free Allowance) from RNRB (Home Allowance), with a new "Estate after Tax-Free Allowances" row that shows the baseline used for calculating the 10% charitable bequest requirement.

### New Table Structure

**Previous Flow:**
1. Net Estate → 2. Less NRB → 3. Less RNRB → 4. Taxable Estate → 5. Charitable Bequest → 6. IHT Liability

**New Flow:**
1. Net Estate → 2. Less NRB → 3. **Estate after Tax-Free Allowances** (charitable baseline) → 4. Charitable Bequest → 5. Less RNRB → 6. Taxable Estate → 7. IHT Liability

### Changes Made

| Change | Description |
|--------|-------------|
| New "Estate after Tax-Free Allowances" row | Blue highlighted row showing the baseline for charitable bequest calculation |
| Charitable bequest moved before RNRB | Now sits logically after NRB deduction, before home allowance |
| Separate NRB and RNRB sections | Married couples: NRB first, then charitable baseline, then RNRB |
| New computed properties | `estateAfterNRB`, `estateAfterNRBProjected`, `secondDeathEstateAfterNRB`, etc. |
| Updated fallback list view | Old list-style view also follows new structure |

### Visual Styling

- Estate after NRB row: Blue background (`bg-blue-50`), blue border (`border-blue-400`), blue text (`text-blue-800`)
- Shows subtitle: "Charitable bequest baseline (before home allowance)"
- Users can now reconcile: Charitable Donation = Estate after Tax-Free Allowances × 10%

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/Estate/IHTPlanning.vue
```

---

## AccountForm.vue Refactoring - Extract Account Type Sections

**Branch:** techDebt

**Status:** ⏳ Awaiting deployment

### Description

Refactored `AccountForm.vue` (2,643 lines) by extracting the three major account-type-specific sections into separate child components. This follows the same pattern used successfully for IHTPlanning.vue.

### Problem Solved

- One component previously handled 14 different account types
- 56 conditional rendering statements (`v-if`/`v-show`)
- Three distinct form "modes" with completely different fields mixed together
- High risk of breaking other account types when making changes
- Difficult to navigate and maintain

### Component Architecture

```
AccountForm.vue (Parent - refactored from ~2,643 to ~1,007 lines)
  │
  ├── PrivateInvestmentFields.vue (NEW - ~650 lines)
  │     └── Company Details, Investment Details, Ownership, Tax Relief, Exit
  │
  ├── EmployeeShareSchemeFields.vue (NEW - ~600 lines)
  │     └── Employer Details, Grant Details, SAYE/CSOP-specific, Vesting, Exercise
  │
  └── StandardInvestmentFields.vue (NEW - ~400 lines)
        └── Provider, Platform, Current Value, Contributions, Platform Fee, Risk Level
```

### Files Created (3 files)

**Frontend:**
```text
resources/js/components/Investment/PrivateInvestmentFields.vue
resources/js/components/Investment/EmployeeShareSchemeFields.vue
resources/js/components/Investment/StandardInvestmentFields.vue
```

### Files Changed (1 file)

**Frontend:**
```text
resources/js/components/Investment/AccountForm.vue
```

### Line Count Changes

| File | Before | After |
|------|--------|-------|
| AccountForm.vue | ~2,643 | ~1,007 |
| PrivateInvestmentFields.vue | - | ~650 |
| EmployeeShareSchemeFields.vue | - | ~600 |
| StandardInvestmentFields.vue | - | ~400 |
| **Total** | ~2,643 | ~2,657 |

*Note: Total lines increase slightly due to component boilerplate, but each file is now focused and manageable.*

### v-model Pattern

Each child component uses the Vue 3 v-model pattern for two-way binding:

```vue
<!-- Parent -->
<PrivateInvestmentFields v-model="formData" :errors="errors" />

<!-- Child -->
props: {
  modelValue: { type: Object, required: true },
  errors: { type: Object, default: () => ({}) }
},
emits: ['update:modelValue'],
computed: {
  localData: {
    get() { return this.modelValue; },
    set(value) { this.$emit('update:modelValue', value); }
  }
}
```

### Account Type Routing

| Account Types | Component |
|---------------|-----------|
| `private_company`, `crowdfunding` | PrivateInvestmentFields |
| `saye`, `csop`, `emi`, `unapproved_options`, `rsu` | EmployeeShareSchemeFields |
| `isa`, `gia`, `onshore_bond`, `offshore_bond`, `vct`, `eis`, `nsi`, `other` | StandardInvestmentFields |

### Other Changes

- Changed amber color classes to orange in fee warning (StandardInvestmentFields.vue) per design standards
- Changed amber color in ISA allowance tracker to orange (StandardInvestmentFields.vue)

---

## Rebuild Required: YES

Frontend Vue components changed. Full rebuild required:

```bash
./deploy/fynla-org/build.sh
```

---

## Upload Checklist

### Step 1: Run Build

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets

Upload the entire `public/build/` directory to:

```text
~/www/fynla.org/public_html/public/build/
```

### Step 3: Upload New Vue Components

Upload the new detail view components:

```text
resources/js/views/Investment/EmployeeShareSchemeDetail.vue
resources/js/views/Investment/PrivateInvestmentDetail.vue
```

*Note: These are included in the build, so only the built assets need uploading for frontend changes.*

### Step 4: Upload PHP Files

Upload the updated PHP files:

```text
app/Http/Controllers/Api/InvestmentController.php
app/Services/Risk/RiskPreferenceService.php
database/seeders/PreviewUserSeeder.php
```

### Step 5: Re-seed Preview Users

```bash
php artisan db:seed --class=PreviewUserSeeder --force
```

### Step 6: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

---

## Verification

After deployment, verify:

1. **Cash ISA Form** (Savings module):
   - Navigate to Net Worth > Cash tab
   - Click "Add Account" and select "Cash ISA"
   - Verify ISA Type dropdown is NOT present in the blue ISA Subscription box
   - Verify "Already Subscribed" helper text shows: "Amount already contributed to this account for 2025/26 tax year, including 9 regular payments."
   - Verify "Regular Contribution" helper text shows: "As of 29 January 2026, you have 3 contributions remaining for the 2025/26 tax year."

2. **Stocks & Shares ISA Form** (Investment module):
   - Navigate to Net Worth > Investments tab
   - Click "Add Account" and select "ISA (Stocks & Shares)"
   - Verify ISA Type dropdown is NOT present in the blue ISA Subscription box
   - Verify "Already Subscribed" helper text shows: "Amount already contributed to this account for 2025/26 tax year, including 9 regular payments."
   - Verify "Regular Contribution" helper text shows: "As of 29 January 2026, you have 3 contributions remaining for the 2025/26 tax year."

3. **ISA Allowance Calculation** (both forms):
   - Enter £500 monthly regular contribution
   - Verify "Planned" amount in allowance tracker shows ~£1,500 (3 remaining months × £500)
   - NOT £6,000 (full year × £500) - this would be double-counting
   - Add a £2,000 planned lump sum
   - Verify "Planned" amount increases to ~£3,500 (£1,500 + £2,000)

4. **Expenditure Form - UI Layout** (Onboarding):
   - Start onboarding as a married user (e.g., James Carter persona)
   - Navigate to the expenditure step
   - Verify spacing between "Why this matters" and "Note" info boxes
   - Verify "Spouse Expenditure" and "Entry Method" cards display side by side
   - Verify both cards have segmented control toggles (not checkboxes/buttons)
   - Verify active toggle option shows green background on white container

5. **Expenditure Form - Toggles** (Onboarding):
   - Toggle "Spouse Expenditure" between Joint and Separate
   - Verify Joint mode shows single inputs, Separate mode shows person tabs
   - Toggle "Entry Method" between Detailed and Simple
   - Verify Detailed shows category breakdown, Simple shows single total input
   - Verify spacing is maintained when toggling between modes

6. **Expenditure Form - Person Tabs** (Onboarding):
   - Set "Spouse Expenditure" to Separate
   - Verify tabs appear showing user name and spouse name
   - Click between tabs to verify each shows different input fields
   - Enter values for user, switch to spouse tab, enter values for spouse
   - Verify values are retained when switching between tabs

7. **Expenditure Form** (Valuable Info Edit):
   - Go to Dashboard > Valuable Info > Expenses
   - Click Edit
   - Verify same UI layout and behaviour as onboarding

8. **Private Company Investment** (Investment module):
   - Navigate to Net Worth > Investments tab
   - Click "Add Account" and select "Private Company"
   - Verify Provider, Country, Platform fields are hidden
   - Verify Company Details section appears with Legal Name, Trading Name, Registration Number, Sector, Website
   - Verify Investment Details section with Date, Amount, Funding Round, Instrument Type, Shares, Valuations
   - Verify Ownership & Legal section with Share Class, Holding Structure, Voting/Dividend Rights checkboxes
   - Verify UK Tax Relief section (blue box) with Relief Type dropdown, Certificate fields
   - Verify Status & Valuation section with Company Status, Latest Valuation

9. **Crowdfunding Investment** (Investment module):
   - Click "Add Account" and select "Crowdfunding Investment"
   - Verify all Private Company sections appear
   - Verify additional "Crowdfunding Platform" dropdown appears in Company Details section
   - Submit with Seedrs as platform and verify it saves

10. **EIS Tax Relief** (Investment module):
    - Add a Private Company investment
    - Select "EIS (30% relief)" as Tax Relief Type
    - Enter investment date
    - Save the investment
    - Edit the investment and verify Disposal Restriction Date is auto-calculated (3 years from investment date)

11. **Exit Tracking** (Investment module):
    - Edit a Private Company investment
    - Change Company Status to "Exited"
    - Verify Exit Details section appears
    - Verify Exit Type, Date, Gross Proceeds, Fees, Net Proceeds fields
    - Verify Loss Relief Eligible and Negligible Value Claim checkboxes

12. **SAYE / Sharesave** (Investment module):
    - Click "Add Account" and select "SAYE / Sharesave"
    - Verify Provider, Country, Platform fields are hidden
    - Verify Employer Details section with Name (required), Registration, Ticker, Listed checkbox
    - Verify Grant Details section with Date (required), Reference, Units Granted (required), Exercise Price (required)
    - Verify SAYE Savings Details section (green box) with Monthly Savings (max £500), Balance, Contract Duration (3/5 years)
    - Verify Vesting Schedule section
    - Verify Current Status section with vested/unvested/exercised counts, share price
    - Set scheme_start_date and scheme_duration_months, verify saye_maturity_date auto-calculates
    - Enter current share price and verify intrinsic value displays

13. **CSOP** (Investment module):
    - Click "Add Account" and select "CSOP (Company Share Option Plan)"
    - Verify all employee share scheme sections appear (no SAYE-specific green box)
    - Verify Tax Treatment section shows CSOP-specific fields
    - Enter grant_date and verify csop_three_year_date auto-calculates (grant + 3 years)
    - Verify yellow info box explaining 3-10 year tax advantage window

14. **EMI** (Investment module):
    - Click "Add Account" and select "EMI (Enterprise Management Incentives)"
    - Verify tax_treatment defaults to "Tax Advantaged"
    - Verify all option scheme sections appear

15. **Unapproved Options** (Investment module):
    - Click "Add Account" and select "Unapproved Share Options"
    - Verify tax_treatment defaults to "Unapproved"
    - Verify all option scheme sections appear

16. **RSUs** (Investment module):
    - Click "Add Account" and select "RSUs (Restricted Stock Units)"
    - Verify Exercise Price field is NOT shown (RSUs don't have exercise price)
    - Verify Exercise & Expiry section is NOT shown (RSUs vest directly)
    - Verify tax_treatment defaults to "Unapproved"

17. **Performance Vesting** (Investment module):
    - Edit any employee share scheme
    - Check "Has performance conditions" checkbox
    - Verify Performance Conditions textarea appears
    - Verify Performance Period End date field appears
    - Verify Min/Max Vesting % fields appear

18. **Leaver Terms** (Investment module):
    - Edit any employee share scheme
    - Select a Leaver Category (e.g., "Good Leaver")
    - Verify Termination Date field appears
    - Verify Leaver Notes textarea appears

19. **Employee Share Scheme Detail View** (Investment module):
    - Add a SAYE or CSOP account with sample data
    - Click on the account card to open detail view
    - Verify it does NOT show Holdings/Performance/Fees tabs
    - Verify it shows Key Metrics header with 4 cards (Exercise/Grant Value, Vested Value, Vesting %, Days to window)
    - Verify it shows Employer Details section
    - Verify it shows Grant Details section
    - Verify it shows Vesting Schedule with progress bar
    - Verify it shows Current Status section
    - For SAYE: Verify SAYE Savings section appears
    - For options: Verify Exercise & Expiry section appears
    - Verify Tax Treatment section with relevant badges

20. **Private Investment Detail View** (Investment module):
    - Add a Private Company or Crowdfunding account with sample data
    - Click on the account card to open detail view
    - Verify it does NOT show Holdings/Performance/Fees tabs
    - Verify it shows Key Metrics header with 4 cards (Valuation, MOIC, Tax Relief, Disposal Countdown)
    - Verify it shows Company Details section
    - Verify it shows Investment Details section
    - Verify it shows Ownership & Legal section
    - If EIS/SEIS: Verify UK Tax Relief section with countdown timer
    - Verify Status & Valuation section with MOIC calculation
    - If exited: Verify Exit Details section appears

21. **Standard Account Detail View** (Investment module):
    - Click on an ISA or GIA account
    - Verify it still shows the standard tabbed view (Overview, Holdings, Diversification, Performance, Rebalancing, Fees, Tax Status, Documents)
    - Verify standard Key Metrics header (Current Value, Annualised Return, Monthly Contribution, Holdings/ISA Allowance)

22. **IHT Summary & Strategies - Inline Layout** (Estate Planning):
    - Navigate to Estate Planning as a non-married user (e.g., Margaret Thompson - widow persona)
    - Verify single "IHT Summary" card with two columns (Taxable Estate, IHT Liability)
    - Verify strategies cards (Will, Gifting, Life Policy, Charitable Bequest) are inline on same row
    - On large screens (lg+), verify all 5 cards fit on one horizontal row
    - Navigate to Estate Planning as a married user (e.g., James Carter)
    - Verify married summary cards still show separately (Joint Death Now, Projected, Tax Payable)
    - Verify strategies section appears separately below summary with "Inheritance Tax Mitigation Strategies" heading

23. **Charitable Bequest - Dynamic IHT Calculation** (Estate Planning):
    - Navigate to Estate Planning IHT tab
    - Scroll to Charitable Bequest card, click "No" to ensure it's disabled
    - Note the IHT liability amount in the calculation table (should show 40% rate)
    - Click "Yes" on the charitable bequest card
    - Verify loading spinner appears briefly while saving
    - Verify IHT calculation table updates:
      - New pink row appears: "Less: Charitable Bequest (10% minimum)"
      - IHT Liability row now shows "36%" instead of "40%"
      - "(Reduced rate)" label appears next to the rate
      - IHT liability amount decreases (should be 10% less than before)
    - Verify summary card also updates to show adjusted liability with pink styling
    - Click "No" on charitable bequest card
    - Verify pink charitable bequest row disappears
    - Verify IHT rate returns to 40% and liability increases back to original

---

## Charitable Bequest - Correct IHT Calculation with Split Allowances

**Branch:** techDebt

**Status:** ✅ Deployed to production

### Description

Fixed the IHT calculation table to correctly handle charitable bequest. When charitable bequest is enabled, the allowances are now split so the charitable donation is deducted from the estate (reducing the taxable estate), not just applied as a reduced rate.

### Before (Incorrect)

When charitable bequest was enabled:
- Showed combined allowances (NRB + RNRB)
- Then showed "Estate after Tax-Free Allowances"
- Charitable bequest row appeared but didn't actually reduce the taxable estate
- Only the rate changed from 40% to 36%

### After (Correct)

When charitable bequest is enabled:
1. Net Estate
2. Less: NRB (Tax-Free Allowance) - collapsible
3. **= Estate after Tax-Free Allowances** (blue row - charitable baseline)
4. **Less: Charitable Bequest (10% minimum)** (green row - deducted from estate)
5. Less: RNRB (Home Allowance) - collapsible
6. = Taxable Estate (now reduced by charitable donation)
7. × 36% = IHT Liability (reduced rate)

When charitable bequest is OFF:
- Combined "Less: Tax-Free Allowances" (NRB + RNRB together, collapsible)
- Taxable Estate
- × 40% = IHT Liability

### Calculation Change

**Without charitable bequest:**
```
Taxable Estate = Net Estate - NRB - RNRB
IHT = Taxable Estate × 40%
```

**With charitable bequest:**
```
Baseline = Net Estate - NRB
Charitable Donation = Baseline × 10%
Taxable Estate = Net Estate - NRB - Charitable Donation - RNRB
IHT = Taxable Estate × 36%
```

### Files Changed (2 files)

**Frontend (included in build):**
```text
resources/js/components/Estate/IHTCalculationTable.vue
resources/js/components/Estate/IHTPlanning.vue
```

### Verification

1. Navigate to Estate Planning > IHT tab
2. With charitable bequest OFF:
   - Verify combined "Less: Tax-Free Allowances" row with total
   - Click chevron to expand and see NRB + RNRB breakdown
   - Verify Taxable Estate = Net Estate - All Allowances
   - Verify IHT rate shows 40%
3. Enable charitable bequest (click "Yes"):
   - Verify NRB section appears separately (collapsible)
   - Verify blue "Estate after Tax-Free Allowances" row appears
   - Verify green "Less: Charitable Bequest" row shows 10% of baseline
   - Verify RNRB section appears separately (collapsible, if eligible)
   - Verify Taxable Estate is reduced by the charitable donation amount
   - Verify IHT rate shows 36%
4. Verify the maths:
   - Charitable Donation = Estate after NRB × 10%
   - Taxable Estate (with charitable) = Taxable Estate (without) - Charitable Donation
   - IHT = Taxable Estate × 36%

---

## Joint Liability Percentage Display Fix

**Branch:** techDebt

**Status:** ✅ Deployed to production

### Description

Fixed joint ownership percentage display in the IHT Liabilities breakdown. The percentage was showing as "50.00%" instead of being hidden for 50/50 splits. The issue was caused by comparing `ownership_percentage !== 50` which failed when the value was stored as `50.00` (float) or `"50.00"` (string).

### Before

- Mortgages showed "(Joint - 50.00%)" instead of "(Joint)"
- Comparison `!== 50` failed for values like `50.00`

### After

- Added `formatJointLabel()` helper method that:
  - Parses percentage as float (handles strings and numbers)
  - Rounds to nearest integer (handles `50.00` → `50`)
  - Returns "(Joint)" for 50% splits
  - Returns "(Joint - X%)" for non-50% splits

### Files Changed (1 file)

**Frontend (included in build):**
```text
resources/js/components/Estate/IHTLiabilityBreakdown.vue
```

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Restore previous PHP files from backup
3. If database migrations were run, rollback with:
   ```bash
   # Rollback Employee Share Scheme migration (61 columns)
   php artisan migrate:rollback --step=1

   # If also rolling back Private Company migration (40 columns)
   php artisan migrate:rollback --step=2
   ```

---
