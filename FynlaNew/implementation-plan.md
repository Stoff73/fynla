# Preview Dashboard Implementation Plan

## Overview

This document provides a detailed implementation plan that integrates with the existing Fynla codebase, avoids component duplication, correctly maps to the database schema, and addresses edge cases.

**Key Architectural Decisions:**
1. **Reuse existing components** - No duplication of dashboard cards or module views
2. **Inject preview data via Vuex** - Same data flow, different source
3. **Use real backend calculations** - Create preview endpoints that accept data payloads
4. **Match exact database schema** - Persona JSON mirrors API response format exactly

---

## Architecture Integration Strategy

### Current Data Flow (Authenticated Users)
```
Dashboard.vue
    → dispatches store actions (netWorth/fetchOverview, protection/fetchProtectionData, etc.)
        → Services call API endpoints
            → Controllers fetch from database
                → Services calculate results
                    → Response returned
                        → Vuex mutations update state
                            → Getters provide data to components
```

### Preview Data Flow (New)
```
PreviewDashboard.vue
    → loads persona JSON file
        → dispatches preview/setPreviewData action
            → Vuex mutations populate same state structure
                → Same getters provide data to SAME components
                    → For calculations: POST to /api/preview/* endpoints
                        → Same services calculate with passed data (no DB save)
```

### Key Integration Points

| Layer | Integration Approach |
|-------|---------------------|
| **Vue Components** | REUSE all existing cards and views - no changes needed |
| **Vuex Stores** | ADD preview mode flag + preview data to existing modules |
| **API Services** | ADD new previewService.js for calculation endpoints |
| **Backend Controllers** | ADD new PreviewController with stateless calculation methods |
| **Backend Services** | MODIFY to accept data arrays OR User objects |

---

## Phase 1: Vuex Store Integration

**Goal:** Modify existing Vuex stores to support preview mode without breaking current functionality.

### Tasks

- [ ] **1.1** Add preview state to `store/modules/netWorth.js`
  ```javascript
  // Add to state:
  isPreviewMode: false,
  previewData: null,

  // Add mutation:
  SET_PREVIEW_MODE(state, { isPreview, data }) {
    state.isPreviewMode = isPreview;
    state.previewData = data;
  }

  // Modify getters to check preview mode first:
  netWorth: (state) => {
    if (state.isPreviewMode && state.previewData) {
      return state.previewData.net_worth;
    }
    return state.overview?.netWorth || 0;
  }
  ```

- [ ] **1.2** Add preview state to `store/modules/protection.js`
  - Same pattern: `isPreviewMode`, `previewData`
  - Modify `lifePolicies`, `criticalIllnessPolicies`, `adequacyScore` getters
  - Ensure getter returns match existing structure exactly

- [ ] **1.3** Add preview state to `store/modules/estate.js`
  - Same pattern for `assets`, `liabilities`, `ihtLiability`, `taxableEstate`
  - Handle both single and married scenarios in getters

- [ ] **1.4** Add preview state to `store/modules/retirement.js`
  - DC pensions, DB pensions, state pension arrays
  - Projections and readiness score

- [ ] **1.5** Add preview state to `store/modules/savings.js`
  - Savings accounts array
  - Emergency fund calculations
  - ISA tracking data

- [ ] **1.6** Add preview state to `store/modules/investment.js`
  - Investment accounts array
  - Holdings array
  - Portfolio analysis data

- [ ] **1.7** Create central `store/modules/preview.js` module
  ```javascript
  const state = {
    isActive: false,
    currentPersonaId: null,
    personaData: null, // Full persona JSON
    editedValues: {}, // User modifications (keyed by path)
    warningShown: {}, // Track which warnings user has seen
  };

  const getters = {
    isPreviewMode: state => state.isActive,
    currentPersona: state => state.currentPersonaId,
    // Merge base data with edits
    effectivePersonaData: state => {
      if (!state.personaData) return null;
      return deepMerge(state.personaData, state.editedValues);
    },
  };

  const actions = {
    async loadPersona({ commit, dispatch }, personaId) {
      const data = await import(`@/data/personas/${personaId}.json`);
      commit('SET_PERSONA_DATA', { personaId, data: data.default });
      // Dispatch to each module store
      dispatch('netWorth/SET_PREVIEW_MODE', { isPreview: true, data: data.default }, { root: true });
      dispatch('protection/SET_PREVIEW_MODE', { isPreview: true, data: data.default.protection }, { root: true });
      // ... etc for all modules
    },

    updateValue({ commit, dispatch }, { path, value }) {
      commit('SET_EDITED_VALUE', { path, value });
      // Trigger recalculation for affected module
      dispatch('triggerRecalculation', path);
    },

    exitPreview({ commit, dispatch }) {
      commit('CLEAR_PREVIEW');
      // Clear preview mode in all module stores
      dispatch('netWorth/SET_PREVIEW_MODE', { isPreview: false, data: null }, { root: true });
      // ... etc
    },
  };
  ```

- [ ] **1.8** Register preview module in `store/index.js`

### Checkpoint 1: Store Integration Complete

**Verification:**
- [ ] Vue DevTools shows `preview` module with correct state
- [ ] Setting `isPreviewMode: true` in netWorth store changes getter output
- [ ] Existing authenticated flow still works (no regressions)
- [ ] Module stores correctly read from previewData when flag is true

**Testing:**
```javascript
// In browser console with Vue DevTools:
$store.commit('preview/SET_PERSONA_DATA', { personaId: 'test', data: mockData });
$store.commit('netWorth/SET_PREVIEW_MODE', { isPreview: true, data: mockData });
// Verify $store.getters['netWorth/netWorth'] returns preview value
```

---

## Phase 2: Persona Data Files

**Goal:** Create accurate, FCA-compliant persona JSON files matching exact database schema.

### Database Schema Reference

**Critical Field Mappings (from exploration):**

| Table | Correct Field | WRONG Field |
|-------|--------------|-------------|
| liabilities | `liability_name` | ~~description~~ |
| liabilities | `current_balance` | ~~amount~~ |
| mortgages | `mortgage_type: 'mixed'` | ~~part_and_part~~ |
| dc_pensions | `monthly_contribution_amount` | ~~employee_contribution_amount~~ |
| holdings | `holdable_type: 'App\\Models\\...'` | Must include namespace |

**Enum Values (must match exactly):**
- property_type: `main_residence`, `secondary_residence`, `buy_to_let`
- ownership_type: `individual`, `joint`, `tenants_in_common`, `trust`
- mortgage_type: `repayment`, `interest_only`, `mixed`
- investment account_type: `isa`, `gia`, `nsi`, `onshore_bond`, `offshore_bond`, `vct`, `eis`, `other`
- dc_pension scheme_type: `workplace`, `sipp`, `personal`
- db_pension scheme_type: `final_salary`, `career_average`, `public_sector`
- life_insurance policy_type: `term`, `whole_of_life`, `decreasing_term`, `level_term`, `family_income_benefit`

### Tasks

- [ ] **2.1** Create directory `resources/js/data/personas/`

