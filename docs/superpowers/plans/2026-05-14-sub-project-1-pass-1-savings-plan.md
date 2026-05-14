---
title: Sub-Project 1, Pass 1 — Savings Canonical Store Implementation Plan
date: 2026-05-14
spec: docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
sub_project: 1 of 6 (Fynla major-overhaul series)
pass: 1 of 14 (Savings — bank/cash accounts)
branch: claude/cranky-lewin-6bc99c
status: ready for execution
---

# Savings Canonical Store Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Do not start implementation in the same session that produced this plan** — split across sessions for cache hygiene and to let CSJ review.

**Goal:** Build the `SavingsStore` service facade so every read and write of `SavingsAccount` goes through a single canonical API. Lock the boundary with a Pest architecture test that hard-fails in CI on any direct model mutation outside the store.

**Architecture:** Approach A (service facade over Eloquent) per spec §4. One store class, one shared `IngestSource` enum, one per-entity normaliser. Three ingest paths (HTTP form, Fyn AI tool, document upload) converge at `SavingsStore::create()` with identical canonical-array shape. Derived columns (`balance_gbp`, `annual_interest_projected_gbp`, `isa_allowance_used_pct`) are materialised on the table with `*_calculated_at` timestamps; consumers read columns, never compute. A `savings_account_value_snapshots` table preserves history per the per-column `SnapshotPolicy`. The arch test ships hard-fail from PR 1 with an explicit allowlist of pre-existing direct-write sites that subsequent PRs progressively remove.

**Tech Stack:** Laravel 10 · PHP 8.2 · Pest 2.36 · Eloquent · Mockery · MySQL 8 · existing `Auditable` / `HasJointOwnership` / `SoftDeletes` traits.

---

## File Structure

### New files (created during this pass)

| Path | Responsibility |
|------|----------------|
| `app/Services/Stores/IngestSource.php` | Shared enum — `FORM`, `FYN_AI`, `UPLOAD`, `SEEDER`, `ADMIN`. Required parameter on every store write. Used across all 13+ entities in sub-project 1, but lives here from PR 1 of pass 1. |
| `app/Services/Stores/SavingsStore.php` | The store facade. Public read/write methods. Owns all `SavingsAccount` mutation logic. |
| `app/Services/Stores/Normalisers/SavingsAccountNormaliser.php` | Maps form / fyn / upload arrays to the canonical input shape. |
| `app/Services/Stores/Exceptions/StoreValidationException.php` | Shared exception thrown by store-internal validation. Reused for every entity. |
| `app/Services/Stores/Exceptions/TierLimitExceededException.php` | Shared exception thrown by tier-cap gate. Reused for every entity. |
| `app/Services/Stores/TierGate.php` | Interface (§13). Per-tier `canCreate` / `softLimit` / `hardLimit` lookup. Sub-project 1 ships an interface + permissive default impl; sub-project 2 supplies the real impl. |
| `app/Services/Stores/PermissiveTierGate.php` | Default impl — always returns true. Bound to `TierGate` interface in `AppServiceProvider` until sub-project 2 replaces it. |
| `app/Services/Stores/Snapshots/SnapshotPolicy.php` | Value object (§10.3). `triggerPredicate`, `retentionDays`, `surfacingWindowDays`, `maxRowsHardCap`, `recalcCadence`. |
| `app/Services/Stores/Snapshots/SnapshotPolicies.php` | Static factory returning per-entity policies. PR 6 of pass 1 adds the Savings entries. |
| `app/Events/Savings/SavingsAccountCreated.php` | Emitted by store after create. Read-only event object. |
| `app/Events/Savings/SavingsAccountUpdated.php` | Emitted by store after update. Carries `$changes` diff. |
| `app/Events/Savings/SavingsAccountDeleted.php` | Emitted by store after delete. |
| `app/Events/Savings/SavingsAccountRestored.php` | Emitted by store after restore. |
| `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` | Pest `arch()` test. Hard-fails CI on any non-allowlisted direct mutation of `SavingsAccount`. Ships from PR 1. |
| `tests/Unit/Services/Stores/SavingsStoreTest.php` | Unit tests for the store. |
| `tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php` | Unit tests for the normaliser. |
| `tests/Feature/Stores/SavingsThreeIngestParityTest.php` | Three-ingest parity test per spec §16.1 #2 — form / fyn / upload produce byte-identical rows. |
| `database/migrations/2026_05_15_100000_add_derived_columns_to_savings_accounts.php` | PR 6 — adds `balance_gbp`, `annual_interest_projected_gbp`, `isa_allowance_used_pct`, plus `_calculated_at` columns. |
| `database/migrations/2026_05_15_100001_create_savings_account_value_snapshots_table.php` | PR 6 — snapshot table. |

### Modified files (touched during this pass)

| Path | Why |
|------|-----|
| `app/Http/Controllers/Api/SavingsController.php` | PR 2 — controllers shrink: validate, normalise, call store, return resource. Direct `SavingsAccount::create / update / delete / save` calls (lines 290, 394, 439, 477) are removed. |
| `app/Agents/CoordinatingAgent.php` | PR 3 — `handleCreateSavingsAccount` (lines ~2052–2132) shrinks to: validate tool input, normalise via Fyn, call store, return existing AI response envelope. `SavingsAccount::create` at line 2119 is removed. |
| `app/Services/Documents/DocumentProcessor.php` | PR 4 — line 404 `SavingsAccount::create($mapped)` becomes a store call with `IngestSource::UPLOAD`. |
| `app/Services/Onboarding/AssetCaptureEntityExtractor.php` | PR 3 — read at line 226 unchanged (already a read); but any write paths in this file (via `OnboardingChatDirector::handleInlineCapture`) route through the same `create_savings_account` tool which PR 3 already migrates. Audit for completeness only. |
| `app/Services/Onboarding/OnboardingService.php` | PR 3 — line 732 `SavingsAccount::create([...])` (initial-onboarding seed) becomes a store call with `IngestSource::FORM` (or `IngestSource::FYN_AI` if it runs inside an onboarding chat flow — investigate before deciding). |
| `app/Http/Controllers/Api/PreviewController.php` | PR 4 / PR 8 — line 480 `SavingsAccount::create([...])` for preview persona seeding becomes a store call with `IngestSource::SEEDER`. Note: PreviewController writes are still blocked at the HTTP layer by `PreviewWriteInterceptor` for live preview users; this call site is for internal persona-seeding. |
| `database/seeders/PreviewUserSeeder.php` | PR 4 / PR 8 — line 825 `SavingsAccount::create([...])` → store call with `IngestSource::SEEDER`. |
| `database/seeders/ChrisUserSeeder.php` | PR 4 / PR 8 — line 194 `SavingsAccount::updateOrCreate(...)` → store call with `IngestSource::SEEDER`. Note `updateOrCreate` semantics — preserve them. |
| `app/Services/Mobile/MobileDashboardAggregator.php` | PR 5 — read consumer migration. Reads `SavingsStore::forUser($user)` instead of `SavingsAccount::forUserOrJoint($user->id)`. |
| `app/Services/Estate/EstateAssetAggregatorService.php` | PR 5 — same pattern. |
| `app/Services/Estate/EstateActionDefinitionService.php` | PR 5 — same pattern. |
| `app/Services/Plans/GoalPlanService.php` | PR 5 — same pattern. |
| `app/Services/Plans/RetirementPlanService.php` | PR 5 — same pattern. |
| `app/Services/Plans/BasePlanService.php` | PR 5 — same pattern. |
| `app/Services/Plans/SavingsPlanService.php` | PR 5 — same pattern. |
| `app/Services/Plans/InvestmentPlanService.php` | PR 5 — same pattern. |
| `app/Services/Retirement/RetirementStrategyService.php` | PR 5 — same pattern. |
| `app/Services/Retirement/RetirementIncomeService.php` | PR 5 — same pattern. |
| `app/Services/Coordination/HouseholdPlanningService.php` | PR 5 — same pattern. |
| `app/Services/Coordination/CashFlowCoordinator.php` | PR 5 — same pattern. |
| `app/Services/Tax/Strategies/JointSavingsStrategy.php` | PR 5 — same pattern. |
| `app/Services/Tax/Strategies/AssetShiftingBundleStrategy.php` | PR 5 — same pattern. |
| `app/Services/Tax/Strategies/PensionAACarryForwardStrategy.php` | PR 5 — same pattern. |
| `app/Services/Tax/Strategies/IsaTopUpStrategy.php` | PR 5 — same pattern. |
| `app/Services/Estate/EstateAssetAggregatorService.php` | PR 5 — IHT consumer. |
| `app/Services/Savings/ISATracker.php` | PR 5 — savings-internal consumer. Reads only — promote to store reads. |
| `app/Services/Savings/SavingsActionDefinitionService.php` | PR 5 — savings-internal consumer. |
| `app/Services/Investment/Tax/ISAAllowanceOptimizer.php` | PR 5 — cross-module ISA consumer. |
| `app/Services/Investment/Tax/TaxOptimizationAnalyzer.php` | PR 5 — cross-module ISA consumer. |
| `app/Services/Investment/Recommendation/UserContextBuilder.php` | PR 5 — cross-module ISA consumer. |
| `app/Services/Shared/CrossModuleAssetAggregator.php` | PR 5 — net-worth consumer. |
| `app/Services/Tax/TaxOptimisationService.php` | PR 5 — tax consumer. |
| `app/Services/Tax/TaxStrategyMath.php` | PR 5 — tax math consumer. |
| `app/Services/Tax/TaxActionDefinitionService.php` | PR 5 — tax action consumer. |
| `app/Services/Goals/LifeEventAllocationService.php` | PR 5 — goals consumer. |
| `app/Services/AI/AdvicePromptBuilder.php` | PR 5 — Fyn AI read consumer. |
| `app/Services/AI/DuplicateAcknowledgement.php` | PR 5 — Fyn AI duplicate check consumer. |
| `app/Services/UserProfile/ProfileCompletenessChecker.php` | PR 5 — profile consumer. |
| `app/Services/UserProfile/LetterToSpouseService.php` | PR 5 — letter-to-spouse consumer. |
| `app/Agents/SavingsAgent.php` | PR 5 — agent now reads via store. |
| `app/Agents/InvestmentAgent.php` | PR 5 — reads savings ISA balances via store. |
| `app/Agents/CoordinatingAgent.php` | PR 5 — any read-only references to `SavingsAccount` (e.g. duplicate-name check, net-worth aggregation) move to store reads. |
| `app/Models/Goal.php` | PR 5 — relationship reads only; no mutation. Audit for any direct `SavingsAccount::query()` calls. |
| `app/Providers/EventServiceProvider.php` | PR 5 — register listeners for the new `SavingsAccount*` events so `NetWorthRecalculator` / cache invalidation / Fyn read-cache invalidation fire on store events instead of model observers (existing observers stay as a transition until pass 2 ships). |
| `app/Providers/AppServiceProvider.php` | PR 1 — bind `TierGate` interface to `PermissiveTierGate` default. |

### Untouched (deliberately)

- `app/Models/SavingsAccount.php` — no model changes in pass 1. Fillable, casts, relationships, encryption stay as-is.
- `app/Observers/SavingsAccountGoalObserver.php` — stays. Observers are on the spec §14.2 allowlist. Pass 2 (reference data) doesn't touch this.
- `app/Observers/SavingsAccountRiskObserver.php` — stays. Same allowlist exemption.
- `app/Http/Resources/SavingsAccountResource.php` — output shape preserved exactly per spec §2.2 (Vue/HTTP API contract unchanged).
- `resources/js/**` — zero frontend changes in pass 1. Vue stores keep reading `/api/savings/*` responses; the backend swap is invisible to them.

---

## TDD discipline

Every task below follows the TDD micro-cycle:

