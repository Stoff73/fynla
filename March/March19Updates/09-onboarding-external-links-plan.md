# Onboarding External Links Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add external links to UK government, MoneyHelper, and reputable third-party resources throughout onboarding form steps — inline within helper text and as grouped "Useful Resources" boxes at the bottom of each step.

**Architecture:** A centralised `onboardingLinks.js` constants file holds all URLs and per-step resource arrays. A shared `UsefulResources.vue` component renders the grouped box. Each step imports its resources and the component.

**Tech Stack:** Vue.js 3, Tailwind CSS

**Spec:** `March/March19Updates/09-onboarding-external-links-design.md`

---

### Task 1: Create the link registry

**Files:**
- Create: `resources/js/constants/onboardingLinks.js`

- [ ] **Step 1: Create the constants file with all URLs and step resource mappings**

```js
/**
 * Onboarding External Links Registry
 *
 * Central source of truth for all external links shown during onboarding.
 * Grouped by source for easy auditing and URL updates.
 */

// ─── URL Constants ───────────────────────────────────────────────────────────

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

// ─── Per-Step Resource Arrays ────────────────────────────────────────────────

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

- [ ] **Step 2: Commit**

```bash
git add resources/js/constants/onboardingLinks.js
git commit -m "feat: add onboarding external links registry"
```

---

### Task 2: Create the UsefulResources component

**Files:**
- Create: `resources/js/components/Onboarding/UsefulResources.vue`

- [ ] **Step 1: Create the shared component**

```vue
<template>
  <div v-if="links && links.length" class="bg-eggshell-500 border border-light-gray rounded-lg p-4 mt-6">
    <h4 class="text-xs font-bold text-horizon-500 uppercase tracking-wide mb-3">Useful Resources</h4>
    <ul class="space-y-2">
      <li v-for="link in links" :key="link.url" class="flex items-center">
        <a
          :href="link.url"
          target="_blank"
          rel="noopener noreferrer"
          class="text-body-sm text-violet-500 hover:text-violet-700 underline font-medium"
        >
          {{ link.label }}
          <svg class="inline-block w-3 h-3 ml-0.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
        </a>
        <span class="text-[10px] text-neutral-500 ml-2">{{ link.source }}</span>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  name: 'UsefulResources',
  props: {
    links: {
      type: Array,
      required: true,
      validator: (val) => val.every(l => l.label && l.url && l.source),
    },
  },
};
</script>
```

- [ ] **Step 2: Verify it compiles**

Check dev server output for errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Onboarding/UsefulResources.vue
git commit -m "feat: add UsefulResources shared component for onboarding"
```

---

### Task 3: Add links to PersonalInfoStep and SimplePersonalInfoStep

**Files:**
- Modify: `resources/js/components/Onboarding/steps/PersonalInfoStep.vue`
- Modify: `resources/js/components/Onboarding/steps/SimplePersonalInfoStep.vue`

- [ ] **Step 1: PersonalInfoStep — add inline link to DOB helper text**

Change line 28-30 from:
```html
<p v-else class="mt-1 text-body-sm text-neutral-500">
  Used for age-based calculations and projections
</p>
```
To:
```html
<p v-else class="mt-1 text-body-sm text-neutral-500">
  Used for age-based calculations and projections. Check your <a :href="LINKS.GOV_STATE_PENSION_AGE" target="_blank" rel="noopener noreferrer" class="underline font-medium text-violet-500 hover:text-violet-700">State Pension age</a>
</p>
```

Add imports and return LINKS + STEP_RESOURCES in setup/data. Add `<UsefulResources :links="STEP_RESOURCES.personalInfo" />` before the closing `</div>` of `space-y-6`.

- [ ] **Step 2: SimplePersonalInfoStep — add inline link to DOB helper text**

Find the DOB helper text and add the same State Pension age link. Add `<UsefulResources :links="STEP_RESOURCES.simplePersonalInfo" />` at the bottom.

- [ ] **Step 3: Verify both compile, commit**

```bash
git add resources/js/components/Onboarding/steps/PersonalInfoStep.vue resources/js/components/Onboarding/steps/SimplePersonalInfoStep.vue
git commit -m "feat: add external links to personal info onboarding steps"
```

---

