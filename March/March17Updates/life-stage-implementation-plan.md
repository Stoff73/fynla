# Life Stage Journey System — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform Fynla's entire UX around 5 UK financial planning life stages — adaptive sidebar, dashboard, onboarding, forms, and demo experience.

**Architecture:** A central `lifeStageConfig.js` constant drives all stage-adaptive behaviour. A `lifeStage` Vuex module manages state. Every component reads from this config via computed properties. Backend mirrors the config in `LifeStageService.php`.

**Tech Stack:** Laravel 10, Vue.js 3 (Options API), Vuex 4 (namespaced modules), Tailwind CSS, ApexCharts, Pest (testing), MySQL 8

**Spec:** `March/March17Updates/life-stage-journey-design.md`

**Design Guide:** `fynlaDesignGuide.md` v1.2.0 — **CRITICAL: Read this before implementing ANY UI component.** Located at `/Users/CSJ/Desktop/fynla/fynlaDesignGuide.md`. This is the single source of truth for all visual decisions: colours (raspberry, horizon, spring, violet, savannah, eggshell), typography (Segoe UI primary, Inter fallback, weights 900/700), component patterns (buttons, cards, forms, modals, badges), spacing, borders, shadows, animations, and responsive breakpoints. All Tailwind tokens must come from this guide. Never use amber-*, orange-*, primary-*, secondary-*, or hardcoded hex values.

---

## Phase 1: Foundation

**Goal:** Create the core life stage infrastructure that all other phases depend on.

### Task 1.1: Database Migration — Add `life_stage` to Users

**Files:**
- Create: `database/migrations/2026_03_17_000001_add_life_stage_to_users_table.php`
- Test: `tests/Unit/MigrationTest.php` (verify column exists)

- [ ] **Step 1: Create the migration**

```php
<?php
// database/migrations/2026_03_17_000001_add_life_stage_to_users_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('life_stage', 20)->nullable()->after('onboarding_mode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('life_stage');
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 3: Add `life_stage` to User model fillable**

Modify: `app/Models/User.php` — add `'life_stage'` to the `$fillable` array.

- [ ] **Step 4: Write test to verify column**

```php
it('has life_stage column on users table', function () {
    expect(Schema::hasColumn('users', 'life_stage'))->toBeTrue();
});
```

- [ ] **Step 5: Run test, verify pass**

```bash
./vendor/bin/pest tests/Unit/MigrationTest.php --filter="life_stage"
```

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_03_17_000001_add_life_stage_to_users_table.php app/Models/User.php tests/Unit/MigrationTest.php
git commit -m "feat: add life_stage column to users table"
```

---

### Task 1.2: Life Stage Config — Frontend

**Files:**
- Create: `resources/js/constants/lifeStageConfig.js`
- Test: `tests/Unit/LifeStageConfigTest.php` (import test — optional, see note)

- [ ] **Step 1: Create `lifeStageConfig.js`**

This is the single source of truth. All 5 stages with their full configuration. Reference spec sections: §2.1 (definitions), §5.2 (onboarding steps), §7.3 (sidebar items), §8.2 (dashboard cards), §9.1 (suggested goals), §11.4 (form fields).

Create: `resources/js/constants/lifeStageConfig.js`

The config must define for each of the 5 stages (`university`, `early_career`, `mid_career`, `peak`, `retirement`):

