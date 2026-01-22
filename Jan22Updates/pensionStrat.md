# Retirement Strategies System - Technical Documentation

This document details the complete decision tree, data sources, feasibility checks, and projection mathematics for the retirement strategies system.

---

## 1. Strategy Decision Tree

The retirement strategies system uses a **hierarchical, priority-ordered decision tree** with 4 strategy levels. Each strategy is evaluated in order with cumulative context tracking.

### Strategy Priority Hierarchy

```
Priority 1: EMPLOYER MATCH (Maximise Free Money)
    ├── Check if employee contribution < employer matching limit
    ├── Infer matching limit from contribution patterns or explicit field
    └── Recommend increasing employee contribution to capture full match

Priority 2: CONTRIBUTION INCREASE (Maximise Tax-Advantaged Saving)
    ├── Check affordability (disposable income > 0)
    ├── Check Annual Allowance remaining
    ├── Include carry-forward if available
    └── Recommend 50% of available capacity (balanced approach)

Priority 3: RETIREMENT AGE ADJUSTMENT (Extend Accumulation Period)
    ├── Find minimum delay that achieves 95% income coverage
    ├── Test years 1-3 incrementally
    ├── Cap recommendations at age 68, allow slider to 75
    └── Provide pot projection at delayed retirement

Priority 4: INCOME TARGET REDUCTION (Adjust Expectations)
    ├── Only triggered if other strategies insufficient
    ├── Recommend reducing target to achievable level
    └── Shows user what they can realistically achieve
```

### Decision Flow

```
User requests strategies
    │
    ▼
Get Monte Carlo projections for current state
    │
    ▼
Calculate current probability of achieving target
    │
    ├── IF probability >= 95%
    │       └── Return "On Track" with no strategies
    │
    └── IF probability < 95%
            │
            ▼
        Build strategies in priority order (1→4)
            │
            ├── Each strategy adds its impact to cumulative context
            ├── Pass cumulative values to next strategy
            └── Stop once probability reaches 95% threshold
            │
            ▼
        Return which strategy (1-4) gets user on track
```

### Key Decision Logic

**File**: `app/Services/Retirement/RetirementStrategyService.php` (lines 36-206)

```php
public function getStrategies(int $userId): array
{
    // 1. Get current projection status
    $projections = $this->projectionService->getProjections($userId);
    $currentProbability = $projections['income_drawdown']['probability'];

    // 2. If on track, no strategies needed
    if ($currentProbability >= 95) {
        return ['on_track' => true, 'strategies' => []];
    }

    // 3. Build strategies with cumulative context
    $strategies = [];
    $cumulativeMonthly = 0;
    $cumulativeIncome = 0;

    // Priority 1: Employer Match
    $employerMatch = $this->checkEmployerMatchStrategy($userId, ...);
    if ($employerMatch) {
        $strategies[] = $employerMatch;
        $cumulativeMonthly += $employerMatch['additional_monthly'];
        $cumulativeIncome += $employerMatch['additional_annual_income'];
    }

    // Priority 2: Contribution Increase (with cumulative context)
    // Priority 3: Retirement Age Adjustment
    // Priority 4: Income Target Reduction

    return $strategies;
}
```

---

## 2. Disposable Income Source

### Complete Data Flow

```
User Profile Data
    │
    ▼
UserProfileService::buildIncomeOccupation()
    │
    ├── Calculate Gross Income (all sources)
    │       ├── Annual employment income
    │       ├── Annual self-employment income
    │       ├── Annual rental income (from properties)
    │       ├── Annual pension income (DB + State if in payment)
    │       ├── Annual dividend income
    │       ├── Annual interest income
    │       └── Annual trust income
    │
    ├── Calculate Net Income
    │       └── UKTaxCalculator::calculateDetailedNetIncome()
    │               ├── Income tax (bands and rates)
    │               ├── National Insurance contributions
    │               ├── Pension contribution deductions
    │               ├── Rental income allowances
    │               └── Dividend allowances
    │
    └── Calculate Annual Expenditure
            └── getExpenditureBreakdown()
                    ├── Manual expenditure (categories OR lump sum)
                    └── Financial commitments (mortgages, loans, etc.)
    │
    ▼
Disposable Income = Net Income - Annual Expenditure
Monthly Disposable = Disposable Income / 12
```

### Expenditure Calculation

**File**: `app/Services/UserProfile/UserProfileService.php` (lines 177-228)

