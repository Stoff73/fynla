# Fynla Module Architecture

## Overview

Fynla (Financial Planning System) uses an **agent-based architecture** where each module follows a consistent three-tier pattern:

```
Presentation Layer (Vue.js 3 + ApexCharts)
         ↕
Application Layer (Laravel Controllers + Agents + Services)
         ↕
Data Layer (MySQL + Eloquent Models)
```

## Core Components

### 1. Agent (Business Logic Layer)

**Location**: `app/Agents/{ModuleName}Agent.php`

The Agent is the brain of the module, orchestrating business logic and coordinating services.

**Responsibilities**:
- Take user inputs from dynamic forms
- Perform domain-specific calculations
- Generate recommendations
- Return structured outputs
- Coordinate multiple services

**Pattern**:
```php
<?php

declare(strict_types=1);

namespace App\Agents;

use App\Services\{ModuleName}\{Service};

class {ModuleName}Agent extends BaseAgent
{
    public function __construct(
        private {Service} $service
    ) {}

    public function analyze(int $userId): array
    {
        // 1. Fetch data
        $data = $this->fetchUserData($userId);

        // 2. Perform calculations
        $analysis = $this->service->calculate($data);

        // 3. Generate recommendations
        $recommendations = $this->generateRecommendations($analysis);

        return [
            'analysis' => $analysis,
            'recommendations' => $recommendations,
        ];
    }

    private function generateRecommendations(array $analysis): array
    {
        // Business logic for generating recommendations
        return [];
    }
}
```

### 2. Service (Calculation Layer)

**Location**: `app/Services/{ModuleName}/{Service}.php`

Services contain reusable calculation logic and complex business rules.

**Responsibilities**:
- Perform financial calculations
- Apply UK tax rules
- Handle complex algorithms
- Return structured data (no recommendations)

**Pattern**:
```php
<?php

declare(strict_types=1);

namespace App\Services\{ModuleName};

class {Service}
{
    public function calculate(array $input): array
    {
        // Pure calculation logic
        $result = $this->performCalculation($input);

        return [
            'value' => $result,
            'breakdown' => $this->getBreakdown($result),
        ];
    }

    private function performCalculation(array $input): float
    {
        // Calculation implementation
        return 0.0;
    }
}
```

### 3. Controller (API Layer)

**Location**: `app/Http/Controllers/Api/{ModuleName}Controller.php`

Controllers handle HTTP requests, delegate to agents, and return JSON responses.

**Responsibilities**:
- Validate requests (via Form Requests)
- Authorize access
- Delegate to agents/services
- Return consistent JSON responses
- Handle errors

**Pattern**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\{ModuleName}Agent;
use App\Http\Controllers\Controller;
use App\Http\Requests\{ModuleName}\{ActionRequest};
use Illuminate\Http\JsonResponse;

class {ModuleName}Controller extends Controller
{
    public function __construct(
        private {ModuleName}Agent $agent
    ) {}

