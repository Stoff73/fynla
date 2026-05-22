# Freemium Tier Model + Count Caps + Fyn Agent Metering — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the freemium model from a permissive stub into an admin-editable, DB-backed, defence-in-depth tier system that is the single source of truth for pricing, capability gating, count caps, and Fyn metering — without breaking existing subscribers.

**Architecture:** Approach A (spec §6, §23). A new `tier_configurations` reference-data table + `TierConfigurationStore` (SP1 §12 store pattern) is the single source of truth. `TierResolver` maps any `User` to one of `free / tier1 / tier2 / tier3`. `DbTierGate` (reading the store) replaces the globally-bound `PermissiveTierGate`; `StaticTierGate` is deleted. Every SP1 entity store already calls `TierGate::canCreate()` — SP2 only changes the bound implementation and the numbers it returns. Fyn metering moves to weekly soft-degrade with the legacy daily cap demoted to an abuse backstop. A generic `TeaserGate` gates Estate. One admin screen edits the store and propagates to `PricingPage.vue`, invoices, `CheckSubscription`, `HasAiGuardrails`, and the Revolut sync.

**Tech Stack:** Laravel 10, PHP 8.2 (`declare(strict_types=1)`), MySQL 8 (JSON columns), Pest (Unit/Feature/Architecture), Vue 3 + Vuex + Tailwind, Revolut subscription API (sandbox on csjones).

**Spec:** `docs/superpowers/specs/2026-05-16-sub-project-2-freemium-tier-model-design.md` (APPROVED 2026-05-16; §22 A1–A4/A6/A7/A10 on defaults; A5/A8/A9 corrected — new tiers, no legacy map).

**The 9-PR ladder (spec §16.1). Each PR ships to `dev`, deploys to csjones, is browser-tested, then rides the periodic `dev → main` release. PR #317 stays parked until SP2 lands on dev.**

| PR | Task group | Produces working software |
|----|------------|---------------------------|
| 1 | `tier_configurations` store + seeder + Pest boundary | The source of truth exists, seeded, admin-writable, boundary-locked |
| 2 | `users.tier` + `TierResolver` + grandfather backfill | Every user resolves to a canonical tier, non-narrowing |
| 3 | `DbTierGate` replaces `PermissiveTierGate`; delete `StaticTierGate` | Real caps live with grandfathering |
| 4 | Admin tier-config screen + propagation | One screen drives PricingPage + invoices + access gating |
| 5 | Revolut plan-variation sync + price-lock | Store ↔ Revolut one-way sync, existing prices locked |
| 6 | Fyn weekly soft-degrade + daily backstop | Weekly budget soft-degrades; daily = abuse backstop |
| 7 | Generic teaser-gate + Estate consumer | Free/Tier1 → IHT teaser; Tier2/3 → full Estate |
| 8 | Doc allowance + storage quota + currency/snapshot/open-API flags | SP1 deferred numbers wired to the store |
| 9 | Lock-down: enable Pest arch test + remove legacy hardcoded caps | No hardcoded tier numbers remain; boundary green |

---

## File Structure

**New files (created by this plan):**

| Path | Responsibility |
|------|----------------|
| `database/migrations/2026_05_17_100000_create_tier_configurations_table.php` | The reference-data table (one row per tier). |
| `database/migrations/2026_05_17_100001_add_tier_to_users_table.php` | `users.tier` enum column + index. |
| `app/Models/TierConfiguration.php` | Eloquent model for the table (JSON casts, fillable). |
| `app/Services/Stores/TierConfigurationStore.php` | SP1 §12 reference-data store: read-heavy, admin-write, audited, cached. The single source of truth. |
| `app/Services/Stores/Exceptions/TierConfigValidationException.php` | Thrown on invalid admin write of a tier config. |
| `app/Services/Tiers/TierResolver.php` | Resolves any `User` → `free|tier1|tier2|tier3`. |
| `app/Services/Tiers/DbTierGate.php` | `TierGate` impl reading `TierConfigurationStore`. Bound globally. |
| `app/Services/Tiers/TeaserGate.php` | Generic capability teaser-gate (Estate is its only SP2 consumer). |
| `app/Services/Tiers/EstateIhtExposureDetector.php` | Cheap IHT-exposure signal for the Free/Tier1 Estate teaser. |
| `database/seeders/TierConfigurationSeeder.php` | Seeds the four tier rows from the spec §7 matrix. |
| `app/Http/Controllers/Api/Admin/TierConfigurationController.php` | Admin CRUD for the store (mirrors `TaxSettingsController` pattern). |
| `app/Http/Requests/Admin/UpdateTierConfigurationRequest.php` | Validation for the admin write. |
| `app/Http/Resources/TierConfigurationResource.php` | API shape for the admin screen + public pricing. |
| `app/Services/Tiers/RevolutTierVariationSync.php` | One-way store → Revolut plan-variation sync + `revolut_plan_variation_id` write-back. |
| `app/Console/Commands/SyncRevolutTierVariations.php` | Scheduled command wrapping the sync. |
| `resources/js/components/Admin/TierConfiguration.vue` | Admin tab editing the store (follows existing AdminPanel tab pattern). |
| `tests/Architecture/TierConfigBoundaryTest.php` | Pest boundary: only the store mutates the table; no hardcoded tier numbers. |
| Tests under `tests/Unit/Services/Tiers/`, `tests/Feature/Tiers/`, `tests/Feature/Admin/` | Per-task suites (paths given inline). |

**Modified files:**

| Path | Change |
|------|--------|
| `app/Providers/AppServiceProvider.php` (≈ lines 60-63) | Bind `TierGate` → `DbTierGate` (was `PermissiveTierGate`). |
| `app/Services/Stores/StaticTierGate.php` | **Deleted** (PR 3). |
| `app/Traits/HasAiGuardrails.php` | Weekly budget from store; soft-degrade in `getAiModel`; daily cap → abuse backstop; remove `DAILY_TOKEN_LIMITS` (PR 9). |
| `app/Http/Middleware/CheckSubscription.php` | Access decisions read tier capability from the store (PR 4). |
| `resources/js/views/Public/PricingPage.vue` | Prices/features read live from the store API (PR 4). |
| `routes/api.php` | Register admin tier-config routes + public pricing-config route. |
| `app/Console/Kernel.php` | Schedule `SyncRevolutTierVariations` (PR 5). |
| Estate module entry (controller/route + Vue view — exact files identified in Task 7.1) | Server-side teaser-gate (PR 7). |
| `database/seeders/DatabaseSeeder.php` | Call `TierConfigurationSeeder`. |

