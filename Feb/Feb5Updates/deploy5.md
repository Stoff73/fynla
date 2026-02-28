# Deployment - 5 February 2026

## Build Required: YES (Completed)
## Reseed Required: YES (Completed)
## Migration Required: YES (Completed)

---

## Changes

### 1. Laravel Best Practices Audit

**Status:** Deployed

**Description:** Comprehensive Laravel best practices audit improving code quality from 85/100 to 94/100.

**What was done:**
- Created 12 Form Request validation classes for Investment and Estate modules
- Created 10 API Resource classes for response transformation
- Extracted 470 lines from IHTController to IHTFormattingService
- Added Builder return types to 39 query scopes across 16 models
- Added readonly to 130+ constructor properties
- Converted 18 services to PHP 8 property promotion
- Changed DELETE endpoints to return 204 No Content
- Enabled Model::preventLazyLoading() in development

**New Files (23):**

```text
app/Http/Requests/Investment/AccountProjectionsRequest.php
app/Http/Requests/Investment/ScenarioRequest.php
app/Http/Requests/Investment/StartMonteCarloRequest.php
app/Http/Requests/Investment/StoreHoldingRequest.php
app/Http/Requests/Investment/StoreInvestmentGoalRequest.php
app/Http/Requests/Investment/StoreRiskProfileRequest.php
app/Http/Requests/Investment/UpdateHoldingRequest.php
app/Http/Requests/Investment/UpdateInvestmentGoalRequest.php
app/Http/Requests/Estate/CalculateIntestacyRequest.php
app/Http/Requests/Estate/StoreBequestRequest.php
app/Http/Requests/Estate/StoreWillRequest.php
app/Http/Requests/Estate/UpdateBequestRequest.php
app/Http/Resources/BusinessInterestResource.php
app/Http/Resources/ChattelResource.php
app/Http/Resources/GoalContributionResource.php
app/Http/Resources/GoalResource.php
app/Http/Resources/HoldingResource.php
app/Http/Resources/InvestmentAccountResource.php
app/Http/Resources/MortgageResource.php
app/Http/Resources/PropertyResource.php
app/Http/Resources/SavingsAccountResource.php
app/Http/Resources/UserResource.php
app/Services/Estate/IHTFormattingService.php
```

**Modified Files (87):**

```text
app/Agents/CoordinatingAgent.php
app/Agents/EstateAgent.php
app/Agents/GoalsAgent.php
app/Agents/InvestmentAgent.php
app/Agents/ProtectionAgent.php
app/Agents/RetirementAgent.php
app/Agents/SavingsAgent.php
app/Http/Controllers/Api/BusinessInterestController.php
app/Http/Controllers/Api/ChattelController.php
app/Http/Controllers/Api/DashboardController.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Controllers/Api/Estate/WillController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/GoalsController.php
app/Http/Controllers/Api/HolisticPlanningController.php
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/MortgageController.php
app/Http/Controllers/Api/NetWorthController.php
app/Http/Controllers/Api/PropertyController.php
app/Http/Controllers/Api/ProtectionController.php
app/Http/Controllers/Api/RecommendationsController.php
app/Http/Controllers/Api/RetirementController.php
app/Http/Controllers/Api/SavingsController.php
app/Models/AuditLog.php
app/Models/DataExport.php
app/Models/Document.php
app/Models/DocumentExtractionLog.php
app/Models/ErasureRequest.php
app/Models/Goal.php
app/Models/GoalContribution.php
app/Models/Investment/Holding.php
app/Models/Investment/InvestmentRecommendation.php
app/Models/Investment/InvestmentScenario.php
app/Models/Investment/RebalancingAction.php
app/Models/LoginAttempt.php
app/Models/OnboardingProgress.php
app/Models/RecommendationTracking.php
app/Models/UserConsent.php
app/Models/UserSession.php
app/Providers/AppServiceProvider.php
app/Services/Auth/PasswordResetService.php
app/Services/Benefits/ChildBenefitService.php
app/Services/Business/BusinessInterestService.php
app/Services/Chattel/ChattelCGTService.php
app/Services/Coordination/ConflictResolver.php
app/Services/Coordination/RecommendationsAggregatorService.php
app/Services/Dashboard/DashboardAggregator.php
app/Services/Documents/AIExtractionService.php
app/Services/Documents/DocumentProcessor.php
app/Services/Estate/CashFlowProjector.php
app/Services/Estate/ComprehensiveEstatePlanService.php
app/Services/Estate/FutureValueCalculator.php
app/Services/Estate/GiftingStrategyOptimizer.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/IHTCalculator.php
app/Services/Estate/IHTStrategyGeneratorService.php
app/Services/Estate/PersonalizedGiftingStrategyService.php
app/Services/Estate/PersonalizedTrustStrategyService.php
app/Services/Estate/SpouseNRBTrackerService.php
app/Services/Estate/TrustService.php
app/Services/Estate/WillAnalysisService.php
app/Services/GDPR/DataErasureService.php
app/Services/GDPR/DataExportService.php
app/Services/Goals/GoalAssignmentService.php
app/Services/Goals/GoalsProjectionService.php
app/Services/Investment/AssetLocation/AssetLocationOptimizer.php
app/Services/Investment/AssetLocation/TaxDragCalculator.php
app/Services/Investment/ContributionEstimatorService.php
app/Services/Investment/InvestmentProjectionService.php
app/Services/Investment/PortfolioStrategyService.php
app/Services/Investment/Tax/BedAndISACalculator.php
app/Services/Investment/Tax/CGTHarvestingCalculator.php
app/Services/Investment/Tax/ISAAllowanceOptimizer.php
app/Services/Investment/Tax/TaxOptimizationAnalyzer.php
app/Services/Investment/TaxEfficiencyCalculator.php
app/Services/Property/PropertyTaxService.php
app/Services/Retirement/AnnualAllowanceChecker.php
app/Services/Retirement/ContributionOptimizer.php
app/Services/Retirement/PensionProjector.php
app/Services/Retirement/RequiredCapitalCalculator.php
app/Services/Retirement/RetirementIncomeService.php
app/Services/Retirement/RetirementProjectionService.php
app/Services/Retirement/RetirementStrategyService.php
app/Services/Risk/AutoRiskCalculator.php
app/Services/Savings/ISATracker.php
app/Services/Settings/AssumptionsService.php
app/Services/Tax/TaxProductInfoService.php
app/Services/Trust/IHTPeriodicChargeCalculator.php
app/Services/UKTaxCalculator.php
app/Services/UserProfile/UserProfileService.php
```