    public function analyze({ActionRequest} $request): JsonResponse
    {
        try {
            $analysis = $this->agent->analyze($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
```

### 4. Form Request (Validation Layer)

**Location**: `app/Http/Requests/{ModuleName}/{Action}Request.php`

Form Requests handle input validation separately from controllers.

**Pattern**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\{ModuleName};

use Illuminate\Foundation\Http\FormRequest;

class {Action}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or implement authorization logic
    }

    public function rules(): array
    {
        return [
            'field_name' => ['required', 'numeric', 'min:0'],
            'optional_field' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'field_name.required' => 'This field is required.',
        ];
    }
}
```

### 5. Model (Data Layer)

**Location**: `app/Models/{ModuleName}/{Model}.php`

Eloquent models represent database tables and define relationships.

**Pattern**:
```php
<?php

declare(strict_types=1);

namespace App\Models\{ModuleName};

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class {Model} extends Model
{
    protected $fillable = [
        'user_id',
        'field_name',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date_field' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
```

### 6. API Routes

**Location**: `routes/api.php`

API routes connect HTTP endpoints to controllers.

**Pattern**:
```php
use App\Http\Controllers\Api\{ModuleName}Controller;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('{module-name}')->group(function () {
        Route::get('/', [{ModuleName}Controller::class, 'index']);
        Route::post('/', [{ModuleName}Controller::class, 'store']);
        Route::get('/{id}', [{ModuleName}Controller::class, 'show']);
        Route::put('/{id}', [{ModuleName}Controller::class, 'update']);
        Route::delete('/{id}', [{ModuleName}Controller::class, 'destroy']);

        // Custom endpoints
        Route::post('/analyze', [{ModuleName}Controller::class, 'analyze']);
    });
});
```

## Frontend Architecture

### 7. Vuex Store Module

**Location**: `resources/js/store/modules/{moduleName}.js`

Vuex modules manage state for each module.

**Pattern**:
```javascript
export default {
  namespaced: true,

  state: {
    data: [],
    analysis: null,
    loading: false,
    error: null,
  },

  getters: {
    getData: (state) => state.data,
    getAnalysis: (state) => state.analysis,
    isLoading: (state) => state.loading,
  },

  mutations: {
    SET_DATA(state, data) {
      state.data = data;
    },
    SET_ANALYSIS(state, analysis) {
      state.analysis = analysis;
    },
    SET_LOADING(state, loading) {
      state.loading = loading;
    },
    SET_ERROR(state, error) {
      state.error = error;
    },
  },

  actions: {
    async fetchData({ commit }) {
      commit('SET_LOADING', true);
      commit('SET_ERROR', null);
      try {
        const data = await {moduleName}Service.getData();
        commit('SET_DATA', data);
      } catch (error) {
        commit('SET_ERROR', error.message);
      } finally {
        commit('SET_LOADING', false);
      }
    },

    async analyze({ commit }, payload) {
      commit('SET_LOADING', true);
      try {
        const analysis = await {moduleName}Service.analyze(payload);
        commit('SET_ANALYSIS', analysis);
        return analysis;
      } catch (error) {
        commit('SET_ERROR', error.message);
        throw error;
      } finally {
        commit('SET_LOADING', false);
      }
    },
  },
};
```

### 8. API Service

**Location**: `resources/js/services/{moduleName}Service.js`

Services wrap API calls using axios.

**Pattern**:
```javascript
import { api } from './api';

export default {
  async getData() {
    const response = await api.get('/{module-name}');
    return response.data.data;
  },

  async analyze(payload) {
    const response = await api.post('/{module-name}/analyze', payload);
    return response.data.data;
  },

  async store(data) {
    const response = await api.post('/{module-name}', data);
    return response.data.data;
  },

  async update(id, data) {
    const response = await api.put(`/{module-name}/${id}`, data);
    return response.data.data;
  },

  async delete(id) {
    const response = await api.delete(`/{module-name}/${id}`);
    return response.data;
  },
};
```

### 9. Dashboard View

**Location**: `resources/js/views/{ModuleName}/{ModuleName}Dashboard.vue`

Main dashboard component with tabs for different views.

**Pattern**:
```vue
<template>
  <AppLayout>
    <div class="container mx-auto px-4 py-6">
      <h1 class="text-3xl font-bold mb-6">{Module Name} Planning</h1>

      <!-- Tab Navigation -->
      <div class="border-b border-gray-200 mb-6">
        <nav class="-mb-px flex space-x-8">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'py-4 px-1 border-b-2 font-medium text-sm',
              activeTab === tab.id
                ? 'border-blue-500 text-blue-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
            ]"
          >
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <div v-if="activeTab === 'overview'" class="space-y-6">
        <!-- Overview content -->
      </div>

      <div v-if="activeTab === 'analysis'" class="space-y-6">
        <!-- Analysis content -->
      </div>

      <div v-if="activeTab === 'recommendations'" class="space-y-6">
        <!-- Recommendations content -->
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import { mapGetters, mapActions } from 'vuex';

export default {
  name: '{ModuleName}Dashboard',

  components: {
    AppLayout,
  },

  data() {
    return {
      activeTab: 'overview',
      tabs: [
        { id: 'overview', name: 'Overview' },
        { id: 'analysis', name: 'Analysis' },
        { id: 'recommendations', name: 'Recommendations' },
      ],
    };
  },

  computed: {
    ...mapGetters('{moduleName}', ['getData', 'getAnalysis', 'isLoading']),
  },

  methods: {
    ...mapActions('{moduleName}', ['fetchData', 'analyze']),
  },

  mounted() {
    this.fetchData();
  },
};
</script>
```

## Dashboard Card (Overview)

**Location**: `resources/js/components/{ModuleName}/{ModuleName}OverviewCard.vue`

Summary card shown on main dashboard.

**Pattern**:
```vue
<template>
  <div
    class="bg-white rounded-lg shadow-md p-6 cursor-pointer hover:shadow-lg transition-shadow"
    @click="navigateToDashboard"
  >
    <h3 class="text-xl font-semibold mb-4">{Module Name}</h3>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <p class="text-sm text-gray-600">Key Metric 1</p>
        <p class="text-2xl font-bold">{{ formattedMetric1 }}</p>
      </div>
      <div>
        <p class="text-sm text-gray-600">Key Metric 2</p>
        <p class="text-2xl font-bold">{{ formattedMetric2 }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: '{ModuleName}OverviewCard',

  props: {
    data: {
      type: Object,
      required: true,
    },
  },

  computed: {
    formattedMetric1() {
      return this.formatCurrency(this.data.metric1);
    },
    formattedMetric2() {
      return this.formatNumber(this.data.metric2);
    },
  },

  methods: {
    navigateToDashboard() {
      this.$router.push('/{module-name}');
    },
    formatCurrency(value) {
      return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
      }).format(value);
    },
    formatNumber(value) {
      return new Intl.NumberFormat('en-GB').format(value);
    },
  },
};
</script>
```

## UK Tax Configuration Integration

All modules must use centralized UK tax configuration:

**Location**: `config/uk_tax_config.php`

**Usage**:
```php
$taxConfig = config('uk_tax_config.current_tax_year');
$isaAllowance = $taxConfig['isa_allowance'];
$nrb = $taxConfig['iht_nil_rate_band'];
```

## Testing Structure

### Unit Tests

**Location**: `tests/Unit/Services/{ModuleName}/{Service}Test.php`

Test individual service calculations.

**Pattern**:
```php
<?php

use App\Services\{ModuleName}\{Service};

describe('{Service}', function () {
    it('calculates correctly with valid input', function () {
        $service = new {Service}();

        $result = $service->calculate([
            'input' => 100,
        ]);

        expect($result['value'])->toBe(100.0);
    });

    it('handles edge cases', function () {
        $service = new {Service}();

        $result = $service->calculate([
            'input' => 0,
        ]);

        expect($result['value'])->toBe(0.0);
    });
});
```

### Feature Tests

**Location**: `tests/Feature/{ModuleName}/{ModuleName}ApiTest.php`

Test API endpoints with authentication.

**Pattern**:
```php
<?php

use App\Models\User;

describe('{ModuleName} API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('returns analysis data', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/{module-name}/analyze', [
                'field' => 'value',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/{module-name}/analyze');

        $response->assertStatus(401);
    });
});
```

## Module Checklist

When creating a new module, ensure all these components are created:

**Backend**:
- [ ] Model with migrations
- [ ] Agent class (extends BaseAgent)
- [ ] Service class(es)
- [ ] Controller
- [ ] Form Request(s)
- [ ] API routes
- [ ] Unit tests for services
- [ ] Feature tests for API

**Frontend**:
- [ ] Vuex store module
- [ ] API service wrapper
- [ ] Dashboard view (with tabs)
- [ ] Overview card component
- [ ] Form components
- [ ] Chart components
- [ ] Register routes in router
- [ ] Register store module
- [ ] Add to main dashboard

**Integration**:
- [ ] UK tax config integration
- [ ] Dashboard navigation
- [ ] Breadcrumbs
- [ ] Error handling
- [ ] Loading states
