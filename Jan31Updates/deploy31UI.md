# Deployment Notes - January 31, 2026 (UI Color Fix)

**Branch:** uiFix

**Deployment Status:** ✅ DEPLOYED

**Rebuild Required:** YES - Frontend build required

---

## Expenditure - Expandable Financial Commitments

Added nested expandable rows in the Financial Commitments section so users can drill down to see individual items.

### Changes Made

| Change | Description |
|--------|-------------|
| New component | Created `ExpenditureExpandableGridRow.vue` for expandable category rows |
| Nested expansion | Clicking a category (e.g., Property Expenses) expands to show individual items |
| Third level | Clicking a property expands to show expense breakdown (Mortgage, Council Tax, Utilities, etc.) |
| Item details | Shows each property, investment, policy with its monthly cost |
| Joint ownership | Displays ownership percentage for joint assets |

### User Flow
1. Click "Financial Commitments" → shows categories (Property Expenses, Investment Contributions, etc.)
2. Click on a category → expands to show individual items with amounts
3. Click on a property → expands to show expense breakdown (Mortgage, Council Tax, Utilities, etc.)

### Consistency Across Tabs
- **Current Budget**: Full expandable view with all commitments
- **Retired Budget**: Shows adjustments (struck-through removed items), Protection Premiums expandable
- **Widowed Budget**: Single-person view with expandable Property, Protection, and Loans

### Files Changed
```
resources/js/components/UserProfile/ExpenditureExpandableGridRow.vue (new)
resources/js/components/UserProfile/ExpenditureForm.vue
```

---

## Onboarding Progress Bar - Fix Skipped Step Display

Fixed bug where skipping the Expenditure step during onboarding would incorrectly mark it as completed (green) instead of skipped (blue) in the progress bar.

### Bug Description
When a user skipped the Expenditure step, the `confirmSkip` function emitted `'next'` instead of properly marking the step as skipped in the store.

### Fix
Changed `confirmSkip()` to directly dispatch `onboarding/skipStep` and `onboarding/goToNextStep` actions, which properly adds the step to the `skippedSteps` array.

### Files Changed
```
resources/js/components/Onboarding/steps/ExpenditureStep.vue
```

---

## Property Financials Tab Improvements

### Changes Made

| File | Change |
|------|--------|
| PropertyFinancials.vue | Removed "Income & Occupation" reference - now says "income calculations for UK tax purposes" |
| PropertyFinancials.vue | Converted shared ownership note from blue box to hover tooltip on info icon next to "Monthly Costs" heading |

### Files Changed
```
resources/js/components/NetWorth/Property/PropertyFinancials.vue
```

---

## Investment Portfolio Projections - Remove Individual Account Projections

### Changes Made

Removed the "Individual Account Projections" section from the Portfolio Projections view. This section showed projection charts for each individual investment account, which cluttered the view.

The view now focuses on:
- Portfolio-level projection chart (Monte Carlo simulation)
- Tax Efficiency summary
- Holdings summary with asset allocation donut chart
- Total Fees summary

### Files Changed
```
resources/js/components/NetWorth/InvestmentProjections.vue
```

---

## Wealth Summary - Fix Combined Chart Heading

Changed the combined pie chart heading from "Combined Wealth Allocation" to "Combined Asset Allocation" for consistency.

### Files Changed
```
resources/js/components/NetWorth/NetWorthWealthSummary.vue
```

---

## Protection Module - Life Insurance Coverage Card Styling

Improved the "Your Existing Life Insurance Coverage" card layout for better visual alignment.

### Changes Made

| Change | Description |
|--------|-------------|
| Removed background | Removed gray background box from "Total Life Insurance" row |
| Column alignment | All currency values now align in a neat right-aligned column |
| Visual hierarchy | Added subtle border separator between total and breakdown items |

### Files Changed
```
resources/js/components/Protection/CurrentSituation.vue
```

---

## Property Details - Add Owner Names for Joint/Tenants in Common

