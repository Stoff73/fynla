# FPS Integration Points

## Overview

This document describes how different parts of the FPS system integrate with each other, helping you understand dependencies when adding or modifying features.

## Backend Integration Points

### 1. UK Tax Configuration

**Location**: `app/Services/TaxConfigService.php` (database-backed)

All modules must reference centralized UK tax configuration via TaxConfigService for rates, allowances, and thresholds.

**Usage**:
```php
use App\Services\TaxConfigService;

public function __construct(private TaxConfigService $taxConfig) {}

// Access specific sections
$isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance']; // £20,000
$nrb = $this->taxConfig->getInheritanceTax()['nil_rate_band']; // £325,000
$rnrb = $this->taxConfig->getInheritanceTax()['residence_nil_rate_band']; // £175,000
$pensionAnnualAllowance = $this->taxConfig->getPensionAllowances()['annual_allowance']; // £60,000

// Income tax bands
$incomeTax = $this->taxConfig->getIncomeTax();
$personalAllowance = $incomeTax['personal_allowance'];
$basicRateLimit = $personalAllowance + $incomeTax['bands'][0]['max'];
```

**When to use**:
- Any financial calculation involving UK tax rules
- IHT calculations (Estate module)
- ISA allowance tracking (Savings/Investment modules)
- Pension calculations (Retirement module)
- Income tax calculations (Protection module)

**Never**:
- Hardcode tax rates, allowances, or thresholds
- Store UK tax config values in database (reference config only)

---

### 2. UKTaxCalculator Service

**Location**: `app/Services/UKTaxCalculator.php`

Centralized service for calculating UK income tax and National Insurance.

**Usage**:
```php
use App\Services\UKTaxCalculator;

$taxCalculator = new UKTaxCalculator();

$result = $taxCalculator->calculateNetIncome(
    employmentIncome: 50000,
    selfEmploymentIncome: 0,
    rentalIncome: 12000,
    dividendIncome: 5000,
    otherIncome: 0
);

// Returns:
// [
//     'gross_income' => 67000.00,
//     'income_tax' => 12345.00,
//     'national_insurance' => 4567.00,
//     'total_deductions' => 16912.00,
//     'net_income' => 50088.00,
//     'effective_tax_rate' => 25.24
// ]
```

**When to use**:
- Protection module: Human capital calculations (use NET income)
- Retirement module: Income projections
- Estate module: Income analysis
- Any calculation requiring net income after tax/NI

**Current integrations**:
- `app/Agents/ProtectionAgent.php` - Human capital valuation
- `app/Services/Protection/CoverageGapAnalyzer.php` - Protection needs

---

### 3. Cross-Module Data Access

**Pattern**: Services can access models from other modules for cross-module calculations.

**Example**: Estate IHT calculation pulling from multiple modules

```php
use App\Models\Property;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\Estate\Mortgage;
use App\Models\Estate\Liability;

class IHTCalculator
{
    public function calculateGrossEstate(int $userId): float
    {
        // Properties (Net Worth module)
        $properties = Property::where('user_id', $userId)->sum('current_value');

        // Investment accounts (Investment module)
        $investments = InvestmentAccount::where('user_id', $userId)->sum('current_value');

        // Cash/Savings (Savings module)
        $cash = SavingsAccount::where('user_id', $userId)->sum('current_balance');

        return $properties + $investments + $cash;
    }

    public function calculateLiabilities(int $userId): float
    {
        // Mortgages (Net Worth module)
        $mortgages = Mortgage::where('user_id', $userId)->sum('outstanding_balance');

        // Other liabilities (Estate module)
        $liabilities = Liability::where('user_id', $userId)->sum('amount');

        return $mortgages + $liabilities;
    }
}
```

**Cross-module integrations**:
- **Estate → Net Worth**: Pull property values
- **Estate → Investment**: Pull investment account values
- **Estate → Savings**: Pull cash balances
- **Protection → All modules**: Pull income, debts for coverage calculation
- **Retirement → Investment**: Pull pension fund values

---

### 4. Spouse Data Sharing

**Tables**: `spouse_permissions`, `users.spouse_id`

Users can grant their spouse permission to view/edit specific modules.

**Usage in Controllers**:
```php
public function index(Request $request)
{
    $user = $request->user();

    // Check if viewing own data or spouse's data (with permission)
    $targetUserId = $request->input('user_id', $user->id);

    if ($targetUserId !== $user->id) {
        // Verify spouse relationship
        if ($user->spouse_id !== $targetUserId) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check permission
        $permission = SpousePermission::where('user_id', $targetUserId)
            ->where('spouse_id', $user->id)
            ->where('module_name', 'investment')
            ->first();

        if (!$permission || !$permission->can_view) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
    }

    // Fetch data for target user
    $data = InvestmentAccount::where('user_id', $targetUserId)->get();

    return response()->json(['success' => true, 'data' => $data]);
}
```

