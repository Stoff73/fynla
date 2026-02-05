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