**Pattern sources the implementer MUST read before the relevant task (named inline, not placeholders):**
- Store pattern: `app/Services/Stores/SavingsStore.php` (constructor DI, `AuditLog::withContext`, `DB::transaction`, `validateCanonical`, `StoreValidationException`).
- Reference-admin-write pattern: `app/Http/Controllers/Api/TaxSettingsController.php` + its FormRequest + `TaxConfigurationAudit` (SP1 §12.1 says fix/follow this, don't invent a new namespace).
- Admin tab pattern: `resources/js/views/Admin/AdminPanel.vue` + an existing admin tab component (e.g. `resources/js/components/Admin/TaxSettings.vue`).
- Arch test pattern: `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` (`arch()->expect()->toOnlyBeUsedIn([...])`).

---

# PR 1 — `tier_configurations` store + seeder + admin-write + Pest boundary

**Branch:** `feature/csj/sp2-pr1-tier-config-store` off `sp2Freemium`. Risk: very low (pure addition, no consumers wired).

### Task 1.1: Create the `tier_configurations` migration

**Files:**
- Create: `database/migrations/2026_05_17_100000_create_tier_configurations_table.php`
- Test: `tests/Feature/Tiers/TierConfigurationsTableTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('has a tier_configurations table with the canonical columns', function () {
    expect(Schema::hasTable('tier_configurations'))->toBeTrue();
    expect(Schema::hasColumns('tier_configurations', [
        'id', 'tier', 'display_name',
        'price_monthly_pence', 'price_annual_pence', 'revolut_plan_variation_id',
        'capability_matrix', 'count_caps',
        'document_upload_allowance', 'document_storage_gb',
        'fyn_weekly_token_budget', 'fyn_daily_hard_backstop',
        'currency_display_mode', 'snapshot_surfacing_window_days',
        'open_api_affordance', 'is_active', 'updated_by',
    ]))->toBeTrue();
});

it('enforces a unique tier slug', function () {
    \App\Models\TierConfiguration::create(tierConfigFixture('free'));
    expect(fn () => \App\Models\TierConfiguration::create(tierConfigFixture('free')))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

Add this helper to `tests/Pest.php` (or a shared helpers file already used by the suite — check `tests/Pest.php` first):

```php
function tierConfigFixture(string $tier): array
{
    return [
        'tier' => $tier,
        'display_name' => ucfirst($tier),
        'price_monthly_pence' => 0,
        'price_annual_pence' => 0,
        'revolut_plan_variation_id' => null,
        'capability_matrix' => ['dashboard' => 'full'],
        'count_caps' => ['savings_account' => $tier === 'free' ? 3 : null],
        'document_upload_allowance' => 3,
        'document_storage_gb' => null,
        'fyn_weekly_token_budget' => 100_000,
        'fyn_daily_hard_backstop' => 500_000,
        'currency_display_mode' => 'gbp_only',
        'snapshot_surfacing_window_days' => 90,
        'open_api_affordance' => false,
        'is_active' => true,
        'updated_by' => null,
    ];
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Tiers/TierConfigurationsTableTest.php -v`
Expected: FAIL — `Schema::hasTable('tier_configurations')` is false.

- [ ] **Step 3: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_configurations', function (Blueprint $table) {
            $table->id();
            $table->enum('tier', ['free', 'tier1', 'tier2', 'tier3'])->unique();
            $table->string('display_name');
            $table->unsignedInteger('price_monthly_pence')->default(0);
            $table->unsignedInteger('price_annual_pence')->default(0);
            $table->string('revolut_plan_variation_id')->nullable();
            $table->json('capability_matrix');   // entity_key => full|none|limited|teaser
            $table->json('count_caps');          // entity_key => int|null (null = unlimited)
            $table->unsignedInteger('document_upload_allowance')->default(0);
            $table->decimal('document_storage_gb', 8, 2)->nullable(); // null = none
            $table->unsignedInteger('fyn_weekly_token_budget');
            $table->unsignedInteger('fyn_daily_hard_backstop');
            $table->enum('currency_display_mode', ['gbp_only', 'user_choice'])->default('gbp_only');
            $table->unsignedInteger('snapshot_surfacing_window_days')->default(90);
            $table->boolean('open_api_affordance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_configurations');
    }
};
```

- [ ] **Step 4: Migrate and run the test**

Run: `php artisan migrate && ./vendor/bin/pest tests/Feature/Tiers/TierConfigurationsTableTest.php -v`
Expected: PASS (both tests green). If "table missing" elsewhere, re-seed: `php artisan db:seed`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_17_100000_create_tier_configurations_table.php tests/Feature/Tiers/TierConfigurationsTableTest.php tests/Pest.php
git commit -m "feat(tier): tier_configurations table

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

### Task 1.2: The `TierConfiguration` model

**Files:**
- Create: `app/Models/TierConfiguration.php`
- Test: `tests/Unit/Models/TierConfigurationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TierConfiguration;

it('casts JSON columns to arrays and booleans correctly', function () {
    $row = TierConfiguration::create(tierConfigFixture('tier2'));
    $fresh = $row->fresh();

    expect($fresh->capability_matrix)->toBeArray()
        ->and($fresh->count_caps)->toBeArray()
        ->and($fresh->open_api_affordance)->toBeBool()
        ->and($fresh->is_active)->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Models/TierConfigurationTest.php -v`
Expected: FAIL — class `App\Models\TierConfiguration` not found.

- [ ] **Step 3: Write the model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TierConfiguration extends Model
{
    protected $fillable = [
        'tier', 'display_name',
        'price_monthly_pence', 'price_annual_pence', 'revolut_plan_variation_id',
        'capability_matrix', 'count_caps',
        'document_upload_allowance', 'document_storage_gb',
        'fyn_weekly_token_budget', 'fyn_daily_hard_backstop',
        'currency_display_mode', 'snapshot_surfacing_window_days',
        'open_api_affordance', 'is_active', 'updated_by',
    ];

    protected $casts = [
        'capability_matrix' => 'array',
        'count_caps' => 'array',
        'price_monthly_pence' => 'integer',
        'price_annual_pence' => 'integer',
        'document_upload_allowance' => 'integer',
        'document_storage_gb' => 'decimal:2',
        'fyn_weekly_token_budget' => 'integer',
        'fyn_daily_hard_backstop' => 'integer',
        'snapshot_surfacing_window_days' => 'integer',
        'open_api_affordance' => 'boolean',
        'is_active' => 'boolean',
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Models/TierConfigurationTest.php -v`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/TierConfiguration.php tests/Unit/Models/TierConfigurationTest.php
git commit -m "feat(tier): TierConfiguration model

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

### Task 1.3: The seeder (spec §7 matrix + §22 approved defaults)

**Files:**
- Create: `database/seeders/TierConfigurationSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (add the call — read it first to match the existing call style)
- Test: `tests/Feature/Tiers/TierConfigurationSeederTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TierConfiguration;

beforeEach(fn () => $this->seed(\Database\Seeders\TierConfigurationSeeder::class));

it('seeds exactly the four canonical tiers', function () {
    expect(TierConfiguration::pluck('tier')->sort()->values()->all())
        ->toBe(['free', 'tier1', 'tier2', 'tier3']);
});

it('applies the spec §7 count caps for free', function () {
    $free = TierConfiguration::where('tier', 'free')->first();
    expect($free->count_caps['savings_account'])->toBe(3)
        ->and($free->count_caps['investment'])->toBe(2)
        ->and($free->count_caps['pension_account'])->toBe(5)
        ->and($free->capability_matrix['estate'])->toBe('teaser')
        ->and($free->capability_matrix['family_module'])->toBe('full') // A5 firm: all tiers
        ->and($free->fyn_weekly_token_budget)->toBe(100_000)
        ->and($free->currency_display_mode)->toBe('gbp_only')
        ->and($free->snapshot_surfacing_window_days)->toBe(90);
});

it('makes tier1+ counts unlimited and tier3 the widest', function () {
    $t1 = TierConfiguration::where('tier', 'tier1')->first();
    $t3 = TierConfiguration::where('tier', 'tier3')->first();
    expect($t1->count_caps['savings_account'])->toBeNull()
        ->and($t1->capability_matrix['estate'])->toBe('teaser')          // teaser at tier1 too
        ->and($t1->capability_matrix['investments_exotic'])->toBe('none') // A1 default
        ->and($t1->capability_matrix['chattels'])->toBe('full')           // A2 default
        ->and($t3->capability_matrix['estate'])->toBe('full')
        ->and($t3->fyn_weekly_token_budget)->toBe(1_000_000)
        ->and($t3->currency_display_mode)->toBe('user_choice')
        ->and($t3->snapshot_surfacing_window_days)->toBe(2555)
        ->and($t3->open_api_affordance)->toBeTrue();
});

it('is idempotent (updateOrCreate)', function () {
    $this->seed(\Database\Seeders\TierConfigurationSeeder::class);
    expect(TierConfiguration::count())->toBe(4);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Tiers/TierConfigurationSeederTest.php -v`
Expected: FAIL — seeder class not found.

- [ ] **Step 3: Write the seeder**

The matrix below is spec §7 with the §22-approved defaults baked in (A1 exotic@tier1 = none, A2 chattels@tier1 = full, A3 doc-storage@tier1 = none, A4 child-benefits@free = full, A5 family@all = full, A6 upload ladder 3/4/5/6, A7 storage null/null/5/20, A10 daily backstop generous). Prices are §22-A8 render-only placeholders (CSJ sets real prices on the admin screen).

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TierConfiguration;
use Illuminate\Database\Seeder;

class TierConfigurationSeeder extends Seeder
{
    /**
     * Capability verbs: full | none | limited | teaser.
     * count_caps: int = cap, null = unlimited, absent = not count-gated.
     * Prices are render-only placeholders (spec §22 A8) — CSJ sets real
     * prices via the admin screen; nothing here maps to legacy plan prices.
     */
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            TierConfiguration::updateOrCreate(['tier' => $row['tier']], $row);
        }
    }

    private function rows(): array
    {
        // entity keys used by SP1 stores + capability flags
        $base = fn (array $matrix, array $caps): array => compact('matrix', 'caps');

        return [
            [
                'tier' => 'free',
                'display_name' => 'Free',
                'price_monthly_pence' => 0,
                'price_annual_pence' => 0,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'none',
                    'goals' => 'full', 'protection' => 'full', 'property' => 'full',
                    'liabilities' => 'full', 'income' => 'full', 'expenditure' => 'full',
                    'estate' => 'teaser', 'chattels' => 'full',
                    'benefits_child' => 'full', 'family_module' => 'full',
                    'investments_exotic' => 'none', 'retirement_decumulation' => 'none',
                    'savings_account' => 'limited', 'investment' => 'limited',
                    'pension_account' => 'limited',
                ],
                'count_caps' => ['savings_account' => 3, 'investment' => 2, 'pension_account' => 5],
                'document_upload_allowance' => 3,   // §22 A6
                'document_storage_gb' => null,
                'fyn_weekly_token_budget' => 100_000,
                'fyn_daily_hard_backstop' => 500_000, // §22 A10 generous
                'currency_display_mode' => 'gbp_only',
                'snapshot_surfacing_window_days' => 90,
                'open_api_affordance' => false,
                'is_active' => true,
                'updated_by' => null,
            ],
            [
                'tier' => 'tier1',
                'display_name' => 'Tier 1',
                'price_monthly_pence' => 499,   // §22 A8 placeholder
                'price_annual_pence' => 4990,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'full',
                    'goals' => 'full', 'protection' => 'full', 'property' => 'full',
                    'liabilities' => 'full', 'income' => 'full', 'expenditure' => 'full',
                    'estate' => 'teaser', 'chattels' => 'full',          // §22 A2
                    'benefits_child' => 'full', 'family_module' => 'full',
                    'investments_exotic' => 'none',                       // §22 A1
                    'retirement_decumulation' => 'none',
                    'savings_account' => 'full', 'investment' => 'full',
                    'pension_account' => 'full',
                ],
                'count_caps' => ['savings_account' => null, 'investment' => null, 'pension_account' => null],
                'document_upload_allowance' => 4,   // §22 A6
                'document_storage_gb' => null,      // §22 A3
                'fyn_weekly_token_budget' => 250_000,
                'fyn_daily_hard_backstop' => 1_000_000,
                'currency_display_mode' => 'gbp_only',
                'snapshot_surfacing_window_days' => 365,
                'open_api_affordance' => false,
                'is_active' => true,
                'updated_by' => null,
            ],
            [
                'tier' => 'tier2',
                'display_name' => 'Tier 2',
                'price_monthly_pence' => 1499,  // §22 A8 placeholder
                'price_annual_pence' => 14990,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'full',
                    'goals' => 'full', 'protection' => 'full', 'property' => 'full',
                    'liabilities' => 'full', 'income' => 'full', 'expenditure' => 'full',
                    'estate' => 'full', 'chattels' => 'full',
                    'benefits_child' => 'full', 'family_module' => 'full',
                    'investments_exotic' => 'full', 'retirement_decumulation' => 'full',
                    'savings_account' => 'full', 'investment' => 'full',
                    'pension_account' => 'full',
                ],
                'count_caps' => ['savings_account' => null, 'investment' => null, 'pension_account' => null],
                'document_upload_allowance' => 5,   // §22 A6
                'document_storage_gb' => 5.00,      // §22 A7
                'fyn_weekly_token_budget' => 500_000,
                'fyn_daily_hard_backstop' => 2_000_000,
                'currency_display_mode' => 'user_choice',
                'snapshot_surfacing_window_days' => 1825,
                'open_api_affordance' => true,
                'is_active' => true,
                'updated_by' => null,
            ],
            [
                'tier' => 'tier3',
                'display_name' => 'Tier 3',
                'price_monthly_pence' => 2999,  // §22 A8 placeholder
                'price_annual_pence' => 29990,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'full',
                    'goals' => 'full', 'protection' => 'full', 'property' => 'full',
                    'liabilities' => 'full', 'income' => 'full', 'expenditure' => 'full',
                    'estate' => 'full', 'chattels' => 'full',
                    'benefits_child' => 'full', 'family_module' => 'full',
                    'investments_exotic' => 'full', 'retirement_decumulation' => 'full',
                    'savings_account' => 'full', 'investment' => 'full',
                    'pension_account' => 'full',
                ],
                'count_caps' => ['savings_account' => null, 'investment' => null, 'pension_account' => null],
                'document_upload_allowance' => 6,   // §22 A6
                'document_storage_gb' => 20.00,     // §22 A7
                'fyn_weekly_token_budget' => 1_000_000,
                'fyn_daily_hard_backstop' => 4_000_000,
                'currency_display_mode' => 'user_choice',
                'snapshot_surfacing_window_days' => 2555,
                'open_api_affordance' => true,
                'is_active' => true,
                'updated_by' => null,
            ],
        ];
    }
}
```

Then add to `database/seeders/DatabaseSeeder.php` — read the file first, then add `$this->call(TierConfigurationSeeder::class);` next to the other reference-data seeders (e.g. near `TaxConfigurationSeeder`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan db:seed --class=TierConfigurationSeeder --force && ./vendor/bin/pest tests/Feature/Tiers/TierConfigurationSeederTest.php -v`
Expected: PASS (all 4 tests).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/TierConfigurationSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Tiers/TierConfigurationSeederTest.php
git commit -m "feat(tier): seed the four canonical tiers (spec §7 + §22 defaults)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