- [ ] **2.2** Create `young_family.json` - Emily & James Carter

  **Profile:**
  - James: 34, Software Developer, £62k salary, employed
  - Emily: 32, Marketing Manager, £48k salary, employed
  - Married, 2 children (Oliver 6, Sophie 3)
  - Birmingham B15 2TT

  **Assets:**
  ```json
  {
    "properties": [{
      "property_type": "main_residence",
      "ownership_type": "joint",
      "ownership_percentage": 50,
      "current_value": 320000,
      "purchase_price": 285000,
      "address_line_1": "42 Oak Avenue",
      "city": "Birmingham",
      "postcode": "B15 2TT"
    }],
    "mortgages": [{
      "lender_name": "Nationwide",
      "mortgage_type": "repayment",
      "outstanding_balance": 245000,
      "interest_rate": 4.2500,
      "rate_type": "fixed",
      "monthly_payment": 1380
    }],
    "savings_accounts": [
      { "institution": "Marcus", "current_balance": 8500, "is_emergency_fund": true, "access_type": "immediate" },
      { "institution": "NS&I", "current_balance": 4200, "is_emergency_fund": false }
    ],
    "investment_accounts": [{
      "account_type": "isa",
      "provider": "Vanguard",
      "current_value": 15000
    }],
    "dc_pensions": [
      { "scheme_name": "James Workplace", "scheme_type": "workplace", "current_fund_value": 45000, "employee_contribution_percent": 5, "employer_contribution_percent": 3, "annual_salary": 62000 },
      { "scheme_name": "Emily Workplace", "scheme_type": "workplace", "current_fund_value": 22000, "employee_contribution_percent": 5, "employer_contribution_percent": 3, "annual_salary": 48000 }
    ]
  }
  ```

  **Protection (with GAPS for demo):**
  ```json
  {
    "life_insurance_policies": [{
      "policy_type": "level_term",
      "provider": "Aviva",
      "sum_assured": 350000,
      "premium_amount": 45,
      "premium_frequency": "monthly",
      "policy_term_years": 20
    }],
    "critical_illness_policies": [],
    "income_protection_policies": []
  }
  ```

  **Expenditure:** £4,200/month total

  **Key demo points:** Protection gaps, emergency fund adequacy, basic estate planning

- [ ] **2.3** Create `peak_earners.json` - David & Sarah Mitchell

  **Profile:**
  - David: 48, Finance Director, £145k salary
  - Sarah: 46, GP Partner, £120k salary
  - Married, 2 children (ages 14, 17 in private school)
  - Surrey

  **Assets:**
  - Main residence: £850k (mortgage £280k)
  - BTL property: £425k (mortgage £220k, rental income)
  - Cash ISAs: £45k, Premium Bonds: £50k
  - S&S ISAs: £180k, GIA: £95k, VCT: £30k
  - David: DB pension + SIPP £320k
  - Sarah: NHS DB pension

  **Key demo points:** Tapered annual allowance, BTL taxation, complex portfolio, retirement projections

- [ ] **2.4** Create `widow.json` - Margaret Thompson

  **Profile:**
  - Margaret: 68, Retired headteacher, widowed
  - 3 adult children, 5 grandchildren
  - Cotswolds

  **Assets:**
  - Main residence: £625k (mortgage-free)
  - Holiday cottage: £285k
  - Cash ISAs: £85k, NS&I: £50k
  - S&S ISA: £220k, Offshore bond: £150k, GIA: £180k
  - State pension: £11,502/year
  - Teacher's DB pension: £28k/year
  - Whole of life policy: £100k IN TRUST

  **Estate:**
  - Transferred NRB from deceased spouse (£325k)
  - Transferred RNRB from deceased spouse (£175k)
  - Gifts in last 7 years: £45k
  - Complex will with trust provisions

  **Key demo points:** IHT calculation with spouse transfer, gifting strategy, 7-year rule

- [ ] **2.5** Create `entrepreneur.json` - Alex Chen

  **Profile:**
  - Alex: 38, Tech consultancy owner, single
  - No dependants, elderly parents
  - Manchester city centre

  **Assets:**
  - Apartment: £380k (mortgage £190k)
  - Business interest: £450k (60% ownership) - Coming Soon placeholder
  - Savings: £65k
  - SIPP: £185k, S&S ISA: £95k, GIA: £45k
  - Life insurance: £200k
  - Key person insurance: £500k (through business)
  - Director's loan to company: £35k

  **Key demo points:** Business interests, single-person estate, key person insurance

- [ ] **2.6** Validate all JSON against database schema
  - Run validation script to check all enum values
  - Verify all required fields present
  - Check decimal precision matches (15,2 for currency, 5,4 for rates)
  - Ensure dates in YYYY-MM-DD format

- [ ] **2.7** Have financial adviser review persona data
  - Verify figures are realistic
  - Check tax calculations would be accurate
  - Ensure FCA compliance

### Checkpoint 2: Persona Data Valid

**Verification:**
- [ ] Each JSON file passes schema validation
- [ ] All enum values match CLAUDE.md canonical types
- [ ] Financial adviser has approved data accuracy
- [ ] Test import of each JSON file succeeds

**Testing:**
```javascript
// Validation script
import youngFamily from '@/data/personas/young_family.json';
import { validatePersonaSchema } from '@/utils/personaValidator';
const errors = validatePersonaSchema(youngFamily);
console.assert(errors.length === 0, 'Schema errors:', errors);
```

---

## Phase 3: Preview Routes & Components

**Goal:** Create preview routing that reuses existing components.

### Routing Strategy

**Option A (Recommended): Route parameter approach**
```javascript
// Single route handles both modes
{
  path: '/dashboard',
  component: Dashboard,
  meta: { requiresAuth: true }
},
{
  path: '/preview',
  component: Dashboard, // SAME component
  meta: { public: true, previewMode: true }
}
```

**Why this works:** Dashboard.vue checks `$route.meta.previewMode` or `$store.getters['preview/isPreviewMode']` and loads data accordingly.

### Tasks

- [ ] **3.1** Add preview routes to `router/index.js`
  ```javascript
  // Public preview routes - REUSE existing view components
  {
    path: '/preview',
    name: 'PreviewDashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { public: true, previewMode: true },
    beforeEnter: (to, from, next) => {
      store.dispatch('preview/loadPersona', 'young_family');
      next();
    }
  },
  {
    path: '/preview/net-worth',
    name: 'PreviewNetWorth',
    component: () => import('@/views/NetWorth/NetWorthDashboard.vue'),
    meta: { public: true, previewMode: true }
  },
  {
    path: '/preview/protection',
    name: 'PreviewProtection',
    component: () => import('@/views/Protection/ProtectionDashboard.vue'),
    meta: { public: true, previewMode: true }
  },
  // ... same pattern for all modules
  ```