Added owner names display in the property details view for properties with shared ownership (joint or tenants in common).

### Changes Made

| Change | Description |
|--------|-------------|
| Owner names display | Shows both owners with their respective ownership percentages for joint/tenants in common properties |
| Ownership type formatting | Added proper formatting for ownership types (e.g., "tenants_in_common" → "Tenants in Common") |
| Individual ownership | Shows single owner name with 100% for individual ownership |

### Example Display
For a joint property:
- Ownership Type: Joint
- David Mitchell: 60%
- Sarah Mitchell: 40%

### Files Changed
```
resources/js/components/NetWorth/Property/PropertyDetailInline.vue
```

---

## Investment Bonds - Add Purchase Date and 5% Withdrawal Fields

Added optional bond-specific fields for onshore and offshore bond accounts.

### New Fields

| Field | Type | Description |
|-------|------|-------------|
| Bond Purchase Date | Date | Date the bond was purchased (used to calculate 5% withdrawal allowance) |
| 5% Withdrawal Taken | Currency | Total amount of tax-deferred 5% annual withdrawals taken to date |

### Features
- Fields only appear when account type is "Onshore Bond" or "Offshore Bond"
- Both fields are optional (not required)
- Info box explains the 5% annual withdrawal rule and carry-forward allowance
- Data stored in `investment_accounts` table

### Files Changed
```
database/migrations/2026_01_31_135615_add_bond_fields_to_investment_accounts_table.php (new)
app/Models/Investment/InvestmentAccount.php
resources/js/components/Investment/AccountForm.vue
resources/js/components/Investment/StandardInvestmentFields.vue
```

---

## Orange/Amber Color Removal

Removed all orange/amber colors from the codebase per the design system rules in `designStyle.md`. Orange and amber are banned colors - replaced with blue for warnings/cautions, and appropriate semantic colors for other use cases.

### Color Replacements Made

| Original | Replacement | Context |
|----------|-------------|---------|
| `orange-*` | `blue-*` | Warnings, cautions, medium priority |
| `orange-*` | `green-*` | Bond type badges |
| `orange-*` | `indigo-*` | Trust ownership badges |
| `orange-*` | `red-*` | Taxable status indicators |

---

## Files Changed (179 files)

### CSS Files (2 files)
```
resources/css/app.css
resources/css/badges.css
```

### Config (1 file)
```
tailwind.config.js
```

### Auth Components (2 files)
```
resources/js/components/Auth/ChangePasswordModal.vue
resources/js/components/Auth/MFASetupModal.vue
```

### Cash Components (1 file)
```
resources/js/components/Cash/CashActionsPanel.vue
```

### Common Components (2 files)
```
resources/js/components/Common/ConfirmationModal.vue
resources/js/components/Common/TaxStatusPanel.vue
```

### Dashboard Components (10 files)
```
resources/js/components/Dashboard/ActionsOverviewCard.vue
resources/js/components/Dashboard/AlertsPanel.vue
resources/js/components/Dashboard/FinancialHealthScore.vue
resources/js/components/Dashboard/GoalsOverviewCard.vue
resources/js/components/Dashboard/InvestmentsOverviewCard.vue
resources/js/components/Dashboard/NetWorthSummary.vue
resources/js/components/Dashboard/RetirementOverviewCard.vue
resources/js/components/Dashboard/TaxOptimisationCard.vue
resources/js/components/Dashboard/UKTaxesAllowancesCard.vue
```