```php
// Two modes of expenditure entry:

IF expenditure_entry_mode === 'category':
    monthly_manual = SUM(
        food_groceries,
        transport_fuel,
        healthcare_medical,
        insurance,
        mobile_phones,
        internet_tv,
        subscriptions,
        clothing_personal_care,
        entertainment_dining,
        holidays_travel,
        pets,
        childcare,
        school_fees,
        school_lunches,
        school_extras,
        university_fees,
        children_activities,
        gifts_charity,
        regular_savings,
        other_expenditure
    )
ELSE:
    monthly_manual = monthly_expenditure (simple lump sum mode)

// Add financial commitments
monthly_commitments = getFinancialCommitments(user)
    ├── Mortgage payments
    ├── Loan repayments
    ├── Insurance premiums
    └── Other committed outgoings

monthly_total = monthly_manual + monthly_commitments
annual_total = monthly_total * 12

// Final calculation
disposable_income = net_income - annual_total
monthly_disposable = disposable_income / 12
```

### Tax Calculation Integration

The net income calculation uses `UKTaxCalculator::calculateDetailedNetIncome()` which factors in:

| Component | Treatment |
|-----------|-----------|
| Income Tax | Applied by band (20%, 40%, 45%) |
| National Insurance | Employee NI rates |
| Pension Contributions | Reduce taxable income (relief at marginal rate) |
| Rental Allowances | Property allowance, finance cost restrictions |
| Dividend Allowances | £1,000 dividend allowance (2024/25) |

---

## 3. Feasibility Checks

### Affordability Validation

**File**: `app/Services/Retirement/RetirementStrategyService.php` (lines 546-631)

For contribution increase strategies:

```php
// 1. Must have positive disposable income
if ($disposable_income <= 0) {
    return null; // Strategy not viable
}

// 2. Must have remaining Annual Allowance
if ($remaining_allowance <= 0) {
    return null; // Strategy not viable
}

// 3. Calculate maximum additional contribution
$max_additional_annual = min($disposable_income, $remaining_allowance);
$max_additional_monthly = $max_additional_annual / 12;

// 4. Recommend half of maximum (conservative approach)
$recommended_monthly = $max_additional_monthly * 0.5;

// 5. Identify binding constraint for user messaging
if ($remaining_allowance < $disposable_income) {
    $constraint = "Limited by Annual Allowance";
} else {
    $constraint = "Limited by affordability";
}
```

### Annual Allowance Checks

**File**: `app/Services/Retirement/AnnualAllowanceChecker.php`

```php
// Standard values (from TaxConfigService)
$standardAA = 60000;          // £60,000
$minimumTaperedAA = 10000;    // £10,000
$thresholdIncome = 260000;    // £260,000
$adjustedIncomeThreshold = 312000; // £312,000

// Tapering calculation for high earners
if ($income > $thresholdIncome && $adjustedIncome > $adjustedIncomeThreshold) {
    $isTapered = true;
    $reduction = ($adjustedIncome - $adjustedIncomeThreshold) / 2;
    $availableAllowance = max($minimumTaperedAA, $standardAA - $reduction);
} else {
    $availableAllowance = $standardAA;
}

// Calculate remaining
$currentContributions = sum(employee + employer contributions);
$remaining = $availableAllowance - $currentContributions;

// Carry-forward (estimated - full tracking requires historical data)
$carryForward = 60000; // 1 year estimate
```

### Employer Match Validation

**File**: `app/Services/Retirement/RetirementStrategyService.php` (lines 514-542)

```php
// Infer matching limit robustly
function inferEmployerMatchLimit($pension) {
    // Priority 1: Explicit limit set by user (1-50%)
    if ($pension->employer_matching_limit && $pension->employer_matching_limit > 0) {
        return $pension->employer_matching_limit;
    }

    // Priority 2: Infer from contribution pattern
    if ($pension->employer_contribution_percent > $pension->employee_contribution_percent) {
        // Employer contributes more - employee can increase to match
        return $pension->employer_contribution_percent;
    }

    // Priority 3: Already at or above employer level
    if ($pension->employee_contribution_percent >= $pension->employer_contribution_percent) {
        return null; // No match opportunity
    }

    return null; // No valid matching scheme detected
}

// Minimum gap to show strategy (avoid trivial improvements)
$minimumGap = 0.5; // 0.5%
if ($matchingOpportunity < $minimumGap) {
    return null;
}
```

---

## 4. Strategy Projections - Mathematics

### Base Formula

All strategy projections use the **Future Value of Annuity** formula:

```
FV = PMT × [((1 + r)^n - 1) / r]
```