- [ ] **3.2** Add route guard for preview mode
  ```javascript
  router.beforeEach((to, from, next) => {
    const isPreviewRoute = to.meta.previewMode;
    const isAuthenticated = store.getters['auth/isAuthenticated'];

    if (isPreviewRoute && isAuthenticated) {
      // Authenticated user trying to access preview
      next({ name: 'Dashboard', query: { message: 'already-registered' } });
      return;
    }

    if (isPreviewRoute) {
      // Ensure preview mode is active
      store.commit('preview/SET_ACTIVE', true);
    } else {
      // Clear preview mode on authenticated routes
      if (store.getters['preview/isPreviewMode']) {
        store.dispatch('preview/exitPreview');
      }
    }

    next();
  });
  ```

- [ ] **3.3** Modify `Dashboard.vue` to support preview mode
  ```javascript
  // Add to computed:
  isPreviewMode() {
    return this.$route.meta.previewMode || this.$store.getters['preview/isPreviewMode'];
  },

  // Modify loadAllData():
  async loadAllData() {
    if (this.isPreviewMode) {
      // Data already loaded via route beforeEnter
      // Just trigger calculations
      await this.loadPreviewCalculations();
      return;
    }
    // Existing authenticated data loading...
  },

  async loadPreviewCalculations() {
    const personaData = this.$store.getters['preview/effectivePersonaData'];
    // Call preview calculation endpoints
    const [iht, protection, netWorth] = await Promise.all([
      previewService.calculateIHT(personaData),
      previewService.calculateProtectionGaps(personaData),
      previewService.calculateNetWorth(personaData),
    ]);
    // Results are stored in preview state
  }
  ```

- [ ] **3.4** Create `PreviewBanner.vue` component
  ```vue
  <template>
    <div v-if="isPreviewMode" class="bg-amber-50 border-b border-amber-200 px-4 py-3">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-3">
          <InformationCircleIcon class="h-5 w-5 text-amber-600" />
          <span class="text-amber-800">
            <strong>Preview Mode</strong> — Viewing example data for {{ personaName }}
          </span>
        </div>
        <div class="flex items-center gap-4">
          <PersonaSelector />
          <router-link to="/register" class="btn-primary btn-sm">
            Register Now
          </router-link>
        </div>
      </div>
    </div>
  </template>
  ```

- [ ] **3.5** Create `PersonaSelector.vue` component
  ```vue
  <template>
    <div class="relative">
      <button @click="isOpen = !isOpen" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-amber-100">
        <UserCircleIcon class="h-6 w-6" />
        <span>{{ currentPersona.name }}</span>
        <ChevronDownIcon class="h-4 w-4" />
      </button>

      <div v-if="isOpen" class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border z-50">
        <div class="p-3 border-b">
          <h3 class="font-semibold text-gray-900">Select a Financial Scenario</h3>
        </div>
        <div class="p-2">
          <button
            v-for="persona in personas"
            :key="persona.id"
            @click="selectPersona(persona.id)"
            class="w-full p-3 rounded-lg text-left hover:bg-gray-50"
            :class="{ 'bg-primary-50 border-primary-500': persona.id === currentPersonaId }"
          >
            <div class="font-medium">{{ persona.name }}</div>
            <div class="text-sm text-gray-500">{{ persona.tagline }}</div>
            <div class="text-xs text-gray-400 mt-1">
              Net worth: {{ persona.netWorthRange }} | {{ persona.focus }}
            </div>
          </button>
        </div>
      </div>
    </div>
  </template>
  ```

- [ ] **3.6** Create `PersonaIntroModal.vue`
  - Shows when switching personas
  - Displays persona bio, key stats, concerns
  - "Explore Dashboard" CTA

- [ ] **3.7** Integrate PreviewBanner into `AppLayout.vue`
  ```vue
  <template>
    <div class="min-h-screen bg-gray-50">
      <Navbar v-if="!isPreviewMode" />
      <PreviewNavbar v-else />
      <PreviewBanner v-if="isPreviewMode" />
      <main>
        <slot />
      </main>
      <Footer />
    </div>
  </template>
  ```

- [ ] **3.8** Update `LandingPage.vue` with preview CTA
  ```vue
  <!-- In hero section, add alongside existing buttons -->
  <router-link to="/preview" class="btn-secondary">
    Try the Demo
  </router-link>
  ```

### Checkpoint 3: Preview Navigation Working

**Verification:**
- [ ] Click "Try the Demo" on landing page → `/preview` loads
- [ ] Preview banner visible with persona selector
- [ ] Clicking persona loads new data
- [ ] Navigation to `/preview/net-worth` etc. works
- [ ] Authenticated user visiting `/preview` redirects to dashboard
- [ ] Existing authenticated routes unchanged

**Testing:**
```
Manual tests:
1. Landing page → "Try the Demo" → Preview dashboard loads
2. Preview banner shows "Emily & James Carter"
3. Click persona selector → Select "Margaret" → Data updates
4. Navigate: Preview Dashboard → Net Worth → Protection → Back
5. Log in → Visit /preview → Redirected to /dashboard
```

---

## Phase 4: Backend Preview Calculations

**Goal:** Create stateless calculation endpoints that accept data payloads.

### Service Modification Strategy

The exploration revealed most services fetch from database. Two approaches:

**Approach A (Recommended): Create preview-specific methods**
```php
// In IHTCalculationService
public function calculateFromData(array $assets, array $liabilities, array $profile): array
{
    // Same calculation logic, but uses passed arrays instead of DB queries
}
```

**Approach B: Create temporary in-memory data**
- More complex, higher risk of side effects

### Tasks

- [ ] **4.1** Create `PreviewController.php`
  ```php
  <?php

  namespace App\Http\Controllers\Api;

  use App\Services\Estate\IHTCalculationService;
  use App\Services\Protection\CoverageGapAnalyzer;
  use App\Services\Savings\EmergencyFundCalculator;
  use Illuminate\Http\Request;

  class PreviewController extends Controller
  {
      public function __construct(
          private IHTCalculationService $ihtService,
          private CoverageGapAnalyzer $gapAnalyzer,
          private EmergencyFundCalculator $emergencyFundCalculator,
      ) {}

      /**
       * Calculate IHT from preview data
       * POST /api/preview/calculate-iht
       */
      public function calculateIHT(Request $request)
      {
          $validated = $request->validate([
              'assets' => 'required|array',
              'liabilities' => 'required|array',
              'profile' => 'required|array',
              'spouse_profile' => 'nullable|array',
          ]);

          $result = $this->ihtService->calculateFromData(
              $validated['assets'],
              $validated['liabilities'],
              $validated['profile'],
              $validated['spouse_profile'] ?? null
          );

          return response()->json($result);
      }

      // ... other calculation endpoints
  }
  ```

