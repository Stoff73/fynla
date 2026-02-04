---
name: fps-module-builder
description: Guide Claude through creating a complete new financial planning module for the Fynla application. Use this skill when the user requests to create an entirely new module (e.g., "Create a new Tax Planning module", "Add a Business Planning module"). This skill provides step-by-step instructions for building backend (Laravel) and frontend (Vue.js) components following Fynla architecture patterns, coding standards, and UK financial planning requirements.
---

# Fynla Module Builder

## Overview

Create complete financial planning modules for the Fynla application following the agent-based architecture pattern. Each module includes backend components (Controller, Agent, Service, Model, Tests) and frontend components (Vuex store, API service, Dashboard view, components).

## When to Use This Skill

Use this skill when creating an **entirely new module** from scratch:

- "Create a new Tax Planning module"
- "Add a Business Planning module"
- "I want to build a new Cashflow Planning module"

**Do NOT use this skill for**:
- Adding features to existing modules (use `fps-feature-builder` instead)
- Creating individual components (use `fps-component-builder` instead)
- Modifying existing modules

## Workflow: Creating a New Module

Follow these steps in order to create a complete, fully functional module.

---

### Step 1: Understand Requirements

Before writing any code, clarify the module requirements with the user.

**Questions to ask**:
1. What is the module name and purpose?
2. What are the key features/calculations?
3. What data needs to be captured and stored?
4. Does it integrate with other modules?
5. What UK tax rules or financial regulations apply?

**Example**:
```
User: "Create a new Tax Planning module"

Claude: "I'll help you create a Tax Planning module. Let me clarify the requirements:

1. What key features should it include?
   - Income tax planning?
   - Capital gains tax planning?
   - Tax-efficient investment strategies?
   - Tax relief tracking?

2. What data do we need to capture?
   - Income sources?
   - Capital gains?
   - Tax reliefs claimed?

3. Should it integrate with:
   - Investment module for CGT calculations?
   - Retirement module for pension tax relief?
   - Estate module for IHT planning?

4. What calculations are needed?
   - Tax liability projections?
   - Tax-efficient contribution recommendations?
   - CGT allowance tracking?"
```

---

### Step 2: Plan Module Structure

Based on requirements, plan the complete module structure using the checklist in `references/module-architecture.md`.

**Backend Components**:
- Database migration(s) for data tables
- Eloquent model(s)
- Agent class (business logic)
- Service class(es) (calculations)
- Controller (API endpoints)
- Form Request(s) (validation)
- Unit tests (services)
- Feature tests (API endpoints)

**Frontend Components**:
- Vuex store module
- API service wrapper
- Main dashboard view (with tabs)
- Overview card component (for main dashboard)
- Tab components (Overview, Analysis, Recommendations, Details)
- Form modal(s) for data entry
- Chart components (if needed)

**Integration**:
- API routes in `routes/api.php`
- Vuex store registration in `resources/js/store/index.js`
- Route registration in `resources/js/router/index.js`
- Overview card in main dashboard

Create a todo list using TodoWrite tool to track all components.

---

### Step 3: Database Schema & Migration

Create database migration(s) for the module's data tables.

**Reference**: `references/coding-standards.md` (MySQL Standards section)

**Naming convention**: `{module_name}_{entity}` (e.g., `tax_reliefs`, `tax_planning_strategies`)

**Required fields**:
- `id` (BIGINT UNSIGNED AUTO_INCREMENT)
- `user_id` (BIGINT UNSIGNED, foreign key)
- `created_at`, `updated_at` (TIMESTAMP)
- Data fields specific to module
- Ownership fields: `ownership_type`, `joint_owner_id`, `trust_id` (if applicable)

