# Onboarding External Links — Design Spec

**Date:** 19 March 2026
**Branch:** `onboardFix`
**Status:** Approved

## Overview

Add external links to UK government, MoneyHelper, and reputable third-party resources throughout the onboarding form steps. Links appear in two forms:
1. **Inline** — within existing helper text under specific form fields
2. **Grouped** — a "Useful Resources" box at the bottom of each step

## Architecture

### Approach C: Centralised Registry + Shared Component

- All URLs stored in `resources/js/constants/onboardingLinks.js` as named constants
- A reusable `<UsefulResources>` component renders the grouped box
- Inline links reference the constants file for URLs
- All links open in new tabs with `target="_blank" rel="noopener noreferrer"`

## New Files

### 1. `resources/js/constants/onboardingLinks.js`

Central registry with two exports:

**`LINKS`** — named URL constants grouped by source:

```js
export const LINKS = {
  // Gov.uk
  GOV_STATE_PENSION: 'https://www.gov.uk/check-state-pension',
  GOV_STATE_PENSION_AGE: 'https://www.gov.uk/state-pension-age',
  GOV_CHILD_BENEFIT: 'https://www.gov.uk/child-benefit',
  GOV_TAX_FREE_CHILDCARE: 'https://www.gov.uk/tax-free-childcare',
  GOV_EARLY_YEARS: 'https://www.gov.uk/get-childcare',
  GOV_ISA_ALLOWANCE: 'https://www.gov.uk/individual-savings-accounts',
  GOV_PENSION_TAX_RELIEF: 'https://www.gov.uk/tax-on-your-private-pension',
  GOV_IHT: 'https://www.gov.uk/inheritance-tax',
  GOV_MAKE_WILL: 'https://www.gov.uk/make-will',
  GOV_STUDENT_LOAN_REPAY: 'https://www.gov.uk/repaying-your-student-loan',
  GOV_INCOME_TAX_RATES: 'https://www.gov.uk/income-tax-rates',
  GOV_DOMICILE: 'https://www.gov.uk/tax-foreign-income/non-domiciled-residents',
  GOV_LPA: 'https://www.gov.uk/lasting-power-attorney',
  GOV_PROPERTY_TAX: 'https://www.gov.uk/stamp-duty-land-tax',
  GOV_BR19: 'https://www.gov.uk/check-state-pension',

  // HMRC
  HMRC_TAX_CALC: 'https://www.gov.uk/estimate-income-tax',
  HMRC_P60: 'https://www.gov.uk/paye-forms-p45-p60-p11d/p60',

  // MoneyHelper
  MONEYHELPER_BUDGET: 'https://www.moneyhelper.org.uk/en/everyday-money/budgeting/budget-planner',
  MONEYHELPER_PENSION: 'https://www.moneyhelper.org.uk/en/pensions-and-retirement',
  MONEYHELPER_PROTECTION: 'https://www.moneyhelper.org.uk/en/family-and-care/protecting-your-family',
  MONEYHELPER_EMERGENCY: 'https://www.moneyhelper.org.uk/en/savings/types-of-savings/emergency-savings',
  MONEYHELPER_MORTGAGE: 'https://www.moneyhelper.org.uk/en/homes/buying-a-home',

  // Third party
  MSE_STUDENT_LOAN: 'https://www.moneysavingexpert.com/students/student-loans-repay/',
  MSE_ISA: 'https://www.moneysavingexpert.com/savings/best-cash-isa/',
  WHICH_LIFE_INSURANCE: 'https://www.which.co.uk/money/insurance/life-insurance',
  STEPCHANGE_DEBT: 'https://www.stepchange.org/',
};
```

**`STEP_RESOURCES`** — keyed by step ID, arrays of `{ label, url, source }`:

