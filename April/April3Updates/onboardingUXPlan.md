# Onboarding UX Improvements Plan

**Date:** 3 April 2026
**Branch:** onboardingBug

---

## Changes

### 1. Family & Dependants — "Did you know" card
Update the bottom card to include:
- The £1 million transferable nil-rate band is to **beneficiaries**
- Transfers between **spouses are inheritance tax free**

### 2. Reorder onboarding sections
Move **Assets** and **Liabilities** before **Income** and **Expenses**.

Current order: About You → Family → Income → Spending → Assets → Debts → Protection → Will → Goals

New order: About You → Family → **Assets → Debts** → Income → Spending → Protection → Will → Goals

**Why:**
- If user owns a home, we hide the rent expense field
- Council tax, utilities etc can be pulled from the property tab into expenses
- Income/expenses benefit from knowing the user's asset base first

### 3. Information icons on prefilled data
Add an info icon (tooltip) next to any field that has been prefilled, stating where the data came from (e.g. "Pulled from your property details", "From your registration").

### 4. Expenses & Income — "Why we ask" popup
Update the "Why we ask" text to include:
- This is used for **affordability assessment** of current plans and recommendations
- This data feeds into the **risk profile** section

### 5. Assets & Wealth — Continue cycles through tabs
- Main **Continue** button cycles through the asset tabs (Property → Savings → Investments → etc.) before advancing to the next step
- **Remove** the internal "Next" CTA within each tab — Continue handles tab advancement
- Scroll to top on each tab change

### 6. Retirement tab changes
- **Remove** "Expected Return" from the pension input form
- **Rename** "Planned access age" label to **"Retirement age"**
- Add **information hover icon** stating where the retirement age value came from (e.g. "Default UK State Pension age" or "From your personal information")

### 7. Scroll to top on all tab/step transitions
Every Continue press that moves to the next tab or step must `window.scrollTo({ top: 0, behavior: 'smooth' })`.

---

## Files to investigate/modify

| Change | Files |
|--------|-------|
| 1. Did you know card | `OnboardingWizard.vue` or step component — find the "Did you know" card data |
| 2. Section reorder | `OnboardingWizard.vue` or `onboarding.js` store — step ordering config |
| 3. Prefilled info icons | Step components that show prefilled data (PersonalInfoStep, ExpenditureStep, etc.) |
| 4. Why we ask text | ExpenditureStep.vue, IncomeStep.vue — sidebar content |
| 5. Assets tab cycling | Assets step component — Continue button logic |
| 6. Retirement tab | Retirement step component — form fields |
| 7. Scroll to top | All step components — handleNext methods |
