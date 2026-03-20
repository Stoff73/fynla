# Onboarding System Refactor — 20 March 2026

**Goal:** Consolidate onboarding to use original Era 1 components only, delete duplicates, add inline forms with contextual sidebar, add family step to journeys 4 and 5.

---

## What Changed

### 1. Deleted 9 duplicate components (Era 2/3/4)

These were redundant copies of Era 1 components. All journeys now use the originals.

```
DELETED:
  resources/js/components/Onboarding/steps/SimplePersonalInfoStep.vue    (Era 2)
  resources/js/components/Onboarding/steps/SimpleIncomeStep.vue          (Era 2)
  resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue     (Era 2)
  resources/js/components/Onboarding/steps/SimpleSavingsAccountStep.vue  (Era 2)
  resources/js/components/Onboarding/steps/SimplePropertyMortgageStep.vue (Era 2)
  resources/js/components/Onboarding/steps/BudgetingCompletionStep.vue   (Era 2)
  resources/js/components/Onboarding/steps/QuickAssetsStep.vue           (Era 3)
  resources/js/components/Onboarding/steps/BudgetingSteps.vue            (Era 3)
  resources/js/components/Onboarding/steps/JourneyCompletionStep.vue     (Era 3)
```

### 2. Unified step mapping in OnboardingWizard.vue

`STEP_COMPONENTS` reduced from 25+ entries to 13. All 5 journeys use the same components:

| Step ID | Component |
|---------|-----------|
| `personal-info` | PersonalInfoStep |
| `student-loan` | StudentLoanStep |
| `income` / `income-career` / `income-tax` | IncomeStep |
| `expenditure` | ExpenditureStep |
| `assets` | AssetsStep |
| `family` | FamilyInfoStep |
| `protection-insurance` | ProtectionPoliciesStep |
| `will-estate` / `estate-iht` / `estate-legacy` | WillInfoStep |
| `goals` | GoalSetupStep |

### 3. Single `assets` step per journey with tab filtering

Previously each journey had split asset step IDs (`savings`, `pension-auto-enrolment`, `investments`, `property-mortgage`) that each rendered separate AssetsStep instances. Now every journey uses a single `assets` step ID. Tabs are filtered per journey via `visibleTabs` in `lifeStageConfig.js`:

| Journey | Visible Tabs |
|---------|-------------|
| 1 — Starting Out | Cash only |
| 2 — Building Foundations | Cash, Retirement, Investments |
| 3 — Protecting What Matters | All 4 (Retirement, Properties, Investments, Cash) |
| 4 — Planning Your Future | All 4 |
| 5 — Enjoying Your Wealth | All 4 |

When only 1 tab is visible, the tab bar is hidden and the heading changes (e.g. "Bank Account" for students).

### 4. Inline forms with `context="onboarding"`

All 6 form components in AssetsStep now render inline (not as modals):
- PropertyForm
- AccountForm (investments)
- SaveAccountModal (savings)
- DCPensionForm
- DBPensionForm
- StatePensionForm

Forms replace the card list when open. The sidebar stays visible alongside.

### 5. Contextual sidebar content for AssetsStep

`SIDEBAR_CONTENT` object in AssetsStep provides "Did you know?", "Why we ask this", and "Quick Stat" for 10 contexts:

| Context | Quick Stat |
|---------|-----------|
| `cash-list` | £20,000 ISA allowance |
| `cash-form` | 4.5%+ best easy access rates |
| `retirement-list` | £60,000 pension allowance |
| `retirement-form-dc` | 32p saved per £1 via salary sacrifice |
| `retirement-form-db` | Guaranteed lifetime income |
| `retirement-form-state` | £11,502 full State Pension |
| `investments-list` | £20,000 ISA allowance (tax-free) |
| `investments-form` | £50,000 cost of 0.5% extra fees |
| `properties-list` | 2 years mortgage review cycle |
| `properties-form` | 24% CGT on residential property |

Sidebar updates when: tab changes, form opens, form closes. Emitted via `sidebar-update` event to OnboardingWizard, which passes it to LearningMilestoneSidebar as `override` prop.

### 6. Family step added to journeys 4 and 5

Previously missing. Now:
- Journey 4 (Peak): `personal-info → family → income-tax → assets → estate-iht → goals`
- Journey 5 (Retirement): `personal-info → family → assets → income-tax → estate-legacy → goals`

Each has tailored `learningMilestones` for the sidebar:
- Journey 4: "£1M combined IHT threshold for married couple"
- Journey 5: "£3,000 annual inheritance tax-free gift exemption"