**Example**:
```bash
php artisan make:migration create_tax_reliefs_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_reliefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('relief_type'); // e.g., 'pension', 'gift_aid', 'marriage_allowance'
            $table->decimal('amount_claimed', 15, 2);
            $table->string('tax_year'); // e.g., '2025/26'
            $table->enum('ownership_type', ['individual', 'joint', 'trust'])->default('individual');
            $table->bigInteger('joint_owner_id')->nullable();
            $table->foreignId('trust_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_reliefs');
    }
};
```

Run migration:
```bash
php artisan migrate
```

---

### Step 4: Create Eloquent Model

Create the model with relationships, fillable fields, and casts.

**Reference**: `references/coding-standards.md` (Laravel Best Practices section)

**Location**: `app/Models/{ModuleName}/{Model}.php`

**Example**:
```php
<?php

declare(strict_types=1);

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRelief extends Model
{
    protected $table = 'tax_reliefs';

    protected $fillable = [
        'user_id',
        'relief_type',
        'amount_claimed',
        'tax_year',
        'ownership_type',
        'joint_owner_id',
        'trust_id',
    ];

    protected $casts = [
        'amount_claimed' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'joint_owner_id');
    }
}
```

---

### Step 5: Create Service Class(es)

Create service classes for calculations and business logic.

**Reference**: `references/module-architecture.md` (Service Layer section)

**Location**: `app/Services/{ModuleName}/{Service}.php`

**Pattern**: Services should be focused, testable, and return structured data.

**Example**:
```php
<?php

declare(strict_types=1);

namespace App\Services\Tax;

class TaxLiabilityCalculator
{
    public function calculateIncomeTaxLiability(int $userId, string $taxYear): array
    {
        // 1. Fetch user income data
        $incomeData = $this->fetchIncomeData($userId);

        // 2. Get UK tax config
        $taxConfig = config('uk_tax_config.current_tax_year');

        // 3. Calculate tax liability
        $grossIncome = $incomeData['employment'] + $incomeData['self_employment'];
        $personalAllowance = $taxConfig['income_tax']['personal_allowance'];

        $taxableIncome = max(0, $grossIncome - $personalAllowance);
        $incomeTax = $this->calculateTax($taxableIncome, $taxConfig['income_tax']);

        // 4. Return structured result
        return [
            'gross_income' => $grossIncome,
            'personal_allowance' => $personalAllowance,
            'taxable_income' => $taxableIncome,
            'income_tax' => $incomeTax,
            'effective_rate' => $grossIncome > 0 ? ($incomeTax / $grossIncome) * 100 : 0,
        ];
    }

    private function calculateTax(float $taxableIncome, array $taxConfig): float
    {
        // Implement progressive tax calculation
        // ...
        return 0.0;
    }
}
```

**Write unit tests for all service methods** (Step 10).

---

### Step 6: Create Agent Class

Create the agent that orchestrates the module's business logic.

**Reference**: `references/module-architecture.md` (Agent Layer section)

**Location**: `app/Agents/{ModuleName}Agent.php`

**Pattern**: Agent extends BaseAgent, uses dependency injection, generates recommendations.

**Example**:
```php
<?php

declare(strict_types=1);

namespace App\Agents;

use App\Services\Tax\TaxLiabilityCalculator;

class TaxAgent extends BaseAgent
{
    public function __construct(
        private TaxLiabilityCalculator $taxCalculator
    ) {}

    public function analyze(int $userId): array
    {
        // 1. Calculate tax liability
        $taxLiability = $this->taxCalculator->calculateIncomeTaxLiability($userId, '2025/26');

        // 2. Generate recommendations
        $recommendations = $this->generateRecommendations($taxLiability);

        // 3. Return structured output
        return [
            'tax_liability' => $taxLiability,
            'recommendations' => $recommendations,
            'summary' => $this->generateSummary($taxLiability),
        ];
    }

    private function generateRecommendations(array $taxLiability): array
    {
        $recommendations = [];

        // Example recommendation logic
        if ($taxLiability['effective_rate'] > 40) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'High Tax Rate',
                'description' => 'Your effective tax rate is over 40%. Consider tax-efficient strategies.',
                'action' => 'Review pension contributions and ISA utilization',
            ];
        }

        return $recommendations;
    }

    private function generateSummary(array $taxLiability): array
    {
        return [
            'total_tax' => $taxLiability['income_tax'],
            'status' => $taxLiability['effective_rate'] > 40 ? 'warning' : 'good',
        ];
    }
}
```