1. Write the failing test.
2. Run the test and confirm it fails for the *right reason*.
3. Write the minimal implementation.
4. Run the test and confirm it passes.
5. Run the broader suite (`./vendor/bin/pest` for the affected module) and confirm no regressions.
6. Commit (one focused commit per micro-step, or one combined commit per `- [ ]` block — engineer's call as long as the failing-test step is in history).

**Run commands from the main worktree:** `/Users/CSJ/Desktop/fynla` has `vendor/`. This worktree (`cranky-lewin-6bc99c`) does not. Either run `cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest ...` or use `composer install` once inside this worktree.

**Browser testing law (CLAUDE.md):** every PR ships with Playwright verification on csjones — click, fill, submit, observe DB + UI. No "verified by code review" claims.

---

## Task 1 — PR 1: Introduce `SavingsStore` facade, `IngestSource`, supporting types, arch test

**PR title:** `feat(savings): introduce SavingsStore facade + validation + audit + boundary arch test`

**Files:**
- Create: `app/Services/Stores/IngestSource.php`
- Create: `app/Services/Stores/SavingsStore.php`
- Create: `app/Services/Stores/Normalisers/SavingsAccountNormaliser.php`
- Create: `app/Services/Stores/Exceptions/StoreValidationException.php`
- Create: `app/Services/Stores/Exceptions/TierLimitExceededException.php`
- Create: `app/Services/Stores/TierGate.php`
- Create: `app/Services/Stores/PermissiveTierGate.php`
- Create: `tests/Unit/Services/Stores/SavingsStoreTest.php`
- Create: `tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php`
- Create: `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php`
- Modify: `app/Providers/AppServiceProvider.php`

### Step 1.1: Write the failing IngestSource enum test

- [ ] **Create `tests/Unit/Services/Stores/IngestSourceTest.php` with the failing test:**

```php
<?php

declare(strict_types=1);

use App\Services\Stores\IngestSource;

it('exposes the five canonical ingest source cases', function () {
    expect(IngestSource::cases())->toHaveCount(5);
    expect(IngestSource::FORM->value)->toBe('form');
    expect(IngestSource::FYN_AI->value)->toBe('fyn_ai');
    expect(IngestSource::UPLOAD->value)->toBe('upload');
    expect(IngestSource::SEEDER->value)->toBe('seeder');
    expect(IngestSource::ADMIN->value)->toBe('admin');
});
```

- [ ] **Run and confirm it fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/IngestSourceTest.php
```

Expected: FAIL with `class App\Services\Stores\IngestSource not found`.

### Step 1.2: Implement IngestSource

- [ ] **Create `app/Services/Stores/IngestSource.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

enum IngestSource: string
{
    case FORM = 'form';
    case FYN_AI = 'fyn_ai';
    case UPLOAD = 'upload';
    case SEEDER = 'seeder';
    case ADMIN = 'admin';
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/IngestSourceTest.php
```

Expected: PASS, 1 test, 5 assertions.

### Step 1.3: Implement supporting exception classes (no test — they are throwable value classes)

- [ ] **Create `app/Services/Stores/Exceptions/StoreValidationException.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Exceptions;

use RuntimeException;

class StoreValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors, string $message = 'Store validation failed')
    {
        parent::__construct($message);
    }
}
```

- [ ] **Create `app/Services/Stores/Exceptions/TierLimitExceededException.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Exceptions;

use RuntimeException;

class TierLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $entityKey,
        public readonly int $currentCount,
        public readonly ?int $hardLimit,
        string $message = 'Tier limit exceeded'
    ) {
        parent::__construct($message);
    }
}
```

### Step 1.4: Write the failing TierGate contract test

- [ ] **Create `tests/Unit/Services/Stores/PermissiveTierGateTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\PermissiveTierGate;

