# Onboarding Form Inventory — Complete Listing by Date

All form components that exist in the codebase, grouped by when they were created.

---

## Era 1: Original Foundation (October 2025)

These are the ORIGINAL forms built when the app was first created. They are the canonical, single-source-of-truth forms used across the entire application for both standalone pages AND onboarding.

### Standalone Module Forms (used everywhere)

| File | Lines | Created | Purpose |
|------|-------|---------|---------|
| `Retirement/DCPensionForm.vue` | 628 | 14 Oct 2025 | DC pension entry/edit. Used on Retirement page AND in onboarding |
| `Protection/PolicyFormModal.vue` | 846 | 14 Oct 2025 | Protection policy entry/edit. Life, CI, IP, disability |
| `Investment/AccountForm.vue` | 1040 | 14 Oct 2025 | Investment account entry/edit. ISA, GIA, VCT, EIS, bonds |
| `Savings/SaveAccountModal.vue` | 1029 | 15 Oct 2025 | Savings account entry/edit. Cash ISA, easy access, NS&I |
| `UserProfile/PersonalInformation.vue` | 1044 | 18 Oct 2025 | Personal info entry/edit. Name, DOB, address, occupation |
| `NetWorth/Property/PropertyForm.vue` | 1969 | 18 Oct 2025 | Property entry/edit. Multi-step: basic info, ownership, mortgage, costs, BTL |

### Original Onboarding Step Components

| File | Lines | Created | Purpose | Has Cards? | Has Add Another? | Embeds Which Form? |
|------|-------|---------|---------|------------|------------------|---------------------|
| `AssetsStep.vue` | 1231 | 22 Oct 2025 | **ALL assets in one step** — tabbed view for Retirement (DC/DB/State pensions), Property, Investments, Savings. Cards for each, click to edit, add buttons per type | YES | YES | PropertyForm, DCPensionForm, DBPensionForm, AccountForm, SaveAccountModal (all as modals) |
| `PersonalInfoStep.vue` | 470 | 22 Oct 2025 | Full personal info with all fields. Name, DOB, gender, marital status, phone, address, health, smoking, occupation, employer | NO | NO | Inline fields |
| `ProtectionPoliciesStep.vue` | 439 | 22 Oct 2025 | Protection policies with cards. Shows saved policies, add/edit/remove | YES | YES | PolicyFormModal (as modal) |
| `FamilyInfoStep.vue` | 353 | 22 Oct 2025 | Family members — spouse, children, dependants. Cards for each, add/edit/remove | YES | YES | Inline form |
| `IncomeStep.vue` | 538 | 22 Oct 2025 | Income sources — employment, self-employment, dividends, interest, other | NO | NO | Inline fields |
| `LiabilitiesStep.vue` | 206 | 22 Oct 2025 | Debts and loans — student loans, credit cards, personal loans | YES | YES | Inline form |
| `WillInfoStep.vue` | 195 | 22 Oct 2025 | Will status — has will yes/no, last updated, executor, Will Builder link | NO | NO | Inline fields |
| `TrustInfoStep.vue` | 141 | 22 Oct 2025 | Trust information | NO | NO | Inline fields |
| `CompletionStep.vue` | 400 | 22 Oct 2025 | Onboarding completion screen | NO | NO | N/A |

### Later October/November Additions

| File | Lines | Created | Purpose |
|------|-------|---------|---------|
| `DomicileInformationStep.vue` | 258 | 27 Oct 2025 | UK domicile status, country of birth, deemed domicile |
| `ExpenditureStep.vue` | 260 | 7 Nov 2025 | Detailed expenditure breakdown by category |

---

## Era 2: Journey System — Simplified Duplicates (March 9, 2026)

These were created as "simplified" versions of the originals for the budgeting and protection journeys. **They duplicate functionality that already existed in the Era 1 components.**

