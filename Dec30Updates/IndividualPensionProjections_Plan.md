# Plan: Individual DC Pension Monte Carlo Projections

## Goal
Add Monte Carlo projection chart to DC pension detail views, matching the existing chart in the Future Value tab but for a single pension. Remove Projections tab from DB and State pension views.

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Services/Retirement/RetirementProjectionService.php` | Add `projectIndividualDCPension()` method |
| `app/Http/Controllers/Api/RetirementController.php` | Add `getDCPensionProjection()` method |
| `routes/api.php` | Add new route |
| `resources/js/services/retirementService.js` | Add `getDCPensionProjection()` |
| `resources/js/components/NetWorth/PensionDetailInline.vue` | Add chart for DC, remove tab for DB/State |
| `resources/js/views/Retirement/PensionDetail.vue` | Same changes |

---

## Implementation Steps

### Step 1: Backend Service Method
**File**: `app/Services/Retirement/RetirementProjectionService.php`

Add method after `projectPensionPot()` (~line 119):

```php
/**
 * Project individual DC pension pot growth using Monte Carlo simulation.
 */
public function projectIndividualDCPension(int $pensionId, int $userId): array
{
    $user = User::findOrFail($userId);
    $pension = $user->dcPensions()->findOrFail($pensionId);

    $currentAge = $user->date_of_birth?->age ?? 40;
    $retirementAge = $pension->retirement_age ?? $this->getRetirementAge($user);
    $yearsToRetirement = max(1, $retirementAge - $currentAge);

    $currentValue = (float) ($pension->current_fund_value ?? 0);
    $monthlyContribution = $this->calculateMonthlyContribution($pension);

    // Get risk parameters
    $riskLevel = $pension->risk_preference ?? $this->getUserRiskLevel($user);
    $riskParams = $this->riskService->getReturnParameters($riskLevel);

    $expectedReturn = $riskParams['expected_return_typical'] / 100;
    $volatility = $riskParams['volatility'] / 100;

    // Run Monte Carlo simulation
    $simulation = $this->simulator->simulate(
        $currentValue,
        $monthlyContribution,
        $expectedReturn,
        $volatility,
        $yearsToRetirement,
        self::MONTE_CARLO_ITERATIONS
    );

    $yearByYear = $this->extractProbabilityBands($simulation, $yearsToRetirement);
    $lastYear = $yearByYear[count($yearByYear) - 1] ?? [];

    return [
        'pension_id' => $pensionId,
        'scheme_name' => $pension->scheme_name,
        'current_value' => round($currentValue, 2),
        'monthly_contribution' => round($monthlyContribution, 2),
        'risk_level' => $riskLevel,
        'expected_return' => $riskParams['expected_return_typical'],
        'volatility' => $riskParams['volatility'],
        'years_to_retirement' => $yearsToRetirement,
        'retirement_age' => $retirementAge,
        'current_age' => $currentAge,
        'percentile_5_at_retirement' => round($lastYear['percentile_5'] ?? $currentValue, 2),
        'median_at_retirement' => round($lastYear['percentile_50'] ?? $currentValue, 2),
        'year_by_year' => $yearByYear,
    ];
}
```

### Step 2: Controller Method
**File**: `app/Http/Controllers/Api/RetirementController.php`

Add method:

```php
/**
 * Get Monte Carlo projections for a specific DC pension.
 */
