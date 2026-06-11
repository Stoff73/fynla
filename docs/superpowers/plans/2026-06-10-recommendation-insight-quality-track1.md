# Recommendation & Insight Quality — Track 1 (dev) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reconcile Fynla's three partial recommendation catalogues into one substance layer, make the savetax guided flow answer users and synthesise a ranked plan, restore the dropped knowledge layer to the unified prompt, and prove it all with the Azlan golden scenario.

**Architecture:** The June `Tax/Strategies` classes stay the quantifiers; the March `tax_action_definitions` table becomes the metadata registry (new `claim_tier`/`required_data`/`sequencing` columns); a new `HouseholdFinancialContext` feeds spouse-aware, allowance-accurate inputs; a new `StrategyPlanComposer` produces the ordered, conflict-resolved plan consumed by the savetax flow, `get_recommendations`, `/api/tax-strategy`, and `/m` unlock cards. All prompt changes are per-turn `FynContextAssembler` layers — `FynSystemPrompt::text()` stays byte-identical (prefix-cache contract).

**Tech Stack:** Laravel 10, Pest, MySQL 8, xAI grok-4.3 live / Anthropic fallback, SSE streaming chat.

**Spec:** `docs/superpowers/specs/2026-06-10-recommendation-insight-quality-design.md`

**Canonical strategy reference:** `/Users/CSJ/Desktop/fynlaBrain/April/April30Updates/savetax-strategy-catalogue.md` (v0.2, CSJ-redlined) defines the 18-strategy catalogue with triggers, mechanisms, priorities, and copy. Seeder metadata (Task 3) takes priorities from it; Tasks 13/14 verify against its #10/#8 "already implemented" notes before creating anything. The vault (`/Users/CSJ/Desktop/fynlaBrain/`) also holds session handovers (June8Updates–June10Updates) and `June8Updates/savetax-math-spec.md` — consult when a task's surrounding history is unclear.

**Verified anchors (do not re-derive):**
- `TaxActionDefinitionService::evaluateActions` is orphaned (zero callers).
- `FinancialPlanningKnowledge` reaches only the legacy path via `AdvicePromptBuilder::buildKnowledgeBlock` (`app/Services/AI/AdvicePromptBuilder.php:189`, `:1113`); `FynContextAssembler` has no knowledge layer.
- ISA YTD answers land in `SavingsAccount.isa_subscription_amount`/`isa_subscription_year` (model `app/Models/SavingsAccount.php:44-45`) but `TaxStrategyMath::estimateIsaSubscriptionsThisYear` (`app/Services/Tax/TaxStrategyMath.php:182-210`) uses a created-this-tax-year proxy instead.
- Pension history capture writes `PensionInputHistory` rows (`app/Services/Stores/PensionStore.php:263-283`); `AnnualAllowanceChecker::getCarryForward` (`app/Services/Retirement/AnnualAllowanceChecker.php:190`) reads only the manual `RetirementProfile.prior_year_unused_allowance` JSON.
- Estate `available_nrb` is a static profile field (`app/Services/Estate/ComprehensiveEstatePlanService.php:965`); `GiftingStrategy::analyzePETs` (`app/Services/Estate/GiftingStrategy.php:43-82`) already filters gifts < 7 years.
- ~~Eval bugs~~ **RESOLVED pre-plan (Task 1 verified 2026-06-10):** tool-name read already fixed (`EvalRecordCommand.php:404` reads `'tool'`); driver makes exactly 4 HTTP calls (trace fetch is `Cache::pull`, `EvalHttpDriver.php:178`) so scenario `calls: 4` is CORRECT — do not change; trace cache keys match on write/read (`EvalTraceCollector::cacheKey`). Task 1 outcome = regression test only.
- **No liability/debt model exists on dev** → `DebtBeforeSavingsStrategy` is DEFERRED (no data source). Restored `AFFORDABILITY_RULES` covers debt-vs-savings narratively.
- `XaiToolDefinitions`/`AiToolDefinitions` have golden-master fixture tests — tool-description edits must regenerate fixtures intentionally. On dev the hardcoded classes are live (corpus `.md` files are coala-only). Track 2 must mirror description changes into `fyn-memory/procedural/tool_schema/` when coala merges.

**Conventions for every task:** `declare(strict_types=1)`, constructor `private readonly` injection, Pest (`it()`), `./vendor/bin/pint` before each commit, British English in user-facing strings, no acronyms except ISA, no emoji/icons, no hardcoded tax values (always `TaxConfigService`).

---

## Phase 0 — Eval harness repair

### Task 1: Fix tool-name capture and call-count assertions; verify trace persistence

**Files:**
- Modify: `app/Console/Commands/EvalRecordCommand.php:316`
- Modify: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json` (all 10)
- Test: `tests/Unit/Console/EvalRecordCommandToolNameTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Console\Commands\EvalRecordCommand;

it('extracts tool names from the tool key of SSE events', function () {
    $command = new EvalRecordCommand;
    $method = new ReflectionMethod($command, 'extractToolCallsFromEvents');
    $method->setAccessible(true);

    $events = [
        ['type' => 'tool_use', 'tool' => 'get_module_analysis', 'status' => 'running'],
        ['type' => 'content', 'text' => 'hello'],
        ['type' => 'tool_use', 'tool' => 'get_tax_information', 'status' => 'running'],
    ];

    $calls = $method->invoke($command, $events);

    expect(array_column($calls, 'name'))
        ->toBe(['get_module_analysis', 'get_tax_information']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Console/EvalRecordCommandToolNameTest.php`
Expected: FAIL — names come back `['unknown', 'unknown']`.

- [ ] **Step 3: Fix the key read**

At `app/Console/Commands/EvalRecordCommand.php:316` change:

```php
'name' => (string) ($event['name'] ?? 'unknown'),
```

to:

```php
'name' => (string) ($event['tool'] ?? $event['name'] ?? 'unknown'),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Console/EvalRecordCommandToolNameTest.php`
Expected: PASS

- [ ] **Step 5: Update all 10 scenario call counts**

In each `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json`, change `"calls": 4` to `"calls": 5` inside `expected_http_log` (the driver's 5th call is the legitimate trace fetch).

Run: `grep -rn '"calls": 4' tests/Feature/Fyn/Eval/scenarios/ | wc -l`
Expected: `0`

- [ ] **Step 6: Verify trace persistence end-to-end (no code change expected)**

The cache-persistence path already exists (`app/Http/Controllers/Api/AiChatController.php:294` calls `EvalTraceCollector::persistForConversation`, which `Cache::put`s at `app/Services/Eval/EvalTraceCollector.php:90-96`). Run one recorded eval and confirm `engine_trace` is non-empty:

Run: `php artisan eval:record mitchell_factual_net_worth 2>&1 | tail -20` (use the actual registered command signature — check `php artisan list | grep eval` first)
Expected: recorded run shows non-empty trace and tool names ≠ "unknown". **If `engine_trace` is empty**, the trace GET endpoint is not reading the cache key written by `persistForConversation` — fix the read side to use the same cache key, do NOT change the scoped binding.

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "fix(eval): record real tool names and correct scenario call counts"
```

---

## Phase 1 — Catalogue metadata substrate

### Task 2: Migration — metadata columns on all six action-definition tables

**Files:**
- Create: `database/migrations/2026_06_10_100001_add_catalogue_metadata_to_action_definitions.php`
- Test: `tests/Unit/Database/ActionDefinitionMetadataColumnsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('adds catalogue metadata columns to every action-definition table', function () {
    $tables = [
        'tax_action_definitions', 'retirement_action_definitions',
        'protection_action_definitions', 'investment_action_definitions',
        'savings_action_definitions', 'estate_action_definitions',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasColumns($table, ['claim_tier', 'required_data', 'sequencing']))
            ->toBeTrue("missing metadata columns on {$table}");
    }

    expect(Schema::hasColumn('tax_action_definitions', 'strategy_type'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Database/ActionDefinitionMetadataColumnsTest.php`
Expected: FAIL — columns missing.

- [ ] **Step 3: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'tax_action_definitions',
        'retirement_action_definitions',
        'protection_action_definitions',
        'investment_action_definitions',
        'savings_action_definitions',
        'estate_action_definitions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'claim_tier')) {
                    // mechanical = stated directly with figures; judgement = hedged + signposted.
                    $t->string('claim_tier', 20)->default('judgement');
                }
                if (! Schema::hasColumn($table, 'required_data')) {
                    $t->json('required_data')->nullable();
                }
                if (! Schema::hasColumn($table, 'sequencing')) {
                    $t->json('sequencing')->nullable();
                }
            });
        }

        Schema::table('tax_action_definitions', function (Blueprint $t) {
            if (! Schema::hasColumn('tax_action_definitions', 'strategy_type')) {
                // Links a definition row to a Tax/Strategies class output type
                // (StrategyRecommendation::type). Null for legacy 'agent' rows.
                $t->string('strategy_type', 64)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['claim_tier', 'required_data', 'sequencing']);
            });
        }
        Schema::table('tax_action_definitions', function (Blueprint $t) {
            $t->dropColumn('strategy_type');
        });
    }
};
```

- [ ] **Step 4: Run migration + test**

Run: `php artisan migrate && ./vendor/bin/pest tests/Unit/Database/ActionDefinitionMetadataColumnsTest.php`
Expected: PASS. **Then reseed (CLAUDE.md law): `php artisan db:seed`**

- [ ] **Step 5: Add columns to the six models' `$fillable` + `$casts`**

In each of `app/Models/{Tax,Retirement,Protection,Investment,Savings,Estate}ActionDefinition.php` add to `$fillable`: `'claim_tier', 'required_data', 'sequencing'` (plus `'strategy_type'` on `TaxActionDefinition` only) and to `$casts`:

```php
'required_data' => 'array',
'sequencing' => 'array',
```

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(catalogue): claim_tier/required_data/sequencing metadata columns on action definitions"
```

### Task 3: Seed catalogue metadata for the 13 tax strategies

**Files:**
- Modify: `database/seeders/TaxActionDefinitionSeeder.php`
- Test: `tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TaxActionDefinition;
use Database\Seeders\TaxActionDefinitionSeeder;

it('seeds a metadata row for every tax strategy type', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    $expected = [
        'income_band_position', 'lifecycle_allowances', 'joint_savings_psa',
        'isa_topup_vs_psa', 'dividend_allowance_harvest', 'salary_sacrifice_ni',
        'bed_and_isa', 'pension_aa_carry_forward', 'gift_aid_higher_rate',
        'tapered_annual_allowance', 'non_earner_spouse_pension',
        'cross_spouse_bundle', 'asset_shifting_bundle',
    ];

    foreach ($expected as $type) {
        $row = TaxActionDefinition::where('strategy_type', $type)->first();
        expect($row)->not->toBeNull("missing catalogue row for {$type}")
            ->and($row->claim_tier)->toBeIn(['mechanical', 'judgement'])
            ->and($row->source)->toBe('strategy');
    }

    // Legacy duplicated 'agent' evaluator rows are disabled (their service is orphaned).
    expect(TaxActionDefinition::where('source', 'agent')->where('is_enabled', true)->count())->toBe(0);
});
```