---

### 2. Bug Report Modal Focus Style

**Status:** Deployed

**Description:** Replaced thick black browser focus border with thin blue border on text inputs.

**What was done:**
- Added `focus:outline-none` to remove browser's default thick black outline
- Added `focus:ring-1` for thinner ring effect
- Kept `focus:border-blue-500` for blue border on focus

**Modified Files (1):**

```text
resources/js/components/BugReportModal.vue
```

---

### 3. Dashboard Protection Premium Fix

**Status:** Deployed

**Description:** Fixed protection premium showing incorrect value and clarified label.

**What was done:**
- Fixed double-division bug: store's `totalPremium` already returns monthly, dashboard was dividing by 12 again
- Changed label from "Monthly Premium" to "Total Monthly Premium" for clarity

**Modified Files (1):**

```text
resources/js/views/Dashboard.vue
```

---

### 4. Actions Card Improvements

**Status:** Deployed

**Description:** Renamed card and removed US-specific tax concept.

**What was done:**
- Renamed "Recommended Actions" to "Items for Review"
- Removed tax-loss harvesting opportunity (US concept not applicable to UK)
- Cleaned up dead code references in PortfolioStrategyService

**Modified Files (3):**

```text
resources/js/components/Dashboard/ActionsOverviewCard.vue
app/Services/Investment/PortfolioStrategyService.php
app/Services/Investment/Tax/TaxOptimizationAnalyzer.php
```

---

### 5. Consistent Asset Ordering

**Status:** Deployed

**Description:** Standardised asset order across the application.

**What was done:**
- Reordered assets to: Pensions, Property, Investments, Cash, Business, Personal Valuables
- Fixed WealthSummary.vue which had incorrect order
- Dashboard NetWorthOverviewCard already had correct order

**Modified Files (1):**

```text
resources/js/components/NetWorth/WealthSummary.vue
```

---

### 6. Rename Chattels to Personal Valuables

**Status:** Deployed

**Description:** Updated terminology from "Chattels" to "Personal Valuables" in Dashboard.

**What was done:**
- Changed category label mapping in Dashboard.vue from "Chattels" to "Personal Valuables"

**Modified Files (1):**

```text
resources/js/views/Dashboard.vue
```

---

### 7. API Resource Null Handling Fix

**Status:** Deployed

**Description:** Fixed 500 errors when relationships are not eager loaded.

**What was done:**
- Changed `new UserResource($this->whenLoaded('user'))` pattern to `$this->when($this->relationLoaded('user'), fn () => new UserResource($this->user))`
- Fixed same pattern for PropertyResource, GoalResource, SavingsAccountResource in nested resources
- Prevents MissingValue::$id error when relationships are not loaded

**Modified Files (7):**

```text
app/Http/Resources/BusinessInterestResource.php
app/Http/Resources/ChattelResource.php
app/Http/Resources/GoalContributionResource.php
app/Http/Resources/GoalResource.php
app/Http/Resources/MortgageResource.php
app/Http/Resources/PropertyResource.php
app/Http/Resources/SavingsAccountResource.php
```

---

### 8. Navbar Valuable Info Dropdown

**Status:** Deployed

**Description:** Show Valuable Info items directly in dropdown for tablet and larger screens.

**What was done:**
- Mobile: Keep "Valuable Info" as single link to tabbed page
- Tablet+: Show Letter to Spouse, Will, Income, Expenditure, Risk Profile directly in dropdown
- Widened dropdown menu from w-48 to w-56 to accommodate longer labels
- Added divider between Valuable Info items and other menu items

**Modified Files (1):**

```text
resources/js/components/Navbar.vue
```

---

### 9. Email Template Redesign

**Status:** Deployed

**Description:** Redesigned all user-facing email templates with consistent branding and improved styling.

**What was done:**