```javascript
export const LIFE_STAGES = {
  university: {
    id: 'university',
    label: 'Starting Out',
    tagline: 'Build smart money habits from day one',
    ageRange: '18–25',
    persona: 'student',
    icon: 'graduation-cap',
    colour: 'violet',

    sidebar: {
      primary: ['dashboard', 'bank-accounts', 'income', 'expenditure', 'savings', 'goals', 'risk-profile'],
      explore: ['investments', 'retirement', 'property', 'protection', 'will', 'estate', 'plans', 'business', 'trusts', 'chattels'],
    },

    dashboard: {
      cards: ['budget-tracker', 'student-loan', 'savings', 'goals', 'life-timeline'],
    },

    onboarding: {
      steps: ['personal-info', 'student-loan', 'income', 'expenditure', 'savings', 'goals'],
      learningMilestones: {
        'personal-info': {
          didYouKnow: 'Your date of birth determines when you\'ll start repaying your student loan and when you\'ll be automatically enrolled in a workplace pension (from age 22).',
          whyWeAsk: 'Your age and gender affect life expectancy projections, pension eligibility dates, and ISA/Lifetime ISA access rules.',
          howItFits: 'As a student, we keep things simple — no need for address or health details yet. We focus on what matters now.',
          quickStat: { value: '22', label: 'Age you\'ll be auto-enrolled in a workplace pension' },
        },
        'student-loan': {
          didYouKnow: 'Student loans are not like normal debt — repayments are income-based and written off after 40 years (Plan 5). Most graduates will never fully repay.',
          whyWeAsk: 'Your plan type determines your repayment threshold and interest rate. This affects whether overpaying ever makes financial sense.',
          howItFits: 'Understanding your loan means you won\'t waste money overpaying when that money could be building your emergency fund or going into a Lifetime ISA instead.',
          quickStat: { value: '£27,295', label: 'Plan 5 repayment threshold (2025/26)' },
        },
        'income': {
          didYouKnow: 'The average student shortfall is £400-600/month. Knowing what comes in is the foundation of budgeting.',
          whyWeAsk: 'Whether it\'s a part-time job, placement salary, or parental support — knowing what comes in lets us build a realistic spending plan.',
          howItFits: 'Income tracking is the first step to budgeting. Once you know what comes in, you can make conscious choices about what goes out.',
          quickStat: { value: '£400–600', label: 'Average monthly student shortfall' },
        },
        'expenditure': {
          didYouKnow: 'Most students overspend by £400-600/month without realising. Tracking isn\'t about restriction — it\'s about conscious choices.',
          whyWeAsk: 'We use your spending to calculate your emergency fund target (3 months of expenses) and show where savings are possible.',
          howItFits: 'A clear picture of spending is the foundation everything else builds on — savings goals, budgets, and financial confidence.',
          quickStat: null,
        },
        'savings': {
          didYouKnow: 'Even £25/month adds up. Starting at 21 instead of 30 could mean tens of thousands more by retirement thanks to compound interest.',
          whyWeAsk: 'Knowing your accounts lets us track emergency fund progress, calculate interest, and monitor ISA allowance usage.',
          howItFits: 'Your first goal is an emergency fund — 3 months of living costs. That\'s your safety net before thinking about investing.',
          quickStat: { value: '£20,000', label: 'Your annual ISA allowance (2025/26)' },
        },
        'goals': {
          didYouKnow: 'People who write down specific financial goals are 42% more likely to achieve them than those who don\'t.',
          whyWeAsk: 'Clear goals let us calculate timelines, suggest monthly savings amounts, and track your progress on your dashboard.',
          howItFits: 'Graduate debt-free? Build an emergency fund? Save for a car? Clear goals give your money a purpose.',
          quickStat: null,
        },
      },
    },

    suggestedGoals: [
      { id: 'emergency-fund', label: 'Build an emergency fund', description: '3 months of living costs' },
      { id: 'save-for-car', label: 'Save for a car', description: 'Set a target and timeline' },
      { id: 'graduate-debt-free', label: 'Graduate debt-free', description: 'Beyond your student loan' },
      { id: 'travel-fund', label: 'Travel fund', description: 'Post-graduation plans' },
    ],

    formFields: {
      personalInfo: {
        always: ['first_name', 'last_name', 'date_of_birth', 'gender', 'phone'],
        stage: ['education_level', 'university', 'student_number'],
        onboardingHide: ['address_line_1', 'address_line_2', 'city', 'county', 'postcode', 'marital_status', 'occupation', 'employer', 'employment_status', 'industry', 'target_retirement_age', 'health_status', 'smoking_status', 'country_of_birth', 'domicile_status'],
      },
      income: {
        always: ['employment_status'],
        stage: ['part_time_income', 'maintenance_loan', 'parental_support', 'bursary_grant'],
        onboardingHide: ['annual_employment_income', 'annual_self_employment_income', 'annual_rental_income', 'dividend_income'],
      },
      savings: {
        defaultTypes: ['current_account', 'easy_access', 'instant_access', 'cash_isa'],
        emergencyFundProminent: true,
        hideOwnership: true,
      },
    },

    learning: {
      pensionContext: 'auto-enrolment-from-22',
      savingsContext: 'emergency-fund-basics',
      debtContext: 'student-loan-not-like-debt',
    },
  },

  // REPEAT FOR: early_career, mid_career, peak, retirement
  // Using data from spec sections §5.2, §7.3, §8.2, §9.1, §11.4
  // Each stage follows the identical shape above with stage-specific values
};

export const STAGE_ORDER = ['university', 'early_career', 'mid_career', 'peak', 'retirement'];

export const PERSONA_TO_STAGE = {
  student: 'university',
  young_saver: 'early_career',
  young_family: 'mid_career',
  entrepreneur: 'mid_career',
  peak_earners: 'peak',
  retired_couple: 'retirement',
};
```

**Note:** The `university` stage is shown in full above. The implementer must write the remaining 4 stages using data from spec sections §5.2, §7.3, §8.2, §9.1, §11.4. Each follows the identical object shape.

- [ ] **Step 2: Verify file loads without error**

```bash
# Start dev server if not running
./dev.sh
# Check for compilation errors in terminal output
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/constants/lifeStageConfig.js
git commit -m "feat: add lifeStageConfig.js — central stage configuration for all 5 life stages"
```

---

### Task 1.3: Vuex Store — `lifeStage` Module

**Files:**
- Create: `resources/js/store/modules/lifeStage.js`
- Modify: `resources/js/store/index.js` (register module)

- [ ] **Step 1: Create the store module**

Create: `resources/js/store/modules/lifeStage.js`

