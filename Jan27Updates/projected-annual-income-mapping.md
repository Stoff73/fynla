# Projected Annual Income - Data Flow Mapping

## Overview

This document maps the complete data flow for the **Projected Annual Income** value displayed on the main dashboard's Retirement Overview tab.

---

## 1. Frontend Display

### Component
**File:** `resources/js/components/Dashboard/RetirementOverviewCard.vue`

### Display Location (Lines 11-17)
```vue
<div class="primary-value">
    <span class="value">{{ formatCurrency(projectedIncome) }}/year</span>
    <span class="label">Projected Annual Income</span>
</div>
```

### Breakdown Display (Lines 24-32)
- Potential Income from DC pensions: `{{ formatCurrency(potentialRetirementIncome) }}/yr`
- Guaranteed Income from DB pensions: `{{ formatCurrency(dbPensionIncome) }}/yr`
- State Pension Income: `{{ formatCurrency(statePensionIncome) }}/yr`

### Frontend Calculation Logic (Lines 144-173)
```javascript
// DC Pension wealth converted to income using 4% safe withdrawal rate
potentialRetirementIncome() {
  return this.dcPensionWealth * 0.04;  // 4% rule
}

// DB Pension accrued annual pension
dbPensionIncome() {
  if (!this.dbPensions || this.dbPensions.length === 0) return 0;
  return this.dbPensions.reduce((sum, pension) => {
    return sum + parseFloat(pension.accrued_annual_pension || 0);
  }, 0);
}

// State Pension - use configured amount or default to 11,500
statePensionIncome() {
  if (!this.hasPensions) return 0;
  const configured = parseFloat(this.statePension?.annual_amount || 0);
  return configured > 0 ? configured : DEFAULT_STATE_PENSION; // 11500
}

// TOTAL PROJECTED INCOME
projectedIncome() {
  return this.potentialRetirementIncome + this.dbPensionIncome + this.statePensionIncome;
}
```

---

## 2. Vuex Store

### Store Module
**File:** `resources/js/store/modules/retirement.js`

### State Properties (Lines 11-36)
```javascript
const state = {
    dcPensions: [],           // Defined Contribution pensions array
    dbPensions: [],           // Defined Benefit pensions array
    statePension: null,       // State Pension record
    profile: null,            // User's retirement profile
    analysis: null,           // Analysis results including projected income
};
```

### Getters (Lines 587-614)
```javascript
projectedIncome: (state) => {
  return state.analysis?.projected_income || 0;
},

targetIncome: (state) => {
  return state.analysis?.target_income || 0;
},

incomeGap: (state) => {
  const projected = state.analysis?.projected_income || 0;
  const target = state.analysis?.target_income || 0;
  return target - projected;
},
```

### Key Actions
| Action | Line | Purpose |
|--------|------|---------|
| `fetchRetirementData` | 147 | Fetches pensions & profile from API |
| `analyseRetirement` | 164 | Runs retirement analysis to calculate projected income |

### Component Bindings (Lines 129-132)
```javascript
computed: {
  ...mapState('retirement', ['dcPensions', 'dbPensions', 'statePension', 'profile', 'analysis']),
  ...mapGetters('retirement', ['totalPensionWealth']),
}
```

---

## 3. API Service

### Service File
**File:** `resources/js/services/retirementService.js`

### API Calls (Lines 9-20)
```javascript
// GET /api/retirement - Fetch basic retirement data
async getRetirementData() {
  return api.get(API_BASE);
}
// Returns: { dc_pensions, db_pensions, state_pension, profile }

// POST /api/retirement/analyze - Calculate projected income
async analyzeRetirement(data = {}) {
  return api.post(`${API_BASE}/analyze`, data);
}
// Returns: { projected_income, target_income, income_gap, ... }
```

---

## 4. Laravel Controller

### Controller File
**File:** `app/Http/Controllers/Api/RetirementController.php`

### GET /api/retirement (Lines 51-67)
```php
public function index(Request $request): JsonResponse {
    $user = $request->user();
    $data = [
        'profile' => RetirementProfile::where('user_id', $user->id)->first(),
        'dc_pensions' => DCPension::where('user_id', $user->id)->get(),
        'db_pensions' => DBPension::where('user_id', $user->id)->get(),
        'state_pension' => StatePension::where('user_id', $user->id)->first(),
    ];
    return response()->json(['success' => true, 'data' => $data]);
}
```