- Removed colored header blocks - emails now start with "Dear {user}"
- Replaced amber/orange warning boxes with blue styling (#f0f9ff, #3b82f6)
- Changed all boxes to use consistent single-color borders (removed border-left only pattern)
- Added sign-off: "Kindest regards, The Fynla Team (Chris & Brett)" with logo
- Added mailto:support@fynla.org links for all "contact support" text
- Added "Need help? Contact Support" link in footer

**New Files (1):**

```text
public/images/logoMain.png
```

**Modified Files (5):**

```text
resources/views/emails/verification-code.blade.php
resources/views/emails/spouse-account-created.blade.php
resources/views/emails/spouse-account-linked.blade.php
resources/views/emails/password-reset-code.blade.php
resources/views/emails/deletion-verification-code.blade.php
```

---

## Summary: All Files to Upload

### New Files (24 total)

```text
app/Http/Requests/Investment/AccountProjectionsRequest.php
app/Http/Requests/Investment/ScenarioRequest.php
app/Http/Requests/Investment/StartMonteCarloRequest.php
app/Http/Requests/Investment/StoreHoldingRequest.php
app/Http/Requests/Investment/StoreInvestmentGoalRequest.php
app/Http/Requests/Investment/StoreRiskProfileRequest.php
app/Http/Requests/Investment/UpdateHoldingRequest.php
app/Http/Requests/Investment/UpdateInvestmentGoalRequest.php
app/Http/Requests/Estate/CalculateIntestacyRequest.php
app/Http/Requests/Estate/StoreBequestRequest.php
app/Http/Requests/Estate/StoreWillRequest.php
app/Http/Requests/Estate/UpdateBequestRequest.php
app/Http/Resources/BusinessInterestResource.php
app/Http/Resources/ChattelResource.php
app/Http/Resources/GoalContributionResource.php
app/Http/Resources/GoalResource.php
app/Http/Resources/HoldingResource.php
app/Http/Resources/InvestmentAccountResource.php
app/Http/Resources/MortgageResource.php
app/Http/Resources/PropertyResource.php
app/Http/Resources/SavingsAccountResource.php
app/Http/Resources/UserResource.php
app/Services/Estate/IHTFormattingService.php
public/images/logoMain.png
```

### Modified Files (101 total)

```text
app/Agents/CoordinatingAgent.php
app/Agents/EstateAgent.php
app/Agents/GoalsAgent.php
app/Agents/InvestmentAgent.php
app/Agents/ProtectionAgent.php
app/Agents/RetirementAgent.php
app/Agents/SavingsAgent.php
app/Http/Controllers/Api/BusinessInterestController.php
app/Http/Controllers/Api/ChattelController.php
app/Http/Controllers/Api/DashboardController.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Controllers/Api/Estate/WillController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/GoalsController.php
app/Http/Controllers/Api/HolisticPlanningController.php
app/Http/Controllers/Api/InvestmentController.php
app/Http/Controllers/Api/MortgageController.php
app/Http/Controllers/Api/NetWorthController.php
app/Http/Controllers/Api/PropertyController.php
app/Http/Controllers/Api/ProtectionController.php
app/Http/Controllers/Api/RecommendationsController.php
app/Http/Controllers/Api/RetirementController.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Resources/BusinessInterestResource.php
app/Http/Resources/ChattelResource.php
app/Http/Resources/GoalContributionResource.php
app/Http/Resources/GoalResource.php
app/Http/Resources/MortgageResource.php
app/Http/Resources/PropertyResource.php
app/Http/Resources/SavingsAccountResource.php
app/Models/AuditLog.php
app/Models/DataExport.php
app/Models/Document.php
app/Models/DocumentExtractionLog.php
app/Models/ErasureRequest.php
app/Models/Goal.php
app/Models/GoalContribution.php
app/Models/Investment/Holding.php
app/Models/Investment/InvestmentRecommendation.php
app/Models/Investment/InvestmentScenario.php
app/Models/Investment/RebalancingAction.php
app/Models/LoginAttempt.php
app/Models/OnboardingProgress.php
app/Models/RecommendationTracking.php
app/Models/UserConsent.php
app/Models/UserSession.php
app/Providers/AppServiceProvider.php
app/Services/Auth/PasswordResetService.php
app/Services/Benefits/ChildBenefitService.php
app/Services/Business/BusinessInterestService.php
app/Services/Chattel/ChattelCGTService.php
app/Services/Coordination/ConflictResolver.php
app/Services/Coordination/RecommendationsAggregatorService.php
app/Services/Dashboard/DashboardAggregator.php
app/Services/Documents/AIExtractionService.php
app/Services/Documents/DocumentProcessor.php
app/Services/Estate/CashFlowProjector.php
app/Services/Estate/ComprehensiveEstatePlanService.php
app/Services/Estate/FutureValueCalculator.php
app/Services/Estate/GiftingStrategyOptimizer.php
app/Services/Estate/IHTCalculationService.php
app/Services/Estate/IHTCalculator.php
app/Services/Estate/IHTStrategyGeneratorService.php
app/Services/Estate/PersonalizedGiftingStrategyService.php
app/Services/Estate/PersonalizedTrustStrategyService.php
app/Services/Estate/SpouseNRBTrackerService.php
app/Services/Estate/TrustService.php
app/Services/Estate/WillAnalysisService.php
app/Services/GDPR/DataErasureService.php
app/Services/GDPR/DataExportService.php
app/Services/Goals/GoalAssignmentService.php
app/Services/Goals/GoalsProjectionService.php
app/Services/Investment/AssetLocation/AssetLocationOptimizer.php
app/Services/Investment/AssetLocation/TaxDragCalculator.php
app/Services/Investment/ContributionEstimatorService.php
app/Services/Investment/InvestmentProjectionService.php
app/Services/Investment/PortfolioStrategyService.php
app/Services/Investment/Tax/BedAndISACalculator.php
app/Services/Investment/Tax/CGTHarvestingCalculator.php
app/Services/Investment/Tax/ISAAllowanceOptimizer.php
app/Services/Investment/Tax/TaxOptimizationAnalyzer.php
app/Services/Investment/TaxEfficiencyCalculator.php
app/Services/Property/PropertyTaxService.php
app/Services/Retirement/AnnualAllowanceChecker.php
app/Services/Retirement/ContributionOptimizer.php
app/Services/Retirement/PensionProjector.php
app/Services/Retirement/RequiredCapitalCalculator.php
app/Services/Retirement/RetirementIncomeService.php
app/Services/Retirement/RetirementProjectionService.php
app/Services/Retirement/RetirementStrategyService.php
app/Services/Risk/AutoRiskCalculator.php
app/Services/Savings/ISATracker.php
app/Services/Settings/AssumptionsService.php
app/Services/Tax/TaxProductInfoService.php
app/Services/Trust/IHTPeriodicChargeCalculator.php
app/Services/UKTaxCalculator.php
app/Services/UserProfile/UserProfileService.php
resources/js/components/BugReportModal.vue
resources/js/components/Dashboard/ActionsOverviewCard.vue
resources/js/components/Navbar.vue
resources/js/components/NetWorth/WealthSummary.vue
resources/js/views/Dashboard.vue
resources/views/emails/deletion-verification-code.blade.php
resources/views/emails/password-reset-code.blade.php
resources/views/emails/spouse-account-created.blade.php
resources/views/emails/spouse-account-linked.blade.php
resources/views/emails/verification-code.blade.php
```

---

## Build Instructions

Run locally before uploading:

```bash
./deploy/fynla-org/build.sh
```

Then upload `public/build/` directory to server.

---

## Post-Upload Commands

After uploading files, SSH to server and run:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

## Files NOT to Upload (Development Only)

```text
tests/Pest.php
tests/Unit/Agents/BaseAgentTest.php
tests/Unit/Agents/GoalsAgentTest.php
tests/Unit/Agents/ProtectionAgentTest.php
tests/Unit/Agents/SavingsAgentTest.php
```

---

### 10. Net Worth Wealth Summary Layout Redesign

**Status:** Deployed

**Description:** Redesigned Wealth Summary tab layout with side-by-side arrangement and improved mobile responsiveness.

**What was done:**

- Sidebar auto-collapses on Wealth Summary tab only for users with spouse data
- Added watcher on `hasSpouse` to re-evaluate sidebar collapse when spouse data loads
- WealthSummary card now on left, asset allocation charts stacked vertically on right
- Fixed mobile overflow issue with grid columns using `minmax(0, 1fr)`
- Single user layout: value column positioned on right side of card (not stretched full width)
- Reduced whitespace on table headings and section dividers
- Right-aligned column headers and all values
- Removed horizontal scrollbar from wealth summary table
- Responsive: on tablet/mobile, charts stack below and display horizontally

**Modified Files (3):**

```text
resources/js/views/NetWorth/NetWorthDashboard.vue
resources/js/components/NetWorth/NetWorthWealthSummary.vue
resources/js/components/NetWorth/WealthSummary.vue
```

---

### 11. Business Interests Fixes

**Status:** Deployed

**Description:** Fixed incorrect "Your Share" values and 500 error when viewing business interest details.

**What was done:**

- Fixed `calculateUserShare` to handle partial individual ownership (e.g., owning 60% of a company)
- Previously, 'individual' ownership always returned 100% of value, ignoring ownership_percentage
- Now correctly calculates: Chen Tech Consulting £750k × 60% = £450k, TechAngel Ventures £400k × 25% = £100k
- Fixed `is_shared` flag to be true when ownership_percentage < 100 (not just for 'joint' ownership type)
- Fixed 500 error in BusinessInterestResource when jointOwner relationship is loaded but null
- Changed Tax Deadlines tab to 3-column responsive grid layout

**Modified Files (4):**

```text
app/Services/Business/BusinessInterestService.php
app/Http/Controllers/Api/BusinessInterestController.php
app/Http/Resources/BusinessInterestResource.php
resources/js/components/NetWorth/BusinessInterestDetailInline.vue
```

---

### 12. Goals Projection Chart - Cap at Retirement for Young Users

**Status:** Deployed

**Description:** For users with more than 25 years to retirement, the Goals & Life Events projection chart now ends at retirement age instead of extending to age 90.

**What was done:**

- Modified `getProjectionEndAge()` to check years until retirement
- If years to retirement > 25, cap projection at retirement age
- If years to retirement ≤ 25, continue to project to age 90 (life expectancy)
- Example: John Morgan (age 25, retires at 67) now sees chart ending at 67 instead of 90
- Example: David Mitchell (age 49, retires at 60) still sees chart to age 90

**Modified Files (1):**

```text
app/Services/Goals/GoalsProjectionService.php
```

### 13. Protection Recommendation Logic - Dependant-Aware Text

**Status:** Deployed

**Description:** Fixed protection recommendations to only suggest life cover for users with dependants, and made recommendation text context-aware based on family status.

**What was done:**

- RecommendationEngine: Life insurance gap only triggers recommendation if user has dependants
- ComprehensiveProtectionPlanService: Life insurance strategy only generated if user has dependants; headline messages now conditional (emotive "protect your family" for those with dependants, generic text for others)
- AreasToConsiderCard: Will description shows "Protect your family's future" for married users, "Ensure your wishes are followed for your assets" for single users
- AreasToConsiderCard: Life insurance suggestion only shown for married users (who likely have family to protect)
- AdequacyScorer: Score insights now conditional - "protect your family" for users with dependants, "improve your financial security" for those without
- ScenarioBuilder: Made dependant-related text conditional based on actual dependant count
- ProtectionAgent: Updated to pass dependants info to AdequacyScorer

**Modified Files (6):**

```text
app/Services/Protection/RecommendationEngine.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
app/Services/Protection/AdequacyScorer.php
app/Services/Protection/ScenarioBuilder.php
app/Agents/ProtectionAgent.php
resources/js/components/Dashboard/AreasToConsiderCard.vue
```

---

### 14. Rent Expense Category & Property Suggestion Logic

**Status:** Deployed

**Description:** Added rent and utilities as expense categories. Property suggestions now hide for renters.

**What was done:**

- Added `rent` and `utilities` columns to users table
- Updated User model with float casts for new columns
- Updated PreviewUserSeeder to map `housing` → `rent` and handle `utilities`
- Updated young_saver.json to use `rent: 650` instead of `housing`
- Added "Your Properties" suggestion to AreasToConsiderCard (priority 11)
- Property suggestion only shows if user has NO properties AND is NOT paying rent
- Added home icon SVG to AreasToConsiderCard for properties
- Updated ModuleDataRequirementsService: properties relationship counts as "filled" if user pays rent

**New Files (1):**

```text
database/migrations/2026_02_05_120000_add_rent_and_utilities_to_users_table.php
```

**Modified Files (6):**

```text
app/Models/User.php
app/Services/UserProfile/ModuleDataRequirementsService.php
database/seeders/PreviewUserSeeder.php
resources/js/components/Dashboard/AreasToConsiderCard.vue
resources/js/components/UserProfile/ExpenditureForm.vue
resources/js/data/personas/young_saver.json
```

---

### 15. Retirement Dashboard Card for Retired Users

**Status:** Deployed

**Description:** Dashboard retirement card now shows actual retirement income breakdown for retired users instead of projected capital figures.

**What was done:**

- Dashboard retirement card detects retired status via `employment_status === 'retired'`
- Retired users see "Retirement Income" card with income sources breakdown:
  - Pension Drawdown (DC wealth × 4% SWR)
  - DB Pension (accrued annual pension)
  - State Pension (configured or £11,502 default)
  - Total Income, Income Need, and Surplus/Shortfall
- Non-retired users continue to see projections (Projected Income, Required Income, Capital figures)
- Removed unused orphan components: `RetirementOverviewCard.vue` (both Dashboard and Retirement versions)

**Modified Files (1):**

```text
resources/js/views/Dashboard.vue
```

**Deleted Files (2):**

```text
resources/js/components/Dashboard/RetirementOverviewCard.vue
resources/js/components/Retirement/RetirementOverviewCard.vue
```

---

### 16. Pensions View Improvements for Retired Users

**Status:** Deployed

**Description:** Improved pensions section for retired users and users with only DB/State pensions. Hides irrelevant contribution strategies and Monte Carlo projections, shows detailed guaranteed income summary instead.

**What was done:**

- Hide "Recommended Strategies" section for retired users (contribution strategies don't apply)
- Hide "Recommended Strategies" for users without DC pensions (can't increase contributions to non-existent pensions)
- Replace blank Monte Carlo screen with "Guaranteed Income Summary" for users with only DB/State pensions:
  - Shows total annual guaranteed income
  - Detailed breakdown per DB pension (annual pension, lump sum, retirement age, spouse pension %)
  - State pension details (annual amount, state pension age, NI years)
  - Income vs Need comparison with surplus/shortfall indicator
- Hide "DC fund depletes at age X" warning badge for retired users
- Mark "money purchase pensions" as filled in "What powers this view" for retired users (they don't need DC pensions)

**Modified Files (2):**

```text
resources/js/components/NetWorth/PensionList.vue
app/Services/UserProfile/ModuleDataRequirementsService.php
```

---

### 17. Other Areas to Consider & What Powers This View - Smart Filtering

**Status:** Deployed

**Description:** Fixed "Other Areas to Consider" and "What powers this view" to not show inappropriate suggestions based on age, retirement status, existing data, and pension income.

**What was done:**

**AreasToConsiderCard.vue (Other Areas to Consider):**

- **Will**: Fixed API endpoint from `/api/will` to `/api/estate/will` - no longer suggests for users who already have a will
- **Income Details**: No longer suggests for users with pension income (state pension, DB pension, or DC pensions for retired users)
- **Critical Illness**: Only suggest for users aged 50 or under (and not retired)
- **Life Insurance**: Only suggest for non-retired users
- **Income Protection**: Only suggest for non-retired users (they have guaranteed pension income)
- **Properties**: Fixed to use `overview.breakdown.property` instead of `properties` array (which was never loaded)
- Added `isRetired` and `userAge` computed properties
- Added `loadWillData()` method to fetch will status from estate API
- Changed mapState from `properties` to `overview` for netWorth store

**ModuleDataRequirementsService.php (What powers this view):**

- **Income field**: Now considers pension income as valid income - users with State Pension, DB Pension, or DC Pensions (for retired users) no longer see "Your annual income" as missing
- Added checks for `hasStatePensionIncome`, `hasDBPensionIncome`, `hasDCPensionIncome` in `isFieldFilled()` method

**Modified Files (2):**

```text
resources/js/components/Dashboard/AreasToConsiderCard.vue
app/Services/UserProfile/ModuleDataRequirementsService.php
```

---

### 18. IHT Calculator - Widow Transferred Allowances

**Status:** Deployed

**Description:** Fixed IHT calculator to show combined NRB and RNRB allowances for widows who have inherited their late spouse's unused allowances.

**The Problem:**
- Widows were only seeing single NRB (£325k) and RNRB (£175k) totalling £500k
- UK law allows widows to claim unused NRB and RNRB from their late spouse's estate
- Margaret Thompson (widow persona) should have £650k NRB + £350k RNRB = £1,000,000 total allowances

**What was done:**

**IHTCalculationService.php:**
- Added `$isWidowed` check for `marital_status === 'widowed'`
- Added `IHTProfile` lookup to get transferred NRB and RNRB amounts
- NRB calculation now adds `nrb_transferred_from_spouse` for widows
- RNRB calculation now adds `rnrb_transferred_from_spouse` for widows
- Updated NRB message: "Combined Nil Rate Band of £650,000 available (own £325,000 + £325,000 transferred from late spouse's estate)"
- Updated RNRB message: "Full Residence Nil Rate Band of £350,000 available (own £175,000 + £175,000 transferred from late spouse's estate)"
- Updated `calculateRNRB()` method signature to accept `$isWidowed` and `$ihtProfile` parameters

**IHTProfile Model:**
- Added `rnrb_transferred_from_spouse` to `$fillable` and `$casts` arrays

**Database Migration:**
- Created `2026_02_05_150000_add_rnrb_transferred_to_iht_profiles_table.php`
- Adds `rnrb_transferred_from_spouse` column to `iht_profiles` table

**PreviewUserSeeder.php:**
- Added `IHTProfile` import
- Added `createIHTProfiles()` method to seed IHT profiles from persona JSON
- Added IHTProfile deletion to `deleteUserData()` cleanup method
- Widow persona now gets IHT profile with `transferred_nrb: 325000` and `transferred_rnrb: 175000`

**New Files (1):**

```text
database/migrations/2026_02_05_150000_add_rnrb_transferred_to_iht_profiles_table.php
```

**Modified Files (4):**

```text
app/Models/Estate/IHTProfile.php
app/Services/Estate/IHTCalculationService.php
database/seeders/PreviewUserSeeder.php
```

---

### 19. Young Saver Persona - LISA as Investment Account

**Status:** Deployed

**Description:** Moved John Morgan's Lifetime ISA from savings to investments, making it a Stocks & Shares LISA with holdings for the Investment tab.

**What was done:**

- Removed Cash LISA from `savings_accounts` array
- Added Stocks & Shares LISA to `investment_accounts` array with:
  - Provider: Moneybox
  - Value: £2,400
  - Monthly contribution: £150
  - Risk preference: lower_medium (suitable for 4-5 year house deposit goal)
  - Platform fee: 0.45%
- Added 2 fund holdings:
  - Moneybox Global Shares Fund (80% allocation)
  - Moneybox UK Shares Fund (20% allocation)
- Student loan and NEST pension were already configured correctly

**Bug Fix - Lazy Loading Violation:**
- Fixed `InvestmentAgent.php` line 39 to eager load `holdings` relationship
- Error: `Attempted to lazy load [holdings] on model [InvestmentAccount] but lazy loading is disabled`
- Solution: Changed `InvestmentAccount::where(...)->get()` to `->with('holdings')->get()`

**Modified Files (2):**

```text
resources/js/data/personas/young_saver.json
app/Agents/InvestmentAgent.php
```

---

### 20. Wealth Summary Reactivity Fix

**Status:** Deployed

**Description:** Fixed Wealth Summary table not updating when assets or liabilities are changed anywhere in the Net Worth module.

**The Problem:**
- When users created, updated, or deleted assets/liabilities in ANY Net Worth section (property, investments, cash, business, chattels, retirement), the Wealth Summary table did not reflect the changes
- The `refreshNetWorth` action was calling the `/net-worth/refresh` endpoint but not fetching spouse data
- For married users, the spouse column showed stale data

**Root Cause:**
- The `/net-worth/refresh` endpoint invalidates cache and returns recalculated data
- However, it does NOT return `spouse_data` like the `/net-worth/overview` endpoint does
- The `refreshNetWorth` action was only using the refresh response, leaving `spouseOverview` with stale data

**What was done:**

**netWorth.js Vuex Store:**
- Modified `refreshNetWorth` action to call BOTH endpoints:
  1. First calls `/net-worth/refresh` to invalidate cache and recalculate
  2. Then calls `/net-worth/overview` to get complete data including spouse
- Now properly commits both `SET_OVERVIEW` and `SET_SPOUSE_OVERVIEW`
- Ensures complete data sync after any asset/liability CRUD operation

**Modified Files (1):**

```text
resources/js/store/modules/netWorth.js
```

---

### 21. Business Interest Share Calculation in Wealth Summary

**Status:** Deployed

**Description:** Fixed Wealth Summary showing total business value instead of user's share for partial ownership.

**The Problem:**
- Alex Chen owns 60% of Chen Tech Consulting (£750k total) and 25% of TechAngel Ventures (£400k total)
- Wealth Summary was showing £1,150,000 (full values) instead of £550,000 (his share: £450k + £100k)
- The generic `CalculatesOwnershipShare` trait treated `individual` ownership as 100% ownership
- But for business interests, `individual` means "owned by an individual person", not "100% shareholding"

**Root Cause:**
- `NetWorthService::calculateBusinessValue()` uses the `CalculatesOwnershipShare` trait
- The trait's `calculateUserShare()` method returned full value for `individual` ownership type
- Business interests differ: `ownership_percentage` represents actual shareholding regardless of `ownership_type`

**What was done:**

**CalculatesOwnershipShare.php trait:**
- Added detection for business interests via `current_valuation` AND `business_name` fields
- Business interests now always apply `ownership_percentage` (represents shareholding)
- Individual ownership: `fullValue * (ownership_percentage / 100)`
- Non-business assets maintain original behavior: `individual` = 100%

**Example calculation now correct:**
- Chen Tech Consulting: £750,000 × 60% = £450,000 ✓
- TechAngel Ventures: £400,000 × 25% = £100,000 ✓
- Total Business in Wealth Summary: £550,000 ✓

**Modified Files (1):**

```text
app/Traits/CalculatesOwnershipShare.php
```

---

### 22. Widow NRB/RNRB Allowances Breakdown

**Status:** Deployed

**Description:** Show separate breakdown of inherited NRB and RNRB allowances for widows like Margaret Thompson.

**The Problem:**
- Widows with transferred allowances only saw combined totals (e.g., £650k NRB, £350k RNRB)
- No visibility into how much was their own vs inherited from late spouse
- Margaret Thompson should see: Own £325k + Transferred £325k = Total £650k NRB

**What was done:**

**Backend - IHTCalculationService.php:**
- Added `nrb_individual` and `nrb_transferred` to the result array
- Added `rnrb_individual` and `rnrb_transferred` to the RNRB calculation
- Added `is_widowed` flag to the result array
- All return statements in `calculateRNRB()` now include individual/transferred breakdown

**Backend - IHTController.php:**
- Added new breakdown fields to `iht_summary.current` response
- Added `is_widowed` flag to `iht_summary` response

**Frontend - IHTPlanning.vue:**
- Updated `ihtData` mapping to use new breakdown fields from API
- Added `is_widowed` to the data object
- Updated `allowances` object in `ihtCalcTableProps` to use breakdown values
- Set `showSeparateSpouseAllowances: true` for widows with transferred allowances

**Frontend - IHTCalculationTable.vue:**
- Added new template case for widows (not showSpouse but showSeparateSpouseAllowances)
- NRB breakdown shows: "[Name] Tax-Free Allowance" and "Transferred from Late Spouse's Estate"
- RNRB breakdown shows: "[Name] Home Allowance" and "Transferred from Late Spouse's Estate"

**Example breakdown now shown:**
- Margaret's Tax-Free Allowance: £325,000
- Transferred from Late Spouse's Estate: £325,000
- Margaret's Home Allowance: £175,000
- Transferred from Late Spouse's Estate: £175,000
- **Total Allowances: £1,000,000**

**Modified Files (4):**

```text
app/Services/Estate/IHTCalculationService.php
app/Http/Controllers/Api/Estate/IHTController.php
resources/js/components/Estate/IHTCalculationTable.vue
resources/js/components/Estate/IHTPlanning.vue
```

---

## Deployment Summary (Sections 15-22)

### 1. Build Locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

**Frontend Build:**

```text
public/build/ → ~/www/fynla.org/public_html/public/build/
```

**PHP Files:**

```text
app/Agents/InvestmentAgent.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Models/Estate/IHTProfile.php
app/Services/Estate/IHTCalculationService.php
app/Services/UserProfile/ModuleDataRequirementsService.php
app/Traits/CalculatesOwnershipShare.php
database/migrations/2026_02_05_150000_add_rnrb_transferred_to_iht_profiles_table.php
database/seeders/PreviewUserSeeder.php
resources/js/data/personas/young_saver.json
resources/js/store/modules/netWorth.js
```

### 3. Delete on Server

```text
resources/js/components/Dashboard/RetirementOverviewCard.vue
resources/js/components/Retirement/RetirementOverviewCard.vue
```

### 4. SSH - Run Migration, Reseed, and Clear Caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan db:seed --class=PreviewUserSeeder --force
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

### 23. UI Cleanup, Data Requirements Fix, and Retirement Planner Improvements

**Status:** Deployed

**Description:** Multiple improvements: Fix domicile status check, hide methodology tables, rename retirement planners, improve planner card UI, and fix persona data.

**What was done:**

**1. Domicile Status Check Fix (ModuleDataRequirementsService.php):**
- Added explicit handling for `domicile_status` field in `isFieldFilled()` method
- Returns true if value is not null, not empty string, and not 'not_set'
- Fixes "What powers this view" showing domicile as missing when user has entered it

**2. Hide Cash Projection Methodology Table (IHTPlanning.vue):**
- Hidden the "Cash Projection Methodology" table from view
- Logic and calculations are retained (just not displayed)
- Added `v-if="false"` to the containing div

**3. Hide Fund Depletion Year-by-Year Table (FundDepletionChart.vue):**
- Hidden the "Year-by-Year Table" inside the Fund Depletion card
- User now only sees the Fund Depletion area chart (graph)
- Logic and calculations are retained (just not displayed)
- Added `v-if="false"` to the containing div

**4. Rename Retirement Planners:**
- "Retirement Income Planner" → "Will I have enough income for retirement?"
  - Updated in PensionList.vue (planner card heading)
  - Updated in RetirementIncomeTab.vue (section title)
- "Capital Adequacy Planner" → "Am I saving enough for retirement?"
  - Updated in PensionList.vue (planner card heading)
  - Updated in CapitalAdequacyTab.vue (section title)

**5. Remove Arrow Icons from Planner Cards (PensionList.vue):**
- Removed the `>` chevron icon from top-right corner of both planner cards
- Cards now have cleaner appearance without navigation arrows

**6. Projected Net Income Conditional Styling (PensionList.vue):**
- Projected net income value now turns red if below 90% of target income
- Uses conditional class binding: `projectedNetIncome >= targetIncome * 0.9 ? 'green' : 'red'`

**7. Capital Adequacy Card Label Updates (PensionList.vue):**
- "Required Capital" → "You will need about:"
- "Projected Capital" → "Current pension potential growth:"
- "Allowance Used" → "Annual Pension Allowance"

**8. Fix Domicile Data in Persona JSON Files:**
- Added proper `domicile` objects to persona JSON files
- PreviewUserSeeder expects nested `domicile.country_of_birth` and `domicile.domicile_status`
- Updated: widow.json, young_family.json, young_saver.json, peak_earners.json

**9. Rename Pension Type Badge:**
- Changed "Occupational" badge to "Work Pension" for clearer terminology
- Updated in PensionList.vue and PensionDetailInline.vue

**10. Pension Pot Projection Title Enhancement:**
- Added probability and return info to chart title
- Now shows: "Pension Pot Projection (using high probability of 80% of achieving X% returns)"
- The X% is dynamically populated from `expected_return` value
- Bracketed text styled smaller with `text-sm font-normal`
- Updated in PensionList.vue and FutureValueTab.vue

**Modified Files (12):**

```text
app/Services/UserProfile/ModuleDataRequirementsService.php
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Retirement/FundDepletionChart.vue
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/Retirement/CapitalAdequacyTab.vue
resources/js/components/Retirement/FutureValueTab.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/PensionDetailInline.vue
resources/js/data/personas/widow.json
resources/js/data/personas/young_family.json
resources/js/data/personas/young_saver.json
resources/js/data/personas/peak_earners.json
```

---

## Deployment Summary (Section 23)

### 1. Build Locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

**Frontend Build:**

```text
public/build/ → ~/www/fynla.org/public_html/public/build/
```

**PHP Files:**

```text
app/Services/UserProfile/ModuleDataRequirementsService.php
```

**Persona JSON Files:**

```text
resources/js/data/personas/widow.json
resources/js/data/personas/young_family.json
resources/js/data/personas/young_saver.json
resources/js/data/personas/peak_earners.json
```

### 3. SSH - Reseed and Clear Caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan db:seed --class=PreviewUserSeeder --force
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

### 24. Goals & Life Events Module Improvements

**Status:** Deployed

**Description:** Multiple improvements to the Goals & Life Events module including renamed headings, enhanced Life Events summary card, and chart visibility for new users.

**What was done:**

**1. Rename Module Heading (GoalsDashboard.vue):**

- Changed "Goals & Financial Planning" to "Goals & Life Events"

**2. Rename Summary Card Headings (ProjectionSummaryCards.vue):**

- "Projected at {age}" → "Projected Net Worth at {age}"
- "Peak Net Worth" → "Projected Peak Net Worth"

**3. Life Events Card Enhancement (ProjectionSummaryCards.vue):**

- Removed "X planned" count display
- Added "{count} cash inflow events {value}" line
- Added "{count} cash outflow events {value}" line
- Right-aligned the currency values
- Added `income_event_count` and `expense_event_count` to computed defaults

**4. Backend: Add Event Counts to Summary (GoalsProjectionService.php):**

- Added `income_event_count` - count of income life events
- Added `expense_event_count` - count of expense life events
- Updated both empty summary return and calculated summary return

**5. Chart Always Visible for New Users (GoalsOverview.vue):**

- Removed conditional that hid chart when no goals existed
- Chart now always shows (projection based on net worth)
- Added prompt below chart: "Add Your First Goal or Life Event" when no goals
- Goals content (Top Goals, Status Summary) still conditional on having goals

**6. Refresh Projection on Goal CRUD (goals.js store):**

- Added `dispatch('fetchProjection')` to `createGoal` action
- Added `dispatch('fetchProjection')` to `updateGoal` action
- Added `dispatch('fetchProjection')` to `deleteGoal` action
- Chart now updates when goals are created, updated, or deleted

**Modified Files (6):**

```text
app/Services/Goals/GoalsProjectionService.php
resources/js/views/Goals/GoalsDashboard.vue
resources/js/components/Goals/GoalsOverview.vue
resources/js/components/Goals/ProjectionSummaryCards.vue
resources/js/store/modules/goals.js
```

---

## Deployment Summary (Section 24)

### 1. Build Locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

**Frontend Build:**

```text
public/build/ → ~/www/fynla.org/public_html/public/build/
```

**PHP Files:**

```text
app/Services/Goals/GoalsProjectionService.php
```

### 3. SSH - Clear Caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