---

### Step 7: Create Form Request(s)

Create validation classes for API endpoints.

**Reference**: `references/coding-standards.md` (Laravel Best Practices section)

**Location**: `app/Http/Requests/{ModuleName}/{Action}Request.php`

**Example**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxReliefRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relief_type' => ['required', 'string', 'in:pension,gift_aid,marriage_allowance'],
            'amount_claimed' => ['required', 'numeric', 'min:0'],
            'tax_year' => ['required', 'string', 'regex:/^\d{4}\/\d{2}$/'],
            'ownership_type' => ['required', 'in:individual,joint,trust'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'trust_id' => ['nullable', 'exists:trusts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'relief_type.in' => 'Invalid relief type selected.',
            'tax_year.regex' => 'Tax year must be in format YYYY/YY (e.g., 2025/26).',
        ];
    }
}
```

---

### Step 8: Create Controller

Create API controller with CRUD endpoints and analysis endpoint.

**Reference**: `references/module-architecture.md` (Controller Layer section)

**Location**: `app/Http/Controllers/Api/{ModuleName}Controller.php`

**Example**:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\TaxAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreTaxReliefRequest;
use App\Models\Tax\TaxRelief;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(
        private TaxAgent $agent
    ) {}

    public function index(Request $request): JsonResponse
    {
        $reliefs = TaxRelief::where('user_id', $request->user()->id)->get();

        return response()->json([
            'success' => true,
            'data' => $reliefs,
        ]);
    }

    public function store(StoreTaxReliefRequest $request): JsonResponse
    {
        try {
            $relief = TaxRelief::create([
                'user_id' => $request->user()->id,
                ...$request->validated(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $relief,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function analyze(Request $request): JsonResponse
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

    // Add update(), destroy() methods as needed
}
```

---

### Step 9: Register API Routes

Add routes in `routes/api.php`.

**Reference**: `references/module-architecture.md` (API Routes section)

**Pattern**: Group related routes under module prefix.

**Example**:
```php
use App\Http\Controllers\Api\TaxController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('tax')->group(function () {
        Route::get('/reliefs', [TaxController::class, 'index']);
        Route::post('/reliefs', [TaxController::class, 'store']);
        Route::put('/reliefs/{id}', [TaxController::class, 'update']);
        Route::delete('/reliefs/{id}', [TaxController::class, 'destroy']);

        // Analysis endpoint
        Route::post('/analyze', [TaxController::class, 'analyze']);
    });
});
```

---

### Step 10: Write Backend Tests

Create unit tests for services and feature tests for API endpoints.

**Reference**: `references/module-architecture.md` (Testing Structure section)

**Unit Test Example** (`tests/Unit/Services/Tax/TaxLiabilityCalculatorTest.php`):
```php
<?php

use App\Services\Tax\TaxLiabilityCalculator;

describe('TaxLiabilityCalculator', function () {
    it('calculates income tax correctly for basic rate taxpayer', function () {
        $calculator = new TaxLiabilityCalculator();

        $result = $calculator->calculateIncomeTaxLiability(1, '2025/26');

        expect($result)->toHaveKeys(['gross_income', 'income_tax', 'effective_rate']);
        expect($result['income_tax'])->toBeGreaterThanOrEqual(0);
    });

    it('applies personal allowance correctly', function () {
        $calculator = new TaxLiabilityCalculator();

        $result = $calculator->calculateIncomeTaxLiability(1, '2025/26');

        expect($result['personal_allowance'])->toBe(12570);
    });
});
```

