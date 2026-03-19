# "Why This Matters" Helper Text Removal

**Date:** 19 March 2026
**Branch:** `onboardFix`

## Summary

Removed all 17 "Why this matters" helper text blocks from 14 onboarding form steps. These violet info boxes are redundant alongside existing field-level helper text and will be replaced with different contextual content later.

## Files Changed (14)

| File | Occurrences |
|------|-------------|
| `AssetsStep.vue` | 4 (pensions, properties, investments, cash) |
| `PersonalInfoStep.vue` | 2 (personal info, health & lifestyle) |
| `SimplePropertyMortgageStep.vue` | 1 |
| `SimpleSavingsAccountStep.vue` | 1 |
| `SimpleExpenditureStep.vue` | 1 |
| `SimpleIncomeStep.vue` | 1 |
| `SimplePersonalInfoStep.vue` | 1 |
| `GoalSetupStep.vue` | 1 |
| `StudentLoanStep.vue` | 1 |
| `BudgetingSteps.vue` | 1 |
| `IncomeStep.vue` | 1 |
| `LiabilitiesStep.vue` | 1 |
| `ProtectionPoliciesStep.vue` | 1 |
| `DomicileInformationStep.vue` | 1 |

## Pattern Removed

Two variants were used:

**Simple (10 files):**
```html
<div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
  <p class="text-body-sm text-violet-800">
    <strong>Why this matters:</strong> [explanation text]
  </p>
</div>
```

**With icon (AssetsStep — 4 tabs):**
```html
<div class="bg-violet-50 border border-violet-200 rounded-lg p-4">
  <div class="flex">
    <svg ...info icon... />
    <div>
      <p class="text-body-sm text-violet-800">
        <strong>Why this matters:</strong> [explanation text]
      </p>
    </div>
  </div>
</div>
```
