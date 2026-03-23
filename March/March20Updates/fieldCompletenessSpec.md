# Field-Level Completeness Tracking — Spec

**Date:** 20 March 2026
**Goal:** Replace binary step completion with field-level tracking. Three states: skipped, partial, complete. Used by onboarding progress bar, dashboard nudges, decision engines, and AI.

---

## Current State (What Exists)

### 1. `LifeStageService::getDataCompleteness()` (step-level, binary)
- Returns array of step IDs that have ANY data
- Used by: onboarding progress bar via `GET /api/life-stage/progress`
- **Problem:** `personal-info` is "complete" if just DOB + gender exist. No field-level detail.

### 2. `PrerequisiteGateService` + 5 DataReadiness services (module-level, field-level)
- Returns per-module blocking/warning/info checks with `key`, `passed`, `form_link`
- Used by: agents, AI chat, `GET /api/life-stage/completeness`
- **Problem:** Organised by module (savings, investment, etc.), not by onboarding step. Doesn't know about step-level field requirements.

### 3. `life_stage_completed_steps` (user model column)
- Binary array — step ID added when user clicks Continue OR Skip
- **Problem:** No difference between "filled everything" and "clicked through empty"

### 4. `LifeStageController::completeness()` endpoint
- Already returns per-module field-level detail from DataReadiness services
- Already has `completeness_percent`, `blocking`, `warnings`, `total_checks`, `passed_checks`
- **This is the closest to what we need — we extend this, not duplicate it.**

---

## What Needs to Change

### Extend `getDataCompleteness()` → `getStepCompleteness()`

Replace the binary step check with field-level tracking per onboarding step. Each step defines its tracked fields. The method checks each field against the DB and returns status.

```php
public function getStepCompleteness(User $user): array
{
    $stage = $user->life_stage;
    $stageConfig = $this->getStageFieldConfig($stage);

    $result = [];
    foreach ($stageConfig as $stepId => $fields) {
        $filled = [];
        $missing = [];

        foreach ($fields as $field) {
            if ($this->isFieldFilled($user, $field)) {
                $filled[] = $field;
            } else {
                $missing[] = $field;
            }
        }

        $total = count($fields);
        $filledCount = count($filled);

        $result[$stepId] = [
            'status' => $this->determineStatus($filledCount, $total),
            'filled' => $filled,
            'missing' => $missing,
            'filled_count' => $filledCount,
            'total_count' => $total,
            'percentage' => $total > 0 ? round(($filledCount / $total) * 100) : 0,
        ];
    }

    return $result;
}

private function determineStatus(int $filled, int $total): string
{
    if ($total === 0) return 'complete';  // no fields required
    if ($filled === 0) return 'skipped';
    if ($filled >= $total) return 'complete';
    return 'partial';
}
```

### Field Definitions Per Step

Each step has a list of tracked fields. These map to actual DB columns or relationship checks.

```php
private function getStepFieldConfig(string $stage): array
{
    // Base fields for all journeys
    $steps = [
        'personal-info' => [
            'date_of_birth',
            'gender',
        ],
        'income' => [
            'employment_status',
            'annual_employment_income',  // or any income > 0
        ],
        'assets' => [
            'has_savings_or_investments_or_pensions_or_property',
        ],
        'goals' => [
            'has_goals',
        ],
    ];

    // Stage-specific field additions
    // These match the fields VISIBLE in onboarding for that stage
    if (in_array($stage, ['mid_career', 'peak', 'retirement'])) {
        $steps['personal-info'][] = 'marital_status';
        $steps['personal-info'][] = 'address_line_1';
        $steps['personal-info'][] = 'city';
        $steps['personal-info'][] = 'postcode';
        $steps['personal-info'][] = 'phone';
        $steps['personal-info'][] = 'health_status';
        $steps['personal-info'][] = 'smoking_status';
    }

    if (in_array($stage, ['mid_career', 'peak', 'retirement'])) {
        $steps['family'] = ['has_family_members'];
    }

    if ($stage === 'university') {
        $steps['student-loan'] = ['has_liabilities'];
        $steps['expenditure'] = ['has_expenditure'];
    }

    // ... etc per journey

    return $steps;
}
```

### `isFieldFilled()` — single method to check any field

