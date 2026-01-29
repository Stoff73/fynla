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

**Status:** 🔄 Ready for testing

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

**Status:** 🔄 Ready for testing

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

**Status:** 🔄 Ready for testing

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
```

### Step 5: Clear Cache (SSH)

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