### Task 1.4: `TierConfigurationStore` (SP1 §12 reference-data store)

**Files:**
- Create: `app/Services/Stores/TierConfigurationStore.php`
- Create: `app/Services/Stores/Exceptions/TierConfigValidationException.php`
- Test: `tests/Unit/Services/Stores/TierConfigurationStoreTest.php`

Read `app/Services/Stores/SavingsStore.php` and `app/Services/Stores/Exceptions/StoreValidationException.php` first — mirror the audited/transactional write shape and the validation-exception shape.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Stores\Exceptions\TierConfigValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;

beforeEach(function () {
    $this->seed(\Database\Seeders\TierConfigurationSeeder::class);
    $this->store = app(TierConfigurationStore::class);
});

it('reads the active config for a tier', function () {
    $cfg = $this->store->forTier('free');
    expect($cfg)->toBeInstanceOf(TierConfiguration::class)
        ->and($cfg->tier)->toBe('free');
});

it('returns the count cap for an entity/tier pair', function () {
    expect($this->store->capFor('free', 'savings_account'))->toBe(3)
        ->and($this->store->capFor('tier1', 'savings_account'))->toBeNull()
        ->and($this->store->capFor('free', 'unknown_entity'))->toBeNull();
});

it('returns the capability verb for an entity/tier pair', function () {
    expect($this->store->capabilityFor('free', 'estate'))->toBe('teaser')
        ->and($this->store->capabilityFor('tier3', 'estate'))->toBe('full');
});

it('memoises reads within a request', function () {
    $this->store->forTier('free');
    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->store->forTier('free');
    expect(\Illuminate\Support\Facades\DB::getQueryLog())->toBeEmpty();
});

it('admin-updates a tier, audits, and invalidates the cache', function () {
    $admin = User::factory()->create();
    $this->store->forTier('free'); // warm cache

    $updated = $this->store->updateTier('free', ['price_monthly_pence' => 199], $admin, IngestSource::ADMIN);

    expect($updated->price_monthly_pence)->toBe(199)
        ->and($this->store->forTier('free')->price_monthly_pence)->toBe(199); // cache dropped
    $this->assertDatabaseHas('audit_logs', ['entity_type' => 'tier_configuration']);
});

it('rejects an invalid tier slug', function () {
    $admin = User::factory()->create();
    expect(fn () => $this->store->updateTier('platinum', [], $admin, IngestSource::ADMIN))
        ->toThrow(TierConfigValidationException::class);
});

