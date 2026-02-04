# Fynla Feature Patterns

## Overview

This document covers common patterns for adding or extending features within existing Fynla modules.

## Pattern 1: Adding a New Calculation

**Use case**: "Add net worth calculation to Estate module"

### Backend Changes

1. **Create/Update Service** (`app/Services/Estate/{Service}.php`):
```php
<?php

declare(strict_types=1);

namespace App\Services\Estate;

class NetWorthCalculator
{
    public function calculate(int $userId): array
    {
        // Fetch all assets
        $properties = $this->getPropertyValues($userId);
        $investments = $this->getInvestmentValues($userId);
        $cash = $this->getCashValues($userId);

        // Fetch all liabilities
        $mortgages = $this->getMortgageBalances($userId);
        $loans = $this->getLoanBalances($userId);

        // Calculate net worth
        $totalAssets = $properties + $investments + $cash;
        $totalLiabilities = $mortgages + $loans;
        $netWorth = $totalAssets - $totalLiabilities;

        return [
            'net_worth' => $netWorth,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'breakdown' => [
                'assets' => [
                    'properties' => $properties,
                    'investments' => $investments,
                    'cash' => $cash,
                ],
                'liabilities' => [
                    'mortgages' => $mortgages,
                    'loans' => $loans,
                ],
            ],
        ];
    }
}
```

2. **Update Agent** to use new service:
```php
public function __construct(
    private NetWorthCalculator $netWorthCalculator
) {}

public function analyze(int $userId): array
{
    $netWorth = $this->netWorthCalculator->calculate($userId);

    return [
        'net_worth' => $netWorth,
        'recommendations' => $this->generateRecommendations($netWorth),
    ];
}
```

3. **Add Controller Method** (if new endpoint needed):
```php
public function calculateNetWorth(Request $request): JsonResponse
{
    try {
        $netWorth = $this->agent->calculateNetWorth($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $netWorth,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
```

4. **Add Route** (if needed):
```php
Route::post('/estate/net-worth', [EstateController::class, 'calculateNetWorth']);
```

5. **Write Tests**:
```php
describe('NetWorthCalculator', function () {
    it('calculates net worth correctly', function () {
        $calculator = new NetWorthCalculator();

        $result = $calculator->calculate(1);

        expect($result['net_worth'])->toBeGreaterThan(0);
        expect($result)->toHaveKeys(['total_assets', 'total_liabilities']);
    });
});
```

### Frontend Changes

1. **Update API Service** (`resources/js/services/estateService.js`):
```javascript
export default {
  async calculateNetWorth() {
    const response = await api.post('/estate/net-worth');
    return response.data.data;
  },
};
```

2. **Add Vuex Action** (`resources/js/store/modules/estate.js`):
```javascript
actions: {
  async calculateNetWorth({ commit }) {
    commit('SET_LOADING', true);
    try {
      const netWorth = await estateService.calculateNetWorth();
      commit('SET_NET_WORTH', netWorth);
      return netWorth;
    } catch (error) {
      commit('SET_ERROR', error.message);
      throw error;
    } finally {
      commit('SET_LOADING', false);
    }
  },
}
```

3. **Add Component or Update Existing View** to display the calculation.

---

## Pattern 2: Adding a New Data Type

**Use case**: "Add pension types to Retirement module"

### Backend Changes

1. **Create Migration**:
```bash
php artisan make:migration create_db_pensions_table
```

```php
Schema::create('db_pensions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('scheme_name');
    $table->decimal('annual_pension', 15, 2);
    $table->integer('retirement_age');
    $table->enum('ownership_type', ['individual', 'joint', 'trust'])->default('individual');
    $table->bigInteger('joint_owner_id')->nullable();
    $table->foreignId('trust_id')->nullable();
    $table->timestamps();

    $table->index('user_id');
});
```

2. **Create Model** (`app/Models/Retirement/DBPension.php`):
```php
<?php

declare(strict_types=1);

namespace App\Models\Retirement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DBPension extends Model
{
    protected $table = 'db_pensions';

    protected $fillable = [
        'user_id',
        'scheme_name',
        'annual_pension',
        'retirement_age',
        'ownership_type',
        'joint_owner_id',
        'trust_id',
    ];

    protected $casts = [
        'annual_pension' => 'decimal:2',
        'retirement_age' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
```

3. **Add CRUD Methods to Controller**:
```php
public function storeDBPension(StoreDBPensionRequest $request): JsonResponse
{
    try {
        $pension = DBPension::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $pension,
        ], 201);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
```

4. **Create Form Request**:
```php
class StoreDBPensionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'scheme_name' => ['required', 'string', 'max:255'],
            'annual_pension' => ['required', 'numeric', 'min:0'],
            'retirement_age' => ['required', 'integer', 'min:55', 'max:75'],
            'ownership_type' => ['required', 'in:individual,joint,trust'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'trust_id' => ['nullable', 'exists:trusts,id'],
        ];
    }
}
```

