# Admin Panel Enhancement Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enhance the admin panel with a visual decision tree matrix for all 6 modules, enhanced user management with granular module/step tracking, and verified database backup functionality.

**Architecture:** New generic `ActionDefinitionController` replaces the per-module duplication pattern. Single `ActionDefinitionDrawer.vue` component serves all 6 modules via config objects. `UserModuleTrackingService` aggregates data from existing models (no new tables). All UI follows `fynlaDesignGuide.md` v1.2.0.

**Tech Stack:** Laravel 10, Vue.js 3, Vuex, Pest testing, MySQL 8

**Spec:** `docs/superpowers/specs/2026-03-17-admin-advisor-design.md` (Feature 1)

**Worktree:** `feature/admin-enhancement`

**Design guide:** `fynlaDesignGuide.md` v1.2.0 — MUST be read before any UI work.

---

## File Map

### New Files

| File | Responsibility |
|------|---------------|
| `app/Models/EstateActionDefinition.php` | Estate action definition model (identical structure to other 5) |
| `database/migrations/2026_03_17_100001_create_estate_action_definitions_table.php` | Estate action definitions table |
| `database/seeders/EstateActionDefinitionSeeder.php` | Seed estate-specific action definitions |
| `app/Services/Estate/EstateActionDefinitionService.php` | Evaluates estate action definitions against user data (mirrors TaxActionDefinitionService pattern) |
| `app/Http/Controllers/Api/ActionDefinitionController.php` | Generic CRUD for all 6 module action definitions |
| `app/Http/Requests/StoreActionDefinitionRequest.php` | Single validation class for all modules |
| `app/Services/Admin/UserModuleTrackingService.php` | Aggregates module status per user across all models |
| `resources/js/components/Admin/DecisionMatrix.vue` | Container with module sub-tabs |
| `resources/js/components/Admin/DecisionTree.vue` | Tree visualisation for one module |
| `resources/js/components/Admin/DecisionNode.vue` | Individual node component |
| `resources/js/components/Admin/ActionDefinitionDrawer.vue` | Single side drawer edit panel for ALL modules |
| `resources/js/components/Admin/TriggerConfigEditor.vue` | Condition builder UI |
| `resources/js/components/Admin/UserModuleStatus.vue` | Module dots + expandable detail |
| `resources/js/components/Admin/UserOnboardingProgress.vue` | Onboarding progress card |
| `resources/js/constants/moduleConfigs.js` | Module-specific enum configs for the drawer |
| `resources/js/services/actionDefinitionService.js` | API wrapper for generic action definition endpoints |
| `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php` | Tests for module tracking |
| `tests/Feature/Api/ActionDefinitionControllerTest.php` | Tests for generic CRUD controller |
| `tests/Feature/Api/AdminBackupTest.php` | Tests for backup functionality |

### Modified Files

| File | Change |
|------|--------|
| `routes/api.php` (lines 1003-1064) | Add generic action-definitions routes and decision-matrix route |
| `resources/js/views/Admin/AdminPanel.vue` (lines 102-131) | Add "Decision Matrix" tab |
| `resources/js/components/Admin/UserManagement.vue` (lines 46-219) | Add module status dots column, expandable rows |
| `resources/js/services/adminService.js` | Add `getUserModuleStatus(userId)` method |

---

## Task 1: EstateActionDefinition Model & Migration

**Files:**
- Create: `database/migrations/2026_03_17_100001_create_estate_action_definitions_table.php`
- Create: `app/Models/EstateActionDefinition.php`
- Reference: `app/Models/ProtectionActionDefinition.php` (copy structure exactly)
- Reference: `database/migrations/2026_03_05_000002_create_protection_action_definitions_table.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_estate_action_definitions_table
```

The migration must match the exact schema of the other 5 action definition tables. Reference the protection migration for column definitions: `key` (string 50, unique), `source` (string 20), `title_template` (string), `description_template` (text), `action_template` (string nullable), `category` (string 50), `priority` (enum critical/high/medium/low), `scope` (enum account/portfolio), `what_if_impact_type` (string 30), `trigger_config` (json), `is_enabled` (boolean default true), `sort_order` (smallInteger unsigned default 100), `notes` (string nullable), timestamps. Add indexes on `source`, `is_enabled`, `sort_order`.

- [ ] **Step 2: Create model**

Create `app/Models/EstateActionDefinition.php`. Copy `ProtectionActionDefinition.php` exactly — same `$fillable`, `$casts`, scopes (`scopeEnabled`, `scopeBySource`), static methods (`findByKey`, `getEnabled`, `getEnabledBySource`), and template rendering methods (`renderTitle`, `renderDescription`, `renderAction`, private `renderTemplate`). Only change the class name and table name.

- [ ] **Step 3: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 4: Verify model works**

```bash
php artisan tinker --execute="echo \App\Models\EstateActionDefinition::count();"
```

Expected: `0` (no seeded data yet)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*estate_action_definitions* app/Models/EstateActionDefinition.php
git commit -m "feat: add EstateActionDefinition model and migration"
```

---

## Task 2: Estate Action Definition Seeder

**Files:**
- Create: `database/seeders/EstateActionDefinitionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (line 67, after TaxActionDefinitionSeeder)
- Reference: `database/seeders/TaxActionDefinitionSeeder.php` (for structure pattern)

- [ ] **Step 1: Create seeder**