it('permissive gate always allows create', function () {
    $user = User::factory()->make(['id' => 1]);
    $gate = new PermissiveTierGate;

    expect($gate->canCreate($user, 'savings_account', 999))->toBeTrue();
    expect($gate->softLimit($user, 'savings_account'))->toBeNull();
    expect($gate->hardLimit($user, 'savings_account'))->toBeNull();
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/PermissiveTierGateTest.php
```

Expected: FAIL with `App\Services\Stores\PermissiveTierGate not found`.

### Step 1.5: Implement TierGate interface and PermissiveTierGate

- [ ] **Create `app/Services/Stores/TierGate.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\User;

interface TierGate
{
    /**
     * Whether $user is permitted to create another record of $entityKey
     * given the current count $currentCount.
     */
    public function canCreate(User $user, string $entityKey, int $currentCount): bool;

    /**
     * Soft limit for an upgrade prompt, or null for unlimited.
     */
    public function softLimit(User $user, string $entityKey): ?int;

    /**
     * Hard limit beyond which create() throws, or null for unlimited.
     */
    public function hardLimit(User $user, string $entityKey): ?int;
}
```

- [ ] **Create `app/Services/Stores/PermissiveTierGate.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\User;

class PermissiveTierGate implements TierGate
{
    public function canCreate(User $user, string $entityKey, int $currentCount): bool
    {
        return true;
    }

    public function softLimit(User $user, string $entityKey): ?int
    {
        return null;
    }

    public function hardLimit(User $user, string $entityKey): ?int
    {
        return null;
    }
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/PermissiveTierGateTest.php
```

Expected: PASS, 1 test, 3 assertions.

### Step 1.6: Bind the interface in AppServiceProvider

- [ ] **Locate the existing `register()` method in `app/Providers/AppServiceProvider.php` and add the binding:**

```php
// Inside AppServiceProvider::register() — alongside any other bindings.
$this->app->bind(
    \App\Services\Stores\TierGate::class,
    \App\Services\Stores\PermissiveTierGate::class
);
```

- [ ] **Add a small test that proves the binding resolves:**

Create `tests/Unit/Services/Stores/TierGateBindingTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\Stores\PermissiveTierGate;
use App\Services\Stores\TierGate;

it('resolves TierGate interface to PermissiveTierGate by default', function () {
    expect(app(TierGate::class))->toBeInstanceOf(PermissiveTierGate::class);
});
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/TierGateBindingTest.php
```

Expected: PASS.

### Step 1.7: Write the failing normaliser test (fromForm)

- [ ] **Create `tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Services\Stores\Normalisers\SavingsAccountNormaliser;

describe('SavingsAccountNormaliser::fromForm', function () {
    it('produces canonical-array shape from HTTP form payload', function () {
        $normaliser = new SavingsAccountNormaliser;

        $canonical = $normaliser->fromForm([
            'account_name' => 'Nationwide Cash ISA',
            'account_type' => 'cash_isa',
            'institution' => 'Nationwide',
            'current_balance' => 5000,
            'interest_rate' => 4.5,
            'is_isa' => true,
            'ownership_type' => 'joint',
            'joint_owner_id' => 99,
            // ownership_percentage NOT set — store should infer 50/50 for joint
        ]);

        expect($canonical['account_name'])->toBe('Nationwide Cash ISA');
        expect($canonical['account_type'])->toBe('cash_isa');
        expect($canonical['ownership_type'])->toBe('joint');
        expect($canonical['ownership_percentage'])->toBe(50.00);
        expect($canonical['country'])->toBe('United Kingdom'); // ISA → UK enforced
        expect($canonical['joint_owner_id'])->toBe(99);
    });

    it('defaults ownership to individual at 100% when not set', function () {
        $canonical = (new SavingsAccountNormaliser)->fromForm([
            'account_name' => 'Vanguard cash',
            'current_balance' => 1000,
        ]);

        expect($canonical['ownership_type'])->toBe('individual');
        expect($canonical['ownership_percentage'])->toBe(100.00);
        expect($canonical['country'])->toBe('United Kingdom'); // default for non-ISA
    });
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php
```

Expected: FAIL with `class not found`.

### Step 1.8: Implement the normaliser (fromForm only — fromFyn / fromUpload follow in PR 3 / PR 4)

- [ ] **Create `app/Services/Stores/Normalisers/SavingsAccountNormaliser.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

class SavingsAccountNormaliser
{
    /**
     * Map HTTP form-validated input to the canonical-array shape consumed
     * by SavingsStore::create(). Replicates the ownership / country logic
     * that previously lived in SavingsController::storeAccount (lines 266-285).
     */
    public function fromForm(array $request): array
    {
        $data = $request;

        $data['ownership_type'] = $data['ownership_type'] ?? 'individual';

        if (! isset($data['ownership_percentage'])) {
            $data['ownership_percentage'] = 100.00;
        }

        if ($data['ownership_type'] === 'joint' && (float) $data['ownership_percentage'] === 100.00) {
            $data['ownership_percentage'] = 50.00;
        }

        // ISA accounts must always be United Kingdom.
        // Non-ISA accounts default to United Kingdom if not provided.
        if (! empty($data['is_isa'])) {
            $data['country'] = 'United Kingdom';
        } elseif (! isset($data['country']) || $data['country'] === null) {
            $data['country'] = 'United Kingdom';
        }

        return $data;
    }
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php
```

Expected: PASS, 2 tests.

### Step 1.9: Write the failing SavingsStore::create test

- [ ] **Create `tests/Unit/Services/Stores/SavingsStoreTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('SavingsStore::create persists a SavingsAccount through the canonical write path', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Nationwide Cash ISA',
        'account_type' => 'cash_isa',
        'institution' => 'Nationwide',
        'current_balance' => 5000,
        'interest_rate' => 4.5,
        'is_isa' => true,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    expect($account)->toBeInstanceOf(SavingsAccount::class);
    expect($account->user_id)->toBe($user->id);
    expect($account->account_name)->toBe('Nationwide Cash ISA');
    expect((float) $account->current_balance)->toBe(5000.00);
    expect(SavingsAccount::count())->toBe(1);
});

it('SavingsStore::create rejects writes with missing required fields', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    expect(fn () => $store->create(['institution' => 'Aviva'], $user, IngestSource::FORM))
        ->toThrow(\App\Services\Stores\Exceptions\StoreValidationException::class);

    expect(SavingsAccount::count())->toBe(0);
});

it('SavingsStore::update mutates the account through the canonical write path', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 1000,
    ]);

    $updated = $store->update($account->id, ['current_balance' => 2500], $user, IngestSource::FORM);

    expect((float) $updated->current_balance)->toBe(2500.00);
});

it('SavingsStore::delete soft-deletes the account', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = SavingsAccount::factory()->create(['user_id' => $user->id]);

    $store->delete($account->id, $user, 'user_requested');

    expect(SavingsAccount::find($account->id))->toBeNull();
    expect(SavingsAccount::withTrashed()->find($account->id))->not->toBeNull();
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsStoreTest.php
```

Expected: FAIL with `class App\Services\Stores\SavingsStore not found`.

### Step 1.10: Implement the minimal SavingsStore

- [ ] **Create `app/Services/Stores/SavingsStore.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Normalisers\SavingsAccountNormaliser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SavingsStore
{
    public const ENTITY_KEY = 'savings_account';

    public function __construct(
        private readonly SavingsAccountNormaliser $normaliser,
        private readonly TierGate $tierGate,
    ) {}

    // ---------- Reads ----------

    public function find(int $id, User $user): ?SavingsAccount
    {
        return SavingsAccount::query()
            ->where('id', $id)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->first();
    }

    public function forUser(User $user): Collection
    {
        return SavingsAccount::forUserOrJoint($user->id)->get();
    }

    // ---------- Writes ----------

    public function create(array $data, User $user, IngestSource $source): SavingsAccount
    {
        // Source-specific normalisation already done by caller — this is the
        // canonical-shape entry point. We validate the canonical shape here.
        $this->validateCanonical($data);

        $count = SavingsAccount::where('user_id', $user->id)->count();
        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }

        $payload = array_merge($data, [
            'user_id' => $user->id,
            'ingest_source' => $source->value, // captured into audit row, not the model column
        ]);

        // Remove non-model keys before persist
        $modelPayload = $payload;
        unset($modelPayload['ingest_source']);

        return DB::transaction(function () use ($modelPayload, $source) {
            $account = SavingsAccount::create($modelPayload);

            // ingest_source captured in audit via the existing Auditable trait
            // when PR 8 wires it; for PR 1 we attach to the audit metadata later.

            return $account;
        });
    }

    public function update(int $id, array $data, User $user, IngestSource $source): SavingsAccount
    {
        $account = SavingsAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($data, partial: true);

        return DB::transaction(function () use ($account, $data) {
            $account->update($data);
            return $account->fresh();
        });
    }

    public function delete(int $id, User $user, string $reason): void
    {
        $account = SavingsAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $account->delete();
    }

    public function restore(int $id, User $user): SavingsAccount
    {
        $account = SavingsAccount::withTrashed()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $account->restore();

        return $account;
    }

    // ---------- Internal ----------

    private function validateCanonical(array $data, bool $partial = false): void
    {
        $rules = [
            'account_name' => ($partial ? 'sometimes|' : 'required|').'string|max:255',
            'current_balance' => ($partial ? 'sometimes|' : 'required|').'numeric|min:0',
            'account_type' => 'sometimes|string|max:255',
            'institution' => 'sometimes|string|max:255',
            'interest_rate' => 'sometimes|numeric|min:0|max:20',
            'is_isa' => 'sometimes|boolean',
            'is_emergency_fund' => 'sometimes|boolean',
            'ownership_type' => 'sometimes|in:individual,joint,trust',
            'ownership_percentage' => 'sometimes|numeric|min:0|max:100',
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            'country' => 'sometimes|string|max:255',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsStoreTest.php
```

Expected: PASS, 4 tests.

### Step 1.11: Write the failing arch test

- [ ] **Create `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php`:**

```php
<?php

declare(strict_types=1);

/**
 * Sub-Project 1, Pass 1 — Savings store boundary enforcement.
 *
 * Hard-fails CI on any direct mutation of App\Models\SavingsAccount
 * outside the canonical write path (App\Services\Stores\SavingsStore).
 *
 * Allowlist (§14.2 of the spec): observers, migrations, seeders, console
 * commands, the store itself, and pre-existing direct-mutation sites
 * that subsequent PRs in this pass will migrate. Each entry below has a
 * comment naming the PR that removes it.
 */

arch('SavingsAccount mutations only happen inside SavingsStore (plus transition allowlist)')
    ->expect('App\Models\SavingsAccount')
    ->toOnlyBeUsedIn([
        // Permanent allowlist
        'App\Services\Stores\SavingsStore',
        'App\Services\Stores\Normalisers\SavingsAccountNormaliser',
        'App\Observers\SavingsAccountGoalObserver',
        'App\Observers\SavingsAccountRiskObserver',
        'App\Models\\',                     // self-references in relationships
        'Database\Factories\SavingsAccountFactory',

        // Transition allowlist — removed by subsequent PRs in pass 1.
        // PR 2 removes: SavingsController (HTTP form path)
        'App\Http\Controllers\Api\SavingsController',
        // PR 3 removes: CoordinatingAgent (Fyn AI tool path), OnboardingService
        'App\Agents\CoordinatingAgent',
        'App\Services\Onboarding\OnboardingService',
        'App\Services\Onboarding\AssetCaptureEntityExtractor', // reads only — kept on read consumers list
        // PR 4 removes: DocumentProcessor (upload path), PreviewController, seeders
        'App\Services\Documents\DocumentProcessor',
        'App\Http\Controllers\Api\PreviewController',
        'Database\Seeders\PreviewUserSeeder',
        'Database\Seeders\ChrisUserSeeder',
        // PR 5 removes: read consumers (all listed in plan §"Modified files")
        'App\Agents\SavingsAgent',
        'App\Agents\InvestmentAgent',
        'App\Services\Mobile\MobileDashboardAggregator',
        'App\Services\Estate\EstateAssetAggregatorService',
        'App\Services\Estate\EstateActionDefinitionService',
        'App\Services\Plans\BasePlanService',
        'App\Services\Plans\GoalPlanService',
        'App\Services\Plans\RetirementPlanService',
        'App\Services\Plans\SavingsPlanService',
        'App\Services\Plans\InvestmentPlanService',
        'App\Services\Retirement\RetirementStrategyService',
        'App\Services\Retirement\RetirementIncomeService',
        'App\Services\Coordination\HouseholdPlanningService',
        'App\Services\Coordination\CashFlowCoordinator',
        'App\Services\Tax\Strategies\JointSavingsStrategy',
        'App\Services\Tax\Strategies\AssetShiftingBundleStrategy',
        'App\Services\Tax\Strategies\PensionAACarryForwardStrategy',
        'App\Services\Tax\Strategies\IsaTopUpStrategy',
        'App\Services\Tax\TaxOptimisationService',
        'App\Services\Tax\TaxStrategyMath',
        'App\Services\Tax\TaxActionDefinitionService',
        'App\Services\Savings\ISATracker',
        'App\Services\Savings\SavingsActionDefinitionService',
        'App\Services\Investment\Tax\ISAAllowanceOptimizer',
        'App\Services\Investment\Tax\TaxOptimizationAnalyzer',
        'App\Services\Investment\Recommendation\UserContextBuilder',
        'App\Services\Shared\CrossModuleAssetAggregator',
        'App\Services\Goals\LifeEventAllocationService',
        'App\Services\AI\AdvicePromptBuilder',
        'App\Services\AI\DuplicateAcknowledgement',
        'App\Services\UserProfile\ProfileCompletenessChecker',
        'App\Services\UserProfile\LetterToSpouseService',
        'App\Models\Goal',
    ]);

arch('App\Services\Stores classes use strict types')
    ->expect('App\Services\Stores')
    ->toUseStrictTypes();
```

- [ ] **Run the architecture suite to confirm the test runs (it should PASS at this point because the allowlist is generous — but it MUST be hard-failing for any new caller not on the list):**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest --testsuite=Architecture
```

Expected: PASS. Note for the engineer: this test starts hard but with a wide allowlist. Subsequent PRs in pass 1 shrink the allowlist. PR 8 confirms it's down to the permanent entries only.

### Step 1.12: Verify the Architecture suite is part of the default `pest` run

- [ ] **Confirm `phpunit.xml` includes the Architecture suite:**

```bash
grep -A2 "<testsuites>" phpunit.xml
```

Expected: an entry for `Architecture` with `<directory>tests/Architecture</directory>`. If missing, add it (this is a CLAUDE.md task — check before adding).

### Step 1.13: Implement the four storage events (spec §11)

- [ ] **Write the failing event test `tests/Unit/Services/Stores/SavingsStoreEventsTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Events\Savings\SavingsAccountCreated;
use App\Events\Savings\SavingsAccountDeleted;
use App\Events\Savings\SavingsAccountUpdated;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('SavingsStore::create emits SavingsAccountCreated', function () {
    Event::fake();
    $user = User::factory()->create();

    app(SavingsStore::class)->create([
        'account_name' => 'X', 'current_balance' => 100,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'UK',
    ], $user, IngestSource::FORM);

    Event::assertDispatched(SavingsAccountCreated::class);
});

it('SavingsStore::update emits SavingsAccountUpdated with changes diff', function () {
    Event::fake();
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 100]);

    app(SavingsStore::class)->update($account->id, ['current_balance' => 500], $user, IngestSource::FORM);

    Event::assertDispatched(SavingsAccountUpdated::class, function ($event) {
        return array_key_exists('current_balance', $event->changes);
    });
});

it('SavingsStore::delete emits SavingsAccountDeleted', function () {
    Event::fake();
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create(['user_id' => $user->id]);

    app(SavingsStore::class)->delete($account->id, $user, 'user_requested');

    Event::assertDispatched(SavingsAccountDeleted::class);
});
```

- [ ] **Run, confirm fails (event classes don't exist):**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsStoreEventsTest.php
```

Expected: FAIL — class not found.

- [ ] **Create the four event classes:**

```php
// app/Events/Savings/SavingsAccountCreated.php
<?php declare(strict_types=1);
namespace App\Events\Savings;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;

class SavingsAccountCreated
{
    public function __construct(
        public readonly SavingsAccount $entity,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
```

```php
// app/Events/Savings/SavingsAccountUpdated.php
<?php declare(strict_types=1);
namespace App\Events\Savings;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;

class SavingsAccountUpdated
{
    public function __construct(
        public readonly SavingsAccount $entity,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
```

```php
// app/Events/Savings/SavingsAccountDeleted.php
<?php declare(strict_types=1);
namespace App\Events\Savings;
use App\Models\User;

class SavingsAccountDeleted
{
    public function __construct(
        public readonly int $entityId,
        public readonly User $user,
        public readonly string $reason,
    ) {}
}
```

```php
// app/Events/Savings/SavingsAccountRestored.php
<?php declare(strict_types=1);
namespace App\Events\Savings;
use App\Models\SavingsAccount;
use App\Models\User;

class SavingsAccountRestored
{
    public function __construct(
        public readonly SavingsAccount $entity,
        public readonly User $user,
    ) {}
}
```

- [ ] **Wire emission into `SavingsStore`. Edit `app/Services/Stores/SavingsStore.php` — at the end of each write method add:**

```php
// In create() — at the end, before return:
event(new \App\Events\Savings\SavingsAccountCreated($account, $user, $source));

// In update() — capture diff before saving, emit after:
$dirty = $account->getDirty();  // before save
$account->update($data);
$fresh = $account->fresh();
event(new \App\Events\Savings\SavingsAccountUpdated($fresh, $dirty, $user, $source));

// In delete() — emit after delete:
event(new \App\Events\Savings\SavingsAccountDeleted($id, $user, $reason));

// In restore() — emit after restore:
event(new \App\Events\Savings\SavingsAccountRestored($account, $user));
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsStoreEventsTest.php
```

Expected: PASS, 3 tests.

### Step 1.14: Run the full suite

- [ ] **Sanity check — no regressions anywhere:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: all suites green. Total ~940+ cases.

### Step 1.15: Commit PR 1

- [ ] **Stage and commit:**

```bash
cd /Users/CSJ/Desktop/fynla && git status
cd /Users/CSJ/Desktop/fynla && git add \
  app/Services/Stores/ \
  app/Providers/AppServiceProvider.php \
  tests/Unit/Services/Stores/ \
  tests/Architecture/StoreBoundary/
cd /Users/CSJ/Desktop/fynla && git commit -m "$(cat <<'EOF'
feat(savings): introduce SavingsStore facade + arch boundary

Sub-project 1 / pass 1 / PR 1. Lays the foundation:

- App\Services\Stores\IngestSource (FORM | FYN_AI | UPLOAD | SEEDER | ADMIN)
- App\Services\Stores\SavingsStore (canonical write path; reads via forUser/find)
- App\Services\Stores\Normalisers\SavingsAccountNormaliser::fromForm
- App\Services\Stores\TierGate interface + PermissiveTierGate default
- StoreValidationException / TierLimitExceededException
- tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php
  Hard-failing in CI from this PR with explicit transition allowlist.

No consumers wired yet — that lands across PR 2 (HTTP form), PR 3 (Fyn AI),
PR 4 (upload + seeders), PR 5 (read consumers). The allowlist names each
file each subsequent PR removes.

Spec: docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
Plan: docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Push and open PR `feat/savings-store-pr1` → `dev`:**

Per CLAUDE.md branching: branch off the current `claude/cranky-lewin-6bc99c` worktree base into `feat/savings-store-pr1`, push, open PR targeting `dev`.

```bash
cd /Users/CSJ/Desktop/fynla && git checkout -b feat/savings-store-pr1
cd /Users/CSJ/Desktop/fynla && git push -u origin feat/savings-store-pr1
gh pr create --base dev --title "feat(savings): introduce SavingsStore facade + boundary arch test" --body "$(cat <<'EOF'
## Summary
- New \`App\Services\Stores\SavingsStore\` facade with create/update/delete/restore + read methods (find, forUser).
- Shared \`IngestSource\` enum across all 13+ sub-project-1 entities.
- \`SavingsAccountNormaliser::fromForm\` extracted from controller logic.
- \`TierGate\` interface + \`PermissiveTierGate\` (sub-project-2 will replace).
- Pest arch test hard-fails CI on direct mutations outside the store, with an explicit transition allowlist that subsequent PRs remove.

## Test plan
- [x] \`./vendor/bin/pest tests/Unit/Services/Stores/\` passes (≥ 10 cases)
- [x] \`./vendor/bin/pest --testsuite=Architecture\` passes
- [x] \`./vendor/bin/pest\` (full suite) passes
- [ ] csjones smoke: register a fresh test user, login, navigate \`/savings\`, observe existing flows still work (this PR is pure addition, no behavioural change yet)

## Browser-test plan
1. Login chris@fynla.org → MFA → dashboard
2. Open \`/savings\` → "Add account" → fill, save → assert account appears
3. Verify zero JS errors, zero new entries in laravel.log

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

### Step 1.16: csjones browser smoke

- [ ] **Deploy `feat/savings-store-pr1` to csjones per CLAUDE.md "Deploying to dev" + `feedback_deploy_gate_csjones_before_admin_merge.md`. Then drive the browser test plan above via Playwright.**

(See "Browser-test plan" in the PR body. Use the Playwright MCP tools — `mcp__playwright__browser_navigate`, `browser_fill_form`, `browser_click`, `browser_snapshot`. No "verified by code review" claims.)

- [ ] **Only after csjones smoke is green: admin-merge per `feedback_admin_merge_pattern_for_solo_reviewer_prs.md`:**

```bash
gh pr merge <PR#> --merge --admin
```

---

## Task 2 — PR 2: Point HTTP form requests at `SavingsStore`

**PR title:** `refactor(savings): point HTTP form requests at SavingsStore`

**Files:**
- Modify: `app/Http/Controllers/Api/SavingsController.php` (lines 258-457: `storeAccount`, `updateAccount`, `destroyAccount`, `toggleRetirementInclusion`)
- Modify: `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` (remove `SavingsController` from allowlist)
- Modify: `tests/Feature/Savings/SavingsApiTest.php` (existing — re-verify after refactor)

### Step 2.1: Pre-flight — confirm existing feature tests still pass

- [ ] **Run baseline:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/Savings/
```

Expected: PASS. Capture the count as the baseline for this PR.

### Step 2.2: Add the failing feature test for normalised semantics through HTTP

- [ ] **Add to `tests/Feature/Savings/SavingsApiTest.php` (or create a new file `tests/Feature/Savings/SavingsStoreIntegrationTest.php`):**

```php
it('HTTP POST /api/savings/accounts persists via SavingsStore with IngestSource::FORM', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/savings/accounts', [
        'account_name' => 'Halifax Easy Saver',
        'account_type' => 'easy_access',
        'institution' => 'Halifax',
        'current_balance' => 12000,
        'interest_rate' => 4.2,
        'is_isa' => false,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('savings_accounts', [
        'user_id' => $user->id,
        'account_name' => 'Halifax Easy Saver',
        'current_balance' => 12000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ]);
});

it('HTTP POST infers UK country and 50/50 split for joint ISA', function () {
    $user = User::factory()->create();
    $spouse = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/savings/accounts', [
        'account_name' => 'Joint Cash ISA',
        'account_type' => 'cash_isa',
        'current_balance' => 8000,
        'is_isa' => true,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
    ])->assertCreated();

    $this->assertDatabaseHas('savings_accounts', [
        'account_name' => 'Joint Cash ISA',
        'country' => 'United Kingdom',
        'ownership_percentage' => 50.00,
    ]);
});
```

- [ ] **Run and confirm the new tests pass (because PR 1 didn't yet change controller logic, but the normaliser logic is identical to what's currently in the controller — they should still pass after the refactor too):**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/Savings/SavingsApiTest.php
```

Expected: PASS for the new tests as well as existing tests. These cases lock the canonical-shape contract.

### Step 2.3: Refactor `SavingsController::storeAccount`

- [ ] **Replace lines 258-309 in `app/Http/Controllers/Api/SavingsController.php` with:**

```php
public function storeAccount(StoreSavingsAccountRequest $request): JsonResponse
{
    $user = $request->user();

    try {
        $canonical = $this->normaliser->fromForm($request->validated());
        $account = $this->savingsStore->create($canonical, $user, IngestSource::FORM);

        $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $account->joint_owner_id);

        $accountData = (new SavingsAccountResource($account))->toArray($request);
        $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
        $accountData['full_balance'] = (float) $account->current_balance;
        $accountData['is_primary_owner'] = true;
        $accountData['is_shared'] = $this->isSharedOwnership($account);

        return response()->json([
            'success' => true,
            'message' => 'Savings account created successfully',
            'data' => $accountData,
        ], 201);
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return $this->validationErrorResponse('Validation failed', $e->errors);
    } catch (\Exception $e) {
        return $this->errorResponse($e, 'Creating savings account');
    }
}
```

- [ ] **Add the two new constructor injections at the top of the class:**

```php
use App\Services\Stores\IngestSource;
use App\Services\Stores\Normalisers\SavingsAccountNormaliser;
use App\Services\Stores\SavingsStore;

// In the existing __construct() — add these two private readonly params:
private readonly SavingsStore $savingsStore,
private readonly SavingsAccountNormaliser $normaliser,
```

### Step 2.4: Refactor `updateAccount` (lines 362-419)

- [ ] **Replace the body with:**

```php
public function updateAccount(UpdateSavingsAccountRequest $request, int $id): JsonResponse
{
    $user = $request->user();

    try {
        $canonical = $this->normaliser->fromForm($request->validated());
        $account = $this->savingsStore->update($id, $canonical, $user, IngestSource::FORM);

        $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $account->joint_owner_id);

        $accountData = (new SavingsAccountResource($account))->toArray($request);
        $accountData['user_share'] = $this->calculateUserShare($account, $user->id);
        $accountData['full_balance'] = (float) $account->current_balance;
        $accountData['is_primary_owner'] = true;
        $accountData['is_shared'] = $this->isSharedOwnership($account);

        return response()->json([
            'success' => true,
            'message' => 'Savings account updated successfully',
            'data' => $accountData,
        ]);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['success' => false, 'message' => 'Account not found or unauthorized'], 404);
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return $this->validationErrorResponse('Validation failed', $e->errors);
    } catch (\Exception $e) {
        return $this->errorResponse($e, 'Updating savings account');
    }
}
```

### Step 2.5: Refactor `destroyAccount` (lines 426-460)

- [ ] **Replace the body with:**

```php
public function destroyAccount(Request $request, int $id): JsonResponse
{
    $user = $request->user();

    try {
        $this->savingsStore->delete($id, $user, 'user_requested');
        $this->cacheInvalidation->invalidateForUser($user->id);

        return response()->json(['success' => true, 'message' => 'Savings account deleted successfully']);
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json(['success' => false, 'message' => 'Account not found or unauthorized'], 404);
    } catch (\Exception $e) {
        return $this->errorResponse($e, 'Deleting savings account');
    }
}
```

### Step 2.6: Refactor `toggleRetirementInclusion` (lines 462-503)

- [ ] **Replace the body — the toggle is a single-field update so it routes through `update`:**

```php
public function toggleRetirementInclusion(Request $request, int $id): JsonResponse
{
    $user = $request->user();

    try {
        // Find current value to compute toggle
        $current = $this->savingsStore->find($id, $user);
        if (! $current) {
            return response()->json(['success' => false, 'message' => 'Account not found'], 404);
        }

        $updated = $this->savingsStore->update(
            $id,
            ['include_in_retirement' => ! $current->include_in_retirement],
            $user,
            IngestSource::FORM
        );

        $this->cacheInvalidation->invalidateForUser($user->id);

        return response()->json([
            'success' => true,
            'data' => ['include_in_retirement' => $updated->include_in_retirement],
        ]);
    } catch (\Exception $e) {
        return $this->errorResponse($e, 'Toggling retirement inclusion');
    }
}
```

### Step 2.7: Update the arch test allowlist — remove `SavingsController`

- [ ] **Edit `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php`. Delete the line:**

```
'App\Http\Controllers\Api\SavingsController',
```

### Step 2.8: Add `include_in_retirement` to the store's whitelisted fields

- [ ] **Add the rule to `SavingsStore::validateCanonical` rules array:**

```php
'include_in_retirement' => 'sometimes|boolean',
```

### Step 2.9: Run feature + arch + unit suites

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/Savings/ tests/Unit/Services/Stores/ tests/Architecture/StoreBoundary/
```

Expected: green across the board. Same case count as baseline + the two new cases in Step 2.2.

### Step 2.10: Run the full suite

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

### Step 2.11: Commit + PR + csjones smoke

- [ ] **Commit:**

```bash
cd /Users/CSJ/Desktop/fynla && git add -A
cd /Users/CSJ/Desktop/fynla && git commit -m "$(cat <<'EOF'
refactor(savings): point HTTP form requests at SavingsStore

Pass 1 / PR 2. SavingsController shrinks to controller-shaped work
(validate, normalise, call store, return resource). Direct
SavingsAccount::create / update / delete calls at lines 290, 394,
439, 477 are removed. Behaviour preserved end-to-end — same
ownership defaults, same UK-country enforcement on ISAs (now
encapsulated in SavingsAccountNormaliser::fromForm).

SavingsController removed from boundary-test allowlist.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Push branch `feat/savings-store-pr2`, open PR → `dev`, run the same browser smoke as PR 1 but with extra coverage for /api/savings/accounts CRUD via Playwright (create, edit, delete an account in the UI). Admin-merge only after csjones smoke is green.**

---

## Task 3 — PR 3: Point Fyn AI write path at `SavingsStore`

**PR title:** `refactor(savings): point Fyn AI write tools at SavingsStore`

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php` (`handleCreateSavingsAccount` ~line 2052-2132)
- Modify: `app/Services/Stores/Normalisers/SavingsAccountNormaliser.php` (add `fromFyn` method)
- Modify: `app/Services/Onboarding/OnboardingService.php` (line 732 — onboarding initial seed)
- Modify: `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` (remove `CoordinatingAgent`, `OnboardingService` from allowlist; keep `AssetCaptureEntityExtractor` for now — it's read-only)
- Modify: `tests/Feature/AI/DirectWrite/CreateSavingsAccountTest.php` (existing — assert envelope unchanged)

### Step 3.1: Write the failing normaliser test for `fromFyn`

- [ ] **Add to `tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php`:**

```php
describe('SavingsAccountNormaliser::fromFyn', function () {
    it('maps AI-facing account_type to DB-canonical value', function () {
        $normaliser = new SavingsAccountNormaliser;

        expect($normaliser->fromFyn(['account_name' => 'X', 'account_type' => 'fixed_term', 'current_balance' => 1000])['account_type'])
            ->toBe('fixed');

        expect($normaliser->fromFyn(['account_name' => 'X', 'account_type' => 'regular_saver', 'current_balance' => 100])['account_type'])
            ->toBe('easy_access');
    });

    it('infers cash_isa when is_isa is true and account_type is not an ISA variant', function () {
        $canonical = (new SavingsAccountNormaliser)->fromFyn([
            'account_name' => 'X',
            'account_type' => 'easy_access',
            'current_balance' => 1000,
            'is_isa' => true,
        ]);

        expect($canonical['account_type'])->toBe('cash_isa');
    });

    it('defaults institution to account_name when missing', function () {
        $canonical = (new SavingsAccountNormaliser)->fromFyn([
            'account_name' => 'Halifax',
            'current_balance' => 1000,
        ]);

        expect($canonical['institution'])->toBe('Halifax');
    });

    it('derives access_type from account_type', function () {
        $normaliser = new SavingsAccountNormaliser;

        expect($normaliser->fromFyn(['account_name' => 'X', 'account_type' => 'notice', 'current_balance' => 1])['access_type'])->toBe('notice');
        expect($normaliser->fromFyn(['account_name' => 'X', 'account_type' => 'fixed_term', 'current_balance' => 1])['access_type'])->toBe('fixed');
        expect($normaliser->fromFyn(['account_name' => 'X', 'current_balance' => 1])['access_type'])->toBe('immediate');
    });
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php --filter=fromFyn
```

Expected: FAIL — `fromFyn` method does not exist.

### Step 3.2: Implement `SavingsAccountNormaliser::fromFyn`

- [ ] **Add the method to `app/Services/Stores/Normalisers/SavingsAccountNormaliser.php`. The logic mirrors `CoordinatingAgent::handleCreateSavingsAccount` lines 2074-2108:**

```php
/**
 * Map Fyn AI tool params to the canonical-array shape consumed by
 * SavingsStore::create(). Replicates the AI-enum-to-DB-value mapping
 * and ISA inference that previously lived in
 * CoordinatingAgent::handleCreateSavingsAccount.
 */
public function fromFyn(array $toolParams): array
{
    $isIsa = (bool) ($toolParams['is_isa'] ?? false);
    $accountType = $toolParams['account_type'] ?? 'easy_access';

    $dbAccountType = match ($accountType) {
        'fixed_term' => 'fixed',
        'regular_saver' => 'easy_access',
        default => $accountType,
    };

    if ($isIsa && ! in_array($dbAccountType, ['cash_isa', 'junior_isa'], true)) {
        $dbAccountType = 'cash_isa';
    }

    $accessType = match ($dbAccountType) {
        'notice' => 'notice',
        'fixed' => 'fixed',
        default => 'immediate',
    };

    $canonical = [
        'account_name' => $toolParams['account_name'],
        'institution' => ! empty($toolParams['institution']) ? $toolParams['institution'] : $toolParams['account_name'],
        'account_type' => $dbAccountType,
        'current_balance' => (float) $toolParams['current_balance'],
        'access_type' => $accessType,
        'is_isa' => $isIsa,
        'is_emergency_fund' => (bool) ($toolParams['is_emergency_fund'] ?? false),
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    if (isset($toolParams['interest_rate'])) {
        $canonical['interest_rate'] = (float) $toolParams['interest_rate'];
    }
    if (isset($toolParams['regular_contribution_amount'])) {
        $canonical['regular_contribution_amount'] = (float) $toolParams['regular_contribution_amount'];
    }

    // ISA / non-ISA country default — same rule as fromForm
    if ($isIsa) {
        $canonical['country'] = 'United Kingdom';
    } else {
        $canonical['country'] = $toolParams['country'] ?? 'United Kingdom';
    }

    return $canonical;
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php
```

Expected: PASS, including the new fromFyn cases.

### Step 3.3: Re-run existing AI direct-write test as a baseline

- [ ] **Run before refactor:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/AI/DirectWrite/CreateSavingsAccountTest.php
```

Expected: PASS (6 cases). Capture as baseline; these MUST stay green after the refactor.

### Step 3.4: Refactor `CoordinatingAgent::handleCreateSavingsAccount`

- [ ] **Replace lines ~2052-2132 in `app/Agents/CoordinatingAgent.php` with:**

```php
private function handleCreateSavingsAccount(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) {
        return $this->previewBlocked('savings account');
    }

    $validationError = $this->validateToolInput($input, [
        'account_name' => 'required|string|max:255',
        'current_balance' => 'required|numeric|min:0|max:999999999.99',
        'account_type' => ['nullable', Rule::in([
            'easy_access', 'notice', 'fixed', 'fixed_term', 'regular_saver',
            'cash_isa', 'junior_isa',
        ])],
        'institution' => 'nullable|string|max:255',
        'interest_rate' => 'nullable|numeric|min:0|max:20',
        'is_isa' => 'nullable|boolean',
        'is_emergency_fund' => 'nullable|boolean',
        'regular_contribution_amount' => 'nullable|numeric|min:0',
    ]);
    if ($validationError) {
        return $validationError;
    }

    $duplicateCheck = $this->checkForDuplicate(SavingsAccount::class, $user->id, 'account_name', $input['account_name']);
    if ($duplicateCheck) {
        return $duplicateCheck;
    }

    $canonical = app(\App\Services\Stores\Normalisers\SavingsAccountNormaliser::class)->fromFyn($input);

    try {
        $account = app(\App\Services\Stores\SavingsStore::class)->create(
            $canonical,
            $user,
            \App\Services\Stores\IngestSource::FYN_AI
        );
    } catch (\App\Services\Stores\Exceptions\StoreValidationException $e) {
        return [
            'error' => true,
            'error_type' => 'validation_failed',
            'errors' => $e->errors,
            'message' => 'Validation failed for savings account.',
        ];
    }

    $this->invalidateUserCache($user->id);

    return [
        'success' => true,
        'created' => true,
        'entity_type' => 'savings_account',
        'entity_id' => $account->id,
        'name' => $account->account_name,
        'persisted_fields' => array_keys($canonical),
        'message' => "I've added your \"{$account->account_name}\" savings account.",
    ];
}
```

### Step 3.5: Refactor `OnboardingService` line 732

- [ ] **Locate the `SavingsAccount::create([...])` call and replace with a store call:**

The existing code (read with `Read` on `app/Services/Onboarding/OnboardingService.php` lines 720-750 before editing) should become:

```php
app(\App\Services\Stores\SavingsStore::class)->create(
    app(\App\Services\Stores\Normalisers\SavingsAccountNormaliser::class)->fromForm([
        // ... existing payload keys
    ]),
    $user,
    \App\Services\Stores\IngestSource::FORM
);
```

Choose `FORM` source because this site is the onboarding wizard's persistence step, which is functionally a form submit. (If audit later wants to distinguish wizard from regular form, the spec allows adding a `FYN_AI` variant — for now FORM is the right canonical mapping.)

### Step 3.6: Re-run the AI direct-write test

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/AI/DirectWrite/CreateSavingsAccountTest.php
```

Expected: all 6 cases PASS — the envelope shape (`success`, `created`, `entity_type`, `entity_id`, `name`, `persisted_fields`) is identical.

### Step 3.7: Update arch test allowlist

- [ ] **Edit `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php`. Delete:**

```
'App\Agents\CoordinatingAgent',
'App\Services\Onboarding\OnboardingService',
```

Keep `App\Services\Onboarding\AssetCaptureEntityExtractor` in the allowlist (it is read-only — confirmed by grep at planning time; PR 5 will remove it once it's been migrated to read via store).

### Step 3.8: Run full suite

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

### Step 3.9: Commit + PR + csjones smoke

- [ ] **Commit:**

```bash
cd /Users/CSJ/Desktop/fynla && git add -A
cd /Users/CSJ/Desktop/fynla && git commit -m "$(cat <<'EOF'
refactor(savings): point Fyn AI write tools at SavingsStore

Pass 1 / PR 3. CoordinatingAgent::handleCreateSavingsAccount no
longer calls SavingsAccount::create directly — the AI-enum-to-DB
mapping and ISA inference move into SavingsAccountNormaliser::fromFyn.
Existing AI direct-write feature tests (6 cases) all pass unchanged;
envelope shape is byte-identical.

OnboardingService's initial-seed savings creation also routes
through the store with IngestSource::FORM.

Boundary allowlist shrinks: CoordinatingAgent + OnboardingService
removed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Push, open PR `feat/savings-store-pr3` → `dev`, deploy csjones, Playwright-test:**
  1. Login as a test user
  2. Open Fyn chat → "Add a Halifax cash ISA with £8,000 at 4.5%"
  3. Wait for the `entity_created` SSE event in the network panel (or wait for the chat bubble confirmation)
  4. Open `/savings` → assert the new account shows with correct fields
  5. Verify DB row directly: `php artisan tinker` on csjones, `SavingsAccount::latest()->first()`
  6. Verify audit row written with `ingest_source = 'fyn_ai'` (column lands in PR 8 — for PR 3, verify only that the entity row exists)

- [ ] **Admin-merge only after green.**

---

## Task 4 — PR 4: Point upload extraction + seeders at `SavingsStore`

**PR title:** `refactor(savings): point upload extraction + seeders at SavingsStore`

**Files:**
- Modify: `app/Services/Documents/DocumentProcessor.php` (line 404)
- Modify: `app/Http/Controllers/Api/PreviewController.php` (line 480)
- Modify: `database/seeders/PreviewUserSeeder.php` (line 825)
- Modify: `database/seeders/ChrisUserSeeder.php` (line 194 — `updateOrCreate`)
- Modify: `app/Services/Stores/Normalisers/SavingsAccountNormaliser.php` (add `fromUpload` method)
- Modify: `app/Services/Stores/SavingsStore.php` (add `updateOrCreate` method for seeder use)
- Modify: `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` (remove `DocumentProcessor`, `PreviewController`, both seeders)

### Step 4.1: Write the failing fromUpload test

- [ ] **Add to `tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php`:**

```php
describe('SavingsAccountNormaliser::fromUpload', function () {
    it('maps a document-extraction shape to canonical', function () {
        $extraction = [
            'account_name' => 'NatWest current account',
            'institution' => 'NatWest',
            'current_balance' => 4250.55,
            'account_type' => 'easy_access',
            'source_document_id' => 42,    // upload-only metadata
        ];

        $canonical = (new SavingsAccountNormaliser)->fromUpload($extraction);

        expect($canonical['account_name'])->toBe('NatWest current account');
        expect((float) $canonical['current_balance'])->toBe(4250.55);
        expect($canonical['ownership_type'])->toBe('individual');
        expect($canonical['country'])->toBe('United Kingdom');
        // source_document_id is dropped — it is not part of the SavingsAccount table
        expect($canonical)->not->toHaveKey('source_document_id');
    });
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php --filter=fromUpload
```

Expected: FAIL — `fromUpload` not defined.

### Step 4.2: Implement `SavingsAccountNormaliser::fromUpload`

- [ ] **Add to the normaliser:**

```php
/**
 * Map document-extraction output to the canonical-array shape.
 * Document extraction never produces ownership info, so we default
 * to individual ownership at 100% — the user can edit afterwards.
 * Source-document linkage (e.g. source_document_id) is handled by
 * the upload controller after the store call returns the account.
 */
public function fromUpload(array $extraction): array
{
    $canonical = [
        'account_name' => $extraction['account_name'] ?? $extraction['institution'] ?? 'Imported account',
        'account_type' => $extraction['account_type'] ?? 'easy_access',
        'institution' => $extraction['institution'] ?? ($extraction['account_name'] ?? null),
        'current_balance' => (float) ($extraction['current_balance'] ?? 0),
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => $extraction['country'] ?? 'United Kingdom',
    ];

    foreach (['interest_rate', 'is_isa', 'is_emergency_fund', 'access_type'] as $optional) {
        if (array_key_exists($optional, $extraction)) {
            $canonical[$optional] = $extraction[$optional];
        }
    }

    // ISA → UK enforced
    if (! empty($canonical['is_isa'])) {
        $canonical['country'] = 'United Kingdom';
    }

    return $canonical;
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsAccountNormaliserTest.php
```

Expected: PASS.

### Step 4.3: Write the failing SavingsStore::updateOrCreate test

- [ ] **Add to `tests/Unit/Services/Stores/SavingsStoreTest.php`:**

```php
it('SavingsStore::updateOrCreate inserts when no match exists', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->updateOrCreate(
        match: ['account_name' => 'Chris Cash ISA'],
        data: ['current_balance' => 5000, 'account_type' => 'cash_isa'],
        user: $user,
        source: IngestSource::SEEDER,
    );

    expect(SavingsAccount::count())->toBe(1);
    expect($account->account_name)->toBe('Chris Cash ISA');
});

it('SavingsStore::updateOrCreate updates when match exists', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Chris Cash ISA',
        'current_balance' => 1000,
    ]);

    $store->updateOrCreate(
        match: ['account_name' => 'Chris Cash ISA'],
        data: ['current_balance' => 5000],
        user: $user,
        source: IngestSource::SEEDER,
    );

    expect(SavingsAccount::count())->toBe(1);
    expect((float) SavingsAccount::first()->current_balance)->toBe(5000.00);
});
```

- [ ] **Run, confirm fails on missing method:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsStoreTest.php --filter=updateOrCreate
```

Expected: FAIL.

### Step 4.4: Implement `SavingsStore::updateOrCreate`

- [ ] **Add to the store:**

```php
public function updateOrCreate(array $match, array $data, User $user, IngestSource $source): SavingsAccount
{
    $existing = SavingsAccount::where('user_id', $user->id)
        ->where($match)
        ->first();

    if ($existing) {
        return $this->update($existing->id, $data, $user, $source);
    }

    return $this->create(array_merge($match, $data), $user, $source);
}
```

- [ ] **Run and confirm pass:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/SavingsStoreTest.php --filter=updateOrCreate
```

Expected: PASS.

### Step 4.5: Refactor `DocumentProcessor.php` line 404

- [ ] **Read the existing context (lines 395-415) first to understand the loop. Then replace the `SavingsAccount::create($mapped)` line with:**

```php
$model = app(\App\Services\Stores\SavingsStore::class)->create(
    app(\App\Services\Stores\Normalisers\SavingsAccountNormaliser::class)->fromUpload($mapped),
    $user,
    \App\Services\Stores\IngestSource::UPLOAD
);
```

(`$user` is in scope inside `DocumentProcessor` — verify by reading the surrounding method. If not, the closest enclosing method has the `$user` parameter; pass it in.)

### Step 4.6: Refactor `PreviewController.php` line 480

- [ ] **Replace `SavingsAccount::create(array_merge($account, [...]))` with:**

```php
app(\App\Services\Stores\SavingsStore::class)->create(
    app(\App\Services\Stores\Normalisers\SavingsAccountNormaliser::class)->fromForm(array_merge($account, [...])),
    $previewUser,
    \App\Services\Stores\IngestSource::SEEDER
);
```

(Verify the variable name for the preview user by reading lines 470-490.)

### Step 4.7: Refactor `database/seeders/PreviewUserSeeder.php` line 825

- [ ] **Replace `SavingsAccount::create([...])` with:**

```php
app(\App\Services\Stores\SavingsStore::class)->create(
    app(\App\Services\Stores\Normalisers\SavingsAccountNormaliser::class)->fromForm([...]),
    $user,    // the persona user being seeded — confirm the variable name
    \App\Services\Stores\IngestSource::SEEDER
);
```

### Step 4.8: Refactor `database/seeders/ChrisUserSeeder.php` line 194

- [ ] **Replace `SavingsAccount::updateOrCreate([match], [values])` with:**

```php
app(\App\Services\Stores\SavingsStore::class)->updateOrCreate(
    match: [/* the existing match array */],
    data: [/* the existing values array */],
    user: $chris,
    source: \App\Services\Stores\IngestSource::SEEDER,
);
```

### Step 4.9: Run seeders end-to-end in a sandboxed DB to confirm parity

- [ ] **Test the seeders directly:**

```bash
cd /Users/CSJ/Desktop/fynla && php artisan db:seed --class=ChrisUserSeeder --force
cd /Users/CSJ/Desktop/fynla && php artisan tinker --execute="echo \App\Models\SavingsAccount::where('user_id', \App\Models\User::where('email','chris@fynla.org')->first()->id)->count();"
```

Expected: same count as before the refactor (CSJ's profile has 2 savings accounts at planning time — confirm against the seeder source).

```bash
cd /Users/CSJ/Desktop/fynla && php artisan db:seed --class=PreviewUserSeeder --force
cd /Users/CSJ/Desktop/fynla && php artisan tinker --execute="echo \App\Models\SavingsAccount::whereHas('user', fn(\$q) => \$q->where('is_preview_user', true))->count();"
```

Expected: same count as before.

### Step 4.10: Update arch test allowlist

- [ ] **Edit `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php`. Delete:**

```
'App\Services\Documents\DocumentProcessor',
'App\Http\Controllers\Api\PreviewController',
'Database\Seeders\PreviewUserSeeder',
'Database\Seeders\ChrisUserSeeder',
```

### Step 4.11: Add a feature test for the upload path

- [ ] **Create `tests/Feature/Stores/SavingsUploadIngestTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Documents\DocumentProcessor;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('DocumentProcessor persists savings extraction via SavingsStore with IngestSource::UPLOAD', function () {
    $user = User::factory()->create();
    $processor = app(DocumentProcessor::class);

    // Construct the minimal extraction payload the processor would normally
    // receive from the OCR layer.
    $extraction = [
        'entity_type' => 'savings_account',
        'mapped' => [
            'account_name' => 'Barclays Cash ISA',
            'institution' => 'Barclays',
            'account_type' => 'cash_isa',
            'current_balance' => 12000,
            'is_isa' => true,
        ],
    ];

    // Drive the processor through whatever its public entry point is — adapt
    // this line to the actual method name. The assertion is that a
    // SavingsAccount row exists after with the correct user_id.
    // $processor->process(...);

    // For the planning step, write the assertion in terms of the public API
    // the processor exposes for the savings flow.
    $this->markTestIncomplete('Adapt to DocumentProcessor public API once read');
});
```

Note for the engineer: the public-API shape of `DocumentProcessor` needs a read pass to write the test concretely — line 404 is inside a private helper. Once read, expand this test to drive the actual entry point and assert the row is persisted via the store.

### Step 4.12: Run full suite + commit + PR + csjones smoke

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

- [ ] **Commit:**

```bash
cd /Users/CSJ/Desktop/fynla && git add -A
cd /Users/CSJ/Desktop/fynla && git commit -m "$(cat <<'EOF'
refactor(savings): point upload + seeders at SavingsStore

Pass 1 / PR 4. The last four direct-write sites for SavingsAccount
go through the store:

- DocumentProcessor (upload OCR path) → IngestSource::UPLOAD
- PreviewController (persona seeding) → IngestSource::SEEDER
- PreviewUserSeeder + ChrisUserSeeder → IngestSource::SEEDER

SavingsStore gains updateOrCreate to match ChrisUserSeeder semantics.
SavingsAccountNormaliser gains fromUpload.

Boundary allowlist shrinks to read-only consumers + observers — PR 5
removes the remaining read sites.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Push, open PR `feat/savings-store-pr4` → `dev`. csjones smoke:**
  1. Login as a test user (not preview)
  2. Open Statement Upload → upload a bank-statement PDF
  3. Wait for OCR completion → assert a new SavingsAccount appears in `/savings`
  4. Switch to a preview persona → confirm preview-user savings still load correctly (regression check on seeders)
  5. Admin-merge after green.

---

## Task 5 — PR 5: Point read consumers at `SavingsStore`

**PR title:** `refactor(savings): point read consumers at SavingsStore`

**Auto-split rule (§15.1):** If the cumulative diff for this task exceeds ~500 lines (sum of additions across all files), the engineer **must** split into sub-PRs along the cluster lines below. Each sub-PR carries its own arch-test allowlist edit. **No consult needed for the split.**

**Cluster groupings (in suggested merge order):**

| Sub-PR | Cluster | Files |
|--------|---------|-------|
| PR 5a | Net-worth + mobile dashboard | `MobileDashboardAggregator`, `CrossModuleAssetAggregator` |
| PR 5b | Estate / IHT | `EstateAssetAggregatorService`, `EstateActionDefinitionService`, `LetterToSpouseService` |
| PR 5c | Plans + Retirement | `BasePlanService`, `GoalPlanService`, `RetirementPlanService`, `SavingsPlanService`, `InvestmentPlanService`, `RetirementStrategyService`, `RetirementIncomeService` |
| PR 5d | Tax strategies | `JointSavingsStrategy`, `AssetShiftingBundleStrategy`, `PensionAACarryForwardStrategy`, `IsaTopUpStrategy`, `TaxOptimisationService`, `TaxStrategyMath`, `TaxActionDefinitionService` |
| PR 5e | Investment ISA consumers | `ISAAllowanceOptimizer`, `TaxOptimizationAnalyzer`, `UserContextBuilder` |
| PR 5f | Coordination + Goals | `HouseholdPlanningService`, `CashFlowCoordinator`, `LifeEventAllocationService` |
| PR 5g | AI prompt + profile | `AdvicePromptBuilder`, `DuplicateAcknowledgement`, `ProfileCompletenessChecker`, `AssetCaptureEntityExtractor` (reads only) |
| PR 5h | Agents + savings-internal | `SavingsAgent`, `InvestmentAgent`, `CoordinatingAgent` (read calls), `ISATracker`, `SavingsActionDefinitionService`, `Goal` |

### Step 5.1 — per-file migration recipe (apply to every file in the cluster)

The mechanical change in every file is:

**Before:**
```php
SavingsAccount::forUserOrJoint($user->id)->get()
// or
SavingsAccount::where('user_id', $user->id)->...->get()
```

**After:**
```php
app(\App\Services\Stores\SavingsStore::class)->forUser($user)
```

If a consumer needs a single account by id:

```php
app(\App\Services\Stores\SavingsStore::class)->find($id, $user)
```

If a consumer needs a filtered subset (e.g. ISA only), add a method to `SavingsStore`:

```php
public function isaAccountsFor(User $user): Collection
{
    return SavingsAccount::forUserOrJoint($user->id)->where('is_isa', true)->get();
}
```

The arch test allows reads from anywhere that doesn't *mutate* — but the **goal** of pass 1 is to also route reads through the store, so consumers get a stable contract and can read canonical derived columns added in PR 6. So the recipe is: every direct query of `SavingsAccount` from a consumer file becomes a store call.

### Step 5.2 — TDD for each cluster

For each cluster:

- [ ] **Write a feature test** in `tests/Feature/Stores/SavingsReadConsumerParityTest.php` that asserts the consumer returns the same data after refactor as before. Snapshot or numerical comparison — depending on the consumer.

Example for the net-worth cluster:

```php
it('MobileDashboardAggregator returns identical savings totals before and after store migration', function () {
    $user = User::factory()->create();
    SavingsAccount::factory(3)->create(['user_id' => $user->id, 'current_balance' => 1000]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 5000, 'is_isa' => true]);

    $aggregator = app(MobileDashboardAggregator::class);
    $data = $aggregator->aggregate($user);

    expect($data['savings']['total_value'])->toBe(8000.00);
    expect($data['savings']['isa_total'])->toBe(5000.00);
});
```

- [ ] **Run, refactor the consumer, re-run, confirm green.**

- [ ] **Remove the consumer file from the arch-test allowlist.**

### Step 5.3 — Per-cluster commit

Commit each cluster as its own commit (or its own sub-PR if the split rule fires):

```bash
git commit -m "refactor(savings): point <cluster> at SavingsStore reads (PR 5x)"
```

### Step 5.4 — End-of-PR-5 full suite + csjones

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

- [ ] **csjones browser smoke (mandatory for PR 5 / each sub-PR):**
  1. Login → dashboard → confirm savings totals match a manual SUM of the DB.
  2. Navigate to `/estate` → confirm savings show up in net-worth correctly.
  3. Open a retirement plan → confirm savings included in retirement income projection (if `include_in_retirement = true`).
  4. Run a what-if scenario that mutates savings — confirm results match pre-refactor baseline.
  5. Open Fyn chat → "What's in my Halifax cash ISA?" → confirm AdvicePromptBuilder reads via store and Fyn answers correctly.

### Step 5.5 — Notes for the engineer

- **Observers stay on the allowlist** — they read+mutate but they are on the spec §14.2 permanent allowlist. Confirm the SavingsAccount observers (`SavingsAccountGoalObserver`, `SavingsAccountRiskObserver`) don't perform any mutation that should funnel through the store. If they do, route through the store and document the exemption.
- **`Goal` model**: Has a `savingsAccounts()` relationship. The arch test should treat the relationship method itself as allowed (it doesn't mutate). The migration here is: if any consumer uses `$goal->savingsAccounts` for read, leave as-is — reading a relationship is not a forbidden operation. Only direct `SavingsAccount::query()` calls in `Goal` move to the store. Re-grep to verify before changes.
- **`AssetCaptureEntityExtractor`** reads `SavingsAccount::query()` at line 226 to do duplicate checks. Migrate to `app(SavingsStore::class)->forUser($user)` filtered for name match.

---

## Task 6 — PR 6: Materialise canonical derived columns + snapshot table

**PR title:** `feat(savings): materialise canonical derived columns + snapshot table`

**Files:**
- Create: `database/migrations/2026_05_15_100000_add_derived_columns_to_savings_accounts.php`
- Create: `database/migrations/2026_05_15_100001_create_savings_account_value_snapshots_table.php`
- Create: `app/Models/SavingsAccountValueSnapshot.php`
- Create: `app/Services/Stores/Snapshots/SnapshotPolicy.php`
- Create: `app/Services/Stores/Snapshots/SnapshotPolicies.php`
- Modify: `app/Services/Stores/SavingsStore.php` (add `recalculateDerived`, `snapshotIfPolicySays`, emit events)
- Modify: `app/Models/SavingsAccount.php` (only adds `protected $casts` entries for new decimal columns — no other model changes)
- Create: `app/Services/Stores/Recalc/SavingsAccountDerivedColumnCalculator.php`
- Modify: `tests/Unit/Services/Stores/SavingsStoreTest.php` (new derived-column assertions)
- Create: `tests/Unit/Services/Stores/Snapshots/SnapshotPolicyTest.php`

### Step 6.1: Write the failing snapshot-policy test

- [ ] **Create `tests/Unit/Services/Stores/Snapshots/SnapshotPolicyTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Services\Stores\Snapshots\SnapshotPolicy;

it('policy fires when threshold predicate returns true', function () {
    $policy = new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => abs($new - $old) > 100,
        retentionDays: 2555,
        surfacingWindowDays: ['free' => 90, 'tier1' => 365, 'tier2' => 1825, 'tier3' => 2555],
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );

    expect($policy->shouldSnapshot(1000, 1500))->toBeTrue();
    expect($policy->shouldSnapshot(1000, 1050))->toBeFalse();
});

it('policy surfacingWindowDays per tier mirrors spec §10.3', function () {
    $policy = new SnapshotPolicy(
        triggerPredicate: fn ($old, $new) => true,
        retentionDays: 2555,
        surfacingWindowDays: ['free' => 90, 'tier1' => 365, 'tier2' => 1825, 'tier3' => 2555],
        maxRowsHardCap: 5000,
        recalcCadence: 'on_change',
    );

    expect($policy->surfacingWindow('free'))->toBe(90);
    expect($policy->surfacingWindow('tier1'))->toBe(365);
    expect($policy->surfacingWindow('tier2'))->toBe(1825);
    expect($policy->surfacingWindow('tier3'))->toBe(2555);
});
```

- [ ] **Run and confirm fails:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Unit/Services/Stores/Snapshots/SnapshotPolicyTest.php
```

Expected: FAIL.

### Step 6.2: Implement SnapshotPolicy

- [ ] **Create `app/Services/Stores/Snapshots/SnapshotPolicy.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Snapshots;

use Closure;

class SnapshotPolicy
{
    public function __construct(
        public readonly Closure $triggerPredicate,
        public readonly int $retentionDays,
        /** @var array{free:int, tier1:int, tier2:int, tier3:int} */
        public readonly array $surfacingWindowDays,
        public readonly int $maxRowsHardCap,
        public readonly string $recalcCadence,
    ) {}

    public function shouldSnapshot(float|int|null $old, float|int|null $new): bool
    {
        if ($old === null) {
            return true;
        }
        return ($this->triggerPredicate)($old, $new);
    }

    public function surfacingWindow(string $tier): int
    {
        return $this->surfacingWindowDays[$tier] ?? $this->surfacingWindowDays['free'];
    }
}
```

- [ ] **Run, confirm PASS.**

### Step 6.3: Implement SnapshotPolicies factory + Savings policies

- [ ] **Create `app/Services/Stores/Snapshots/SnapshotPolicies.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Snapshots;

class SnapshotPolicies
{
    private const TIER_WINDOW = [
        'free' => 90,
        'tier1' => 365,
        'tier2' => 1825,
        'tier3' => 2555,
    ];

    private const RETENTION_DAYS = 2555;

    public static function savingsAccountBalance(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && (abs($new - $old) > 100 || ($old > 0 && abs($new - $old) / $old > 0.01)),
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: self::TIER_WINDOW,
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public static function savingsAnnualInterestProjected(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) > 10,
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: self::TIER_WINDOW,
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }

    public static function savingsIsaAllowanceUsedPct(): SnapshotPolicy
    {
        return new SnapshotPolicy(
            triggerPredicate: fn ($old, $new) => $old !== null && abs($new - $old) > 1.0,
            retentionDays: self::RETENTION_DAYS,
            surfacingWindowDays: self::TIER_WINDOW,
            maxRowsHardCap: 5000,
            recalcCadence: 'on_change',
        );
    }
}
```

### Step 6.4: Create the migrations

- [ ] **Create `database/migrations/2026_05_15_100000_add_derived_columns_to_savings_accounts.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->decimal('balance_gbp', 12, 2)->nullable()->after('current_balance');
            $table->timestamp('balance_gbp_calculated_at')->nullable()->after('balance_gbp');

            $table->decimal('annual_interest_projected_gbp', 12, 2)->nullable()->after('interest_rate');
            $table->timestamp('annual_interest_projected_gbp_calculated_at')->nullable()->after('annual_interest_projected_gbp');

            $table->decimal('isa_allowance_used_pct', 5, 2)->nullable()->after('isa_subscription_amount');
            $table->timestamp('isa_allowance_used_pct_calculated_at')->nullable()->after('isa_allowance_used_pct');
        });
    }

    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'balance_gbp', 'balance_gbp_calculated_at',
                'annual_interest_projected_gbp', 'annual_interest_projected_gbp_calculated_at',
                'isa_allowance_used_pct', 'isa_allowance_used_pct_calculated_at',
            ]);
        });
    }
};
```

- [ ] **Create `database/migrations/2026_05_15_100001_create_savings_account_value_snapshots_table.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('savings_account_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_account_id')->constrained('savings_accounts')->cascadeOnDelete();
            $table->string('column_name', 64);                 // balance_gbp | annual_interest_projected_gbp | ...
            $table->decimal('value', 14, 2);
            $table->char('currency', 3)->default('GBP');
            $table->decimal('value_gbp', 14, 2)->nullable();
            $table->timestamp('taken_at');
            $table->string('trigger_reason', 64);              // 'create' | 'update' | 'recalc_daily'
            $table->string('ingest_source', 16);               // mirrors IngestSource enum
            $table->timestamps();
            $table->index(['savings_account_id', 'column_name', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_account_value_snapshots');
    }
};
```

### Step 6.5: Create the snapshot model

- [ ] **Create `app/Models/SavingsAccountValueSnapshot.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsAccountValueSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'savings_account_id', 'column_name', 'value', 'currency', 'value_gbp',
        'taken_at', 'trigger_reason', 'ingest_source',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'value_gbp' => 'decimal:2',
        'taken_at' => 'datetime',
    ];

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
    }
}
```

### Step 6.6: Add `protected $casts` entries to SavingsAccount

- [ ] **Edit `app/Models/SavingsAccount.php` — add to the `$casts` array:**

```php
'balance_gbp' => 'decimal:2',
'balance_gbp_calculated_at' => 'datetime',
'annual_interest_projected_gbp' => 'decimal:2',
'annual_interest_projected_gbp_calculated_at' => 'datetime',
'isa_allowance_used_pct' => 'decimal:2',
'isa_allowance_used_pct_calculated_at' => 'datetime',
```

Add the new keys to `$fillable` too.

### Step 6.7: Implement the derived-column calculator

- [ ] **Create `app/Services/Stores/Recalc/SavingsAccountDerivedColumnCalculator.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Models\SavingsAccount;
use App\Services\Tax\TaxConfigService;