**Feature Test Example** (`tests/Feature/Tax/TaxApiTest.php`):
```php
<?php

use App\Models\User;

describe('Tax API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('returns tax analysis for authenticated user', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/tax/analyze');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'tax_liability',
                    'recommendations',
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/tax/analyze');

        $response->assertStatus(401);
    });
});
```

Run tests:
```bash
./vendor/bin/pest
```

---

### Step 11: Create Frontend API Service

Create API service wrapper for module endpoints.

**Reference**: `references/module-architecture.md` (API Service section)

**Location**: `resources/js/services/{moduleName}Service.js`

**Example**:
```javascript
import { api } from './api';

export default {
  async getReliefs() {
    const response = await api.get('/tax/reliefs');
    return response.data.data;
  },

  async storeRelief(data) {
    const response = await api.post('/tax/reliefs', data);
    return response.data.data;
  },

  async analyze() {
    const response = await api.post('/tax/analyze');
    return response.data.data;
  },

  async updateRelief(id, data) {
    const response = await api.put(`/tax/reliefs/${id}`, data);
    return response.data.data;
  },

  async deleteRelief(id) {
    const response = await api.delete(`/tax/reliefs/${id}`);
    return response.data;
  },
};
```

---

### Step 12: Create Vuex Store Module

Create Vuex module for state management.

**Reference**: `references/module-architecture.md` (Vuex Store Module section)

**Location**: `resources/js/store/modules/{moduleName}.js`

**Example**:
```javascript
import taxService from '@/services/taxService';

export default {
  namespaced: true,

  state: {
    reliefs: [],
    analysis: null,
    loading: false,
    error: null,
  },

  getters: {
    getReliefs: (state) => state.reliefs,
    getAnalysis: (state) => state.analysis,
    isLoading: (state) => state.loading,
    getError: (state) => state.error,
  },

  mutations: {
    SET_RELIEFS(state, reliefs) {
      state.reliefs = reliefs;
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
    async fetchReliefs({ commit }) {
      commit('SET_LOADING', true);
      commit('SET_ERROR', null);
      try {
        const reliefs = await taxService.getReliefs();
        commit('SET_RELIEFS', reliefs);
      } catch (error) {
        commit('SET_ERROR', error.message);
      } finally {
        commit('SET_LOADING', false);
      }
    },

    async analyze({ commit }) {
      commit('SET_LOADING', true);
      try {
        const analysis = await taxService.analyze();
        commit('SET_ANALYSIS', analysis);
        return analysis;
      } catch (error) {
        commit('SET_ERROR', error.message);
        throw error;
      } finally {
        commit('SET_LOADING', false);
      }
    },

    async storeRelief({ dispatch }, data) {
      await taxService.storeRelief(data);
      await dispatch('fetchReliefs');
    },

    async deleteRelief({ dispatch }, id) {
      await taxService.deleteRelief(id);
      await dispatch('fetchReliefs');
    },
  },
};
```

**Register in** `resources/js/store/index.js`:
```javascript
import tax from './modules/tax';

export default createStore({
  modules: {
    // ... existing modules
    tax,
  },
});
```

---

### Step 13: Create Dashboard View

Create main dashboard view with tabbed interface.

**Reference**: `references/module-architecture.md` (Dashboard View section)

**Location**: `resources/js/views/{ModuleName}/{ModuleName}Dashboard.vue`

See `references/module-architecture.md` for complete pattern.

---

### Step 14: Create Overview Card

Create overview card for main dashboard.

**Reference**: `references/module-architecture.md` (Dashboard Card section)

**Location**: `resources/js/components/{ModuleName}/{ModuleName}OverviewCard.vue`

**Add to main dashboard** (`resources/js/views/Dashboard/Dashboard.vue`):
```vue
<TaxOverviewCard v-if="taxData" :data="taxData" />
```

