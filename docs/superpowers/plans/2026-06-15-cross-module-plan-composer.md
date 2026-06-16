# Cross-Module Plan Composer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generalise the tax-only composed-plan machinery so every module produces an ordered, conflict-aware, claim-tier-voiced plan (with locked-strategy unlock prompts), and add a cross-module composite layer that ranks the combined plan by affordability and folds in goals and life events — all reached CoALA-natively through the FynLoop's pointers/handlers, with catalogue knowledge authored as a semantic + procedural + DB triple.

**Architecture:** A new `ModuleStrategySource` interface abstracts each module's (recommendations, metadata rows, availability) triple. A generalised `ComposedModulePlanService` runs any source through the **unchanged** pure `StrategyPlanComposer`. Tax is refactored onto this seam with zero behaviour change (byte-identity pinned). A new `CompositePlanService` gathers every module's composed plan, ranks by a finite monthly surplus, and annotates affordability. Both reach Fyn through new `FetchHandler`s + pointer `.md` defs; nothing is ever frozen into the prompt (lean-prompt / pointer law).

**Tech Stack:** Laravel 10, PHP 8.2 (`declare(strict_types=1)`), Pest, MySQL 8, the existing CoALA substrate (`FynLoop`, `FetchHandlerRegistry`, `PointerRegistry`, `SemanticRetriever`), Vue 3 + Vuex for surfaces.

**Spec:** `docs/superpowers/specs/2026-06-15-cross-module-plan-composer-design.md` (approved). Read it before starting. Read **Appendix A** of this plan for the exact substrate map (signatures, return shapes, file:line) — it means you do not need to re-discover the codebase.

**Branch:** `cross-module-plan-composer` (already created off `dev`). Work sequentially in the main working dir (per `feedback_never_switch_branches`). All PRs target `dev`.

**Canonical guardrails (do not violate):**
- `FynSystemPrompt::text()` is byte-invariant — never edit it. New knowledge is corpus + pointers, never prompt text.
- The pointer law: a composed plan is live-owned data, fetched at the moment of need. Never copy plan figures into the corpus or the prompt.
- house_view / pointer corpus bodies must contain **no `£` figures** (guard tests enforce this).
- Pointer corpus is **fail-closed**: a pointer `.md` whose `handler` is not registered in `FetchHandlerRegistry` makes `PointerRegistry::all()` throw and breaks the whole chat tool catalogue. **Always register the handler in code first, add the `.md` second.**
- Rule #19: every user-facing surface is web **and** `/m`. Rule #15: no decorative icons. Rule #12: no financial-quality scores.

---

## Phase Overview & Sequencing

| Phase | Deliverable (independently testable) | Risk |
|-------|--------------------------------------|------|
| **1** | `StrategyRecommendation` cost fields + `ModuleStrategySource` + `ComposedModulePlanService`; **tax refactored onto it, byte-identical** | **Highest** (tax is live on prod) |
| **2** | Per-module sources + adapters (Retirement → Savings → Investment → Protection → Estate) + `strategy_type` migration + `source='strategy'` catalogue rows + availability vocabulary | Medium |
| **3** | Catalogue triple: house_view narratives + pointer defs + handler registration + reindex/golden recapture | Low–Medium |
| **4** | `CompositePlanService` (affordability rank+annotate, cross-module sequencing, goals-as-demands, life-events-as-modifiers, episodic recall) + `CrossModulePlanHandler` (tool) | Medium |
| **5** | Surfaces: `RecommendationsAggregatorService` wire-through, `/holistic-plan` composite view (web **and** new `/m` surface), Fyn handlers | Medium |
| **6** | Full test pass + golden recapture + browser E2E (web + `/m`) | — |

**Phase 1 must land and be byte-identity-green before Phase 2 adds any module.** Each phase ends with a commit and a green suite.

---

## PHASE 1 — DTO cost fields + generalise the composer (tax byte-identical)

**Why first / why highest risk:** Tax's composed plan is live on prod and consumed by Fyn through two paths (skill `RecommendationHandler::fetch` and tool `CoordinatingAgent::handleRecommendations`), pinned by `RecommendationHandlerParityTest` + `ComposedTaxPlanServiceDerivationsTest` + the tool-schema golden masters. Any change to `StrategyRecommendation::toArray()` or the composed-plan shape changes `planDigest` output. We add affordability cost fields **without** changing tax's serialised output by serialising the new keys **only when non-null** (tax sets them null → identical bytes).

### Task 1.1 — Add nullable cost fields to `StrategyRecommendation`

**Files:**
- Modify: `app/DataTransferObjects/StrategyRecommendation.php`
- Test: `tests/Unit/DataTransferObjects/StrategyRecommendationCostFieldsTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DataTransferObjects/StrategyRecommendationCostFieldsTest.php`:

```php
<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;

it('omits cost keys from toArray when both costs are null (byte-identity for tax)', function (): void {
    $rec = new StrategyRecommendation(
        type: 'isa_topup_vs_psa',
        category: StrategyCategory::Allowance,
        priority: 'high',
        title: 'Wrap excess cash',
        description: 'Move taxable interest into an ISA.',
        estimatedAnnualTaxSaved: 312.5,
    );

    // No required_monthly_cost / required_lump_sum keys appear at all.
    expect($rec->toArray())->toBe([
        'type' => 'isa_topup_vs_psa',
        'category' => 'allowance',
        'priority' => 'high',
        'title' => 'Wrap excess cash',
        'description' => 'Move taxable interest into an ISA.',
        'estimated_annual_tax_saved' => 312.5,
        'requires_advice' => false,
    ]);
});

it('includes cost keys in toArray when set, and round-trips through fromArray', function (): void {
    $rec = new StrategyRecommendation(
        type: 'increase_pension_contribution',
        category: StrategyCategory::Lifecycle,
        priority: 'medium',
        title: 'Increase pension contribution',
        description: 'Raise your monthly DC contribution to use unused allowance.',
        estimatedAnnualTaxSaved: null,
        requiresAdvice: false,
        extra: [],
        requiredMonthlyCost: 250.0,
        requiredLumpSum: null,
    );

    $arr = $rec->toArray();
    expect($arr['required_monthly_cost'])->toBe(250.0)
        ->and($arr)->not->toHaveKey('required_lump_sum');

    $round = StrategyRecommendation::fromArray(StrategyCategory::Lifecycle, $arr);
    expect($round->requiredMonthlyCost)->toBe(250.0)
        ->and($round->requiredLumpSum)->toBeNull();
});

it('reads lump sum cost from a legacy array via fromArray', function (): void {
    $rec = StrategyRecommendation::fromArray(StrategyCategory::Lifecycle, [
        'type' => 'bed_and_isa',
        'priority' => 'low',
        'title' => 'Bed and ISA',
        'description' => 'Crystallise gains and re-wrap.',
        'required_lump_sum' => 20000.0,
    ]);

    expect($rec->requiredLumpSum)->toBe(20000.0)
        ->and($rec->requiredMonthlyCost)->toBeNull()
        // cost keys must NOT leak into extra (they are reserved)
        ->and($rec->extra)->not->toHaveKey('required_lump_sum');
});
```

- [ ] **Step 2: Run it to confirm it fails**

Run: `./vendor/bin/pest tests/Unit/DataTransferObjects/StrategyRecommendationCostFieldsTest.php`
Expected: FAIL — unknown named argument `$requiredMonthlyCost`, and the round-trip assertions error.

- [ ] **Step 3: Add the two promoted properties to the constructor**

In `app/DataTransferObjects/StrategyRecommendation.php`, extend the constructor signature (after `$extra`):