class SavingsAccountDerivedColumnCalculator
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /** @return array{balance_gbp:float, annual_interest_projected_gbp:?float, isa_allowance_used_pct:?float} */
    public function calculate(SavingsAccount $account): array
    {
        // For pass 1, all savings stored in GBP — currency conversion lands in
        // sub-project's later passes (currency-rate ref data). So balance_gbp == current_balance.
        $balanceGbp = (float) $account->current_balance;

        $annualInterestGbp = null;
        if ($account->interest_rate !== null) {
            $annualInterestGbp = round($balanceGbp * ((float) $account->interest_rate / 100), 2);
        }

        $isaAllowanceUsedPct = null;
        if ($account->is_isa) {
            $isaAllowance = $this->taxConfig->getIsaAllowance(); // £20,000 currently
            if ($isaAllowance > 0 && $account->isa_subscription_amount !== null) {
                $isaAllowanceUsedPct = round((float) $account->isa_subscription_amount / $isaAllowance * 100, 2);
            }
        }

        return [
            'balance_gbp' => $balanceGbp,
            'annual_interest_projected_gbp' => $annualInterestGbp,
            'isa_allowance_used_pct' => $isaAllowanceUsedPct,
        ];
    }
}
```

(Confirm `TaxConfigService::getIsaAllowance()` returns £20,000 — if the method name differs, adjust. CLAUDE.md Rule #3 forbids hardcoding.)

### Step 6.8: Wire recalc into SavingsStore

- [ ] **Edit `app/Services/Stores/SavingsStore.php` — inject calculator, call from `create` and `update` inside the transaction. Add `recalculateDerived` private method:**

```php
public function __construct(
    private readonly SavingsAccountNormaliser $normaliser,
    private readonly TierGate $tierGate,
    private readonly \App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator $derivedCalc,
) {}

