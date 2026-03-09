# Journey-Driven Simplified Onboarding — Deployment Notes

**Date:** 9 March 2026
**Branch:** `onboarding`
**Feature:** Simplified onboarding forms for budgeting and protection journeys

---

## What Changed

### Phase 1: Budgeting Journey

Budgeting journey users get a streamlined 4-step onboarding flow:

**Personal Info → Income → Expenditure → Savings → Completion**

Each step collects only the minimum data needed for budgeting.

### Phase 2: Protection Journey

Protection journey users get a 7-step onboarding flow:

**Personal Info → Income → Expenditure → Property & Mortgage → Family → Debts → Existing Protection → Completion**

The simplified personal info and income steps conditionally show extra fields (DOB, marital status, health, smoking, occupation) when protection is selected.

### Budgeting + Protection Combined

When both are selected, an 8-step merged flow is used — no duplicate fields:

**Personal Info → Income → Expenditure → Property & Mortgage → Family → Debts → Savings → Existing Protection → Completion**

A single smart completion screen shows benefits for ALL completed journeys.

---

## Files to Upload

### New Files (6) — Frontend Components

```
resources/js/components/Onboarding/steps/SimplePersonalInfoStep.vue
resources/js/components/Onboarding/steps/SimpleIncomeStep.vue
resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue
resources/js/components/Onboarding/steps/SimpleSavingsAccountStep.vue
resources/js/components/Onboarding/steps/SimplePropertyMortgageStep.vue
resources/js/components/Onboarding/steps/BudgetingCompletionStep.vue
```

### Modified Files (3)

```
app/Services/Onboarding/JourneyFieldResolver.php
resources/js/components/Onboarding/OnboardingWizard.vue
resources/js/components/Onboarding/steps/JourneyCompletionStep.vue
```

### Build Output (rebuild required)

```
public/build/    (entire directory — run build script first)
```

---

## Deployment Steps

### 1. Build locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

Upload to `~/www/fynla.org/public_html/`:

| Local Path | Remote Path |
|------------|-------------|
| `public/build/` | `public_html/public/build/` |
| `app/Services/Onboarding/JourneyFieldResolver.php` | `public_html/app/Services/Onboarding/JourneyFieldResolver.php` |

The 6 new Vue components and the modified `OnboardingWizard.vue` + `JourneyCompletionStep.vue` are compiled into the build output — no need to upload them separately.

### 3. SSH and clear caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## What Each File Does

### New Components

| Component | What it shows | Key behaviour |
|-----------|--------------|---------------|
| `SimplePersonalInfoStep.vue` | Read-only name/surname, phone number | DOB conditional (hidden for budgeting-only). Marital status conditional (protection/estate). Health status & smoking status conditional (protection only) |
| `SimpleIncomeStep.vue` | Employment status (includes Student), monthly take-home pay | Occupation autocomplete conditional (protection only). Retirement age conditional (retirement only) |
| `SimpleExpenditureStep.vue` | Single monthly spending amount | Monthly only (no toggle). Surplus preview if income entered |
| `SimpleSavingsAccountStep.vue` | Inline savings account form | Creates real accounts via `savingsService.createAccount()`. Multiple accounts supported |
| `SimplePropertyMortgageStep.vue` | Mortgage details + optional property details | "I do not own a property" checkbox. Mortgage: lender, type, balance, payment, rate, end date. Property: type, value, address (optional expandable). Creates real records via `propertyService` |
| `BudgetingCompletionStep.vue` | Budgeting-specific congratulations | **Deprecated** — retained for backwards compatibility but `JourneyCompletionStep` now handles all journeys |

### Modified Files

| File | What changed |
|------|-------------|
| `JourneyFieldResolver.php` | Added protection to `JOURNEY_STEP_OVERRIDES` (7 steps). Added `MULTI_JOURNEY_STEP_OVERRIDES` with `budgeting,protection` combination (8 merged steps). Updated `getStepsForJourneys()` to check multi-journey overrides |
| `JourneyCompletionStep.vue` | **Rewritten** as single smart completion component. Shows personalised benefits for ALL completed journeys (budgeting, protection, or both). Dynamic heading adapts to single vs multi-journey. Replaces both `BudgetingCompletionStep` and old generic completion |
| `OnboardingWizard.vue` | Added `SimplePropertyMortgageStep` import/registration. Added direct component mappings for `FamilyInfoStep`, `LiabilitiesStep`, `ProtectionPoliciesStep`. Added step labels for progress bar (Property, Family, Debts, Protection). Replaced dual completion logic with single `JourneyCompletionStep` for all journeys |