---

### Step 15: Register Routes

Add module routes to Vue Router.

**Location**: `resources/js/router/index.js`

**Example**:
```javascript
import TaxDashboard from '@/views/Tax/TaxDashboard.vue';

const routes = [
  // ... existing routes
  {
    path: '/tax',
    name: 'TaxDashboard',
    component: TaxDashboard,
    meta: { requiresAuth: true },
  },
];
```

---

### Step 16: Test End-to-End

Test the complete module flow:

1. Start Laravel server: `php artisan serve`
2. Start Vite dev server: `npm run dev`
3. Navigate to module dashboard
4. Test creating data
5. Test analysis endpoint
6. Test CRUD operations
7. Check main dashboard overview card

---

## Module Checklist

Use this checklist to ensure completeness:

**Backend**:
- [ ] Migration(s) created and run
- [ ] Model(s) created with relationships
- [ ] Service class(es) created
- [ ] Agent class created (extends BaseAgent)
- [ ] Controller created
- [ ] Form Request(s) created
- [ ] Routes registered in `routes/api.php`
- [ ] Unit tests written for services
- [ ] Feature tests written for API endpoints
- [ ] All tests passing

**Frontend**:
- [ ] API service created
- [ ] Vuex store module created
- [ ] Store registered in `store/index.js`
- [ ] Dashboard view created (with tabs)
- [ ] Overview card created
- [ ] Form modal(s) created
- [ ] Chart component(s) created (if needed)
- [ ] Routes registered in `router/index.js`
- [ ] Overview card added to main dashboard

**Integration**:
- [ ] UK tax config integration (if applicable)
- [ ] Cross-module dependencies handled
- [ ] Cache strategy implemented (if needed)
- [ ] Error handling in place
- [ ] Loading states implemented
- [ ] End-to-end testing complete

## References

This skill includes comprehensive reference documentation:

### references/module-architecture.md
Complete Fynla module architecture patterns including:
- Three-tier architecture (Presentation, Application, Data)
- Agent pattern implementation
- Service layer patterns
- Controller patterns
- Vuex store patterns
- Component patterns
- Testing patterns

Load this reference when you need detailed patterns for any module component.

### references/coding-standards.md
Fynla coding standards including:
- PHP PSR-12 standards
- MySQL naming conventions and schema design
- Vue.js 3 style guide
- JavaScript/TypeScript best practices
- Laravel best practices
- Asset ownership patterns
- Form modal event naming (avoid @submit bug)

Load this reference to ensure code quality and consistency.

## Key Principles

1. **Agent-Based Architecture**: Business logic lives in Agent classes
2. **Service Layer**: Complex calculations in dedicated service classes
3. **UK Tax Rules**: Always reference `config/uk_tax_config.php`
4. **PSR-12 Compliance**: Follow PHP coding standards
5. **Vue Style Guide**: Follow Vue.js best practices
6. **Test First**: Write tests for all financial calculations
7. **Progressive Disclosure**: Summaries first, details on demand
8. **Mobile-First**: Responsive design from 320px
9. **No Hardcoded Values**: Use centralized configuration
10. **Data Isolation**: Users can only access their own data

## Common Pitfalls to Avoid

1. **Don't hardcode tax rates** - Always use `config/uk_tax_config.php`
2. **Don't skip tests** - Financial calculations must be tested
3. **Don't use `@submit` events** - Use `@save` for form modals
4. **Don't forget ownership fields** - Include ownership_type, joint_owner_id, trust_id
5. **Don't skip ISA validation** - ISAs must be individually owned
6. **Don't forget cache invalidation** - Clear cache when data changes
7. **Don't skip authorization checks** - Verify user ownership
8. **Don't create todos without tracking** - Use TodoWrite tool
9. **Don't forget to register routes** - Both API and Vue Router
10. **Don't skip end-to-end testing** - Test complete user flow