```php
    public function __construct(
        public readonly string $type,
        StrategyCategory|string $category,
        StrategyPriority|string $priority,
        public readonly string $title,
        public readonly string $description,
        public readonly ?float $estimatedAnnualTaxSaved = null,
        public readonly bool $requiresAdvice = false,
        public readonly array $extra = [],
        public readonly ?float $requiredMonthlyCost = null,
        public readonly ?float $requiredLumpSum = null,
    ) {
```

(Leave the enum-normalising body unchanged.)

- [ ] **Step 4: Read the cost keys in `fromArray` and reserve them**

Replace the `$reservedKeys` array and the `return new self(...)` block in `fromArray`:

```php
        $reservedKeys = [
            'type', 'category', 'priority', 'title', 'description',
            'estimated_annual_tax_saved', 'requires_advice',
            'required_monthly_cost', 'required_lump_sum',
        ];

        return new self(
            type: (string) ($arr['type'] ?? ''),
            category: $category,
            priority: (string) ($arr['priority'] ?? 'medium'),
            title: (string) ($arr['title'] ?? ''),
            description: (string) ($arr['description'] ?? ''),
            estimatedAnnualTaxSaved: isset($arr['estimated_annual_tax_saved'])
                ? (float) $arr['estimated_annual_tax_saved']
                : null,
            requiresAdvice: (bool) ($arr['requires_advice'] ?? false),
            extra: array_diff_key($arr, array_flip($reservedKeys)),
            requiredMonthlyCost: isset($arr['required_monthly_cost'])
                ? (float) $arr['required_monthly_cost']
                : null,
            requiredLumpSum: isset($arr['required_lump_sum'])
                ? (float) $arr['required_lump_sum']
                : null,
        );
```

- [ ] **Step 5: Serialise the cost keys ONLY when non-null in `toArray`**

Replace the `toArray()` body:

```php
    public function toArray(): array
    {
        $base = [
            'type' => $this->type,
            'category' => $this->category,
            'priority' => $this->priority,
            'title' => $this->title,
            'description' => $this->description,
            'estimated_annual_tax_saved' => $this->estimatedAnnualTaxSaved,
            'requires_advice' => $this->requiresAdvice,
        ];

        // Cost fields are first-class for affordability ranking, but omitted
        // when null so tax plans (which never set them) serialise byte-identically
        // to the pre-cost shape — preserving planDigest parity.
        if ($this->requiredMonthlyCost !== null) {
            $base['required_monthly_cost'] = $this->requiredMonthlyCost;
        }
        if ($this->requiredLumpSum !== null) {
            $base['required_lump_sum'] = $this->requiredLumpSum;
        }

        return array_merge($base, $this->extra);
    }
```

- [ ] **Step 6: Run the new test — expect PASS**

Run: `./vendor/bin/pest tests/Unit/DataTransferObjects/StrategyRecommendationCostFieldsTest.php`
Expected: PASS (3 passed).

- [ ] **Step 7: Run the tax byte-identity guards — expect STILL GREEN**

Run:
```bash
./vendor/bin/pest \
  tests/Unit/Services/Coordination/ComposedTaxPlanServiceDerivationsTest.php \
  tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerParityTest.php \
  tests/Feature/AI/ToolSchemaGoldenMasterTest.php \
  tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
```
Expected: PASS, all. If `planDigest` parity fails here, a non-null cost key leaked into a tax item — fix Step 5 (conditional inclusion) before continuing.

- [ ] **Step 8: Commit**

```bash
./vendor/bin/pint app/DataTransferObjects/StrategyRecommendation.php tests/Unit/DataTransferObjects/StrategyRecommendationCostFieldsTest.php
git add app/DataTransferObjects/StrategyRecommendation.php tests/Unit/DataTransferObjects/StrategyRecommendationCostFieldsTest.php
git commit -m "feat(composer): add nullable cost fields to StrategyRecommendation (null-omitted for tax byte-identity)"
```

### Task 1.2 — Define the `ModuleStrategySource` interface

**Files:**
- Create: `app/Services/Coordination/PlanSources/ModuleStrategySource.php`

- [ ] **Step 1: Write the interface**

```php
<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * One module's contribution to a composed plan: the three module-specific
 * inputs the generic ComposedModulePlanService needs. Implementations adapt
 * each module's bespoke recommendation engine + catalogue + data-availability
 * into the common currency. The composition itself (sort, sequencing, conflict
 * resolution, locked derivation) is module-agnostic and lives in
 * ComposedModulePlanService + the pure StrategyPlanComposer.
 */
interface ModuleStrategySource
{
    /** Stable module key: 'tax', 'retirement', 'savings', 'investment', 'protection', 'estate'. */
    public function moduleKey(): string;

    /** @return list<StrategyRecommendation> the eligible recommendations, as the common DTO */
    public function recommendations(User $user): array;

    /**
     * The enabled source='strategy' catalogue rows for this module, each exposing
     * strategy_type, claim_tier, sequencing (array{do_before:list,conflicts_with:list}),
     * and required_data (list<string>). One row per strategy_type.
     *
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function metadataRows(): Collection;

    /** @return array<string, bool> data-availability map keyed by required_data vocabulary */
    public function availability(User $user): array;
}
```

- [ ] **Step 2: Commit (interface only, no test needed yet)**

```bash
git add app/Services/Coordination/PlanSources/ModuleStrategySource.php
git commit -m "feat(composer): ModuleStrategySource interface — per-module input contract"
```

### Task 1.3 — Generalise the join into `ComposedModulePlanService`

This is the exact logic currently inside `ComposedTaxPlanService::forUser()` (Appendix A §3), made source-driven. The static helpers `extractStrategyIds()` and `planDigest()` move here (they are already module-agnostic), and `ComposedTaxPlanService` keeps byte-for-byte-compatible static delegators so every existing caller (`CoordinatingAgent:1888,1900`, `RecommendationHandler:29`, the derivation + parity tests) keeps working unchanged.

**Files:**
- Create: `app/Services/Coordination/ComposedModulePlanService.php`
- Test: `tests/Unit/Services/Coordination/ComposedModulePlanServiceTest.php` (create)

- [ ] **Step 1: Write the failing test (uses a fake source — proves module-agnosticism)**

```php
<?php

declare(strict_types=1);

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use App\Services\Coordination\StrategyPlanComposer;
use App\Models\User;
use Illuminate\Support\Collection;

function fakeSource(array $recs, array $rows, array $availability): ModuleStrategySource
{
    return new class($recs, $rows, $availability) implements ModuleStrategySource
    {
        public function __construct(
            private array $recs,
            private array $rows,
            private array $availability,
        ) {}

        public function moduleKey(): string { return 'retirement'; }
        public function recommendations(User $user): array { return $this->recs; }
        public function metadataRows(): Collection { return collect($this->rows); }
        public function availability(User $user): array { return $this->availability; }
    };
}

it('composes a module plan from a source and derives locked from unmet required_data', function (): void {
    $rec = new StrategyRecommendation(
        type: 'increase_pension_contribution',
        category: StrategyCategory::Lifecycle,
        priority: 'high',
        title: 'Increase pension contribution',
        description: 'Use unused annual allowance.',
        estimatedAnnualTaxSaved: 800.0,
    );

    // Two catalogue rows: one fired (above), one locked behind missing data.
    $firedRow = (object) [
        'strategy_type' => 'increase_pension_contribution',
        'claim_tier' => 'mechanical',
        'sequencing' => ['do_before' => [], 'conflicts_with' => []],
        'required_data' => ['pension_contributions'],
    ];
    $lockedRow = (object) [
        'strategy_type' => 'carry_forward_unused_allowance',
        'claim_tier' => 'judgement',
        'sequencing' => ['do_before' => [], 'conflicts_with' => []],
        'required_data' => ['pension_input_history'],
    ];

    $service = new ComposedModulePlanService(new StrategyPlanComposer);
    $source = fakeSource(
        recs: [$rec],
        rows: [$firedRow, $lockedRow],
        availability: ['pension_contributions' => true, 'pension_input_history' => false],
    );

    $plan = $service->forSource($source, new User);

    expect($plan['items'])->toHaveCount(1)
        ->and($plan['items'][0]['type'])->toBe('increase_pension_contribution')
        ->and($plan['locked'])->toBe([
            ['strategy_type' => 'carry_forward_unused_allowance', 'missing' => ['pension_input_history']],
        ]);
});

it('extractStrategyIds and planDigest behave identically to the tax facade', function (): void {
    $plan = [
        'items' => [['type' => 'a'], ['type' => '']],
        'combined_annual_saving' => 0.0,
        'locked' => [['strategy_type' => 'b', 'missing' => ['x']]],
    ];

    expect(ComposedModulePlanService::extractStrategyIds($plan))
        ->toBe(\App\Services\Coordination\ComposedTaxPlanService::extractStrategyIds($plan))
        ->and(ComposedModulePlanService::planDigest($plan))
        ->toBe(\App\Services\Coordination\ComposedTaxPlanService::planDigest($plan));
});
```