Create `database/seeders/EstateActionDefinitionSeeder.php` using `updateOrCreate` on `key` field (so it's safe to re-run). Follow the exact pattern of `TaxActionDefinitionSeeder.php`: `run()` iterates `getDefinitions()` array, calling `EstateActionDefinition::updateOrCreate(['key' => $def['key']], $def)`.

Seed these 8 estate-specific action definitions:

```php
private function getDefinitions(): array
{
    return [
        // ── Will ─────────────────────────────────────────────────────
        [
            'key' => 'no_will',
            'source' => 'agent',
            'title_template' => 'No Will in Place',
            'description_template' => 'There is no will recorded for this estate. Without a valid will, assets will be distributed according to intestacy rules, which may not reflect the individual\'s wishes.',
            'action_template' => 'Arrange a will with a solicitor to ensure assets are distributed according to your wishes.',
            'category' => 'Will',
            'priority' => 'critical',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'estate_protection',
            'trigger_config' => ['condition' => 'no_will'],
            'is_enabled' => true,
            'sort_order' => 10,
            'notes' => 'Triggers when user has no will record.',
        ],

        // ── Trust ────────────────────────────────────────────────────
        [
            'key' => 'policy_not_in_trust',
            'source' => 'agent',
            'title_template' => 'Life Policy Not Held in Trust',
            'description_template' => 'A life insurance policy worth {policy_value} is not held in trust. Policies outside of a trust form part of the taxable estate and may be subject to Inheritance Tax at 40%.',
            'action_template' => 'Consider placing this policy into a trust to remove it from the taxable estate. This is usually straightforward and incurs no immediate tax charge.',
            'category' => 'Trust',
            'priority' => 'high',
            'scope' => 'account',
            'what_if_impact_type' => 'iht_reduction',
            'trigger_config' => ['condition' => 'policy_not_in_trust'],
            'is_enabled' => true,
            'sort_order' => 20,
            'notes' => 'Triggers when life policy value exceeds nil-rate band and policy is not in trust.',
        ],

        // ── Inheritance Tax ──────────────────────────────────────────
        [
            'key' => 'iht_exceeds_nrb',
            'source' => 'agent',
            'title_template' => 'Estate Value Exceeds Nil-Rate Band',
            'description_template' => 'The estimated estate value of {estate_value} exceeds the nil-rate band of {nrb}. The excess of {excess_amount} may be subject to Inheritance Tax at 40%, resulting in an estimated liability of {iht_liability}.',
            'action_template' => 'Review estate planning strategies such as gifting, trust arrangements, or charitable giving to reduce the potential Inheritance Tax liability.',
            'category' => 'Inheritance Tax',
            'priority' => 'high',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'iht_reduction',
            'trigger_config' => ['condition' => 'iht_exceeds_nrb'],
            'is_enabled' => true,
            'sort_order' => 30,
            'notes' => 'Triggers when total estate value exceeds available nil-rate band (NRB + RNRB if applicable).',
        ],

        // ── Lasting Power of Attorney ────────────────────────────────
        [
            'key' => 'no_lpa',
            'source' => 'agent',
            'title_template' => 'No Lasting Power of Attorney (Financial)',
            'description_template' => 'No financial Lasting Power of Attorney is recorded. Without one, managing financial affairs could require a costly and time-consuming Court of Protection application if you lose mental capacity.',
            'action_template' => 'Consider setting up a Property and Financial Affairs Lasting Power of Attorney while you have capacity.',
            'category' => 'Lasting Power of Attorney',
            'priority' => 'high',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'estate_protection',
            'trigger_config' => ['condition' => 'no_lpa'],
            'is_enabled' => true,
            'sort_order' => 40,
            'notes' => 'Triggers when user has no financial LPA record.',
        ],
        [
            'key' => 'no_lpa_health',
            'source' => 'agent',
            'title_template' => 'No Lasting Power of Attorney (Health)',
            'description_template' => 'No health and welfare Lasting Power of Attorney is recorded. This means medical and care decisions may default to healthcare professionals rather than a trusted person of your choosing.',
            'action_template' => 'Consider setting up a Health and Welfare Lasting Power of Attorney to appoint someone you trust to make decisions about your care.',
            'category' => 'Lasting Power of Attorney',
            'priority' => 'medium',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'estate_protection',
            'trigger_config' => ['condition' => 'no_lpa_health'],
            'is_enabled' => true,
            'sort_order' => 50,
            'notes' => 'Triggers when user has no health/welfare LPA record.',
        ],

        // ── Gifts ────────────────────────────────────────────────────
        [
            'key' => 'gifts_pet_window',
            'source' => 'agent',
            'title_template' => 'Gifts Within Seven-Year Window',
            'description_template' => '{gift_count} gift(s) totalling {gift_total} are within the seven-year Potentially Exempt Transfer window. If the individual were to pass away within this period, these gifts may be subject to taper relief and could affect the Inheritance Tax calculation.',
            'action_template' => 'Maintain records of all gifts and review the potential taper relief implications with your adviser.',
            'category' => 'Inheritance Tax',
            'priority' => 'medium',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'iht_reduction',
            'trigger_config' => ['condition' => 'gifts_pet_window'],
            'is_enabled' => true,
            'sort_order' => 60,
            'notes' => 'Triggers when user has gifts within the 7-year PET window.',
        ],

        // ── Trust Review ─────────────────────────────────────────────
        [
            'key' => 'trust_review_due',
            'source' => 'agent',
            'title_template' => 'Trust Arrangement Review Due',
            'description_template' => 'The trust "{trust_name}" was last reviewed on {last_review_date}. Regular review ensures the trust still meets its objectives and complies with current legislation.',
            'action_template' => 'Schedule a review of this trust arrangement with your solicitor or trustee.',
            'category' => 'Trust',
            'priority' => 'medium',
            'scope' => 'account',
            'what_if_impact_type' => 'estate_protection',
            'trigger_config' => ['condition' => 'trust_review_due', 'months_threshold' => 12],
            'is_enabled' => true,
            'sort_order' => 70,
            'notes' => 'Triggers when a trust has not been reviewed in 12+ months.',
        ],

        // ── Beneficiaries ────────────────────────────────────────────
        [
            'key' => 'beneficiary_review',
            'source' => 'agent',
            'title_template' => 'Beneficiary Designations Review',
            'description_template' => 'Beneficiary designations on pension and insurance policies should be reviewed periodically to ensure they reflect current wishes, especially after life events such as marriage, divorce, or the birth of a child.',
            'action_template' => 'Review nomination of beneficiaries on all pension schemes and insurance policies.',
            'category' => 'Beneficiaries',
            'priority' => 'low',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'estate_protection',
            'trigger_config' => ['condition' => 'beneficiary_review'],
            'is_enabled' => true,
            'sort_order' => 80,
            'notes' => 'Periodic reminder to review beneficiary designations.',
        ],
    ];
}
```

- [ ] **Step 2: Register seeder in DatabaseSeeder.php**

Add `$this->call(EstateActionDefinitionSeeder::class);` to the `run()` method in `database/seeders/DatabaseSeeder.php`.

- [ ] **Step 3: Run seeder**

```bash
php artisan db:seed --class=EstateActionDefinitionSeeder --force
```

- [ ] **Step 4: Verify**

```bash
php artisan tinker --execute="echo \App\Models\EstateActionDefinition::count();"
```

Expected: `8`

- [ ] **Step 5: Create EstateActionDefinitionService**

Create `app/Services/Estate/EstateActionDefinitionService.php`. Mirrors `TaxActionDefinitionService` pattern exactly:
- Uses `FormatsCurrency` and `StructuredLogging` traits
- Constructor injects `TaxConfigService`
- `evaluateActions(User $user): array` — iterates enabled `EstateActionDefinition` records, calls private evaluator per condition
- Returns `['recommendations' => [...], 'total_count' => int, 'high_priority_count' => int]`

Private evaluator methods for each of the 8 seeded conditions:
- `evaluateNoWill()` — checks if user has a will record
- `evaluatePolicyNotInTrust()` — checks life policies not in trust vs NRB
- `evaluateIhtExceedsNrb()` — checks total estate value vs nil-rate band (from `TaxConfigService`)
- `evaluateNoLpa()` — checks for financial LPA record
- `evaluateNoLpaHealth()` — checks for health/welfare LPA record
- `evaluateGiftsPetWindow()` — checks gifts within 7-year PET window
- `evaluateTrustReviewDue()` — checks trust last review date > 12 months
- `evaluateBeneficiaryReview()` — periodic reminder

Reference `app/Services/Tax/TaxActionDefinitionService.php` for exact structure. Each evaluator returns an array of recommendations or empty array.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/EstateActionDefinitionSeeder.php database/seeders/DatabaseSeeder.php app/Services/Estate/EstateActionDefinitionService.php
git commit -m "feat: seed estate action definitions (8 definitions) and evaluation service"
```

---

## Task 3: Generic ActionDefinitionController

**Files:**
- Create: `app/Http/Controllers/Api/ActionDefinitionController.php`
- Create: `app/Http/Requests/StoreActionDefinitionRequest.php`
- Modify: `routes/api.php` (after line 1064)
- Reference: `app/Http/Controllers/Api/ProtectionActionDefinitionController.php` (exact CRUD pattern to follow)
- Reference: `app/Http/Requests/StoreProtectionActionDefinitionRequest.php` (validation pattern)
- Test: `tests/Feature/Api/ActionDefinitionControllerTest.php`

**Note:** Existing per-module routes use `PUT` for update. New generic routes use `PATCH` per spec. Both are valid — existing routes are retained, generic routes are additive.

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Api/ActionDefinitionControllerTest.php`. Test the generic endpoints for all 6 modules. Key test cases:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\ProtectionActionDefinition;
use App\Models\EstateActionDefinition;
use App\Models\TaxActionDefinition;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    // Assign admin role via RolesPermissionsSeeder pattern
    $adminRole = \App\Models\Role::where('slug', 'admin')->first();
    if ($adminRole) {
        $this->admin->role_id = $adminRole->id;
        $this->admin->save();
    }
});