5. **Add Routes**:
```php
Route::prefix('retirement')->group(function () {
    Route::get('/db-pensions', [RetirementController::class, 'indexDBPensions']);
    Route::post('/db-pensions', [RetirementController::class, 'storeDBPension']);
    Route::put('/db-pensions/{id}', [RetirementController::class, 'updateDBPension']);
    Route::delete('/db-pensions/{id}', [RetirementController::class, 'destroyDBPension']);
});
```

### Frontend Changes

1. **Update API Service**:
```javascript
export default {
  async getDBPensions() {
    const response = await api.get('/retirement/db-pensions');
    return response.data.data;
  },

  async storeDBPension(data) {
    const response = await api.post('/retirement/db-pensions', data);
    return response.data.data;
  },
};
```

2. **Add Vuex State/Actions**:
```javascript
state: {
  dbPensions: [],
},

mutations: {
  SET_DB_PENSIONS(state, pensions) {
    state.dbPensions = pensions;
  },
},

actions: {
  async fetchDBPensions({ commit }) {
    const pensions = await retirementService.getDBPensions();
    commit('SET_DB_PENSIONS', pensions);
  },
},
```

3. **Create Form Component** (see form-modal-pattern.md)

4. **Update Dashboard View** to show new data type

---

## Pattern 3: Adding a Chart/Visualization

**Use case**: "Add performance chart to Investment dashboard"

### Backend Changes

1. **Add Endpoint** to return chart data:
```php
public function getPerformanceData(Request $request): JsonResponse
{
    try {
        $data = $this->agent->getPerformanceData($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
```

2. **Agent Method** to structure data:
```php
public function getPerformanceData(int $userId): array
{
    $accounts = InvestmentAccount::where('user_id', $userId)->get();

    $series = [];
    $categories = [];

    foreach ($accounts as $account) {
        $history = $this->getAccountHistory($account->id);

        $series[] = [
            'name' => $account->account_name,
            'data' => $history->pluck('value')->toArray(),
        ];

        if (empty($categories)) {
            $categories = $history->pluck('date')->toArray();
        }
    }

    return [
        'series' => $series,
        'categories' => $categories,
    ];
}
```

### Frontend Changes

1. **Update API Service**:
```javascript
async getPerformanceData() {
  const response = await api.get('/investment/performance-data');
  return response.data.data;
},
```

2. **Create Chart Component** (see chart-pattern.md):
```vue
<template>
  <div class="performance-chart">
    <h3>Performance Over Time</h3>
    <apexchart
      type="line"
      :options="chartOptions"
      :series="series"
    ></apexchart>
  </div>
</template>

<script>
export default {
  name: 'PerformanceChart',

  props: {
    data: {
      type: Object,
      required: true,
    },
  },

  computed: {
    series() {
      return this.data.series || [];
    },

    chartOptions() {
      return {
        chart: {
          type: 'line',
          height: 350,
        },
        xaxis: {
          categories: this.data.categories || [],
        },
        yaxis: {
          labels: {
            formatter: (value) => `£${value.toLocaleString()}`,
          },
        },
      };
    },
  },
};
</script>
```

3. **Add to Dashboard**:
```vue
<PerformanceChart v-if="performanceData" :data="performanceData" />
```

---

## Pattern 4: Adding a New Tab to Module Dashboard

**Use case**: "Add 'Scenarios' tab to Estate dashboard"

### Frontend Changes

1. **Update Dashboard Component** (`resources/js/views/Estate/EstateDashboard.vue`):
```vue
<script>
data() {
  return {
    tabs: [
      { id: 'overview', name: 'Overview' },
      { id: 'property', name: 'Property' },
      { id: 'scenarios', name: 'Scenarios' },  // New tab
    ],
  };
},
</script>

<template>
  <div v-if="activeTab === 'scenarios'" class="space-y-6">
    <ScenariosView />
  </div>
</template>
```

2. **Create Tab Component** (`resources/js/components/Estate/ScenariosView.vue`):
```vue
<template>
  <div class="scenarios-view">
    <h2 class="text-2xl font-bold mb-4">What-If Scenarios</h2>
    <!-- Scenario content -->
  </div>
</template>

<script>
export default {
  name: 'ScenariosView',
  // Component logic
};
</script>
```

---

## Pattern 5: Adding Validation Rules

**Use case**: "Add ISA ownership validation"

### Backend Changes

1. **Update Form Request**:
```php
public function rules(): array
{
    return [
        'account_type' => ['required', 'in:savings,isa,current'],
        'ownership_type' => ['required', 'in:individual,joint,trust'],
    ];
}

public function withValidator($validator)
{
    $validator->after(function ($validator) {
        if ($this->account_type === 'isa' && $this->ownership_type !== 'individual') {
            $validator->errors()->add(
                'ownership_type',
                'ISAs can only be individually owned under UK tax rules.'
            );
        }
    });
}
```