- [ ] **4.2** Add `calculateFromData()` to `IHTCalculationService`
  ```php
  public function calculateFromData(
      array $assets,
      array $liabilities,
      array $profile,
      ?array $spouseProfile = null
  ): array {
      // Calculate totals from arrays
      $totalGrossAssets = collect($assets)
          ->where('is_iht_exempt', false)
          ->sum('current_value');

      $totalLiabilities = collect($liabilities)->sum('current_balance');

      $totalNetEstate = $totalGrossAssets - $totalLiabilities;

      // Get allowances from TaxConfigService
      $ihtConfig = $this->taxConfig->getInheritanceTax();
      $nrb = $ihtConfig['nil_rate_band'];
      $rnrb = $this->calculateRNRB($profile, $assets, $ihtConfig);

      // Handle married transferable allowances
      if ($spouseProfile && $profile['marital_status'] === 'married') {
          $nrb += $nrb; // Transferable NRB
          $rnrb += $rnrb; // Transferable RNRB (if applicable)
      }

      $taxableEstate = max(0, $totalNetEstate - $nrb - $rnrb);
      $ihtLiability = $taxableEstate * ($ihtConfig['rate'] / 100);

      return [
          'total_gross_assets' => $totalGrossAssets,
          'total_liabilities' => $totalLiabilities,
          'total_net_estate' => $totalNetEstate,
          'nrb_available' => $nrb,
          'rnrb_available' => $rnrb,
          'total_allowances' => $nrb + $rnrb,
          'taxable_estate' => $taxableEstate,
          'iht_liability' => $ihtLiability,
          'effective_rate' => $totalNetEstate > 0
              ? ($ihtLiability / $totalNetEstate) * 100
              : 0,
      ];
  }
  ```

- [ ] **4.3** Add preview method to `CoverageGapAnalyzer`
  ```php
  public function analyzeFromData(array $profile, array $policies): array
  {
      // Calculate needs
      $needs = $this->calculateNeedsFromProfile($profile);

      // Calculate coverage from policy arrays
      $coverage = $this->calculateCoverageFromPolicies($policies);

      // Calculate gaps
      $gaps = $this->calculateGaps($needs, $coverage);

      // Calculate adequacy score
      $adequacyScore = $this->calculateAdequacyScore($needs, $coverage);

      return [
          'needs' => $needs,
          'coverage' => $coverage,
          'gaps' => $gaps,
          'adequacy_score' => $adequacyScore,
      ];
  }
  ```

- [ ] **4.4** Create `calculateNetWorthFromData()` method
  ```php
  // In NetWorthService or new PreviewNetWorthService
  public function calculateFromData(array $data): array
  {
      $assets = [
          'property' => collect($data['properties'] ?? [])->sum('current_value'),
          'investments' => collect($data['investment_accounts'] ?? [])->sum('current_value'),
          'cash' => collect($data['savings_accounts'] ?? [])->sum('current_balance'),
          'pensions' => $this->calculatePensionValue($data),
          'business' => collect($data['business_interests'] ?? [])
              ->sum(fn($b) => $b['current_value'] * ($b['ownership_percentage'] / 100)),
          'chattels' => collect($data['chattels'] ?? [])
              ->sum(fn($c) => $c['current_value'] * ($c['ownership_percentage'] / 100)),
      ];

      $liabilities = [
          'mortgages' => collect($data['mortgages'] ?? [])->sum('outstanding_balance'),
          'loans' => collect($data['liabilities'] ?? [])
              ->whereIn('liability_type', ['personal_loan', 'secured_loan', 'hire_purchase', 'student_loan', 'business_loan'])
              ->sum('current_balance'),
          'credit_cards' => collect($data['liabilities'] ?? [])
              ->where('liability_type', 'credit_card')
              ->sum('current_balance'),
          'other' => collect($data['liabilities'] ?? [])
              ->whereIn('liability_type', ['overdraft', 'other'])
              ->sum('current_balance'),
      ];

      return [
          'total_assets' => array_sum($assets),
          'total_liabilities' => array_sum($liabilities),
          'net_worth' => array_sum($assets) - array_sum($liabilities),
          'breakdown' => $assets,
          'liabilities_breakdown' => $liabilities,
      ];
  }

  private function calculatePensionValue(array $data): float
  {
      $dcValue = collect($data['dc_pensions'] ?? [])->sum('current_fund_value');

      // DB pensions: 20x annual income + lump sum
      $dbValue = collect($data['db_pensions'] ?? [])->sum(function ($pension) {
          return ($pension['accrued_annual_pension'] * 20) + ($pension['lump_sum_entitlement'] ?? 0);
      });

      return $dcValue + $dbValue;
  }
  ```

- [ ] **4.5** Emergency fund calculation (already works with raw data)
  ```php
  // EmergencyFundCalculator already accepts floats directly
  public function calculateEmergencyFund(Request $request)
  {
      $validated = $request->validate([
          'total_savings' => 'required|numeric|min:0',
          'monthly_expenditure' => 'required|numeric|min:0',
          'target_months' => 'nullable|integer|min:1|max:24',
      ]);

      $runway = $this->emergencyFundCalculator->calculateRunway(
          $validated['total_savings'],
          $validated['monthly_expenditure']
      );

      $adequacy = $this->emergencyFundCalculator->calculateAdequacy(
          $runway,
          $validated['target_months'] ?? 6
      );

      return response()->json([
          'runway_months' => $runway,
          'target_months' => $validated['target_months'] ?? 6,
          'adequacy_score' => $adequacy['adequacy_score'],
          'shortfall_months' => $adequacy['shortfall'],
          'category' => $this->emergencyFundCalculator->categorizeAdequacy($runway),
      ]);
  }
  ```

- [ ] **4.6** Register preview routes in `routes/api.php`
  ```php
  // Preview calculation routes - NO AUTH REQUIRED
  Route::prefix('preview')->group(function () {
      Route::post('/calculate-iht', [PreviewController::class, 'calculateIHT']);
      Route::post('/calculate-protection-gaps', [PreviewController::class, 'calculateProtectionGaps']);
      Route::post('/calculate-net-worth', [PreviewController::class, 'calculateNetWorth']);
      Route::post('/calculate-retirement-projection', [PreviewController::class, 'calculateRetirementProjection']);
      Route::post('/calculate-emergency-fund', [PreviewController::class, 'calculateEmergencyFund']);
  })->middleware(['throttle:60,1']); // Rate limit to prevent abuse
  ```

- [ ] **4.7** Create `previewService.js` frontend service
  ```javascript
  import api from './api';

  export default {
    async calculateIHT(personaData) {
      const payload = {
        assets: this.extractAssets(personaData),
        liabilities: this.extractLiabilities(personaData),
        profile: personaData.user,
        spouse_profile: personaData.spouse,
      };
      const response = await api.post('/preview/calculate-iht', payload);
      return response.data;
    },

    async calculateProtectionGaps(personaData) {
      const payload = {
        profile: {
          annual_income: personaData.user.annual_employment_income,
          monthly_expenditure: this.calculateTotalExpenditure(personaData.expenditure),
          mortgage_balance: personaData.mortgages?.[0]?.outstanding_balance || 0,
          number_of_dependents: personaData.family_members?.filter(m => m.is_dependant).length || 0,
          current_age: this.calculateAge(personaData.user.date_of_birth),
          retirement_age: personaData.user.target_retirement_age || 65,
        },
        policies: {
          life: personaData.life_insurance_policies || [],
          critical_illness: personaData.critical_illness_policies || [],
          income_protection: personaData.income_protection_policies || [],
          disability: personaData.disability_policies || [],
          sickness_illness: personaData.sickness_illness_policies || [],
        },
      };
      const response = await api.post('/preview/calculate-protection-gaps', payload);
      return response.data;
    },

    // ... other methods

    extractAssets(personaData) {
      return [
        ...personaData.properties?.map(p => ({
          type: 'property',
          current_value: p.current_value * (p.ownership_percentage / 100),
          is_iht_exempt: false,
        })) || [],
        ...personaData.investment_accounts?.map(a => ({
          type: 'investment',
          current_value: a.current_value,
          is_iht_exempt: false,
        })) || [],
        ...personaData.savings_accounts?.map(s => ({
          type: 'savings',
          current_value: s.current_balance,
          is_iht_exempt: false,
        })) || [],
        ...personaData.dc_pensions?.map(p => ({
          type: 'pension',
          current_value: p.current_fund_value,
          is_iht_exempt: true, // Pensions exempt if beneficiary nominated
        })) || [],
      ];
    },
  };
  ```