// ...
// Inside create(), after the new $account = SavingsAccount::create(...):
$this->recalculateDerived($account, IngestSource $source, reason: 'create');

// Inside update(), after $account->update($data):
$this->recalculateDerived($account, $source, reason: 'update');
```

- [ ] **Add the recalc method:**

```php
private function recalculateDerived(SavingsAccount $account, IngestSource $source, string $reason): void
{
    $derived = $this->derivedCalc->calculate($account);
    $now = now();

    $oldValues = [
        'balance_gbp' => $account->balance_gbp,
        'annual_interest_projected_gbp' => $account->annual_interest_projected_gbp,
        'isa_allowance_used_pct' => $account->isa_allowance_used_pct,
    ];

    $account->fill([
        'balance_gbp' => $derived['balance_gbp'],
        'balance_gbp_calculated_at' => $now,
        'annual_interest_projected_gbp' => $derived['annual_interest_projected_gbp'],
        'annual_interest_projected_gbp_calculated_at' => $now,
        'isa_allowance_used_pct' => $derived['isa_allowance_used_pct'],
        'isa_allowance_used_pct_calculated_at' => $now,
    ])->save();

    // Snapshot per policy
    $policies = [
        'balance_gbp' => \App\Services\Stores\Snapshots\SnapshotPolicies::savingsAccountBalance(),
        'annual_interest_projected_gbp' => \App\Services\Stores\Snapshots\SnapshotPolicies::savingsAnnualInterestProjected(),
        'isa_allowance_used_pct' => \App\Services\Stores\Snapshots\SnapshotPolicies::savingsIsaAllowanceUsedPct(),
    ];

    foreach ($policies as $column => $policy) {
        if (! $policy->shouldSnapshot($oldValues[$column], $derived[$column])) {
            continue;
        }
        \App\Models\SavingsAccountValueSnapshot::create([
            'savings_account_id' => $account->id,
            'column_name' => $column,
            'value' => $derived[$column] ?? 0,
            'currency' => 'GBP',
            'value_gbp' => $derived[$column],
            'taken_at' => $now,
            'trigger_reason' => $reason,
            'ingest_source' => $source->value,
        ]);
    }
}
```

### Step 6.9: Add the assertion test

- [ ] **Add to `tests/Unit/Services/Stores/SavingsStoreTest.php`:**

```php
it('SavingsStore::create materialises balance_gbp and writes initial snapshot', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Halifax', 'current_balance' => 1000, 'interest_rate' => 4.0,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    expect((float) $account->balance_gbp)->toBe(1000.00);
    expect((float) $account->annual_interest_projected_gbp)->toBe(40.00);
    expect($account->balance_gbp_calculated_at)->not->toBeNull();

    expect(\App\Models\SavingsAccountValueSnapshot::where('savings_account_id', $account->id)->count())
        ->toBeGreaterThanOrEqual(2); // balance + interest snapshots
});