```js
export const STEP_RESOURCES = {
  personalInfo: [
    { label: 'Check your State Pension age', url: LINKS.GOV_STATE_PENSION_AGE, source: 'Gov.uk' },
    { label: 'Income Tax rates and bands', url: LINKS.GOV_INCOME_TAX_RATES, source: 'Gov.uk' },
  ],
  simplePersonalInfo: [
    { label: 'Check your State Pension age', url: LINKS.GOV_STATE_PENSION_AGE, source: 'Gov.uk' },
  ],
  family: [
    { label: 'Child Benefit', url: LINKS.GOV_CHILD_BENEFIT, source: 'Gov.uk' },
    { label: 'Tax-Free Childcare', url: LINKS.GOV_TAX_FREE_CHILDCARE, source: 'Gov.uk' },
    { label: 'Free early years education and childcare', url: LINKS.GOV_EARLY_YEARS, source: 'Gov.uk' },
    { label: 'Lasting Power of Attorney', url: LINKS.GOV_LPA, source: 'Gov.uk' },
  ],
  income: [
    { label: 'Estimate your Income Tax', url: LINKS.HMRC_TAX_CALC, source: 'Gov.uk' },
    { label: 'Understanding your P60', url: LINKS.HMRC_P60, source: 'Gov.uk' },
    { label: 'Income Tax rates and bands', url: LINKS.GOV_INCOME_TAX_RATES, source: 'Gov.uk' },
  ],
  simpleIncome: [
    { label: 'Estimate your Income Tax', url: LINKS.HMRC_TAX_CALC, source: 'Gov.uk' },
    { label: 'Income Tax rates and bands', url: LINKS.GOV_INCOME_TAX_RATES, source: 'Gov.uk' },
  ],
  expenditure: [
    { label: 'MoneyHelper Budget Planner', url: LINKS.MONEYHELPER_BUDGET, source: 'MoneyHelper' },
  ],
  simpleExpenditure: [
    { label: 'MoneyHelper Budget Planner', url: LINKS.MONEYHELPER_BUDGET, source: 'MoneyHelper' },
  ],
  assetsPensions: [
    { label: 'Check your State Pension (BR19)', url: LINKS.GOV_BR19, source: 'Gov.uk' },
    { label: 'Pension tax relief', url: LINKS.GOV_PENSION_TAX_RELIEF, source: 'Gov.uk' },
    { label: 'MoneyHelper pensions guide', url: LINKS.MONEYHELPER_PENSION, source: 'MoneyHelper' },
  ],
  assetsProperties: [
    { label: 'Stamp Duty Land Tax', url: LINKS.GOV_PROPERTY_TAX, source: 'Gov.uk' },
    { label: 'MoneyHelper buying a home', url: LINKS.MONEYHELPER_MORTGAGE, source: 'MoneyHelper' },
  ],
  assetsInvestments: [
    { label: 'ISA allowances', url: LINKS.GOV_ISA_ALLOWANCE, source: 'Gov.uk' },
    { label: 'Best Cash ISAs', url: LINKS.MSE_ISA, source: 'MoneySavingExpert' },
  ],
  assetsCash: [
    { label: 'Emergency savings guide', url: LINKS.MONEYHELPER_EMERGENCY, source: 'MoneyHelper' },
    { label: 'ISA allowances', url: LINKS.GOV_ISA_ALLOWANCE, source: 'Gov.uk' },
  ],
  simpleSavings: [
    { label: 'ISA allowances', url: LINKS.GOV_ISA_ALLOWANCE, source: 'Gov.uk' },
    { label: 'Emergency savings guide', url: LINKS.MONEYHELPER_EMERGENCY, source: 'MoneyHelper' },
  ],
  simpleProperty: [
    { label: 'MoneyHelper buying a home', url: LINKS.MONEYHELPER_MORTGAGE, source: 'MoneyHelper' },
    { label: 'Stamp Duty Land Tax', url: LINKS.GOV_PROPERTY_TAX, source: 'Gov.uk' },
  ],
  studentLoan: [
    { label: 'Repaying your student loan', url: LINKS.GOV_STUDENT_LOAN_REPAY, source: 'Gov.uk' },
    { label: 'Student loan repayment guide', url: LINKS.MSE_STUDENT_LOAN, source: 'MoneySavingExpert' },
  ],
  protection: [
    { label: 'Life insurance guide', url: LINKS.WHICH_LIFE_INSURANCE, source: 'Which?' },
    { label: 'Protecting your family', url: LINKS.MONEYHELPER_PROTECTION, source: 'MoneyHelper' },
  ],
  liabilities: [
    { label: 'Free debt advice', url: LINKS.STEPCHANGE_DEBT, source: 'StepChange' },
  ],
  will: [
    { label: 'Making a will', url: LINKS.GOV_MAKE_WILL, source: 'Gov.uk' },
    { label: 'Lasting Power of Attorney', url: LINKS.GOV_LPA, source: 'Gov.uk' },
  ],
  domicile: [
    { label: 'Non-domiciled residents tax guidance', url: LINKS.GOV_DOMICILE, source: 'Gov.uk' },
  ],
  goals: [
    { label: 'MoneyHelper Budget Planner', url: LINKS.MONEYHELPER_BUDGET, source: 'MoneyHelper' },
  ],
  budgeting: [
    { label: 'MoneyHelper Budget Planner', url: LINKS.MONEYHELPER_BUDGET, source: 'MoneyHelper' },
  ],
  trust: [
    { label: 'Inheritance Tax guidance', url: LINKS.GOV_IHT, source: 'Gov.uk' },
  ],
};
```