### Estate Components (14 files)
```
resources/js/components/Estate/CashFlow.vue
resources/js/components/Estate/EstateOverviewCard.vue
resources/js/components/Estate/EstateProjectionComparison.vue
resources/js/components/Estate/GiftCard.vue
resources/js/components/Estate/GiftingStrategy.vue
resources/js/components/Estate/GiftingTimelineChart.vue
resources/js/components/Estate/IHTLiabilityGauge.vue
resources/js/components/Estate/IHTMitigationStrategies.vue
resources/js/components/Estate/IHTPlanning.vue
resources/js/components/Estate/IntestacyRules.vue
resources/js/components/Estate/LifeCoverRecommendations.vue
resources/js/components/Estate/LifePolicyStrategy.vue
resources/js/components/Estate/MissingDataAlert.vue
resources/js/components/Estate/NRBRNRBTracker.vue
resources/js/components/Estate/TrustForm.vue
resources/js/components/Estate/TrustPlanning.vue
resources/js/components/Estate/TrustPlanningStrategy.vue
resources/js/components/Estate/WillPlanning.vue
```

### Goals Components (9 files)
```
resources/js/components/Goals/GoalCard.vue
resources/js/components/Goals/GoalContributionStreak.vue
resources/js/components/Goals/GoalCountdown.vue
resources/js/components/Goals/GoalFormModal.vue
resources/js/components/Goals/GoalMilestoneTracker.vue
resources/js/components/Goals/GoalProgressBar.vue
resources/js/components/Goals/GoalsAnalysis.vue
resources/js/components/Goals/GoalsByModule.vue
resources/js/components/Goals/GoalsOverview.vue
```

### Holistic Components (4 files)
```
resources/js/components/Holistic/CashFlowAllocationChart.vue
resources/js/components/Holistic/ExecutiveSummary.vue
resources/js/components/Holistic/PrioritizedRecommendations.vue
resources/js/components/Holistic/RiskAssessment.vue
```

### Investment Components (36 files)
```
resources/js/components/Investment/AccountForm.vue
resources/js/components/Investment/AssetLocationOptimizer.vue
resources/js/components/Investment/BedAndISATransfers.vue
resources/js/components/Investment/BedAndISAWizardModal.vue
resources/js/components/Investment/BondWrapperInfoModal.vue
resources/js/components/Investment/CGTHarvestingOpportunities.vue
resources/js/components/Investment/ComprehensiveInvestmentPlan.vue
resources/js/components/Investment/CorrelationMatrix.vue
resources/js/components/Investment/EfficientFrontier.vue
resources/js/components/Investment/EmployeeShareSchemeFields.vue
resources/js/components/Investment/FeeBreakdown.vue
resources/js/components/Investment/FeeSavingsCalculator.vue
resources/js/components/Investment/GoalCard.vue
resources/js/components/Investment/GoalProjection.vue
resources/js/components/Investment/HarvestLossModal.vue
resources/js/components/Investment/HoldingForm.vue
resources/js/components/Investment/ISAOptimizationStrategy.vue
resources/js/components/Investment/ISATransferModal.vue
resources/js/components/Investment/InvestmentOverviewCard.vue
resources/js/components/Investment/InvestmentRecommendationsTracker.vue
resources/js/components/Investment/PlanSections/ActionPlanSection.vue
resources/js/components/Investment/PlanSections/FeeAnalysisSection.vue
resources/js/components/Investment/PlanSections/GoalProgressSection.vue
resources/js/components/Investment/PlanSections/RecommendationsSection.vue
resources/js/components/Investment/PlanSections/RiskAnalysisSection.vue
resources/js/components/Investment/PlanSections/TaxStrategySection.vue
resources/js/components/Investment/PortfolioOptimization.vue
resources/js/components/Investment/PortfolioOverview.vue
resources/js/components/Investment/RebalancingActions.vue
resources/js/components/Investment/StandardInvestmentFields.vue
resources/js/components/Investment/StrategyRecommendationCard.vue
resources/js/components/Investment/TaxEfficiencyPanel.vue
resources/js/components/Investment/TaxOptimizationOverview.vue
resources/js/components/Investment/TaxOptimizationRecommendations.vue
resources/js/components/Investment/WhatIfScenariosBuilder.vue
resources/js/components/Investment/WrapperOptimizer.vue
```

### Legal Components (1 file)
```
resources/js/components/Legal/StrategyDisclaimer.vue
```