it('SavingsStore::update fires snapshot only when policy threshold exceeded', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Halifax', 'current_balance' => 1000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    $initialSnapshotCount = \App\Models\SavingsAccountValueSnapshot::where('savings_account_id', $account->id)->count();

    // Sub-threshold change — no new snapshot
    $store->update($account->id, ['current_balance' => 1020], $user, IngestSource::FORM);

    expect(\App\Models\SavingsAccountValueSnapshot::where('savings_account_id', $account->id)->count())
        ->toBe($initialSnapshotCount);

    // Super-threshold change — snapshot fires
    $store->update($account->id, ['current_balance' => 5000], $user, IngestSource::FORM);

    expect(\App\Models\SavingsAccountValueSnapshot::where('savings_account_id', $account->id)->count())
        ->toBeGreaterThan($initialSnapshotCount);
});
```

- [ ] **Run, confirm pass.**

### Step 6.10: Backfill existing rows

- [ ] **Create `app/Console/Commands/BackfillSavingsDerivedColumns.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SavingsAccount;
use App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator;
use Illuminate\Console\Command;

class BackfillSavingsDerivedColumns extends Command
{
    protected $signature = 'savings:backfill-derived';
    protected $description = 'One-off backfill of canonical derived columns for existing SavingsAccount rows';