---

## How It Works

### Backend Flow

1. User selects journey(s) (e.g., "Protection" or "Budgeting + Protection")
2. Frontend calls `GET /api/journeys/{journey}/steps`
3. `JourneyController` calls `JourneyFieldResolver::getStepsForJourney()` or `getStepsForJourneys()`
4. Method checks `JOURNEY_STEP_OVERRIDES` (single journey) or `MULTI_JOURNEY_STEP_OVERRIDES` (combinations) first
5. Returns explicit steps with simplified component names

### Frontend Flow

1. `OnboardingWizard.vue` receives steps from backend
2. `resolveJourneyComponent()` maps each step's `component` field to the actual Vue component
3. User progresses through steps — simplified components check `store.state.journeys.selections` to show/hide conditional fields
4. After last step, `handleJourneyNext()` triggers completion
5. Smart `JourneyCompletionStep` shown with benefits for all completed journeys

### Conditional Fields Logic

The simplified step components use journey selections to conditionally show/hide fields:

```javascript
// SimplePersonalInfoStep.vue
const showMaritalStatus = computed(() => {
  const selections = store.state.journeys?.selections || [];
  return selections.includes('protection') || selections.includes('estate');
});

const showHealthFields = computed(() => {
  const selections = store.state.journeys?.selections || [];
  return selections.includes('protection');
});
```

```javascript
// SimpleIncomeStep.vue
const showOccupation = computed(() => {
  const selections = store.state.journeys?.selections || [];
  return selections.includes('protection');
});
```

### Extensibility

Other journeys can be added to `JOURNEY_STEP_OVERRIDES` and `MULTI_JOURNEY_STEP_OVERRIDES`:

```php
private const JOURNEY_STEP_OVERRIDES = [
    'budgeting' => [...],
    'protection' => [...],
    'investment' => [...],  // Future
    'retirement' => [...],  // Future
];

private const MULTI_JOURNEY_STEP_OVERRIDES = [
    'budgeting,protection' => [...],
    'budgeting,investment' => [...],  // Future
];
```

---

## No Database Changes

No migrations required. The `saveStepData` endpoint, `savingsService.createAccount()`, `propertyService.createProperty()`, and `propertyService.createPropertyMortgage()` APIs already exist. The new components use existing backend endpoints.

---

## Testing

### Budgeting Journey

1. Register a new account or use `chris@fynla.org` / `Password1!`
2. Navigate to journey selection and choose "Budgeting"
3. Verify 4-step flow: Personal → Income → Expenditure → Savings
4. Check progress bar shows: Personal, Income, Spending, Savings
5. Verify name/surname are read-only on step 1
6. Verify DOB is NOT shown (budgeting-only)
7. Verify "Student" appears in employment status dropdown
8. Verify income is labelled as "take-home pay" / "after tax"
9. Verify expenditure step shows monthly only (no toggle)
10. Add a savings account — verify it appears in the account list
11. Complete journey — verify completion screen shows budgeting benefits
12. Verify "View your Budgeting Dashboard" navigates to `/net-worth/cash`

### Protection Journey

13. Start new journey selection and choose "Protection"
14. Verify 7-step flow: Personal → Income → Spending → Property → Family → Debts → Protection
15. Verify step 1 shows: DOB, marital status, health status, smoking status
16. Verify marital status and DOB are required
17. Verify step 2 shows occupation autocomplete field
18. Verify step 4 (Property & Mortgage) has "I do not own a property" checkbox
19. Verify mortgage fields: lender, type, balance, payment, rate, end date
20. Verify optional property details section expands when checked
21. Verify Family, Debts, and Protection steps reuse existing full components
22. Complete journey — verify completion screen shows protection benefits

### Budgeting + Protection Combined

23. Start new journey selection and choose both "Budgeting" and "Protection"
24. Verify 8-step flow: Personal → Income → Spending → Property → Family → Debts → Savings → Protection
25. Verify personal info shows ALL fields (DOB, marital, health, smoking)
26. Verify income shows occupation autocomplete
27. Verify savings step appears (from budgeting)
28. Complete journey — verify completion screen shows BOTH budgeting and protection benefits
29. Verify "Continue to next journey" button appears if additional journeys selected