**IMPORTANT:** Before writing the seeder, confirm the 13 actual `StrategyRecommendation::type` strings by grepping each strategy class: `grep -h "type: '" app/Services/Tax/Strategies/*.php`. The list above is indicative (`isa_topup_vs_psa` is verified from `IsaTopUpStrategy.php:75`) — use the real strings found, and update the test to match before first run.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php`
Expected: FAIL — no `strategy_type` rows.

- [ ] **Step 3: Extend the seeder**

Append to `TaxActionDefinitionSeeder::run()` (after the existing loop) a second loop over this metadata map, and disable the five legacy `agent` rows:

```php
// ── Catalogue metadata for the June strategy registry (source: 'strategy') ──
// One row per Tax/Strategies class output type. The strategy class remains the
// quantifier; this row carries admin-visible metadata only.
foreach ($this->strategyMetadata() as $meta) {
    TaxActionDefinition::updateOrCreate(
        ['strategy_type' => $meta['strategy_type']],
        $meta + [
            'key' => 'strategy_'.$meta['strategy_type'],
            'source' => 'strategy',
            'title_template' => $meta['strategy_type'],   // display copy comes from the strategy DTO
            'description_template' => 'Computed by '.$meta['strategy_type'].' strategy class.',
            'category' => $meta['category'],
            'scope' => 'portfolio',
            'is_enabled' => true,
            'sort_order' => 100,
        ]
    );
}