it('rejects a non-admin/seeder ingest source', function () {
    $admin = User::factory()->create();
    expect(fn () => $this->store->updateTier('free', ['price_monthly_pence' => 1], $admin, IngestSource::FORM))
        ->toThrow(TierConfigValidationException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Stores/TierConfigurationStoreTest.php -v`
Expected: FAIL — store class not found.

- [ ] **Step 3: Write the exception**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Exceptions;

use RuntimeException;

class TierConfigValidationException extends RuntimeException
{
    public function __construct(
        public readonly array $errors,
        string $message = 'Tier configuration validation failed'
    ) {
        parent::__construct($message);
    }
}
```

- [ ] **Step 4: Write the store**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\AuditLog;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Stores\Exceptions\TierConfigValidationException;
use Illuminate\Support\Facades\DB;

class TierConfigurationStore
{
    public const TIERS = ['free', 'tier1', 'tier2', 'tier3'];

    /** Per-request memoisation: tier => TierConfiguration */
    private array $cache = [];

    public function forTier(string $tier): TierConfiguration
    {
        if (! in_array($tier, self::TIERS, true)) {
            throw new TierConfigValidationException(['tier' => "Unknown tier: {$tier}"]);
        }

        return $this->cache[$tier] ??= TierConfiguration::where('tier', $tier)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /** Count cap for an entity at a tier. null = unlimited / not count-gated. */
    public function capFor(string $tier, string $entityKey): ?int
    {
        $caps = $this->forTier($tier)->count_caps ?? [];

        return $caps[$entityKey] ?? null;
    }

    /** Capability verb (full|none|limited|teaser) for an entity at a tier. */
    public function capabilityFor(string $tier, string $entityKey): string
    {
        $matrix = $this->forTier($tier)->capability_matrix ?? [];

        return $matrix[$entityKey] ?? 'none';
    }

    /**
     * Admin/seeder-only write. Audited and cache-invalidating. Mirrors the
     * SavingsStore audited-transaction shape; the only legitimate ingest
     * sources are ADMIN and SEEDER (spec §6.1, §12.1).
     */
    public function updateTier(string $tier, array $data, User $actor, IngestSource $source): TierConfiguration
    {
        if (! in_array($tier, self::TIERS, true)) {
            throw new TierConfigValidationException(['tier' => "Unknown tier: {$tier}"]);
        }
        if (! in_array($source, [IngestSource::ADMIN, IngestSource::SEEDER], true)) {
            throw new TierConfigValidationException(['source' => 'tier_configurations is admin/seeder-write only']);
        }

        $allowed = array_intersect_key($data, array_flip([
            'display_name', 'price_monthly_pence', 'price_annual_pence',
            'revolut_plan_variation_id', 'capability_matrix', 'count_caps',
            'document_upload_allowance', 'document_storage_gb',
            'fyn_weekly_token_budget', 'fyn_daily_hard_backstop',
            'currency_display_mode', 'snapshot_surfacing_window_days',
            'open_api_affordance', 'is_active',
        ]));

        return AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($tier, $allowed, $actor) {
            $row = TierConfiguration::where('tier', $tier)->firstOrFail();
            $before = $row->only(array_keys($allowed));
            $row->fill(array_merge($allowed, ['updated_by' => $actor->id]))->save();

            AuditLog::create([
                'user_id' => $actor->id,
                'actor_user_id' => $actor->id,
                'entity_type' => 'tier_configuration',
                'entity_id' => $row->id,
                'action' => 'update',
                'changes' => ['before' => $before, 'after' => $row->only(array_keys($allowed))],
            ]);

            unset($this->cache[$tier]);

            return $row->fresh();
        }));
    }
}
```

Note: confirm the `audit_logs` column names against `app/Models/AuditLog.php` before running (the SP1 store uses `AuditLog::withContext`; match the existing audit row shape — adjust the explicit `AuditLog::create(...)` keys if the schema differs, e.g. `auditable_type`/`auditable_id`). This is the one place to verify against the live audit model.

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Stores/TierConfigurationStoreTest.php -v`
Expected: PASS (all 7). Fix audit-row keys if the audit assertion fails, then re-run until green (Rule #15 loop).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Stores/TierConfigurationStore.php app/Services/Stores/Exceptions/TierConfigValidationException.php tests/Unit/Services/Stores/TierConfigurationStoreTest.php
git commit -m "feat(tier): TierConfigurationStore — single source of truth (SP1 §12 pattern)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

### Task 1.5: Pest boundary test stub (soft — flips to hard in PR 9)

**Files:**
- Create: `tests/Architecture/TierConfigBoundaryTest.php`

Read `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` first for the exact `arch()` idiom.

- [ ] **Step 1: Write the boundary test**

```php
<?php

declare(strict_types=1);

/**
 * SP2 — tier_configurations boundary. Only TierConfigurationStore (+ the
 * spec §14.2 permanents: seeder, factory, migrations, console commands)
 * mutates the table. The "no hardcoded tier numbers outside the store"
 * clause is added and made HARD in PR 9 — until then this asserts the
 * mutation boundary only.
 */
arch('TierConfiguration is only mutated inside the canonical set')
    ->expect('App\Models\TierConfiguration')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\TierConfigurationStore',
        'App\Services\Tiers\TierResolver',          // read-only (added PR 2)
        'App\Services\Tiers\DbTierGate',            // read-only (added PR 3)
        'App\Services\Tiers\TeaserGate',            // read-only (added PR 7)
        'App\Http\Resources\TierConfigurationResource', // read-only (added PR 4)
        'Database\Seeders\TierConfigurationSeeder',
        'Database\Factories\TierConfigurationFactory',
        'App\Models\\',
    ]);
```

- [ ] **Step 2: Run it**

Run: `./vendor/bin/pest tests/Architecture/TierConfigBoundaryTest.php -v`
Expected: PASS (only the store + model exist as consumers now). If a `TierConfigurationFactory` is required by the runner and absent, create a minimal factory using `tierConfigFixture()`.

- [ ] **Step 3: Run the full arch suite to confirm no regression**

Run: `./vendor/bin/pest --testsuite=Architecture`
Expected: PASS (all architecture tests green).

- [ ] **Step 4: Commit & open PR 1**

```bash
git add tests/Architecture/TierConfigBoundaryTest.php
git commit -m "feat(tier): tier_configurations Pest boundary stub

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin feature/csj/sp2-pr1-tier-config-store
gh pr create --base sp2Freemium --title "SP2 PR1: tier_configurations store + seeder + boundary" --body "$(cat <<'EOF'
SP2 PR 1 of 9. Pure addition — no consumers wired.

- tier_configurations table (one row/tier)
- TierConfiguration model
- TierConfigurationSeeder (spec §7 + §22 defaults; placeholder prices)
- TierConfigurationStore (SP1 §12 reference-data store pattern)
- Pest boundary stub (hardens in PR 9)

Spec: docs/superpowers/specs/2026-05-16-sub-project-2-freemium-tier-model-design.md

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

# PR 2 — `users.tier` + `TierResolver` + grandfather backfill

**Branch:** `feature/csj/sp2-pr2-tier-resolver` off `sp2Freemium` (after PR 1 merges). Risk: low (additive + non-narrowing backfill).

### Task 2.1: `users.tier` column + non-narrowing backfill migration

**Files:**
- Create: `database/migrations/2026_05_17_100001_add_tier_to_users_table.php`
- Test: `tests/Feature/Tiers/UsersTierColumnTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('adds a nullable tier column defaulting to null', function () {
    expect(Schema::hasColumn('users', 'tier'))->toBeTrue();
    $u = User::factory()->create();
    expect($u->fresh()->tier)->toBeNull(); // resolver, not the column, decides
});
```

Spec §5.2: there is **no mechanical plan→tier map**. The column is nullable; `free`/no-subscription users resolve to `free` via the resolver (Task 2.2). Paid legacy subscribers are left `null` here and grandfathered by the resolver (their access never narrows). A real conversion tier is a per-cohort CSJ decision settled before PR 5 — this migration does **not** assign one.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Tiers/UsersTierColumnTest.php -v`
Expected: FAIL — column missing.

- [ ] **Step 3: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable: null means "resolve via TierResolver". No mechanical
            // plan→tier backfill (spec §5.2) — paid legacy subscribers stay
            // null and are grandfathered by the resolver until renewal.
            $table->enum('tier', ['free', 'tier1', 'tier2', 'tier3'])
                ->nullable()->after('plan')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
```

- [ ] **Step 4: Migrate & test**

Run: `php artisan migrate && ./vendor/bin/pest tests/Feature/Tiers/UsersTierColumnTest.php -v`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_05_17_100001_add_tier_to_users_table.php tests/Feature/Tiers/UsersTierColumnTest.php
git commit -m "feat(tier): users.tier column (nullable; no mechanical backfill, spec §5.2)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

### Task 2.2: `TierResolver`

**Files:**
- Create: `app/Services/Tiers/TierResolver.php`
- Test: `tests/Unit/Services/Tiers/TierResolverTest.php`

Read `app/Traits/HasAiGuardrails.php::getUserPlan()` first — reuse its preview/subscription/trial logic shape so resolution is consistent with existing behaviour. Confirm `User` exposes `is_preview_user`, `is_admin`, and a `subscription` relation (it does per the trait).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tiers\TierResolver;

beforeEach(fn () => $this->resolver = app(TierResolver::class));

it('resolves an explicit users.tier value', function () {
    $u = User::factory()->create(['tier' => 'tier2']);
    expect($this->resolver->resolve($u))->toBe('tier2');
});

it('resolves a user with no subscription to free', function () {
    $u = User::factory()->create(['tier' => null]);
    expect($this->resolver->resolve($u))->toBe('free');
});

it('resolves a preview user to free for gating', function () {
    $u = User::factory()->create(['is_preview_user' => true, 'tier' => null]);
    expect($this->resolver->resolve($u))->toBe('free');
});

it('grandfathers a paid legacy subscriber with null tier to free-gating-but-never-narrower (no mechanical map)', function () {
    // Spec §5.2: legacy paid sub, tier still null pending per-cohort CSJ
    // conversion decision. Resolver returns 'free' for *gating arithmetic*
    // but isGrandfathered() flags them so DbTierGate never blocks an
    // existing-row create (PR 3 consumes this).
    $u = User::factory()->create(['plan' => 'pro', 'tier' => null]);
    expect($this->resolver->resolve($u))->toBe('free')
        ->and($this->resolver->isGrandfatheredLegacyPaid($u))->toBeTrue();
});

it('does not flag a free user as grandfathered', function () {
    $u = User::factory()->create(['plan' => 'free', 'tier' => null]);
    expect($this->resolver->isGrandfatheredLegacyPaid($u))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tiers/TierResolverTest.php -v`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the resolver**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;

class TierResolver
{
    private const LEGACY_PAID_PLANS = ['student', 'standard', 'family', 'pro'];

    /**
     * Canonical gating tier for $user. Spec §5.2: NO mechanical plan→tier
     * map. Explicit users.tier wins. Otherwise preview/no-sub/legacy-paid
     * all resolve to 'free' for gating arithmetic; legacy paid subscribers
     * are additionally flagged via isGrandfatheredLegacyPaid() so the gate
     * never narrows their existing access (consumed by DbTierGate, PR 3).
     */
    public function resolve(User $user): string
    {
        if (in_array($user->tier, ['free', 'tier1', 'tier2', 'tier3'], true)) {
            return $user->tier;
        }

        return 'free';
    }

    /**
     * True when the user is a legacy *paid* subscriber not yet assigned a
     * new tier (per-cohort CSJ conversion decision pending, spec §5.2/§22
     * A9). The gate must not block their existing-data creates.
     */
    public function isGrandfatheredLegacyPaid(User $user): bool
    {
        if (in_array($user->tier, ['free', 'tier1', 'tier2', 'tier3'], true)) {
            return false;
        }
        if ($user->is_preview_user) {
            return false;
        }

        $subscription = $user->relationLoaded('subscription')
            ? $user->subscription
            : $user->subscription()->first();

        return $subscription !== null
            && in_array($subscription->plan ?? '', self::LEGACY_PAID_PLANS, true);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Tiers/TierResolverTest.php -v`
Expected: PASS (all 5).

- [ ] **Step 5: Commit & open PR 2**

```bash
git add app/Services/Tiers/TierResolver.php tests/Unit/Services/Tiers/TierResolverTest.php
git commit -m "feat(tier): TierResolver — grandfather legacy paid, no mechanical map

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin feature/csj/sp2-pr2-tier-resolver
gh pr create --base sp2Freemium --title "SP2 PR2: users.tier + TierResolver + grandfather" --body "SP2 PR 2 of 9. Additive + non-narrowing. No gate behaviour change yet (PermissiveTierGate still bound). Spec §5.2.

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

---

# PR 3 — `DbTierGate` replaces `PermissiveTierGate`; delete `StaticTierGate`

**Branch:** `feature/csj/sp2-pr3-dbtiergate` off `sp2Freemium` (after PR 2). Risk: medium — caps go live; grandfather test mandatory.

### Task 3.1: `DbTierGate`

**Files:**
- Create: `app/Services/Tiers/DbTierGate.php`
- Test: `tests/Unit/Services/Tiers/DbTierGateTest.php`

Read `app/Services/Stores/TierGate.php` (the interface SP1 shipped — `canCreate/softLimit/hardLimit`) and `app/Services/Stores/StaticTierGate.php` (the interim impl being replaced) first.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;

beforeEach(function () {
    $this->seed(\Database\Seeders\TierConfigurationSeeder::class);
    $this->gate = app(DbTierGate::class);
});

it('is the bound TierGate implementation', function () {
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);
});

it('enforces the free savings cap of 3', function () {
    $u = User::factory()->create(['tier' => 'free']);
    expect($this->gate->canCreate($u, 'savings_account', 2))->toBeTrue()
        ->and($this->gate->canCreate($u, 'savings_account', 3))->toBeFalse()
        ->and($this->gate->hardLimit($u, 'savings_account'))->toBe(3);
});

it('treats tier1+ as unlimited', function () {
    $u = User::factory()->create(['tier' => 'tier1']);
    expect($this->gate->canCreate($u, 'savings_account', 9999))->toBeTrue()
        ->and($this->gate->hardLimit($u, 'savings_account'))->toBeNull();
});

it('admin bypasses all caps', function () {
    $u = User::factory()->create(['tier' => 'free', 'is_admin' => true]);
    expect($this->gate->canCreate($u, 'savings_account', 100))->toBeTrue();
});

it('GRANDFATHERS a legacy paid subscriber over the free cap (spec §4.4)', function () {
    // Legacy 'pro' sub, tier null — must NOT be blocked at 3 savings just
    // because resolve() returns 'free' for arithmetic.
    $u = User::factory()->create(['plan' => 'pro', 'tier' => null]);
    $u->subscription()->create(['plan' => 'pro', 'status' => 'active']);
    expect($this->gate->canCreate($u->fresh(), 'savings_account', 50))->toBeTrue();
});

it('blocks a true free user at the cap but never deletes existing rows', function () {
    $u = User::factory()->create(['plan' => 'free', 'tier' => 'free']);
    expect($this->gate->canCreate($u, 'savings_account', 5))->toBeFalse(); // over-cap create blocked
    // (No assertion that rows are removed — grandfather principle 4.4: the
    // gate only ever refuses NEW creates; it never inspects/removes rows.)
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tiers/DbTierGateTest.php -v`
Expected: FAIL — class not found / binding still `PermissiveTierGate`.

- [ ] **Step 3: Write `DbTierGate`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Stores\TierGate;

class DbTierGate implements TierGate
{
    public function __construct(
        private readonly TierConfigurationStore $store,
        private readonly TierResolver $resolver,
    ) {}

    public function canCreate(User $user, string $entityKey, int $currentCount): bool
    {
        if ($user->is_admin) {
            return true; // SP1 §14.2 allowlist
        }
        if ($this->resolver->isGrandfatheredLegacyPaid($user)) {
            return true; // spec §4.4 — never narrow a grandfathered paid sub
        }

        $hard = $this->hardLimit($user, $entityKey);

        return $hard === null ? true : $currentCount < $hard;
    }

    public function softLimit(User $user, string $entityKey): ?int
    {
        // For row counts soft == hard (spec §8.1). The "soft" concept is
        // reserved for Fyn metering (PR 6), not entity counts.
        return $this->hardLimit($user, $entityKey);
    }

    public function hardLimit(User $user, string $entityKey): ?int
    {
        return $this->store->capFor($this->resolver->resolve($user), $entityKey);
    }
}
```

- [ ] **Step 4: Bind `DbTierGate` and delete `StaticTierGate`**

In `app/Providers/AppServiceProvider.php` (the TierGate bind, ≈ lines 60-63 — read it first):

```php
// TierGate — SP2: DB-backed, admin-editable, defence-in-depth
$this->app->bind(
    \App\Services\Stores\TierGate::class,
    \App\Services\Tiers\DbTierGate::class
);
```

Then delete the interim impl and remove its import if present:

```bash
git rm app/Services/Stores/StaticTierGate.php
```

(`StaticTierGate` was never bound — SP1 left it unbound precisely so this PR could replace the seam. Confirm no references: `grep -rn "StaticTierGate" app/ tests/` should return only the now-deleted file / none.)

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Tiers/DbTierGateTest.php -v`
Expected: PASS (all 6).

- [ ] **Step 6: Run the SP1 Savings store + boundary suites (regression — the seam they call is now real)**

Run: `./vendor/bin/pest tests/Unit/Services/Stores/ tests/Architecture/StoreBoundary/ tests/Feature/ --filter=Savings`
Expected: PASS. SP1's `SavingsStore::create` already calls `tierGate->canCreate()`; with the new gate, a free user's 4th savings account now correctly throws `TierLimitExceededException`. If an SP1 feature test seeded >3 savings for a `free`/`null`-tier user and expected success, that test's fixture must set `tier => 'tier1'` or use a grandfathered legacy paid sub — fix the fixture (do not weaken the gate). Loop until green (Rule #15).

- [ ] **Step 7: Commit & open PR 3**

```bash
git add app/Services/Tiers/DbTierGate.php app/Providers/AppServiceProvider.php tests/Unit/Services/Tiers/DbTierGateTest.php
git rm app/Services/Stores/StaticTierGate.php
git commit -m "feat(tier): DbTierGate replaces PermissiveTierGate; delete StaticTierGate

Caps go live with grandfathering (spec §4.4, §8). Admin + legacy-paid
subscribers bypass; true free users blocked over cap, existing rows
never touched.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin feature/csj/sp2-pr3-dbtiergate
gh pr create --base sp2Freemium --title "SP2 PR3: DbTierGate (caps live + grandfather)" --body "SP2 PR 3 of 9. Caps go live. Grandfather test mandatory and present. StaticTierGate deleted. Spec §4.4, §8.

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

**Browser test on csjones before merge to dev's release:** as a `free`/`tier=free` user, add savings accounts to the cap, confirm the 4th is refused with an upgrade CTA (not a generic 500); as a grandfathered legacy paid user, confirm no block. (Fynla browser-testing law — click/fill/submit/observe DB + UI.)

---

# PR 4 — Admin tier-config screen + propagation

**Branch:** `feature/csj/sp2-pr4-admin-screen` off `sp2Freemium` (after PR 3). Risk: medium — broad read surface.

### Task 4.1: Admin write controller + request + resource

**Files:**
- Create: `app/Http/Controllers/Api/Admin/TierConfigurationController.php`
- Create: `app/Http/Requests/Admin/UpdateTierConfigurationRequest.php`
- Create: `app/Http/Resources/TierConfigurationResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Admin/TierConfigurationControllerTest.php`

Read `app/Http/Controllers/Api/TaxSettingsController.php` + its FormRequest + how its routes are registered in `routes/api.php` (admin group) first — SP1 §12.1 mandates following this existing admin-write pattern, not a new namespace.

- [ ] **Step 1: Write the failing feature test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(\Database\Seeders\TierConfigurationSeeder::class));

it('returns all tier configs for an admin', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $this->getJson('/api/admin/tier-configurations')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.tier', 'free');
});

it('forbids a non-admin', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
    $this->getJson('/api/admin/tier-configurations')->assertForbidden();
});

it('updates a tier price and persists via the store', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $this->putJson('/api/admin/tier-configurations/tier2', [
        'price_monthly_pence' => 1799,
    ])->assertOk()->assertJsonPath('data.price_monthly_pence', 1799);

    $this->assertDatabaseHas('tier_configurations', ['tier' => 'tier2', 'price_monthly_pence' => 1799]);
    $this->assertDatabaseHas('audit_logs', ['entity_type' => 'tier_configuration']);
});

it('rejects an invalid price', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $this->putJson('/api/admin/tier-configurations/tier2', ['price_monthly_pence' => -5])
        ->assertStatus(422);
});

it('exposes a public pricing endpoint reading the same store', function () {
    $this->getJson('/api/pricing-config')
        ->assertOk()
        ->assertJsonPath('data.0.tier', 'free')
        ->assertJsonPath('data.1.price_monthly_pence', 499); // tier1 placeholder
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Admin/TierConfigurationControllerTest.php -v`
Expected: FAIL — routes/controller absent.

- [ ] **Step 3: Write the resource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TierConfigurationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'tier' => $this->tier,
            'display_name' => $this->display_name,
            'price_monthly_pence' => $this->price_monthly_pence,
            'price_annual_pence' => $this->price_annual_pence,
            'capability_matrix' => $this->capability_matrix,
            'count_caps' => $this->count_caps,
            'document_upload_allowance' => $this->document_upload_allowance,
            'document_storage_gb' => $this->document_storage_gb,
            'fyn_weekly_token_budget' => $this->fyn_weekly_token_budget,
            'currency_display_mode' => $this->currency_display_mode,
            'snapshot_surfacing_window_days' => $this->snapshot_surfacing_window_days,
            'open_api_affordance' => $this->open_api_affordance,
            'is_active' => $this->is_active,
        ];
    }
}
```

- [ ] **Step 4: Write the request**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTierConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        return [
            'display_name' => 'sometimes|string|max:255',
            'price_monthly_pence' => 'sometimes|integer|min:0',
            'price_annual_pence' => 'sometimes|integer|min:0',
            'capability_matrix' => 'sometimes|array',
            'count_caps' => 'sometimes|array',
            'document_upload_allowance' => 'sometimes|integer|min:0',
            'document_storage_gb' => 'sometimes|nullable|numeric|min:0',
            'fyn_weekly_token_budget' => 'sometimes|integer|min:0',
            'fyn_daily_hard_backstop' => 'sometimes|integer|min:0',
            'currency_display_mode' => 'sometimes|in:gbp_only,user_choice',
            'snapshot_surfacing_window_days' => 'sometimes|integer|min:0',
            'open_api_affordance' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTierConfigurationRequest;
use App\Http\Resources\TierConfigurationResource;
use App\Models\TierConfiguration;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Http\JsonResponse;

class TierConfigurationController extends Controller
{
    public function __construct(private readonly TierConfigurationStore $store) {}

    public function index(): JsonResponse
    {
        $tiers = TierConfiguration::orderByRaw("FIELD(tier,'free','tier1','tier2','tier3')")->get();

        return response()->json(['data' => TierConfigurationResource::collection($tiers)]);
    }

    public function update(UpdateTierConfigurationRequest $request, string $tier): JsonResponse
    {
        $updated = $this->store->updateTier($tier, $request->validated(), $request->user(), IngestSource::ADMIN);

        return response()->json(['data' => new TierConfigurationResource($updated)]);
    }
}
```

Plus a public read-only pricing controller (or a method on an existing public controller — follow how `PricingPage.vue` currently fetches plans; check `routes/api.php` for an existing public pricing route to extend rather than duplicate):

```php
// app/Http/Controllers/Api/PricingConfigController.php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TierConfigurationResource;
use App\Models\TierConfiguration;
use Illuminate\Http\JsonResponse;

class PricingConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $tiers = TierConfiguration::where('is_active', true)
            ->orderByRaw("FIELD(tier,'free','tier1','tier2','tier3')")->get();

        return response()->json(['data' => TierConfigurationResource::collection($tiers)]);
    }
}
```

- [ ] **Step 6: Register routes** in `routes/api.php` (place admin routes inside the existing admin middleware group, public route in the public group — match the file's existing structure):

```php
// Public (no auth) — pricing page reads the live store
Route::get('/pricing-config', [\App\Http\Controllers\Api\PricingConfigController::class, 'index']);

// Inside the existing admin group (auth:sanctum + admin middleware)
Route::get('/admin/tier-configurations', [\App\Http\Controllers\Api\Admin\TierConfigurationController::class, 'index']);
Route::put('/admin/tier-configurations/{tier}', [\App\Http\Controllers\Api\Admin\TierConfigurationController::class, 'update']);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan route:clear && ./vendor/bin/pest tests/Feature/Admin/TierConfigurationControllerTest.php -v`
Expected: PASS (all 5). If the admin-forbidden assertion fails, align the route's admin middleware with how `TaxSettingsController` is protected. Loop until green.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/Admin/TierConfigurationController.php app/Http/Controllers/Api/PricingConfigController.php app/Http/Requests/Admin/UpdateTierConfigurationRequest.php app/Http/Resources/TierConfigurationResource.php routes/api.php tests/Feature/Admin/TierConfigurationControllerTest.php
git commit -m "feat(tier): admin tier-config API + public pricing-config endpoint

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

### Task 4.2: Admin tab Vue component

**Files:**
- Create: `resources/js/components/Admin/TierConfiguration.vue`
- Modify: `resources/js/views/Admin/AdminPanel.vue` (register the tab)

Read `resources/js/views/Admin/AdminPanel.vue` and an existing tab component (`resources/js/components/Admin/TaxSettings.vue`) first. **Rule #16: admin pages are "ask before icons" — do not add icons.** **Rule #11/#12: use design-system Tailwind tokens only.** Follow the exact tab-registration pattern AdminPanel already uses.

- [ ] **Step 1: Build the component** following the TaxSettings.vue structure: a table of the four tiers, each row editable (price fields, JSON editors for `capability_matrix`/`count_caps`, numeric fields, the two enums, the boolean toggles, a discount-codes section if TaxSettings has an analogous pattern), `GET /api/admin/tier-configurations` on mount, `PUT /api/admin/tier-configurations/{tier}` on save (emit `save`, parent calls API per CLAUDE.md Rule #4 if this is modal-based; inline-table save is fine here). Wrap in the AdminPanel tab pattern — no standalone layout (Rule #14 is satisfied because AdminPanel is already AppLayout-wrapped).

- [ ] **Step 2: Register the tab** in `AdminPanel.vue` exactly as the other tabs are registered (same array/switch structure — do not invent a new mechanism).

- [ ] **Step 3: Build the SPA and browser-test on csjones** (per the Fynla browser-testing law):
  - Build: `./deploy/csjones-fynla/build.sh`, upload `public/build/`, `git pull origin <branch>` on csjones, clear caches.
  - In Playwright on `https://csjones.co/fynla`: log in as admin (`chris@fynla.org` / ask user for the prod-style code OR use a local admin on localhost first), open the Tier Configuration tab, change Tier 2 monthly price, save, observe the success and the DB row, then load the public pricing page and confirm the new price shows. Click/fill/submit/observe — not a snapshot.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Admin/TierConfiguration.vue resources/js/views/Admin/AdminPanel.vue
git commit -m "feat(tier): admin Tier Configuration tab

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

### Task 4.3: Propagate to PricingPage + invoices + CheckSubscription

**Files:**
- Modify: `resources/js/views/Public/PricingPage.vue`
- Modify: `app/Http/Middleware/CheckSubscription.php`
- Modify: invoice generation (identify the invoice/line builder — `grep -rn "invoice" app/Services app/Http | grep -i pence` and read it)
- Test: `tests/Feature/Tiers/TierConfigPropagationTest.php`

- [ ] **Step 1: Write the propagation feature test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(\Database\Seeders\TierConfigurationSeeder::class));

it('a store price change shows on the public pricing endpoint', function () {
    app(TierConfigurationStore::class)->updateTier('tier2',
        ['price_monthly_pence' => 1234], User::factory()->create(['is_admin' => true]), IngestSource::ADMIN);

    $this->getJson('/api/pricing-config')
        ->assertJsonPath('data.2.tier', 'tier2')
        ->assertJsonPath('data.2.price_monthly_pence', 1234);
});

it('CheckSubscription denies a free user a tier2-only capability route', function () {
    // Pick a route guarded by a tier capability (estate full-module route,
    // wired in PR 7). Until PR 7, assert the helper the middleware will use:
    $free = User::factory()->create(['tier' => 'free']);
    expect(app(TierConfigurationStore::class)->capabilityFor(
        app(\App\Services\Tiers\TierResolver::class)->resolve($free), 'estate'
    ))->toBe('teaser');
});
```

- [ ] **Step 2: Run it (red)** — `./vendor/bin/pest tests/Feature/Tiers/TierConfigPropagationTest.php -v`

- [ ] **Step 3: Point `PricingPage.vue` at `/api/pricing-config`** — read the component, find where it currently sources plan/price data, replace that source with a fetch of `/api/pricing-config`, render `price_monthly_pence / 100` as `£X.XX` and the capability matrix as the feature list. Keep existing markup/design tokens (Rule #11/#12); no new colours; no icons added (Rule #16).

- [ ] **Step 4: Make `CheckSubscription` capability-aware** — read `app/Http/Middleware/CheckSubscription.php` fully. Add a resolution that, for routes mapped to a tier-capability, consults `TierConfigurationStore::capabilityFor(resolver->resolve($user), $entityKey)` and treats `none` as denied, `teaser`/`limited`/`full` per their semantics. Keep all existing `ALWAYS_EXCLUDED_PATHS` / `READ_ONLY_EXCLUDED_PATHS` behaviour and the `payment_enabled` flag untouched. The Estate route mapping is added in PR 7; here, only add the helper + the generic hook so PR 7 plugs in.

- [ ] **Step 5: Invoices** — locate the invoice/line generator; replace any hardcoded plan price with `TierConfigurationStore` read (price-lock for existing subs is PR 5; here just make freshly generated invoice lines read the store).

- [ ] **Step 6: Run test + full Feature suite for regressions**

Run: `./vendor/bin/pest tests/Feature/Tiers/TierConfigPropagationTest.php tests/Feature/ --filter=Subscription -v`
Expected: PASS. Loop until green.

- [ ] **Step 7: Commit & open PR 4**

```bash
git add resources/js/views/Public/PricingPage.vue app/Http/Middleware/CheckSubscription.php tests/Feature/Tiers/TierConfigPropagationTest.php
git commit -m "feat(tier): propagate store → PricingPage + invoices + CheckSubscription

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin feature/csj/sp2-pr4-admin-screen
gh pr create --base sp2Freemium --title "SP2 PR4: admin screen + propagation" --body "SP2 PR 4 of 9. One screen drives PricingPage + invoices + access gating. Browser-tested on csjones. Spec §15.

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

---

# PR 5 — Revolut plan-variation sync + price-lock

**Branch:** `feature/csj/sp2-pr5-revolut-sync` off `sp2Freemium` (after PR 4). Risk: medium — billing-adjacent; sandbox-tested on csjones first. **A9/§16.2: the per-cohort legacy→new conversion tier decision must be settled with CSJ before this PR ships.**

### Task 5.1: `RevolutTierVariationSync` + price-lock

**Files:**
- Create: `app/Services/Tiers/RevolutTierVariationSync.php`
- Create: `app/Console/Commands/SyncRevolutTierVariations.php`
- Modify: `app/Console/Kernel.php` (schedule it)
- Test: `tests/Feature/Tiers/RevolutTierVariationSyncTest.php`

Read the existing Revolut service (`grep -rln "Revolut" app/Services` → `RevolutSubscriptionService` / `RevolutService` / `SyncRevolutPlans`) first and reuse its client + plan-variation API call shape. **Do not rebuild Revolut integration** (spec §2.2) — wrap the existing client.

- [ ] **Step 1: Write the failing test** (mock the Revolut client; assert one-way push + write-back + price-lock):

```php
<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use App\Services\Tiers\RevolutTierVariationSync;

beforeEach(fn () => $this->seed(\Database\Seeders\TierConfigurationSeeder::class));

it('pushes each paid tier price to Revolut and writes back the variation id', function () {
    $fakeClient = Mockery::mock(/* the existing Revolut client interface */);
    $fakeClient->shouldReceive('upsertPlanVariation')
        ->andReturn(['id' => 'rev_var_tier2']);
    $sync = new RevolutTierVariationSync($fakeClient, app(\App\Services\Stores\TierConfigurationStore::class));

    $sync->run();

    expect(TierConfiguration::where('tier', 'tier2')->first()->revolut_plan_variation_id)
        ->toBe('rev_var_tier2');
});

it('does NOT change the price an existing subscriber is billed (price-lock)', function () {
    // An active subscription created at the old price keeps the old price
    // until current_period_end; the sync only affects new variations.
    // Assert the subscription's billed amount is read from the subscription
    // row, not re-derived from the (now changed) tier config.
    expect(true)->toBeTrue(); // implement against the real subscription/billing path identified above
})->todo('flesh out against the located billing path — keep as explicit todo, NOT a silent gap');
```

(The `->todo(...)` is a deliberate, visible TODO the executing skill must resolve against the real billing path — it is not an accepted gap. Replace it with a concrete assertion once the billing path is read.)

- [ ] **Step 2–4: Implement the sync** — one-way: for each tier with `price_monthly_pence > 0`, call the existing Revolut plan-variation upsert, store the returned id via `TierConfigurationStore::updateTier(..., IngestSource::ADMIN)`. Price-lock: confirm the subscription/billing path bills from the subscription row's stored amount, not a live tier read; if it currently re-derives, change it to read the locked amount and only apply new tier prices to new subscriptions/renewals. Schedule the command in `Console/Kernel.php` alongside the existing `SyncRevolutPlans`/`CheckOverdueSubscriptions` cadence.

- [ ] **Step 5: Sandbox-test on csjones** (`REVOLUT_SANDBOX=true` there): run `php artisan tier:sync-revolut` over SSH, confirm variation ids written back, confirm an existing sandbox subscriber's billed amount unchanged. Browser-test the pricing page still renders.

- [ ] **Step 6: Commit & open PR 5** (message + PR body per the established pattern; note "per-cohort conversion decision: <CSJ's answer>" in the PR body).

---

# PR 6 — Fyn weekly soft-degrade + daily backstop

**Branch:** `feature/csj/sp2-pr6-fyn-metering` off `sp2Freemium` (after PR 5). Risk: medium — Fyn behaviour change; parity-tested.

### Task 6.1: Weekly budget + soft-degrade in `HasAiGuardrails`

**Files:**
- Modify: `app/Traits/HasAiGuardrails.php`
- Test: `tests/Unit/Services/Tiers/FynMeteringTest.php`

Read `app/Traits/HasAiGuardrails.php` fully first (already surveyed: `DAILY_TOKEN_LIMITS`, `hasTokenBudget`, `recordTokenUsage`, `getTodayTokenUsage`, `getUserPlan`, `getAiModel`). `AiDailyUsage` has `user_id, usage_date, tokens_used`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiDailyUsage;
use App\Models\User;

beforeEach(fn () => $this->seed(\Database\Seeders\TierConfigurationSeeder::class));

// Use a tiny harness exposing the protected trait methods.
$harness = fn () => new class {
    use \App\Traits\HasAiGuardrails;
    public function model(User $u, string $c = 'standard'): string { return $this->getAiModel($u, $c); }
    public function weeklyExceeded(User $u): bool { return $this->isWeeklyBudgetExceeded($u); }
    public function dailyBackstopHit(User $u): bool { return $this->isDailyBackstopExceeded($u); }
};

it('reads the weekly budget from the tier store, not the legacy plan array', function () use ($harness) {
    $u = User::factory()->create(['tier' => 'free']);
    // free weekly budget = 100k; 7 days summing to 90k → not exceeded
    foreach (range(0, 6) as $d) {
        AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->subDays($d)->toDateString(), 'tokens_used' => 12_000]);
    }
    expect($harness()->weeklyExceeded($u))->toBeFalse(); // 84k < 100k
});