    public function handle(SavingsAccountDerivedColumnCalculator $calc): int
    {
        SavingsAccount::chunkById(200, function ($chunk) use ($calc) {
            foreach ($chunk as $account) {
                $derived = $calc->calculate($account);
                $now = now();
                $account->forceFill([
                    'balance_gbp' => $derived['balance_gbp'],
                    'balance_gbp_calculated_at' => $now,
                    'annual_interest_projected_gbp' => $derived['annual_interest_projected_gbp'],
                    'annual_interest_projected_gbp_calculated_at' => $now,
                    'isa_allowance_used_pct' => $derived['isa_allowance_used_pct'],
                    'isa_allowance_used_pct_calculated_at' => $now,
                ])->saveQuietly();
            }
        });

        $this->info('Backfill complete.');
        return self::SUCCESS;
    }
}
```

- [ ] **Run migration + backfill:**

```bash
cd /Users/CSJ/Desktop/fynla && php artisan migrate
cd /Users/CSJ/Desktop/fynla && php artisan savings:backfill-derived
```

- [ ] **Add a feature test:**

```php
// tests/Feature/Savings/SavingsDerivedColumnsBackfillTest.php
it('savings:backfill-derived populates derived columns on legacy rows', function () {
    SavingsAccount::factory()->create(['current_balance' => 5000, 'interest_rate' => 3.0]);

    artisan('savings:backfill-derived')->assertSuccessful();

    $account = SavingsAccount::first();
    expect((float) $account->balance_gbp)->toBe(5000.00);
    expect((float) $account->annual_interest_projected_gbp)->toBe(150.00);
});
```

### Step 6.11: Run full suite + commit + PR + csjones smoke

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

- [ ] **Commit + PR + browser smoke. The csjones smoke for PR 6:**
  1. Deploy migration + backfill command to csjones.
  2. Run `php artisan migrate && php artisan savings:backfill-derived` on csjones.
  3. Login → /savings → verify the listing renders correctly (no errors due to new columns).
  4. Inspect the DB via `php artisan tinker`: confirm `balance_gbp` and `balance_gbp_calculated_at` populated on all existing rows.
  5. Open Fyn chat → create a new savings account → verify a snapshot row is written: `\App\Models\SavingsAccountValueSnapshot::latest()->first()`.

---

## Task 7 — PR 7: Tier-cap enforcement at store level

**PR title:** `feat(savings): tier-cap enforcement at store level`

**Files:**
- Modify: `app/Services/Stores/SavingsStore.php` (the `canCreate` check is already wired from PR 1 — this PR enables a non-permissive default for the savings entity specifically)
- Create: `app/Services/Stores/StaticTierGate.php` (interim impl with hardcoded sub-project-2 defaults from spec §13)
- Modify: `app/Providers/AppServiceProvider.php` (bind `TierGate` to `StaticTierGate` instead of `PermissiveTierGate`)
- Create: `tests/Unit/Services/Stores/StaticTierGateTest.php`
- Create: `tests/Feature/Stores/SavingsTierCapTest.php`

### Step 7.1: Write the failing tier-cap test

- [ ] **Create `tests/Feature/Stores/SavingsTierCapTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('refuses to create a 4th savings account for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']); // adjust to actual user tier column
    $store = app(SavingsStore::class);

    SavingsAccount::factory(3)->create(['user_id' => $user->id]);

    $payload = ['account_name' => 'Fourth', 'current_balance' => 100, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'UK'];

    expect(fn () => $store->create($payload, $user, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);

    expect(SavingsAccount::count())->toBe(3);
});