- [ ] **Step 2: Run it — expect FAIL (class missing)**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/ComposedModulePlanServiceTest.php`
Expected: FAIL — `Class "App\Services\Coordination\ComposedModulePlanService" not found`.

- [ ] **Step 3: Implement `ComposedModulePlanService`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\User;
use App\Services\Coordination\PlanSources\ModuleStrategySource;

/**
 * Module-agnostic composed plan: takes any ModuleStrategySource, joins its
 * recommendations with its catalogue metadata, derives the locked list
 * (enabled strategy rows whose required_data are not all available and which
 * produced no recommendation), and runs the pure StrategyPlanComposer.
 * Generalised verbatim from ComposedTaxPlanService::forUser — tax is now one
 * source among many. Locked strategies are surfaced as unlock prompts, never
 * silently skipped.
 */
final class ComposedModulePlanService
{
    public function __construct(private readonly StrategyPlanComposer $composer) {}

    /**
     * @return array{items: list<array<string,mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}
     */
    public function forSource(ModuleStrategySource $source, User $user): array
    {
        $recommendations = $source->recommendations($user);
        $rows = $source->metadataRows();

        $metadata = [];
        foreach ($rows as $row) {
            $metadata[$row->strategy_type] = [
                'claim_tier' => (string) $row->claim_tier,
                'sequencing' => $row->sequencing ?? ['do_before' => [], 'conflicts_with' => []],
            ];
        }

        $availability = $source->availability($user);
        $firedTypes = array_map(fn (StrategyRecommendation $r): string => $r->type, $recommendations);

        $locked = [];
        foreach ($rows as $row) {
            $missing = array_values(array_filter(
                (array) ($row->required_data ?? []),
                fn (string $key): bool => ($availability[$key] ?? false) === false
            ));
            if ($missing !== [] && ! in_array($row->strategy_type, $firedTypes, true)) {
                $locked[] = ['strategy_type' => (string) $row->strategy_type, 'missing' => $missing];
            }
        }

        return $this->composer->compose($recommendations, $metadata, $locked);
    }

    /**
     * Module-agnostic strategy-id derivation (moved from ComposedTaxPlanService).
     *
     * @param  array{items: list<array<string,mixed>>, locked: list<array{strategy_type: string, missing: list<string>}>}  $plan
     * @return array{surfaced: list<string>, locked: list<string>}
     */
    public static function extractStrategyIds(array $plan): array
    {
        $surfaced = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['type'] ?? ''),
            $plan['items'],
        ), static fn (string $id): bool => $id !== ''));

        $locked = array_values(array_map(
            static fn (array $l): string => (string) $l['strategy_type'],
            $plan['locked'],
        ));

        return ['surfaced' => $surfaced, 'locked' => $locked];
    }

    /**
     * The harmonised plan digest — same encoding RecommendationHandler::fetch
     * passes to FetchResult::make. Byte-stable; pinned by parity tests.
     *
     * @param  array<string, mixed>  $plan
     */
    public static function planDigest(array $plan): string
    {
        return substr(hash('sha256', (string) json_encode(['composed_tax_plan' => $plan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 16);
    }
}
```

> **Note:** the digest preimage key stays `'composed_tax_plan'` for **every** module — it is the digest namespace, not a tax label, and changing it would break the byte-stable tax parity. Do not rename it.

- [ ] **Step 4: Run the new test — expect PASS**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/ComposedModulePlanServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Coordination/ComposedModulePlanService.php tests/Unit/Services/Coordination/ComposedModulePlanServiceTest.php
git add app/Services/Coordination/ComposedModulePlanService.php tests/Unit/Services/Coordination/ComposedModulePlanServiceTest.php
git commit -m "feat(composer): ComposedModulePlanService — module-agnostic plan composition"
```

### Task 1.4 — Implement `TaxStrategySource` (wrap existing tax logic)

**Files:**
- Create: `app/Services/Coordination/PlanSources/TaxStrategySource.php`
- Test: `tests/Unit/Services/Coordination/PlanSources/TaxStrategySourceTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Coordination\PlanSources\TaxStrategySource;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\ComposedTaxPlanService;

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->seed(\Database\Seeders\TaxActionDefinitionSeeder::class);
});

it('produces the same plan via TaxStrategySource as ComposedTaxPlanService::forUser', function (): void {
    $user = User::factory()->create(['annual_employment_income' => 110000]);

    $viaFacade = app(ComposedTaxPlanService::class)->forUser($user->fresh());
    $viaSource = app(ComposedModulePlanService::class)
        ->forSource(app(TaxStrategySource::class), $user->fresh());

    expect(ComposedModulePlanService::planDigest($viaSource))
        ->toBe(ComposedModulePlanService::planDigest($viaFacade));
});

it('reports moduleKey tax', function (): void {
    expect(app(TaxStrategySource::class)->moduleKey())->toBe('tax');
});
```

- [ ] **Step 2: Run it — expect FAIL (class missing)**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/PlanSources/TaxStrategySourceTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `TaxStrategySource`** (lifts lines 34–51 of the old `ComposedTaxPlanService`)

```php
<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Tax\TaxStrategyCalculator;
use Illuminate\Support\Collection;

/** Tax module's plan source — wraps the calculator + tax catalogue + household availability. */
final class TaxStrategySource implements ModuleStrategySource
{
    public function __construct(
        private readonly TaxStrategyCalculator $calculator,
        private readonly HouseholdFinancialContext $context,
    ) {}

    public function moduleKey(): string
    {
        return 'tax';
    }

    public function recommendations(User $user): array
    {
        $output = $this->calculator->calculate($user);

        return array_map(
            fn (array $r) => StrategyRecommendation::fromArray((string) $r['category'], $r),
            $output->recommendations
        );
    }

    public function metadataRows(): Collection
    {
        return TaxActionDefinition::where('source', 'strategy')->where('is_enabled', true)->get();
    }

    public function availability(User $user): array
    {
        return $this->context->availability($user);
    }
}
```

- [ ] **Step 4: Run the test — expect PASS**

Run: `./vendor/bin/pest tests/Unit/Services/Coordination/PlanSources/TaxStrategySourceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Coordination/PlanSources/TaxStrategySource.php tests/Unit/Services/Coordination/PlanSources/TaxStrategySourceTest.php
git add app/Services/Coordination/PlanSources/TaxStrategySource.php tests/Unit/Services/Coordination/PlanSources/TaxStrategySourceTest.php
git commit -m "feat(composer): TaxStrategySource adapter (parity with ComposedTaxPlanService)"
```

### Task 1.5 — Refactor `ComposedTaxPlanService` onto the seam (keep public API byte-stable)

**Files:**
- Modify: `app/Services/Coordination/ComposedTaxPlanService.php`

- [ ] **Step 1: Replace the body — `forUser` delegates; statics delegate to the canonical home**

```php
<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\User;
use App\Services\Coordination\PlanSources\TaxStrategySource;