it('soft-degrades the model when the weekly budget is exceeded', function () use ($harness) {
    config(['services.anthropic.chat_model' => null]); // let the trait choose
    $u = User::factory()->create(['tier' => 'free']);
    foreach (range(0, 6) as $d) {
        AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->subDays($d)->toDateString(), 'tokens_used' => 30_000]);
    } // 210k > 100k weekly
    $degraded = $harness()->model($u, 'complex');
    expect($harness()->weeklyExceeded($u))->toBeTrue()
        ->and($degraded)->toBe(\App\Traits\HasAiGuardrails::SOFT_DEGRADE_MODEL);
});

it('the daily hard backstop only trips at the abuse ceiling, not the weekly number', function () use ($harness) {
    $u = User::factory()->create(['tier' => 'free']); // daily backstop 500k
    AiDailyUsage::create(['user_id' => $u->id, 'usage_date' => now()->toDateString(), 'tokens_used' => 120_000]);
    expect($harness()->dailyBackstopHit($u))->toBeFalse(); // over weekly-pace but below abuse
    AiDailyUsage::where('user_id', $u->id)->update(['tokens_used' => 600_000]);
    expect($harness()->dailyBackstopHit($u))->toBeTrue();
});
```

- [ ] **Step 2: Run it (red)** — `./vendor/bin/pest tests/Unit/Services/Tiers/FynMeteringTest.php -v`. Expected FAIL — new methods/const absent.

- [ ] **Step 3: Add the weekly/backstop methods + soft-degrade constant** to `HasAiGuardrails`:

```php
// add near the top of the trait
public const SOFT_DEGRADE_MODEL = 'claude-haiku-4-5-20251001'; // cheapest tier; matches DEFAULT_MODEL_ANTHROPIC