### Checkpoint 4: Calculations Working

**Verification:**
- [ ] POST `/api/preview/calculate-iht` with Margaret's data returns ~£238k liability
- [ ] POST `/api/preview/calculate-protection-gaps` with Young Family returns gaps
- [ ] POST `/api/preview/calculate-net-worth` returns correct totals
- [ ] All endpoints return within 2 seconds
- [ ] Rate limiting works (61st request in 1 minute blocked)

**Testing:**
```bash
# Test IHT calculation
curl -X POST http://localhost:8000/api/preview/calculate-iht \
  -H "Content-Type: application/json" \
  -d '{"assets":[{"current_value":1595000,"is_iht_exempt":false}],"liabilities":[],"profile":{"marital_status":"widowed","date_of_birth":"1957-03-15"}}'

# Expected: iht_liability ~238000
```

---

## Phase 5: Interactive Editing

**Goal:** Allow users to edit values with proper warnings for personal info.

### Field Classification

**Freely Editable (no warning):**
- All monetary values (balances, values, amounts)
- All percentages (allocation, contribution rates, ownership)
- Dates (policy dates, retirement age)
- Quantities (NI years, term years)

**Personal Info (warning required):**
- name, date_of_birth, gender
- address_line_1, address_line_2, city, county, postcode
- email, phone, national_insurance_number
- employer, occupation, industry
- family member names
- beneficiary names

### Tasks

- [ ] **5.1** Create field classification utility
  ```javascript
  // utils/previewFieldConfig.js
  export const PERSONAL_INFO_FIELDS = [
    'user.name',
    'user.date_of_birth',
    'user.gender',
    'user.address_line_1',
    'user.address_line_2',
    'user.city',
    'user.county',
    'user.postcode',
    'user.email',
    'user.phone',
    'user.national_insurance_number',
    'user.employer',
    'user.occupation',
    'user.industry',
    'spouse.name',
    'spouse.date_of_birth',
    'family_members.*.name',
    'family_members.*.first_name',
    'family_members.*.last_name',
  ];

  export function isPersonalInfoField(fieldPath) {
    return PERSONAL_INFO_FIELDS.some(pattern => {
      const regex = new RegExp('^' + pattern.replace('*', '\\d+') + '$');
      return regex.test(fieldPath);
    });
  }
  ```

- [ ] **5.2** Create `PersonalInfoWarningModal.vue`
  ```vue
  <template>
    <TransitionRoot :show="isOpen" as="template">
      <Dialog @close="$emit('close')" class="relative z-50">
        <div class="fixed inset-0 bg-black/30" />
        <div class="fixed inset-0 flex items-center justify-center p-4">
          <DialogPanel class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <div class="flex items-center gap-3 mb-4">
              <ExclamationTriangleIcon class="h-8 w-8 text-amber-500" />
              <DialogTitle class="text-lg font-semibold">
                Personal Information Will Not Be Saved
              </DialogTitle>
            </div>

            <p class="text-gray-600 mb-6">
              Personal details like names, dates of birth, and addresses are not
              saved in preview mode. Register now to create your own personalised
              financial plan.
            </p>

            <div class="space-y-3">
              <button @click="$emit('register')" class="btn-primary w-full">
                Register Now
              </button>
              <button @click="$emit('continue')" class="btn-secondary w-full">
                Continue Without Saving
              </button>
              <p class="text-xs text-amber-600 text-center flex items-center justify-center gap-1">
                <ExclamationTriangleIcon class="h-4 w-4" />
                Warning: Any data you enter will be lost
              </p>
            </div>
          </DialogPanel>
        </div>
      </Dialog>
    </TransitionRoot>
  </template>
  ```

- [ ] **5.3** Create `EditablePreviewField.vue` wrapper
  ```vue
  <template>
    <div
      class="relative group cursor-pointer"
      :class="fieldClasses"
      @click="handleClick"
    >
      <!-- Display value -->
      <span v-if="!isEditing">{{ formattedValue }}</span>

      <!-- Inline editor -->
      <input
        v-else
        ref="input"
        v-model="editValue"
        :type="inputType"
        class="border rounded px-2 py-1 w-full"
        @blur="saveEdit"
        @keyup.enter="saveEdit"
        @keyup.escape="cancelEdit"
      />

      <!-- Edit affordance -->
      <PencilIcon
        v-if="!isEditing"
        class="absolute -right-5 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 opacity-0 group-hover:opacity-100"
      />

      <!-- Personal info indicator -->
      <InformationCircleIcon
        v-if="isPersonalInfo"
        class="absolute -left-5 top-1/2 -translate-y-1/2 h-4 w-4 text-amber-500"
        title="Changes not saved"
      />
    </div>

    <!-- Warning modal -->
    <PersonalInfoWarningModal
      :is-open="showWarning"
      @close="showWarning = false"
      @register="$router.push('/register')"
      @continue="proceedWithEdit"
    />
  </template>

  <script>
  import { isPersonalInfoField } from '@/utils/previewFieldConfig';

  export default {
    props: {
      value: { required: true },
      fieldPath: { type: String, required: true },
      type: { type: String, default: 'number' }, // number, percentage, currency, date, text
    },

    data() {
      return {
        isEditing: false,
        editValue: null,
        showWarning: false,
        warningAcknowledged: false,
      };
    },

    computed: {
      isPersonalInfo() {
        return isPersonalInfoField(this.fieldPath);
      },

      fieldClasses() {
        return {
          'hover:ring-1 hover:ring-primary-200 rounded px-1': !this.isPersonalInfo,
          'hover:ring-1 hover:ring-amber-200 rounded px-1': this.isPersonalInfo,
        };
      },

      formattedValue() {
        if (this.type === 'currency') {
          return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(this.value);
        }
        if (this.type === 'percentage') {
          return `${this.value}%`;
        }
        return this.value;
      },
    },

    methods: {
      handleClick() {
        if (this.isPersonalInfo && !this.warningAcknowledged) {
          this.showWarning = true;
        } else {
          this.startEdit();
        }
      },

      proceedWithEdit() {
        this.warningAcknowledged = true;
        this.showWarning = false;
        this.startEdit();
      },

      startEdit() {
        this.editValue = this.value;
        this.isEditing = true;
        this.$nextTick(() => this.$refs.input?.focus());
      },

      saveEdit() {
        if (this.editValue !== this.value) {
          this.$store.dispatch('preview/updateValue', {
            path: this.fieldPath,
            value: this.editValue,
          });
        }
        this.isEditing = false;
      },

      cancelEdit() {
        this.isEditing = false;
      },
    },
  };
  </script>
  ```