```javascript
import { LIFE_STAGES, STAGE_ORDER, PERSONA_TO_STAGE } from '@/constants/lifeStageConfig';
import lifeStageService from '@/services/lifeStageService';

const state = {
  currentStage: null, // 'university' | 'early_career' | 'mid_career' | 'peak' | 'retirement'
  completedSteps: [],
  loading: false,
  error: null,
};

const getters = {
  currentStage: (state) => state.currentStage,
  stageConfig: (state) => state.currentStage ? LIFE_STAGES[state.currentStage] : null,
  stageLabel: (state, getters) => getters.stageConfig?.label || '',
  stageColour: (state, getters) => getters.stageConfig?.colour || 'horizon',
  stageTagline: (state, getters) => getters.stageConfig?.tagline || '',

  sidebarPrimary: (state, getters) => getters.stageConfig?.sidebar?.primary || [],
  sidebarExplore: (state, getters) => getters.stageConfig?.sidebar?.explore || [],
  dashboardCards: (state, getters) => getters.stageConfig?.dashboard?.cards || [],
  onboardingSteps: (state, getters) => getters.stageConfig?.onboarding?.steps || [],
  suggestedGoals: (state, getters) => getters.stageConfig?.suggestedGoals || [],
  learningMilestone: (state, getters) => (stepId) => getters.stageConfig?.onboarding?.learningMilestones?.[stepId] || null,
  formFields: (state, getters) => (formName) => getters.stageConfig?.formFields?.[formName] || {},

  progressPercentage: (state, getters) => {
    const steps = getters.onboardingSteps;
    if (!steps.length) return 0;
    return Math.round((state.completedSteps.length / steps.length) * 100);
  },

  nextStep: (state, getters) => {
    const steps = getters.onboardingSteps;
    return steps.find(step => !state.completedSteps.includes(step)) || null;
  },

  // Dynamic promotion: merge primary + user-data-promoted modules
  effectiveSidebarPrimary: (state, getters, rootState, rootGetters) => {
    const primary = [...(getters.sidebarPrimary || [])];
    const explore = getters.sidebarExplore || [];
    const flags = getters.userDataFlags;

    // Promote modules from explore to primary if user has data
    const moduleToFlag = {
      'property': 'properties',
      'protection': 'protection',
      'investments': 'investments',
      'retirement': 'pensions',
      'will': 'will',
      'estate': 'will',
      'trusts': 'trusts',
      'business': 'business',
      'savings': 'savings',
    };

    explore.forEach(moduleId => {
      const flagKey = moduleToFlag[moduleId];
      if (flagKey && flags[flagKey] && !primary.includes(moduleId)) {
        primary.push(moduleId);
      }
    });

    return primary;
  },

  effectiveSidebarExplore: (state, getters) => {
    const effectivePrimary = getters.effectiveSidebarPrimary;
    return (getters.sidebarExplore || []).filter(id => !effectivePrimary.includes(id));
  },

  userDataFlags: (state, getters, rootState) => ({
    properties: (rootState.netWorth?.properties?.length || 0) > 0,
    savings: (rootState.savings?.accounts?.length || 0) > 0,
    investments: (rootState.investment?.accounts?.length || 0) > 0,
    pensions: (rootState.retirement?.pensions?.length || 0) > 0,
    protection: (rootState.protection?.policies?.length || 0) > 0,
    will: rootState.estate?.will !== null && rootState.estate?.will !== undefined,
    trusts: (rootState.estate?.trusts?.length || 0) > 0,
    business: (rootState.netWorth?.businessInterests?.length || 0) > 0,
  }),

  isFieldVisible: (state, getters) => (formName, fieldName, context) => {
    if (context === 'standalone') return true;
    const config = getters.formFields(formName);
    if (!config) return true;
    const alwaysFields = config.always || [];
    const stageFields = config.stage || [];
    const onboardingHide = config.onboardingHide || [];
    if (context === 'onboarding' && onboardingHide.includes(fieldName)) return false;
    return alwaysFields.includes(fieldName) || stageFields.includes(fieldName);
  },

  allStages: () => STAGE_ORDER.map(id => LIFE_STAGES[id]),
  personaToStage: () => PERSONA_TO_STAGE,
};

const mutations = {
  setCurrentStage(state, stage) { state.currentStage = stage; },
  setCompletedSteps(state, steps) { state.completedSteps = steps; },
  addCompletedStep(state, step) {
    if (!state.completedSteps.includes(step)) {
      state.completedSteps.push(step);
    }
  },
  setLoading(state, loading) { state.loading = loading; },
  setError(state, error) { state.error = error; },
};

const actions = {
  async fetchStage({ commit, rootGetters }) {
    commit('setLoading', true);
    try {
      const user = rootGetters['auth/user'];
      if (user?.life_stage) {
        commit('setCurrentStage', user.life_stage);
      }
      // Also load completed steps from backend
      const response = await lifeStageService.getProgress();
      commit('setCompletedSteps', response.completed_steps || []);
    } catch (error) {
      commit('setError', error.message);
    } finally {
      commit('setLoading', false);
    }
  },

  async setStage({ commit }, stage) {
    commit('setLoading', true);
    try {
      await lifeStageService.setStage(stage);
      commit('setCurrentStage', stage);
    } catch (error) {
      commit('setError', error.message);
      throw error;
    } finally {
      commit('setLoading', false);
    }
  },

  async completeStep({ commit }, stepId) {
    commit('addCompletedStep', stepId);
    try {
      await lifeStageService.completeStep(stepId);
    } catch (error) {
      commit('setError', error.message);
    }
  },

  setStageFromPersona({ commit }, personaId) {
    const basePersona = personaId.replace(/_spouse$/, '');
    const stage = PERSONA_TO_STAGE[basePersona];
    if (stage) {
      commit('setCurrentStage', stage);
    }
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
```

- [ ] **Step 2: Create the API service**

Create: `resources/js/services/lifeStageService.js`

```javascript
import api from './api';

const lifeStageService = {
  async getProgress() {
    const response = await api.get('/api/life-stage/progress');
    return response.data;
  },
  async setStage(stage) {
    const response = await api.post('/api/life-stage/set', { life_stage: stage });
    return response.data;
  },
  async completeStep(stepId) {
    const response = await api.post('/api/life-stage/complete-step', { step: stepId });
    return response.data;
  },
};

export default lifeStageService;
```

- [ ] **Step 3: Register in Vuex store**

Modify: `resources/js/store/index.js` — add `import lifeStage from './modules/lifeStage';` and add `lifeStage` to the modules object.

- [ ] **Step 4: Verify store loads**

Check `./dev.sh` terminal for compilation errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/store/modules/lifeStage.js resources/js/services/lifeStageService.js resources/js/store/index.js
git commit -m "feat: add lifeStage Vuex module with stage config, dynamic promotion, and progress tracking"
```

---

### Task 1.4: Backend — LifeStageService + API Routes

**Files:**
- Create: `app/Services/LifeStage/LifeStageService.php`
- Create: `app/Http/Controllers/Api/LifeStageController.php`
- Modify: `routes/api.php` (add routes)

- [ ] **Step 1: Create LifeStageService**

Create: `app/Services/LifeStage/LifeStageService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\LifeStage;

use App\Models\User;

class LifeStageService
{
    public const VALID_STAGES = ['university', 'early_career', 'mid_career', 'peak', 'retirement'];