### POST /api/retirement/analyze (Lines 111-146)
```php
public function analyze(RetirementAnalysisRequest $request): JsonResponse {
    $user = $request->user();
    $analysis = $this->agent->analyze($user->id);  // RetirementAgent

    $flattenedData = [
        'projected_income' => $data['summary']['projected_retirement_income'] ?? 0,
        'target_income' => $data['summary']['target_retirement_income'] ?? 0,
        'income_gap' => $data['summary']['income_gap'] ?? 0,
    ];
    return response()->json(['success' => true, 'data' => $flattenedData]);
}
```

---

## 5. Retirement Agent

### Agent File
**File:** `app/Agents/RetirementAgent.php`

### analyze() Method (Lines 49-105)
```php
public function analyze(int $userId): array {
    $profile = RetirementProfile::where('user_id', $userId)->first();
    $dcPensions = DCPension::where('user_id', $userId)->get();
    $dbPensions = DBPension::where('user_id', $userId)->get();
    $statePension = StatePension::where('user_id', $userId)->first();

    // Core calculation - calls PensionProjector
    $incomeProjection = $this->projector->projectTotalRetirementIncome($userId);

    $targetIncome = (float) $profile->target_retirement_income;
    $projectedIncome = $incomeProjection['total_projected_income'];
    $incomeGap = $targetIncome - $projectedIncome;

    $summary = [
        'projected_retirement_income' => $projectedIncome,  // CORE VALUE
        'target_retirement_income' => $targetIncome,
        'income_gap' => $incomeGap,
    ];

    return $this->response(true, 'Retirement analysis completed', [
        'summary' => $summary,
        'income_projection' => $incomeProjection,
    ]);
}
```

---

## 6. Pension Projector Service (Core Calculations)

### Service File
**File:** `app/Services/Retirement/PensionProjector.php`

### projectTotalRetirementIncome() (Lines 101-155)
```php
public function projectTotalRetirementIncome(int $userId): array {
    $dcPensions = DCPension::where('user_id', $userId)->get();
    $dbPensions = DBPension::where('user_id', $userId)->get();
    $statePension = StatePension::where('user_id', $userId)->first();

    $totalDCValue = 0.0;
    $totalDBIncome = 0.0;
    $statePensionIncome = 0.0;

    $currentAge = $this->getUserAge($userId);

    // DC PENSION PROJECTIONS
    foreach ($dcPensions as $dcPension) {
        $retirementAge = $dcPension->retirement_age ?? 67;
        $yearsToRetirement = max(0, $retirementAge - $currentAge);
        $growthRate = $this->getGrowthRateForPension($dcPension, $userId);
        $projectedValue = $this->projectDCPension($dcPension, $yearsToRetirement, $growthRate);
        $totalDCValue += $projectedValue;
    }

    // DB PENSION PROJECTIONS
    foreach ($dbPensions as $dbPension) {
        $totalDBIncome += $this->projectDBPension($dbPension);
    }

    // STATE PENSION PROJECTION
    if ($statePension) {
        $statePensionIncome = $this->projectStatePension($statePension);
    }

    // CONVERT DC WEALTH TO ANNUAL INCOME (4% rule)
    $dcAnnualIncome = $totalDCValue * 0.04;

    // TOTAL PROJECTED INCOME
    $totalProjectedIncome = $dcAnnualIncome + $totalDBIncome + $statePensionIncome;

    return [
        'dc_total_value' => round($totalDCValue, 2),
        'dc_annual_income' => round($dcAnnualIncome, 2),
        'db_annual_income' => round($totalDBIncome, 2),
        'state_pension_income' => round($statePensionIncome, 2),
        'total_projected_income' => round($totalProjectedIncome, 2),
    ];
}
```

### DC Pension Projection (Lines 36-57)
```php
public function projectDCPension(DCPension $pension, int $yearsToRetirement, float $growthRate): float {
    $currentValue = (float) $pension->current_fund_value;
    $annualContribution = $this->calculateAnnualContribution($pension);

    // Account for platform fees
    $netGrowthRate = $growthRate - ((float) $pension->platform_fee_percent ?? 0.0) / 100;

    // Future value of current fund: FV = PV x (1+r)^n
    $futureValueOfCurrentFund = $currentValue * pow(1 + $netGrowthRate, $yearsToRetirement);

    // Future value of contributions: FV = PMT x [((1+r)^n - 1) / r]
    if ($netGrowthRate > 0 && $annualContribution > 0) {
        $futureValueOfContributions = $annualContribution *
            ((pow(1 + $netGrowthRate, $yearsToRetirement) - 1) / $netGrowthRate);
    } elseif ($annualContribution > 0) {
        $futureValueOfContributions = $annualContribution * $yearsToRetirement;
    }

    return $futureValueOfCurrentFund + $futureValueOfContributions;
}
```