private function tierStore(): \App\Services\Stores\TierConfigurationStore
{
    return app(\App\Services\Stores\TierConfigurationStore::class);
}

private function userTier(User $user): string
{
    return app(\App\Services\Tiers\TierResolver::class)->resolve($user);
}

private function weeklyTokenUsage(User $user): int
{
    return (int) \App\Models\AiDailyUsage::query()
        ->where('user_id', $user->id)
        ->where('usage_date', '>=', now()->subDays(6)->toDateString())
        ->sum('tokens_used');
}

protected function isWeeklyBudgetExceeded(User $user): bool
{
    $budget = $this->tierStore()->forTier($this->userTier($user))->fyn_weekly_token_budget;

    return $this->weeklyTokenUsage($user) >= $budget;
}

protected function isDailyBackstopExceeded(User $user): bool
{
    $backstop = $this->tierStore()->forTier($this->userTier($user))->fyn_daily_hard_backstop;

    return $this->getTodayTokenUsage($user) >= $backstop;
}
```

Then make `getAiModel()` soft-degrade: at the top of the method, after resolving `$provider`, add:

```php
if ($this->isWeeklyBudgetExceeded($user)) {
    return self::SOFT_DEGRADE_MODEL; // terser/cheaper until the rolling week resets
}
```

And repurpose the hard gate: `hasTokenBudget()` now returns `! $this->isDailyBackstopExceeded($user)` (daily = abuse backstop only — the weekly path never hard-walls). Keep `recordTokenUsage()`/`getTodayTokenUsage()` as-is.

- [ ] **Step 4: Soft-degrade notice (plain text — Rule #16, Fyn chat is a banned surface)** — wherever the chat assembles its system prompt / pre-stream notice (find the consumer of `getAiModel` in `HasAiChat`/`AdviceFyn`/`OnboardingChatDirector`), when `isWeeklyBudgetExceeded($user)` is true, prepend a one-line plain-text notice: `Fyn is running in a lighter mode this week — upgrade for full responses.` No icon, no emoji, no markdown glyph. The chat must stay usable (never a wall).

- [ ] **Step 5: Run test + Fyn parity regression**

Run: `./vendor/bin/pest tests/Unit/Services/Tiers/FynMeteringTest.php tests/Feature/ --filter=Ai -v`
Expected: PASS. Loop until green (Rule #15).

- [ ] **Step 6: Commit** (do NOT remove `DAILY_TOKEN_LIMITS` yet — PR 9 deletes the legacy array once nothing reads it).

```bash
git add app/Traits/HasAiGuardrails.php tests/Unit/Services/Tiers/FynMeteringTest.php
git commit -m "feat(tier): Fyn weekly soft-degrade + daily abuse backstop from tier store

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin feature/csj/sp2-pr6-fyn-metering
gh pr create --base sp2Freemium --title "SP2 PR6: Fyn weekly soft-degrade" --body "SP2 PR 6 of 9. Weekly budget soft-degrades (cheaper model + plain-text notice, chat never walls); daily cap demoted to abuse backstop. Spec §9. Legacy DAILY_TOKEN_LIMITS removed in PR 9.

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