Where:
- **FV** = Future Value of additional contributions at retirement
- **PMT** = Monthly contribution amount
- **r** = Monthly growth rate (annual rate / 12)
- **n** = Number of months to retirement

### Projection Calculation

**File**: `app/Services/Retirement/RetirementStrategyService.php` (lines 982-1441)

```php
// Year-by-year pot growth with strategy
for ($year = 0; $year <= $yearsToRetirement; $year++) {
    if ($year == 0) {
        $potWithoutStrategy = $currentPot;
        $potWithStrategy = $currentPot;
    } else {
        // Without strategy: from Monte Carlo 5th percentile
        $potWithoutStrategy = $monteCarloData[$year - 1]['percentile_5'];

        // With strategy: add additional pot from new contributions
        $months = $year * 12;
        $monthlyRate = $growthRate / 12;

        $additionalPot = $additionalMonthly *
            ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);

        $potWithStrategy = $potWithoutStrategy + $additionalPot;
    }

    $projection[] = [
        'year' => $calendarYear,
        'years_from_now' => $year,
        'pot_with_strategy' => round($potWithStrategy),
        'pot_without_strategy' => round($potWithoutStrategy)
    ];
}
```

### Retirement Income Calculation

```php
// Sustainable withdrawal rate: 4.7%
$withdrawalRate = 0.047;

// Calculate sustainable income from DC pot
$sustainableIncomeWith = $potAtRetirementWith * $withdrawalRate;
$sustainableIncomeWithout = $potAtRetirementWithout * $withdrawalRate;

// Add guaranteed income (DB + State Pension)
$guaranteedIncome = $dbPensionAnnual + $statePensionAnnual;

$totalIncomeWith = $sustainableIncomeWith + $guaranteedIncome;
$totalIncomeWithout = $sustainableIncomeWithout + $guaranteedIncome;

// Calculate coverage percentage
$coveragePercent = ($totalIncomeWith / $targetIncome) * 100;
```

### Probability Calculation

```php
// Income ratio drives probability
$incomeRatio = $projectedIncome / $targetIncome;

// Linear mapping with caps
$probability = 10 + ($incomeRatio * 85);
$probability = min(95, max(10, $probability));

// Probability bands:
// 100%+ of target  → 95% (Excellent)
// 90-99% of target → 85% (On Track)
// 75-89% of target → 65% (Needs Attention)
// 50-74% of target → 40% (Off Track)
// 25-49% of target → 20% (Significantly Off Track)
// <25% of target   → 10% (Critical)

// Longevity bonus
if ($fundLastsYears >= 35) {
    $probability += 5;
}
if ($fundLastsYears >= 25) {
    $probability += 3;
}
```

### Employer Match Strategy Math

```php
// Additional income from increasing employee contribution
// (which also triggers additional employer match)

$currentEmployeePercent = $pension->employee_contribution_percent;
$matchingLimit = $inferredMatchingLimit;
$salary = $pension->annual_salary;

// Calculate additional contribution
$additionalPercent = $matchingLimit - $currentEmployeePercent;
$additionalMonthly = ($salary * $additionalPercent / 100) / 12;

// Double it because employer matches
$totalAdditionalMonthly = $additionalMonthly * 2;

// Project to retirement
$months = $yearsToRetirement * 12;
$additionalPot = $totalAdditionalMonthly *
    ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);

$additionalIncome = $additionalPot * 0.047;
```

---

## 5. Data Flow - Complete End-to-End

### API Request Flow