    public function setStage(User $user, string $stage): void
    {
        if (!in_array($stage, self::VALID_STAGES, true)) {
            throw new \InvalidArgumentException("Invalid life stage: {$stage}");
        }

        $user->update(['life_stage' => $stage]);
    }

    public function getProgress(User $user): array
    {
        return [
            'life_stage' => $user->life_stage,
            'completed_steps' => $user->life_stage_completed_steps ?? [],
        ];
    }

    public function completeStep(User $user, string $stepId): void
    {
        $steps = $user->life_stage_completed_steps ?? [];
        if (!in_array($stepId, $steps, true)) {
            $steps[] = $stepId;
        }
        $user->update(['life_stage_completed_steps' => $steps]);
    }

    public function suggestTransition(User $user): ?string
    {
        // Implements §13.7 Stage Suggestion Algorithm
        // Returns suggested stage or null
        $currentStage = $user->life_stage;
        $age = $user->age; // Assumes age accessor exists on User model

        if ($currentStage === 'university' && $age > 22) {
            if ($user->employment_status === 'employed' || $user->properties()->exists()) {
                return 'early_career';
            }
        }

        if ($currentStage === 'early_career' && $age > 29) {
            if ($user->familyMembers()->where('relationship', 'child')->exists()
                || $user->marital_status === 'married') {
                return 'mid_career';
            }
        }

        // ... additional rules per spec §13.7

        return null;
    }
}
```

- [ ] **Step 2: Create the controller**

Create: `app/Http/Controllers/Api/LifeStageController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LifeStage\LifeStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LifeStageController extends Controller
{
    public function __construct(
        private readonly LifeStageService $lifeStageService
    ) {}

    public function progress(Request $request): JsonResponse
    {
        $progress = $this->lifeStageService->getProgress($request->user());
        return response()->json($progress);
    }

    public function setStage(Request $request): JsonResponse
    {
        $request->validate([
            'life_stage' => 'required|string|in:' . implode(',', LifeStageService::VALID_STAGES),
        ]);

        $this->lifeStageService->setStage($request->user(), $request->life_stage);

        return response()->json(['success' => true, 'life_stage' => $request->life_stage]);
    }

    public function completeStep(Request $request): JsonResponse
    {
        $request->validate(['step' => 'required|string']);

        $this->lifeStageService->completeStep($request->user(), $request->step);

        return response()->json(['success' => true]);
    }
}
```

- [ ] **Step 3: Add routes**

Modify: `routes/api.php` — add inside the authenticated middleware group:

```php
Route::prefix('life-stage')->group(function () {
    Route::get('/progress', [LifeStageController::class, 'progress']);
    Route::post('/set', [LifeStageController::class, 'setStage']);
    Route::post('/complete-step', [LifeStageController::class, 'completeStep']);
});
```

- [ ] **Step 4: Add `life_stage_completed_steps` column** (if not yet created — JSON column on users table)

Create migration: `database/migrations/2026_03_17_000002_add_life_stage_completed_steps_to_users.php`

```php
Schema::table('users', function (Blueprint $table) {
    $table->json('life_stage_completed_steps')->nullable()->after('life_stage');
});
```

Add to User model `$casts`:
```php
'life_stage_completed_steps' => 'array',
```

- [ ] **Step 5: Write tests**

```php
// tests/Feature/LifeStageControllerTest.php
it('sets a valid life stage', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->postJson('/api/life-stage/set', ['life_stage' => 'university'])
        ->assertOk()
        ->assertJson(['life_stage' => 'university']);
    expect($user->fresh()->life_stage)->toBe('university');
});

it('rejects invalid life stage', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->postJson('/api/life-stage/set', ['life_stage' => 'invalid'])
        ->assertUnprocessable();
});

it('returns progress', function () {
    $user = User::factory()->create(['life_stage' => 'university']);
    $this->actingAs($user)
        ->getJson('/api/life-stage/progress')
        ->assertOk()
        ->assertJsonStructure(['life_stage', 'completed_steps']);
});