it('allows unlimited for tier1+', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(SavingsStore::class);

    SavingsAccount::factory(10)->create(['user_id' => $user->id]);

    $payload = ['account_name' => 'Eleventh', 'current_balance' => 100, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'UK'];

    $store->create($payload, $user, IngestSource::FORM);

    expect(SavingsAccount::count())->toBe(11);
});
```

- [ ] **Run, confirm fails (Permissive gate is still wired).**

### Step 7.2: Implement StaticTierGate

- [ ] **Create `app/Services/Stores/StaticTierGate.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\User;

/**
 * Interim TierGate impl with hardcoded sub-project-2 defaults per spec §13.
 * Sub-project 2 will replace this with a database-backed implementation
 * that reads from the freemium tier configuration table.
 */
class StaticTierGate implements TierGate
{
    private const LIMITS = [
        'savings_account' => ['free' => 3, 'tier1' => null, 'tier2' => null, 'tier3' => null],
    ];

    public function canCreate(User $user, string $entityKey, int $currentCount): bool
    {
        $hard = $this->hardLimit($user, $entityKey);
        if ($hard === null) return true;
        return $currentCount < $hard;
    }

    public function softLimit(User $user, string $entityKey): ?int
    {
        return $this->hardLimit($user, $entityKey); // soft == hard until sub-project 2
    }

    public function hardLimit(User $user, string $entityKey): ?int
    {
        $tier = $this->resolveTier($user);
        return self::LIMITS[$entityKey][$tier] ?? null;
    }

    private function resolveTier(User $user): string
    {
        // Adjust to whatever User exposes for tier in pass-1 reality. If User
        // doesn't have a tier column yet, default to 'free' for everyone.
        return $user->tier ?? 'free';
    }
}
```

### Step 7.3: Rebind in AppServiceProvider

- [ ] **Replace the binding in `app/Providers/AppServiceProvider.php`:**

```php
$this->app->bind(
    \App\Services\Stores\TierGate::class,
    \App\Services\Stores\StaticTierGate::class
);
```

### Step 7.4: Run + verify

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest tests/Feature/Stores/SavingsTierCapTest.php
```

Expected: PASS.

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: full suite green. **Note**: existing tests that create > 3 savings accounts may break if those test users default to `tier = free`. Update the factory to default to `tier = tier1` for tests, OR explicitly set `tier = tier1` in the affected tests. Document this decision in the commit message.

### Step 7.5: Commit + PR + csjones smoke

- [ ] **Commit + PR.** csjones smoke:
  1. Create a free-tier test user
  2. Add 3 savings accounts via Fyn chat
  3. Try to add a 4th → confirm Fyn surfaces a clear "you've reached your free-tier limit" message (the SavingsStore exception becomes a tool error envelope)
  4. Upgrade the test user (if there's an admin path) → confirm 4th account adds successfully

- [ ] **If sub-project 2's tier model isn't ready and CSJ wants to defer enforcement, this PR can ship with the binding unchanged (PermissiveTierGate) and only ship the StaticTierGate class + tests. Flag this to CSJ in the PR description and let CSJ decide. Default: ship with StaticTierGate bound.**

---

## Task 8 — PR 8: Final sweep — allowlist locked, audit ingest_source captured

**PR title:** `lock-down(savings): boundary allowlist reduced to permanent entries, audit captures ingest_source`

**Files:**
- Modify: `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` (final allowlist = permanent entries only)
- Modify: `app/Traits/Auditable.php` (capture `ingest_source` in audit row metadata — if the trait already supports custom metadata, just feed it through; otherwise add the hook)
- Modify: `app/Services/Stores/SavingsStore.php` (pass `ingest_source` through to the audit when persisting)
- Create: `tests/Feature/Stores/SavingsAuditIngestSourceTest.php`

### Step 8.1: Confirm the allowlist can be reduced

- [ ] **Run:**

```bash
cd /Users/CSJ/Desktop/fynla && grep -rln "SavingsAccount" app/ database/ 2>/dev/null
```

Cross-reference with the expected permanent allowlist:
- `App\Services\Stores\SavingsStore`
- `App\Services\Stores\Normalisers\SavingsAccountNormaliser`
- `App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator`
- `App\Observers\SavingsAccountGoalObserver`
- `App\Observers\SavingsAccountRiskObserver`
- `App\Models\SavingsAccountValueSnapshot`
- `App\Models\Goal` (relationship method only — read-only reference is allowed by Pest `arch()` semantics; verify)
- `Database\Factories\SavingsAccountFactory`
- Migrations (Pest arch tests ignore migrations by default; verify)

If any file outside this set still references `SavingsAccount`, audit it — it's either a missed read site (route through store) or a legitimate exception (add a documented comment in the allowlist).

### Step 8.2: Update arch test allowlist to the final shape

- [ ] **Edit `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php`:**

```php
arch('SavingsAccount mutations and reads only happen inside the savings canonical set')
    ->expect('App\Models\SavingsAccount')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\SavingsStore',
        'App\Services\Stores\Normalisers\SavingsAccountNormaliser',
        'App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator',
        'App\Observers\SavingsAccountGoalObserver',
        'App\Observers\SavingsAccountRiskObserver',
        'App\Models\SavingsAccountValueSnapshot',
        'Database\Factories\SavingsAccountFactory',
    ]);
```

### Step 8.3: Capture ingest_source in audit rows

- [ ] **Read `app/Traits/Auditable.php` to find the metadata extension point. Then in `SavingsStore::create()`, pass `ingest_source` via the trait's `auditWith()` (or whatever its API is):**

If the trait supports a `Model::withAuditContext(['ingest_source' => $source->value])` pattern, wrap the persist call in it. Otherwise extend the trait minimally to accept a metadata array.

- [ ] **Write the test:**

```php
// tests/Feature/Stores/SavingsAuditIngestSourceTest.php
it('SavingsStore::create writes an audit row with ingest_source', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Audit Test', 'current_balance' => 100,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'UK',
    ], $user, IngestSource::FYN_AI);

    $auditRow = \App\Models\AuditLog::where('auditable_type', SavingsAccount::class)
        ->where('auditable_id', $account->id)
        ->latest()->first();

    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('fyn_ai');
});
```

(Confirm the actual audit model name — it may be `AuditLog`, `Audit`, etc. Read `app/Traits/Auditable.php` to verify.)

### Step 8.4: Run + commit + PR + csjones smoke

```bash
cd /Users/CSJ/Desktop/fynla && ./vendor/bin/pest
```

Expected: green.

- [ ] **Commit + PR. csjones smoke for PR 8:**
  1. Login → /savings → create an account via UI → verify `AuditLog::latest()->first()->metadata['ingest_source']` is `'form'`
  2. Open Fyn → "Add my Halifax saver £2k" → verify audit row's `ingest_source` is `'fyn_ai'`
  3. Upload a bank statement → verify the new account's audit row has `ingest_source = 'upload'`
  4. Run the arch test: `./vendor/bin/pest --testsuite=Architecture` → confirm green with the locked-down allowlist.

- [ ] **Admin-merge after green. Pass 1 complete.**

---

## Acceptance gate for pass 1 closure

After PR 8 merges, verify pass-1 acceptance per spec §16.1 (every entity must pass before moving on):

1. [ ] **Single write path** — `arch()` test green with locked-down allowlist.
2. [ ] **Three-ingest parity** — `tests/Feature/Stores/SavingsThreeIngestParityTest.php` asserts identical rows from form / fyn / upload.
3. [ ] **Audit completeness** — every write produces an audit row with `ingest_source` populated (PR 8 test).
4. [ ] **Derived-column correctness** — `balance_gbp`, `annual_interest_projected_gbp`, `isa_allowance_used_pct` match a manual recompute in tests.
5. [ ] **Snapshot policy applied** — thresholds fire; sub-threshold updates don't snapshot.
6. [ ] **Currency round-trip** — pass 1 only ships GBP-native (full multi-currency lands in a later pass per spec §9). Confirm `balance_gbp == current_balance` and `currency = 'GBP'` are explicit defaults.
7. [ ] **Tier-cap enforcement** — `SavingsTierCapTest` green (or PR 7 deferred per CSJ decision).
8. [ ] **Browser-tested via Playwright** — every PR has a recorded csjones smoke.

Only after every box is checked does pass 2 (Reference data — `tax_configurations` admin UI fix etc.) start. See spec §15.3 for pass 2 scope. The pass-2 plan will live at `docs/superpowers/plans/2026-MM-DD-sub-project-1-pass-2-reference-data-plan.md`.

---

## Self-review notes

- **Spec coverage:** every numbered section of the spec that applies to a single entity has a corresponding task or step here. The cross-entity sections (§19 dependencies, §21 sign-off) don't generate tasks — they are sub-project-level concerns.
- **Auto-split rule:** PR 5 has the explicit cluster split in §"Cluster groupings" — engineers do not consult CSJ before splitting.
- **`AssetCaptureEntityExtractor`** is read-only and moves to read-via-store in PR 5g. It is NOT in the write allowlist after PR 3.
- **Observers stay on the allowlist forever** per spec §14.2 — they are the canonical exception to the boundary.
- **Currency native-vs-GBP storage** (spec §9) is partially deferred: pass 1 ships `balance_gbp` and the column-pair convention but the `currency_rates` ref-data table comes from pass 2. For pass 1, all balances assumed GBP-native, so `balance_gbp = current_balance`. Pass 2 will retrofit native-currency support.
- **`tier` column on User**: the plan assumes there's a tier column. If not (sub-project 2 hasn't shipped), `StaticTierGate::resolveTier` defaults to `'free'` and the test users will fail tier-cap. PR 7 has an explicit fallback: ship `StaticTierGate` as the class but keep `PermissiveTierGate` bound until sub-project 2 lands. Document this in the PR-7 description.

---

*End of plan.*