```
┌─────────────────────────────────────────────────────────────┐
│ FRONTEND: StrategiesTab.vue                                 │
│   User views Retirement → Strategies tab                    │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ API: GET /api/retirement/strategies                         │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ STEP 1: GET PROJECTIONS                                     │
│   RetirementProjectionService::getProjections($userId)      │
│   ├── Run Monte Carlo (1,000 iterations)                    │
│   ├── Extract probability bands (5th-90th percentiles)      │
│   ├── Calculate year-by-year projections (0-30 years)       │
│   └── Return: pension_pot_projection + income_drawdown      │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ STEP 2: CALCULATE AFFORDABILITY                             │
│   calculateAffordability($user)                             │
│   ├── UserProfileService::getCompleteProfile()              │
│   ├── Get income_occupation with net income                 │
│   ├── Get expenditure_breakdown (categories + commitments)  │
│   ├── Calculate: disposable = net - expenditure             │
│   └── Return: gross, net, expenditure, disposable           │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ STEP 3: CHECK ANNUAL ALLOWANCE                              │
│   AnnualAllowanceChecker::checkAnnualAllowance()            │
│   ├── Sum all DC pension contributions                      │
│   ├── Get user income for tapering check                    │
│   ├── Apply tapering if high earner                         │
│   ├── Estimate carry-forward                                │
│   └── Return: standard, available, remaining, carry_forward │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ STEP 4: BUILD STRATEGIES (WITH CUMULATIVE CONTEXT)          │
│   FOR each strategy priority (1-4):                         │
│   ├── Calculate additional monthly contribution             │
│   ├── Calculate additional annual income                    │
│   ├── Calculate new probability                             │
│   ├── Build projection with cumulative values               │
│   ├── Add to strategies list                                │
│   ├── Update cumulative values for next strategy            │
│   └── Break if probability >= 95%                           │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ STEP 5: RETURN STRUCTURED RESPONSE                          │
│   {                                                         │
│     current_status: { probability, income_gap, ... },       │
│     affordability: { disposable_income, ... },              │
│     annual_allowance: { remaining, ... },                   │
│     strategies: [ {impact, projection}, ... ],              │
│     on_track_at_strategy: 2                                 │
│   }                                                         │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ FRONTEND RENDERING                                          │
│   ├── Summary cards (disposable income, AA remaining)       │
│   ├── Strategy cards with interactive sliders               │
│   ├── ApexCharts: pot growth comparison                     │
│   └── Income comparison tables                              │
└─────────────────────────────────────────────────────────────┘
```

### API Response Structure

```json
{
  "current_status": {
    "on_track_status": "Off Track",
    "probability": 72,
    "projected_income": 24500,
    "target_income": 40000,
    "income_gap": 15500
  },
  "affordability": {
    "gross_income": 80000,
    "net_income": 62000,
    "annual_expenditure": 45000,
    "monthly_disposable": 1416.67,
    "disposable_income": 17000
  },
  "annual_allowance": {
    "standard_allowance": 60000,
    "available_allowance": 60000,
    "remaining_allowance": 55000,
    "current_contributions": 5000
  },
  "strategies": [
    {
      "type": "employer_match",
      "priority": 1,
      "title": "Maximise Employer Match",
      "current_value": 5,
      "recommended_value": 8,
      "slider_config": {
        "min": 5,
        "max": 10,
        "step": 0.5
      },
      "impact": {
        "additional_monthly": 180,
        "additional_annual_income": 1125,
        "new_probability": 78,
        "probability_improvement": 6
      },
      "projection": {
        "pot_growth": [...],
        "with_strategy": {...},
        "without_strategy": {...}
      }
    }
  ],
  "on_track_at_strategy": 3
}
```

---

## 6. Key Files Reference

| File | Purpose |
|------|---------|
| `app/Services/Retirement/RetirementStrategyService.php` | Main strategy logic, decision tree, projections |
| `app/Services/Retirement/RetirementProjectionService.php` | Monte Carlo projections, income drawdown |
| `app/Services/Retirement/AnnualAllowanceChecker.php` | AA calculations, tapering, carry-forward |
| `app/Services/UserProfile/UserProfileService.php` | Disposable income, expenditure breakdown |
| `app/Services/Tax/UKTaxCalculator.php` | Net income calculation |
| `app/Http/Controllers/Api/RetirementController.php` | API endpoints |
| `resources/js/components/Retirement/StrategiesTab.vue` | Frontend strategy display |
| `resources/js/components/Retirement/StrategyCard.vue` | Individual strategy card with slider |

---

## 7. Mathematical Formulas Summary

```
1. FUTURE VALUE OF ANNUITY (monthly contributions):
   FV = PMT × (((1 + r)^n - 1) / r)
   Where:
     PMT = monthly contribution
     r = monthly_return = annual_return / 12
     n = months to retirement

2. RETIREMENT INCOME FROM POT:
   annual_income = pot_value × 0.047 (4.7% withdrawal rate)

3. PROBABILITY CALCULATION:
   income_ratio = projected_income / target_income
   probability = 10 + (income_ratio × 85)
   CAPPED: MIN(95, MAX(10, probability))

4. EMPLOYER MATCH IMPACT:
   additional_income = (salary × additional_percent / 100) × 2
   (doubled because employer matches employee increase)

5. DISPOSABLE INCOME:
   disposable = net_income - (manual_expenditure + financial_commitments)

6. ANNUAL ALLOWANCE TAPERING:
   reduction = (adjusted_income - £312,000) / 2
   tapered_aa = MAX(£10,000, £60,000 - reduction)
```