### DB Pension Projection (Lines 66-72)
```php
public function projectDBPension(DBPension $pension): float {
    return (float) $pension->accrued_annual_pension;
}
```

### State Pension Projection (Lines 79-96)
```php
public function projectStatePension(StatePension $statePension): float {
    // Use forecast if available
    if ($statePension->state_pension_forecast_annual) {
        return (float) $statePension->state_pension_forecast_annual;
    }

    // Otherwise calculate based on NI years
    $fullStatePension = 11502.00;  // 2024/25 rate
    $requiredYears = $statePension->ni_years_required;
    $completedYears = min($statePension->ni_years_completed, $requiredYears);

    if ($requiredYears > 0) {
        return ($completedYears / $requiredYears) * $fullStatePension;
    }

    return 0.0;
}
```

### Annual Contribution Calculation (Lines 250-272)
```php
private function calculateAnnualContribution(DCPension $pension): float {
    // Priority 1: Fixed monthly contribution (SIPP/personal pensions)
    $monthlyContribution = (float) ($pension->monthly_contribution_amount ?? 0.0);
    if ($monthlyContribution > 0) {
        return $monthlyContribution * 12;
    }

    // Priority 2: Percentage-based contributions (workplace pensions)
    $annualSalary = (float) ($pension->annual_salary ?? 0.0);
    if ($annualSalary > 0) {
        $employeePercent = (float) ($pension->employee_contribution_percent ?? 0.0);
        $employerPercent = (float) ($pension->employer_contribution_percent ?? 0.0);
        return $annualSalary * (($employeePercent + $employerPercent) / 100);
    }

    return 0.0;
}
```

### Growth Rate Determination (Lines 202-239)
```php
private function getGrowthRateForPension(DCPension $pension, int $userId): float {
    // Priority 1: Pension's own risk_preference (if has_custom_risk is true)
    if ($pension->has_custom_risk && $pension->risk_preference) {
        return $this->getGrowthRateForRiskLevel($pension->risk_preference);
    }

    // Priority 2: User's main risk level from Risk module
    return $this->getGrowthRateForUser($userId);

    // Priority 3: Default 5% fallback
}
```

---

## 7. Database Tables & Models

### DC Pensions Table
**Model:** `app/Models/DCPension.php`

| Field | Type | Purpose |
|-------|------|---------|
| `current_fund_value` | decimal(12,2) | Current pension pot |
| `annual_salary` | decimal(12,2) | Used for % contributions |
| `employee_contribution_percent` | decimal(5,2) | % of salary contributed |
| `employer_contribution_percent` | decimal(5,2) | % employer matches |
| `monthly_contribution_amount` | decimal(10,2) | Fixed monthly contribution |
| `retirement_age` | integer | Age to project to (default 67) |
| `platform_fee_percent` | decimal(6,4) | Reduces growth rate |
| `risk_preference` | string | Custom risk level |
| `has_custom_risk` | boolean | Override user risk? |

### DB Pensions Table
**Model:** `app/Models/DBPension.php`

| Field | Type | Purpose |
|-------|------|---------|
| `accrued_annual_pension` | decimal(12,2) | Guaranteed annual income |
| `scheme_type` | string | Scheme classification |
| `revaluation_method` | string | How pension grows |

### State Pensions Table
**Model:** `app/Models/StatePension.php`

| Field | Type | Purpose |
|-------|------|---------|
| `state_pension_forecast_annual` | decimal(12,2) | Official forecast |
| `ni_years_completed` | integer | NI contributions made |
| `ni_years_required` | integer | Required for full pension |

### Retirement Profiles Table
**Model:** `app/Models/RetirementProfile.php`

| Field | Type | Purpose |
|-------|------|---------|
| `current_age` | integer | User's current age |
| `target_retirement_age` | integer | Retirement goal (default 67) |
| `target_retirement_income` | decimal(12,2) | User's target income |

---

## 8. Complete Calculation Formula

```
PROJECTED ANNUAL INCOME = DC Annual Income + DB Annual Income + State Pension Income

WHERE:

DC Annual Income = (DC Projected Fund Value at Retirement) x 0.04

DC Projected Value = (Current Fund x (1 + Net Growth Rate)^Years)
                   + (Annual Contribution x [((1 + Net Growth Rate)^Years - 1) / Net Growth Rate])

  Net Growth Rate = Risk-based growth rate - platform fees
  Years = target_retirement_age - current_age
  Annual Contribution = (monthly_contribution x 12)
                     OR (annual_salary x (employee% + employer%))

DB Annual Income = SUM of all DB pensions' accrued_annual_pension

State Pension Income = IF forecast available THEN state_pension_forecast_annual
                     ELSE (ni_years_completed / ni_years_required) x 11,502
```