/**
 * The tax facade. Now a thin adapter over ComposedModulePlanService +
 * TaxStrategySource — preserved as a named entry point because Fyn's tax paths
 * (CoordinatingAgent::handleRecommendations, RecommendationHandler::fetch) and
 * the parity tests reference it by name. forUser + the two statics are
 * byte-stable; the locked-strategy / claim-tier behaviour is unchanged.
 */
final class ComposedTaxPlanService
{
    public function __construct(
        private readonly ComposedModulePlanService $composedModulePlanService,
        private readonly TaxStrategySource $taxSource,
    ) {}

    /**
     * @return array{items: list<array<string,mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}
     */
    public function forUser(User $user): array
    {
        return $this->composedModulePlanService->forSource($this->taxSource, $user);
    }

    /**
     * @param  array{items: list<array<string,mixed>>, locked: list<array{strategy_type: string, missing: list<string>}>}  $plan
     * @return array{surfaced: list<string>, locked: list<string>}
     */
    public static function extractStrategyIds(array $plan): array
    {
        return ComposedModulePlanService::extractStrategyIds($plan);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public static function planDigest(array $plan): string
    {
        return ComposedModulePlanService::planDigest($plan);
    }
}
```

> Laravel auto-resolves the new constructor deps (`ComposedModulePlanService`, `TaxStrategySource`) — both are concrete classes with auto-wireable deps (`StrategyPlanComposer`, `TaxStrategyCalculator`, `HouseholdFinancialContext`). No `AppServiceProvider` change is required for Task 1.5.

- [ ] **Step 2: Run the FULL tax + parity + golden guard set — expect GREEN**

Run:
```bash
./vendor/bin/pest \
  tests/Unit/Services/Coordination/ \
  tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerParityTest.php \
  tests/Feature/AI/ToolSchemaGoldenMasterTest.php \
  tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
```
Expected: PASS, all. This is the byte-identity gate for the whole refactor. **If anything is red, do NOT proceed to Phase 2** — the tax contract is broken. Diagnose via the parity test's 3-way equality (skill == tool == direct).

- [ ] **Step 3: Run the full AI + agents suite as a regression sweep**

Run: `./vendor/bin/pest tests/Unit/Agents tests/Feature/AI tests/Unit/Services/AI`
Expected: PASS (no behavioural drift in the Fyn recommendation paths).

- [ ] **Step 4: Commit**

```bash
./vendor/bin/pint app/Services/Coordination/ComposedTaxPlanService.php
git add app/Services/Coordination/ComposedTaxPlanService.php
git commit -m "refactor(composer): ComposedTaxPlanService delegates to ComposedModulePlanService + TaxStrategySource (byte-identical)"
```

**Phase 1 exit criteria:** tax composed plan byte-identical (`planDigest` parity green); `ComposedModulePlanService` proven module-agnostic via fake source; full AI/agents suite green. **Open a `dev` PR for Phase 1 alone** (`feat: generalise composed-plan machinery — tax byte-identical`) so the highest-risk change is reviewed + (optionally) deployed independently before any module is added.

---

## PHASE 2 — Per-module sources + adapters + catalogue metadata

**Order (lowest adapter risk first):** Retirement → Savings → Investment → Protection → Estate. Goals is **not** a per-module composed-plan source (it has no `*_action_definitions` table — Appendix A §2.7); goals enter the cross-module layer as *demands* in Phase 4.

Each module repeats the same five-task shape (2.x.1 … 2.x.5). The migration (2.0) and the per-module availability provider (2.0b) come first.

### Task 2.0 — Migration: `strategy_type` on the five non-tax action_definitions tables

**Files:**
- Create: `database/migrations/2026_06_16_000001_add_strategy_type_to_non_tax_action_definitions.php`
- Test: `tests/Feature/Database/StrategyTypeColumnTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('adds a nullable strategy_type column to every non-tax action_definitions table', function (): void {
    foreach ([
        'retirement_action_definitions',
        'savings_action_definitions',
        'investment_action_definitions',
        'protection_action_definitions',
        'estate_action_definitions',
    ] as $table) {
        expect(Schema::hasColumn($table, 'strategy_type'))->toBeTrue("$table missing strategy_type");
    }
});
```

- [ ] **Step 2: Run — expect FAIL**

Run: `./vendor/bin/pest tests/Feature/Database/StrategyTypeColumnTest.php`
Expected: FAIL (columns absent).

- [ ] **Step 3: Write the migration** (mirror tax — Appendix A §2.8; the three metadata cols already exist on all six tables)

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'retirement_action_definitions',
        'savings_action_definitions',
        'investment_action_definitions',
        'protection_action_definitions',
        'estate_action_definitions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->string('strategy_type', 64)->nullable()->unique()->after('source');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t): void {
                $t->dropUnique([$table.'_strategy_type_unique']);
                $t->dropColumn('strategy_type');
            });
        }
    }
};
```

- [ ] **Step 4: Migrate + run the test — expect PASS**

Run: `php artisan migrate --force && ./vendor/bin/pest tests/Feature/Database/StrategyTypeColumnTest.php`
Expected: migration runs; test PASSES.
> Per CLAUDE.md never use `migrate:fresh`. After migrating, reseed: `php artisan db:seed`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_16_000001_add_strategy_type_to_non_tax_action_definitions.php tests/Feature/Database/StrategyTypeColumnTest.php
git commit -m "feat(composer): add strategy_type to non-tax action_definitions tables"
```

### Task 2.0b — `ModuleAvailabilityProvider` for non-tax vocabulary

`HouseholdFinancialContext::availability()` (Appendix A §3.11) returns 13 tax-centric keys. Non-tax strategies need keys like `life_cover_in_force`, `emergency_fund_months`, `will_in_place`, `lpa_registered`, `portfolio_allocation`, `pension_on_track`. Add a dedicated provider rather than bloating the tax-named context.

**Files:**
- Create: `app/Services/Coordination/PlanSources/ModuleAvailabilityProvider.php`
- Test: `tests/Unit/Services/Coordination/PlanSources/ModuleAvailabilityProviderTest.php`

- [ ] **Step 1–5 (TDD):** Build a provider with `forModule(string $module, User $user): array<string,bool>`. Each module's keys are computed from existing models:

| Module | Availability key | True when |
|--------|------------------|-----------|
| retirement | `dc_pension_exists` | user has a `DCPension` |
| retirement | `pension_input_history` | `PensionStore::pensionInputHistory()` non-empty |
| retirement | `target_retirement_age` | `users.target_retirement_age` set |
| savings | `emergency_fund_target_known` | monthly expenditure resolvable (`ResolvesExpenditure` ≠ 'none') |
| savings | `savings_balances` | any `SavingsAccount` owned |
| investment | `gia_holdings` | non-ISA `InvestmentAccount` exists |
| investment | `risk_profile_set` | `users.risk_tolerance` (or RiskProfile) set |
| protection | `dependants_known` | any `FamilyMember` dependants recorded |
| protection | `life_cover_in_force` | any `LifeInsurancePolicy` |
| protection | `income_known` | `ResolvesIncome` gross > 0 |
| estate | `will_in_place` | `Will` record exists |
| estate | `lpa_registered` | `PowerOfAttorney` record exists |
| estate | `estate_value_known` | net-worth computable |

Write the test asserting two representative keys flip true/false for a seeded vs empty user. Implement by composing existing models/traits (cite Appendix A §1.4 for the traits). Commit `feat(composer): ModuleAvailabilityProvider for non-tax required_data vocabulary`.

> **Confirm key names against the seeded `required_data` (Task 2.x.4) before finishing** — the availability key set and the catalogue `required_data` must be the same vocabulary, or every strategy locks. Add a guard test (Task 6) asserting every seeded non-tax `required_data` key is produced by `ModuleAvailabilityProvider::forModule()`.

### Tasks 2.1–2.5 — Per module (Retirement, Savings, Investment, Protection, Estate)

For each module **M** with rec generator **G** (see table), repeat:

| M | Generator G (Appendix A §1) | Rec shape note | Effort |
|---|------------------------------|----------------|--------|
| Retirement | `RetirementActionDefinitionService::evaluateAgentActions(array): array{recommendations,...}` | int priority; `impact` High/Med/Low; no `type` | Medium |
| Savings | `SavingsActionDefinitionService::evaluateAgentActions(...)` | `definition_key`; `estimated_impact` | Medium |
| Investment | `InvestmentActionDefinitionService::evaluateAgentActions(...)` | `definition_key`; `estimated_impact` | Medium |
| Protection | `RecommendationEngine::generateRecommendations(array $gaps, ProtectionProfile): array` (the live path used by `ProtectionAgent:124`) | **`action`/`rationale`** not `title`/`description`; `estimated_cost`; priority 1–5 | Higher |
| Estate | `EstateActionDefinitionService::evaluateActions(User): array{recommendations,...}` (catalogue path; prefer over the hardcoded `EstateAgent::generateRecommendations`) | `definition_key`; `estimated_impact` | Higher |

#### Task 2.M.1 — Adapter: generator output → `StrategyRecommendation[]`

**Files:** Create `app/Services/Coordination/PlanSources/Adapters/{M}RecommendationAdapter.php` + test.

The adapter is a pure mapper. Field mapping (use the documented shapes in Appendix A §1):

```
StrategyRecommendation field   ← generator field
  type                         ← a STABLE slug. For catalogue modules use $rec['definition_key'];
                                 for Protection (no definition_key) derive a fixed slug from category
                                 (e.g. 'Life Insurance' → 'protection_life_cover_gap'). The slug MUST
                                 equal the strategy_type seeded in Task 2.M.4.
  category                     ← map the module's category string to a StrategyCategory case
                                 (income_band|allowance|household|lifecycle|warning). Most non-tax
                                 actions are 'lifecycle'; gaps/risks are 'warning'.
  priority                     ← map to StrategyPriority (high|medium|low). Retirement/Savings/
                                 Investment/Estate carry $rec['impact'] (High/Med/Low → lowercased).
                                 Protection priority int 1–5 → 1-2:high, 3:medium, 4-5:low.
  title                        ← $rec['title'] (Protection: $rec['action'])
  description                  ← $rec['description'] (Protection: $rec['rationale'])
  estimatedAnnualTaxSaved      ← null for non-tax modules (these are not tax savings)
  requiresAdvice               ← true when the action implies a product purchase needing advice
                                 (Protection cover, Estate trust/will, pension transfer); else false
  requiredMonthlyCost          ← $rec['available_monthly_headroom'] / regular contribution where the
                                 action is "contribute £X/mo" (Retirement/Savings); else null
  requiredLumpSum              ← Protection $rec['estimated_cost'] is a PREMIUM not a lump sum → leave
                                 requiredLumpSum null, carry premium in extra['estimated_premium'].
                                 Bed-and-ISA / lump investments → requiredLumpSum.
  extra                        ← decision_trace, account_id, account_name, scope, definition_key,
                                 estimated_premium, impact_parameters — everything else
```

Test (TDD): feed a hand-built generator array (copy a real shape from Appendix A §1.M), assert the adapter returns a `StrategyRecommendation` with the expected `type`, mapped `category`/`priority`, and cost field. Commit `feat(composer): {M}RecommendationAdapter`.

#### Task 2.M.2 — `{M}StrategySource implements ModuleStrategySource`

**Files:** Create `app/Services/Coordination/PlanSources/{M}StrategySource.php` + test.

```
moduleKey()       → '{m}'
recommendations() → call generator G (build its required analysis input the way the module's
                    Agent does — see {M}Agent::generateRecommendations / ::analyze for the call
                    pattern), then map each via {M}RecommendationAdapter.
metadataRows()    → {M}ActionDefinition::where('source','strategy')->where('is_enabled',true)->get()
availability()    → app(ModuleAvailabilityProvider::class)->forModule('{m}', $user)
```

Test (TDD): seed one `source='strategy'` row + a user; assert `forSource()` via `ComposedModulePlanService` returns the expected item or locked entry. Commit `feat(composer): {M}StrategySource`.

#### Task 2.M.3 — Seed `source='strategy'` catalogue rows for module M

**Files:** Modify `database/seeders/{M}ActionDefinitionSeeder.php` (add a `source='strategy'` block mirroring `TaxActionDefinitionSeeder` — Appendix A §2.10) + test asserting the rows exist with `strategy_type`, `claim_tier`, `required_data`, `sequencing`.

Author 3–6 strategy rows per module keyed by the same slugs the adapter emits as `type`. Example (retirement):

```php
[
    'strategy_type' => 'increase_pension_contribution',
    'key'           => 'strategy_increase_pension_contribution',
    'source'        => 'strategy',
    'category'      => 'lifecycle',
    'priority'      => 'high',
    'claim_tier'    => 'mechanical',
    'required_data' => ['dc_pension_exists'],
    'sequencing'    => ['do_before' => [], 'conflicts_with' => []],
    'title_template'       => 'increase_pension_contribution',
    'description_template' => 'Computed by the retirement plan source.',
    'scope'         => 'portfolio',
    'is_enabled'    => true,
    'sort_order'    => 100,
    'trigger_config'=> [],
],
```

Cross-module sequencing is allowed: e.g. retirement `pension_aa_carry_forward` may list `'conflicts_with' => ['isa_topup_vs_psa']` referencing a **tax** strategy_type — the composer resolves it in Phase 4. Commit `feat(composer): seed {M} source=strategy catalogue rows`.

#### Task 2.M.4 — `Composed{M}PlanTest` (mirror the tax test)

**Files:** Create `tests/Unit/Services/Coordination/Composed{M}PlanTest.php`. Seed config + the module's strategy rows + a user with/without the required data; assert: (a) a fired strategy appears in `items` ordered by saving/impact; (b) a strategy with unmet `required_data` appears in `locked` with the correct `missing` keys; (c) `planDigest` is stable across two calls. Commit `test(composer): Composed{M}PlanTest`.

**Phase 2 exit criteria:** five module sources produce composed plans; every seeded `required_data` key is in `ModuleAvailabilityProvider`'s vocabulary; full suite green. Commit + `dev` PR `feat: per-module composed plan sources + catalogue metadata`.

---

## PHASE 3 — Catalogue triple (semantic + procedural + DB)

DB metadata landed in Phase 2. Phase 3 adds the **semantic narrative** + **procedural pointer** per module, and registers the handlers.

### Task 3.1 — Per-module `FetchHandler` (generalise `RecommendationHandler`)

**Decision:** one parameterised handler per module (keeps `id()` stable + matches the closed-whitelist registry). Create `app/Services/AI/Pointers/Handlers/ModulePlanHandler.php` as an abstract base, with thin subclasses `RetirementPlanHandler`, `SavingsPlanHandler`, `InvestmentPlanHandler`, `ProtectionPlanHandler`, `EstatePlanHandler`. Each returns a `FetchResult` whose value is the same digest-preimage encoding as `RecommendationHandler` (Appendix A §2.4) but keyed by `['composed_{m}_plan' => $plan]`, with `extra` strategy-id provenance via `ComposedModulePlanService::extractStrategyIds`.

> **Do not reuse** the `'composed_tax_plan'` digest key for non-tax modules — that key is the tax parity namespace. Use `composed_{m}_plan` per module.

**Files (per module):** Create the handler + a `tests/Unit/Services/AI/Pointers/Handlers/{M}PlanHandlerTest.php` asserting `id()` and that `fetch()` returns a `FetchResult` with the module plan + `strategy_ids` extra. Then register all five in `AppServiceProvider` (Appendix A §2.5 — the `FetchHandlerRegistry` singleton iterable). Add a `tests/Unit/Services/AI/Pointers/PointerBindingsTest.php` case (or extend it) asserting each new handler id resolves. Commit per module.

### Task 3.2 — Pointer `.md` defs (code-registered handler FIRST)

**Files (per module):** Create `fyn-memory/procedural/pointers/{m}-plan.md` mirroring `recommendations.md` (Appendix A §2.6). `mode: tool` (or `both` where a module page benefits from prefetch — retirement/savings/investment pages do; protection/estate `tool`). `handler` = the registered id from 3.1. **Then** run `php artisan fyn:pointers:reindex` — expect "Pointer corpus validated: N pointers loaded." (non-zero exit if a handler is unregistered — that means 3.1 wasn't merged first). Add the planner routing note to `recommendation-routing.md` so planning turns can choose a module plan. Commit `feat(composer): {m}-plan pointer def`.

### Task 3.3 — house_view narratives per non-tax strategy

**Files:** Create `fyn-memory/semantic/house_view/{strategy-slug}.md` for each non-tax strategy seeded in Phase 2 (frontmatter format in Appendix A §2.5 of the surfaces map: `fact_id: hv-{slug}`, `category: house_view`, `title`, `version: 1`, `valid_to: null`). Body = source-less narrative (what it is, when it applies, sequence position, claim tier/voicing). **No `£` figures.** Run `php artisan fyn:semantic:reindex` — expect clean validation + the index count to rise. Commit `feat(composer): house_view narratives for {m} strategies`.

### Task 3.4 — Corpus guard tests + golden recapture

- [ ] Run `./vendor/bin/pest tests/Unit/Services/AI/SemanticCorpusContentTest.php tests/Unit/Services/AI/PointerCorpusContentTest.php` — expect PASS (no `£` figures; registry loads).
- [ ] The new `fetch_{m}_plan` pointer tools change the live pointer-tool set. Recapture the tool-schema golden masters: `CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php` and `CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php`, then re-run both without the env flag to confirm green. Review the fixture diff to confirm only the new pointer tool names were added. Commit `test(composer): recapture tool-schema golden masters for module-plan pointers`.

**Phase 3 exit:** every module reachable as a Fyn tool; corpus reindex clean; golden masters green.

---

## PHASE 4 — `CompositePlanService` (cross-module)

### Task 4.1 — Affordability annotation DTO + ranker

**Files:** Create `app/Services/Coordination/CompositePlanService.php` + `tests/Unit/Services/Coordination/CompositePlanServiceTest.php`.

`compose(User $user): array` does:
1. Gather every module's composed plan: `foreach (sources) $plans[$m] = $composedModulePlanService->forSource($source, $user)`. (Inject all six `ModuleStrategySource`s, or a registry of them.)
2. Flatten `items` across modules into one list (carry `module` on each item).
3. **Affordability rank + annotate (D4):** pull the monthly surplus `app(CashFlowCoordinator::class)->calculateAvailableSurplus($user->id)` (Appendix A §1.1 — this is already net of committed contributions, the correct base). Rank items by impact (`estimated_annual_tax_saved` desc, then high→low priority). Walk a running surplus, subtracting each item's `required_monthly_cost` (null cost = informational, consumes nothing). Tag each item:
   - `fits` — full `required_monthly_cost` ≤ remaining surplus
   - `partially_fits` — 0 < remaining surplus < `required_monthly_cost`
   - `beyond_current_surplus` — remaining surplus ≤ 0
   - plus `surplus_consumed_to_here` (running total). **Never drop an item.**
4. **Cross-module sequencing/conflicts:** pass the merged metadata (all modules' `sequencing`, now allowed to reference other modules' strategy types) through `StrategyPlanComposer` once more, OR apply the same `do_before`/`conflicts_with` pass at the composite level. Reuse the pure composer; do not re-implement.
5. Return `{items: [...annotated...], by_module: {...}, locked: [...all modules...], available_monthly_surplus: float}`.

Test (TDD): three items with costs £200/£300/£400 against a £400 surplus → first `fits`, second `partially_fits`, third `beyond_current_surplus`; surplus_consumed_to_here monotonic; nothing dropped. Commit `feat(composer): CompositePlanService affordability rank+annotate`.

### Task 4.2 — Goals as demands

**Files:** Modify `CompositePlanService` + test.

Each active goal's `monthly_contribution` (Appendix A §2.5 Goal model) competes for surplus. Use `GoalAffordabilityService::analyzeAllGoals($user)` (§1.3) for the committed total + per-goal share, and group module strategies under a goal via `Goal.assigned_module` + the `blocks` graph (`dependsOn()` pivot, §2.5). Annotate goal-linked items and add a `goals` block to the composite output. Test: a goal with `monthly_contribution` reduces the surplus available to ranked strategies. Commit `feat(composer): goals as cross-module demands`.

### Task 4.3 — Life events as time-phased modifiers

**Files:** Modify `CompositePlanService` + test.

Near-term life events shift available capital/surplus in their window. Use `LifeEventCashFlowService::buildCashFlowMap($userId, 'all', $years)` (§1.11) + `LifeEventIntegrationService::EVENT_MODULE_MAP` (§1.10) to (a) flag a near-term income event as a lump-sum that can unlock a `beyond_current_surplus` lump-sum strategy, and (b) reduce surplus in an expense-event window. Test: a confirmed near-term expense event downgrades an otherwise-`fits` item. Commit `feat(composer): life events as time-phased plan modifiers`.

### Task 4.4 — Episodic recall shapes ranking

**Files:** Modify `CompositePlanService` + test.

Pull `app(FynMemoryStore::class)->recallContext($user->id)` (existing — Appendix A / spec §4 Layer D) and use recalled decisions to adjust ranking/voicing: de-rank a repeatedly-declined strategy; honour a stated module preference in tie-breaks. **Capture+recall only — no promotion (Phase 6 territory, deferred).** Test: inject a recalled "declined increase_pension_contribution" episode → that item sorts below an equal-impact peer. Commit `feat(composer): episodic recall shapes composite ranking`.

### Task 4.5 — `CrossModulePlanHandler` (tool) + pointer

**Files:** Create `app/Services/AI/Pointers/Handlers/CrossModulePlanHandler.php` (id `cross-module-plan`, wraps `CompositePlanService`, digest key `composite_plan`) + register in `AppServiceProvider` + create `fyn-memory/procedural/pointers/cross-module-plan.md` (`mode: tool`, triggers "what should I do", "can I afford", "overall plan") + handler test + reindex + golden recapture (as Task 3.4). Commit `feat(composer): CrossModulePlanHandler (tool) + pointer`.

**Phase 4 exit:** the cross-module composite plan is a Fyn tool; affordability/goals/life-events/episodic all unit-tested; nothing silently dropped.

---

## PHASE 5 — Surfaces (web **and** `/m`)

### Task 5.1 — `RecommendationsAggregatorService` wire-through

**Files:** Modify `app/Services/Coordination/RecommendationsAggregatorService.php` (Appendix A §1.1 — tax plugs in at ~line 192). Route the five non-tax modules through `ComposedModulePlanService->forSource(...)` the same way tax goes through `ComposedTaxPlanService::forUser()` (gate each via `PrerequisiteGateService::enforce()` as the existing modules are). This feeds the dashboard, `NextActionsService` (`/m`), and `/holistic` automatically. Test: `aggregateRecommendations()` now includes composed items from a seeded non-tax module with `claim_tier`/`sequence_position`. Commit `feat(composer): aggregate non-tax composed plans`.

> Keep the existing per-agent recommendation rows for modules until their composed source is proven, to avoid a dashboard regression — A/B by gating the composed path behind a small feature check if needed, but default ON once Phase 2 tests are green.

### Task 5.2 — `/holistic-plan` composite view (web)

**Files:** Modify `app/Http/Controllers/Api/HolisticPlanningController.php` + `resources/js/views/HolisticPlan.vue` + the `components/Plans/Holistic/*` components (Appendix A §1.2). Add a composite-plan section that renders the affordability-ranked, sequenced, goal/life-event-aware list with `fits`/`partially_fits`/`beyond_current_surplus` annotations as **descriptive text** (Rule #12 — no scores; show £ surplus and the running consumption, not a rating). No decorative icons (Rule #15). Commit `feat(composer): holistic-plan composite view (web)`.

### Task 5.3 — `/m` holistic surface (NET-NEW — parity gap)

**Files:** Create the mobile holistic screen under `resources/mobile/views/` + route under `/m/app/*` (Appendix A §1.2 flagged: **no `/m` holistic counterpart exists today** — this is the largest net-new surface). It rides the shared backend (`GET /api/holistic/...`), wraps in `MobileLayout` (Rule #13), and reuses the gamification-free composite rendering. Verify on csjones (built bundle, not HMR). Commit `feat(composer): /m holistic composite surface`.

> **Scope flag for CSJ:** this `/m` screen is the single biggest net-new piece. The spec commits to "web + /m" and Rule #19 makes it default-in-scope, but it can be split to an immediate follow-up PR if CSJ wants Phases 1–4 + web surfaced first. Surface this in the execution handoff.

### Task 5.4 — Fyn handlers reach every module + composite on web and `/m`

No surface-specific work — `/m` and web share `POST /api/ai-chat/conversations/{id}/messages` (Appendix A / CLAUDE.md). Verify the module + composite plan tools fire from both surfaces in Phase 6 E2E. Commit any wiring fixes.

**Phase 5 exit:** composed plans surface on the dashboard, `/holistic-plan` (web + `/m`), and Fyn (web + `/m`).

---

## PHASE 6 — Tests, golden masters, browser E2E

### Task 6.1 — Vocabulary guard test
Assert every seeded non-tax `required_data` key is produced by `ModuleAvailabilityProvider::forModule()` for some user state (so no strategy is permanently locked by a typo). Create `tests/Unit/Services/Coordination/AvailabilityVocabularyTest.php`.

### Task 6.2 — Full suite
Run `./vendor/bin/pest`. Expected: green (target: prior 5030 + the new cases, 0 failures). Fix any regression before proceeding.

### Task 6.3 — Golden masters
Confirm `ToolSchemaGoldenMasterTest` + `XaiToolSchemaGoldenMasterTest` green (recaptured in 3.4/4.5). Confirm `SemanticCorpusContentTest` + `PointerCorpusContentTest` green.

### Task 6.4 — Browser E2E (Rule #14 — loop until green; web AND `/m`)
On local dev (`john@example.com` / `password`, fetch the MFA code from the DB per CLAUDE.md), and again on csjones for `/m`:
1. Ask Fyn (advice) "what should I do about my pension?" → a **retirement** composed plan returns (ordered, locked unlock prompts).
2. Ask "what's my overall plan / can I afford it?" → the **cross-module composite** returns with affordability annotations; nothing dropped.
3. Verify a locked strategy surfaces its single unlock question.
4. Verify a captured preference (decline a strategy) changes ranking on a later turn (episodic recall).
5. Verify `/holistic-plan` composite view renders on web and the new `/m` surface.
Record evidence (DB rows, SSE shape, UI). **No completion report until all five are green on both surfaces.**

### Task 6.5 — tech-debt-session + PR
Run the `tech-debt-session` skill over the changed files. Open the final `dev` PR. Per `feedback_main_via_dev_only`, prod is CSJ's call.

---

## Self-Review (run before handing off — author checklist)

**Spec coverage:** §4 Layers A–E → Phases 1–5 (A=1+2, B=3.1/3.2/4.5, C=2+3.3, D=4.4, E=GroundGate read-side, unchanged). §5 change table → Tasks 1.1/1.2/1.3/2.0/2.x/3/4/5. §6 surfaces → Phase 5. §10 success criteria 1–6 → Phase 6 E2E + golden + parity. **Gap check:** Goals has no per-module source (Appendix A §2.7) — correctly handled as a Phase 4 demand, matching spec D9. ✓

**Placeholder scan:** Phase 1 is fully coded. Phases 2–5 use repeated per-module task shapes with concrete field-mapping tables + exact paths + named tests; the only deliberately-parameterised parts are the per-module adapter bodies, which carry their exact input shape (Appendix A §1) and output mapping table inline — grounded, not "TBD". ✓

**Type consistency:** `ComposedModulePlanService::forSource()`, `ModuleStrategySource::{moduleKey,recommendations,metadataRows,availability}`, `StrategyRecommendation::{requiredMonthlyCost,requiredLumpSum}`, digest key `composed_tax_plan` (tax) / `composed_{m}_plan` (module) / `composite_plan` (cross) — names consistent across tasks. ✓

---

## Appendix A — Substrate map (verified on `dev`, 2026-06-15)

### §1 Composition core
- **`StrategyPlanComposer`** `app/Services/Coordination/StrategyPlanComposer.php` — `compose(array $recommendations, array $metadata, array $lockedStrategies): array{items, combined_annual_saving, locked}`. **Pure** (no DB/container). Only tax coupling: reads `IsaAllowanceAllocator::EXCLUDED_FLAG` / `::NOTE_FIELD` constants from `$rec->extra[]`. Steps: savings-desc sort → `do_before` bubble-sort → undirected `conflicts_with` resolution (higher-saving wins) → item build (`array_merge($rec->toArray(), [claim_tier, sequence_position, conflict_note])`) → combined total.
- **`StrategyRecommendation`** `app/DataTransferObjects/StrategyRecommendation.php` — `final`. Props: `type, category(string from enum), priority(string from enum), title, description, ?estimatedAnnualTaxSaved, requiresAdvice, extra[]`. `fromArray(StrategyCategory|string $category, array $arr)`, `toArray()` (base 7 keys + extra merged). **No cost field yet** (added in Task 1.1). Enums: `StrategyCategory` = income_band|allowance|household|lifecycle|warning; `StrategyPriority` = high|medium|low.
- **`ComposedTaxPlanService`** `app/Services/Coordination/ComposedTaxPlanService.php` — `forUser(User): array`; statics `extractStrategyIds(array): array{surfaced,locked}`, `planDigest(array): string` (16-hex sha256 of `json_encode(['composed_tax_plan'=>$plan], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)`). Callers of statics: `CoordinatingAgent:1888,1900`, `RecommendationHandler:29`, derivation + parity tests.
- **Affordability:** `CashFlowCoordinator::calculateAvailableSurplus(int $userId): float` (net of committed contributions; `calculateCommittedContributions` is **private**), `optimizeContributionAllocation(float, array): array` (priority waterfall emergency_fund→protection→pension→investment→estate→goals, urgency≥80 jumps). `DisposableIncomeAccessor::getMonthlyForUser(User): float`. `GoalAffordabilityService::analyzeAffordability(Goal,User)`, `analyzeAllGoals(User)`, `analyzeAffordabilityWithLifeEvents(Goal,User)` (7 tiers: unaffordable/completed/comfortable/moderate/challenging/stretch/overcommitted). Traits `ResolvesIncome` (`resolveGrossAnnualIncome`/`resolveNetAnnualIncome`), `ResolvesExpenditure` (`resolveMonthlyExpenditure → ['amount','source','label']`).

### §2 Per-module generators + catalogue
- Retirement `RetirementActionDefinitionService::evaluateAgentActions(array): array{recommendations,total_count,high_priority_count}` — rec keys: priority(int), category, title, description, action, impact(High/Med/Low), scope, account_id?, decision_trace, available_monthly_headroom?, potential_saving?. No `type`, no `estimated_annual_tax_saved`.
- Savings `SavingsActionDefinitionService::evaluateAgentActions(...)` / Investment `InvestmentActionDefinitionService::evaluateAgentActions(...)` — rec keys incl. `definition_key`, `estimated_impact`, `decision_trace`.
- Protection **live path** `RecommendationEngine::generateRecommendations(array $gaps, ProtectionProfile): array` (called by `ProtectionAgent:124`) — keys **`action`/`rationale`** (not title/description), `priority`(1–5), `category`, `impact`, `estimated_cost`. (`ProtectionActionDefinitionService` exists but is not wired into the agent.)
- Estate `EstateActionDefinitionService::evaluateActions(User): array{recommendations,...}` (catalogue path; prefer over hardcoded `EstateAgent::generateRecommendations`).
- Goals: **no** `goals_action_definitions` table, no `GoalsActionDefinitionService` — recs hardcoded in `GoalsAgent::generateRecommendations`. → Goals is a Phase-4 demand, not a per-module source.
- Tables: `{tax,retirement,savings,investment,protection,estate}_action_definitions`, models `App\Models\{Module}ActionDefinition`. Migration `2026_06_10_100001` added `claim_tier`+`required_data`+`sequencing` to **all six**; `strategy_type` to **tax only**. Non-tax seeders seed only `source='agent'`/`'goal'`; metadata cols at defaults.
- `HouseholdFinancialContext::availability(User): array<string,bool>` — 13 tax keys: annual_income, charitable_giving, date_of_birth, dividend_income, employment_status, gia_holdings, isa_subscriptions_ytd, marital_status, pension_contributions, pension_input_history, savings_balances, spouse_income, workplace_pension.

### §3 CoALA wiring
- `RecommendationHandler` `app/Services/AI/Pointers/Handlers/RecommendationHandler.php` — `id()='recommendations'`; `fetch()` → `FetchResult::make(json_encode(['composed_tax_plan'=>$plan], …), 'recommendation engine', today, ['strategy_ids'=>…,'locked_strategy_ids'=>…])`.
- `FetchHandler` (`id():string`, `fetch(FetchContext):FetchResult`); `FetchHandlerRegistry` (closed whitelist, fail-closed `get()`); registered in `AppServiceProvider:84-90` (Tax/UserFinancial/Recommendation handlers). `FetchDispatcher::run(Pointer,FetchContext,?AiMessage):?FetchResult` (exception→null). `FetchResult::make(value,label,version,extra=[])` derives 16-hex digest.
- `PointerRegistry` parses `fyn-memory/procedural/pointers/*.md` (frontmatter: pointer_id, topic, triggers, mode∈{prefetch,tool,both}, handler, source_label, version). **Fail-closed**: unregistered handler → throws. `matchPrefetch(query)`, `toolPointers()`.
- `FynContextAssembler::build(FynTurnContext,?callable):string` — prefetch matching + `<live_data>` inject (lines 138–154); `SemanticRetriever::retrieveForUser($userId,$msg)` at line 114.
- `FynLoop::run(SessionMode,…)` (planner advice turn) / `stream(...)` (raw turn). `GroundGate::blocksWriteSurface(string $surface, ?string $persona): bool` (only `'advice'` persona gated against `AdviceFyn::WRITE_TOOLS`).
- Pointer how-to: `fyn-memory/procedural/pointers/README.md` (register handler in code FIRST, then `.md`, then `fyn:pointers:reindex`). `_TEMPLATE.md` to start.

### §4 Memory corpus + surfaces
- `fyn-memory/semantic/house_view/` — 20 tax `.md`. Format: frontmatter `fact_id:hv-…, category:house_view, title, version:int, valid_to:null` (no `valid_from` needed for house_view); body markdown, **no `£`**. No `.xai.md` for semantic. `SemanticRetriever::retrieve(query,Carbon,?categories)` / `retrieveForUser(userId,msg)`; sparse scoring (tokenise `/[a-z0-9]{3,}/` minus stopwords). `SemanticCorpusLoader` parses `fyn.memory.semantic_path = base_path('fyn-memory/semantic')`. `fyn:semantic:reindex` → writes `storage/app/memory/semantic/index.json`. `fyn:pointers:reindex` → validates only (no index file).
- Provider duality: `config('services.ai_provider')` (`AI_PROVIDER`, default anthropic; runtime cache `ai_provider` = `'xai'`). `tool_schema/*.xai.md` variants routed by `provider:` frontmatter (not filename). Golden masters: `tests/Feature/AI/ToolSchemaGoldenMasterTest.php` (`CAPTURE_TOOL_SCHEMA_GOLDEN=1`) + `XaiToolSchemaGoldenMasterTest.php` (`CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1`). Corpus guards: `SemanticCorpusContentTest`, `PointerCorpusContentTest`, `PointerBindingsTest`.
- Surfaces: `RecommendationsAggregatorService::aggregateRecommendations(int $userId): array` (tax plugs in ~line 192; consumed by `RecommendationsController:41` web + `NextActionsService:182` `/m`). `/holistic-plan` → `HolisticPlanningController` (`analyze`/`plan`/`recommendations`/`cashFlowAnalysis`) + `resources/js/views/HolisticPlan.vue` + `components/Plans/Holistic/*`. **No `/m` holistic surface exists.** `ModuleSummaryController` (`/m` module summaries) uses raw per-agent `analyze()`, not the aggregator; `removeScores()` strips only financial-quality scores (never the gamification fields — Rule #12 carve-out).
- Goals/life-events: `Goal` (`assigned_module`, `module_override`, `monthly_contribution`, `status`, `dependsOn()`/`dependedOnBy()` via `goal_dependencies` pivot `dependency_type∈{blocks,funds,prerequisite}`, `savingsAccounts()` pivot, `linked_investment_account_id`). `GoalAssignmentService::determineModule(array): string` (savings/investment/property/retirement only). `LifeEvent` (`event_type` 19 values, `impact_type∈{income,expense}`, `certainty∈{confirmed,likely,possible,speculative}`, `status`). `LifeEventAllocation` (no `goal_id`). `LifeEventIntegrationService::EVENT_MODULE_MAP` (event→primary+secondary modules). `LifeEventCashFlowService::buildCashFlowMap(int,string $module,int $years): array<int,float>`.

---

## Execution notes
- Per `feedback_never_switch_branches`: work sequentially in the main dir on `cross-module-plan-composer`.
- After any migration, `php artisan db:seed` (never `migrate:fresh`).
- Phase 1 is a standalone shippable PR; land + (optionally) deploy it before Phase 2.
- Browser-verify on web (local) AND `/m` (csjones built bundle) per Rule #19.