### Navigation (1 file)
```
resources/js/components/Navbar.vue
```

### NetWorth Components (15 files)
```
resources/js/components/NetWorth/BusinessInterestCard.vue
resources/js/components/NetWorth/BusinessInterestDetailInline.vue
resources/js/components/NetWorth/ChattelCard.vue
resources/js/components/NetWorth/ChattelDetailInline.vue
resources/js/components/NetWorth/InvestmentDetailInline.vue
resources/js/components/NetWorth/InvestmentList.vue
resources/js/components/NetWorth/InvestmentProjections.vue
resources/js/components/NetWorth/JointAccountHistory.vue
resources/js/components/NetWorth/NetWorthOverview.vue
resources/js/components/NetWorth/PensionList.vue
resources/js/components/NetWorth/Property/AmortizationScheduleView.vue
resources/js/components/NetWorth/Property/PropertyDetailInline.vue
resources/js/components/NetWorth/Property/PropertyFinancials.vue
resources/js/components/NetWorth/Property/PropertyForm.vue
resources/js/components/NetWorth/PropertyCard.vue
```

### Onboarding Components (6 files)
```
resources/js/components/Onboarding/SkipConfirmationModal.vue
resources/js/components/Onboarding/steps/AssetsStep.vue
resources/js/components/Onboarding/steps/DomicileInformationStep.vue
resources/js/components/Onboarding/steps/ExpenditureStep.vue
resources/js/components/Onboarding/steps/FamilyInfoStep.vue
resources/js/components/Onboarding/steps/IncomeStep.vue
```

### Plans Components (1 file)
```
resources/js/components/Plans/InvestmentSavingsPlanView.vue
```

### Preview Components (4 files)
```
resources/js/components/Preview/PersonaIntroModal.vue
resources/js/components/Preview/PersonaSelectionModal.vue
resources/js/components/Preview/PersonaSelector.vue
resources/js/components/Preview/PreviewBanner.vue
```

### Protection Components (7 files)
```
resources/js/components/Protection/CurrentSituation.vue
resources/js/components/Protection/GapAnalysis.vue
resources/js/components/Protection/PolicyCard.vue
resources/js/components/Protection/PolicyDetail.vue
resources/js/components/Protection/ProtectionOverviewCard.vue
resources/js/components/Protection/RecommendationCard.vue
resources/js/components/Protection/Recommendations.vue
```

### Retirement Components (9 files)
```
resources/js/components/Retirement/AnnualAllowanceTracker.vue
resources/js/components/Retirement/DBPensionForm.vue
resources/js/components/Retirement/DrawdownSimulator.vue
resources/js/components/Retirement/IncomeDrawdownChart.vue
resources/js/components/Retirement/RequiredCapitalDetail.vue
resources/js/components/Retirement/StrategiesTab.vue
resources/js/components/Retirement/StrategyCard.vue
resources/js/components/Retirement/TargetIncomeDrawdownChart.vue
resources/js/components/Retirement/TaxBreakdownCard.vue
```

### Savings Components (5 files)
```
resources/js/components/Savings/CurrentSituation.vue
resources/js/components/Savings/EmergencyFund.vue
resources/js/components/Savings/Recommendations.vue
resources/js/components/Savings/SaveAccountModal.vue
resources/js/components/Savings/SavingsOverviewCard.vue
```

### Shared Components (6 files)
```
resources/js/components/Shared/ConfidenceBadge.vue
resources/js/components/Shared/DocumentUploadModal.vue
resources/js/components/Shared/ISAAllowanceSummary.vue
resources/js/components/Shared/InfoGuideButton.vue
resources/js/components/Shared/InfoGuidePanel.vue
resources/js/components/Shared/ProfileCompletenessAlert.vue
```

### Trusts Components (2 files)
```
resources/js/components/Trusts/TrustCard.vue
resources/js/components/Trusts/TrustsOverviewCard.vue
```