it('lists action definitions for a valid module', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/action-definitions/protection')
        ->assertOk()
        ->assertJsonStructure(['success', 'data']);
});

it('returns 422 for invalid module parameter', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/action-definitions/invalid')
        ->assertStatus(422);
});

it('creates an action definition via generic endpoint', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/admin/action-definitions/estate', [
            'key' => 'test_estate_action',
            'source' => 'agent',
            'title_template' => 'Test Title',
            'description_template' => 'Test description',
            'category' => 'Test',
            'priority' => 'medium',
            'scope' => 'portfolio',
            'what_if_impact_type' => 'estate_protection',
            'trigger_config' => ['condition' => 'test'],
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('estate_action_definitions', ['key' => 'test_estate_action']);
});

it('updates an action definition via generic endpoint', function () {
    $def = ProtectionActionDefinition::first();
    $this->actingAs($this->admin)
        ->patchJson("/api/admin/action-definitions/protection/{$def->id}", [
            'key' => $def->key,
            'source' => $def->source,
            'title_template' => 'Updated Title',
            'description_template' => $def->description_template,
            'category' => $def->category,
            'priority' => $def->priority,
            'scope' => $def->scope,
            'what_if_impact_type' => $def->what_if_impact_type,
            'trigger_config' => $def->trigger_config,
        ])
        ->assertOk()
        ->assertJsonPath('data.title_template', 'Updated Title');
});

it('toggles enabled state via generic endpoint', function () {
    $def = ProtectionActionDefinition::first();
    $original = $def->is_enabled;
    $this->actingAs($this->admin)
        ->patchJson("/api/admin/action-definitions/protection/{$def->id}/toggle")
        ->assertOk();

    expect($def->fresh()->is_enabled)->toBe(!$original);
});