### Task 4: Add links to FamilyInfoStep

**Files:**
- Modify: `resources/js/components/Onboarding/steps/FamilyInfoStep.vue`

- [ ] **Step 1: Add inline link to dependent helper text**

In the family member form, where dependents are mentioned, add inline links for Child Benefit and free childcare. Add `<UsefulResources :links="STEP_RESOURCES.family" />` at the bottom of the step.

- [ ] **Step 2: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/FamilyInfoStep.vue
git commit -m "feat: add external links to family info onboarding step"
```

---

### Task 5: Add links to IncomeStep and SimpleIncomeStep

**Files:**
- Modify: `resources/js/components/Onboarding/steps/IncomeStep.vue`
- Modify: `resources/js/components/Onboarding/steps/SimpleIncomeStep.vue`

- [ ] **Step 1: IncomeStep — add inline P60 link to retirement age helper**

After the existing helper "Planned retirement age, used for all pension forecast calculations." add text with a link to the HMRC tax calculator. Add `<UsefulResources :links="STEP_RESOURCES.income" />` at the bottom.

- [ ] **Step 2: SimpleIncomeStep — add inline link and grouped resources**

Add inline link to P60 explanation near the income field. Add `<UsefulResources :links="STEP_RESOURCES.simpleIncome" />` at the bottom.

- [ ] **Step 3: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/IncomeStep.vue resources/js/components/Onboarding/steps/SimpleIncomeStep.vue
git commit -m "feat: add external links to income onboarding steps"
```

---

### Task 6: Add links to expenditure steps

**Files:**
- Modify: `resources/js/components/Onboarding/steps/ExpenditureStep.vue`
- Modify: `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue`

- [ ] **Step 1: Add grouped resources to both steps**

No inline links (no single field suits a link). Add `<UsefulResources :links="STEP_RESOURCES.expenditure" />` and `STEP_RESOURCES.simpleExpenditure` respectively.

- [ ] **Step 2: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/ExpenditureStep.vue resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue
git commit -m "feat: add external links to expenditure onboarding steps"
```

---

### Task 7: Add links to AssetsStep (4 tabs)

**Files:**
- Modify: `resources/js/components/Onboarding/steps/AssetsStep.vue`

- [ ] **Step 1: Pensions tab — add BR19 inline link to State Pension section**

Near the "Add State Pension" button or state pension display, add helper text with BR19 link. Add `<UsefulResources :links="STEP_RESOURCES.assetsPensions" />` at the bottom of the pensions tab.

- [ ] **Step 2: Properties tab — add grouped resources**

Add `<UsefulResources :links="STEP_RESOURCES.assetsProperties" />` at the bottom of the properties tab.

- [ ] **Step 3: Investments tab — add grouped resources**

Add `<UsefulResources :links="STEP_RESOURCES.assetsInvestments" />` at the bottom of the investments tab.

- [ ] **Step 4: Cash tab — add grouped resources**

Add `<UsefulResources :links="STEP_RESOURCES.assetsCash" />` at the bottom of the cash tab.

- [ ] **Step 5: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/AssetsStep.vue
git commit -m "feat: add external links to assets onboarding step (all 4 tabs)"
```

---

### Task 8: Add links to savings and property simple steps

**Files:**
- Modify: `resources/js/components/Onboarding/steps/SimpleSavingsAccountStep.vue`
- Modify: `resources/js/components/Onboarding/steps/SimplePropertyMortgageStep.vue`

- [ ] **Step 1: SimpleSavingsAccountStep — add ISA inline link and grouped resources**

Near ISA account type options, add helper text with ISA allowance link. Add `<UsefulResources :links="STEP_RESOURCES.simpleSavings" />` at the bottom.

- [ ] **Step 2: SimplePropertyMortgageStep — add grouped resources**

Add `<UsefulResources :links="STEP_RESOURCES.simpleProperty" />` at the bottom.