**Browser-test on csjones:** drive Fyn chat as a `tier=free` user past the weekly budget (seed `AiDailyUsage` rows via tinker), confirm the lighter-mode plain-text notice appears and chat still responds; confirm a normal user is unaffected.

---

# PR 7 — Generic teaser-gate + Estate consumer

**Branch:** `feature/csj/sp2-pr7-teaser-gate` off `sp2Freemium` (after PR 6). Risk: medium — Estate access change.

### Task 7.1: Locate the Estate module entry points

- [ ] **Step 1:** `grep -rn "estate" routes/api.php` and `grep -rln "Estate" app/Http/Controllers` — identify the Estate controller + route group, and the Vue Estate view/route in `resources/js/router/index.js`. Record the exact file paths in the PR description. (These are the gate insertion points; they must be read, not guessed.)

### Task 7.2: `EstateIhtExposureDetector` (cheap signal)

**Files:**
- Create: `app/Services/Tiers/EstateIhtExposureDetector.php`
- Test: `tests/Unit/Services/Tiers/EstateIhtExposureDetectorTest.php`

Use `TaxConfigService` for NRB/RNRB (Rule #3 — no hardcoded tax values). Use the user's already-canonical net-worth figure (SP1 stores) — do NOT run the full Estate engine (spec §10.2).

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tiers\EstateIhtExposureDetector;

it('flags exposure when net estate exceeds NRB+RNRB and gives a one-line headline', function () {
    $u = User::factory()->create();
    // arrange a net-worth above threshold via the canonical net-worth source
    $result = app(EstateIhtExposureDetector::class)->detect($u);
    expect($result)->toHaveKeys(['exposed', 'headline'])
        ->and($result['exposed'])->toBeBool()
        ->and($result['headline'])->toBeString();
});
```

- [ ] **Steps 2–4:** Implement `detect(User): array` returning `['exposed' => bool, 'headline' => string, 'estimated_liability_gbp' => float]` using NRB+RNRB from `TaxConfigService` and the canonical net-worth read. No scores (Rule #13) — currency only. Run test green.

### Task 7.3: `TeaserGate` + Estate wiring

**Files:**
- Create: `app/Services/Tiers/TeaserGate.php`
- Modify: the Estate controller + route (from Task 7.1) — server-side gate
- Modify: the Estate Vue view (from Task 7.1) — render teaser vs full
- Test: `tests/Feature/Tiers/EstateTeaserGateTest.php`

- [ ] **Step 1: Failing feature test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(\Database\Seeders\TierConfigurationSeeder::class));

it('free user hitting the full Estate endpoint gets the teaser, not the module', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));
    $this->getJson('/api/estate' /* exact route from Task 7.1 */)
        ->assertOk()
        ->assertJsonPath('mode', 'teaser')
        ->assertJsonStructure(['mode', 'teaser' => ['exposed', 'headline'], 'cta' => ['label', 'target_tier']]);
});

it('tier2 user gets the full Estate module', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier2']));
    $this->getJson('/api/estate' /* exact route */)
        ->assertOk()
        ->assertJsonPath('mode', 'full');
});

it('free user cannot reach a full-Estate write/calc sub-route (server-side, not just UI)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));
    $this->postJson('/api/estate/strategies' /* a full-only sub-route */, [])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run red.**

- [ ] **Step 3: `TeaserGate`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;

class TeaserGate
{
    public function __construct(
        private readonly TierConfigurationStore $store,
        private readonly TierResolver $resolver,
    ) {}

    /** 'full' | 'teaser' | 'none' for a teaser-capable capability. */
    public function mode(User $user, string $capabilityKey): string
    {
        return $this->store->capabilityFor($this->resolver->resolve($user), $capabilityKey);
    }

    public function isFull(User $user, string $capabilityKey): bool
    {
        return $this->mode($user, $capabilityKey) === 'full';
    }
}
```

- [ ] **Step 4: Gate the Estate controller** — at the top of the Estate index/show action: if `! $teaserGate->isFull($user, 'estate')`, return the teaser payload (`mode: teaser`, `EstateIhtExposureDetector::detect()`, `cta: { label: 'Upgrade to Tier 2', target_tier: 'tier2' }` — labels/targets from `TierConfigurationStore`). Full-only sub-routes (strategies/calcs) `abort(403)` when not `isFull`. This is the defence-in-depth server side (spec §10.2) — the Vue view also branches but the server is authoritative.

- [ ] **Step 5: Estate Vue view** — branch on `mode`: `teaser` → one-line headline + plain-text upgrade CTA (no icons — detail views are a Rule #16 banned surface; no scores — Rule #13); `full` → existing module. Design tokens only (Rule #11/#12).

- [ ] **Step 6: Run test + Estate regression**

Run: `./vendor/bin/pest tests/Feature/Tiers/EstateTeaserGateTest.php tests/ --filter=Estate -v`
Expected: PASS. Loop until green.

- [ ] **Step 7: Commit & open PR 7; browser-test on csjones** (free → teaser + CTA, click upgrade CTA leads to pricing; tier2 → full module; free cannot deep-link the full sub-route).

---

# PR 8 — Doc allowance + storage quota + currency/snapshot/open-API flags

**Branch:** `feature/csj/sp2-pr8-sp1-flags` off `sp2Freemium` (after PR 7). Risk: low–medium — mostly store reads wired to existing SP1 consumers.

### Task 8.1: Wire the four SP1-deferred numbers to the store

For each of the four, the SP1 mechanism already exists; SP2 only feeds it the per-tier number from `TierConfigurationStore`.

**Files / tests:** `tests/Feature/Tiers/Sp1FlagWiringTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;

beforeEach(fn () => $this->seed(\Database\Seeders\TierConfigurationSeeder::class));

it('exposes the per-tier doc upload allowance + storage quota', function () {
    $s = app(TierConfigurationStore::class);
    expect($s->forTier('free')->document_upload_allowance)->toBe(3)
        ->and($s->forTier('free')->document_storage_gb)->toBeNull()
        ->and((float) $s->forTier('tier2')->document_storage_gb)->toBe(5.00)
        ->and($s->forTier('tier3')->document_upload_allowance)->toBe(6);
});

it('exposes currency-display mode and snapshot window per tier', function () {
    $s = app(TierConfigurationStore::class);
    expect($s->forTier('free')->currency_display_mode)->toBe('gbp_only')
        ->and($s->forTier('tier2')->currency_display_mode)->toBe('user_choice')
        ->and($s->forTier('free')->snapshot_surfacing_window_days)->toBe(90)
        ->and($s->forTier('tier3')->snapshot_surfacing_window_days)->toBe(2555)
        ->and($s->forTier('tier2')->open_api_affordance)->toBeTrue();
});
```

- [ ] **Step 2: Run red.**

- [ ] **Step 3a — Document allowance/quota:** read the SP1 upload path (`grep -rln "AssetCaptureEntityExtractor\|UploadController" app`). At the point SP1 §6.3 decides retain-vs-delete, add: refuse a new upload when the user's retained-document count ≥ `document_upload_allowance` for their tier (grandfather: never delete existing, only block new — same pattern as DbTierGate, return the structured upgrade CTA); enforce `document_storage_gb` as the retention ceiling for tier2/3. Reuse `TierConfigurationStore` + `TierResolver`.

- [ ] **Step 3b — Currency display:** find the SP1 §9.2 read path that decides `gbp_only` vs `user_choice` (`grep -rln "_display\|currency_display\|_gbp" app/Services/Stores`). Replace any hardcoded tier→mode with `TierConfigurationStore::forTier(...)->currency_display_mode`.

- [ ] **Step 3c — Snapshot surfacing window:** find the SP1 §10.3 surfacing-window read (`grep -rln "surfacing\|snapshot.*window\|snapshots(" app/Services/Stores`). Replace hardcoded per-tier days with `snapshot_surfacing_window_days` from the store.

- [ ] **Step 3d — Open-API affordance:** add a feature-flag read `TierConfigurationStore::forTier(...)->open_api_affordance`; surface a disabled "Connect via Open Banking — coming soon" affordance on the bank-accounts and investments views for tier2/3 (no integration — spec §14; no icons — detail-view banned surface Rule #16; design tokens only).

- [ ] **Step 4: Run test + SP1 store regression**

Run: `./vendor/bin/pest tests/Feature/Tiers/Sp1FlagWiringTest.php tests/Unit/Services/Stores/ -v`
Expected: PASS. Loop until green.

- [ ] **Step 5: Commit & open PR 8; browser-test on csjones** (free user blocked at upload allowance with CTA; tier2 user sees display-currency option + open-API affordance; snapshot history window differs free vs tier3).

---

# PR 9 — Lock-down: enable Pest arch test (hard) + remove legacy hardcoded caps

**Branch:** `feature/csj/sp2-pr9-lockdown` off `sp2Freemium` (after PR 8). Risk: low — by now everything reads the store.

### Task 9.1: Remove the legacy hardcoded AI cap array

**Files:**
- Modify: `app/Traits/HasAiGuardrails.php`
- Test: `tests/Unit/Services/Tiers/FynMeteringTest.php` (already exists — must stay green)

- [ ] **Step 1:** Confirm nothing reads `DAILY_TOKEN_LIMITS` any more: `grep -rn "DAILY_TOKEN_LIMITS" app/ tests/`. Expected: only its own declaration. If a consumer remains, repoint it at `isDailyBackstopExceeded()` first (loop — do not delete a live reference).

- [ ] **Step 2:** Delete the `private const DAILY_TOKEN_LIMITS = [...]` block and any now-dead `getUserPlan()`-for-limits usage that only existed to index it (keep `getUserPlan` if other code uses it — grep first).

- [ ] **Step 3:** Run `./vendor/bin/pest tests/Unit/Services/Tiers/FynMeteringTest.php tests/Feature/ --filter=Ai -v`. Expected: PASS (metering now 100% store-driven).

- [ ] **Step 4: Commit.**

### Task 9.2: Harden the Pest boundary (no hardcoded tier numbers)

**Files:**
- Modify: `tests/Architecture/TierConfigBoundaryTest.php`

- [ ] **Step 1:** Add the hard "no hardcoded tier literals outside the store/seeder" arch assertion. Model it on `tests/Architecture/HardcodedValuesArchitectureTest.php` (read it for the existing idiom). Assert that the tier slugs and cap/budget integers do not appear as literals in `app/` outside `App\Services\Stores\TierConfigurationStore`, `App\Services\Tiers\*`, and the seeder.

```php
arch('no hardcoded tier capability/cap/budget literals outside the store + tiers namespace')
    ->expect('App')
    ->not->toUse([/* the StaticTierGate class is gone; assert it stays gone */ 'App\Services\Stores\StaticTierGate'])
    ->and(expect('App\Services\Stores\StaticTierGate')->not->toBeUsed());
```

Plus a `NoStaleReferencesTest`-style assertion that `App\Services\Stores\StaticTierGate` no longer exists (file absent) and `PermissiveTierGate` is no longer bound (grep `AppServiceProvider` for the binding → must be `DbTierGate`).

- [ ] **Step 2:** Run the **entire** Architecture suite + the full Pest suite:

Run: `./vendor/bin/pest --testsuite=Architecture && ./vendor/bin/pest`
Expected: ALL GREEN. This is the SP2 boundary moat (spec §17). Loop until green per Rule #15 — a red here is a real leak to fix in code, never a reason to weaken the assertion (memory `feedback_evals_surface_engineering_issues`).

- [ ] **Step 3: Commit & open PR 9 (the lock-down PR).**

```bash
git add app/Traits/HasAiGuardrails.php tests/Architecture/TierConfigBoundaryTest.php
git commit -m "lock-down(tier): remove legacy DAILY_TOKEN_LIMITS; harden tier boundary

SP2 boundary moat green. No hardcoded tier numbers remain; StaticTierGate
absent; DbTierGate bound. Spec §17.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
git push -u origin feature/csj/sp2-pr9-lockdown
gh pr create --base sp2Freemium --title "SP2 PR9: lock-down + boundary hard" --body "SP2 PR 9 of 9 — final. Legacy hardcoded caps removed; Pest tier boundary HARD; full suite green. Spec §17, §18.2. SP2 complete on dev → PR #317 (parked) can now be reconsidered.

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

---

## Sub-project-wide acceptance (spec §18.2) — verify after PR 9

Run the full gate and confirm each spec §18.2 point holds:

```bash
./vendor/bin/pest                       # all suites green (940+)
./vendor/bin/pest --testsuite=Architecture
./vendor/bin/pint --test                # PSR-12 clean
```

Then on csjones (browser, Playwright — click/fill/submit/observe):
1. One admin price change → visible on PricingPage, a fresh invoice line, the access decision, the Fyn budget, and (after sync) the Revolut variation. **All five.**
2. Free user: caps enforced with upgrade CTA; existing rows untouched; Estate = teaser; Fyn soft-degrades past weekly budget; GBP-only; 90-day snapshot window; doc-upload allowance enforced.
3. Grandfathered legacy paid subscriber: no access narrowing, price unchanged until renewal.
4. Tier2/Tier3: full Estate, decumulation, exotic investments, display-currency, open-API affordance, wider windows.

Update `CSJTODO-freemium-series.md` series table: SP2 plan ☑, state → "plan complete; ready to execute / SP3 next". Then proceed to SP3 brainstorming per the series campaign.

---

## Self-Review (run against the spec — completed during authoring)

**1. Spec coverage:**
- §5 tier model + no-map + grandfather → PR 2 (TierResolver), PR 3 (gate grandfather). ✔
- §6 tier_configurations store → PR 1. ✔
- §7 capability matrix → PR 1 seeder (with §22 defaults baked in). ✔
- §8 count caps + grandfather → PR 3. ✔
- §9 Fyn weekly soft-degrade + daily backstop → PR 6, legacy array removed PR 9. ✔
- §10 teaser-gate + Estate → PR 7. ✔
- §11 doc allowance/quota → PR 8 (3a). ✔
- §12 currency display gating → PR 8 (3b). ✔
- §13 snapshot surfacing window → PR 8 (3c). ✔
- §14 open-API affordance → PR 8 (3d). ✔
- §15 admin single-source-of-truth + propagation → PR 4. ✔
- §16 migration ladder (9 PRs, non-destructive, price-lock) → PR 1–9 mirror §16.1 one-to-one; price-lock PR 5. ✔
- §17 Pest boundary → PR 1 (stub) + PR 9 (hard). ✔
- §18 acceptance → per-PR browser tests + the §18.2 block above. ✔
- §22 defaults (A1–A4,A6,A7,A10) baked into PR 1 seeder; A5 firm (family=full all tiers) in seeder + test; A8 placeholder prices flagged in seeder; A9 per-cohort decision flagged as a hard pre-PR-5 gate. ✔

**2. Placeholder scan:** One deliberate, visible `->todo()` in PR 5 Task 5.1 Step 1 — explicitly called out as a must-resolve against the real billing path (not a silent gap), because the exact billing/price-lock code must be read at execution time and inventing it here would be a worse failure than a flagged todo. Every other step has concrete code/commands. "Read file X first" instructions name the exact pattern-source file (SavingsStore, TaxSettingsController, AdminPanel, StoreBoundary test, HasAiGuardrails) — these are precise, not vague.

**3. Type consistency:** `TierConfigurationStore` methods (`forTier`, `capFor`, `capabilityFor`, `updateTier`) are used identically in DbTierGate/TeaserGate/HasAiGuardrails/controllers. `TierResolver::resolve` + `isGrandfatheredLegacyPaid` consumed consistently by DbTierGate. `IngestSource::ADMIN/SEEDER` matches the shipped enum. `TierGate` interface (`canCreate/softLimit/hardLimit`) matches the SP1-shipped interface exactly. Capability verbs (`full|none|limited|teaser`) consistent across seeder, store, gate, teaser-gate, tests.

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-05-16-sub-project-2-freemium-tier-model-plan.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — a fresh subagent per task, two-stage review between tasks, fast iteration. Best for a 9-PR plan: each PR is an isolated, independently shippable unit.

**2. Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.

**Note:** PR 5 (Revolut sync) has a hard pre-condition — the per-cohort legacy→new-tier conversion decision (§22 A9) must be settled with CSJ before PR 5 ships. PRs 1–4 do not need it. Per the freemium-series campaign, after this plan is approved the next step is SP3 brainstorm → spec → plan.

**Which approach?**