**Modules with spouse permissions**:
- User Profile
- Net Worth
- Savings
- Investment
- Retirement
- Estate Planning
- Protection
- Holistic Planning

---

### 5. Joint Ownership

**Pattern**: Assets can be jointly owned by spouses, creating reciprocal records.

**Database schema**:
```php
$table->enum('ownership_type', ['individual', 'joint', 'trust'])->default('individual');
$table->bigInteger('joint_owner_id')->nullable();
$table->foreignId('trust_id')->nullable();
```

**Creating jointly owned assets**:
```php
// User creates property with joint ownership
$property = Property::create([
    'user_id' => $userId,
    'ownership_type' => 'joint',
    'joint_owner_id' => $spouseId,
    'current_value' => 500000,
    // ... other fields
]);

// Automatically create reciprocal property for spouse
Property::create([
    'user_id' => $spouseId,
    'ownership_type' => 'joint',
    'joint_owner_id' => $userId,
    'current_value' => 500000,
    // ... same fields
]);
```

**Assets supporting joint ownership**:
- Properties
- Investment Accounts (except ISAs - UK rule)
- Savings Accounts (except ISAs - UK rule)
- Business Interests
- Chattels
- Mortgages

**ISA restriction**:
```php
// Backend validation
if ($validated['account_type'] === 'isa' && $validated['ownership_type'] !== 'individual') {
    return response()->json([
        'success' => false,
        'message' => 'ISAs can only be individually owned under UK tax rules.',
    ], 422);
}
```

---

### 6. Cache Invalidation

**When**: Invalidate cache when underlying data changes.

**Pattern**:
```php
use Illuminate\Support\Facades\Cache;

// After updating user income
Cache::forget("protection_analysis_{$userId}");
Cache::forget("dashboard_data_{$userId}");

// After creating/updating property
Cache::forget("net_worth_{$userId}");
Cache::forget("estate_iht_{$userId}");

// After ISA subscription
Cache::forget("isa_allowance_{$userId}_{$taxYear}");
```

**Common cache keys**:
- `protection_analysis_{userId}`
- `net_worth_{userId}`
- `estate_iht_{userId}`
- `retirement_readiness_{userId}`
- `dashboard_data_{userId}`
- `isa_allowance_{userId}_{taxYear}`

---

## Frontend Integration Points

### 1. Vuex Store Structure

**Location**: `resources/js/store/index.js`

Centralized state management with module-specific stores.

**Structure**:
```javascript
import { createStore } from 'vuex';
import auth from './modules/auth';
import protection from './modules/protection';
import savings from './modules/savings';
import investment from './modules/investment';
import retirement from './modules/retirement';
import estate from './modules/estate';

export default createStore({
  modules: {
    auth,
    protection,
    savings,
    investment,
    retirement,
    estate,
  },
});
```

**Accessing from components**:
```javascript
import { mapGetters, mapActions } from 'vuex';

export default {
  computed: {
    ...mapGetters('investment', ['getAccounts', 'getAnalysis']),
    ...mapGetters('auth', ['user']),
  },

  methods: {
    ...mapActions('investment', ['fetchAccounts', 'analyze']),
  },
};
```

---

### 2. API Service Layer

**Location**: `resources/js/services/{moduleName}Service.js`

All API calls go through service layer using centralized axios instance.

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
};
```

**Axios instance** (`resources/js/bootstrap.js`):
```javascript
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

// Sanctum authentication
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}
```

---

### 3. Router Integration

**Location**: `resources/js/router/index.js`

Vue Router handles navigation with authentication guards.

**Adding module routes**:
```javascript
import {ModuleName}Dashboard from '@/views/{ModuleName}/{ModuleName}Dashboard.vue';

const routes = [
  {
    path: '/{module-name}',
    name: '{ModuleName}Dashboard',
    component: {ModuleName}Dashboard,
    meta: { requiresAuth: true },
  },
];
```

**Auth guard**:
```javascript
router.beforeEach((to, from, next) => {
  const isAuthenticated = store.getters['auth/isAuthenticated'];

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login');
  } else {
    next();
  }
});
```

---

### 4. Dashboard Navigation

**Main Dashboard**: `resources/js/views/Dashboard/Dashboard.vue`

All modules show overview cards on main dashboard with navigation to detailed views.

**Pattern**:
```vue
<template>
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <ProtectionOverviewCard v-if="protectionData" :data="protectionData" />
    <SavingsOverviewCard v-if="savingsData" :data="savingsData" />
    <InvestmentOverviewCard v-if="investmentData" :data="investmentData" />
    <!-- More cards -->
  </div>
</template>