public function getDCPensionProjection(int $id): JsonResponse
{
    $userId = Auth::id();

    try {
        $projections = $this->projectionService->projectIndividualDCPension($id, $userId);

        return response()->json([
            'success' => true,
            'data' => $projections,
        ]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Pension not found',
        ], 404);
    }
}
```

### Step 3: API Route
**File**: `routes/api.php`

Add to retirement routes group:

```php
Route::get('/dc-pensions/{id}/projections', [RetirementController::class, 'getDCPensionProjection']);
```

### Step 4: Frontend Service
**File**: `resources/js/services/retirementService.js`

Add method:

```javascript
async getDCPensionProjection(pensionId) {
    const response = await api.get(`/retirement/dc-pensions/${pensionId}/projections`);
    return response.data;
},
```

### Step 5: PensionDetailInline.vue Updates
**File**: `resources/js/components/NetWorth/PensionDetailInline.vue`

**5a. Import chart component** (line ~360):
```javascript
import PensionPotProjectionChart from '@/components/Retirement/PensionPotProjectionChart.vue';
```

**5b. Register component** (line ~368):
```javascript
components: {
    UnifiedPensionForm,
    ConfirmationModal,
    PensionPotProjectionChart,
},
```

**5c. Add data properties** (line ~387):
```javascript
data() {
    return {
        activeTab: 'overview',
        loading: false,
        showEditModal: false,
        showDeleteConfirm: false,
        projectionData: null,
        projectionLoading: false,
    };
},
```

**5d. Update tabs computed** (line ~397):
```javascript
tabs() {
    const baseTabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'documents', label: 'Documents' },
    ];
    // Only DC pensions get Projections tab
    if (this.pensionType === 'dc') {
        baseTabs.splice(1, 0, { id: 'projections', label: 'Projections' });
    }
    return baseTabs;
},
```

**5e. Add watcher for tab changes** (after computed, before methods):
```javascript
watch: {
    activeTab(newTab) {
        if (newTab === 'projections' && !this.projectionData && this.pensionType === 'dc') {
            this.loadProjections();
        }
    },
},
```

**5f. Add loadProjections method** (in methods):
```javascript
async loadProjections() {
    if (this.projectionLoading || this.projectionData) return;

    this.projectionLoading = true;
    try {
        const response = await retirementService.getDCPensionProjection(this.pension.id);
        if (response.success) {
            this.projectionData = response.data;
        }
    } catch (error) {
        console.error('Failed to load projections:', error);
    } finally {
        this.projectionLoading = false;
    }
},
```

**5g. Import service** (top of script):
```javascript
import retirementService from '@/services/retirementService';
```

**5h. Replace Projections tab template** (lines 315-322):
```vue
<!-- Projections Tab (DC only) -->
<div v-show="activeTab === 'projections'" class="projections-tab">
    <div v-if="projectionLoading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p class="mt-4 text-gray-600">Loading projections...</p>
    </div>
    <div v-else-if="projectionData">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                <p class="text-sm text-gray-600">Current Value</p>
                <p class="text-xl font-bold text-blue-600">{{ formatCurrency(projectionData.current_value) }}</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                <p class="text-sm text-gray-600">95% Probability at Retirement</p>
                <p class="text-xl font-bold text-green-600">{{ formatCurrency(projectionData.percentile_5_at_retirement) }}</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 border border-purple-200">
                <p class="text-sm text-gray-600">Median at Retirement</p>
                <p class="text-xl font-bold text-purple-600">{{ formatCurrency(projectionData.median_at_retirement) }}</p>
            </div>
        </div>

        <!-- Monte Carlo Chart -->
        <div class="bg-white rounded-lg border border-gray-200 p-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Projected Pension Pot Growth</h3>
            <PensionPotProjectionChart :data="projectionData" />
        </div>

        <!-- Assumptions -->
        <div class="mt-4 text-sm text-gray-500">
            <p>Based on {{ projectionData.years_to_retirement }} years to retirement age {{ projectionData.retirement_age }},
            {{ projectionData.risk_level }} risk profile ({{ projectionData.expected_return }}% expected return),
            and {{ formatCurrency(projectionData.monthly_contribution) }}/month contributions.</p>
        </div>
    </div>
    <div v-else class="text-center py-12 text-gray-500">
        <p>Unable to load projection data</p>
    </div>
</div>
```

### Step 6: PensionDetail.vue Updates
**File**: `resources/js/views/Retirement/PensionDetail.vue`

Apply same changes as Step 5 (identical structure).

---

## Data Structure (matching existing chart)

```javascript
{
    pension_id: 1,
    scheme_name: "Company Pension",
    current_value: 125000,
    monthly_contribution: 500,
    risk_level: "medium",
    expected_return: 5.0,
    volatility: 12.0,
    years_to_retirement: 25,
    retirement_age: 65,
    current_age: 40,
    percentile_5_at_retirement: 280000,
    median_at_retirement: 420000,
    year_by_year: [
        { year: 2025, year_number: 0, percentile_5: 125000, percentile_10: ..., percentile_15: ..., percentile_20: ..., percentile_50: ... },
        // ... one entry per year to retirement
    ]
}
```

## Notes

- `PensionPotProjectionChart.vue` requires no changes - already reusable
- MonteCarloSimulator already handles single-asset simulation
- DB/State pensions no longer show Projections tab (user preference)
- Chart shows 4 probability bands: 95%, 90%, 85%, 80%