| File | Lines | Created | Purpose | Duplicates |
|------|-------|---------|---------|------------|
| `SimplePersonalInfoStep.vue` | 320 | 9 Mar 2026 | Simplified personal info — read-only name, phone, conditional DOB | Duplicates `PersonalInfoStep.vue` |
| `SimpleIncomeStep.vue` | 249 | 9 Mar 2026 | Simplified income — employment status, monthly take-home | Duplicates `IncomeStep.vue` |
| `SimpleExpenditureStep.vue` | 267 | 9 Mar 2026 | Simplified expenditure — single monthly total | Duplicates `ExpenditureStep.vue` |
| `SimpleSavingsAccountStep.vue` | 307 | 9 Mar 2026 | Inline savings form with cards | Duplicates savings tab in `AssetsStep.vue` |
| `SimplePropertyMortgageStep.vue` | 401 | 9 Mar 2026 | Simplified property + mortgage | Duplicates property tab in `AssetsStep.vue` |
| `BudgetingCompletionStep.vue` | 186 | 9 Mar 2026 | Budgeting journey completion | Duplicates `CompletionStep.vue` |

---

## Era 3: Life Stage Journey Additions (March 7-18, 2026)

| File | Lines | Created | Purpose |
|------|-------|---------|---------|
| `QuickAssetsStep.vue` | 174 | 7 Mar 2026 | Quick assets overview |
| `BudgetingSteps.vue` | 196 | 7 Mar 2026 | Budgeting step container |
| `GoalSetupStep.vue` | 247 | 7 Mar 2026 | Goal creation — type, target, date |
| `JourneyCompletionStep.vue` | 338 | 7 Mar 2026 | Smart completion screen per journey |
| `StudentLoanStep.vue` | 242 | 18 Mar 2026 | Student loan plan type, balance, rate |

---

## Era 4: Today's Broken Wrappers (March 20, 2026)

These were created TODAY and are BROKEN. They wrap the standalone forms in OnboardingStep containers, creating doubled navigation buttons and broken save flows.

| File | Lines | Created | Purpose | Status |
|------|-------|---------|---------|--------|
| `PropertyStep.vue` | 219 | 20 Mar 2026 | Wrapper around PropertyForm | **BROKEN** — doubled Continue buttons, address pre-fill issues |
| `InvestmentStep.vue` | 190 | 20 Mar 2026 | Wrapper around AccountForm | **BROKEN** — doubled navigation |
| `PensionStep.vue` | 187 | 20 Mar 2026 | Wrapper around DCPensionForm | **BROKEN** — doubled navigation, value not saving |

---

## Era 5: Life Stage Journey — External Form Mapping (March 17, 2026)

The life stage journey system (OnboardingWizard.vue STEP_COMPONENTS mapping) maps step IDs directly to standalone module form components, bypassing the original onboarding steps entirely:

| Step ID | Currently Mapped To | Should Use Instead |
|---------|--------------------|--------------------|
| `personal-info` | `PersonalInformation.vue` (standalone) | `PersonalInfoStep.vue` (original onboarding) |
| `property-mortgage` | `PropertyStep.vue` (today's broken wrapper) | `AssetsStep.vue` property tab (original) |
| `property-portfolio` | `PropertyStep.vue` (today's broken wrapper) | `AssetsStep.vue` property tab (original) |
| `pensions` | `PensionStep.vue` (today's broken wrapper) | `AssetsStep.vue` retirement tab (original) |
| `pension-auto-enrolment` | `PensionStep.vue` (today's broken wrapper) | `AssetsStep.vue` retirement tab (original) |
| `pension-review` | `PensionStep.vue` (today's broken wrapper) | `AssetsStep.vue` retirement tab (original) |
| `pension-drawdown` | `PensionStep.vue` (today's broken wrapper) | `AssetsStep.vue` retirement tab (original) |
| `investments` | `InvestmentStep.vue` (today's broken wrapper) | `AssetsStep.vue` investments tab (original) |
| `investments-isa` | `InvestmentStep.vue` (today's broken wrapper) | `AssetsStep.vue` investments tab (original) |
| `savings` | `SaveAccountModal.vue` (standalone modal) | `AssetsStep.vue` savings tab (original) |
| `savings-emergency` | `SaveAccountModal.vue` (standalone modal) | `AssetsStep.vue` savings tab (original) |
| `protection-insurance` | `PolicyFormModal.vue` (standalone modal) | `ProtectionPoliciesStep.vue` (original) |
| `family` | `FamilyInfoStep.vue` (original) | `FamilyInfoStep.vue` (already correct) |
| `will-estate` | `WillInfoStep.vue` (original) | `WillInfoStep.vue` (already correct) |
| `goals` | `GoalSetupStep.vue` | `GoalSetupStep.vue` (already correct) |
| `income` | `IncomeStep.vue` (original) | `IncomeStep.vue` (already correct) |
| `student-loan` | `StudentLoanStep.vue` | `StudentLoanStep.vue` (already correct) |
| `expenditure` | `SimpleExpenditureStep.vue` (duplicate) | `ExpenditureStep.vue` (original) |

---

## Summary

| Era | Date | Components | Status |
|-----|------|-----------|--------|
| 1. Original | Oct-Nov 2025 | 11 onboarding steps + 6 standalone forms | **WORKING. Tested. 5 months in codebase.** |
| 2. Simplified duplicates | 9 Mar 2026 | 6 components | Unnecessary duplicates of Era 1 |
| 3. Journey additions | 7-18 Mar 2026 | 5 components | GoalSetupStep and StudentLoanStep are new; rest overlap |
| 4. Today's broken wrappers | 20 Mar 2026 | 3 components | BROKEN. Delete these. |
| 5. Direct form mapping | 17 Mar 2026 | STEP_COMPONENTS in wizard | Wrong approach. Should map to Era 1 steps. |

**The answer has always been: use the Era 1 components. They work. They've been in the codebase for 5 months. They have cards, add another, edit, and use the same forms as the rest of the app.**

---

## Journey Step Definitions (from lifeStageConfig.js)

### Journey 1: Starting Out (university) — 6 steps

| # | Step ID | Current Mapping (BROKEN) | Correct Mapping (Era 1) |
|---|---------|--------------------------|-------------------------|
| 1 | `personal-info` | `PersonalInformation.vue` (standalone) | `PersonalInfoStep.vue` |
| 2 | `student-loan` | `StudentLoanStep.vue` | `StudentLoanStep.vue` (correct) |
| 3 | `income` | `IncomeStep.vue` | `IncomeStep.vue` (correct) |
| 4 | `expenditure` | `SimpleExpenditureStep.vue` (DELETED) | `ExpenditureStep.vue` |
| 5 | `savings` | `SaveAccountModal.vue` (standalone modal) | `AssetsStep.vue` (savings tab) |
| 6 | `goals` | `GoalSetupStep.vue` | `GoalSetupStep.vue` (correct) |

### Journey 2: Building Foundations (early_career) — 7 steps

| # | Step ID | Current Mapping (BROKEN) | Correct Mapping (Era 1) |
|---|---------|--------------------------|-------------------------|
| 1 | `personal-info` | `PersonalInformation.vue` (standalone) | `PersonalInfoStep.vue` |
| 2 | `income-career` | `IncomeStep.vue` | `IncomeStep.vue` (correct) |
| 3 | `savings-emergency` | `SaveAccountModal.vue` (standalone modal) | `AssetsStep.vue` (savings tab) |
| 4 | `first-home-lisa` | `SaveAccountModal.vue` (standalone modal) | `AssetsStep.vue` (savings tab) |
| 5 | `pension-auto-enrolment` | `PensionStep.vue` (DELETED) | `AssetsStep.vue` (retirement tab) |
| 6 | `investments` | `InvestmentStep.vue` (DELETED) | `AssetsStep.vue` (investments tab) |
| 7 | `goals` | `GoalSetupStep.vue` | `GoalSetupStep.vue` (correct) |

### Journey 3: Protecting What Matters (mid_career) — 8 steps

| # | Step ID | Current Mapping (BROKEN) | Correct Mapping (Era 1) |
|---|---------|--------------------------|-------------------------|
| 1 | `personal-info` | `PersonalInformation.vue` (standalone) | `PersonalInfoStep.vue` |
| 2 | `family` | `FamilyInfoStep.vue` | `FamilyInfoStep.vue` (correct) |
| 3 | `income` | `IncomeStep.vue` | `IncomeStep.vue` (correct) |
| 4 | `property-mortgage` | `PropertyStep.vue` (DELETED) | `AssetsStep.vue` (property tab) |
| 5 | `protection-insurance` | `PolicyFormModal.vue` (standalone modal) | `ProtectionPoliciesStep.vue` |
| 6 | `pensions` | `PensionStep.vue` (DELETED) | `AssetsStep.vue` (retirement tab) |
| 7 | `will-estate` | `WillInfoStep.vue` | `WillInfoStep.vue` (correct) |
| 8 | `goals` | `GoalSetupStep.vue` | `GoalSetupStep.vue` (correct) |

### Journey 4: Planning Your Future (peak) — 7 steps

| # | Step ID | Current Mapping (BROKEN) | Correct Mapping (Era 1) |
|---|---------|--------------------------|-------------------------|
| 1 | `personal-info` | `PersonalInformation.vue` (standalone) | `PersonalInfoStep.vue` |
| 2 | `income-tax` | `IncomeStep.vue` | `IncomeStep.vue` (correct) |
| 3 | `pension-review` | `PensionStep.vue` (DELETED) | `AssetsStep.vue` (retirement tab) |
| 4 | `investments-isa` | `InvestmentStep.vue` (DELETED) | `AssetsStep.vue` (investments tab) |
| 5 | `property-portfolio` | `PropertyStep.vue` (DELETED) | `AssetsStep.vue` (property tab) |
| 6 | `estate-iht` | `WillInfoStep.vue` | `WillInfoStep.vue` (correct) |
| 7 | `goals` | `GoalSetupStep.vue` | `GoalSetupStep.vue` (correct) |

### Journey 5: Enjoying Your Wealth (retirement) — 6 steps

| # | Step ID | Current Mapping (BROKEN) | Correct Mapping (Era 1) |
|---|---------|--------------------------|-------------------------|
| 1 | `personal-info` | `PersonalInformation.vue` (standalone) | `PersonalInfoStep.vue` |
| 2 | `pension-drawdown` | `PensionStep.vue` (DELETED) | `AssetsStep.vue` (retirement tab) |
| 3 | `state-pension` | `StatePensionForm.vue` (standalone) | `AssetsStep.vue` (retirement tab) |
| 4 | `income-tax` | `IncomeStep.vue` | `IncomeStep.vue` (correct) |
| 5 | `estate-legacy` | `WillInfoStep.vue` | `WillInfoStep.vue` (correct) |
| 6 | `goals` | `GoalSetupStep.vue` | `GoalSetupStep.vue` (correct) |

---

## Remapping Summary

### Steps that are already correct (no change needed)

| Step ID | Component | Used in Journeys |
|---------|-----------|-----------------|
| `student-loan` | `StudentLoanStep.vue` | 1 |
| `income`, `income-career`, `income-tax` | `IncomeStep.vue` | 1, 2, 3, 4, 5 |
| `family` | `FamilyInfoStep.vue` | 3 |
| `will-estate`, `estate-iht`, `estate-legacy` | `WillInfoStep.vue` | 3, 4, 5 |
| `goals` | `GoalSetupStep.vue` | 1, 2, 3, 4, 5 |

### Steps that need remapping

| Step ID | FROM (broken/deleted) | TO (Era 1) | Used in Journeys |
|---------|----------------------|------------|-----------------|
| `personal-info` | `PersonalInformation.vue` | `PersonalInfoStep.vue` | 1, 2, 3, 4, 5 |
| `expenditure` | `SimpleExpenditureStep.vue` (DELETED) | `ExpenditureStep.vue` | 1 |
| `savings`, `savings-emergency`, `first-home-lisa` | `SaveAccountModal.vue` | `AssetsStep.vue` | 1, 2 |
| `property-mortgage`, `property-portfolio` | `PropertyStep.vue` (DELETED) | `AssetsStep.vue` | 3, 4 |
| `pensions`, `pension-auto-enrolment`, `pension-review`, `pension-drawdown` | `PensionStep.vue` (DELETED) | `AssetsStep.vue` | 2, 3, 4, 5 |
| `investments`, `investments-isa` | `InvestmentStep.vue` (DELETED) | `AssetsStep.vue` | 2, 4 |
| `protection-insurance` | `PolicyFormModal.vue` | `ProtectionPoliciesStep.vue` | 3 |
| `state-pension` | `StatePensionForm.vue` | `AssetsStep.vue` | 5 |

### AssetsStep.vue tab visibility per step ID

AssetsStep.vue has 4 tabs: Retirement, Property, Investments, Savings. Each step ID should show only the relevant tab:

| Step ID | Tab to show | Hide other tabs? |
|---------|-------------|-----------------|
| `savings`, `savings-emergency`, `first-home-lisa` | Savings | Yes |
| `property-mortgage`, `property-portfolio` | Property | Yes |
| `pensions`, `pension-auto-enrolment`, `pension-review`, `pension-drawdown` | Retirement | Yes |
| `investments`, `investments-isa` | Investments | Yes |
| `state-pension` | Retirement (State Pension section) | Yes |