<script>
export default {
  name: 'Dashboard',

  computed: {
    ...mapGetters('protection', { protectionData: 'getData' }),
    ...mapGetters('savings', { savingsData: 'getData' }),
  },

  mounted() {
    this.$store.dispatch('protection/fetchData');
    this.$store.dispatch('savings/fetchData');
    // ... more dispatches
  },
};
</script>
```

**Overview Card Pattern**:
- Clickable card
- Shows 2-3 key metrics
- Navigates to module dashboard on click
- Consistent styling across modules

---

### 5. Form Modal Events

**Critical**: Never use `@submit` for custom events.

**Correct pattern**:
```vue
<!-- Form Modal Component -->
<template>
  <form @submit.prevent="submitForm">
    <!-- fields -->
  </form>
</template>

<script>
methods: {
  submitForm() {
    if (!this.validateForm()) return;
    this.$emit('save', this.formData); // Use 'save' NOT 'submit'
  },
}
</script>

<!-- Parent Component -->
<template>
  <FormModal @save="handleSubmit" @close="closeModal" />
</template>

<script>
methods: {
  async handleSubmit(formData) {
    try {
      await this.$store.dispatch('module/save', formData);
      this.closeModal();
    } catch (error) {
      this.error = error.message;
      // Modal stays open on error
    }
  },
}
</script>
```

---

### 6. Chart Integration (ApexCharts)

**Global registration**: `resources/js/app.js`

```javascript
import VueApexCharts from 'vue3-apexcharts';
app.component('apexchart', VueApexCharts);
```

**Usage in components**:
```vue
<template>
  <apexchart
    :type="chartType"
    :options="chartOptions"
    :series="series"
  ></apexchart>
</template>

<script>
export default {
  props: {
    data: Object,
  },

  computed: {
    chartType() {
      return 'line';
    },

    chartOptions() {
      return {
        chart: { type: 'line', height: 350 },
        xaxis: { categories: this.data.categories },
      };
    },

    series() {
      return this.data.series || [];
    },
  },
};
</script>
```

---

## Testing Integration Points

### 1. Pest Tests

**Run tests**:
```bash
./vendor/bin/pest                      # All tests
./vendor/bin/pest --testsuite=Unit     # Unit tests only
./vendor/bin/pest --testsuite=Feature  # Feature tests only
```

**Unit test dependencies**:
- Services should not depend on database
- Use mock data for calculations
- Test pure business logic

**Feature test dependencies**:
- Require database
- Use `RefreshDatabase` trait
- Create test users with factories

---

## Module Dependency Map

```
Dashboard (Main)
├── Protection Module
│   ├── Uses: UKTaxCalculator
│   ├── Reads: User income data
│   └── Provides: Coverage analysis
│
├── Savings Module
│   ├── Uses: uk_tax_config (ISA allowance)
│   ├── Reads: User expenditure
│   └── Provides: Emergency fund status, ISA tracking
│
├── Investment Module
│   ├── Uses: uk_tax_config (ISA allowance)
│   ├── Reads: Investment accounts
│   └── Provides: Portfolio analysis, Monte Carlo projections
│
├── Retirement Module
│   ├── Uses: uk_tax_config (pension allowance)
│   ├── Reads: DC/DB pensions, User DOB
│   └── Provides: Retirement readiness, income projections
│
└── Estate Module
    ├── Uses: uk_tax_config (IHT rates, NRB, RNRB)
    ├── Reads: Properties, Investments, Cash, Liabilities (all modules)
    └── Provides: Net worth, IHT liability, will planning
```

---

## Common Integration Scenarios

### Scenario 1: User updates income

**Cascade effects**:
1. User Profile → Save new income
2. Invalidate cache: `protection_analysis_{userId}`
3. Trigger: Protection module recalculates coverage needs
4. Dashboard: Update Protection overview card

### Scenario 2: User adds property

**Cascade effects**:
1. Net Worth → Create property
2. If joint ownership → Create reciprocal property for spouse
3. Invalidate cache: `net_worth_{userId}`, `estate_iht_{userId}`
4. Estate module: Recalculate IHT
5. Dashboard: Update Net Worth and Estate cards

### Scenario 3: User adds ISA subscription

**Cascade effects**:
1. Savings or Investment → Create ISA account
2. Validate: ISA must be `ownership_type = 'individual'`
3. Track subscription in `isa_subscription_current_year`
4. ISATracker service: Aggregate across modules
5. Dashboard: Update ISA allowance progress
6. Invalidate cache: `isa_allowance_{userId}_{taxYear}`

### Scenario 4: Tax year changes (April 6)

**Cascade effects**:
1. ISA subscriptions: Reset `isa_subscription_current_year = 0` for all accounts
2. Pension allowances: Reset carry-forward tracking
3. Update uk_tax_config for new tax year rates
4. Invalidate all cached calculations
5. Dashboard: Recalculate all module summaries