### 2. `resources/js/components/Onboarding/UsefulResources.vue`

Shared component rendering a grouped links box:

**Props:** `links` — array of `{ label, url, source }`

**Template:**
```html
<div v-if="links && links.length" class="bg-eggshell-500 border border-light-gray rounded-lg p-4 mt-6">
  <h4 class="text-xs font-bold text-horizon-500 uppercase tracking-wide mb-3">
    Useful Resources
  </h4>
  <ul class="space-y-2">
    <li v-for="link in links" :key="link.url">
      <a :href="link.url" target="_blank" rel="noopener noreferrer"
         class="text-body-sm text-violet-500 hover:text-violet-700 underline font-medium">
        {{ link.label }}
      </a>
      <span class="text-[10px] text-neutral-500 ml-1">{{ link.source }}</span>
    </li>
  </ul>
</div>
```

## Modified Files (20 step components)

### Inline Links (added to existing helper text)

| Step | Field | Inline Link Text |
|------|-------|-----------------|
| PersonalInfoStep | DOB | "Check your [State Pension age](gov.uk)" |
| SimplePersonalInfoStep | DOB | "Check your [State Pension age](gov.uk)" |
| FamilyInfoStep | Child dependent | "You may be eligible for [Child Benefit](gov.uk) and [free childcare](gov.uk)" |
| IncomeStep | Annual salary | "Check your [P60](gov.uk)" |
| SimpleIncomeStep | Annual salary | "Check your [P60](gov.uk)" |
| AssetsStep | State pension | "Request your [BR19 forecast](gov.uk)" |
| SimpleSavingsAccountStep | ISA type | "Tax-free up to the [annual ISA limit](gov.uk)" |
| StudentLoanStep | Plan type | "Not sure? Check your [repayment plan](gov.uk)" |
| DomicileInformationStep | Domicile status | "Learn about [UK domicile rules](gov.uk)" |

### Grouped Resources (UsefulResources component at bottom)

All 20 step components listed in the STEP_RESOURCES mapping above get the `<UsefulResources>` component placed at the bottom of their form content, before the closing `</div>` of the `space-y-6` container.

For AssetsStep (tabbed), each tab gets its own resources box with tab-specific links.

## Steps That Get Nothing

- CompletionStep
- JourneyCompletionStep
- BudgetingCompletionStep
- QuickAssetsStep

## Design Constraints

- All links use `target="_blank" rel="noopener noreferrer"`
- Inline links use `class="underline font-medium text-violet-500 hover:text-violet-700"`
- External link icon (small SVG arrow) appended to grouped resource links
- No links to commercial products or services with referral incentives
- All URLs verified as current UK government/official pages
- British English spelling in all labels