- [ ] **5.4** Integrate editable fields into existing components

  **Strategy:** Create preview-aware variants or use slots

  ```vue
  <!-- In NetWorthOverviewCard.vue or wherever values display -->
  <template>
    <span v-if="!isPreviewMode">{{ formatCurrency(propertyValue) }}</span>
    <EditablePreviewField
      v-else
      :value="propertyValue"
      field-path="properties.0.current_value"
      type="currency"
    />
  </template>
  ```

- [ ] **5.5** Implement value update and recalculation flow
  ```javascript
  // In preview store
  async updateValue({ commit, dispatch, state }, { path, value }) {
    commit('SET_EDITED_VALUE', { path, value });

    // Determine which calculations need refresh
    const affectedModules = getAffectedModules(path);

    // Trigger recalculations
    for (const module of affectedModules) {
      await dispatch('triggerRecalculation', module);
    }
  },

  async triggerRecalculation({ state }, module) {
    const personaData = getters.effectivePersonaData(state);

    switch (module) {
      case 'netWorth':
        const nwResult = await previewService.calculateNetWorth(personaData);
        commit('netWorth/SET_PREVIEW_CALCULATION', nwResult, { root: true });
        break;
      case 'iht':
        const ihtResult = await previewService.calculateIHT(personaData);
        commit('estate/SET_PREVIEW_CALCULATION', ihtResult, { root: true });
        break;
      // ... etc
    }
  }
  ```

- [ ] **5.6** Handle persona switch with unsaved edits
  ```javascript
  // In PersonaSelector.vue
  async selectPersona(personaId) {
    if (this.hasUnsavedEdits) {
      const confirmed = await this.$confirm(
        'Switching personas will discard your changes. Continue?',
        { title: 'Unsaved Changes' }
      );
      if (!confirmed) return;
    }

    await this.$store.dispatch('preview/loadPersona', personaId);
    this.isOpen = false;
  }
  ```

### Checkpoint 5: Editing Works

**Verification:**
- [ ] Click property value → Inline editor appears
- [ ] Change value → Net worth recalculates
- [ ] Click name field → Warning modal appears
- [ ] "Continue Without Saving" → Can edit name
- [ ] "Register Now" → Navigates to registration
- [ ] Switch persona with edits → Warning shown
- [ ] Edits persist during navigation within preview
- [ ] Browser refresh → All edits lost (session only)

---

## Phase 6: Registration Flow & Guidance

**Goal:** Modified registration flow and post-registration guidance.

### Database Migrations

- [ ] **6.1** Create migration for guidance columns on users table
  ```php
  Schema::table('users', function (Blueprint $table) {
      // Guidance tracking
      $table->boolean('guidance_active')->default(false);
      $table->boolean('guidance_completed')->default(false);
      $table->integer('guidance_current_step')->default(0);
      $table->json('guidance_completed_steps')->nullable();
      $table->json('guidance_skipped_steps')->nullable();
      $table->string('guidance_version')->nullable();

      // Registration source
      $table->string('registration_source')->nullable(); // 'preview', 'direct', 'referral'
      $table->string('preview_persona_kept')->nullable();
  });
  ```

- [ ] **6.2** Create migration for `is_demo_origin` flag
  ```php
  $tables = [
      'properties', 'mortgages', 'savings_accounts', 'investment_accounts',
      'holdings', 'dc_pensions', 'db_pensions', 'state_pensions',
      'life_insurance_policies', 'critical_illness_policies',
      'income_protection_policies', 'disability_policies',
      'sickness_illness_policies', 'liabilities', 'trusts', 'gifts',
      'family_members', 'wills', 'bequests',
  ];

  foreach ($tables as $tableName) {
      Schema::table($tableName, function (Blueprint $table) {
          $table->boolean('is_demo_origin')->default(false)->after('user_id');
      });
  }
  ```

- [ ] **6.3** Run migrations and update models
  - Add fillable fields to User model
  - Add `is_demo_origin` to all affected model fillables

### Registration Flow

- [ ] **6.4** Create `KeepDataOrFreshModal.vue`
  ```vue
  <template>
    <Dialog :open="isOpen" @close="$emit('close')">
      <DialogPanel class="max-w-lg mx-auto bg-white rounded-xl p-6">
        <DialogTitle class="text-xl font-semibold mb-4">
          Welcome to Fynla!
        </DialogTitle>

        <p class="text-gray-600 mb-6">
          You were exploring <strong>{{ personaName }}</strong>'s example data.
          Would you like to keep it as a starting point?
        </p>

        <div class="space-y-4">
          <!-- Keep Data Option -->
          <button
            @click="selected = 'keep'"
            class="w-full p-4 border-2 rounded-xl text-left transition"
            :class="selected === 'keep' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'"
          >
            <div class="flex items-start gap-3">
              <HomeIcon class="h-6 w-6 text-primary-600 mt-1" />
              <div>
                <div class="font-medium">Keep {{ personaName }}'s data</div>
                <div class="text-sm text-gray-500">
                  Start with example data and modify to match your situation.
                </div>
                <div class="text-xs text-gray-400 mt-2">
                  {{ dataSummary }}
                </div>
              </div>
            </div>
          </button>

          <!-- Start Fresh Option -->
          <button
            @click="selected = 'fresh'"
            class="w-full p-4 border-2 rounded-xl text-left transition"
            :class="selected === 'fresh' ? 'border-primary-500 bg-primary-50' : 'border-gray-200'"
          >
            <div class="flex items-start gap-3">
              <SparklesIcon class="h-6 w-6 text-primary-600 mt-1" />
              <div>
                <div class="font-medium">Start fresh</div>
                <div class="text-sm text-gray-500">
                  Begin with a clean slate and enter your own data.
                </div>
              </div>
            </div>
          </button>

          <!-- Spouse option for married personas -->
          <div v-if="personaIsMarried && selected === 'keep'" class="pl-4 mt-2">
            <label class="flex items-center gap-2">
              <input type="checkbox" v-model="createSpouseAccount" class="rounded" />
              <span class="text-sm">Also create account for {{ spouseName }}</span>
            </label>
          </div>
        </div>

        <button
          @click="handleContinue"
          :disabled="!selected"
          class="btn-primary w-full mt-6"
        >
          Continue
        </button>
      </DialogPanel>
    </Dialog>
  </template>
  ```