- [ ] **Step 3: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/SimpleSavingsAccountStep.vue resources/js/components/Onboarding/steps/SimplePropertyMortgageStep.vue
git commit -m "feat: add external links to savings and property onboarding steps"
```

---

### Task 9: Add links to StudentLoanStep

**Files:**
- Modify: `resources/js/components/Onboarding/steps/StudentLoanStep.vue`

- [ ] **Step 1: Add inline link to plan type helper and grouped resources**

After the plan type select, add helper text: "Not sure which plan? Check your <a>repayment plan</a>". Add `<UsefulResources :links="STEP_RESOURCES.studentLoan" />` at the bottom.

- [ ] **Step 2: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/StudentLoanStep.vue
git commit -m "feat: add external links to student loan onboarding step"
```

---

### Task 10: Add links to ProtectionPoliciesStep

**Files:**
- Modify: `resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue`

- [ ] **Step 1: Add grouped resources**

Add `<UsefulResources :links="STEP_RESOURCES.protection" />` at the bottom.

- [ ] **Step 2: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/ProtectionPoliciesStep.vue
git commit -m "feat: add external links to protection policies onboarding step"
```

---

### Task 11: Add links to LiabilitiesStep

**Files:**
- Modify: `resources/js/components/Onboarding/steps/LiabilitiesStep.vue`

- [ ] **Step 1: Add grouped resources**

Add `<UsefulResources :links="STEP_RESOURCES.liabilities" />` at the bottom.

- [ ] **Step 2: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/LiabilitiesStep.vue
git commit -m "feat: add external links to liabilities onboarding step"
```

---

### Task 12: Add links to WillInfoStep and TrustInfoStep

**Files:**
- Modify: `resources/js/components/Onboarding/steps/WillInfoStep.vue`
- Modify: `resources/js/components/Onboarding/steps/TrustInfoStep.vue`

- [ ] **Step 1: WillInfoStep — add grouped resources**

Add `<UsefulResources :links="STEP_RESOURCES.will" />` at the bottom.

- [ ] **Step 2: TrustInfoStep — add grouped resources**

Add `<UsefulResources :links="STEP_RESOURCES.trust" />` at the bottom.

- [ ] **Step 3: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/WillInfoStep.vue resources/js/components/Onboarding/steps/TrustInfoStep.vue
git commit -m "feat: add external links to will and trust onboarding steps"
```

---

### Task 13: Add links to DomicileInformationStep

**Files:**
- Modify: `resources/js/components/Onboarding/steps/DomicileInformationStep.vue`

- [ ] **Step 1: Add inline link to domicile helper text and grouped resources**

Change line 26-28 from:
```html
<p class="mt-1 text-body-sm text-neutral-500">
  Your country of birth helps us determine your domicile status for tax purposes.
</p>
```
To:
```html
<p class="mt-1 text-body-sm text-neutral-500">
  Your country of birth helps us determine your domicile status for tax purposes. Learn about <a :href="LINKS.GOV_DOMICILE" target="_blank" rel="noopener noreferrer" class="underline font-medium text-violet-500 hover:text-violet-700">UK domicile rules</a>
</p>
```

Add `<UsefulResources :links="STEP_RESOURCES.domicile" />` at the bottom.

- [ ] **Step 2: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/DomicileInformationStep.vue
git commit -m "feat: add external links to domicile onboarding step"
```

---

### Task 14: Add links to GoalSetupStep and BudgetingSteps

**Files:**
- Modify: `resources/js/components/Onboarding/steps/GoalSetupStep.vue`
- Modify: `resources/js/components/Onboarding/steps/BudgetingSteps.vue`

- [ ] **Step 1: Add grouped resources to both**

Add `<UsefulResources :links="STEP_RESOURCES.goals" />` and `STEP_RESOURCES.budgeting` respectively.

- [ ] **Step 2: Verify, commit**

```bash
git add resources/js/components/Onboarding/steps/GoalSetupStep.vue resources/js/components/Onboarding/steps/BudgetingSteps.vue
git commit -m "feat: add external links to goal and budgeting onboarding steps"
```

---

### Task 15: Final verification and change document

**Files:**
- Create: `March/March19Updates/09-onboarding-external-links.md` (update existing)

- [ ] **Step 1: Verify all links render correctly**

Check dev server for compile errors. Navigate through onboarding steps in browser to confirm links display.

- [ ] **Step 2: Verify all links open in new tabs**

Click each link type (inline and grouped) to confirm `target="_blank"` works.

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat: complete onboarding external links — 20 steps, 9 inline links, grouped resources"
```