it('completes a step', function () {
    $user = User::factory()->create(['life_stage' => 'university', 'life_stage_completed_steps' => []]);
    $this->actingAs($user)
        ->postJson('/api/life-stage/complete-step', ['step' => 'personal-info'])
        ->assertOk();
    expect($user->fresh()->life_stage_completed_steps)->toContain('personal-info');
});
```

- [ ] **Step 6: Run tests**

```bash
./vendor/bin/pest tests/Feature/LifeStageControllerTest.php -v
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/LifeStage/ app/Http/Controllers/Api/LifeStageController.php routes/api.php database/migrations/2026_03_17_000002* tests/Feature/LifeStageControllerTest.php app/Models/User.php
git commit -m "feat: add LifeStageService, API controller, and routes for life stage management"
```

---

### Task 1.5: Persona Changes — Remove Widow, Amend Young Saver, Add `life_stage`

**Files:**
- Delete: `resources/js/data/personas/widow.json`
- Modify: `resources/js/data/personas/young_saver.json`
- Modify: `resources/js/data/personas/student.json` (add `life_stage`)
- Modify: `resources/js/data/personas/young_family.json` (add `life_stage`)
- Modify: `resources/js/data/personas/entrepreneur.json` (add `life_stage`)
- Modify: `resources/js/data/personas/peak_earners.json` (add `life_stage`)
- Modify: `resources/js/data/personas/retired_couple.json` (add `life_stage`)
- Modify: `database/seeders/PreviewUserSeeder.php`
- Modify: `app/Http/Controllers/Api/PreviewController.php`
- Modify: `app/Console/Commands/ResetPreviewData.php`

- [ ] **Step 1: Delete widow persona JSON**

```bash
rm resources/js/data/personas/widow.json
```

- [ ] **Step 2: Add `life_stage` to all 6 remaining persona JSONs**

For each file, add `"life_stage": "<stage_id>"` at the top level of the JSON object:

| File | Value |
|------|-------|
| `student.json` | `"life_stage": "university"` |
| `young_saver.json` | `"life_stage": "early_career"` |
| `young_family.json` | `"life_stage": "mid_career"` |
| `entrepreneur.json` | `"life_stage": "mid_career"` |
| `peak_earners.json` | `"life_stage": "peak"` |
| `retired_couple.json` | `"life_stage": "retirement"` |

- [ ] **Step 3: Amend `young_saver.json`**

Update John Morgan's profile:
- `date_of_birth`: change to ~1998 (making him ~28)
- `annual_income`: change to 38000
- `occupation`: "Data Analyst" (from "Junior Data Analyst")
- `employer_name`: add employer
- `employment_status`: "employed"
- Pension: increase NEST balance to ~£8,000
- Savings: increase to reflect early career (£3,500 emergency fund, £6,000 LISA)
- Goals: add "house-deposit" goal (£25,000 target, £6,000 current)
- Add `life_stage: "early_career"`

- [ ] **Step 4: Update PreviewUserSeeder**

Modify: `database/seeders/PreviewUserSeeder.php`
- Remove `'widow'` from the `PERSONAS` constant array
- The seeder loads from JSON files, so removing the widow JSON + constant entry is sufficient

- [ ] **Step 5: Update PreviewController**

Modify: `app/Http/Controllers/Api/PreviewController.php`
- Remove `'widow'` from `VALID_PERSONAS` constant
- Remove the widow metadata block (id, tagline, description)
- Add `life_stage` to the persona metadata returned to frontend

- [ ] **Step 6: Update ResetPreviewData command**

Modify: `app/Console/Commands/ResetPreviewData.php`
- Remove any `'widow'` references

- [ ] **Step 7: Reseed database**

```bash
php artisan db:seed
```

- [ ] **Step 8: Verify all personas load**

Test each preview persona login works via the landing page.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: remove widow persona, amend young_saver to early career, add life_stage to all personas"
```

---

### Task 1.6: Seed Database

```bash
php artisan db:seed
```

---

## Phase 2: Unified Forms

**Goal:** Add `context` prop to existing forms, implement stage-adaptive field visibility, retire 12 duplicate onboarding-only forms.

**Depends on:** Phase 1 (lifeStageConfig, lifeStage Vuex module)

### Task 2.1: `isFieldVisible` Composable

**Files:**
- Create: `resources/js/composables/useLifeStageFields.js`

- [ ] **Step 1: Create the composable**

```javascript
// resources/js/composables/useLifeStageFields.js
import { computed } from 'vue';
import { useStore } from 'vuex';

export function useLifeStageFields(formName, context) {
  const store = useStore();

  const isFieldVisible = (fieldName) => {
    return store.getters['lifeStage/isFieldVisible'](formName, fieldName, context);
  };

  const stageConfig = computed(() => store.getters['lifeStage/formFields'](formName));

  return { isFieldVisible, stageConfig };
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/composables/useLifeStageFields.js
git commit -m "feat: add useLifeStageFields composable for stage-adaptive form field visibility"
```

---

### Task 2.2: Adapt PersonalInformation.vue

**Files:**
- Modify: `resources/js/components/UserProfile/PersonalInformation.vue`

- [ ] **Step 1: Add `context` prop and field visibility**

Add prop: `context: { type: String, default: 'standalone' }`

Import and use the composable. Wrap each field block with `v-if="isFieldVisible('field_name')"`. In standalone context (`context === 'standalone'`), all fields are always visible (the composable returns true for everything).

For the university stage, add new fields (shown only when `isFieldVisible('student_number')` etc.):
- University (text input)
- Student Number (text input)
- Education Level (select)

Add an info bar visible when `context === 'onboarding'`:
> "You can add your address, occupation and other details later in your profile settings."

- [ ] **Step 2: Add `@save` emit for onboarding context**

When `context === 'onboarding'`, instead of calling the API directly in `handleSubmit`, emit `save` with the form data so the OnboardingStep wrapper can handle it:

```javascript
if (this.context === 'onboarding') {
  this.$emit('save', this.form);
  return;
}
// ... existing API call for standalone
```

- [ ] **Step 3: Test in both contexts**

Verify the form works on the profile page (standalone) with all fields visible, and verify it would work with reduced fields when `context="onboarding"` is passed.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/UserProfile/PersonalInformation.vue
git commit -m "feat: add context prop and stage-adaptive field visibility to PersonalInformation.vue"
```

---

### Task 2.3: Adapt SaveAccountModal.vue

**Files:**
- Modify: `resources/js/components/Savings/SaveAccountModal.vue`

- [ ] **Step 1: Add `context` prop**

Add prop: `context: { type: String, default: 'standalone' }`

For stage-adaptive behaviour (per spec §11.4):
- Reorder product type `<optgroup>` based on stage (student defaults: Current, Easy Access, Cash ISA first)
- Make Emergency Fund checkbox more prominent for university stage (wrap in spring-coloured highlight box)
- Hide Joint Ownership section when `context === 'onboarding'` for university stage

The existing conditional logic (ISA fields, notice period, access type) stays completely untouched.

- [ ] **Step 2: Test existing usage is unaffected**

Verify `SaveAccountModal` still works in:
- `views/NetWorth/CashOverview.vue`
- `views/Savings/SavingsAccountDetail.vue`
- `components/Savings/CurrentSituation.vue`

All should function identically since `context` defaults to `'standalone'`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Savings/SaveAccountModal.vue
git commit -m "feat: add context prop and stage-adaptive defaults to SaveAccountModal.vue"
```

---

### Task 2.4: Adapt Remaining Forms

Repeat the same pattern for each form. Each gets a `context` prop (default `'standalone'`), and stage-specific behaviour only activates when `context === 'onboarding'`:

- [ ] **Step 1: Adapt `ExpenditureForm.vue`** — hide detailed categories for university stage in onboarding (show simple total only)
- [ ] **Step 2: Adapt `PropertyForm.vue`** — add context prop (no field hiding needed — property step only appears for stages that need it)
- [ ] **Step 3: Adapt `PolicyFormModal.vue`** — add context prop, stage-adaptive default type suggestions
- [ ] **Step 4: Commit each**

```bash
git add resources/js/components/UserProfile/ExpenditureForm.vue resources/js/components/NetWorth/Property/PropertyForm.vue resources/js/components/Protection/PolicyFormModal.vue
git commit -m "feat: add context prop to ExpenditureForm, PropertyForm, and PolicyFormModal"
```

---

### Task 2.5: Create LearningMilestoneSidebar.vue

**Files:**
- Create: `resources/js/components/Onboarding/LearningMilestoneSidebar.vue`

- [ ] **Step 1: Create the component**

Props: `step` (String), `stage` (String)

Reads milestone data from `lifeStageConfig[stage].onboarding.learningMilestones[step]`.

Renders three sections:
1. "Did you know?" — stage-coloured gradient card with educational content
2. "Why we ask this" — plain text section
3. "How this fits your journey" — plain text section
4. Quick stat (optional) — eggshell-500 card with large number + label

Stage colour drives the gradient and accent colours (violet for university, raspberry for mid-career, etc.).

Mobile: this component stacks below the form as a collapsible "Learn more" accordion.

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Onboarding/LearningMilestoneSidebar.vue
git commit -m "feat: add LearningMilestoneSidebar component for onboarding education"
```

---

### Task 2.6: Retire Onboarding-Only Forms

**Files to delete (12):**
- `resources/js/components/Onboarding/steps/SimplePersonalInfoStep.vue`
- `resources/js/components/Onboarding/steps/PersonalInfoStep.vue`
- `resources/js/components/Onboarding/steps/SimpleExpenditureStep.vue`
- `resources/js/components/Onboarding/steps/ExpenditureStep.vue`
- `resources/js/components/Onboarding/steps/SimplePropertyMortgageStep.vue`
- `resources/js/components/Onboarding/steps/SimpleSavingsAccountStep.vue`
- `resources/js/components/Onboarding/steps/LiabilitiesStep.vue`
- `resources/js/components/Onboarding/steps/FamilyInfoStep.vue`
- `resources/js/components/Onboarding/steps/GoalSetupStep.vue`
- `resources/js/components/Onboarding/steps/IncomeStep.vue`
- `resources/js/components/Onboarding/steps/QuickAssetsStep.vue`
- `resources/js/components/Onboarding/steps/AssetsStep.vue`

**IMPORTANT:** Do NOT delete these until Phase 4 (Onboarding Flow) is complete and the OnboardingWizard is rewired to use the unified forms. Until then, the existing onboarding still references these files. Mark them as deprecated with a comment at the top for now.

- [ ] **Step 1: Add deprecation comments to each file**

Add to the top of each `<script>` block:
```javascript
// DEPRECATED: This component will be replaced by [UnifiedFormName].vue with context="onboarding"
// See life-stage-journey-design.md §11.7
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/Onboarding/steps/
git commit -m "chore: mark 12 onboarding-only form components as deprecated — to be retired in Phase 4"
```

---

## Phase 3: Adaptive Sidebar

**Goal:** Transform the sidebar to show stage-relevant items with "Explore more" for secondary modules.

**Depends on:** Phase 1 (lifeStage Vuex module)

### Task 3.1: SideMenu.vue — Stage Badge & Progress

**Files:**
- Modify: `resources/js/components/SideMenu.vue`

- [ ] **Step 1: Add stage badge below logo**

Read `lifeStage/stageLabel` and `lifeStage/stageColour` from Vuex. Show below logo:
```html
<div class="text-xs font-semibold mt-1" :class="`text-${stageColour}-500`">
  {{ stageLabel }} · {{ stageConfig?.ageRange }}
</div>
```

- [ ] **Step 2: Add journey progress bar**

Below the stage badge, add a compact progress bar:
```html
<div class="px-4 py-2 border-b border-horizon-700">
  <div class="flex justify-between text-xs mb-1">
    <span class="text-horizon-400">Journey Progress</span>
    <span :class="`text-${stageColour}-500`" class="font-bold">{{ progressPercentage }}%</span>
  </div>
  <div class="h-1 bg-horizon-700 rounded-full">
    <div class="h-1 rounded-full" :class="`bg-${stageColour}-500`" :style="{ width: progressPercentage + '%' }"></div>
  </div>