```php
private function isFieldFilled(User $user, string $field): bool
{
    return match ($field) {
        // User model columns
        'date_of_birth' => $user->date_of_birth !== null,
        'gender' => !empty($user->gender),
        'marital_status' => !empty($user->marital_status),
        'employment_status' => !empty($user->employment_status),
        'address_line_1' => !empty($user->address_line_1),
        'city' => !empty($user->city),
        'postcode' => !empty($user->postcode),
        'phone' => !empty($user->phone),
        'health_status' => !empty($user->health_status),
        'smoking_status' => !empty($user->smoking_status),
        'occupation' => !empty($user->occupation),
        'employer' => !empty($user->employer),

        // Income (any source > 0)
        'annual_employment_income' => $this->calculateTotalIncome($user) > 0,

        // Relationships (exists checks)
        'has_family_members' => $user->familyMembers()->exists(),
        'has_savings' => $user->savingsAccounts()->exists(),
        'has_investments' => $user->investmentAccounts()->exists(),
        'has_pensions' => $user->dcPensions()->exists() || $user->dbPensions()->exists(),
        'has_property' => $user->properties()->exists(),
        'has_savings_or_investments_or_pensions_or_property' =>
            $user->savingsAccounts()->exists() ||
            $user->investmentAccounts()->exists() ||
            $user->dcPensions()->exists() ||
            $user->dbPensions()->exists() ||
            $user->properties()->exists(),
        'has_goals' => $user->goals()->exists(),
        'has_liabilities' => $user->liabilities()->exists(),
        'has_expenditure' => $user->monthly_expenditure > 0 || $this->hasExpenditureProfile($user),
        'has_will' => \App\Models\Estate\Will::where('user_id', $user->id)->exists(),
        'has_protection' => $user->lifeInsurancePolicies()->exists()
            || $user->criticalIllnessPolicies()->exists()
            || $user->incomeProtectionPolicies()->exists(),
        'has_state_pension' => $user->statePension()->exists(),

        default => false,
    };
}
```

### Full Field Completeness (for agents/AI — not journey-filtered)

A separate method that checks ALL possible fields regardless of journey. Used by decision engines and AI to know what's missing site-wide.

```php
public function getFullFieldCompleteness(User $user): array
{
    // Returns ALL fields across ALL steps, regardless of journey
    // Agents use this to guide users to fill missing data
    // Does NOT filter by onboarding visibility
}
```

This reuses the same `isFieldFilled()` method but with the full field list, not the journey-filtered one.

---

## API Changes

### Update `GET /api/life-stage/progress`

Add `step_completeness` to the response alongside existing `data_completed_steps`:

```json
{
  "success": true,
  "data": {
    "life_stage": "peak",
    "completed_steps": ["personal-info", "income-tax"],
    "data_completed_steps": ["personal-info"],
    "step_completeness": {
      "personal-info": {
        "status": "partial",
        "filled": ["date_of_birth", "gender", "marital_status"],
        "missing": ["address_line_1", "city", "postcode", "phone", "health_status", "smoking_status"],
        "filled_count": 3,
        "total_count": 9,
        "percentage": 33
      },
      "family": {
        "status": "skipped",
        "filled": [],
        "missing": ["has_family_members"],
        "filled_count": 0,
        "total_count": 1,
        "percentage": 0
      }
    }
  }
}
```

No new endpoint needed. Extends existing response.

---

## Frontend Changes

### Progress Bar (OnboardingWizard.vue)

Replace `isLifeStageStepCompleted()` with three-state check from `step_completeness`:

| Status | Circle | Icon | Label colour |
|--------|--------|------|-------------|
| `skipped` | Raspberry bg | X or dash | Raspberry |
| `partial` | Raspberry bg + spring border | Number | Violet |
| `complete` | Spring bg | Tick | Spring |
| Current | Stage colour (pulsing) | Number | Stage colour |
| Upcoming | White bg, light-gray border | Number | Neutral |

### Dashboard

Existing `completeness` endpoint already provides module-level field detail. No changes needed — agents and dashboard cards already use this.

### Vuex Store

Add `stepCompleteness` to lifeStage state, populated from the `progress` endpoint response.

---

## Files to Change

| File | Change |
|------|--------|
| `app/Services/LifeStage/LifeStageService.php` | Add `getStepCompleteness()`, `getFullFieldCompleteness()`, `isFieldFilled()`, `getStepFieldConfig()` |
| `app/Http/Controllers/Api/LifeStageController.php` | Add `step_completeness` to `progress()` response |
| `resources/js/store/modules/lifeStage.js` | Add `stepCompleteness` state, mutation, getter |
| `resources/js/components/Onboarding/OnboardingWizard.vue` | Update progress bar to use three states |

**No new files. No new endpoints. No new tables.**

---

## What Does NOT Change

- `PrerequisiteGateService` — untouched, still handles module-level agent gates
- 5 DataReadiness services — untouched, still handle module-level field checks
- `completeness` endpoint — untouched, still serves dashboard/agents
- `completeStep` action — still called, but progress bar no longer relies solely on it
- Form components — no changes needed, they already save to DB

---

## Key Design Decisions

1. **Journey-aware for onboarding only**: `getStepCompleteness()` filters fields by journey. `getFullFieldCompleteness()` shows everything for agents/AI.
2. **No new database columns**: All data already exists in the DB. We're just querying it differently.
3. **Reuses `isFieldFilled()`**: Single method for all field checks. No duplication with DataReadiness services (those check module prerequisites, this checks individual field presence).
4. **Backend-driven**: Frontend just reads the status from the API. No frontend field counting.