// The March 'agent' evaluators duplicate the strategy registry and their
// service (TaxActionDefinitionService) is orphaned — disable, don't delete.
TaxActionDefinition::where('source', 'agent')->update(['is_enabled' => false]);
```

And the metadata method (adjust `strategy_type` strings to the grep results from Step 1; data-point keys are the canonical vocabulary consumed by Task 7; **`priority` values come from the canonical catalogue** `/Users/CSJ/Desktop/fynlaBrain/April/April30Updates/savetax-strategy-catalogue.md` — add `'priority' => '<value>'` to each entry: income_band_position high, lifecycle_allowances medium, joint_savings_psa low, isa_topup_vs_psa high, dividend_allowance_harvest low, salary_sacrifice_ni medium, bed_and_isa medium, pension_aa_carry_forward medium, gift_aid_higher_rate medium, tapered_annual_allowance high, non_earner_spouse_pension medium, cross_spouse_bundle high, asset_shifting_bundle high):

```php
/** @return list<array<string,mixed>> */
private function strategyMetadata(): array
{
    return [
        ['strategy_type' => 'income_band_position', 'category' => 'income_band', 'claim_tier' => 'mechanical',
            'required_data' => ['annual_income'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'lifecycle_allowances', 'category' => 'lifecycle', 'claim_tier' => 'judgement',
            'required_data' => ['date_of_birth'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'joint_savings_psa', 'category' => 'household', 'claim_tier' => 'mechanical',
            'required_data' => ['marital_status', 'savings_balances'], 'sequencing' => ['do_before' => [], 'conflicts_with' => ['asset_shifting_bundle']]],
        ['strategy_type' => 'isa_topup_vs_psa', 'category' => 'allowance', 'claim_tier' => 'mechanical',
            'required_data' => ['savings_balances', 'isa_subscriptions_ytd'], 'sequencing' => ['do_before' => ['asset_shifting_bundle'], 'conflicts_with' => []]],
        ['strategy_type' => 'dividend_allowance_harvest', 'category' => 'allowance', 'claim_tier' => 'mechanical',
            'required_data' => ['dividend_income'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'salary_sacrifice_ni', 'category' => 'allowance', 'claim_tier' => 'mechanical',
            'required_data' => ['employment_status', 'annual_income', 'workplace_pension'], 'sequencing' => ['do_before' => ['pension_aa_carry_forward'], 'conflicts_with' => []]],
        ['strategy_type' => 'bed_and_isa', 'category' => 'allowance', 'claim_tier' => 'judgement',
            'required_data' => ['gia_holdings', 'isa_subscriptions_ytd'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'pension_aa_carry_forward', 'category' => 'allowance', 'claim_tier' => 'mechanical',
            'required_data' => ['pension_input_history', 'annual_income'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'gift_aid_higher_rate', 'category' => 'allowance', 'claim_tier' => 'mechanical',
            'required_data' => ['charitable_giving', 'annual_income'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'tapered_annual_allowance', 'category' => 'warning', 'claim_tier' => 'mechanical',
            'required_data' => ['annual_income', 'pension_contributions'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'non_earner_spouse_pension', 'category' => 'household', 'claim_tier' => 'mechanical',
            'required_data' => ['marital_status', 'spouse_income'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'cross_spouse_bundle', 'category' => 'household', 'claim_tier' => 'judgement',
            'required_data' => ['marital_status', 'spouse_income'], 'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
        ['strategy_type' => 'asset_shifting_bundle', 'category' => 'household', 'claim_tier' => 'mechanical',
            'required_data' => ['marital_status', 'spouse_income', 'savings_balances'], 'sequencing' => ['do_before' => [], 'conflicts_with' => ['joint_savings_psa']]],
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(catalogue): seed metadata rows linking tax strategies to the definition registry"
```

---

## Phase 2 — HouseholdFinancialContext + data-correctness fixes

### Task 4: `estimateIsaSubscriptionsThisYear` reads captured subscription amounts

**Files:**
- Modify: `app/Services/Tax/TaxStrategyMath.php:182-210`
- Test: `tests/Unit/Services/Tax/TaxStrategyMathIsaSubscriptionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

it('prefers captured isa_subscription_amount for the current tax year', function () {
    $user = User::factory()->create();
    $taxYear = app(TaxConfigService::class)->getTaxYear(); // e.g. '2026/27'

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 19000,
        'isa_subscription_year' => $taxYear, 'isa_subscription_amount' => 100,
        'created_at' => now()->subYears(3), // proxy would say £0 — captured says £100
    ]);

    expect(app(TaxStrategyMath::class)->estimateIsaSubscriptionsThisYear($user->fresh()))
        ->toBe(100.0);
});

it('falls back to the created-this-tax-year proxy when no subscription amounts are captured', function () {
    $user = User::factory()->create();

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 5000,
        'isa_subscription_year' => null, 'isa_subscription_amount' => null,
        'created_at' => now(), // opened this tax year → proxy counts balance
    ]);

    expect(app(TaxStrategyMath::class)->estimateIsaSubscriptionsThisYear($user->fresh()))
        ->toBe(5000.0);
});
```

- [ ] **Step 2: Run to verify the first test fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/TaxStrategyMathIsaSubscriptionsTest.php`
Expected: first FAILS (proxy returns 0.0), second passes.

- [ ] **Step 3: Implement**

In `estimateIsaSubscriptionsThisYear`, before the existing proxy logic, sum captured amounts for the current tax year and return them when any exist:

```php
$taxYearLabel = $this->taxConfig->getTaxYear();

$captured = app(SavingsStore::class)->forUser($user)
    ->where('user_id', $user->id)
    ->where('is_isa', true)
    ->where('isa_subscription_year', $taxYearLabel)
    ->sum('isa_subscription_amount');

if ((float) $captured > 0) {
    return (float) $captured;
}

// Fallback: created-this-tax-year proxy (existing logic below, unchanged).
```

- [ ] **Step 4: Run both tests + the existing strategy suite**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/`
Expected: ALL PASS (existing strategy tests must not regress).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "fix(tax): ISA subscription estimate prefers captured per-account amounts"
```

### Task 5: Carry-forward consumes captured `PensionInputHistory`

**Files:**
- Modify: `app/Services/Retirement/AnnualAllowanceChecker.php:190-230` (`getCarryForward`)
- Test: `tests/Unit/Services/Retirement/AnnualAllowanceCarryForwardTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\PensionInputHistory;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\TaxConfigService;

it('computes carry-forward from captured pension input history when present', function () {
    $user = User::factory()->create();
    $standard = (float) (app(TaxConfigService::class)->getPensionAllowances()['annual_allowance'] ?? 60000);

    // Three prior years captured at £30k each → carry-forward = 3 × (AA − 30k).
    foreach (['2025/26', '2024/25', '2023/24'] as $year) {
        PensionInputHistory::create([
            'user_id' => $user->id, 'tax_year' => $year, 'pension_input_amount' => 30000,
        ]);
    }

    $carryForward = app(AnnualAllowanceChecker::class)->getCarryForward($user->id, '2026/27');

    expect($carryForward)->toBe(3 * ($standard - 30000.0));
});
```

(Confirm `PensionInputHistory` fillable/table name by opening `app/Models/PensionInputHistory.php` first; adjust `create()` to match. If prior-year tax-year labels are formatted differently in that table — e.g. `2025-26` — use the format `PensionStore::captureInputHistory` writes.)

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Retirement/AnnualAllowanceCarryForwardTest.php`
Expected: FAIL — current implementation reads only `RetirementProfile.prior_year_unused_allowance`.

- [ ] **Step 3: Implement**

In `getCarryForward`, before the `RetirementProfile` fallback:

```php
$history = PensionInputHistory::where('user_id', $userId)
    ->where('tax_year', '!=', $taxYear)
    ->orderByDesc('tax_year')
    ->limit(3)
    ->get();

if ($history->isNotEmpty()) {
    $standard = (float) ($this->taxConfig->getPensionAllowances()['annual_allowance'] ?? 60000);

    return (float) $history->sum(
        fn ($row) => max(0.0, $standard - (float) $row->pension_input_amount)
    );
}

// Fallback: manually-entered RetirementProfile.prior_year_unused_allowance (existing logic).
```

Note: using the current standard allowance for all three prior years is a simplification — the prior-year allowances may differ. If `TaxConfigService` exposes per-year historical pension allowances, use them; otherwise keep current-year as the conservative approximation and say so in a code comment (constraint: no hardcoded values).

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/Retirement/`
Expected: PASS, no regressions.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(retirement): carry-forward computed from captured pension input history"
```

### Task 6: Derived available nil-rate band (7-year gift lookback)

**Files:**
- Modify: `app/Services/Estate/ComprehensiveEstatePlanService.php` (~line 965 usage site)
- Create: `app/Services/Estate/AvailableNrbCalculator.php`
- Test: `tests/Unit/Services/Estate/AvailableNrbCalculatorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\Gift;
use App\Models\User;
use App\Services\Estate\AvailableNrbCalculator;
use App\Services\TaxConfigService;

it('reduces available nil-rate band by non-exempt gifts made in the last 7 years', function () {
    $user = User::factory()->create();
    $nrb = (float) app(TaxConfigService::class)->getInheritanceTax()['nil_rate_band'];

    Gift::factory()->create(['user_id' => $user->id, 'amount' => 100000, 'gift_date' => now()->subYears(3)]);
    Gift::factory()->create(['user_id' => $user->id, 'amount' => 50000, 'gift_date' => now()->subYears(8)]); // outside window

    expect(app(AvailableNrbCalculator::class)->forUser($user))->toBe($nrb - 100000.0);
});

it('never returns below zero', function () {
    $user = User::factory()->create();
    Gift::factory()->create(['user_id' => $user->id, 'amount' => 999999, 'gift_date' => now()->subYear()]);

    expect(app(AvailableNrbCalculator::class)->forUser($user))->toBe(0.0);
});
```

(Open `app/Models/Gift.php` first for actual column names — `amount`/`gift_date`/`gift_type` — and mirror `GiftingStrategy::analyzePETs`'s 7-year filter semantics at `app/Services/Estate/GiftingStrategy.php:43-82`. If the model distinguishes exempt gift types, exclude exempt ones.)

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Estate/AvailableNrbCalculatorTest.php`
Expected: FAIL — class does not exist.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Gift;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * Available nil-rate band derived from gift history: NRB minus non-exempt
 * gifts made within the prior 7 years (cumulation window). Mirrors the
 * 7-year filter in GiftingStrategy::analyzePETs.
 */
final class AvailableNrbCalculator
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    public function forUser(User $user): float
    {
        $nrb = (float) ($this->taxConfig->getInheritanceTax()['nil_rate_band'] ?? 0);

        $gifted = (float) Gift::where('user_id', $user->id)
            ->where('gift_date', '>=', now()->subYears(7))
            ->sum('amount');

        return max(0.0, $nrb - $gifted);
    }
}
```

- [ ] **Step 4: Wire into the estate plan**

At `ComprehensiveEstatePlanService.php:~965` replace:

```php
$availableNRB = $profile->available_nrb ?? $ihtConfig['nil_rate_band'];
```

with:

```php
// Derived from gift history (7-year cumulation); manual profile value wins if set.
$availableNRB = $profile->available_nrb
    ?? app(AvailableNrbCalculator::class)->forUser($user);
```

- [ ] **Step 5: Run estate suite**

Run: `./vendor/bin/pest tests/Unit/Services/Estate/`
Expected: PASS (existing estate tests unaffected — users without gifts get full NRB as before).

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(estate): available NRB derived from 7-year gift cumulation window"
```

### Task 7: `HouseholdFinancialContext` service

**Files:**
- Create: `app/Services/Coordination/HouseholdFinancialContext.php`
- Test: `tests/Unit/Services/Coordination/HouseholdFinancialContextTest.php`

This is the data-availability + household-rates service the composer (Task 9) and unlock cards (Task 19) consume. The data-point keys MUST match the `required_data` vocabulary seeded in Task 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\HouseholdFinancialContext;

it('reports which catalogue data points are available for a user', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'marital_status' => 'married',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
        'household_calculation_mode' => 'single_earner_couple',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_savings_balance' => 0]);

    $availability = app(HouseholdFinancialContext::class)->availability($user->fresh());

    expect($availability['annual_income'])->toBeTrue()
        ->and($availability['marital_status'])->toBeTrue()
        ->and($availability['spouse_income'])->toBeTrue()      // single_earner_couple ⇒ spouse income known to be £0
        ->and($availability['workplace_pension'])->toBeFalse() // no pension records
        ->and($availability['charitable_giving'])->toBeFalse();
});

it('exposes the user marginal rate', function () {
    $user = User::factory()->create(['annual_employment_income' => 110000]);

    $rate = app(HouseholdFinancialContext::class)->marginalRateFor($user);

    expect($rate)->toBeGreaterThan(0.0)->toBeLessThanOrEqual(1.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/HouseholdFinancialContextTest.php`
Expected: FAIL — class does not exist.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\PensionInputHistory;
use App\Models\User;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\TaxStrategyMath;

/**
 * Household-aware data availability + rates for the strategy catalogue.
 *
 * availability() keys are the canonical required_data vocabulary used by the
 * action-definition metadata (Task 3 seeder). A strategy whose required_data
 * are not all true is "locked" — surfaced as an unlock prompt, never silently
 * skipped.
 */
final class HouseholdFinancialContext
{
    public function __construct(private readonly TaxStrategyMath $math) {}

    /** @return array<string, bool> */
    public function availability(User $user): array
    {
        $household = $user->taxStrategyHouseholdInput;
        $mode = (string) ($user->household_calculation_mode ?? 'single');

        $savings = app(SavingsStore::class)->forUser($user)->where('user_id', $user->id);
        $pensions = app(PensionStore::class)->forUserByType($user, 'dc');

        return [
            'date_of_birth' => $user->date_of_birth !== null,
            'marital_status' => ! empty($user->marital_status),
            'employment_status' => ! empty($user->employment_status),
            'annual_income' => (float) ($user->annual_employment_income ?? 0) > 0
                || (float) ($user->annual_self_employment_income ?? 0) > 0,
            'dividend_income' => (float) ($user->annual_dividend_income ?? 0) > 0,
            'savings_balances' => $savings->isNotEmpty(),
            'isa_subscriptions_ytd' => $savings->where('is_isa', true)->isNotEmpty(),
            'gia_holdings' => false, // refined by investment store when wired; locked by default
            'workplace_pension' => $pensions->isNotEmpty(),
            'pension_contributions' => $pensions->isNotEmpty(),
            'pension_input_history' => PensionInputHistory::where('user_id', $user->id)->exists(),
            'charitable_giving' => (float) ($user->annual_charitable_donations ?? 0) > 0,
            'spouse_income' => $mode === 'single_earner_couple'
                || ($household !== null && $household->spouse_annual_income !== null),
        ];
    }

    public function marginalRateFor(User $user): float
    {
        return (float) $this->math->bandRateFor($user);
    }

    /** Spouse marginal rate from household input; null when unknown. */
    public function spouseMarginalRate(User $user): ?float
    {
        $mode = (string) ($user->household_calculation_mode ?? 'single');
        if ($mode === 'single_earner_couple') {
            return 0.0; // non-earner
        }

        $income = $user->taxStrategyHouseholdInput?->spouse_annual_income;
        if ($income === null) {
            return null;
        }

        return (float) $this->math->bandRateFromIncome((float) $income);
    }
}
```

**Adjust to reality while implementing:** confirm `TaxStrategyMath` method names with `grep -n "public function" app/Services/Tax/TaxStrategyMath.php` — if there is no `bandRateFromIncome`, compose from the existing `bandFromIncome(...)` + `psaForBand(...)`-style helpers (a `bandRateFor(User)` exists per `IsaTopUpStrategy.php:71`; add a small public income-based variant to `TaxStrategyMath` if needed, with its own one-case test). Confirm the `gia_holdings` availability by checking what store `BedAndIsaStrategy` reads, and use the same source.

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/HouseholdFinancialContextTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(coordination): HouseholdFinancialContext availability + household marginal rates"
```

### Task 8: Personalise `NonEarnerSpousePensionStrategy` with actual spouse position

**Files:**
- Modify: `app/Services/Tax/Strategies/NonEarnerSpousePensionStrategy.php:27-112`
- Test: `tests/Unit/Services/Tax/Strategies/NonEarnerSpousePensionStrategyTest.php` (extend existing if present — check first with `ls tests/Unit/Services/Tax/Strategies/`)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\Strategies\NonEarnerSpousePensionStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;

it('does not fire the non-earner framing when the spouse has earnings', function () {
    $user = User::factory()->create([
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
    ]);
    $household = TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_annual_income' => 8000, // part-time earner — £2,880 net cap is wrong framing
    ]);

    $recs = app(NonEarnerSpousePensionStrategy::class)
        ->generate(new TaxStrategyContext($user, null, $household, 'single_earner_couple'));

    // Either no rec, or a rec whose description references the spouse's earnings-based
    // contribution capacity rather than the flat non-earner £2,880/£720 figures.
    if ($recs !== []) {
        expect($recs[0]->description)->not->toContain('£2,880');
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/NonEarnerSpousePensionStrategyTest.php`
Expected: FAIL — current implementation always uses the flat `TaxDefaults::NON_EARNER_PENSION_NET_CONTRIBUTION`.

- [ ] **Step 3: Implement**

Inside `generate()`, after resolving `$household`, branch on spouse earnings:

```php
$spouseIncome = (float) ($household?->spouse_annual_income ?? 0);

if ($spouseIncome > 0) {
    // Spouse has relevant UK earnings: contribution capacity is their earnings
    // (relief at source), not the £3,600 non-earner gross cap. Reframe.
    $grossCapacity = $spouseIncome;
    $relief = $grossCapacity * 0.20; // basic-rate relief at source on contribution
    // Build the StrategyRecommendation with earnings-based copy here, keeping
    // type/category/priority unchanged; description must quote $spouseIncome
    // and the 20% relief-at-source uplift, not the £2,880/£720 pair.
} else {
    // Existing non-earner path (unchanged): £2,880 net → £3,600 gross.
}
```

Use `TaxConfigService` for the basic rate rather than the `0.20` literal if it exposes one (`getIncomeTax()['basic_rate']` — check; Rule 2 applies).

- [ ] **Step 4: Run strategy suite**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/ 2>/dev/null || ./vendor/bin/pest --filter=NonEarnerSpousePension`
Expected: PASS including existing non-earner cases.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "fix(tax): spouse pension strategy uses actual spouse earnings, not flat non-earner figures"
```

---

## Phase 3 — Aggregator + Plan Composer

### Task 9: `StrategyPlanComposer`

**Files:**
- Create: `app/Services/Coordination/StrategyPlanComposer.php`
- Test: `tests/Unit/Services/Coordination/StrategyPlanComposerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Coordination\StrategyPlanComposer;

it('orders by sequencing, sums savings, and marks conflicts', function () {
    $recs = [
        new StrategyRecommendation('asset_shifting_bundle', StrategyCategory::Household, StrategyPriority::High,
            'Gift savings to spouse', 'desc', 530.0),
        new StrategyRecommendation('isa_topup_vs_psa', StrategyCategory::Allowance, StrategyPriority::High,
            'Wrap cash in ISA', 'desc', 259.0),
        new StrategyRecommendation('joint_savings_psa', StrategyCategory::Household, StrategyPriority::High,
            'Split savings', 'desc', 330.0),
    ];

    $metadata = [
        'isa_topup_vs_psa' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => ['asset_shifting_bundle'], 'conflicts_with' => []]],
        'asset_shifting_bundle' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['joint_savings_psa']]],
        'joint_savings_psa' => ['claim_tier' => 'mechanical', 'sequencing' => ['do_before' => [], 'conflicts_with' => ['asset_shifting_bundle']]],
    ];

    $plan = app(StrategyPlanComposer::class)->compose($recs, $metadata, lockedStrategies: []);

    $types = array_column($plan['items'], 'type');
    // isa_topup must precede asset_shifting (do_before).
    expect(array_search('isa_topup_vs_psa', $types))
        ->toBeLessThan(array_search('asset_shifting_bundle', $types));
    // Conflict pair: higher-saving one keeps rank; the other carries a conflict note.
    $joint = collect($plan['items'])->firstWhere('type', 'joint_savings_psa');
    expect($joint['conflict_note'])->toContain('asset_shifting_bundle');
    expect($plan['combined_annual_saving'])->toBe(1119.0);
    expect($plan['items'][0]['claim_tier'])->toBe('mechanical');
});

it('lists locked strategies with their missing data points', function () {
    $plan = app(StrategyPlanComposer::class)->compose([], [], lockedStrategies: [
        ['strategy_type' => 'salary_sacrifice_ni', 'missing' => ['workplace_pension']],
    ]);

    expect($plan['locked'][0]['strategy_type'])->toBe('salary_sacrifice_ni')
        ->and($plan['locked'][0]['missing'])->toBe(['workplace_pension']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/StrategyPlanComposerTest.php`
Expected: FAIL — class does not exist.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\DataTransferObjects\StrategyRecommendation;

/**
 * Composes eligible strategy recommendations into ONE ordered plan:
 * sequencing honoured (do_before), conflicts noted (both kept, higher
 * saving first), combined total computed, claim tier attached for voicing.
 *
 * Pure function of its inputs — callers fetch recommendations from
 * TaxStrategyCalculator and metadata from TaxActionDefinition rows.
 */
final class StrategyPlanComposer
{
    /**
     * @param  list<StrategyRecommendation>  $recommendations
     * @param  array<string, array{claim_tier?: string, sequencing?: array{do_before?: list<string>, conflicts_with?: list<string>}}>  $metadata
     * @param  list<array{strategy_type: string, missing: list<string>}>  $lockedStrategies
     * @return array{items: list<array<string, mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}
     */
    public function compose(array $recommendations, array $metadata, array $lockedStrategies): array
    {
        // 1. Base order: estimated saving descending (nulls last).
        $items = collect($recommendations)
            ->sortByDesc(fn (StrategyRecommendation $r) => $r->estimatedAnnualTaxSaved ?? -1)
            ->values()
            ->all();

        // 2. Sequencing: bubble any item with a do_before edge ahead of its target.
        //    Bounded passes (n²) — the list is small (≤ ~20 strategies).
        $position = fn (array $list, string $type): int|false => array_search(
            $type, array_map(fn (StrategyRecommendation $r) => $r->type, $list), true
        );
        for ($pass = 0; $pass < count($items); $pass++) {
            $moved = false;
            foreach ($items as $i => $rec) {
                foreach (($metadata[$rec->type]['sequencing']['do_before'] ?? []) as $target) {
                    $t = $position($items, $target);
                    if ($t !== false && $t < $i) {
                        array_splice($items, $i, 1);
                        array_splice($items, $t, 0, [$rec]);
                        $moved = true;
                        break 2;
                    }
                }
            }
            if (! $moved) {
                break;
            }
        }

        // 3. Conflicts: keep both, lower-saving one carries the note.
        $bySaving = [];
        foreach ($items as $rec) {
            $bySaving[$rec->type] = (float) ($rec->estimatedAnnualTaxSaved ?? 0);
        }

        $out = [];
        foreach ($items as $index => $rec) {
            $conflictNote = null;
            foreach (($metadata[$rec->type]['sequencing']['conflicts_with'] ?? []) as $other) {
                if (isset($bySaving[$other]) && $bySaving[$other] >= $bySaving[$rec->type]) {
                    $conflictNote = "Alternative to {$other} — compare before doing both.";
                }
            }

            $out[] = $rec->toArray() + [
                'claim_tier' => $metadata[$rec->type]['claim_tier'] ?? 'judgement',
                'sequence_position' => $index + 1,
                'conflict_note' => $conflictNote,
            ];
        }

        return [
            'items' => $out,
            'combined_annual_saving' => round(array_sum($bySaving), 2),
            'locked' => array_values($lockedStrategies),
        ];
    }
}
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/StrategyPlanComposerTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(coordination): StrategyPlanComposer — ordered, conflict-aware plan with combined total"
```

### Task 10: `ComposedTaxPlanService` — user → composed plan (the single entry point)

**Files:**
- Create: `app/Services/Coordination/ComposedTaxPlanService.php`
- Test: `tests/Feature/Services/ComposedTaxPlanServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use Database\Seeders\TaxActionDefinitionSeeder;

it('produces a composed plan with locked strategies for a thin user', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'marital_status' => 'married',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]);

    $plan = app(ComposedTaxPlanService::class)->forUser($user->fresh());

    expect($plan)->toHaveKeys(['items', 'combined_annual_saving', 'locked'])
        // No pension records captured → salary_sacrifice_ni must appear LOCKED, not vanish.
        ->and(array_column($plan['locked'], 'strategy_type'))->toContain('salary_sacrifice_ni');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Services/ComposedTaxPlanServiceTest.php`
Expected: FAIL — class does not exist.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;

/**
 * The one entry point: user → composed tax plan. Joins the calculator's
 * eligible recommendations with catalogue metadata, and derives the locked
 * list (enabled strategy rows whose required_data are not all available and
 * which produced no recommendation).
 */
final class ComposedTaxPlanService
{
    public function __construct(
        private readonly TaxStrategyCalculator $calculator,
        private readonly HouseholdFinancialContext $context,
        private readonly StrategyPlanComposer $composer,
    ) {}

    /** @return array{items: list<array<string,mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>} */
    public function forUser(User $user): array
    {
        $output = $this->calculator->calculate($user);
        $recommendations = array_map(
            fn (array $r) => \App\DataTransferObjects\StrategyRecommendation::fromArray($r['category'], $r),
            $output->recommendations
        );

        $rows = TaxActionDefinition::where('source', 'strategy')->where('is_enabled', true)->get();

        $metadata = [];
        foreach ($rows as $row) {
            $metadata[$row->strategy_type] = [
                'claim_tier' => $row->claim_tier,
                'sequencing' => $row->sequencing ?? ['do_before' => [], 'conflicts_with' => []],
            ];
        }

        $availability = $this->context->availability($user);
        $firedTypes = array_map(fn ($r) => $r->type, $recommendations);

        $locked = [];
        foreach ($rows as $row) {
            $missing = array_values(array_filter(
                (array) ($row->required_data ?? []),
                fn (string $key) => ($availability[$key] ?? false) === false
            ));
            if ($missing !== [] && ! in_array($row->strategy_type, $firedTypes, true)) {
                $locked[] = ['strategy_type' => $row->strategy_type, 'missing' => $missing];
            }
        }

        return $this->composer->compose($recommendations, $metadata, $locked);
    }
}
```

Note: `TaxStrategyOutputDTO::$recommendations` is `StrategyRecommendation::toArray()` output (`TaxStrategyCalculator.php:97`); the `fromArray` round-trip above preserves `extra`. Verify `$output->recommendations` element shape includes `category` (it does — `toArray()` emits it).

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Feature/Services/ComposedTaxPlanServiceTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(coordination): ComposedTaxPlanService — user to composed plan with locked strategies"
```

### Task 11: Tax becomes the seventh aggregator module

**Files:**
- Modify: `app/Services/Coordination/RecommendationsAggregatorService.php` (constructor + after the goals block ~line 186; `getSummary` by_module ~line 352)
- Test: `tests/Feature/Services/RecommendationsAggregatorTaxModuleTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;
use Database\Seeders\TaxActionDefinitionSeeder;

it('includes tax strategy recommendations for a user passing the tax gate', function () {
    $this->seed(TaxActionDefinitionSeeder::class);

    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]);
    // £81k taxable savings at 3.25% with ISA headroom → isa_topup_vs_psa fires.
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($user->id);

    $taxRecs = array_values(array_filter($recs, fn ($r) => $r['module'] === 'tax'));
    expect($taxRecs)->not->toBeEmpty()
        ->and(array_column($taxRecs, 'recommendation_id'))->toContain('tax_isa_topup_vs_psa');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Services/RecommendationsAggregatorTaxModuleTest.php`
Expected: FAIL — no `tax` module in output.

- [ ] **Step 3: Implement**

Add `private readonly ComposedTaxPlanService $taxPlan,` to the constructor, and after the goals block (before the personaliser call at ~line 188):

```php
// Tax module — the strategy catalogue, gated by the tax_optimisation prerequisites.
if ($this->moduleGateOpen('tax_optimisation', $user)) {
    try {
        $plan = $this->taxPlan->forUser($user);
        $taxRecs = array_map(static function (array $item): array {
            return [
                // Stable id: derived from the strategy type, never content-hashed.
                'recommendation_id' => 'tax_'.$item['type'],
                'recommendation_text' => $item['title'].' — '.$item['description'],
                'priority_score' => match ($item['priority']) {
                    'high' => 85.0, 'medium' => 60.0, default => 45.0,
                },
                'category' => $item['category'],
                'potential_benefit' => $item['estimated_annual_tax_saved'],
                'claim_tier' => $item['claim_tier'],
                'sequence_position' => $item['sequence_position'],
            ];
        }, $plan['items']);
        $allRecommendations = array_merge($allRecommendations, $this->formatRecommendations($taxRecs, 'tax'));
    } catch (\Exception $e) {
        Log::warning("Failed to get tax recommendations for user {$userId}: ".$e->getMessage());
    }
}
```

Also: in `getSummary()` add `'tax' => 0,` to the `by_module` array (~line 358). Confirm `PrerequisiteGateService::enforce('tax_optimisation', …)` is the correct action name (`app/Services/PrerequisiteGateService.php:127-143`) — if `moduleGateOpen` expects bare module names, pass `'tax_optimisation'` exactly as the gate defines it.

`formatRecommendations` passes unknown keys through? **No — it whitelists.** Extend its returned array with pass-throughs so the new fields survive:

```php
'claim_tier' => $rec['claim_tier'] ?? null,
'sequence_position' => $rec['sequence_position'] ?? null,
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Feature/Services/RecommendationsAggregatorTaxModuleTest.php tests/Feature/Services/ 2>/dev/null || ./vendor/bin/pest --filter=Aggregator`
Expected: PASS, existing aggregator tests green.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(recs): tax strategy catalogue joins the aggregator as the seventh module"
```

### Task 12: `/api/tax-strategy` response carries the composed plan

**Files:**
- Modify: the controller behind `routes/api.php:349` (`Route::prefix('tax-strategy')` group — open the route file to get the controller class, e.g. `TaxStrategyController@index`)
- Test: `tests/Feature/Api/TaxStrategyComposedPlanTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxActionDefinitionSeeder;
use Laravel\Sanctum\Sanctum;

it('returns the composed plan alongside the existing payload', function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'employment_status' => 'full_time',
        'annual_employment_income' => 110000, 'monthly_expenditure' => 3000,
        'marital_status' => 'married',
    ]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/tax-strategy');

    $response->assertOk()
        ->assertJsonStructure(['composed_plan' => ['items', 'combined_annual_saving', 'locked']]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Api/TaxStrategyComposedPlanTest.php`
Expected: FAIL — `composed_plan` key missing. (If the GET path differs — check `php artisan route:list --path=tax-strategy` — fix the test path first.)

- [ ] **Step 3: Implement (additive only)**

In the controller method that returns the calculator output, inject `ComposedTaxPlanService` and merge:

```php
$payload = /* existing response array */;
$payload['composed_plan'] = $this->composedTaxPlan->forUser($user);

return response()->json($payload);
```

Do not remove or reshape any existing keys — the savetax terminal page and `/m` TaxStrategy.vue consume them.

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Feature/Api/TaxStrategyComposedPlanTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(api): tax-strategy endpoint returns the composed plan (additive)"
```

---

## Phase 4 — New strategies

### Task 13: `SpouseIsaAfterGiftStrategy`

**Files:**
- Create: `app/Services/Tax/Strategies/SpouseIsaAfterGiftStrategy.php` (only if Step 0 confirms the gap)
- Modify: `app/Services/Tax/TaxStrategyCalculator.php` (constructor + `$strategies` registry, lines ~31-83)
- Modify: `database/seeders/TaxActionDefinitionSeeder.php` (metadata row)
- Test: `tests/Unit/Services/Tax/Strategies/SpouseIsaAfterGiftStrategyTest.php`

- [ ] **Step 0: Verify against the canonical catalogue (do NOT duplicate)**

Catalogue #10 ("ISA Top-Up in Spouse's Name") was marked "already implemented (buildAssetShiftingSuggestions, line 290)" in April, pre-refactor. Read `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php` fully and list every `StrategyRecommendation` type it emits. If a spouse-ISA recommendation already emits as its own typed item with an `estimatedAnnualTaxSaved`, SKIP creating the new class — instead ensure its type has a Task 3 seeder metadata row and is mapped in Task 17's `SECTION_STRATEGY_TYPES['spouse']`, then adapt the test below to assert on the existing type. If it emits only as part of a merged bundle item (or not at all), proceed with the new class as specified.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\Strategies\SpouseIsaAfterGiftStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;

it('recommends the spouse opening their own ISA after an inter-spouse gift', function () {
    $user = User::factory()->create([
        'marital_status' => 'married', 'annual_employment_income' => 110000,
        'household_calculation_mode' => 'single_earner_couple',
    ]);
    $household = TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_existing_isa_balance' => 0,
        'spouse_existing_savings_balance' => 0,
    ]);
    // Large taxable cash pile in the user's sole name — giftable.
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    $recs = app(SpouseIsaAfterGiftStrategy::class)
        ->generate(new TaxStrategyContext($user, null, $household, 'single_earner_couple'));

    expect($recs)->toHaveCount(1)
        ->and($recs[0]->type)->toBe('spouse_isa_after_gift')
        ->and($recs[0]->title)->toContain('ISA');
});

it('stays silent when the spouse already holds an ISA near the allowance', function () {
    $user = User::factory()->create([
        'marital_status' => 'married', 'household_calculation_mode' => 'single_earner_couple',
    ]);
    $household = TaxStrategyHouseholdInput::create([
        'user_id' => $user->id, 'spouse_existing_isa_balance' => 50000,
    ]);

    $recs = app(SpouseIsaAfterGiftStrategy::class)
        ->generate(new TaxStrategyContext($user, null, $household, 'single_earner_couple'));

    expect($recs)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/SpouseIsaAfterGiftStrategyTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy — Spouse ISA After Gift. Pairs with the asset-shifting bundle:
 * once cash is gifted to the spouse (CGT/IHT-exempt inter-spouse transfer),
 * the spouse's OWN £20k ISA allowance shelters it permanently. Doubles the
 * household's annual ISA capacity. Fires for couples where the user holds
 * substantial non-ISA cash and the spouse's ISA capacity is materially unused.
 */
final class SpouseIsaAfterGiftStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        if (! in_array($context->mode, ['dual_earner', 'single_earner_couple'], true)) {
            return [];
        }

        $isaAllowance = (float) ($this->taxConfig->getISAAllowances()['annual_allowance'] ?? 20000);
        $spouseIsa = (float) ($context->household?->spouse_existing_isa_balance ?? 0);
        if ($spouseIsa >= $isaAllowance) {
            return [];
        }

        $nonIsaCash = (float) app(SavingsStore::class)->forUser($context->user)
            ->where('user_id', $context->user->id)
            ->where('is_isa', false)
            ->sum('current_balance');

        $transferable = min($isaAllowance, $nonIsaCash);
        if ($transferable < 1000) {
            return [];
        }

        $rate = $this->math->bandRateFor($context->user);
        $annualInterest = $this->math->estimateAnnualInterest($context->user);
        if ($nonIsaCash <= 0 || $annualInterest <= 0) {
            return []; // never invent an interest rate — fire only on real data
        }
        $avgInterest = $annualInterest / $nonIsaCash;
        $saving = $transferable * $avgInterest * $rate;

        return [new StrategyRecommendation(
            type: 'spouse_isa_after_gift',
            category: StrategyCategory::Household,
            priority: StrategyPriority::High,
            title: sprintf('After gifting, your spouse opens their own ISA — shelter another £%s', number_format((int) $transferable)),
            description: sprintf(
                'Inter-spouse gifts are exempt from Capital Gains Tax and Inheritance Tax. Once cash is in your spouse\'s name, their own £%s ISA allowance shelters £%s of it permanently — roughly £%s a year of tax saved at your current rates, on top of their Personal Savings Allowance.',
                number_format((int) $isaAllowance),
                number_format((int) $transferable),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: ['suggested_transfer_amount' => round($transferable / 1000) * 1000],
        )];
    }
}
```

Register in `TaxStrategyCalculator`: add constructor param `private readonly Strategies\SpouseIsaAfterGiftStrategy $spouseIsaAfterGift,` and append `$this->spouseIsaAfterGift,` to the `$strategies` array after `$this->assetShifting`. Add the Task 3 seeder metadata row:

```php
['strategy_type' => 'spouse_isa_after_gift', 'category' => 'household', 'claim_tier' => 'mechanical',
    'required_data' => ['marital_status', 'spouse_income', 'savings_balances'],
    'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
```

- [ ] **Step 4: Run tests + reseed**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/SpouseIsaAfterGiftStrategyTest.php tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php` (add the new type to that test's expected list)
Expected: PASS

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(tax): SpouseIsaAfterGiftStrategy — household doubles annual ISA capacity"
```

### Task 14: `MarriageAllowanceStrategy` (eligibility-aware)

**Files:**
- Create: `app/Services/Tax/Strategies/MarriageAllowanceStrategy.php` (only if Step 0 confirms the gap)
- Modify: `app/Services/Tax/TaxStrategyCalculator.php` (register)
- Modify: `database/seeders/TaxActionDefinitionSeeder.php` (metadata row)
- Test: `tests/Unit/Services/Tax/Strategies/MarriageAllowanceStrategyTest.php`

- [ ] **Step 0: Verify against the canonical catalogue (do NOT duplicate)**

Catalogue #8 ("Marriage Allowance Transfer", £1,260 transfer, £252 saving, recipient must be basic-rate) was marked "already implemented (buildAssetShiftingSuggestions, line 244)" in April, pre-refactor. Read `AssetShiftingBundleStrategy.php` (and `CrossSpouseBundleStrategy.php`) and check whether a marriage-allowance recommendation already emits with the strict recipient-band gate. If yes: skip the new class, verify the gate matches catalogue #8 (basic-rate recipient only — fix the gate in place if it fires for higher-rate users, keeping its existing type string), add its seeder metadata row, and adapt the test to the existing type. If no: proceed with the new class as specified.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\Strategies\MarriageAllowanceStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;

it('recommends marriage allowance for a basic-rate earner with a non-earning spouse', function () {
    $user = User::factory()->create([
        'marital_status' => 'married', 'annual_employment_income' => 35000,
        'household_calculation_mode' => 'single_earner_couple',
    ]);

    $recs = app(MarriageAllowanceStrategy::class)
        ->generate(new TaxStrategyContext($user, null, null, 'single_earner_couple'));

    expect($recs)->toHaveCount(1)
        ->and($recs[0]->type)->toBe('marriage_allowance');
});

it('stays silent for a higher-rate earner (ineligible) — no false positives', function () {
    $user = User::factory()->create([
        'marital_status' => 'married', 'annual_employment_income' => 110000,
        'household_calculation_mode' => 'single_earner_couple',
    ]);

    $recs = app(MarriageAllowanceStrategy::class)
        ->generate(new TaxStrategyContext($user, null, null, 'single_earner_couple'));

    expect($recs)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/MarriageAllowanceStrategyTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy — Marriage Allowance. The lower earner transfers 10% of their
 * Personal Allowance to a BASIC-RATE-paying partner. Eligibility is strict:
 * recipient must not pay above basic rate. Fires only for eligible couples;
 * the recipient band check prevents the classic false positive.
 */
final class MarriageAllowanceStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        if (! in_array($context->mode, ['dual_earner', 'single_earner_couple'], true)) {
            return [];
        }

        $income = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($income['personal_allowance'] ?? 12570);
        $basicRate = (float) ($income['basic_rate'] ?? 0.20);
        $higherThreshold = (float) ($income['higher_rate_threshold'] ?? 50270);

        $userIncome = (float) $this->math->taxableIncomeFor($context->user);

        // Recipient (the earner) must be a basic-rate taxpayer.
        if ($userIncome <= $personalAllowance || $userIncome > $higherThreshold) {
            return [];
        }

        // Transferor must have unused Personal Allowance.
        $spouseIncome = (float) ($context->household?->spouse_annual_income ?? 0.0);
        if ($context->mode === 'dual_earner' && $spouseIncome >= $personalAllowance) {
            return [];
        }

        $transferable = floor($personalAllowance * 0.10 / 10) * 10; // 10%, HMRC-rounded
        $saving = $transferable * $basicRate;

        return [new StrategyRecommendation(
            type: 'marriage_allowance',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Medium,
            title: sprintf('Claim Marriage Allowance — around £%s a year', number_format((int) round($saving))),
            description: sprintf(
                'Your spouse can transfer £%s of their unused Personal Allowance to you because you pay tax at the basic rate. That reduces your tax by about £%s every year, and claims can be backdated up to four tax years.',
                number_format((int) $transferable),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
        )];
    }
}
```

Check `getIncomeTax()` key names first (`grep -n "basic_rate\|higher_rate" app/Services/TaxConfigService.php` or the `TaxConfiguration` seeder) and use the real keys; if rates are stored as `20` not `0.20`, normalise. Register in `TaxStrategyCalculator` and add the seeder metadata row:

```php
['strategy_type' => 'marriage_allowance', 'category' => 'household', 'claim_tier' => 'mechanical',
    'required_data' => ['marital_status', 'annual_income', 'spouse_income'],
    'sequencing' => ['do_before' => [], 'conflicts_with' => []]],
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/Tax/Strategies/MarriageAllowanceStrategyTest.php tests/Feature/Seeders/TaxCatalogueMetadataSeederTest.php` (add `marriage_allowance` to that test's expected list, as Task 13 did for its type)
Expected: PASS

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(tax): MarriageAllowanceStrategy with strict recipient-band eligibility"
```

---

## Phase 5 — Guided flow fixes (savetax/onboarding)

### Task 15: Answer-the-user-first (capture template + off-script filter)

**Files:**
- Modify: `app/Services/AI/Fyn/FynCaptureTurnInstructions.php` (template)
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (the `$flushBuffer` closure, ~line 1940-1955)
- Test: `tests/Feature/Fyn/CaptureTurnAnswersQuestionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynCaptureTurnInstructions;

it('instructs the model to answer a user question before resuming capture', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    expect($rendered)
        ->toContain('If the user asks a question')
        ->toContain('answer it')
        ->not->toContain('do NOT ask follow-up questions, do NOT navigate,');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/CaptureTurnAnswersQuestionsTest.php`
Expected: FAIL.

- [ ] **Step 3: Amend the template**

In `FynCaptureTurnInstructions.php`, insert this block immediately BEFORE the "Off-script guardrail (FR-M14)" paragraph, and soften the two conflicting absolutes:

```
QUESTION EXCEPTION (overrides the guardrail below for questions only):
If the user's message asks a question — about a term you used ("what's salary
sacrifice?"), a financial concept, or why you're asking — ANSWER IT FIRST in
two to three plain-English sentences before anything else. Definitional and
conceptual answers only: never quote the user's own figures, never compute
their personal numbers, never give a personal recommendation in this turn —
say "I'll show you what that means for your numbers at the end" and continue.
After answering, re-ask the capture question you were on. Do NOT advance past
it. If their message contains both an answer and a question, capture the
answer with tools AND answer the question in the same turn.
```

Then edit the existing guardrail sentence "Do NOT ask any question — not with a question mark…" to start: "Outside the QUESTION EXCEPTION above, do NOT ask any question…", and remove ", do NOT ask follow-up questions" from the earlier "Do NOT greet" sentence (the exception governs questions now).

- [ ] **Step 4: Let answers through the zero-tool buffer drop**

In `OnboardingChatDirector.php` the `$flushBuffer` closure currently drops all prose on zero-tool turns:

```php
if ($toolCallsSeen === 0 || $contentBuffer === '') {
```

Replace the closure's drop condition so question-answers survive (the user message is in scope as `$message` in `handleUserMessage` — pass it into the closure with `use`):

```php
$userAskedQuestion = str_contains($message, '?');

$flushBuffer = function () use (&$contentBuffer, &$toolCallsSeen, &$flushed, $selection, $userAskedQuestion) {
    $flushed = true;
    if ($contentBuffer === '' || ($toolCallsSeen === 0 && ! $userAskedQuestion)) {
        $contentBuffer = '';

        return null;
    }
    $cleaned = $this->filterOffScriptContent($contentBuffer, $selection, allowAnswer: $userAskedQuestion);
    $contentBuffer = '';
    if ($cleaned === '') {
        return null;
    }

    return ['type' => 'content', 'text' => $cleaned];
};
```

Then open `filterOffScriptContent` (grep its definition in the same file), add the `bool $allowAnswer = false` parameter, and when `$allowAnswer === true` skip the question-mark/keyword stripping rules but KEEP the personal-figures rule (no £-figures the user didn't state this turn). Show the exact diff in the PR.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest tests/Feature/Fyn/CaptureTurnAnswersQuestionsTest.php && ./vendor/bin/pest --filter=OffScript`
Expected: PASS; any existing FR-M14 tests still green (they test non-question turns).

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(onboarding): capture turns answer user questions before resuming (A1)"
```

### Task 16: Ack hygiene — merge acks, never claim writes that didn't happen

**Files:**
- Modify: `app/Services/AI/Fyn/FynCaptureTurnInstructions.php` (ack copy rules)
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (delegated-turn ack handling region ~1880-1990)
- Test: `tests/Feature/Fyn/CaptureAckHygieneTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynCaptureTurnInstructions;

it('tells the model to stay silent when no records are captured', function () {
    $rendered = FynCaptureTurnInstructions::render('SaveTax', 'create_savings_account');

    expect($rendered)
        ->toContain('If you call no tools')
        ->not->toContain('"Got it — recording those now."');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/CaptureAckHygieneTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement the template change**

In the template: replace the example ack sentence

```
Keep your text output to a single short confirmation sentence like
"Got it — recording those now."
```

with:

```
Keep your text output to a single short confirmation sentence that states
WHAT was recorded, e.g. "Recorded — two ISAs totalling £22,000." If you call
no tools (nothing to record), output NO confirmation text at all — either
answer the user's question (QUESTION EXCEPTION) or stay silent.
```

Apply the same wording change at the duplicate source `app/Services/Onboarding/OnboardingPromptBuilder.php:173` (grep for the sentence; D4 wording-parity between the two is intentional — keep both identical).

- [ ] **Step 4: Kill the double-ack**

The May-18 tripled-ack fix gates `captureTurnCompleteDirective` on the `data_capture` persona (`OnboardingChatDirector.php:~1905-1912` comment). The remaining double-ack ("Got it — recording those now.Recorded.") is buffered LLM prose concatenated across agent-loop continuations. In the `$flushBuffer` closure (same one as Task 15), deduplicate consecutive ack-like sentences before yielding:

```php
// Collapse repeated short acks the model emits once per agent-loop pass.
$sentences = preg_split('/(?<=[.!?])\s+/', trim($cleaned)) ?: [];
$deduped = [];
foreach ($sentences as $s) {
    if ($deduped === [] || strcasecmp(end($deduped), $s) !== 0) {
        $deduped[] = $s;
    }
}
$cleaned = implode(' ', $deduped);
```

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest tests/Feature/Fyn/CaptureAckHygieneTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "fix(onboarding): acks state what was recorded, silent on zero-write turns, dedupe double-acks (A2)"
```

### Task 17: Catalogue-driven per-section advice voicing (A3)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` — `buildSectionAdvice` (find with `grep -n "buildSectionAdvice" app/Services/Onboarding/OnboardingChatDirector.php`)
- Test: `tests/Feature/Onboarding/SectionAdviceFromCatalogueTest.php`

- [ ] **Step 1: Read the current `buildSectionAdvice` implementation fully** (it currently relays per-section engine output). Record which sections map to which strategy categories/types today.

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use Database\Seeders\TaxActionDefinitionSeeder;

it('voices the ISA wrap strategy in the savings section when it is the top mechanical item', function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000, 'household_calculation_mode' => 'single_earner_couple',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 19000,
        'isa_subscription_year' => app(\App\Services\TaxConfigService::class)->getTaxYear(),
        'isa_subscription_amount' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'buildSectionAdvice');
    $method->setAccessible(true);

    $text = (string) $method->invoke($director, $user->fresh(), 'savings');

    // The Azlan miss: ISA wrap MUST be voiced in the savings section.
    expect($text)->toContain('ISA')
        ->and($text)->toMatch('/£[\d,]+/');
});
```

- [ ] **Step 3: Run test to verify it fails** (it may pass if current section advice already mentions ISAs — if so, tighten the assertion to the strategy title from `IsaTopUpStrategy` ("Wrap £…") and re-run).

Run: `./vendor/bin/pest tests/Feature/Onboarding/SectionAdviceFromCatalogueTest.php`
Expected: FAIL.

- [ ] **Step 4: Reimplement `buildSectionAdvice` over the composer**

```php
/** Strategy types voiced per campaign section (order = voice priority). */
private const SECTION_STRATEGY_TYPES = [
    'income' => ['income_band_position', 'tapered_annual_allowance'],
    'savings' => ['isa_topup_vs_psa', 'joint_savings_psa'],
    'investments' => ['bed_and_isa', 'dividend_allowance_harvest'],
    'pensions' => ['salary_sacrifice_ni', 'pension_aa_carry_forward'],
    'spouse' => ['non_earner_spouse_pension', 'asset_shifting_bundle', 'spouse_isa_after_gift', 'marriage_allowance'],
];

private function buildSectionAdvice(User $user, string $section): ?string
{
    if ($section === 'synthesis') {
        return $this->buildSynthesisAdvice($user); // Task 18
    }

    $plan = app(\App\Services\Coordination\ComposedTaxPlanService::class)->forUser($user);
    $wanted = self::SECTION_STRATEGY_TYPES[$section] ?? [];

    $lines = [];
    foreach ($plan['items'] as $item) {
        if (! in_array($item['type'], $wanted, true)) {
            continue;
        }
        // Mechanical claims voiced directly; judgement claims hedged.
        $prefix = $item['claim_tier'] === 'mechanical' ? '' : 'You may want to consider: ';
        $lines[] = $prefix.$item['title'].'. '.$item['description'];
        if (count($lines) >= 2) {
            break; // max two strategies per section — the synthesis collects the rest
        }
    }

    return $lines === [] ? null : implode("\n\n", $lines);
}
```

Preserve whatever the old implementation did for sections with no catalogue mapping (return null → the advice turn stays silent and auto-advances, existing behaviour at `emitAdviceTurn`). Delete superseded per-section hardcoded strategy copy ONLY where this method replaces it — do not touch the state-machine `prompt_text` for capture states.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest tests/Feature/Onboarding/SectionAdviceFromCatalogueTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(onboarding): per-section savetax advice voiced from the strategy catalogue (A3)"
```

### Task 18: Synthesis turn before the terminal state (A4)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` (new state + rewire `nextCampaignSection` terminal hop)
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (`buildSynthesisAdvice`)
- Test: `tests/Feature/Onboarding/CampaignSynthesisTurnTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingStateMachine;

it('routes the campaign to a synthesis advice state before the terminal state', function () {
    $states = OnboardingStateMachine::states();

    expect($states)->toHaveKey(OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS)
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['turn_type'])->toBe('advice')
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['advice_section'])->toBe('synthesis')
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['next'])->toBe(OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL);
});
```

(Confirm the public accessor for the state table — if `states()` is not public, use the same access pattern existing state-machine tests use: `grep -rn "OnboardingStateMachine::" tests/ | head -5`.)

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Onboarding/CampaignSynthesisTurnTest.php`
Expected: FAIL — constant/state missing.

- [ ] **Step 3: Add the state**

In `OnboardingStateMachine`: declare `public const STATE_CAMPAIGN_SYNTHESIS = 'campaign_synthesis';` next to the other campaign constants, add the state entry alongside the other advice states:

```php
self::STATE_CAMPAIGN_SYNTHESIS => [
    'turn_type' => 'advice',
    'advice_section' => 'synthesis',
    'capture_field' => null,
    'next' => self::STATE_CAMPAIGN_TERMINAL,
],
```

Then find `nextCampaignSection` (grep) and change its sections-exhausted return from `STATE_CAMPAIGN_TERMINAL` to `STATE_CAMPAIGN_SYNTHESIS`. **Cycle-safety:** the synthesis state's `next` is a plain constant (no self-edge) and `MAX_ADVICE_CHAIN` already guards chains — verify the chain spouse-advice → synthesis → terminal stays under the cap (read `MAX_ADVICE_CHAIN`'s value; raise by one if the new hop would exceed it, with a comment referencing the campaign_advice_spouse 17,509-row incident).

- [ ] **Step 4: Implement `buildSynthesisAdvice`**

```php
private function buildSynthesisAdvice(User $user): ?string
{
    $plan = app(\App\Services\Coordination\ComposedTaxPlanService::class)->forUser($user);
    if ($plan['items'] === []) {
        return null;
    }

    $lines = ['Here is your plan, in the order I suggest tackling it:'];
    foreach ($plan['items'] as $item) {
        $saving = $item['estimated_annual_tax_saved'];
        $savingText = is_numeric($saving) && $saving > 0
            ? sprintf(' — around £%s a year', number_format((int) round((float) $saving)))
            : '';
        $lines[] = sprintf('%d. %s%s', $item['sequence_position'], $item['title'], $savingText);
        if (! empty($item['conflict_note'])) {
            $lines[] = '   Note: '.$item['conflict_note'];
        }
    }

    $total = (float) $plan['combined_annual_saving'];
    if ($total > 0) {
        $lines[] = sprintf('Together these are worth roughly £%s a year.', number_format((int) round($total)));
    }
    foreach ($plan['locked'] as $locked) {
        $lines[] = sprintf(
            'One more strategy is waiting — tell me about your %s and I can check %s for you.',
            str_replace('_', ' ', implode(' and ', $locked['missing'])),
            str_replace('_', ' ', $locked['strategy_type']),
        );
        break; // tease at most one locked strategy here
    }
    $lines[] = 'For regulated advice personal to your circumstances, speak to a qualified financial adviser.';

    return implode("\n", $lines);
}
```

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest tests/Feature/Onboarding/CampaignSynthesisTurnTest.php && ./vendor/bin/pest --filter=Onboarding`
Expected: PASS, no onboarding regressions.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(onboarding): savetax flow ends with a ranked synthesis plan before the terminal page (A4)"
```

---

## Phase 6 — Free-form chat

### Task 19: `get_recommendations` returns the composed plan; tool descriptions rewritten

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php:1811-1820` (`handleRecommendations`)
- Modify: `app/Services/AI/XaiToolDefinitions.php:199-204` and the matching `AiToolDefinitions.php` entry (~line 144)
- Modify: golden-master fixtures for both definition classes (regenerate intentionally)
- Test: `tests/Feature/Agents/HandleRecommendationsComposedTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;
use Database\Seeders\TaxActionDefinitionSeeder;

it('returns the composed tax plan alongside ranked recommendations', function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('get_recommendations', [], $user->fresh(), null);

    expect($result)->toHaveKeys(['recommendations', 'total', 'surplus', 'composed_tax_plan'])
        ->and($result['composed_tax_plan'])->toHaveKeys(['items', 'combined_annual_saving', 'locked']);
});
```

(Match `executeTool`'s real signature — check `app/Agents/CoordinatingAgent.php:832` for parameter order; adjust the call.)

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Agents/HandleRecommendationsComposedTest.php`
Expected: FAIL — no `composed_tax_plan` key.

- [ ] **Step 3: Extend the handler**

```php
private function handleRecommendations(User $user): array
{
    $analysis = $this->orchestrateAnalysis($user->id);

    return [
        'recommendations' => $analysis['ranked_recommendations'] ?? [],
        'total' => count($analysis['ranked_recommendations'] ?? []),
        'surplus' => $analysis['available_surplus'] ?? 0,
        // Ordered, conflict-resolved tax plan with claim tiers + locked strategies.
        'composed_tax_plan' => app(\App\Services\Coordination\ComposedTaxPlanService::class)->forUser($user),
    ];
}
```

- [ ] **Step 4: Rewrite the tool description (both providers, byte-identical text)**

New description string for `get_recommendations` in BOTH `XaiToolDefinitions` and `AiToolDefinitions`:

```
Get the user's personalised, ranked financial recommendations across all modules, plus a composed tax plan (composed_tax_plan) ordered by what to do first with conflicts resolved and a combined annual saving. Call this whenever the user asks what they should do, wants strategies, or asks about saving tax. Present the top 3 to 5 items in sequence order: state each title with its pound saving, quote the working for mechanical-tier items directly, hedge judgement-tier items ("you may want to consider"). If composed_tax_plan.locked is non-empty, tell the user how many further strategies unlock and what single data point each needs. Offer to go through the remaining items rather than dumping the full list.
```

- [ ] **Step 5: Regenerate golden-master fixtures**

Run: `./vendor/bin/pest --filter=ToolDefinitions` — the byte-identity tests will fail; regenerate their fixtures per the pattern those tests document (read the failing test file for its fixture-regeneration instructions; these masters exist to make exactly this kind of change deliberate). Commit fixture changes together with the code.

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/pest tests/Feature/Agents/HandleRecommendationsComposedTest.php --filter=ToolDefinitions`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(fyn): get_recommendations returns composed plan; tool description teaches presentation (B1)"
```

**Track 2 note (do not do now):** when `coala` merges, mirror this description into `fyn-memory/procedural/tool_schema/analysis/get_recommendations*` for BOTH providers.

### Task 20: Restore the knowledge layer + add voicing rules to the unified prompt

**Files:**
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php` (insert after known-facts block, line ~59)
- Modify: `app/Constants/FinancialPlanningKnowledge.php` (`RECOMMENDATION_FRAMEWORK` refresh)
- Test: `tests/Feature/Fyn/UnifiedKnowledgeLayerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;

it('injects financial knowledge and voicing rules on advice turns', function () {
    $user = User::factory()->create(['annual_employment_income' => 110000]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $ctx = FynTurnContext::forAdvice( // use the REAL named constructor — grep FynTurnContext for how HasAiChat builds it
        user: $user, conversation: $conversation,
        message: 'Should I increase my pension contributions?',
        currentRoute: '/dashboard',
        classification: ['primary' => 'retirement_contribution', 'related' => [], 'modules' => ['retirement']],
        kycResult: null, isPreview: false,
    );

    $built = app(FynContextAssembler::class)->build($ctx, fn () => []);

    expect($built)
        ->toContain('<financial_knowledge>')
        ->toContain('AFFORDABILITY')
        ->toContain('<voicing_rules>');
});

it('omits knowledge and voicing on onboarding turns', function () {
    // Build an onboarding-mode FynTurnContext the same way OnboardingChatDirector does,
    // assert the built context contains neither block.
});
```

(Open `app/Services/AI/Fyn/FynTurnContext.php` first; mirror its actual constructor/named constructors. Fill in the second test body with the real onboarding construction — copy the pattern from the existing `FynContextAssembler` tests: `ls tests/*/Fyn* -R | grep -i assembler`.)

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/UnifiedKnowledgeLayerTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement — two additive blocks in `FynContextAssembler::build`**

Insert after the known-facts block (line ~59), before the POSITION bucket:

```php
// Financial knowledge (parity restoration). The legacy 12-layer builder
// injected FinancialPlanningKnowledge via buildKnowledgeBlock (Layer 8,
// AdvicePromptBuilder.php:189); PR #335's unified cutover dropped it with
// no per-turn replacement — same regression family as the billing layer
// above. Classification-scoped via QueryKnowledge, advice turns only.
if (! $ctx->isOnboarding()) {
    $knowledge = \App\Services\AI\Prompts\QueryKnowledge::getForClassification($ctx->classification);
    if ($knowledge !== '') {
        $lines[] = "<financial_knowledge>\n{$knowledge}\n</financial_knowledge>";
    }

    $lines[] = $this->voicingRules();
}
```

And the new private method:

```php
private function voicingRules(): string
{
    return <<<'RULES'
<voicing_rules>
Claim tiers govern how you state guidance:
- MECHANICAL claims (allowance arithmetic, tax-band maths, carry-forward totals, taper effects, recommendations marked claim_tier=mechanical): state them directly and quantified with the user's own figures, and show the working inline — e.g. "£110,000 − £10,000 contribution = £100,000, restoring your full Personal Allowance — worth around £6,000 this year." Always quote threshold figures retrieved from get_tax_information.
- JUDGEMENT claims (investment selection, trust structures, drawdown choices, anything marked claim_tier=judgement): hedge them ("you may want to consider", "one option might be") and signpost regulated advice.
Proactivity: after fully answering the user's question, you MAY surface AT MOST ONE additional high-value strategy from the recommendations if it is clearly relevant to what they asked — lead with the pound impact, keep it to two sentences, and never let it crowd the actual answer.
Ambiguity: if a figure the user gave you is ambiguous in a way that changes the answer (e.g. "£90,000" — total or per year?), ask the one clarifying question BEFORE computing anything from it.
</voicing_rules>
RULES;
}
```

- [ ] **Step 4: Refresh `RECOMMENDATION_FRAMEWORK`**

In `FinancialPlanningKnowledge.php`, update the `TAX:` line of `RECOMMENDATION_FRAMEWORK` to describe the catalogue:

```
TAX: strategy catalogue computed per-user (ISA wrapping vs Personal Savings Allowance, pension carry forward from captured contribution history, salary sacrifice, spousal transfers and spouse ISA, marriage allowance with strict eligibility, dividend allowance, Gift Aid relief, taper warnings) — composed into one sequenced plan with conflicts resolved and a combined annual saving; locked strategies list the single data point that unlocks them
```

- [ ] **Step 5: Confirm the static prompt is untouched**

Run: `./vendor/bin/pest --filter=FynSystemPrompt`
Expected: PASS — snapshot byte-identical (we changed only per-turn layers).

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/pest tests/Feature/Fyn/UnifiedKnowledgeLayerTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(fyn): restore knowledge layer to unified prompt + tiered voicing rules (1f, B2)"
```

### Task 21: Module-scoped financial context (B3) + validator capture-awareness

**Files:**
- Modify: `app/Services/AI/AdvicePromptBuilder.php` (`buildFinancialContext`, ~445-600)
- Modify: `app/Services/AI/StructuredResponseValidator.php` (locate `missing_amounts` rule: `grep -n "missing_amounts" app/Services/AI/`)
- Test: `tests/Feature/Fyn/ModuleScopedFinancialContextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;

it('renders only the classified modules detail for module-scoped queries', function () {
    $user = User::factory()->create(['annual_employment_income' => 110000]);

    $classification = ['primary' => 'retirement_contribution', 'related' => [], 'modules' => ['retirement']];
    $context = app(AdvicePromptBuilder::class)->buildFinancialContext($user, fn () => [], $classification);

    expect($context)->toContain('pension')        // retirement detail stays
        ->and($context)->not->toContain('Protection gap'); // other modules' detail filtered
});
```

(Calibrate the two assertion strings to the actual section headings in `buildFinancialContext` — read lines 473-560 first and pick one retained heading + one filtered heading verbatim.)

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/ModuleScopedFinancialContextTest.php`
Expected: FAIL — all modules render today.

- [ ] **Step 3: Implement the filter inside `buildFinancialContext`**

At the top of the module-snapshot section:

```php
$scopedModules = \App\Constants\QuerySchemas::getModulesForClassification($classification ?? []);
$renderModule = fn (string $module): bool => $scopedModules === [] || in_array($module, $scopedModules, true);
```

Wrap each per-module snapshot block (savings, investments, retirement, protection, property, IHT) in `if ($renderModule('savings')) { … }` etc. **Keep ALWAYS-rendered:** net worth totals, monthly surplus, goals, life events, and the ranked-recommendations block (the strategy headlines stay holistic on every advice turn — spec B3). Holistic classifications return `[]`/all-modules from `getModulesForClassification` → everything renders, unchanged.

- [ ] **Step 4: Validator capture-awareness**

In `StructuredResponseValidator`, find the `missing_amounts` check ("Advice response contains no specific £ amounts"). Gate it on persona: skip when the message's persona is `data_capture` (the persona string is available at the `validateAndLog` call site — `app/Traits/HasAiChat.php:764-765` region passes message context; thread the persona through as a parameter if it is not already). Add one unit test: a 5-word ack under persona `data_capture` produces zero violations.

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/pest tests/Feature/Fyn/ModuleScopedFinancialContextTest.php && ./vendor/bin/pest --filter=Validator`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(fyn): module-scoped financial context + capture-aware missing_amounts validator (B3)"
```

---

## Phase 7 — `/m` strategy unlock cards

### Task 22: Strategy-level unlock items in `NextActionsService`

**Files:**
- Modify: `app/Services/Mobile/NextActionsService.php` (`unlockItems`, ~line 236)
- Test: `tests/Feature/Mobile/StrategyUnlockCardsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\TaxActionDefinitionSeeder;

it('surfaces a strategy-level unlock card naming the missing data point', function () {
    $this->seed(TaxActionDefinitionSeeder::class);
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]); // tax gate passes; no pension records → salary_sacrifice_ni locked

    $actions = app(NextActionsService::class)->build($user->fresh());

    $strategyUnlocks = array_values(array_filter(
        $actions['items'] ?? $actions, // match build()'s actual return shape — read it first
        fn ($a) => ($a['type'] ?? '') === 'strategy_unlock'
    ));
    expect($strategyUnlocks)->not->toBeEmpty()
        ->and($strategyUnlocks[0]['id'])->toStartWith('strategy_unlock:');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Mobile/StrategyUnlockCardsTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

In `unlockItems()` (or a sibling `strategyUnlockItems()` merged at the same call sites), after the module loop:

```php
// Strategy-level unlocks: tax gate open but individual strategies locked
// by a single missing data point each. Tease the value, link the fix.
if ($this->gate->enforce('tax_optimisation', $user)['can_proceed'] === true) {
    $plan = app(\App\Services\Coordination\ComposedTaxPlanService::class)->forUser($user);
    foreach (array_slice($plan['locked'], 0, 2) as $locked) {
        $missingLabel = str_replace('_', ' ', (string) ($locked['missing'][0] ?? 'a detail'));
        $items[] = [
            'id' => 'strategy_unlock:'.$locked['strategy_type'],
            'type' => 'strategy_unlock',
            'module' => 'tax',
            'title' => 'Unlock a tax strategy',
            'meta' => 'Tell us about your '.$missingLabel,
            'weight' => $weight,
            'route' => '/tax-strategy',
        ];
    }
}
```

Match the exact item shape of the existing unlock cards (read lines 249-260 fully and replicate every key — `weight`, `route`/`action`, label keys — so the `/m` carousel renders them without frontend changes). Rules 12/15: plain text, no icons, no scores.

- [ ] **Step 4: Run tests + mobile cache note**

Run: `./vendor/bin/pest tests/Feature/Mobile/StrategyUnlockCardsTest.php`
Expected: PASS. After deploy: `php artisan cache:clear` (mobile dashboard cached 5 min/user).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint --dirty
git add -A && git commit -m "feat(m): strategy-level unlock cards on the mobile next-actions feed"
```

---

## Phase 8 — Golden scenarios + live verification

### Task 23: Azlan golden scenario + persona advice scenarios

**Files:**
- Create: `tests/Feature/Fyn/Eval/scenarios/02-insight-quality/azlan_savetax_journey.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/02-insight-quality/peak_earners_composed_recommendations.json`
- Test: run via the existing eval recorder/driver

- [ ] **Step 1: Write the Azlan scenario** (multi-turn, mirrors the 2026-06-09 transcript; persona/user setup per the eval harness's seeded-persona mechanism — `peak_earners` is the closest persona; the scenario seeds Azlan-shaped data via its `is_mutating` setup turns):

```json
{
  "id": "azlan_savetax_journey",
  "category": "02-insight-quality",
  "persona": "peak_earners",
  "is_mutating": true,
  "description": "Replay of the 2026-06-09 Azlan transcript failure modes. A £110k single-earner-couple CMO goes through the savetax flow. Must-surface: ISA wrap, salary sacrifice answered when asked, carry-forward ambiguity clarified. Must-not: standalone acks, zero-write 'recording' claims, advancing past a direct question.",
  "input": {
    "turns": [
      {"user": "£110,000 at dentsu and I'm the CMO.", "current_route": "/dashboard"},
      {"user": "I have a cash isa and a stocks and shares ISA. Cash: 19000 Stocks and shares: 3000. About £100 this year", "current_route": "/dashboard"},
      {"user": "About £500 in premium bonds and £81000 savings. Savings is 3.25%", "current_route": "/dashboard"},
      {"user": "3% contribution and it's matched. What's salary sacrifice?", "current_route": "/dashboard"},
      {"user": "Around £90000", "current_route": "/dashboard"}
    ]
  },
  "expected_assistant_text": {
    "turn_assertions": [
      {"turn": 3, "must_contain_at_least_one_of": [["salary sacrifice"]],
       "must_not_contain_substrings": ["Got it — recording those now."],
       "note": "The direct question MUST be answered in this turn."},
      {"turn": 4, "must_contain_at_least_one_of": [["each year", "per year", "in total", "altogether"]],
       "note": "£90,000 is ambiguous (total vs per-year) — a clarifying question must come before any carry-forward arithmetic."}
    ],
    "journey_assertions": {
      "must_contain_at_least_one_of": [
        ["Wrap", "ISA allowance", "into your ISA"]
      ],
      "must_not_contain_substrings": ["recording those now.Recorded."]
    }
  },
  "expected_sse_events": {
    "must_not_emit": ["persona_state_change", "error"]
  }
}
```

**Adapt the assertion keys to the harness's actual schema** — open `app/Services/Eval/EvalHttpDriver.php`'s assertion loader (or the scenario-asserting test) first; if `turn_assertions`/`journey_assertions` don't exist as keys, extend the assertion engine with them in this task (small additive change, TDD with one unit test per new key), or express the same checks using the existing per-scenario keys with one scenario file per turn.

- [ ] **Step 2: Write the peak_earners composed-recommendations scenario**

```json
{
  "id": "peak_earners_composed_recommendations",
  "category": "02-insight-quality",
  "persona": "peak_earners",
  "is_mutating": false,
  "description": "David Mitchell asks what he should do. get_recommendations must fire; the reply must present sequenced strategies with pound figures and working, capped count, no raw dump.",
  "input": {"turns": [{"user": "What should I be doing to improve my finances and save tax?", "current_route": "/dashboard"}]},
  "expected_tool_calls": ["get_recommendations"],
  "expected_assistant_text": {
    "must_contain_at_least_one_of": [["£"], ["first", "start with", "1."]],
    "must_not_contain_substrings": ["priority_score", "recommendation_id", "claim_tier"],
    "minimum_length_chars": 200
  },
  "expected_sse_events": {"must_contain_types": ["content", "done"], "must_emit_exactly_once": ["done"]}
}
```

- [ ] **Step 3: Write the remaining persona scenarios (spec §6)**

Three more files in `02-insight-quality/`, same shape as `peak_earners_composed_recommendations.json`, one per persona, each with a persona-appropriate must-surface set:
- `young_saver_first_strategies.json` — persona `young_saver`, asks "how do I start saving properly?"; must surface emergency-fund guidance before ISA/pension contributions (AFFORDABILITY_RULES ordering); must NOT recommend carry-forward or trust strategies.
- `entrepreneur_tax_position.json` — persona `entrepreneur`, asks "how can I be more tax efficient?"; must quote figures from the user's actual data; must hedge any judgement-tier item.
- `retired_couple_decumulation.json` — persona `retired_couple`, asks "are we drawing our money in the right order?"; must NOT fire accumulation strategies (salary sacrifice, carry-forward for a retired user); judgement-tier voicing throughout; FCA signposting present.

- [ ] **Step 4: Run all scenarios through the recorder**

Run: `php artisan list | grep eval` then the record command for each scenario.
Expected: all runs complete; assertions evaluated. **LOOP UNTIL GREEN (Rule #14)** — failures here are real engineering issues (fix code, never soften assertions).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "test(eval): Azlan savetax golden scenario + composed recommendations scenario"
```

### Task 24: Live browser verification (Playwright) — Azlan replica end-to-end

**Files:** none (verification task; bugs found route through fix sub-tasks per Rule #14)

- [ ] **Step 1: Environment**

`./dev.sh` running; `php artisan db:seed` done; create a fresh user via the real register flow (savetax funnel entry per the campaign path — `http://localhost:8000` → savetax CTA), fetching the verification code from the DB per CLAUDE.md Authentication for Testing.

- [ ] **Step 2: Drive the full savetax journey in Playwright** (click/fill/submit every step):
  1. Answer income: "£110,000 at dentsu and I'm the CMO." → expect income-section advice citing the 60% taper with a pound figure.
  2. ISA step: "Cash ISA 19000, stocks and shares 3000, about £100 this year" → expect ack stating what was recorded (no bare "recording those now"), then savings prompt.
  3. Savings: "£500 premium bonds and £81000 savings at 3.25%" → savings-section advice MUST include the ISA wrap strategy (~£20k, with a £ saving), ack rules hold.
  4. Workplace pension: "3% contribution and it's matched. What's salary sacrifice?" → the answer to the question MUST come first (2–3 plain sentences), then the capture re-prompt; the flow must NOT advance past the question unanswered.
  5. Pension history: "Around £90000" → Fyn MUST ask total-or-per-year BEFORE computing carry-forward; answer "in total across the three years" → carry-forward advice with visible working.
  6. Spouse: "No, they don't currently work" → "All in mine" → household strategies (spouse pension framed correctly, asset shifting, spouse ISA after gift).
  7. End of flow: the synthesis turn lists the numbered plan with a combined total, then navigates to `/tax-strategy`; the page's plan matches the chat synthesis (same strategies, same total).
- [ ] **Step 3: Verify DB state**: savings accounts (ISA subscription amount = 100), `PensionInputHistory` rows, `TaxStrategyHouseholdInput` row, no duplicate records.
- [ ] **Step 4: Free-form check**: in chat ask "What should I do first?" → response presents sequenced strategies with figures, ≤5 items, one proactive extra max.
- [ ] **Step 5: `/m` check**: load the mobile dashboard (`/m` route per memory `reference_mobile_phone_entry_responsive`), confirm tax recommendations/unlock cards render in next-actions. `php artisan cache:clear` first.
- [ ] **Step 6:** Any RED → systematic-debugging skill → fix → **restart this task from Step 1** (Rule #14). Only after full GREEN: final commit + report.

### Task 25: Full regression + wrap

- [ ] **Step 1:** `./vendor/bin/pest` — full suite green (including the ~1.5k savetax tests).
- [ ] **Step 2:** `./vendor/bin/pint`
- [ ] **Step 3:** Update `database/seeders/DatabaseSeeder.php` if `TaxActionDefinitionSeeder` is not already in the run list (check first), reseed, confirm `php artisan db:seed` idempotent.
- [ ] **Step 4:** Commit any stragglers; prepare the PR to `dev` summarising: catalogue reconciliation, guided-flow A1–A4, chat B1–B3, knowledge-layer restoration, `/m` unlock cards, eval scenarios. PR body ends with the standard Claude Code attribution.

---

## Deferred (reported, not built — do not silently add)

- `DebtBeforeSavingsStrategy` — no liability/debt model exists on dev; needs a capture flow + schema first (CSJ decision). `AFFORDABILITY_RULES` (restored in Task 20) covers the guidance narratively meanwhile.
- Child benefit taper, emergency-fund proportionality, premium-bonds reality-check strategies — pending CSJ approval of the extended strategy set.
- Employer salary-sacrifice availability/match-structure capture fields — `salary_sacrifice_ni` currently keys off existing pension data; richer capture is a follow-up once CSJ approves the field additions (spec 5D candidates).
- Metadata population for the five non-tax modules' action definitions (columns exist after Task 2; content follows when those modules get the catalogue treatment).
- Track 2 (coala): house_view corpus from catalogue ids, `RecommendationHandler` returning the composed plan, planner heuristics, capture overlays, tool_schema `.md` mirroring — separate plan when coala work resumes.