</div>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/SideMenu.vue
git commit -m "feat: add stage badge and journey progress bar to sidebar"
```

---

### Task 3.2: SideMenu.vue — Primary/Explore Split

- [ ] **Step 1: Replace static menu items with stage-driven computed**

Replace the hardcoded menu item lists with computed properties reading from `lifeStage/effectiveSidebarPrimary` and `lifeStage/effectiveSidebarExplore`.

Create a mapping from sidebar item IDs to route paths and icon names (using existing `SideMenuIcon.vue` icons):

```javascript
const SIDEBAR_ITEMS = {
  'dashboard': { path: '/dashboard', icon: 'dashboard', label: 'Dashboard' },
  'bank-accounts': { path: '/net-worth/cash', icon: 'bank', label: 'Bank Accounts' },
  'income': { path: '/valuable-info?section=income', icon: 'income', label: 'Income' },
  'expenditure': { path: '/valuable-info?section=expenditure', icon: 'expenditure', label: 'Expenditure' },
  'savings': { path: '/net-worth/cash', icon: 'savings', label: 'Savings' },
  'investments': { path: '/net-worth/investments', icon: 'investments', label: 'Investments' },
  'retirement': { path: '/net-worth/retirement', icon: 'retirement', label: 'Retirement' },
  'property': { path: '/net-worth/property', icon: 'property', label: 'Property' },
  'protection': { path: '/protection', icon: 'protection', label: 'Protection' },
  'will': { path: '/estate/will-builder', icon: 'will', label: 'Will' },
  'estate': { path: '/estate', icon: 'estate', label: 'Estate Planning' },
  'goals': { path: '/goals', icon: 'goals', label: 'Goals' },
  'risk-profile': { path: '/risk-profile', icon: 'risk', label: 'Risk Profile' },
  'plans': { path: '/plans', icon: 'plans', label: 'Plans' },
  'business': { path: '/net-worth/business', icon: 'business', label: 'Business' },
  'trusts': { path: '/trusts', icon: 'trusts', label: 'Trusts' },
  'chattels': { path: '/net-worth/chattels', icon: 'chattels', label: 'Personal Valuables' },
};
```

- [ ] **Step 2: Add "Explore more" collapsible section**

Below the primary items, add a divider and an "Explore more" section using `SideMenuSection.vue`:
- Collapsed by default
- Items visually muted (`text-neutral-500`, reduced icon opacity)
- Chevron indicator (▼/▲)

- [ ] **Step 3: Use stage colour for active state**

Replace the fixed active colour with the dynamic stage colour:
```javascript
const activeClass = computed(() => `bg-${stageColour.value}-500/10 border-l-${stageColour.value}-500`);
```

- [ ] **Step 4: Test with different personas**

Switch between student and mid-career personas to verify different items appear.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/SideMenu.vue
git commit -m "feat: stage-driven sidebar with primary items and Explore more section"
```

---

### Task 3.3: Collapsed Sidebar — Progress Ring & Flyout

- [ ] **Step 1: Add SVG progress ring around favicon**

When sidebar is collapsed, replace the stage badge + progress bar with a circular SVG progress ring:

```html
<svg viewBox="0 0 40 40" class="w-10 h-10 -rotate-90">
  <circle cx="20" cy="20" r="17" fill="none" stroke-width="3" class="stroke-horizon-700" />
  <circle cx="20" cy="20" r="17" fill="none" stroke-width="3" :class="`stroke-${stageColour}-500`"
    :stroke-dasharray="106.8" :stroke-dashoffset="106.8 - (106.8 * progressPercentage / 100)"
    stroke-linecap="round" />
</svg>
```

- [ ] **Step 2: Add flyout panel for "Explore more"**

Replace the collapsed "Explore more" ⋮ icon with a hover-triggered flyout:
- Positioned `left-16` (slides out from collapsed sidebar edge)
- Shows all explore items with labels
- Rounded right corners, shadow, `bg-horizon-500`