### UKTaxes Components (1 file)
```
resources/js/components/UKTaxes/CalculationsTab.vue
```

### UserProfile Components (13 files)
```
resources/js/components/UserProfile/AssetsOverview.vue
resources/js/components/UserProfile/CashFlowTab.vue
resources/js/components/UserProfile/CashflowView.vue
resources/js/components/UserProfile/DomicileInformation.vue
resources/js/components/UserProfile/FamilyMemberFormModal.vue
resources/js/components/UserProfile/FamilyMembers.vue
resources/js/components/UserProfile/IncomeOccupation.vue
resources/js/components/UserProfile/LetterToSpouse.vue
resources/js/components/UserProfile/PersonalInformation.vue
resources/js/components/UserProfile/ProfitAndLossView.vue
resources/js/components/UserProfile/SpouseDataSharing.vue
resources/js/components/UserProfile/TaxIncomeCard.vue
```

### Views (22 files)
```
resources/js/views/Estate/ComprehensiveEstatePlan.vue
resources/js/views/Investment/AccountDetailView.vue
resources/js/views/Investment/AccountFeesPanel.vue
resources/js/views/Investment/AccountHoldingsPanel.vue
resources/js/views/Investment/AccountRebalancingPanel.vue
resources/js/views/Investment/AccountSummaryPanel.vue
resources/js/views/Investment/EmployeeShareSchemeDetail.vue
resources/js/views/Investment/PortfolioStrategyPanel.vue
resources/js/views/Investment/PrivateInvestmentDetail.vue
resources/js/views/Login.vue
resources/js/views/Protection/ComprehensiveProtectionPlan.vue
resources/js/views/Public/CalculatorsPage.vue
resources/js/views/Public/LandingPage.vue
resources/js/views/Public/LearningCentre.vue
resources/js/views/Public/SecurityPage.vue
resources/js/views/Public/SitemapPage.vue
resources/js/views/Retirement/ContributionsAllowances.vue
resources/js/views/Retirement/DecumulationPlanning.vue
resources/js/views/Retirement/PortfolioAnalysis.vue
resources/js/views/Retirement/Projections.vue
resources/js/views/Retirement/Recommendations.vue
resources/js/views/Retirement/RetirementReadiness.vue
resources/js/views/Savings/SavingsAccountDetail.vue
resources/js/views/Savings/SavingsAccountDetailInline.vue
resources/js/views/UKTaxes/UKTaxesDashboard.vue
resources/js/views/Version.vue
```

---

## Deployment Steps

### 1. Build Frontend (Required)
```bash
./deploy/fynla-org/build.sh
```

### 2. Upload to Server

**Upload the entire build directory:**
```
public/build/
```

**Upload CSS files:**
```
resources/css/app.css
resources/css/badges.css
```

**Upload config:**
```
tailwind.config.js
```