---

## 9. Data Flow Diagram

```
DATABASE LAYER
+---------------------------+
| dc_pensions               |
|   current_fund_value      |
|   annual_salary           |
|   contribution_%s         |
|   platform_fee_percent    |
|   retirement_age          |
+---------------------------+
| db_pensions               |
|   accrued_annual_pension  |
+---------------------------+
| state_pensions            |
|   forecast_annual         |
|   ni_years_completed      |
|   ni_years_required       |
+---------------------------+
| retirement_profiles       |
|   current_age             |
|   target_retirement_age   |
+---------------------------+
            |
            v
SERVICE LAYER
+------------------------------------------+
| PensionProjector                         |
|   projectTotalRetirementIncome()         |
|     - projectDCPension() per pension     |
|     - projectDBPension() per pension     |
|     - projectStatePension()              |
|     - Apply 4% withdrawal rate           |
|                                          |
|   Returns: total_projected_income        |
+------------------------------------------+
            |
            v
AGENT LAYER
+------------------------------------------+
| RetirementAgent::analyze()               |
|   Calls PensionProjector                 |
|   Builds summary with:                   |
|     projected_retirement_income          |
+------------------------------------------+
            |
            v
CONTROLLER LAYER
+------------------------------------------+
| RetirementController::analyze()          |
|   POST /api/retirement/analyze           |
|   Response: { projected_income: 45000 }  |
+------------------------------------------+
            |
            v
API SERVICE LAYER
+------------------------------------------+
| retirementService.analyzeRetirement()    |
|   POST /api/retirement/analyze           |
+------------------------------------------+
            |
            v
VUEX STORE LAYER
+------------------------------------------+
| retirement.js                            |
|   state.analysis.projected_income        |
|   getter: projectedIncome                |
+------------------------------------------+
            |
            v
VUE COMPONENT LAYER
+------------------------------------------+
| RetirementOverviewCard.vue               |
|   computed: projectedIncome              |
|   display: formatCurrency(projectedIncome)|
+------------------------------------------+
```

---

## 10. Key Files Reference

| Layer | File | Lines | Key Function |
|-------|------|-------|--------------|
| Display | `resources/js/components/Dashboard/RetirementOverviewCard.vue` | 11-173 | Display + frontend calc |
| Store | `resources/js/store/modules/retirement.js` | 587-614 | projectedIncome getter |
| API Service | `resources/js/services/retirementService.js` | 9-20 | analyzeRetirement() |
| Controller | `app/Http/Controllers/Api/RetirementController.php` | 111-146 | analyze() endpoint |
| Agent | `app/Agents/RetirementAgent.php` | 49-105 | analyze() orchestration |
| Service | `app/Services/Retirement/PensionProjector.php` | 101-155 | Core calculation |
| DC Model | `app/Models/DCPension.php` | - | DC pension schema |
| DB Model | `app/Models/DBPension.php` | - | DB pension schema |
| State Model | `app/Models/StatePension.php` | - | State pension schema |
| Profile Model | `app/Models/RetirementProfile.php` | - | Retirement profile schema |

---

## 11. Important Notes

### Dual Calculation Path
- **Backend:** `PensionProjector.projectTotalRetirementIncome()` calculates and stores in `analysis.projected_income`
- **Frontend:** `RetirementOverviewCard.vue` independently calculates from component data
- Both use the same 4% withdrawal rate

### 4% Safe Withdrawal Rate Applied In
- Backend: `PensionProjector` line 143: `$dcAnnualIncome = $totalDCValue * 0.04;`
- Frontend: `RetirementOverviewCard` line 145: `return this.dcPensionWealth * 0.04;`

### Growth Rate Priority
1. Pension-level custom risk (`pension.risk_preference` if `has_custom_risk = true`)
2. User's main risk profile from Risk Module
3. Default fallback of 5%

### Platform Fees
Deducted from growth rate: `netGrowthRate = growthRate - platformFeePercent`

### State Pension Logic
- Uses official forecast if available (`state_pension_forecast_annual`)
- Falls back to pro-rata: `(ni_years_completed / ni_years_required) x 11,502`

---

*Document generated: 27 January 2026*
