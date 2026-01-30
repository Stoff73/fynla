# Deployment Notes - January 30, 2026

---

## Remove Individual Retirement Age Fields from Pension Forms

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Removed individual retirement age input fields from all three pension form types. These fields are no longer needed as retirement age will be managed centrally rather than per-pension.

### Changes Made

| Form | Field Removed | Notes |
|------|---------------|-------|
| DCPensionForm.vue | "Planned Retirement Age" | Also removed validation method, formData property, watcher logic |
| DBPensionForm.vue | "Normal Retirement Age" | Also removed formData property, watcher logic, apiData mapping |
| StatePensionForm.vue | "Your State Pension Age" | Also removed formData property, populateForm logic, validation, dataToSend mapping |

### DCPensionForm.vue Changes

| Change | Description |
|--------|-------------|
| Template | Removed retirement_age input field from "Expected Return and Retirement Age" grid (now just "Expected Return") |
| formData | Removed `retirement_age` property |
| validationErrors | Removed `retirement_age` property |
| watch | Removed code that populated retirement_age from `currentUser.target_retirement_age` |
| methods | Removed `validateRetirementAge()` method |
| handleSubmit | Removed retirement age validation call and check |

### DBPensionForm.vue Changes

| Change | Description |
|--------|-------------|
| Template | Removed normal_retirement_age input field from "Accrual Rate and Normal Retirement Age" grid (now just "Accrual Rate") |
| formData | Removed `normal_retirement_age` property |
| watch | Removed code that populated normal_retirement_age from `currentUser.target_retirement_age` |
| handleSubmit | Removed `normal_retirement_age` from apiData mapping |

### StatePensionForm.vue Changes

| Change | Description |
|--------|-------------|
| Template | Removed "Your State Pension Age" input field and helper text (link to gov.uk/state-pension-age) |
| formData | Removed `state_pension_age` property (was defaulting to 67) |
| populateForm | Removed state_pension_age mapping from statePension prop |
| handleSubmit | Removed state_pension_age validation check |
| handleSubmit | Removed `state_pension_age` from dataToSend mapping |

### Files Changed (3 files - Included in Build)

**Retirement Module:**
```text
resources/js/components/Retirement/DCPensionForm.vue
resources/js/components/Retirement/DBPensionForm.vue
resources/js/components/Retirement/StatePensionForm.vue
```

---

## Retirement Age Label Updates - Onboarding & User Profile

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Updated the retirement age field label and helper text in both onboarding and user profile to emphasise that this is the central retirement age used for all pension calculations.

### Changes Made

| Location | Before | After |
|----------|--------|-------|
| Onboarding (IncomeStep.vue) - Label | "What age do you want to retire?" | "Retirement Age" |
| Onboarding (IncomeStep.vue) - Helper text | "Your planned retirement age. This may be different to the age entered for your DC Pension Plans." | "Planned retirement age, used for all pension forecast calculations." |
| User Profile View Mode - Label | "Target Retirement Age:" | "Retirement Age:" |
| User Profile Edit Mode - Label | "Target Retirement Age" | "Retirement Age" |
| User Profile Edit Mode - Helper text | (none) | "Planned retirement age, used for all pension forecast calculations." |

### Files Changed (2 files - Included in Build)

**Onboarding:**
```text
resources/js/components/Onboarding/steps/IncomeStep.vue
```

**User Profile:**
```text
resources/js/components/UserProfile/PersonalInformation.vue
```

---

## Onboarding Completion - Remove Icon

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Removed the completion icon from the onboarding completion screen entirely. Previously there was a green checkmark for full completion and an orange warning triangle for partial completion.

### Before

- Full completion: Green checkmark icon
- Partial completion: Orange warning triangle icon

### After

- No icon displayed

### Files Changed (1 file - Included in Build)

**Onboarding:**
```text
resources/js/components/Onboarding/steps/CompletionStep.vue
```

---

## Onboarding Progress Bar - Color Updates

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Updated the onboarding progress bar step indicator colors for better visual distinction.

### Color Changes

| State | Before | After |
|-------|--------|-------|
| Current step (circle) | Blue (`bg-blue-600`) | Teal (`bg-teal-600`) |
| Current step (label) | Blue (`text-blue-600`) | Teal (`text-teal-600`) |
| Skipped step (circle) | Orange (`bg-orange-500`) | Blue (`bg-blue-500`) |
| Skipped step (label) | Orange (`text-orange-600`) | Blue (`text-blue-600`) |

### Files Changed (1 file - Included in Build)

**Onboarding:**
```text
resources/js/components/Onboarding/OnboardingWizard.vue
```

---

## Pension Pot Projection - Retirement Age Display

**Branch:** decumRetire

**Status:** Ready to deploy

### Description

Added prominent display of retirement age and years to retirement at the top of the Pension Pot Projection chart in the Retirement dashboard's Future Value tab. This uses the retirement age from the user's profile (set in Employment & Income / Personal Information).

### Changes Made

| Change | Description |
|--------|-------------|
| Retirement age info box | New teal-styled info box showing Retirement Age and Years to Retirement |
| Chart subtitle | Simplified subtitle (removed "to age X" as it's now shown above) |
| New CSS styles | Added `.retirement-age-info`, `.retirement-age-item`, `.retirement-age-label`, `.retirement-age-value`, `.retirement-age-divider` |

### Visual Design

The retirement age info displays inline with the summary cards in a 3-column layout:

```
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────┐
│ PENSION POT     │  │ PROJECTED VALUE │  │ RETIREMENT  │  YEARS TO │
│ VALUE           │  │ (80%)           │  │ AGE         │  GO       │
│ £125,000        │  │ £450,000        │  │ 67          │  22       │
└─────────────────┘  └─────────────────┘  └─────────────────────────┘
     (blue)               (purple)               (teal)
```

### Files Changed (2 files - Included in Build)

**Net Worth Module:**

```text
resources/js/components/NetWorth/PensionList.vue
```

**Retirement Module:**

```text
resources/js/components/Retirement/FutureValueTab.vue
```

---

## Rebuild Required: YES

Frontend Vue components changed. Full rebuild required:

```bash
./deploy/fynla-org/build.sh
```

---

## Upload Checklist

### Step 1: Run Build

```bash
cd /Users/Chris/Desktop/fynla
./deploy/fynla-org/build.sh
```

### Step 2: Upload Built Assets

Upload the entire `public/build/` directory to:

```text
~/www/fynla.org/public_html/public/build/
```

### Step 3: Upload PHP Files

No PHP files changed in this deployment.

### Step 4: Clear Cache (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear
```

---

## Rollback

If issues occur:

1. Restore previous `public/build/` directory from backup
2. Clear cache:
   ```bash
   php artisan cache:clear && php artisan config:clear && php artisan view:clear
   ```

---