2. **Add Controller Validation**:
```php
if ($validated['account_type'] === 'isa' && $validated['ownership_type'] !== 'individual') {
    return response()->json([
        'success' => false,
        'message' => 'ISAs can only be individually owned under UK tax rules.',
    ], 422);
}
```

### Frontend Changes

1. **Add Form Validation**:
```vue
<script>
methods: {
  validateForm() {
    if (this.formData.account_type === 'isa' && this.formData.ownership_type !== 'individual') {
      this.errors.ownership_type = 'ISAs can only be individually owned';
      return false;
    }
    return true;
  },
}
</script>
```

2. **Show Error Messages**:
```vue
<template>
  <div v-if="errors.ownership_type" class="text-red-600 text-sm mt-1">
    {{ errors.ownership_type }}
  </div>
</template>
```

---

## Pattern 6: Extending Agent Recommendations

**Use case**: "Add new recommendation logic to Protection agent"

### Backend Changes

1. **Update Agent Method**:
```php
private function generateRecommendations(array $analysis): array
{
    $recommendations = [];

    // Existing recommendation logic
    if ($analysis['coverage_gap'] > 0) {
        $recommendations[] = [
            'type' => 'warning',
            'title' => 'Coverage Gap Identified',
            'description' => "You have a protection gap of £{$analysis['coverage_gap']}",
            'action' => 'Increase life insurance coverage',
        ];
    }

    // New recommendation logic
    if ($analysis['income_protection_coverage'] === 0) {
        $recommendations[] = [
            'type' => 'info',
            'title' => 'Consider Income Protection',
            'description' => 'You currently have no income protection insurance',
            'action' => 'Review income protection options',
        ];
    }

    return $recommendations;
}
```

2. **Write Tests**:
```php
it('generates income protection recommendation when coverage is zero', function () {
    $agent = new ProtectionAgent();

    $recommendations = $agent->analyze(1);

    expect($recommendations['recommendations'])
        ->toContain(fn($r) => $r['title'] === 'Consider Income Protection');
});
```

---

## Pattern 7: Adding Cross-Module Integration

**Use case**: "Estate IHT calculation needs to pull from Investment module"

### Backend Changes

1. **Create Cross-Module Service** (`app/Services/Estate/IHTCalculator.php`):
```php
<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Investment\InvestmentAccount;
use App\Models\Property;
use App\Models\SavingsAccount;

class IHTCalculator
{
    public function calculateGrossEstate(int $userId): float
    {
        // Pull from multiple modules
        $properties = Property::where('user_id', $userId)->sum('current_value');
        $investments = InvestmentAccount::where('user_id', $userId)->sum('current_value');
        $cash = SavingsAccount::where('user_id', $userId)->sum('current_balance');

        return $properties + $investments + $cash;
    }

    public function calculateIHT(int $userId): array
    {
        $grossEstate = $this->calculateGrossEstate($userId);
        $liabilities = $this->getLiabilities($userId);
        $netEstate = $grossEstate - $liabilities;

        $taxConfig = config('uk_tax_config.current_tax_year');
        $nrb = $taxConfig['iht_nil_rate_band'];

        $taxableEstate = max(0, $netEstate - $nrb);
        $ihtLiability = $taxableEstate * 0.4; // 40% IHT rate

        return [
            'gross_estate' => $grossEstate,
            'liabilities' => $liabilities,
            'net_estate' => $netEstate,
            'nil_rate_band' => $nrb,
            'taxable_estate' => $taxableEstate,
            'iht_liability' => $ihtLiability,
        ];
    }
}
```

2. **Use in Agent**:
```php
public function __construct(
    private IHTCalculator $ihtCalculator
) {}

public function analyze(int $userId): array
{
    $iht = $this->ihtCalculator->calculateIHT($userId);

    return [
        'iht_analysis' => $iht,
        'recommendations' => $this->generateIHTRecommendations($iht),
    ];
}
```

---

## Checklist for Adding Features

When adding a new feature, ensure:

**Backend**:
- [ ] Service/calculation logic implemented
- [ ] Agent updated (if needed)
- [ ] Controller method added (if new endpoint)
- [ ] Form Request validation created (if needed)
- [ ] Routes registered (if new endpoint)
- [ ] Unit tests for service logic
- [ ] Feature tests for API endpoints
- [ ] UK tax config integration (if financial calculation)

**Frontend**:
- [ ] API service method added
- [ ] Vuex action/mutation added
- [ ] Component created/updated
- [ ] Form validation (if form)
- [ ] Error handling
- [ ] Loading states
- [ ] Dashboard updated (if needed)
- [ ] Navigation updated (if needed)

**Integration**:
- [ ] Cross-module dependencies resolved
- [ ] Cache invalidation (if needed)
- [ ] Documentation updated