- [ ] **Step 3: Test collapsed + expanded states**

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/SideMenu.vue
git commit -m "feat: add progress ring and explore-more flyout to collapsed sidebar"
```

---

## Phase 4: Onboarding Flow

**Goal:** Replace the existing onboarding with life-stage-driven journeys, journey map, and two-column layout.

**Depends on:** Phase 1 (config), Phase 2 (unified forms)

### Task 4.1: Welcome Screen — FocusAreaSelection Rewrite

**Files:**
- Modify: `resources/js/components/Onboarding/FocusAreaSelection.vue`

- [ ] **Step 1: Replace content with "Find Your Stage" layout**

5 life stage cards in a row, each with:
- Stage-coloured gradient icon circle
- Stage label, age range, tagline
- Click opens Journey Map modal (Task 4.2)

Plus "or focus on a specific area" divider with 4 focus area pills.

Use `lifeStage/allStages` getter for stage data.

- [ ] **Step 2: Commit**

---

### Task 4.2: Journey Map Component

**Files:**
- Create: `resources/js/components/Journey/JourneyMap.vue`

- [ ] **Step 1: Create SVG meandering path component**

Props: `stage` (String), `completedSteps` (Array, default [])

Reads steps from `lifeStageConfig[stage].onboarding.steps`. Renders:
- SVG meandering dashed path with cubic bezier curves
- Numbered nodes along the path (stage colour, fading opacity)
- Labels positioned with 28px gap from node edge
- Green destination node at the end
- Completed nodes shown in spring-500 with tick
- Current node has glow animation

Clicking a node emits `step-clicked` with step data for the expanded detail card.

- [ ] **Step 2: Add expanded detail card below map**

Shows learning milestone content for the clicked step.

- [ ] **Step 3: Add CTAs**

"Start My Journey" (raspberry-500) and "See It in Action" (white border) buttons. Emits `start` or `preview` events.

- [ ] **Step 4: Commit**

---

### Task 4.3: OnboardingWizard — Two-Column Layout

**Files:**
- Modify: `resources/js/components/Onboarding/OnboardingWizard.vue`

- [ ] **Step 1: Rewrite step rendering**

Replace the current step component references with unified forms:

```javascript
const STEP_COMPONENTS = {
  'personal-info': () => import('@/components/UserProfile/PersonalInformation.vue'),
  'student-loan': () => import('@/components/Estate/LiabilityForm.vue'), // filtered to student loan type
  'income': () => import('@/components/UserProfile/IncomeForm.vue'), // new unified
  'expenditure': () => import('@/components/UserProfile/ExpenditureForm.vue'),
  'savings': () => import('@/components/Savings/SaveAccountModal.vue'),
  'property': () => import('@/components/NetWorth/Property/PropertyForm.vue'),
  'protection': () => import('@/components/Protection/PolicyFormModal.vue'),
  'goals': () => import('@/components/Goals/GoalFormModal.vue'),
  // ... all step → component mappings
};
```

Each rendered with `context="onboarding"` prop.

- [ ] **Step 2: Add two-column layout**

Left (60%): Form component + Back/Skip/Continue navigation
Right (340px): `LearningMilestoneSidebar` component

- [ ] **Step 3: Add progress bar header**

Compact journey map showing completed (green tick), current (stage colour pulse), upcoming (grey) steps.

- [ ] **Step 4: Wire step completion to lifeStage store**

On "Continue", dispatch `lifeStage/completeStep` and advance to next step.

- [ ] **Step 5: Test full onboarding flow for student persona**

- [ ] **Step 6: Commit**

---

## Phase 5: Dashboard

**Goal:** Add journey progress hero, stage-curated card ordering, Goals card, and Life Timeline card.

**Depends on:** Phase 1 (config), Phase 3 (sidebar for consistent stage state)

### Task 5.1: JourneyProgressHero Component

**Files:**
- Create: `resources/js/components/Journey/JourneyProgressHero.vue`

- [ ] **Step 1: Create the component**

Reads from `lifeStage` store: greeting, stage label, progress %, next step. Compact horizontal layout with progress bar and "Continue Journey" CTA.

- [ ] **Step 2: Commit**

---

### Task 5.2: Dashboard.vue — Stage-Curated Cards

**Files:**
- Modify: `resources/js/views/Dashboard.vue`

- [ ] **Step 1: Add JourneyProgressHero at top**

Import and render above the existing card grid.

- [ ] **Step 2: Filter and order cards based on stage config**

Read `lifeStage/dashboardCards` and only render cards that match. Map card IDs to existing card sections in Dashboard.vue.

- [ ] **Step 3: Commit**

---

### Task 5.3: GoalsCard Component

**Files:**
- Create: `resources/js/components/Dashboard/GoalsCard.vue`

- [ ] **Step 1: Create with progress bars + stage-suggested goals**

Shows active goals with progress bars. Stage-suggested goals as dashed border cards. "+ Add goal" CTA.

- [ ] **Step 2: Commit**

---

### Task 5.4: LifeTimelineCard Component

**Files:**
- Create: `resources/js/components/Dashboard/LifeTimelineCard.vue`

- [ ] **Step 1: Create vertical timeline**

Past events (spring-500), imminent (stage colour with glow), future (light-gray). "What if →" and "+ Add event" links. "See how this affects your plan" for imminent events.

- [ ] **Step 2: Commit**

---

### Task 5.5: StageTransitionModal Component

**Files:**
- Create: `resources/js/components/Shared/StageTransitionModal.vue`

- [ ] **Step 1: Create modal per spec §9.3**

Shows current → suggested stage with icons. "Update My Journey" / "Stay Where I Am" CTAs.

- [ ] **Step 2: Wire to LifeStageService suggestion checks**

On dashboard load, check if a transition should be suggested (based on backend `suggestTransition` endpoint). Show modal if so.

- [ ] **Step 3: Commit**

---

## Phase 6: Landing Page & Mobile

**Goal:** "Find Your Stage" on landing page, preview mode integration, mobile adaptations.

**Depends on:** Phases 1, 3, 5

### Task 6.1: Landing Page — Find Your Stage

**Files:**
- Modify: Landing page components (identify exact files from `resources/js/views/` or `resources/js/components/Public/`)

- [ ] **Step 1: Add 5 stage cards to landing page**

Same layout as the welcome screen (Task 4.1) but clicking opens Journey Map in preview context. "See It in Action" enters preview mode for that stage's persona.

- [ ] **Step 2: Commit**

---

### Task 6.2: Preview Mode — Stage Integration

**Files:**
- Modify: `resources/js/store/modules/preview.js`
- Modify: `resources/js/components/Preview/PersonaSelector.vue`

- [ ] **Step 1: Auto-set stage from persona on preview login**

When a persona is selected, dispatch `lifeStage/setStageFromPersona` to set the stage from `PERSONA_TO_STAGE` mapping.

- [ ] **Step 2: Group personas by stage in PersonaSelector**

Render personas grouped under stage headings instead of a flat list.

- [ ] **Step 3: Commit**

---

### Task 6.3: Mobile Dashboard Adaptation

**Files:**
- Modify: `resources/js/mobile/views/MobileDashboard.vue`
- Modify: `resources/js/mobile/MobileTabBar.vue`

- [ ] **Step 1: Add journey progress to mobile dashboard**

Compact version of JourneyProgressHero at top of mobile home screen.

- [ ] **Step 2: Stage-curate mobile module cards**

Filter `ModuleSummaryCard` grid based on `lifeStage/dashboardCards`.

- [ ] **Step 3: Adapt tab bar**

Map top 4 stage-relevant modules to tabs. "More" tab for everything else.

- [ ] **Step 4: Commit**

---

### Task 6.4: Final — Reseed & Verify

- [ ] **Step 1: Run full database seed**

```bash
php artisan db:seed
```

- [ ] **Step 2: Test all 6 preview personas**

Verify each persona shows the correct stage-adapted experience (sidebar, dashboard, card selection).

- [ ] **Step 3: Run full test suite**

```bash
./vendor/bin/pest
```

- [ ] **Step 4: Commit any final fixes**

---

## Appendix: Phase Dependencies

```
Phase 1: Foundation (no dependencies)
  ├── Phase 2: Unified Forms (depends on Phase 1)
  ├── Phase 3: Adaptive Sidebar (depends on Phase 1)
  │     └── Phase 5: Dashboard (depends on Phase 1, 3)
  │           └── Phase 6: Landing + Mobile (depends on Phase 1, 3, 5)
  └── Phase 4: Onboarding (depends on Phase 1, 2)
```

Phases 2 and 3 can be developed in parallel after Phase 1 is complete.
Phases 4 and 5 can be developed in parallel after their dependencies are met.
Phase 6 is the final integration phase.