- [ ] **6.5** Create backend endpoint for seeding persona data
  ```php
  // POST /api/user/seed-persona-data
  public function seedPersonaData(Request $request)
  {
      $validated = $request->validate([
          'persona_id' => 'required|string|in:young_family,peak_earners,widow,entrepreneur',
          'create_spouse_account' => 'boolean',
      ]);

      $user = $request->user();
      $personaData = $this->loadPersonaJson($validated['persona_id']);

      DB::transaction(function () use ($user, $personaData, $validated) {
          // Seed all data with is_demo_origin = true
          $this->seedProperties($user, $personaData['properties']);
          $this->seedMortgages($user, $personaData['mortgages']);
          $this->seedSavingsAccounts($user, $personaData['savings_accounts']);
          $this->seedInvestmentAccounts($user, $personaData['investment_accounts']);
          $this->seedPensions($user, $personaData);
          $this->seedProtectionPolicies($user, $personaData);
          $this->seedLiabilities($user, $personaData['liabilities']);
          $this->seedFamilyMembers($user, $personaData['family_members']);
          $this->seedEstateData($user, $personaData);

          // Update user profile fields
          $user->update([
              'preview_persona_kept' => $validated['persona_id'],
              'guidance_active' => true,
              'guidance_current_step' => 0,
          ]);

          // Handle spouse account creation if requested
          if ($validated['create_spouse_account'] && isset($personaData['spouse'])) {
              $this->createSpouseAccount($user, $personaData['spouse']);
          }
      });

      return response()->json(['success' => true]);
  }

  private function seedProperties(User $user, array $properties)
  {
      foreach ($properties as $property) {
          $property['user_id'] = $user->id;
          $property['is_demo_origin'] = true;
          Property::create($property);
      }
  }
  // ... similar methods for other entities
  ```

- [ ] **6.6** Modify `Register.vue` for preview flow
  ```javascript
  async handleRegistration() {
    try {
      await this.$store.dispatch('auth/register', this.form);

      // Check if coming from preview
      const wasInPreview = this.$store.getters['preview/isPreviewMode'];
      const personaId = this.$store.getters['preview/currentPersona'];

      if (wasInPreview && personaId) {
        // Show keep/fresh modal
        this.showKeepDataModal = true;
        this.previewPersonaId = personaId;
      } else {
        // Direct registration - go to dashboard with guidance
        this.$router.push('/dashboard');
      }
    } catch (error) {
      this.handleError(error);
    }
  },

  async handleKeepDataChoice(choice) {
    if (choice === 'keep') {
      await this.$store.dispatch('user/seedPersonaData', {
        personaId: this.previewPersonaId,
        createSpouseAccount: this.createSpouseAccount,
      });
    }

    // Clear preview mode
    this.$store.dispatch('preview/exitPreview');

    // Navigate to dashboard (NOT onboarding)
    this.$router.push('/dashboard');
  }
  ```

### Guidance System

- [ ] **6.7** Create `store/modules/guidance.js`
  ```javascript
  const GUIDANCE_STEPS = [
    { id: 'personal_info', label: 'Personal Information', target: '#profile-section', route: '/profile' },
    { id: 'family', label: 'Family Members', target: '#family-section', route: '/profile#family' },
    { id: 'properties', label: 'Properties', target: '#property-card', route: '/net-worth/property' },
    { id: 'savings', label: 'Savings', target: '#savings-card', route: '/savings' },
    { id: 'investments', label: 'Investments', target: '#investment-card', route: '/investments' },
    { id: 'pensions', label: 'Pensions', target: '#retirement-card', route: '/retirement' },
    { id: 'protection', label: 'Protection', target: '#protection-card', route: '/protection' },
    { id: 'income', label: 'Income & Expenditure', target: '#income-section', route: '/profile#income' },
  ];

  const state = {
    isActive: false,
    currentStepIndex: 0,
    completedSteps: [],
    skippedSteps: [],
    version: '1.0.0',
  };

  const getters = {
    currentStep: state => GUIDANCE_STEPS[state.currentStepIndex],
    progress: state => ({
      current: state.currentStepIndex + 1,
      total: GUIDANCE_STEPS.length,
      percentage: ((state.completedSteps.length + state.skippedSteps.length) / GUIDANCE_STEPS.length) * 100,
    }),
    isComplete: state => state.completedSteps.length + state.skippedSteps.length >= GUIDANCE_STEPS.length,
  };

  const actions = {
    async startGuidance({ commit }) {
      commit('SET_ACTIVE', true);
      commit('SET_STEP', 0);
    },

    async completeStep({ commit, state }, stepId) {
      commit('ADD_COMPLETED_STEP', stepId);
      if (state.currentStepIndex < GUIDANCE_STEPS.length - 1) {
        commit('SET_STEP', state.currentStepIndex + 1);
      }
      await this.dispatch('guidance/saveStatus');
    },

    async skipStep({ commit, state }, stepId) {
      commit('ADD_SKIPPED_STEP', stepId);
      if (state.currentStepIndex < GUIDANCE_STEPS.length - 1) {
        commit('SET_STEP', state.currentStepIndex + 1);
      }
      await this.dispatch('guidance/saveStatus');
    },

    async dismissGuidance({ commit }) {
      commit('SET_ACTIVE', false);
      await this.dispatch('guidance/saveStatus');
    },

    async saveStatus({ state }) {
      await api.post('/user/guidance-status', {
        is_active: state.isActive,
        current_step: state.currentStepIndex,
        completed_steps: state.completedSteps,
        skipped_steps: state.skippedSteps,
      });
    },

    async fetchStatus({ commit }) {
      const response = await api.get('/user/guidance-status');
      commit('SET_STATUS', response.data);
    },
  };
  ```

- [ ] **6.8** Create `GuidanceWelcomeModal.vue`
  ```vue
  <template>
    <Dialog :open="isOpen" @close="$emit('dismiss')">
      <DialogPanel class="max-w-md mx-auto bg-white rounded-xl p-6 text-center">
        <div class="w-16 h-16 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <SparklesIcon class="h-8 w-8 text-primary-600" />
        </div>

        <DialogTitle class="text-xl font-semibold mb-2">
          Welcome to Fynla!
        </DialogTitle>

        <p class="text-gray-600 mb-6">
          Let's personalise your financial plan. We'll guide you through the
          key sections step by step.
        </p>

        <div class="space-y-3">
          <button @click="$emit('start')" class="btn-primary w-full">
            Let's Start
          </button>
          <button @click="$emit('dismiss')" class="text-gray-500 hover:text-gray-700">
            I'll explore on my own
          </button>
        </div>
      </DialogPanel>
    </Dialog>
  </template>
  ```

- [ ] **6.9** Create `GuidanceTooltip.vue`
  ```vue
  <template>
    <Teleport to="body">
      <div
        v-if="isVisible"
        ref="tooltip"
        class="fixed z-50 bg-white rounded-xl shadow-xl border p-4 max-w-xs"
        :style="tooltipPosition"
      >
        <!-- Arrow -->
        <div class="absolute w-3 h-3 bg-white border-l border-t transform rotate-45" :style="arrowStyle" />

        <!-- Content -->
        <div class="text-xs text-gray-500 mb-1">
          Step {{ progress.current }} of {{ progress.total }}
        </div>
        <p class="text-gray-800 mb-3">{{ step.message }}</p>

        <div class="flex items-center gap-2">
          <button @click="goToStep" class="btn-primary btn-sm flex-1">
            {{ step.actionLabel || 'Go' }}
          </button>
          <button @click="skip" class="text-sm text-gray-500 hover:text-gray-700">
            Skip
          </button>
        </div>
      </div>
    </Teleport>

    <!-- Target highlight overlay -->
    <div
      v-if="isVisible && targetElement"
      class="fixed inset-0 z-40 pointer-events-none"
    >
      <div
        class="absolute ring-2 ring-primary-500 ring-offset-2 rounded-lg animate-pulse"
        :style="highlightStyle"
      />
    </div>
  </template>
  ```

