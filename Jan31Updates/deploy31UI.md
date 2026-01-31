# Deployment Notes - January 31, 2026 (UI Color Fix)

**Branch:** uiFix

**Deployment Status:** Ready for deployment

**Rebuild Required:** YES - Frontend build required

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

## Files Changed (177 files)

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

- [ ] Onboarding flow - verify no orange badges when adding bonds
- [ ] Protection module - verify "No Protection Coverage" notice uses blue
- [ ] Dashboard - verify financial health score colors
- [ ] Warning messages throughout app use blue, not orange
- [ ] Medium priority badges use blue, not orange
- [ ] Trust ownership badges use indigo
- [ ] Bond type badges use green

---

## Design System Reference

Per `designStyle.md`, the following colors are **BANNED**:
- All `amber-*` variants
- All `orange-*` variants

Warnings and cautions should use `blue-*` instead.