### 3. Clear Caches (SSH)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear
```

---

## Testing Checklist

- [x] Onboarding flow - verify no orange badges when adding bonds
- [x] Protection module - verify "No Protection Coverage" notice uses blue
- [x] Dashboard - verify financial health score colors
- [x] Warning messages throughout app use blue, not orange
- [x] Medium priority badges use blue, not orange
- [x] Trust ownership badges use indigo
- [x] Bond type badges use green
- [x] Property details - verify joint/tenants in common properties show both owner names with percentages
- [x] Onboarding - skip Expenditure step and verify progress bar shows it as blue (skipped) not green (completed)
- [x] Expenditure - verify Financial Commitments categories expand to show individual items (properties, investments, etc.)
- [x] Expenditure - verify clicking a property expands to show expense breakdown (Mortgage, Council Tax, etc.)
- [x] Investment - add Onshore/Offshore Bond and verify Bond Details section appears with purchase date and withdrawal fields
- [ ] Landing page - click "Try Demo" and verify register button appears in modal
- [ ] Verify persona cards display in correct order: Carters, Mitchells, Chen, Morgan, Williams, Thompson
- [ ] In demo mode, verify preview banner button says "Signup Now"
- [ ] Expenditure - verify Retired and Widowed tab column headers are right-aligned
- [ ] Investment - add Private Company and verify BADR section appears with eligibility checkboxes
- [ ] Investment - view Private Company detail and verify BADR section shows eligibility status and CGT savings

---

---

## uiUp Branch Updates

### Persona Selection Modal - Add Register Button

Added a register button to the persona selection modal on the landing page, encouraging users to explore the demo personas before creating an account.

| Change | Description |
|--------|-------------|
| Register section | Added new section below persona grid with register button |
| Encouragement message | "We strongly encourage you to explore the personas above first to see what Fynla can do." |
| Register button | "Create Your Account" button that closes modal and navigates to /register |

**Files Changed:**
```
resources/js/components/Preview/PersonaSelectionModal.vue
```

### Persona Card Order - Reorder in Selection Modal

Reordered the persona cards in the selection modal:
1. **Carters** (young_family) - James & Emily Carter
2. **Mitchells** (peak_earners) - David & Sarah Mitchell
3. **Chen** (entrepreneur) - Alex Chen
4. **Morgan** (young_saver) - John Morgan
5. **Williams** (retired_couple) - Robert & Patricia Williams
6. **Thompson** (widow) - Margaret Thompson

**Files Changed:**
```
resources/js/store/modules/preview.js
```

### Preview Banner - Change Register Button Text

Changed the register button text in the demo preview banner from "Register to Save Your Data" to "Signup Now".

**Files Changed:**
```
resources/js/components/Preview/PreviewBanner.vue
```

### Expenditure - Align Column Headers in Retired and Widowed Tabs

Fixed column header alignment in the Retired and Widowed budget tabs to match the Current tab styling.

**Fix:** Added CSS rule for `.col-header` class to align text to the right.

**Files Changed:**
```
resources/js/components/UserProfile/ExpenditureForm.vue
```

### Business Asset Disposal Relief (BADR) - Private Company Investments

Added full BADR support for private company investments, allowing users to track eligibility and calculate potential CGT savings.

#### What is BADR?
Business Asset Disposal Relief reduces CGT to 14% (from 6 April 2025) on qualifying disposals of business assets, with a £1 million lifetime limit.

#### Qualifying Conditions
- Employee or officer of the company for 2+ years
- Trading company (not investment company)
- 5% shareholding (waived for EMI share options)
- Held shares for 2+ years before disposal

#### New Fields in Private Investment Form

| Field | Type | Description |
|-------|------|-------------|
| BADR Eligible | Toggle | Master flag to enable BADR tracking |
| Employee/Officer | Checkbox | 2+ years as employee/officer |
| Trading Company | Checkbox | Qualifies as trading company |
| 5% Shareholding | Checkbox | Holds 5%+ of shares/voting rights |
| Held 2+ Years | Checkbox | Shares held for qualifying period |
| EMI Shares | Checkbox | Acquired via EMI scheme (relaxes 5% rule) |
| Lifetime Used | Currency | Amount of £1m lifetime allowance already used |

#### Detail View Features
- BADR eligibility status badge (green if fully qualified, yellow if partial)
- Remaining lifetime allowance display
- Estimated CGT savings calculation (6% of unrealised gain)
- Qualifying conditions checklist

**Files Changed:**
```
database/migrations/2026_01_31_154201_add_badr_fields_to_investment_accounts_table.php (new)
app/Models/Investment/InvestmentAccount.php
resources/js/components/Investment/AccountForm.vue
resources/js/components/Investment/PrivateInvestmentFields.vue
resources/js/views/Investment/PrivateInvestmentDetail.vue
```

**Database Migration Required:** YES
```bash
php artisan migrate
```

---

## Design System Reference

Per `designStyle.md`, the following colors are **BANNED**:
- All `amber-*` variants
- All `orange-*` variants

Warnings and cautions should use `blue-*` instead.