- [ ] **6.10** Integrate guidance into `Dashboard.vue`
  ```vue
  <template>
    <AppLayout>
      <!-- Welcome modal for first-time users -->
      <GuidanceWelcomeModal
        v-if="showWelcome"
        :is-open="showWelcome"
        @start="startGuidance"
        @dismiss="dismissGuidance"
      />

      <!-- Guidance tooltip -->
      <GuidanceTooltip
        v-if="guidanceActive"
        :step="currentGuidanceStep"
        :target="currentGuidanceStep?.target"
        @complete="completeGuidanceStep"
        @skip="skipGuidanceStep"
      />

      <!-- Rest of dashboard -->
      <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- ... existing dashboard content ... -->
      </div>
    </AppLayout>
  </template>

  <script>
  export default {
    computed: {
      ...mapGetters('guidance', ['isActive', 'currentStep', 'progress']),

      showWelcome() {
        return this.isFirstVisit && !this.guidanceActive && !this.guidanceDismissed;
      },

      isFirstVisit() {
        return this.$store.state.auth.user?.guidance_active === true
          && this.$store.state.guidance.completedSteps.length === 0;
      },
    },

    async mounted() {
      await this.$store.dispatch('guidance/fetchStatus');
    },
  };
  </script>
  ```

- [ ] **6.11** Create guidance API endpoints
  ```php
  // GET /api/user/guidance-status
  public function getGuidanceStatus(Request $request)
  {
      $user = $request->user();
      return response()->json([
          'is_active' => $user->guidance_active,
          'current_step' => $user->guidance_current_step,
          'completed_steps' => $user->guidance_completed_steps ?? [],
          'skipped_steps' => $user->guidance_skipped_steps ?? [],
          'version' => $user->guidance_version,
      ]);
  }

  // POST /api/user/guidance-status
  public function updateGuidanceStatus(Request $request)
  {
      $validated = $request->validate([
          'is_active' => 'boolean',
          'current_step' => 'integer|min:0',
          'completed_steps' => 'array',
          'skipped_steps' => 'array',
      ]);

      $request->user()->update([
          'guidance_active' => $validated['is_active'] ?? false,
          'guidance_current_step' => $validated['current_step'] ?? 0,
          'guidance_completed_steps' => $validated['completed_steps'] ?? [],
          'guidance_skipped_steps' => $validated['skipped_steps'] ?? [],
      ]);

      return response()->json(['success' => true]);
  }
  ```

- [ ] **6.12** Add "Re-run Setup Guide" to settings
  ```vue
  <!-- In UserSettings.vue or Profile page -->
  <button
    @click="restartGuidance"
    class="text-primary-600 hover:text-primary-700"
  >
    Re-run Setup Guide
  </button>
  ```

### Checkpoint 6: Full Journey Complete

**Verification:**
- [ ] Landing → Preview → Register → "Keep or Fresh" modal appears
- [ ] Select "Keep data" → Dashboard loads with persona data
- [ ] Check database: records have `is_demo_origin = true`
- [ ] Select "Start fresh" → Dashboard loads empty
- [ ] Welcome modal appears for new users
- [ ] "Let's Start" → First tooltip appears
- [ ] Complete step → Next tooltip appears
- [ ] Skip step → Next tooltip appears
- [ ] Dismiss guidance → No more tooltips
- [ ] Settings → "Re-run Setup Guide" → Guidance restarts

---

## Phase 7: Polish & Edge Cases

### Tasks

- [ ] **7.1** Handle authenticated user visiting preview
  - Show toast: "You already have an account"
  - Redirect to dashboard

- [ ] **7.2** Handle session expiry during preview
  - Preview data is session-only
  - On browser refresh, return to default persona

- [ ] **7.3** Handle preview route bookmarking
  - `/preview` always loads default persona
  - `/preview?persona=widow` could load specific persona

- [ ] **7.4** Mobile optimisations
  - Persona selector: Full-screen modal on mobile
  - Guidance tooltips: Bottom sheets on mobile
  - Preview banner: Collapsible on mobile

- [ ] **7.5** Analytics tracking
  ```javascript
  // Track key events
  analytics.track('preview_started', { persona: 'young_family' });
  analytics.track('persona_switched', { from: 'young_family', to: 'widow' });
  analytics.track('preview_value_edited', { field: 'properties.0.current_value' });
  analytics.track('preview_registration_started');
  analytics.track('preview_data_kept', { persona: 'widow' });
  ```

- [ ] **7.6** Loading states
  - Skeleton loaders during persona switch
  - Calculation loading indicators
  - Button loading states

- [ ] **7.7** Error handling
  - Graceful fallback if persona JSON fails to load
  - Error toast if calculation endpoint fails
  - Retry mechanisms

- [ ] **7.8** Accessibility
  - Keyboard navigation for modals
  - Screen reader announcements
  - Focus management in guidance flow

- [ ] **7.9** Cross-browser testing
  - Chrome, Firefox, Safari, Edge
  - iOS Safari, Android Chrome

- [ ] **7.10** Performance
  - Lazy load persona JSON files
  - Debounce calculation API calls
  - Cache calculation results during session

### Final Checkpoint: Production Ready

**Verification:**
- [ ] All P0 acceptance criteria pass
- [ ] All P1 acceptance criteria pass
- [ ] Mobile experience professional
- [ ] No console errors
- [ ] Performance acceptable (<3s loads)
- [ ] Accessibility audit passes

---

## Summary

| Phase | Tasks | Key Integration Point |
|-------|-------|----------------------|
| 1. Vuex Integration | 8 | Modify existing stores, don't duplicate |
| 2. Persona Data | 7 | Match exact DB schema |
| 3. Routes & Components | 8 | Reuse existing views |
| 4. Backend Calculations | 7 | Add stateless methods to existing services |
| 5. Interactive Editing | 6 | Field classification + warning modals |
| 6. Registration & Guidance | 12 | New registration flow, guidance system |
| 7. Polish | 10 | Edge cases, mobile, analytics |
| **Total** | **58** | |

---

## Critical Integration Notes

1. **No component duplication** - Existing cards/views work with preview data via Vuex getters
2. **Schema must match exactly** - Persona JSON = API response format = Database schema
3. **Real calculations** - Backend services get new `calculateFromData()` methods
4. **Session-only state** - No localStorage, refresh = reset
5. **Demo origin tracking** - `is_demo_origin` flag invisible to user but tracks seeded data

---

*Document created: 11 December 2025*
*For PRD reference: `/Users/Chris/Desktop/fpsApp/tengo/FynlaNew/newFynla.md`*