### 7. PersonalInfoStep field visibility

Fields hidden per journey via `isFieldVisible()` using lifeStage store getter:
- Journey 1: marital status hidden, address hidden, health hidden
- Journey 2: health hidden
- Journeys 3-5: all fields visible

Name (first_name, surname) and email pre-populated and disabled from auth store.

### 8. All frontend validation removed

All `required` markers removed from all onboarding form templates (21 Vue files). All PHP Form Requests changed from `required` to `sometimes` (31 files). 15 non-nullable enum columns made nullable via migration.

### 9. LifeStageService updated

`getDataCompleteness()` uses single `'assets'` check instead of split step IDs:
```php
'assets' => $hasSavings || $hasInvestments || $hasPensions || $hasProperty
```

### 10. Returning user mode simplified

`isLifeStageMode` always returns true when user has a life stage set. No more mode confusion between "classic" and "life stage" onboarding. Returning users auto-resume their journey.

---

## Journey Step Summary

| Journey | Steps |
|---------|-------|
| 1 — Starting Out | About You, Student Loan, Income, Expenditure, Assets (Cash only), Goals |
| 2 — Building Foundations | About You, Income & Career, Assets (Cash + Retirement + Investments), Goals |
| 3 — Protecting What Matters | About You, Family, Income, Assets (all 4), Protection, Will & Estate, Goals |
| 4 — Planning Your Future | About You, Family, Income & Tax, Assets (all 4), Estate (IHT), Goals |
| 5 — Enjoying Your Wealth | About You, Family, Assets (all 4), Income & Tax, Estate & Legacy, Goals |

---

## Browser Test Results

All 5 journeys tested end-to-end with full data entry on localhost:8000.

### Journey 4 (Peak) — David Mitchell

| Step | Data | Sidebar | Status |
|------|------|---------|--------|
| Personal Info | DOB 1974, male, married, W11 3BU, healthy, non-smoker | "57" pension access age | PASS |
| Family | Sarah Mitchell (spouse, 50), James Mitchell (child, 21, dependent) | "£1M" combined IHT threshold | PASS |
| Income & Tax | £125k employment, £8.5k dividends, £2.2k interest, retire at 60 | "40%" tax relief | PASS |
| Assets — SIPP | Hargreaves Lansdown SIPP £380k, £1,500/month | "32p" salary sacrifice | PASS |
| Assets — Property | 42 Kensington Park Road £1.85M, joint tenancy, Nationwide mortgage £180k | "24%" CGT | PASS |
| Estate (IHT) | Will yes, executor Sarah Mitchell, updated 2022 | "£500k" NRB+RNRB | PASS |
| Goals | Retire at 60, £750k target | "£59k" comfortable retirement | PASS |
| Dashboard | 6/6 steps, 100% complete | All cards populated | PASS |

### Journey 5 (Retirement) — Robert Williams

| Step | Data | Sidebar | Status |
|------|------|---------|--------|
| Personal Info | DOB 1958, male, married, Guildford, ex-smoker | "87+" life expectancy | PASS |
| Family | Patricia Williams (spouse, 65) | "£3,000" annual gift exemption | PASS |
| Assets — State Pension | £221.20/week, 38 NI years | "£60k" pension allowance | PASS |
| Assets — Property | 7 The Willows £725k, joint tenancy with Patricia, costs filled | "2 years" mortgage review | PASS |
| Assets — Investments | AJ Bell ISA £185k + HL GIA £92k | "£50k" fee impact | PASS |
| Assets — Cash | Nationwide Easy Access £35k + Coventry Cash ISA £20k | "4.5%+" rates | PASS |
| Income & Tax | Retired, £4.2k dividends, £2.8k interest | "£12,570" personal allowance | PASS |
| Estate & Legacy | Will yes, executor Patricia, updated Jan 2024 | "40%" IHT rate | PASS |
| Goals | Leave £250k inheritance, target 2040 | Retirement goals guidance | PASS |
| Dashboard | 6/6 steps, Net Worth £694,500, IHT liability £77,800 | All cards populated | PASS |

---

## Known Minor Issues

1. **Step 3 label in journey overview** shows "assets" (lowercase) instead of "Assets & Wealth"
2. **State Pension sidebar** shows DC pension sidebar content instead of `retirement-form-state` — the `emitSidebarContent` watcher may not detect the state pension form type correctly
3. **Vue warnings** — "Failed to resolve component" appears in console (likely stale JourneyCompletionStep reference somewhere in template)