it('deletes an action definition via generic endpoint', function () {
    // Create directly (not all action definition models have factories)
    $def = TaxActionDefinition::create([
        'key' => 'test_delete_target',
        'source' => 'agent',
        'title_template' => 'Delete Me',
        'description_template' => 'Test',
        'category' => 'Test',
        'priority' => 'low',
        'scope' => 'portfolio',
        'what_if_impact_type' => 'tax_optimisation',
        'trigger_config' => ['condition' => 'test'],
        'is_enabled' => true,
        'sort_order' => 999,
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/admin/action-definitions/tax/{$def->id}")
        ->assertOk();

    $this->assertDatabaseMissing('tax_action_definitions', ['id' => $def->id]);
});

it('returns decision matrix data for a module', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/admin/decision-matrix/protection')
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'module',
                'stats' => ['total', 'enabled', 'disabled', 'critical_high', 'medium'],
                'categories' => [
                    '*' => [
                        'name',
                        'definitions' => [
                            '*' => [
                                'id', 'key', 'title_template', 'description_template',
                                'category', 'priority', 'is_enabled', 'trigger_config',
                                'tree_nodes',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
});

it('requires admin permission for action definition endpoints', function () {
    $user = User::factory()->create(); // non-admin
    $this->actingAs($user)
        ->getJson('/api/admin/action-definitions/protection')
        ->assertStatus(403);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Feature/Api/ActionDefinitionControllerTest.php
```

Expected: All FAIL (routes and controller don't exist yet)

- [ ] **Step 3: Create StoreActionDefinitionRequest**

Create `app/Http/Requests/StoreActionDefinitionRequest.php`. Key patterns:

1. **Dynamic table resolution for unique key rule** — the `{module}` route parameter maps to a table name:

```php
private const MODULE_TABLES = [
    'protection' => 'protection_action_definitions',
    'savings' => 'savings_action_definitions',
    'investment' => 'investment_action_definitions',
    'retirement' => 'retirement_action_definitions',
    'estate' => 'estate_action_definitions',
    'tax' => 'tax_action_definitions',
];

private const MODULE_SOURCES = [
    'protection' => ['agent', 'gap'],
    'savings' => ['agent', 'goal'],
    'investment' => ['agent', 'goal'],
    'retirement' => ['agent', 'goal'],
    'estate' => ['agent', 'goal'],
    'tax' => ['agent', 'goal'],
];

private const MODULE_IMPACT_TYPES = [
    'protection' => ['coverage_increase', 'gap_reduction', 'default'],
    'savings' => ['savings_increase', 'rate_improvement', 'default'],
    'investment' => ['fee_reduction', 'savings_increase', 'contribution', 'tax_optimisation', 'default'],
    'retirement' => ['contribution', 'consolidation', 'tax_optimisation', 'default'],
    'estate' => ['iht_reduction', 'estate_protection', 'default'],
    'tax' => ['tax_optimisation', 'allowance_utilisation', 'default'],
];
```

2. **`authorize()`** — same pattern as `StoreProtectionActionDefinitionRequest`: checks `Permission::ADMIN_ACCESS` via `PermissionService`.

3. **`rules()`** — reads `$this->route('module')` to select the correct table, sources, and impact types:

```php
public function rules(): array
{
    $module = $this->route('module');
    $table = self::MODULE_TABLES[$module] ?? 'protection_action_definitions';
    $sources = self::MODULE_SOURCES[$module] ?? ['agent'];
    $impacts = self::MODULE_IMPACT_TYPES[$module] ?? ['default'];

    $uniqueKeyRule = $this->route('id')
        ? Rule::unique($table, 'key')->ignore($this->route('id'))
        : Rule::unique($table, 'key');

    return [
        'key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', $uniqueKeyRule],
        'source' => ['required', 'string', Rule::in($sources)],
        'title_template' => ['required', 'string', 'max:255'],
        'description_template' => ['required', 'string', 'max:2000'],
        'action_template' => ['nullable', 'string', 'max:255'],
        'category' => ['required', 'string', 'max:50'],
        'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
        'scope' => ['required', Rule::in(['account', 'portfolio'])],
        'what_if_impact_type' => ['required', Rule::in($impacts)],
        'trigger_config' => ['required', 'array'],
        'trigger_config.condition' => ['required', 'string'],
        'is_enabled' => ['sometimes', 'boolean'],
        'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        'notes' => ['nullable', 'string', 'max:255'],
    ];
}
```

4. **`messages()`** — same as `StoreProtectionActionDefinitionRequest`.

- [ ] **Step 4: Create ActionDefinitionController**

Create `app/Http/Controllers/Api/ActionDefinitionController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActionDefinitionRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\EstateActionDefinition;
use App\Models\InvestmentActionDefinition;
use App\Models\ProtectionActionDefinition;
use App\Models\RetirementActionDefinition;
use App\Models\SavingsActionDefinition;
use App\Models\TaxActionDefinition;
use Illuminate\Http\JsonResponse;

class ActionDefinitionController extends Controller
{
    use SanitizedErrorResponse;

    private const ALLOWED_MODULES = [
        'protection' => ProtectionActionDefinition::class,
        'savings' => SavingsActionDefinition::class,
        'investment' => InvestmentActionDefinition::class,
        'retirement' => RetirementActionDefinition::class,
        'estate' => EstateActionDefinition::class,
        'tax' => TaxActionDefinition::class,
    ];

    // resolveModel() — static array lookup, returns model class or aborts 422
    private function resolveModel(string $module): string
    {
        if (!isset(self::ALLOWED_MODULES[$module])) {
            abort(422, 'Invalid module: ' . $module);
        }
        return self::ALLOWED_MODULES[$module];
    }

    // index() — identical to ProtectionActionDefinitionController::index()
    // but uses $modelClass::orderBy('sort_order')->orderBy('id')->get()

    // show(), store(), update(), destroy(), toggleEnabled()
    // — all identical to ProtectionActionDefinitionController pattern
    // — replace ProtectionActionDefinition with resolved $modelClass
    // — store() uses StoreActionDefinitionRequest (not per-module request)

    // decisionMatrix() — returns structured data for tree rendering:
    public function decisionMatrix(string $module): JsonResponse
    {
        $modelClass = $this->resolveModel($module);

        try {
            $definitions = $modelClass::orderBy('sort_order')->orderBy('id')->get();

            // Group by category
            $categories = $definitions->groupBy('category')->map(function ($defs, $name) {
                return [
                    'name' => $name,
                    'definitions' => $defs->map(function ($def) {
                        return array_merge($def->toArray(), [
                            'tree_nodes' => $this->buildTreeNodes($def),
                        ]);
                    })->values(),
                ];
            })->values();

            // Stats
            $stats = [
                'total' => $definitions->count(),
                'enabled' => $definitions->where('is_enabled', true)->count(),
                'disabled' => $definitions->where('is_enabled', false)->count(),
                'critical_high' => $definitions->whereIn('priority', ['critical', 'high'])->count(),
                'medium' => $definitions->where('priority', 'medium')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'module' => $module,
                    'stats' => $stats,
                    'categories' => $categories,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch decision matrix', $e);
        }
    }

    // Converts trigger_config into the 4-column tree node structure
    private function buildTreeNodes(object $definition): array
    {
        $config = $definition->trigger_config ?? [];
        return [
            'data' => [
                'type' => 'data',
                'label' => $config['condition'] ?? 'Unknown',
                'description' => 'Reads user data for ' . ($config['condition'] ?? 'condition'),
            ],
            'trigger' => [
                'type' => 'trigger',
                'label' => $config['condition'] ?? 'Unknown',
                'description' => isset($config['threshold'])
                    ? "Threshold: {$config['threshold']}"
                    : 'Boolean condition',
            ],
            'logic' => [
                'type' => 'logic',
                'label' => $definition->what_if_impact_type ?? 'default',
                'description' => $definition->scope ?? 'portfolio',
            ],
            'outcome' => [
                'type' => 'outcome',
                'label' => $definition->title_template,
                'description' => $definition->category,
            ],
        ];
    }
}
```

- [ ] **Step 5: Add routes**

Add to `routes/api.php` after line 1064 (after the protection-actions group):

```php
// Generic action definition routes (for Decision Matrix)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])
    ->prefix('admin/action-definitions/{module}')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'store']);
        Route::patch('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'destroy']);
        Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'toggleEnabled']);
    });

Route::middleware(['auth:sanctum', 'permission:admin.access'])
    ->get('admin/decision-matrix/{module}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'decisionMatrix']);
```

**Important:** Use fully-qualified controller class references (not `->controller()` shorthand) to match the existing route style in this file.

- [ ] **Step 6: Run tests to verify they pass**

```bash
./vendor/bin/pest tests/Feature/Api/ActionDefinitionControllerTest.php
```

Expected: All PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/ActionDefinitionController.php app/Http/Requests/StoreActionDefinitionRequest.php routes/api.php tests/Feature/Api/ActionDefinitionControllerTest.php
git commit -m "feat: generic ActionDefinitionController for all 6 modules"
```

---

## Task 4: UserModuleTrackingService

**Files:**
- Create: `app/Services/Admin/UserModuleTrackingService.php`
- Modify: `app/Http/Controllers/Api/AdminController.php` (after line 310)
- Modify: `routes/api.php` (inside existing admin group, before backup routes ~line 1025)
- Reference: `app/Models/User.php` (for relationship names)
- Test: `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Admin\UserModuleTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->service = new UserModuleTrackingService();
});

it('returns complete status for user with all protection data', function () {
    $user = User::factory()->create();
    // Create at least one of each protection sub-area
    \App\Models\LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);
    \App\Models\CriticalIllnessPolicy::factory()->create(['user_id' => $user->id]);
    \App\Models\IncomeProtectionPolicy::factory()->create(['user_id' => $user->id]);

    $result = $this->service->getModuleStatus($user);

    expect($result['protection']['status'])->toBe('complete');
    expect($result['protection']['sub_areas']['life_insurance']['count'])->toBe(1);
});

it('returns partial status for user with some data', function () {
    $user = User::factory()->create();
    \App\Models\LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);
    // No other protection types

    $result = $this->service->getModuleStatus($user);

    expect($result['protection']['status'])->toBe('partial');
});

it('returns empty status for user with no data', function () {
    $user = User::factory()->create();
    $result = $this->service->getModuleStatus($user);

    expect($result['protection']['status'])->toBe('empty');
    expect($result['savings']['status'])->toBe('empty');
    expect($result['investment']['status'])->toBe('empty');
    expect($result['retirement']['status'])->toBe('empty');
    expect($result['estate']['status'])->toBe('empty');
});

it('returns correct sub-area counts and values', function () {
    $user = User::factory()->create();
    \App\Models\LifeInsurancePolicy::factory()->count(3)->create([
        'user_id' => $user->id,
        'cover_amount' => 100000,
    ]);

    $result = $this->service->getModuleStatus($user);

    expect($result['protection']['sub_areas']['life_insurance']['count'])->toBe(3);
    expect($result['protection']['sub_areas']['life_insurance']['total_cover'])->toBe(300000.0);
});

it('returns onboarding data', function () {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'life_stage' => 'young_family',
    ]);

    $result = $this->service->getModuleStatus($user);

    expect($result['onboarding']['completed'])->toBeTrue();
    expect($result['onboarding']['life_stage'])->toBe('young_family');
});

it('handles user with no relationships loaded', function () {
    $user = User::factory()->create();
    $freshUser = User::find($user->id); // No eager-loaded relations

    $result = $this->service->getModuleStatus($freshUser);

    expect($result)->toHaveKeys(['protection', 'savings', 'investment', 'retirement', 'estate', 'onboarding']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/pest tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php
```

- [ ] **Step 3: Implement UserModuleTrackingService**

Create `app/Services/Admin/UserModuleTrackingService.php` with a single public method `getModuleStatus(User $user): array`.

The method eager-loads all module relationships in one call:
```php
$user->loadMissing([
    // Protection
    'lifeInsurancePolicies', 'criticalIllnessPolicies',
    'incomeProtectionPolicies', 'disabilityPolicies', 'sicknessIllnessPolicies',
    // Savings
    'cashAccounts', 'personalAccounts', 'savingsAccounts',
    // Investment
    'investmentAccounts.holdings',
    // Retirement
    'dcPensions', 'dbPensions', 'statePension', 'retirementProfile',
    // Estate
    'trusts', 'assets', 'gifts', 'lastingPowersOfAttorney',
    'properties', 'mortgages', 'liabilities',
    // Onboarding
    'onboardingProgress',
]);
```

**Module status determination per module — "key sub-areas" definition:**

| Module | Key Sub-Areas (ALL needed for `complete`) |
|--------|------------------------------------------|
| Protection | `lifeInsurancePolicies`, `criticalIllnessPolicies`, `incomeProtectionPolicies` |
| Savings | `cashAccounts` OR `savingsAccounts` (any account), ISA accounts (filtered from savingsAccounts where type=isa) |
| Investment | `investmentAccounts` (at least one), risk profile set (via `investmentAccounts.riskProfile`) |
| Retirement | `dcPensions` OR `dbPensions` (at least one pension), `statePension` OR `retirementProfile` |
| Estate | Will (exists/not exists — check for will record), `lastingPowersOfAttorney`, `trusts` OR `assets` |

Status rules:
- `complete`: All key sub-areas have at least one record
- `partial`: Some key sub-areas have records, but not all
- `empty`: No records in any sub-area
- `skipped`: User's `journey_states` JSON has this module marked as `skipped`

Returns structure:
```php
[
    'protection' => [
        'status' => 'complete|partial|empty|skipped',
        'sub_areas' => [
            'life_insurance' => ['count' => 2, 'total_cover' => 500000],
            'critical_illness' => ['count' => 1],
            'income_protection' => ['count' => 0],
            'disability' => ['count' => 0],
            'sickness_illness' => ['count' => 0],
        ],
    ],
    'savings' => [
        'status' => 'complete|partial|empty|skipped',
        'sub_areas' => [
            'cash_accounts' => ['count' => 1, 'total_balance' => 25000],
            'savings_accounts' => ['count' => 2, 'total_balance' => 50000],
            'isa_accounts' => ['count' => 1],
            'emergency_fund' => ['exists' => true],
        ],
    ],
    'investment' => [
        'status' => 'complete|partial|empty|skipped',
        'sub_areas' => [
            'investment_accounts' => ['count' => 1, 'total_value' => 120000],
            'holdings' => ['count' => 5],
            'risk_profile' => ['exists' => true],
            'investment_goals' => ['count' => 2],
        ],
    ],
    'retirement' => [
        'status' => 'complete|partial|empty|skipped',
        'sub_areas' => [
            'dc_pensions' => ['count' => 1, 'total_fund_value' => 80000],
            'db_pensions' => ['count' => 0],
            'state_pension' => ['exists' => true],
            'retirement_profile' => ['exists' => false],
        ],
    ],
    'estate' => [
        'status' => 'complete|partial|empty|skipped',
        'sub_areas' => [
            'will' => ['exists' => true],
            'lasting_powers_of_attorney' => ['count' => 1],
            'trusts' => ['count' => 0, 'total_value' => 0],
            'gifts' => ['count' => 3],
            'assets' => ['count' => 2],
        ],
    ],
    'onboarding' => [
        'completed' => true,
        'started_at' => '2025-06-15T10:00:00Z',
        'completed_at' => '2025-06-15T10:30:00Z',
        'life_stage' => 'young_family',
        'life_stage_completed_steps' => ['protection', 'savings'],
        'journey_states' => [...],
        'journey_selections' => [...],
        'progress_records' => 5, // count from OnboardingProgress model
    ],
]
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/pest tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php
```

- [ ] **Step 5: Add API endpoint**

Add `moduleStatus` method to `AdminController.php` (after line 310, after `getRoles()`):

```php
public function moduleStatus(int $id, UserModuleTrackingService $service): JsonResponse
{
    $user = User::findOrFail($id);
    return response()->json([
        'success' => true,
        'data' => $service->getModuleStatus($user),
    ]);
}
```

Add import at top of `AdminController.php`:
```php
use App\Services\Admin\UserModuleTrackingService;
```

Add route inside the existing admin middleware group in `routes/api.php` (inside the `Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')` group, ~line 1010):

```php
Route::get('users/{id}/module-status', [\App\Http\Controllers\Api\AdminController::class, 'moduleStatus']);
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/Admin/UserModuleTrackingService.php tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php app/Http/Controllers/Api/AdminController.php routes/api.php
git commit -m "feat: UserModuleTrackingService for per-user module status"
```

---

## Task 5: Module Config Constants (Frontend)

**Files:**
- Create: `resources/js/constants/moduleConfigs.js`
- Create: `resources/js/services/actionDefinitionService.js`

- [ ] **Step 1: Create module configs**

Create `resources/js/constants/moduleConfigs.js` containing `MODULE_CONFIGS` object with per-module configuration for the drawer component. Each module config includes: `label`, `sourceOptions`, `whatIfImpactOptions`, `conditionOptions` (array of `{value, label}` objects), and `triggerFields` (which conditional fields to show for each condition).

Populate the condition options by reading the existing modal components:
- Protection conditions: Read from `ProtectionActionModal.vue` lines 347-356
- Investment conditions: Read from `InvestmentActionModal.vue`
- Retirement conditions: Read from `RetirementActionModal.vue`
- Savings/Tax/Estate: Derive from corresponding service files

- [ ] **Step 2: Create action definition API service**

Create `resources/js/services/actionDefinitionService.js`. **Important:** Import `api` from `./api` (not raw `axios`) — this is the project's API wrapper with CSRF, auth token, retry logic, and preview mode detection.

```javascript
import api from './api';

export default {
    getDecisionMatrix(module) { return api.get(`/admin/decision-matrix/${module}`); },
    getDefinitions(module) { return api.get(`/admin/action-definitions/${module}`); },
    getDefinition(module, id) { return api.get(`/admin/action-definitions/${module}/${id}`); },
    createDefinition(module, data) { return api.post(`/admin/action-definitions/${module}`, data); },
    updateDefinition(module, id, data) { return api.patch(`/admin/action-definitions/${module}/${id}`, data); },
    deleteDefinition(module, id) { return api.delete(`/admin/action-definitions/${module}/${id}`); },
    toggleDefinition(module, id) { return api.patch(`/admin/action-definitions/${module}/${id}/toggle`); },
};
```

Note: Paths are relative (e.g. `/admin/...` not `/api/admin/...`) because the `api` instance has the base URL pre-configured.

- [ ] **Step 3: Commit**

```bash
git add resources/js/constants/moduleConfigs.js resources/js/services/actionDefinitionService.js
git commit -m "feat: module config constants and action definition API service"
```

---

## Task 6: Decision Tree Vue Components

**Files:**
- Create: `resources/js/components/Admin/DecisionMatrix.vue`
- Create: `resources/js/components/Admin/DecisionTree.vue`
- Create: `resources/js/components/Admin/DecisionNode.vue`
- Create: `resources/js/components/Admin/ActionDefinitionDrawer.vue`
- Create: `resources/js/components/Admin/TriggerConfigEditor.vue`
- Modify: `resources/js/views/Admin/AdminPanel.vue` (lines 102-131 tabs, lines 48-69 content)
- Reference: `March/March17Updates/Admin/decision-tree-mockup.html` (visual reference)
- Reference: `fynlaDesignGuide.md` (MUST read before starting)

**CRITICAL: Read `fynlaDesignGuide.md` before writing any component CSS/markup.** All colors, spacing, typography, borders, shadows, and component patterns must match exactly.

- [ ] **Step 1: Create DecisionNode.vue**

Smallest component first. Receives props: `type` (data|trigger|logic|outcome), `label`, `description`, `priority`, `disabled`, `selected`. Emits `click`.

**Node container** (per mockup `.node` class):
- `rounded-xl p-4 cursor-pointer border min-w-[180px] max-w-[220px] transition-all duration-150`
- Hover: `hover:shadow-md hover:-translate-y-px`
- Disabled: `opacity-45`

**Node type colours** (from mockup lines 95-98):
- data: `bg-light-blue-100 border-light-blue-500`
- trigger: `bg-violet-50 border-violet-200`
- logic: `bg-spring-50 border-spring-200`
- outcome: `bg-raspberry-50 border-raspberry-200`

**Node label**: `text-sm font-bold text-horizon-500 mb-1`
**Node description**: `text-xs text-neutral-500 leading-relaxed`

**Priority badge** (absolute positioned, per mockup `.node-badge`):
- Position: `absolute -top-2 -right-2`
- Style: `text-[10px] font-bold px-2 py-0.5 rounded-full text-white`
- CRIT: `bg-raspberry-700`, HIGH: `bg-raspberry-500`, MED: `bg-violet-500`, LOW: `bg-spring-500`, OFF: `bg-neutral-500`

- [ ] **Step 2: Create TriggerConfigEditor.vue**

Receives `modelValue` (the trigger_config object) and `moduleConfig` (condition options and trigger fields for the selected module). Emits `update:modelValue`.

**Container**: `bg-eggshell-500 rounded-lg p-3 mt-2` (per mockup `.trigger-config`)

**Each condition row**: `flex gap-2 items-center mb-2 text-[13px]`
- Field selectors: `px-2.5 py-1.5 border border-light-gray rounded-md bg-white text-[13px] text-horizon-500` (per mockup `.trigger-field`)
- Operators: `text-violet-500 font-bold text-xs min-w-[24px] text-center` (per mockup `.trigger-op`)
- **AND/OR combinators** between rows: same `.trigger-op` styling with `text-violet-500 font-bold`

Add/remove condition buttons for multi-condition support.

- [ ] **Step 3: Create ActionDefinitionDrawer.vue**

Single drawer component for ALL modules. Receives props: `definition` (object or null), `module` (string), `moduleConfig` (from moduleConfigs.js). Emits `save` and `close`.

**Drawer container** (per `fynlaDesignGuide.md` section 42 "Slide-in Panels" + mockup `.drawer-overlay`):
- Use `<Teleport to="body">` with `<Transition>` (enter: `translate-x-full opacity-0` → `translate-x-0 opacity-100`, 300ms ease-out; leave: 200ms ease-in)
- `fixed right-0 top-0 bottom-0 w-[420px] bg-white shadow-lg z-50 flex flex-col`

**Header** (per mockup `.drawer-header`):
- `p-6 border-b border-light-gray flex items-center justify-between`
- Title: `text-xl font-bold text-horizon-500`
- Key: `text-xs text-neutral-500 mt-0.5 font-mono` (JetBrains Mono — per mockup `.drawer-key`)
- Close button: `w-8 h-8 rounded-lg border border-light-gray bg-white text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100 transition-all duration-150`

**Body**: `p-6 flex-1 overflow-y-auto`

**Fields** (per `fynlaDesignGuide.md` section 8 "Forms & Inputs"):
- Labels: `block text-body-sm font-medium text-neutral-500 mb-1` (design guide Form Group pattern)
- Inputs: `px-4 py-2 border border-light-gray rounded-md text-horizon-500 focus:border-violet-500 focus:ring-violet-500/20 w-full`
- Selects: same as inputs with `bg-white`
- Textareas: same as inputs + `font-mono min-h-[80px] resize-y` (JetBrains Mono for templates)
- Field spacing: `mb-4`

**Status toggle** (per `fynlaDesignGuide.md` section 30 "Toggle Switch"):
- Use the exact design guide pattern: `.toggle` 44x24px, `.toggle-slider` with `horizon-300` off / `spring-500` on, knob 18x18px, `translateX(20px)` when checked
- Label next to toggle: `text-sm font-semibold` + `text-spring-500` when on / `text-neutral-500` when off

**Template variable tags** (per mockup `.var-tag`):
- `bg-violet-50 text-violet-700 text-xs px-2 py-0.5 rounded font-mono inline-block`
- Wrapped in `flex flex-wrap gap-1.5 mt-1`

**Footer** (per mockup `.drawer-footer`):
- `p-4 border-t border-light-gray flex gap-3 justify-end`
- Cancel: design guide secondary button pattern
- Save Changes: design guide primary button pattern

- [ ] **Step 4: Create DecisionTree.vue**

Receives `module` (string), `definitions` (array), `stats` (object). Emits `edit(definition)`.

**Stats bar** (per mockup `.stats-bar`, 5 cards):
- Container: `flex gap-4 mb-6`
- Each card: `bg-white border border-light-gray rounded-xl p-[18px_20px] flex-1 shadow-card` (design guide standard card)
- Value: `text-[28px] font-black text-horizon-500 tracking-tight` (900 weight per mockup `.stat-value`)
- Value colours: Total = `text-horizon-500`, Enabled = `text-spring-500`, Disabled = `text-neutral-500`, Critical/High = `text-raspberry-500`, Medium = `text-violet-500`
- Label: `text-xs text-neutral-500 mt-1`

**Header** (per mockup `.tree-header`):
- Title: `text-2xl font-bold text-horizon-500` — format: "{Module Name} Module — Decision Tree"
- Subtitle: `text-sm text-neutral-500 mt-0.5` — "Click any node to view and edit its configuration"
- Controls (right side): Search (btn-secondary + search icon SVG), Filter (btn-secondary + filter icon SVG), Collapse All (btn-secondary), + Add Action (btn-primary)

**Legend bar** (per mockup `.tree-legend`):
- `flex gap-5 mb-5 p-3 px-4 bg-savannah-100 rounded-lg border border-light-gray items-center`
- 5 legend items (left): User Data Input (`light-blue-500` dot), Trigger Condition (`violet-500`), Decision Logic (`spring-500`), Outcome/Action (`raspberry-500`), Disabled (`neutral-500`)
- Each: `flex items-center gap-1.5 text-xs text-neutral-500` with `w-3 h-3 rounded` colour dot
- Priority badges (right, `ml-auto`): CRIT/HIGH/MED/LOW pills using same badge classes as DecisionNode

**Tree canvas** (per mockup `.tree-canvas`):
- `bg-white border border-light-gray rounded-xl p-8 min-h-[520px] shadow-card overflow-x-auto`

**Column headers**:
- Container: `flex gap-0 mb-4`
- Each header: `min-w-[210px] px-3 text-center`
- Label: `text-[11px] font-bold uppercase tracking-wide text-neutral-500 bg-eggshell-500 rounded px-3 py-1.5 inline-block`
- Arrow spacer: `min-w-[60px]`

**Flow rows** (one per action definition):
- Container: `flex items-center mb-4`
- Each column: `min-w-[210px] px-3` containing a `<DecisionNode />`
- Arrow between columns: `min-w-[60px] flex items-center justify-center`
  - Enabled: `<svg width="40" height="2"><line x1="0" y1="1" x2="30" y2="1" stroke="var(--horizon-300)" stroke-width="2"/><polygon points="30,0 38,1 30,2" fill="var(--horizon-300)"/></svg>`
  - Disabled: same but `stroke="var(--light-gray)" stroke-dasharray="4,3"` and no arrowhead polygon

Clicking any node emits `edit` with the definition object → parent opens drawer.

- [ ] **Step 5: Create DecisionMatrix.vue**

Container component. Manages active module tab, data loading, and drawer state.

**Module sub-tabs** (per mockup `.module-tabs`):
- Container: `bg-white px-6 pt-3 flex gap-2`
- Each tab: `px-4 py-2 text-xs font-semibold rounded-t-lg border border-light-gray border-b-0 cursor-pointer transition-all duration-150`
- Active: `bg-eggshell-500 text-raspberry-500`
- Inactive: `bg-white text-neutral-500 hover:text-horizon-500 hover:bg-savannah-100`
- Count badge: `text-[10px] font-bold px-1.5 py-px rounded-full ml-1.5`
  - Active tab: `bg-raspberry-500 text-white`
  - Inactive tab: `bg-neutral-500 text-white`

6 tabs with labels: Protection, Cash & Savings, Investments, Retirement, Estate Planning, Tax.

Loads data from `actionDefinitionService.getDecisionMatrix(module)` on tab change. Passes `definitions`, `stats` to `<DecisionTree />`. Handles drawer open/close/save via `<ActionDefinitionDrawer />`.

- [ ] **Step 6: Replace per-module action tabs with Decision Matrix tab in AdminPanel.vue**

Modify `resources/js/views/Admin/AdminPanel.vue`:

The spec shows the final tab bar as: **Dashboard, User Management, Decision Matrix, Tax Settings, Database** (5 tabs). The 3 per-module action tabs (Retirement Actions, Investment Actions, Protection Actions) are replaced by the single Decision Matrix tab.

1. **Remove 3 tab entries** from the `tabs` array (lines 119-131): remove `retirement-actions`, `investment-actions`, `protection-actions`
2. **Add 1 tab entry** after `users` (line 110): `{ id: 'decision-matrix', label: 'Decision Matrix' }`
3. **Remove 3 component imports** (lines 81-83): `AdminRetirementActions`, `AdminInvestmentActions`, `AdminProtectionActions`
4. **Remove from `components` object** (lines 94-96)
5. **Add import**: `const DecisionMatrix = () => import('../../components/Admin/DecisionMatrix.vue');` (lazy-loaded)
6. **Add to `components` object**: `DecisionMatrix`
7. **Remove 3 template blocks** (lines 61-68): `AdminRetirementActions`, `AdminInvestmentActions`, `AdminProtectionActions` `v-if` blocks
8. **Add template block** after the Tax Settings block (line 59):
```html
<!-- Decision Matrix Tab -->
<DecisionMatrix v-if="activeTab === 'decision-matrix'" />
```
9. **Add icon mapping** in `getTabIcon()` for `'decision-matrix'`: use a tree/flow icon path
10. **Add short label** in `getTabShortLabel()`: `'decision-matrix': 'Matrix'`

**Note:** The existing per-module routes (`admin/retirement-actions`, etc.) and their controllers are kept for backward compatibility. Only the admin panel UI tabs are consolidated.

- [ ] **Step 7: Test in browser**

```bash
./dev.sh
```

Navigate to admin panel → Decision Matrix tab. Verify:
- 6 module sub-tabs render with correct counts
- Tree renders for each module with nodes and connections
- Clicking a node opens the drawer
- Editing and saving works
- Disabled nodes show correctly
- Design guide colours and typography match

- [ ] **Step 8: Commit**

```bash
git add resources/js/components/Admin/DecisionMatrix.vue resources/js/components/Admin/DecisionTree.vue resources/js/components/Admin/DecisionNode.vue resources/js/components/Admin/ActionDefinitionDrawer.vue resources/js/components/Admin/TriggerConfigEditor.vue resources/js/views/Admin/AdminPanel.vue
git commit -m "feat: Decision Matrix visual tree with 6 module tabs and inline editing"
```

---

## Task 7: Enhanced User Management

**Files:**
- Create: `resources/js/components/Admin/UserModuleStatus.vue`
- Create: `resources/js/components/Admin/UserOnboardingProgress.vue`
- Modify: `resources/js/components/Admin/UserManagement.vue` (lines 46-219)
- Modify: `resources/js/services/adminService.js`

- [ ] **Step 1: Add API method to adminService.js**

Add to `resources/js/services/adminService.js`:

```javascript
getUserModuleStatus(userId) {
    return api.get(`/admin/users/${userId}/module-status`);
},
```

(Uses `api` from `./api`, not raw `axios` — consistent with all other methods in this file.)

- [ ] **Step 2: Create UserModuleStatus.vue**

Component that receives `userId` prop. Shows P S I R E module dots (24px squares, rounded-4px). Colour coding: complete=`bg-spring-500`, partial=`bg-violet-500`, empty=`bg-light-gray text-neutral-500`, skipped=`bg-eggshell-500 text-horizon-300 line-through border border-light-gray`.

Has an `expanded` state. When expanded, fetches detailed module status from the API and renders sub-area breakdown per module.

- [ ] **Step 3: Create UserOnboardingProgress.vue**

Receives `onboarding` object (from the module status response). Renders a card showing: onboarding completion state, started/completed dates, life stage, journey states, completed steps count.

- [ ] **Step 4: Modify UserManagement.vue**

Add a "Modules" column to the user table (between Email and Role columns):
- In the `<th>` row: add `<th>Modules</th>`
- In the `<td>` row: add `<UserModuleStatus :userId="user.id" />`
- Add expandable row functionality: clicking a user row toggles an expanded detail section below it showing `UserModuleStatus` (expanded) + `UserOnboardingProgress`

- [ ] **Step 5: Test in browser**

Navigate to admin panel → User Management. Verify:
- Module dots appear for each user
- Clicking a row expands to show detailed breakdown
- Colours match design guide
- Data is accurate for preview personas

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Admin/UserModuleStatus.vue resources/js/components/Admin/UserOnboardingProgress.vue resources/js/components/Admin/UserManagement.vue resources/js/services/adminService.js
git commit -m "feat: enhanced user management with module status tracking"
```

---

## Task 8: Database Backup Verification & Fix

**Files:**
- Test: `tests/Feature/Api/AdminBackupTest.php`
- Modify: `app/Http/Controllers/Api/AdminController.php` (lines 312-589 — backup methods: `createBackup`, `listBackups`, `restoreBackup`, `deleteBackup`, `getLastBackupTime`)

- [ ] **Step 1: Write backup tests**

Create `tests/Feature/Api/AdminBackupTest.php` covering:

```php
it('creates a backup file successfully');
it('lists existing backups with correct metadata');
it('restores a backup file'); // IMPORTANT: Must use test database (RefreshDatabase trait), NEVER production
it('deletes a backup file');
it('rejects path traversal in backup filename');
it('rejects invalid backup filename format');
it('rate limits backup operations to 3 per minute');
it('requires admin permission for backup operations');
it('cleans up temporary credential files after backup');
```

- [ ] **Step 2: Run tests to identify failures**

```bash
./vendor/bin/pest tests/Feature/Api/AdminBackupTest.php
```

Document which tests fail and why.

- [ ] **Step 3: Fix any issues found**

Common issues to check:
- mysqldump binary path may not be configured
- `storage/app/backups/` directory may not exist
- Credential file permissions on macOS vs Linux
- Error handling when mysqldump fails

Fix each issue in `AdminController.php` as identified by the tests.

- [ ] **Step 4: Run tests to verify all pass**

```bash
./vendor/bin/pest tests/Feature/Api/AdminBackupTest.php
```

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Api/AdminBackupTest.php app/Http/Controllers/Api/AdminController.php
git commit -m "fix: database backup verification and fixes"
```

---

## Task 9: Final Integration & Seed

- [ ] **Step 1: Run full test suite**

```bash
./vendor/bin/pest
```

All tests must pass.

- [ ] **Step 2: Run code formatter**

```bash
./vendor/bin/pint
```

- [ ] **Step 3: Seed database**

```bash
php artisan db:seed
```

- [ ] **Step 4: Full browser test**

Start dev server and test:
- Admin → Decision Matrix: All 6 module tabs, tree renders, drawer opens/saves
- Admin → User Management: Module dots, expandable rows, onboarding progress
- Admin → Database: Create backup, list, delete

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: final integration, formatting, and seed verification"
```
