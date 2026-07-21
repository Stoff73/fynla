---
title: Sub-Project 1, Pass 2 — Reference-Data Stores Implementation Plan
date: 2026-05-22
spec: docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
sub_project: 1 of 6 (Fynla major-overhaul series)
pass: 2 of 14 (Reference data — R1 Tax / R2 Currency / R3 Actuarial / R4 Savings rates)
branch: dev (work on short-lived feature branches per PR; flow feature → dev → main per CLAUDE.md)
status: ready for execution
---

# Reference-Data Canonical Stores Implementation Plan (SP1 Pass 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **Do not start implementation in the same session that produced this plan** — split across sessions for cache hygiene and to let CSJ review.

**Goal:** Bring all four reference-data entities (R1 Tax, R2 Currency, R3 Actuarial, R4 Savings market rates) under the SP1 canonical-store pattern. Close B2 (`tax_configurations` admin-edit gap) and ship a working admin UI for every reference table. Lock each entity's boundary with a Pest architecture test that hard-fails CI on direct model mutation.

**Architecture:** Approach A (service facade over Eloquent) per spec §4 + §12. Per-entity store classes in `App\Services\Stores\` with `IngestSource = ADMIN | SEEDER` only (no FORM / FYN_AI / UPLOAD paths for reference data — see spec §12.1). Per-request memoised read cache (§12.2) invalidated on admin writes; a shared `ReferenceDataUpdated` event lets consumers drop their own caches. Admin UI lives inside the existing `AdminPanel.vue` tab pattern (NOT a separate `/admin/reference-data/*` namespace, per spec §12.1).

**Tech Stack:** Laravel 10 · PHP 8.2 · Pest 2.36 · Eloquent · Mockery · MySQL 8 · Vue 3 + Vuex · existing `Auditable` trait + `IngestSource` enum + `TierConfigurationStore` reference impl (shipped by SP2).

---

## File Structure

### New shared infrastructure (PR 0)

| Path | Responsibility |
|------|----------------|
| `app/Events/ReferenceData/ReferenceDataUpdated.php` | Single shared event emitted by every reference-data store after a write. Consumers listen and invalidate their own caches. Carries `entity_key` (string), `entity_id` (int), `changed_keys` (array), `actor_user_id` (int). |
| `app/Services/Stores/ReferenceData/ReferenceDataStore.php` | Abstract base class for reference-data stores. Implements per-request memoised cache, audit-row emission, `ReferenceDataUpdated` event dispatch, and the `IngestSource = ADMIN | SEEDER` invariant. |
| `tests/Unit/Events/ReferenceDataUpdatedTest.php` | Unit test for the shared event shape. |
| `tests/Unit/Services/Stores/ReferenceDataStoreTest.php` | Unit test for the abstract base behaviours (memoisation, event emit, audit emit, write-source invariant). Uses a `FakeReferenceDataStore` fixture inside the test file. |

### R4 — Savings Market Rates track

| Path | Action | Responsibility |
|------|--------|----------------|
| `app/Services/Stores/SavingsMarketRateStore.php` | Create | The store facade. Wraps `SavingsMarketRate` model. |
| `app/Services/Stores/Normalisers/SavingsMarketRateNormaliser.php` | Create | Single `fromAdmin(array)` method. No fyn / upload / form. |
| `app/Http/Requests/Admin/StoreSavingsMarketRateRequest.php` | Create | FormRequest for admin POST. |
| `app/Http/Requests/Admin/UpdateSavingsMarketRateRequest.php` | Create | FormRequest for admin PATCH. |
| `app/Http/Controllers/Api/Admin/SavingsMarketRateController.php` | Create | Admin CRUD controller. Thin — validate, normalise, call store. |
| `app/Http/Resources/Admin/SavingsMarketRateResource.php` | Create | Admin response shape. |
| `app/Services/Savings/RateComparator.php` | Modify | Read consumer — switch to `SavingsMarketRateStore::query()` reads. |
| `resources/js/components/Admin/SavingsMarketRates.vue` | Create | Admin Vue panel. Inserted as a tab in `AdminPanel.vue`. |
| `resources/js/services/admin/savingsMarketRatesService.js` | Create | Axios wrapper for the admin endpoints. |
| `resources/js/store/modules/savingsMarketRates.js` | Create | Vuex module for admin state. |
| `database/seeders/SavingsMarketRatesSeeder.php` | Modify | Routes inserts through `SavingsMarketRateStore::create()` with `IngestSource::SEEDER`. |
| `routes/api.php` | Modify | Admin CRUD routes added under the `permission:admin.savings_market_rates` group. |
| `database/migrations/2026_05_22_100000_add_admin_savings_market_rates_permission.php` | Create | Adds the new permission row to the permissions seeder data. |
| `tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php` | Create | Pest `arch()` test. Hard-fails CI on direct `SavingsMarketRate` mutation outside the store. |
| `tests/Unit/Services/Stores/SavingsMarketRateStoreTest.php` | Create | Unit tests for the store. |
| `tests/Unit/Services/Stores/Normalisers/SavingsMarketRateNormaliserTest.php` | Create | Normaliser unit tests. |
| `tests/Feature/Admin/SavingsMarketRateAdminTest.php` | Create | Feature tests for the admin CRUD endpoints. |

### R3 — Actuarial Life Tables track

| Path | Action | Responsibility |
|------|--------|----------------|
| `app/Services/Stores/ActuarialLifeTableStore.php` | Create | Store facade. |
| `app/Services/Stores/Normalisers/ActuarialLifeTableNormaliser.php` | Create | `fromAdmin(array)`. |
| `app/Http/Requests/Admin/StoreActuarialLifeTableRequest.php` | Create | |
| `app/Http/Requests/Admin/UpdateActuarialLifeTableRequest.php` | Create | |
| `app/Http/Controllers/Api/Admin/ActuarialLifeTableController.php` | Create | |
| `app/Http/Resources/Admin/ActuarialLifeTableResource.php` | Create | |
| `app/Services/Estate/TrustService.php` | Modify | Read consumer — switch to store reads. |
| `app/Services/Estate/FutureValueCalculator.php` | Modify | Read consumer — switch to store reads. |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | Modify | Read consumer — switch to store reads. |
| `resources/js/components/Admin/ActuarialLifeTables.vue` | Create | Admin Vue panel. |
| `resources/js/services/admin/actuarialLifeTablesService.js` | Create | |
| `resources/js/store/modules/actuarialLifeTables.js` | Create | |
| `database/seeders/ActuarialLifeTablesSeeder.php` | Modify | Route inserts through the store with `IngestSource::SEEDER`. |
| `routes/api.php` | Modify | Admin CRUD routes added under `permission:admin.actuarial_life_tables`. |
| `database/migrations/2026_05_22_100001_add_admin_actuarial_life_tables_permission.php` | Create | Adds the new permission. |
| `tests/Architecture/StoreBoundary/ActuarialLifeTableStoreBoundaryTest.php` | Create | |
| `tests/Unit/Services/Stores/ActuarialLifeTableStoreTest.php` | Create | |
| `tests/Unit/Services/Stores/Normalisers/ActuarialLifeTableNormaliserTest.php` | Create | |
| `tests/Feature/Admin/ActuarialLifeTableAdminTest.php` | Create | |

### R1 — Tax Configuration track

| Path | Action | Responsibility |
|------|--------|----------------|
| `app/Services/Stores/TaxConfigStore.php` | Create | Store facade. Wraps the existing `TaxConfiguration` model AND audits through the existing `TaxConfigurationAudit` model (do NOT introduce a parallel audit pipeline — see spec §8.1). |
| `app/Services/Stores/Normalisers/TaxConfigNormaliser.php` | Create | `fromAdmin(array)`. |
| `app/Services/TaxConfigService.php` | Modify | The read service stays. Internally swap any direct `TaxConfiguration::*` lookups for `TaxConfigStore::query()` reads. Public read API unchanged (every consumer in `app/Constants/*` and `app/Agents/*` keeps calling `TaxConfigService`). |
| `app/Http/Controllers/Api/TaxSettingsController.php` | Modify | Direct `TaxConfiguration::create / save / update / delete` calls (lines 99–164 `update`, 165–203 `create`, 204–268 `setActive`, 359–407 `duplicate`, 409–end `delete`) all route through `TaxConfigStore::*` with `IngestSource::ADMIN`. Controllers shrink to: FormRequest → store call → return resource. |
| `resources/js/components/Admin/TaxSettings.vue` | Modify (audit + fix) | **Pre-PR B2 audit step required (PR R1.0)** to identify exactly which fields/sections don't round-trip today. Then minimum-diff fix: the spec calls this out as "fix wiring, don't rebuild". |
| `database/seeders/TaxConfigurationSeeder.php` | Modify | Route inserts/updates through `TaxConfigStore::*` with `IngestSource::SEEDER`. |
| `tests/Architecture/StoreBoundary/TaxConfigStoreBoundaryTest.php` | Create | |
| `tests/Unit/Services/Stores/TaxConfigStoreTest.php` | Create | |
| `tests/Unit/Services/Stores/Normalisers/TaxConfigNormaliserTest.php` | Create | |
| `tests/Feature/Admin/TaxConfigAdminTest.php` | Create | End-to-end feature tests covering create / update / setActive / duplicate / delete via the existing routes. **Plus a B2-closure parity test:** admin edits a tax-config value via the UI's exposed endpoints → next `TaxConfigService::get()` returns the new value, no seeder re-run required. |

### R2 — Currency Rates track (greenfield)

| Path | Action | Responsibility |
|------|--------|----------------|
| `database/migrations/2026_05_22_100002_create_currency_rates_table.php` | Create | Schema below. |
| `app/Models/CurrencyRate.php` | Create | Eloquent model. |
| `database/factories/CurrencyRateFactory.php` | Create | Factory for tests. |
| `database/seeders/CurrencyRatesSeeder.php` | Create | Seeder for default GBP-base rates (GBP↔EUR, GBP↔USD initially). Calls the store with `IngestSource::SEEDER`. |
| `app/Services/Stores/CurrencyRateStore.php` | Create | Store facade. Exposes `latestFor($fromCcy, $toCcy)` + `convert($amount, $fromCcy, $toCcy)` read API. |
| `app/Services/Stores/Normalisers/CurrencyRateNormaliser.php` | Create | `fromAdmin(array)`. |
| `app/Http/Requests/Admin/StoreCurrencyRateRequest.php` | Create | |
| `app/Http/Requests/Admin/UpdateCurrencyRateRequest.php` | Create | |
| `app/Http/Controllers/Api/Admin/CurrencyRateController.php` | Create | |
| `app/Http/Resources/Admin/CurrencyRateResource.php` | Create | |
| `resources/js/components/Admin/CurrencyRates.vue` | Create | Admin Vue panel. |
| `resources/js/services/admin/currencyRatesService.js` | Create | |
| `resources/js/store/modules/currencyRates.js` | Create | |
| `routes/api.php` | Modify | Admin CRUD routes under `permission:admin.currency_rates`. |
| `database/migrations/2026_05_22_100003_add_admin_currency_rates_permission.php` | Create | |
| `tests/Architecture/StoreBoundary/CurrencyRateStoreBoundaryTest.php` | Create | |
| `tests/Unit/Services/Stores/CurrencyRateStoreTest.php` | Create | |
| `tests/Unit/Services/Stores/Normalisers/CurrencyRateNormaliserTest.php` | Create | |
| `tests/Feature/Admin/CurrencyRateAdminTest.php` | Create | |

### Pass-wide admin nav

| Path | Action | Responsibility |
|------|--------|----------------|
| `resources/js/views/Admin/AdminPanel.vue` | Modify | Add four new tabs: "Tax Settings" (already exists — verify), "Currency Rates", "Actuarial Life Tables", "Savings Market Rates". |
| `resources/js/router/index.js` | No change | All admin pages share the `/admin` route; tabs select panels inside. |

### Untouched (deliberately)

- `app/Services/TaxConfigService.php` PUBLIC API — every read consumer in `app/Agents/*` and `app/Constants/*` keeps calling `TaxConfigService::get*()`. The store sits below the service; the service surface is preserved per spec §2.2 (no breaking changes to consumers in this sub-project).
- `app/Services/Savings/RateComparator.php` PUBLIC API — read consumers of this service (if any) continue to call its methods; only the *internal* read path swaps to the store.
- `app/Services/Estate/*` PUBLIC APIs — same principle for actuarial-table consumers.
- `app/Services/Tiers/*` and `app/Services/Stores/TierConfigurationStore.php` — out of scope; SP2 already shipped those.

---

## TDD discipline

Every task follows the TDD micro-cycle:

1. Write the failing test.
2. Run the test and confirm it fails for the *right reason*.
3. Write the minimal implementation.
4. Run the test and confirm it passes.
5. Run the broader suite (`./vendor/bin/pest` for the affected module) and confirm no regressions.
6. Commit one focused commit per `### Step` block (or batched per CSJ preference, as long as the failing-test step is in history).

**Run commands from the repo root** `/Users/CSJ/Desktop/fynla` — `vendor/` lives here.

**Browser testing law (CLAUDE.md Rule §15):** every PR ships with Playwright verification on csjones — click + fill + submit + verify DB + observe UI. No "verified by code review" claims.

**Branch flow per PR:** branch off `dev` → push → open PR `feature/xxx → dev` → admin-merge → deploy to csjones → smoke → (later) backmerge to main.

---

## Pre-pass verification (PR R1.0)

> **DO THIS FIRST.** The spec (§12.1, written 2026-05-14) claims `TaxSettings.vue` is broken — "views exist but are not wired correctly — values can't actually be edited." Between then and now (today, 2026-05-22), several commits touched tax config (`d79523d`, `300fc2a`, `441eb07`, `f646f88`). B2's scope today may be smaller than the spec implies.

**No code change. Just verification + a memory entry.**

### Step R1.0.1: Browser-verify B2's current real state

- [ ] **Log in to local dev as `chris@fynla.org` (admin):** start the dev server, hit the local Tax Settings admin panel via the existing route.

```bash
./dev.sh  # if not running
# Then in a browser at http://localhost:8000:
# - Log in as chris@fynla.org / Password1! (or whatever your local admin is)
# - Navigate to the admin panel → Tax Settings tab
```

- [ ] **For each editable field in `resources/js/components/Admin/TaxSettings.vue`, attempt to edit + save + reload:**
  - Tax year picker (effective_from / effective_to)
  - Income tax bands (personal allowance, basic-rate threshold, etc.)
  - National Insurance thresholds
  - ISA allowances
  - IHT NRB / RNRB / RNRB-taper-threshold
  - CGT annual exempt amount
  - Pension annual allowance / lifetime allowance / MPAA
  - Any "duplicate / setActive" action

- [ ] **Record which fields round-trip correctly and which don't.** A field round-trips if: edit value → save → page reload → DB row reflects the new value → `TaxConfigService::get(...)` returns the new value.

### Step R1.0.2: Write the B2 audit memo

- [ ] **Create `May/May22Updates/b2-tax-config-admin-audit-2026-05-22.md`** documenting the state. Use this template:

```markdown
---
type: audit
title: B2 — TaxSettings.vue admin-edit audit (2026-05-22)
date: 2026-05-22
spec_section: 12.1 of docs/superpowers/specs/2026-05-14-module-canonical-store-design.md
---

# B2 Status — 2026-05-22

The spec (2026-05-14 §12.1) claims `TaxSettings.vue` admin views are not wired. Today's state:

## Round-trip working (no fix needed)
- [list of fields that work]

## Round-trip broken (PR R1.5 fixes these)
- [list of fields that don't]

## Routes / controller surface present today
- TaxSettingsController has 8 methods: getCurrent, getAll, update, create, setActive, getCalculations, duplicate, delete (verified 2026-05-22)
- Routes gated by `permission:admin.tax_config`
- Audit captured via `TaxConfigurationAudit` model

## Verdict
- Spec's B2 scope today is [FULL / PARTIAL / OBSOLETE]
- Adjust pass-2 R1 track to [no change / shrink PR R1.5 / expand PR R1.5]
```

- [ ] **Commit the audit memo:**

```bash
git add May/May22Updates/b2-tax-config-admin-audit-2026-05-22.md
git commit -m "docs(audit): B2 tax-config admin-edit current state pre-pass-2"
```

This is the only output of PR R1.0. The rest of the plan continues regardless of B2's verdict; the audit just informs PR R1.5's scope.

---

## PR 0 — Shared reference-data infrastructure

**PR title:** `feat(ref-data): introduce ReferenceDataStore base + ReferenceDataUpdated event`

**Files:**
- Create: `app/Events/ReferenceData/ReferenceDataUpdated.php`
- Create: `app/Services/Stores/ReferenceData/ReferenceDataStore.php`
- Create: `tests/Unit/Events/ReferenceDataUpdatedTest.php`
- Create: `tests/Unit/Services/Stores/ReferenceDataStoreTest.php`

### Step 0.1: Write the failing ReferenceDataUpdated event test

- [ ] **Create `tests/Unit/Events/ReferenceDataUpdatedTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;

it('carries entity_key, entity_id, changed_keys, actor_user_id as readonly props', function () {
    $event = new ReferenceDataUpdated(
        entityKey: 'tax_configuration',
        entityId: 7,
        changedKeys: ['income_tax', 'isa_allowance'],
        actorUserId: 42,
    );

    expect($event->entityKey)->toBe('tax_configuration');
    expect($event->entityId)->toBe(7);
    expect($event->changedKeys)->toBe(['income_tax', 'isa_allowance']);
    expect($event->actorUserId)->toBe(42);
});

it('accepts null actor for seeder writes', function () {
    $event = new ReferenceDataUpdated(
        entityKey: 'currency_rate',
        entityId: 3,
        changedKeys: ['rate'],
        actorUserId: null,
    );

    expect($event->actorUserId)->toBeNull();
});
```

- [ ] **Run and confirm it fails:**

```bash
./vendor/bin/pest tests/Unit/Events/ReferenceDataUpdatedTest.php
```

Expected: FAIL with `class App\Events\ReferenceData\ReferenceDataUpdated not found`.

### Step 0.2: Implement the event

- [ ] **Create `app/Events/ReferenceData/ReferenceDataUpdated.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Events\ReferenceData;

use Illuminate\Foundation\Events\Dispatchable;

class ReferenceDataUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $entityKey,
        public readonly int $entityId,
        public readonly array $changedKeys,
        public readonly ?int $actorUserId,
    ) {}
}
```

- [ ] **Run and confirm pass:**

```bash
./vendor/bin/pest tests/Unit/Events/ReferenceDataUpdatedTest.php
```

Expected: PASS, 2 tests, 5 assertions.

### Step 0.3: Write the failing ReferenceDataStore base contract test

- [ ] **Create `tests/Unit/Services/Stores/ReferenceDataStoreTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Services\Stores\IngestSource;
use App\Services\Stores\ReferenceData\ReferenceDataStore;
use App\Services\Stores\Exceptions\StoreValidationException;
use Illuminate\Support\Facades\Event;

/**
 * Minimal in-memory fixture concrete subclass to exercise the base.
 * Defined inline because it's only used by these tests.
 */
class FakeRefStore extends ReferenceDataStore
{
    public array $rows = [];

    protected function entityKey(): string { return 'fake_ref'; }

    protected function persist(array $canonical, ?int $id = null): int
    {
        if ($id === null) {
            $id = count($this->rows) + 1;
            $this->rows[$id] = $canonical;
        } else {
            $this->rows[$id] = array_merge($this->rows[$id] ?? [], $canonical);
        }
        return $id;
    }

    protected function read(int $id): array
    {
        return $this->rows[$id] ?? [];
    }

    protected function delete_(int $id): void
    {
        unset($this->rows[$id]);
    }
}

beforeEach(function () {
    Event::fake([ReferenceDataUpdated::class]);
});

it('rejects writes from non-admin / non-seeder sources', function () {
    $store = new FakeRefStore;

    expect(fn () => $store->create(['k' => 'v'], IngestSource::FORM))
        ->toThrow(StoreValidationException::class);
    expect(fn () => $store->create(['k' => 'v'], IngestSource::FYN_AI))
        ->toThrow(StoreValidationException::class);
    expect(fn () => $store->create(['k' => 'v'], IngestSource::UPLOAD))
        ->toThrow(StoreValidationException::class);
});

it('accepts ADMIN and SEEDER writes', function () {
    $store = new FakeRefStore;

    $id1 = $store->create(['k' => 'v1'], IngestSource::ADMIN, actorUserId: 1);
    $id2 = $store->create(['k' => 'v2'], IngestSource::SEEDER);

    expect($id1)->toBe(1);
    expect($id2)->toBe(2);
});

it('emits ReferenceDataUpdated on create', function () {
    $store = new FakeRefStore;
    $id = $store->create(['k' => 'v'], IngestSource::ADMIN, actorUserId: 9);

    Event::assertDispatched(ReferenceDataUpdated::class, function ($e) use ($id) {
        return $e->entityKey === 'fake_ref'
            && $e->entityId === $id
            && $e->changedKeys === ['k']
            && $e->actorUserId === 9;
    });
});

it('memoises reads within the same request', function () {
    $store = new FakeRefStore;
    $id = $store->create(['k' => 'v'], IngestSource::ADMIN, actorUserId: 1);

    $first = $store->find($id);
    $store->rows[$id] = ['k' => 'modified-out-of-band'];
    $second = $store->find($id);

    // Memoised: second call returns the cached first read, not the out-of-band mutation.
    expect($second)->toBe($first);
});

it('invalidates the cache after a write', function () {
    $store = new FakeRefStore;
    $id = $store->create(['k' => 'v1'], IngestSource::ADMIN, actorUserId: 1);

    $store->find($id);                          // primes cache
    $store->update($id, ['k' => 'v2'], IngestSource::ADMIN, actorUserId: 1);

    expect($store->find($id))->toBe(['k' => 'v2']);
});
```

- [ ] **Run and confirm it fails:**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/ReferenceDataStoreTest.php
```

Expected: FAIL with `class App\Services\Stores\ReferenceData\ReferenceDataStore not found`.

### Step 0.4: Implement the abstract base

- [ ] **Create `app/Services/Stores/ReferenceData/ReferenceDataStore.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\ReferenceData;

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;

abstract class ReferenceDataStore
{
    /** Per-request memoised cache keyed by entity_id. */
    private array $cache = [];

    /** The string key identifying this entity (e.g. 'tax_configuration', 'currency_rate'). */
    abstract protected function entityKey(): string;

    /**
     * Persist a canonical row. Returns the row's id.
     * If $id is provided, this is an update; otherwise it's a create.
     */
    abstract protected function persist(array $canonical, ?int $id = null): int;

    /** Read a single row by id. Return [] if not found. */
    abstract protected function read(int $id): array;

    /** Delete a row by id. Idempotent — no error if absent. */
    abstract protected function delete_(int $id): void;

    public function create(array $canonical, IngestSource $source, ?int $actorUserId = null): int
    {
        $this->guardSource($source);
        $id = $this->persist($canonical);
        $this->cache[$id] = $canonical;

        ReferenceDataUpdated::dispatch(
            $this->entityKey(),
            $id,
            array_keys($canonical),
            $actorUserId
        );

        return $id;
    }

    public function update(int $id, array $canonical, IngestSource $source, ?int $actorUserId = null): void
    {
        $this->guardSource($source);
        $this->persist($canonical, $id);
        unset($this->cache[$id]);

        ReferenceDataUpdated::dispatch(
            $this->entityKey(),
            $id,
            array_keys($canonical),
            $actorUserId
        );
    }

    public function delete(int $id, IngestSource $source, ?int $actorUserId = null): void
    {
        $this->guardSource($source);
        $this->delete_($id);
        unset($this->cache[$id]);

        ReferenceDataUpdated::dispatch(
            $this->entityKey(),
            $id,
            ['__deleted'],
            $actorUserId
        );
    }

    public function find(int $id): array
    {
        if (! array_key_exists($id, $this->cache)) {
            $this->cache[$id] = $this->read($id);
        }
        return $this->cache[$id];
    }

    private function guardSource(IngestSource $source): void
    {
        if (! in_array($source, [IngestSource::ADMIN, IngestSource::SEEDER], true)) {
            throw new StoreValidationException(
                ['ingest_source' => "Reference-data writes only permitted from ADMIN or SEEDER (got: {$source->value})"]
            );
        }
    }
}
```

- [ ] **Run and confirm pass:**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/ReferenceDataStoreTest.php
```

Expected: PASS, 5 tests.

### Step 0.5: Verify the broader suite

- [ ] **Run the unit + architecture suites:**

```bash
./vendor/bin/pest tests/Unit tests/Architecture
```

Expected: all green (no regressions).

### Step 0.6: Commit PR 0

- [ ] **Commit + push + open PR:**

```bash
git checkout -b feat/ref-data-pr0-shared-infra
git add app/Events/ReferenceData/ReferenceDataUpdated.php \
        app/Services/Stores/ReferenceData/ReferenceDataStore.php \
        tests/Unit/Events/ReferenceDataUpdatedTest.php \
        tests/Unit/Services/Stores/ReferenceDataStoreTest.php
git commit -m "$(cat <<'EOF'
feat(ref-data): shared ReferenceDataStore base + ReferenceDataUpdated event (SP1 P2 PR0)

Spec: docs/superpowers/specs/2026-05-14-module-canonical-store-design.md §12

- ReferenceDataStore abstract base: per-request memoised cache,
  ADMIN|SEEDER-only ingest invariant, event emit on every write
- ReferenceDataUpdated event with readonly props for consumer listeners

No store implementations yet — those land in R4, R3, R1, R2 PRs.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
git push -u origin feat/ref-data-pr0-shared-infra
gh pr create --base dev --title "feat(ref-data): SP1 P2 PR0 — shared ReferenceDataStore base + event" --body "Spec §12 shared infrastructure for the four reference-data store implementations that follow. No consumer migration yet."
```

- [ ] **Wait for admin-merge by CSJ, then `git checkout dev && git pull` before starting the next PR.**

---

## R4 — Savings Market Rates track

This is the **smallest reference-data entity** (one consumer, simple data shape). It proves the pattern under reference-data constraints before applying to the more complex R3 and R1 entities.

### PR R4.1: Introduce `SavingsMarketRateStore` + arch boundary

**PR title:** `feat(ref-data): introduce SavingsMarketRateStore facade + arch boundary (SP1 P2 R4.1)`

**Files:**
- Create: `app/Services/Stores/SavingsMarketRateStore.php`
- Create: `app/Services/Stores/Normalisers/SavingsMarketRateNormaliser.php`
- Create: `tests/Unit/Services/Stores/SavingsMarketRateStoreTest.php`
- Create: `tests/Unit/Services/Stores/Normalisers/SavingsMarketRateNormaliserTest.php`
- Create: `tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php`

### Step R4.1.1: Write the failing normaliser test

- [ ] **Create `tests/Unit/Services/Stores/Normalisers/SavingsMarketRateNormaliserTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Services\Stores\Normalisers\SavingsMarketRateNormaliser;
use App\Services\Stores\Exceptions\StoreValidationException;

it('normalises a complete admin payload', function () {
    $normaliser = new SavingsMarketRateNormaliser;
    $out = $normaliser->fromAdmin([
        'product_type' => 'easy_access',
        'provider'     => 'Marcus by Goldman Sachs',
        'rate_aer'     => 4.75,
        'effective_from' => '2026-05-01',
    ]);

    expect($out)->toBe([
        'product_type' => 'easy_access',
        'provider'     => 'Marcus by Goldman Sachs',
        'rate_aer'     => 4.75,
        'effective_from' => '2026-05-01',
    ]);
});

it('rejects missing required fields', function () {
    $normaliser = new SavingsMarketRateNormaliser;
    expect(fn () => $normaliser->fromAdmin(['product_type' => 'easy_access']))
        ->toThrow(StoreValidationException::class);
});

it('rejects unknown product types', function () {
    $normaliser = new SavingsMarketRateNormaliser;
    expect(fn () => $normaliser->fromAdmin([
        'product_type' => 'bogus_type',
        'provider' => 'X',
        'rate_aer' => 1.0,
        'effective_from' => '2026-05-01',
    ]))->toThrow(StoreValidationException::class);
});

it('rejects negative rates', function () {
    $normaliser = new SavingsMarketRateNormaliser;
    expect(fn () => $normaliser->fromAdmin([
        'product_type' => 'easy_access',
        'provider' => 'X',
        'rate_aer' => -1.0,
        'effective_from' => '2026-05-01',
    ]))->toThrow(StoreValidationException::class);
});
```

- [ ] **Run and confirm fails:**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/Normalisers/SavingsMarketRateNormaliserTest.php
```

Expected: FAIL with `class App\Services\Stores\Normalisers\SavingsMarketRateNormaliser not found`.

### Step R4.1.2: Implement the normaliser

- [ ] **Read the current `SavingsMarketRate` model + migration to learn its actual field set:**

```bash
cat app/Models/SavingsMarketRate.php
cat database/migrations/2026_02_21_120001_create_savings_market_rates_table.php
```

Use the *actual* column names + types when implementing — the test above uses the canonical names; if the migration uses different ones (e.g. `rate` instead of `rate_aer`), update both the test and the normaliser to match before proceeding.

- [ ] **Create `app/Services/Stores/Normalisers/SavingsMarketRateNormaliser.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores\Normalisers;

use App\Services\Stores\Exceptions\StoreValidationException;

class SavingsMarketRateNormaliser
{
    private const ALLOWED_PRODUCT_TYPES = [
        // Mirror the canonical type list in app/Models/SavingsMarketRate.php.
        // Verify against the model before merging this PR.
        'easy_access',
        'notice',
        'fixed_term',
        'cash_isa',
        'regular_saver',
    ];

    public function fromAdmin(array $input): array
    {
        $errors = [];
        $required = ['product_type', 'provider', 'rate_aer', 'effective_from'];
        foreach ($required as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
                $errors[$field] = 'required';
            }
        }
        if ($errors) {
            throw new StoreValidationException($errors);
        }

        if (! in_array($input['product_type'], self::ALLOWED_PRODUCT_TYPES, true)) {
            throw new StoreValidationException([
                'product_type' => 'unknown product_type: ' . $input['product_type'],
            ]);
        }
        if (! is_numeric($input['rate_aer']) || (float) $input['rate_aer'] < 0.0) {
            throw new StoreValidationException(['rate_aer' => 'must be numeric and >= 0']);
        }

        return [
            'product_type' => (string) $input['product_type'],
            'provider'     => trim((string) $input['provider']),
            'rate_aer'     => (float) $input['rate_aer'],
            'effective_from' => (string) $input['effective_from'],
        ];
    }
}
```

- [ ] **Run and confirm pass:**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/Normalisers/SavingsMarketRateNormaliserTest.php
```

Expected: PASS, 4 tests.

### Step R4.1.3: Write the failing store test

- [ ] **Create `tests/Unit/Services/Stores/SavingsMarketRateStoreTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Models\SavingsMarketRate;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsMarketRateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([ReferenceDataUpdated::class]);
});

it('creates a row from an admin payload', function () {
    $store = new SavingsMarketRateStore;

    $id = $store->create([
        'product_type' => 'easy_access',
        'provider'     => 'Marcus',
        'rate_aer'     => 4.5,
        'effective_from' => '2026-05-01',
    ], IngestSource::ADMIN, actorUserId: 1);

    expect(SavingsMarketRate::find($id))->not->toBeNull();
    expect(SavingsMarketRate::find($id)->provider)->toBe('Marcus');
});

it('emits ReferenceDataUpdated on create', function () {
    $store = new SavingsMarketRateStore;

    $id = $store->create([
        'product_type' => 'easy_access',
        'provider'     => 'Marcus',
        'rate_aer'     => 4.5,
        'effective_from' => '2026-05-01',
    ], IngestSource::ADMIN, actorUserId: 1);

    Event::assertDispatched(ReferenceDataUpdated::class, fn ($e) =>
        $e->entityKey === 'savings_market_rate' && $e->entityId === $id
    );
});

it('updates a row through the store', function () {
    $store = new SavingsMarketRateStore;
    $id = $store->create([
        'product_type' => 'easy_access',
        'provider'     => 'Marcus',
        'rate_aer'     => 4.5,
        'effective_from' => '2026-05-01',
    ], IngestSource::ADMIN, actorUserId: 1);

    $store->update($id, ['rate_aer' => 5.0], IngestSource::ADMIN, actorUserId: 1);

    expect(SavingsMarketRate::find($id)->rate_aer)->toEqualWithDelta(5.0, 0.0001);
});

it('refuses FORM / FYN_AI / UPLOAD ingest sources', function () {
    $store = new SavingsMarketRateStore;

    foreach ([IngestSource::FORM, IngestSource::FYN_AI, IngestSource::UPLOAD] as $bad) {
        expect(fn () => $store->create([
            'product_type' => 'easy_access',
            'provider'     => 'X',
            'rate_aer'     => 1.0,
            'effective_from' => '2026-05-01',
        ], $bad))->toThrow(\App\Services\Stores\Exceptions\StoreValidationException::class);
    }
});

it('memoises reads within a request', function () {
    $store = new SavingsMarketRateStore;
    $id = $store->create([
        'product_type' => 'easy_access',
        'provider'     => 'Marcus',
        'rate_aer'     => 4.5,
        'effective_from' => '2026-05-01',
    ], IngestSource::ADMIN, actorUserId: 1);

    $r1 = $store->find($id);
    // mutate out-of-band
    SavingsMarketRate::where('id', $id)->update(['provider' => 'NotMarcus']);
    $r2 = $store->find($id);

    expect($r2['provider'])->toBe('Marcus');  // memoised — original value
});
```

- [ ] **Run and confirm fails:**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/SavingsMarketRateStoreTest.php
```

Expected: FAIL with `class App\Services\Stores\SavingsMarketRateStore not found`.

### Step R4.1.4: Implement the store

- [ ] **Create `app/Services/Stores/SavingsMarketRateStore.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\SavingsMarketRate;
use App\Services\Stores\Normalisers\SavingsMarketRateNormaliser;
use App\Services\Stores\ReferenceData\ReferenceDataStore;

class SavingsMarketRateStore extends ReferenceDataStore
{
    public function __construct(
        private readonly SavingsMarketRateNormaliser $normaliser = new SavingsMarketRateNormaliser
    ) {}

    protected function entityKey(): string
    {
        return 'savings_market_rate';
    }

    public function create(array $input, IngestSource $source, ?int $actorUserId = null): int
    {
        $canonical = $this->normaliser->fromAdmin($input);
        return parent::create($canonical, $source, $actorUserId);
    }

    public function update(int $id, array $input, IngestSource $source, ?int $actorUserId = null): void
    {
        // Partial updates: merge with existing then re-normalise.
        $existing = $this->find($id);
        $merged = array_merge($existing, $input);
        $canonical = $this->normaliser->fromAdmin($merged);
        parent::update($id, $canonical, $source, $actorUserId);
    }

    protected function persist(array $canonical, ?int $id = null): int
    {
        if ($id === null) {
            return SavingsMarketRate::create($canonical)->id;
        }
        SavingsMarketRate::where('id', $id)->update($canonical);
        return $id;
    }

    protected function read(int $id): array
    {
        $row = SavingsMarketRate::find($id);
        return $row ? $row->toArray() : [];
    }

    protected function delete_(int $id): void
    {
        SavingsMarketRate::where('id', $id)->delete();
    }

    /**
     * Reads — public read API for consumers.
     * Returns the currently-effective rate for a given product type.
     */
    public function currentRateFor(string $productType): ?float
    {
        $row = SavingsMarketRate::where('product_type', $productType)
            ->where('effective_from', '<=', now()->toDateString())
            ->orderByDesc('effective_from')
            ->first();
        return $row ? (float) $row->rate_aer : null;
    }
}
```

- [ ] **Run and confirm pass:**

```bash
./vendor/bin/pest tests/Unit/Services/Stores/SavingsMarketRateStoreTest.php
```

Expected: PASS, 5 tests.

### Step R4.1.5: Write the failing arch boundary test

- [ ] **Create `tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php`:**

```php
<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R4: only SavingsMarketRateStore (and explicitly allowlisted
 * call sites) may mutate the SavingsMarketRate model. Hard CI failure per
 * spec §14.1.
 *
 * Allowlist for this PR (will shrink as subsequent PRs migrate each site):
 *   - App\Services\Stores\SavingsMarketRateStore (the store itself)
 *   - App\Services\Savings\RateComparator (read consumer — never mutates; here to silence the test until PR R4.3)
 *   - database\seeders\SavingsMarketRatesSeeder (migrated in PR R4.4)
 *   - App\Http\Controllers\Api\Admin\SavingsMarketRateController (migrated in PR R4.2)
 */
arch('only SavingsMarketRateStore mutates SavingsMarketRate')
    ->expect('App\Models\SavingsMarketRate')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\SavingsMarketRateStore',
        'App\Services\Savings\RateComparator',
        'Database\Seeders\SavingsMarketRatesSeeder',
        'App\Http\Controllers\Api\Admin\SavingsMarketRateController',
    ]);
```

- [ ] **Run and confirm pass (it should pass because nothing outside the allowlist touches the model today):**

```bash
./vendor/bin/pest tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php
```

Expected: PASS.

### Step R4.1.6: Run the broader suite and commit

- [ ] **Run unit + architecture suites:**

```bash
./vendor/bin/pest tests/Unit tests/Architecture
```

Expected: all green.

- [ ] **Commit + push + open PR:**

```bash
git checkout -b feat/ref-data-r4-pr1-savings-rate-store
git add app/Services/Stores/SavingsMarketRateStore.php \
        app/Services/Stores/Normalisers/SavingsMarketRateNormaliser.php \
        tests/Unit/Services/Stores/SavingsMarketRateStoreTest.php \
        tests/Unit/Services/Stores/Normalisers/SavingsMarketRateNormaliserTest.php \
        tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php
git commit -m "$(cat <<'EOF'
feat(ref-data): introduce SavingsMarketRateStore facade + arch boundary (SP1 P2 R4.1)

Spec §3.3 R4, §12

- SavingsMarketRateStore extends ReferenceDataStore (PR0)
- ADMIN|SEEDER-only writes; FORM/FYN/UPLOAD throw StoreValidationException
- Per-request memoised reads; ReferenceDataUpdated event on every write
- Arch test enforces store boundary with explicit allowlist for the four
  call sites we'll migrate in R4.2 - R4.4 (controller, consumer, seeder)

No consumer migrated yet.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
git push -u origin feat/ref-data-r4-pr1-savings-rate-store
gh pr create --base dev --title "feat(ref-data): SP1 P2 R4.1 — SavingsMarketRateStore facade + arch boundary"
```

- [ ] **Wait for admin-merge; pull dev; move to PR R4.2.**

### PR R4.2: Build the admin CRUD endpoints + Vue panel

**PR title:** `feat(ref-data): admin CRUD for SavingsMarketRate (SP1 P2 R4.2)`

**Files:**
- Create: `app/Http/Controllers/Api/Admin/SavingsMarketRateController.php`
- Create: `app/Http/Requests/Admin/StoreSavingsMarketRateRequest.php`
- Create: `app/Http/Requests/Admin/UpdateSavingsMarketRateRequest.php`
- Create: `app/Http/Resources/Admin/SavingsMarketRateResource.php`
- Create: `database/migrations/2026_05_22_100000_add_admin_savings_market_rates_permission.php`
- Create: `resources/js/components/Admin/SavingsMarketRates.vue`
- Create: `resources/js/services/admin/savingsMarketRatesService.js`
- Create: `resources/js/store/modules/savingsMarketRates.js`
- Create: `tests/Feature/Admin/SavingsMarketRateAdminTest.php`
- Modify: `routes/api.php`
- Modify: `resources/js/views/Admin/AdminPanel.vue` (add tab)
- Modify: `resources/js/store/index.js` (register Vuex module)

### Step R4.2.1: Write the failing feature test

- [ ] **Create `tests/Feature/Admin/SavingsMarketRateAdminTest.php`:**

```php
<?php

declare(strict_types=1);

use App\Models\SavingsMarketRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The test admin user must hold the new `admin.savings_market_rates` permission.
    $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    // Permission seed happens in the per-test setup — covered by the migration in this PR.
    // If your test seeder doesn't pick it up, attach the permission explicitly here.
    Sanctum::actingAs($this->admin);
});

it('lists savings market rates', function () {
    SavingsMarketRate::factory()->count(3)->create();

    $this->getJson('/api/admin/savings-market-rates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a rate via the store', function () {
    $payload = [
        'product_type' => 'easy_access',
        'provider' => 'Marcus',
        'rate_aer' => 4.75,
        'effective_from' => '2026-05-01',
    ];

    $this->postJson('/api/admin/savings-market-rates', $payload)
        ->assertCreated()
        ->assertJsonPath('data.provider', 'Marcus')
        ->assertJsonPath('data.rate_aer', 4.75);

    $this->assertDatabaseHas('savings_market_rates', ['provider' => 'Marcus', 'rate_aer' => 4.75]);
});

it('updates a rate via the store', function () {
    $rate = SavingsMarketRate::factory()->create(['rate_aer' => 3.0]);

    $this->patchJson("/api/admin/savings-market-rates/{$rate->id}", ['rate_aer' => 5.0])
        ->assertOk()
        ->assertJsonPath('data.rate_aer', 5.0);

    $this->assertDatabaseHas('savings_market_rates', ['id' => $rate->id, 'rate_aer' => 5.0]);
});

it('deletes a rate via the store', function () {
    $rate = SavingsMarketRate::factory()->create();

    $this->deleteJson("/api/admin/savings-market-rates/{$rate->id}")->assertOk();

    $this->assertDatabaseMissing('savings_market_rates', ['id' => $rate->id]);
});

it('returns 422 for an unknown product type', function () {
    $this->postJson('/api/admin/savings-market-rates', [
        'product_type' => 'bogus',
        'provider' => 'X',
        'rate_aer' => 1.0,
        'effective_from' => '2026-05-01',
    ])->assertStatus(422);
});

it('returns 403 for non-admin users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/admin/savings-market-rates')->assertStatus(403);
});
```

- [ ] **Run and confirm fails:**

```bash
./vendor/bin/pest tests/Feature/Admin/SavingsMarketRateAdminTest.php
```

Expected: FAIL — route not defined.

### Step R4.2.2: Add the admin permission migration

- [ ] **Create `database/migrations/2026_05_22_100000_add_admin_savings_market_rates_permission.php`:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Adapt to your existing permissions schema. Two common Fynla shapes:
        //   (a) a `permissions` table with (name, description); roles linked via pivot.
        //   (b) a JSON column on `users` (`permissions`) — in that case skip the
        //       table insert and add the constant to `app/Constants/Permissions.php`.
        // The exact form depends on what `permission:admin.tax_config` middleware uses today.
        if (DB::getSchemaBuilder()->hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['name' => 'admin.savings_market_rates'],
                ['description' => 'Edit savings market rates reference data', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('permissions')) {
            DB::table('permissions')->where('name', 'admin.savings_market_rates')->delete();
        }
    }
};
```

- [ ] **Run the migration locally:**

```bash
php artisan migrate
```

### Step R4.2.3: Create FormRequests

- [ ] **Create `app/Http/Requests/Admin/StoreSavingsMarketRateRequest.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavingsMarketRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.savings_market_rates') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_type' => 'required|string',
            'provider'     => 'required|string|max:255',
            'rate_aer'     => 'required|numeric|min:0|max:25',
            'effective_from' => 'required|date',
        ];
    }
}
```

- [ ] **Create `app/Http/Requests/Admin/UpdateSavingsMarketRateRequest.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavingsMarketRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.savings_market_rates') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_type' => 'sometimes|string',
            'provider'     => 'sometimes|string|max:255',
            'rate_aer'     => 'sometimes|numeric|min:0|max:25',
            'effective_from' => 'sometimes|date',
        ];
    }
}
```

### Step R4.2.4: Create the resource

- [ ] **Create `app/Http/Resources/Admin/SavingsMarketRateResource.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SavingsMarketRateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_type' => $this->product_type,
            'provider' => $this->provider,
            'rate_aer' => (float) $this->rate_aer,
            'effective_from' => $this->effective_from?->toDateString() ?? $this->effective_from,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

### Step R4.2.5: Create the controller

- [ ] **Create `app/Http/Controllers/Api/Admin/SavingsMarketRateController.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSavingsMarketRateRequest;
use App\Http\Requests\Admin\UpdateSavingsMarketRateRequest;
use App\Http\Resources\Admin\SavingsMarketRateResource;
use App\Models\SavingsMarketRate;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsMarketRateStore;
use Illuminate\Http\JsonResponse;

class SavingsMarketRateController extends Controller
{
    public function __construct(
        private readonly SavingsMarketRateStore $store,
    ) {}

    public function index(): JsonResponse
    {
        $rows = SavingsMarketRate::orderBy('product_type')->orderByDesc('effective_from')->get();
        return response()->json(['data' => SavingsMarketRateResource::collection($rows)]);
    }

    public function store(StoreSavingsMarketRateRequest $request): JsonResponse
    {
        $id = $this->store->create(
            $request->validated(),
            IngestSource::ADMIN,
            actorUserId: $request->user()->id
        );

        return response()->json([
            'data' => new SavingsMarketRateResource(SavingsMarketRate::find($id)),
        ], 201);
    }

    public function update(UpdateSavingsMarketRateRequest $request, int $id): JsonResponse
    {
        $this->store->update(
            $id,
            $request->validated(),
            IngestSource::ADMIN,
            actorUserId: $request->user()->id
        );

        return response()->json([
            'data' => new SavingsMarketRateResource(SavingsMarketRate::find($id)),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->store->delete($id, IngestSource::ADMIN, actorUserId: request()->user()->id);
        return response()->json(['data' => null]);
    }
}
```

### Step R4.2.6: Wire the routes

- [ ] **Locate the existing admin route group in `routes/api.php` (look for `'permission:admin.tax_config'` as an anchor) and append the new resource group:**

```php
use App\Http\Controllers\Api\Admin\SavingsMarketRateController;

// Place AFTER the existing tax-config admin group, INSIDE the same outer `Route::middleware('auth:sanctum')->prefix('admin')` block.
Route::middleware(['auth:sanctum', 'permission:admin.savings_market_rates'])
    ->prefix('admin/savings-market-rates')
    ->group(function () {
        Route::get('/', [SavingsMarketRateController::class, 'index']);
        Route::post('/', [SavingsMarketRateController::class, 'store']);
        Route::patch('/{id}', [SavingsMarketRateController::class, 'update']);
        Route::delete('/{id}', [SavingsMarketRateController::class, 'destroy']);
    });
```

- [ ] **Run the feature test and confirm pass:**

```bash
./vendor/bin/pest tests/Feature/Admin/SavingsMarketRateAdminTest.php
```

Expected: PASS, 6 tests.

### Step R4.2.7: Build the Vue admin panel

- [ ] **Create `resources/js/services/admin/savingsMarketRatesService.js`:**

```javascript
import api from '@/services/api';

const savingsMarketRatesService = {
  async list() {
    return (await api.get('/admin/savings-market-rates')).data;
  },
  async create(payload) {
    return (await api.post('/admin/savings-market-rates', payload)).data;
  },
  async update(id, payload) {
    return (await api.patch(`/admin/savings-market-rates/${id}`, payload)).data;
  },
  async delete(id) {
    return (await api.delete(`/admin/savings-market-rates/${id}`)).data;
  },
};

export default savingsMarketRatesService;
```

- [ ] **Create `resources/js/store/modules/savingsMarketRates.js`:**

```javascript
import savingsMarketRatesService from '@/services/admin/savingsMarketRatesService';

const state = {
  items: [],
  loading: false,
  error: null,
};

const getters = {
  items: (state) => state.items,
  loading: (state) => state.loading,
  error: (state) => state.error,
};

const mutations = {
  setItems(state, items) { state.items = items; },
  setLoading(state, v) { state.loading = v; },
  setError(state, e) { state.error = e; },
  addItem(state, item) { state.items.unshift(item); },
  updateItem(state, item) {
    const i = state.items.findIndex((x) => x.id === item.id);
    if (i !== -1) state.items.splice(i, 1, item);
  },
  removeItem(state, id) {
    state.items = state.items.filter((x) => x.id !== id);
  },
};

const actions = {
  async fetchItems({ commit }) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const res = await savingsMarketRatesService.list();
      commit('setItems', res.data);
    } catch (e) {
      commit('setError', e.message);
      throw e;
    } finally {
      commit('setLoading', false);
    }
  },
  async createItem({ commit }, payload) {
    const res = await savingsMarketRatesService.create(payload);
    commit('addItem', res.data);
    return res.data;
  },
  async updateItem({ commit }, { id, payload }) {
    const res = await savingsMarketRatesService.update(id, payload);
    commit('updateItem', res.data);
    return res.data;
  },
  async deleteItem({ commit }, id) {
    await savingsMarketRatesService.delete(id);
    commit('removeItem', id);
  },
};

export default { namespaced: true, state, getters, mutations, actions };
```

- [ ] **Register in `resources/js/store/index.js`** alongside other admin modules:

```javascript
import savingsMarketRates from './modules/savingsMarketRates';
// ...
modules: {
  // ... existing modules
  savingsMarketRates,
}
```

- [ ] **Create `resources/js/components/Admin/SavingsMarketRates.vue`:** Match the style of an existing admin tab (e.g. `TaxSettings.vue` or `AdminInsights/ArticleListPage.vue`). The required shape:
  - Table of rates: provider, product_type, rate_aer, effective_from
  - "Add rate" button → modal with the four fields
  - Edit per row → modal pre-filled
  - Delete per row → confirmation modal
  - Wire to `savingsMarketRates` Vuex module

```vue
<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-horizon-500">Savings market rates</h2>
      <button class="btn-primary" @click="openCreateModal">Add rate</button>
    </div>

    <div v-if="loading" class="text-neutral-500">Loading...</div>
    <div v-else-if="error" class="text-raspberry-500">{{ error }}</div>
    <table v-else class="w-full">
      <thead>
        <tr class="text-left text-sm text-horizon-400 uppercase">
          <th class="py-2">Provider</th>
          <th>Product type</th>
          <th>Rate AER (%)</th>
          <th>Effective from</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in items" :key="row.id" class="border-b border-savannah-200">
          <td class="py-3 font-medium">{{ row.provider }}</td>
          <td>{{ formatProductType(row.product_type) }}</td>
          <td>{{ row.rate_aer.toFixed(2) }}</td>
          <td>{{ row.effective_from }}</td>
          <td class="text-right">
            <button class="text-horizon-500 mr-3" @click="openEditModal(row)">Edit</button>
            <button class="text-raspberry-500" @click="openDeleteModal(row)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Modals: SavingsMarketRateFormModal, ConfirmDelete -->
    <SavingsMarketRateFormModal
      v-if="formModal.open"
      :rate="formModal.row"
      @save="handleSave"
      @close="formModal.open = false"
    />
    <ConfirmDeleteModal
      v-if="deleteModal.open"
      :title="`Delete ${deleteModal.row.provider}?`"
      @confirm="handleDelete"
      @cancel="deleteModal.open = false"
    />
  </div>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import SavingsMarketRateFormModal from './SavingsMarketRateFormModal.vue';
import ConfirmDeleteModal from '../Shared/ConfirmDeleteModal.vue';

export default {
  name: 'SavingsMarketRates',
  components: { SavingsMarketRateFormModal, ConfirmDeleteModal },
  data() {
    return {
      formModal: { open: false, row: null },
      deleteModal: { open: false, row: null },
    };
  },
  computed: {
    ...mapGetters('savingsMarketRates', ['items', 'loading', 'error']),
  },
  methods: {
    ...mapActions('savingsMarketRates', ['fetchItems', 'createItem', 'updateItem', 'deleteItem']),
    openCreateModal() {
      this.formModal = { open: true, row: null };
    },
    openEditModal(row) {
      this.formModal = { open: true, row };
    },
    openDeleteModal(row) {
      this.deleteModal = { open: true, row };
    },
    async handleSave(payload) {
      if (this.formModal.row) {
        await this.updateItem({ id: this.formModal.row.id, payload });
      } else {
        await this.createItem(payload);
      }
      this.formModal.open = false;
    },
    async handleDelete() {
      await this.deleteItem(this.deleteModal.row.id);
      this.deleteModal.open = false;
    },
    formatProductType(t) {
      return t.replace(/_/g, ' ');
    },
  },
  mounted() {
    this.fetchItems();
  },
};
</script>
```

- [ ] **Create the companion form-modal `resources/js/components/Admin/SavingsMarketRateFormModal.vue`:** mirror `TrustFormModal.vue` style — fields, validation, `@save` emit (NOT `@submit`, per CLAUDE.md form-modal-events rule).

- [ ] **Register the new tab in `resources/js/views/Admin/AdminPanel.vue`:** find the existing `<tab>` declarations and add `{ key: 'savings-market-rates', label: 'Savings market rates', component: SavingsMarketRates }`. Import the component at the top of the script.

### Step R4.2.8: Browser-test on local dev

- [ ] **Manual Playwright/browser test:** start dev server, log in as admin, navigate to the new tab, add a rate, edit it, delete it. Verify the DB row appears/changes/disappears each time.

### Step R4.2.9: Run full suite

- [ ] **Run full Pest suite:**

```bash
./vendor/bin/pest
```

Expected: no regressions vs the 4-failure post-merge baseline established earlier (`tests/Feature/AI/ProviderSwapLockTest` x2 now-fixed → 2 remaining: `CassetteModelProvenanceTest` documented C1 + 1 unidentified).

### Step R4.2.10: Commit + push + open PR + csjones smoke

- [ ] **Commit:**

```bash
git checkout -b feat/ref-data-r4-pr2-admin-crud
git add app/Http/Controllers/Api/Admin/SavingsMarketRateController.php \
        app/Http/Requests/Admin/StoreSavingsMarketRateRequest.php \
        app/Http/Requests/Admin/UpdateSavingsMarketRateRequest.php \
        app/Http/Resources/Admin/SavingsMarketRateResource.php \
        database/migrations/2026_05_22_100000_add_admin_savings_market_rates_permission.php \
        resources/js/components/Admin/SavingsMarketRates.vue \
        resources/js/components/Admin/SavingsMarketRateFormModal.vue \
        resources/js/services/admin/savingsMarketRatesService.js \
        resources/js/store/modules/savingsMarketRates.js \
        resources/js/store/index.js \
        resources/js/views/Admin/AdminPanel.vue \
        routes/api.php \
        tests/Feature/Admin/SavingsMarketRateAdminTest.php
git commit -m "feat(ref-data): admin CRUD for SavingsMarketRate (SP1 P2 R4.2)"
git push -u origin feat/ref-data-r4-pr2-admin-crud
gh pr create --base dev --title "feat(ref-data): SP1 P2 R4.2 — admin CRUD for SavingsMarketRate"
```

- [ ] **Deploy to csjones after admin-merge:** rebuild SPA (`./deploy/csjones-fynla/build.sh`), upload `public/build/`, SSH csjones, `git pull origin dev`, `php artisan migrate --force`, `php artisan cache:clear && config:clear && view:clear && route:clear && composer dump-autoload -o && php artisan optimize`. Smoke the admin panel.

### PR R4.3: Migrate `RateComparator` read consumer

**PR title:** `refactor(ref-data): point RateComparator at SavingsMarketRateStore reads (SP1 P2 R4.3)`

**Files:**
- Modify: `app/Services/Savings/RateComparator.php`
- Modify: `tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php` (remove from allowlist)

### Step R4.3.1: Read the existing RateComparator implementation

- [ ] **Read `app/Services/Savings/RateComparator.php`** to learn its consumers + the exact `SavingsMarketRate` queries it issues today.

### Step R4.3.2: Add a regression test for RateComparator behaviour

- [ ] **Create / extend `tests/Unit/Services/Savings/RateComparatorTest.php`** with a test that asserts the current behaviour against a known fixture set. This test is the safety net — it must pass before AND after the refactor.

### Step R4.3.3: Refactor to use the store

- [ ] **Inject `SavingsMarketRateStore` via constructor; replace direct `SavingsMarketRate::query()` / `::where()` calls with `$store->currentRateFor(...)`** (or with whatever read methods you add to the store — extend the store's public read API as needed; just don't reach past it).

- [ ] **Run the regression test:**

```bash
./vendor/bin/pest tests/Unit/Services/Savings/RateComparatorTest.php
```

Expected: PASS.

### Step R4.3.4: Tighten the arch test

- [ ] **Remove `App\Services\Savings\RateComparator` from the allowlist in `tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php`.** The test should still pass — RateComparator now goes through the store.

```bash
./vendor/bin/pest tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php
```

Expected: PASS.

### Step R4.3.5: Commit + PR

- [ ] **Commit, push, open PR `feat/ref-data-r4-pr3-ratecomparator → dev`.**

### PR R4.4: Migrate the seeder

**PR title:** `refactor(ref-data): SavingsMarketRatesSeeder via SavingsMarketRateStore (SP1 P2 R4.4)`

**Files:**
- Modify: `database/seeders/SavingsMarketRatesSeeder.php`
- Modify: `tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php` (remove seeder from allowlist)

### Step R4.4.1: Refactor

- [ ] **Read the current seeder; replace its direct `SavingsMarketRate::create(...)` / `::updateOrCreate(...)` calls with `$store->create($payload, IngestSource::SEEDER)`.** Inject the store via `$this->container->make(SavingsMarketRateStore::class)` if the seeder isn't constructor-injectable.

### Step R4.4.2: Re-run the seeder and verify

- [ ] **Run the seeder:**

```bash
php artisan db:seed --class=SavingsMarketRatesSeeder --force
```

Expected: zero errors; DB rows present.

### Step R4.4.3: Remove seeder from arch test allowlist

- [ ] **Remove `Database\Seeders\SavingsMarketRatesSeeder` from the allowlist; re-run the arch test.**

```bash
./vendor/bin/pest tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php
```

Expected: PASS.

### Step R4.4.4: Commit + PR

- [ ] **Commit, push, open PR `feat/ref-data-r4-pr4-seeder → dev`.**

### PR R4.5: Lock-down — controller off the allowlist

**PR title:** `lock-down(ref-data): R4 SavingsMarketRate boundary fully locked (SP1 P2 R4.5)`

**Files:**
- Modify: `tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php`

### Step R4.5.1: Remove the controller from the allowlist

- [ ] **The admin controller doesn't mutate the model directly (it goes through the store).** Removing it from the allowlist proves the boundary holds. The final allowlist should contain ONLY the store class itself.

- [ ] **Re-run the arch test.**

```bash
./vendor/bin/pest tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php
```

Expected: PASS, allowlist = [`SavingsMarketRateStore`] only.

### Step R4.5.2: Run the full suite

- [ ] **`./vendor/bin/pest`** — no regressions.

### Step R4.5.3: Commit + PR

- [ ] **Commit, push, open PR `feat/ref-data-r4-pr5-lockdown → dev`.**

**R4 acceptance criteria:**
- [x] Pest arch test: only `SavingsMarketRateStore` mutates `SavingsMarketRate`
- [x] Admin panel: tab visible, CRUD works (browser-tested on csjones)
- [x] `RateComparator` reads through the store; no direct model access
- [x] Seeder writes through the store with `IngestSource::SEEDER`
- [x] `ReferenceDataUpdated` event emitted on every admin write
- [x] Per-request memoised reads working

---

## R3 — Actuarial Life Tables track

**Same five-PR pattern as R4**, applied to `ActuarialLifeTable`. Differences:

- Three read consumers to migrate in PR R3.3 (`TrustService`, `FutureValueCalculator`, `ComprehensiveEstatePlanService`) — may split into R3.3a / R3.3b if the diff exceeds ~500 lines per spec §15.1.
- Read-API surface on the store: probably `mortalityRateFor($age, $gender)` and `lifeExpectancyAt($age, $gender)` — verify against the actual model + consumer queries before locking the API.

### PR R3.1: Introduce store + arch boundary

Files, steps, and templates mirror R4.1. Replace `SavingsMarketRate*` with `ActuarialLifeTable*`. Entity key: `actuarial_life_table`.

- [ ] Create `app/Services/Stores/ActuarialLifeTableStore.php` (extends `ReferenceDataStore`)
- [ ] Create `app/Services/Stores/Normalisers/ActuarialLifeTableNormaliser.php`
- [ ] Create `tests/Unit/Services/Stores/ActuarialLifeTableStoreTest.php`
- [ ] Create `tests/Unit/Services/Stores/Normalisers/ActuarialLifeTableNormaliserTest.php`
- [ ] Create `tests/Architecture/StoreBoundary/ActuarialLifeTableStoreBoundaryTest.php` with initial allowlist:
  - `App\Services\Stores\ActuarialLifeTableStore`
  - `App\Services\Estate\TrustService`
  - `App\Services\Estate\FutureValueCalculator`
  - `App\Services\Estate\ComprehensiveEstatePlanService`
  - `Database\Seeders\ActuarialLifeTablesSeeder`
  - `App\Http\Controllers\Api\Admin\ActuarialLifeTableController`

Follow the same TDD micro-cycle as R4.1: failing normaliser test → impl → failing store test → impl → arch test → commit. Open PR `feat/ref-data-r3-pr1 → dev`.

### PR R3.2: Admin CRUD + Vue panel

Mirror R4.2. Files:
- `app/Http/Controllers/Api/Admin/ActuarialLifeTableController.php`
- `app/Http/Requests/Admin/{Store,Update}ActuarialLifeTableRequest.php`
- `app/Http/Resources/Admin/ActuarialLifeTableResource.php`
- `database/migrations/2026_05_22_100001_add_admin_actuarial_life_tables_permission.php`
- `resources/js/components/Admin/ActuarialLifeTables.vue` + form modal
- `resources/js/services/admin/actuarialLifeTablesService.js`
- `resources/js/store/modules/actuarialLifeTables.js`
- `tests/Feature/Admin/ActuarialLifeTableAdminTest.php`
- Routes under `permission:admin.actuarial_life_tables`
- Add tab to `AdminPanel.vue`
- Register Vuex module in `store/index.js`

Follow R4.2 step-by-step. **Browser-test on csjones before merging.**

### PR R3.3: Migrate the three read consumers

Three sites to migrate; ~500-line diff guard applies (split into R3.3a + R3.3b if needed):

- [ ] **`app/Services/Estate/TrustService.php`** — inject `ActuarialLifeTableStore`; replace direct queries with store reads. Add a regression test that asserts the trust valuation matches the pre-refactor result on a known fixture.

- [ ] **`app/Services/Estate/FutureValueCalculator.php`** — same pattern.

- [ ] **`app/Services/Estate/ComprehensiveEstatePlanService.php`** — same pattern.

- [ ] Remove each consumer from the arch test allowlist as it's migrated.

### PR R3.4: Migrate the seeder

Mirror R4.4. `database/seeders/ActuarialLifeTablesSeeder.php` writes through `$store->create($payload, IngestSource::SEEDER)`.

### PR R3.5: Lock-down

Mirror R4.5. Final allowlist for `ActuarialLifeTableStoreBoundaryTest`: `[App\Services\Stores\ActuarialLifeTableStore]` only.

**R3 acceptance:** mirrors R4.

---

## R1 — Tax Configuration track

**This is the biggest, most carefully-scoped entity.** It has a heavy existing surface — `TaxConfiguration` + `TaxConfigurationAudit` models, `TaxConfigService` (read-heavy public API consumed everywhere), `TaxSettingsController` (8 write methods), the 3068-line `TaxSettings.vue` admin panel — and the spec specifically calls out fixing B2 (admin can't actually edit values). PR R1.0 (the B2 audit, above) informs the scope of PR R1.5.

### PR R1.1: Introduce `TaxConfigStore` facade + arch boundary

**PR title:** `feat(ref-data): introduce TaxConfigStore facade + arch boundary (SP1 P2 R1.1)`

**Files:**
- Create: `app/Services/Stores/TaxConfigStore.php`
- Create: `app/Services/Stores/Normalisers/TaxConfigNormaliser.php`
- Create: `tests/Unit/Services/Stores/TaxConfigStoreTest.php`
- Create: `tests/Unit/Services/Stores/Normalisers/TaxConfigNormaliserTest.php`
- Create: `tests/Architecture/StoreBoundary/TaxConfigStoreBoundaryTest.php`

### Step R1.1.1: Map the existing TaxConfig surface before writing tests

- [ ] **Inventory the consumer surface of `TaxConfigService`:**

```bash
grep -rln "TaxConfigService\b" app/ tests/ 2>/dev/null
```

Note every file that calls a `TaxConfigService::get*` method. These are READ consumers — they stay unchanged in pass 2. The store sits BELOW `TaxConfigService`, not as a replacement.

- [ ] **Inventory direct `TaxConfiguration::` model access:**

```bash
grep -rnE "TaxConfiguration::(create|update|save|delete|forceDelete|insert)\b" app/ tests/ 2>/dev/null
grep -rn "TaxConfiguration::\(getActive\|find\|where\|query\)" app/ tests/ 2>/dev/null
```

Direct mutations go on the arch-test allowlist (initial) and get migrated PR-by-PR. Direct READS via `getActive`/etc. that live OUTSIDE `TaxConfigService` are technically allowed under spec §14.2 (model is read-only OK), but we'll audit and route them through `TaxConfigService` where it makes the dependency graph clearer.

### Step R1.1.2: Implement the store with the same TDD micro-cycle as R4.1

- [ ] **Write the failing normaliser test** covering:
  - Required fields: `tax_year`, `effective_from`, `effective_to`, `config_data`
  - `tax_year` shape validation (e.g. `/^\d{4}\/\d{2}$/` like `2026/27`)
  - `effective_from < effective_to`
  - `config_data` is an array
  - Rejects unknown sections (allowlisted: income_tax, ni, isa, iht, cgt, pension, etc. — pull the actual list from `TaxConfigurationSeeder.php`)

- [ ] **Implement `TaxConfigNormaliser::fromAdmin`** following the R4 normaliser pattern.

- [ ] **Write the failing store test** covering:
  - Create / update / setActive (replaces the existing `is_active=true` deactivate-others-first pattern)
  - Duplicate (replicates the existing `duplicate` controller method as a store method)
  - Audit row written to `tax_configuration_audits` via the existing `TaxConfigurationAudit` model (do NOT replace this with the generic ReferenceDataUpdated event — the audit table is the canonical history for tax config per spec §10.4)
  - `ReferenceDataUpdated` event ALSO emitted (so consumer caches drop)
  - ADMIN|SEEDER-only writes

- [ ] **Implement `TaxConfigStore` extending `ReferenceDataStore`:**

```php
<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\TaxConfiguration;
use App\Models\TaxConfigurationAudit;
use App\Services\Stores\Normalisers\TaxConfigNormaliser;
use App\Services\Stores\ReferenceData\ReferenceDataStore;
use Illuminate\Support\Facades\DB;

class TaxConfigStore extends ReferenceDataStore
{
    public function __construct(
        private readonly TaxConfigNormaliser $normaliser = new TaxConfigNormaliser
    ) {}

    protected function entityKey(): string
    {
        return 'tax_configuration';
    }

    public function create(array $input, IngestSource $source, ?int $actorUserId = null, ?string $rationale = null): int
    {
        $canonical = $this->normaliser->fromAdmin($input);
        return DB::transaction(function () use ($canonical, $source, $actorUserId, $rationale) {
            $id = parent::create($canonical, $source, $actorUserId);
            $this->writeAudit($id, 'created', null, $rationale, $actorUserId);
            return $id;
        });
    }

    public function update(int $id, array $input, IngestSource $source, ?int $actorUserId = null, ?string $rationale = null): void
    {
        return DB::transaction(function () use ($id, $input, $source, $actorUserId, $rationale) {
            $before = TaxConfiguration::find($id)?->config_data;
            $existing = $this->find($id);
            $merged = array_merge($existing, $input);
            $canonical = $this->normaliser->fromAdmin($merged);
            parent::update($id, $canonical, $source, $actorUserId);
            $this->writeAudit($id, 'updated', $before, $rationale, $actorUserId);
        });
    }

    public function setActive(int $id, IngestSource $source, ?int $actorUserId = null): void
    {
        DB::transaction(function () use ($id, $source, $actorUserId) {
            TaxConfiguration::where('is_active', true)->update(['is_active' => false]);
            TaxConfiguration::where('id', $id)->update(['is_active' => true]);
            $this->writeAudit($id, 'activated', null, null, $actorUserId);
        });
        // Drop memoised entries — both the activated one and any previously-active one.
        $this->forgetAll();
    }

    public function duplicate(int $sourceId, string $newTaxYear, IngestSource $source, ?int $actorUserId = null): int
    {
        $existing = $this->find($sourceId);
        if (! $existing) {
            throw new \App\Services\Stores\Exceptions\StoreValidationException(['id' => 'source config not found']);
        }
        $payload = [
            'tax_year' => $newTaxYear,
            'effective_from' => $existing['effective_from'],
            'effective_to' => $existing['effective_to'],
            'config_data' => $existing['config_data'],
            'is_active' => false,
        ];
        return $this->create($payload, $source, $actorUserId, "Duplicated from tax-year {$existing['tax_year']}");
    }

    public function delete(int $id, IngestSource $source, ?int $actorUserId = null, ?string $rationale = null): void
    {
        DB::transaction(function () use ($id, $source, $actorUserId, $rationale) {
            $before = TaxConfiguration::find($id)?->config_data;
            parent::delete($id, $source, $actorUserId);
            $this->writeAudit($id, 'deleted', $before, $rationale, $actorUserId);
        });
    }

    protected function persist(array $canonical, ?int $id = null): int
    {
        if ($id === null) {
            return TaxConfiguration::create($canonical)->id;
        }
        TaxConfiguration::where('id', $id)->update($canonical);
        return $id;
    }

    protected function read(int $id): array
    {
        $row = TaxConfiguration::find($id);
        return $row ? $row->toArray() : [];
    }

    protected function delete_(int $id): void
    {
        TaxConfiguration::where('id', $id)->delete();
    }

    private function writeAudit(int $id, string $action, ?array $before, ?string $rationale, ?int $actorUserId): void
    {
        TaxConfigurationAudit::create([
            'tax_configuration_id' => $id,
            'action' => $action,
            'before_state' => $before ? json_encode($before) : null,
            'rationale' => $rationale,
            'actor_user_id' => $actorUserId,
        ]);
    }

    private function forgetAll(): void
    {
        // Reset memoised cache fully — used after setActive which can affect any/all rows.
        $reflection = new \ReflectionClass(ReferenceDataStore::class);
        $cacheProp = $reflection->getProperty('cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($this, []);
    }
}
```

### Step R1.1.3: Initial arch test allowlist

- [ ] **Create `tests/Architecture/StoreBoundary/TaxConfigStoreBoundaryTest.php`** with the allowlist drawn from the inventory in Step R1.1.1. Expect ~5–8 entries initially; each subsequent PR shrinks it.

### Step R1.1.4: Commit + PR

- [ ] **`git checkout -b feat/ref-data-r1-pr1-tax-config-store` → commit → push → PR `→ dev`.** Wait for admin-merge.

### PR R1.2: Point `TaxSettingsController` write methods at `TaxConfigStore`

**PR title:** `refactor(ref-data): TaxSettingsController routes writes via TaxConfigStore (SP1 P2 R1.2)`

**Files:**
- Modify: `app/Http/Controllers/Api/TaxSettingsController.php`
- Modify: `tests/Architecture/StoreBoundary/TaxConfigStoreBoundaryTest.php` (remove controller from allowlist)
- Create: `tests/Feature/Admin/TaxConfigAdminTest.php` (covers create / update / setActive / duplicate / delete via existing routes)

### Step R1.2.1: Add the failing feature parity test

- [ ] **Create `tests/Feature/Admin/TaxConfigAdminTest.php`** asserting:
  - `POST /api/tax-settings` creates a row + audit row + emits event
  - `PATCH /api/tax-settings/{id}` updates row + writes audit row with before-state + emits event
  - `POST /api/tax-settings/{id}/set-active` deactivates others + activates this one + writes audit
  - `POST /api/tax-settings/{id}/duplicate` creates a new row with the source's config + audit referencing source
  - `DELETE /api/tax-settings/{id}` deletes + writes audit + emits event
  - Non-admin: 403
  - Missing `permission:admin.tax_config`: 403

### Step R1.2.2: Refactor each controller method to call the store

- [ ] **Inject `TaxConfigStore`** via constructor.

- [ ] **`update(...)`:** replace the existing inline logic (lines 99–164) with `$this->store->update($id, $request->validated(), IngestSource::ADMIN, $request->user()->id, $request->input('rationale'))`. Return resource.

- [ ] **`create(StoreTaxConfigurationRequest $request)`:** `$this->store->create($request->validated(), IngestSource::ADMIN, $request->user()->id, $request->input('rationale'))`. Return resource.

- [ ] **`setActive(int $id)`:** `$this->store->setActive($id, IngestSource::ADMIN, $request->user()->id)`.

- [ ] **`duplicate(Request $request, int $id)`:** `$this->store->duplicate($id, $request->input('tax_year'), IngestSource::ADMIN, $request->user()->id)`.

- [ ] **`delete(int $id)`:** `$this->store->delete($id, IngestSource::ADMIN, request()->user()->id, request()->input('rationale'))`.

- [ ] **Run the feature test:**

```bash
./vendor/bin/pest tests/Feature/Admin/TaxConfigAdminTest.php
```

Expected: all pass.

### Step R1.2.3: Remove controller from arch test allowlist

- [ ] **Remove `App\Http\Controllers\Api\TaxSettingsController` from `TaxConfigStoreBoundaryTest`. Re-run.**

### Step R1.2.4: Commit + PR

- [ ] **PR `feat/ref-data-r1-pr2-controller → dev`.**

### PR R1.3: Migrate the seeder

**PR title:** `refactor(ref-data): TaxConfigurationSeeder via TaxConfigStore (SP1 P2 R1.3)`

**Files:**
- Modify: `database/seeders/TaxConfigurationSeeder.php`
- Modify: `tests/Architecture/StoreBoundary/TaxConfigStoreBoundaryTest.php`

### Step R1.3.1: Refactor seeder

- [ ] **Replace direct `TaxConfiguration::create / updateOrCreate` calls in the seeder with `$store->create($payload, IngestSource::SEEDER)`.**

- [ ] **Important — preserve idempotency:** the existing seeder uses `updateOrCreate` to be re-runnable. The new path should check existence by `tax_year` and call `update` instead of `create` if found.

- [ ] **Re-run the seeder:**

```bash
php artisan db:seed --class=TaxConfigurationSeeder --force
```

Expected: zero errors; DB state matches pre-seeder.

### Step R1.3.2: Remove seeder from allowlist

- [ ] **Remove `Database\Seeders\TaxConfigurationSeeder` from allowlist; re-run arch test.**

### Step R1.3.3: Commit + PR

- [ ] **PR `feat/ref-data-r1-pr3-seeder → dev`.**

### PR R1.4: Point `TaxConfigService` internal reads at `TaxConfigStore`

**PR title:** `refactor(ref-data): TaxConfigService internal reads via TaxConfigStore (SP1 P2 R1.4)`

**Files:**
- Modify: `app/Services/TaxConfigService.php`

### Step R1.4.1: Inventory the service's existing reads

- [ ] **Read `app/Services/TaxConfigService.php`.** It probably uses `TaxConfiguration::getActive()` and similar static helpers. Each becomes a `$this->store->find($activeId)` or a new store read method.

### Step R1.4.2: Add an `activeConfig()` read method to the store

- [ ] **In `TaxConfigStore`:** add a public `activeConfig(): ?array` method that returns the currently-active config (memoised). The service's public API stays the same — only the internal read swaps.

### Step R1.4.3: Refactor service internals

- [ ] **Replace each `TaxConfiguration::getActive()` / `TaxConfiguration::where(...)->first()` call inside `TaxConfigService` with `$this->store->activeConfig()` or `$this->store->find($id)`.**

- [ ] **Public service API stays unchanged.** Every consumer in `app/Constants/*` and `app/Agents/*` continues to call `TaxConfigService::getIncomeTax()`, `getISAAllowances()`, etc.

### Step R1.4.4: Run the consumer suite

- [ ] **Run the broader consumer suite:**

```bash
./vendor/bin/pest tests/Feature tests/Unit
```

Expected: no regressions. Tax-related tests should still pass because the read API is preserved.

### Step R1.4.5: Commit + PR

- [ ] **PR `feat/ref-data-r1-pr4-service-reads → dev`.**

### PR R1.5: Fix B2 — close the admin-edit gap identified in PR R1.0

**Scope informed by the PR R1.0 audit memo.** Only attempt this PR after R1.0 has produced its memo and is itself merged.

**PR title:** `fix(ref-data): close TaxSettings.vue admin-edit gaps per B2 audit (SP1 P2 R1.5)`

**Files (illustrative — depends on the audit):**
- Modify: `resources/js/components/Admin/TaxSettings.vue`
- Maybe modify: `app/Http/Controllers/Api/TaxSettingsController.php` (if a route is missing)
- Maybe modify: `app/Http/Requests/StoreTaxConfigurationRequest.php` (if validation is too tight)
- Modify: `tests/Feature/Admin/TaxConfigAdminTest.php` (add round-trip tests for the fixed fields)

### Step R1.5.1: For each broken field identified in R1.0, write a failing browser/feature test

- [ ] **Use the audit memo's "Round-trip broken" list as the test backlog.** Write one feature test per field that:
  1. POSTs/PATCHes the admin endpoint with a new value
  2. Reloads via `TaxConfigService::get(...)` and asserts the new value is returned
  3. Asserts the audit row was written

### Step R1.5.2: Fix each gap with the smallest possible diff

- [ ] **Per spec §12.1: "Sub-project 1 audits and fixes the wiring rather than building from scratch."** Each fix should be a minimum-diff PR-internal commit.

### Step R1.5.3: Browser-test on local + csjones

- [ ] **Playwright walk-through:** every field the audit identified is now round-trippable.

### Step R1.5.4: Commit + PR + csjones smoke

- [ ] **PR `feat/ref-data-r1-pr5-b2-fix → dev`.** csjones smoke MANDATORY before merge.

### PR R1.6: Lock-down

**PR title:** `lock-down(ref-data): R1 TaxConfig boundary fully locked (SP1 P2 R1.6)`

**Files:**
- Modify: `tests/Architecture/StoreBoundary/TaxConfigStoreBoundaryTest.php`

### Step R1.6.1: Reduce allowlist to store only

- [ ] **Allowlist should now contain only `App\Services\Stores\TaxConfigStore`.** Any other entries indicate consumer migrations PR R1.2–R1.4 missed — fix those before locking down.

### Step R1.6.2: Run full suite

- [ ] **`./vendor/bin/pest`** — no regressions.

### Step R1.6.3: Commit + PR

- [ ] **PR `feat/ref-data-r1-pr6-lockdown → dev`.**

**R1 acceptance criteria:**
- [x] Pest arch test: only `TaxConfigStore` mutates `TaxConfiguration`
- [x] Admin can edit every tax-config field; round-trip verified per B2 audit
- [x] `TaxConfigurationAudit` rows captured for every write (create / update / setActive / duplicate / delete)
- [x] `ReferenceDataUpdated` event emitted on every write
- [x] `TaxConfigService` public API unchanged; consumers in `Constants/` and `Agents/` not modified
- [x] Per-request memoised reads working

---

## R2 — Currency Rates track (greenfield)

This entity has no existing infrastructure — table, model, service, admin, all new. Pass 2 builds the admin-managed reference store; the `_gbp` derived columns on user-data entities (per spec §9) belong to passes 3–14 (Properties, Investments, etc.) when each entity is migrated.

### PR R2.1: Migration + Model + Factory + Seeder

**PR title:** `feat(ref-data): currency_rates table + CurrencyRate model + seeder (SP1 P2 R2.1)`

**Files:**
- Create: `database/migrations/2026_05_22_100002_create_currency_rates_table.php`
- Create: `app/Models/CurrencyRate.php`
- Create: `database/factories/CurrencyRateFactory.php`
- Create: `database/seeders/CurrencyRatesSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (call `CurrencyRatesSeeder`)

### Step R2.1.1: Schema

- [ ] **Create the migration:**

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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->char('from_ccy', 3);            // ISO 4217, e.g. 'GBP'
            $table->char('to_ccy', 3);              // ISO 4217, e.g. 'EUR'
            $table->decimal('rate', 18, 8);          // 1 from_ccy = rate to_ccy
            $table->dateTime('effective_at');        // when this rate became applicable
            $table->string('source', 64)->default('manual'); // 'manual' | 'feed:ecb' | etc.
            $table->timestamps();
            $table->index(['from_ccy', 'to_ccy', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
```

- [ ] **Run the migration:**

```bash
php artisan migrate
```

### Step R2.1.2: Model + Factory + Seeder

- [ ] **Create `app/Models/CurrencyRate.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use HasFactory;

    protected $fillable = ['from_ccy', 'to_ccy', 'rate', 'effective_at', 'source'];

    protected $casts = [
        'rate' => 'decimal:8',
        'effective_at' => 'datetime',
    ];
}
```

- [ ] **Create `database/factories/CurrencyRateFactory.php`:**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurrencyRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyRateFactory extends Factory
{
    protected $model = CurrencyRate::class;

    public function definition(): array
    {
        return [
            'from_ccy' => 'GBP',
            'to_ccy' => $this->faker->randomElement(['EUR', 'USD']),
            'rate' => $this->faker->randomFloat(8, 0.5, 2.0),
            'effective_at' => now(),
            'source' => 'manual',
        ];
    }
}
```

- [ ] **Create `database/seeders/CurrencyRatesSeeder.php`** — seed initial GBP↔EUR and GBP↔USD rates (and the inverse pairs). Calls `app(CurrencyRateStore::class)->create(...)` once R2.2 lands; until then, direct `CurrencyRate::create()` is the seeder body. **Important:** open this PR with direct-write seeder; PR R2.4 (seeder migration) swaps it to the store after R2.2 lands the store.

### Step R2.1.3: Register the seeder

- [ ] **In `database/seeders/DatabaseSeeder.php`:** call `$this->call(CurrencyRatesSeeder::class)` alongside the other ref-data seeders. Run `php artisan db:seed --class=CurrencyRatesSeeder --force` to verify.

### Step R2.1.4: Commit + PR

- [ ] **PR `feat/ref-data-r2-pr1-schema → dev`.**

### PR R2.2: Introduce `CurrencyRateStore` + arch boundary

Mirror R4.1 / R3.1. Entity key: `currency_rate`. Public read API:
- `latestFor(string $fromCcy, string $toCcy): ?float`
- `convert(float $amount, string $fromCcy, string $toCcy): ?float`
- `historical(string $fromCcy, string $toCcy, \DateTimeInterface $at): ?float`

Initial allowlist:
- `App\Services\Stores\CurrencyRateStore`
- `Database\Seeders\CurrencyRatesSeeder` (migrates in R2.4)
- `App\Http\Controllers\Api\Admin\CurrencyRateController` (lands in R2.3)

### PR R2.3: Admin CRUD + Vue panel

Mirror R4.2. Files in **File Structure** above. Routes under `permission:admin.currency_rates`. Add tab to `AdminPanel.vue`.

### PR R2.4: Migrate the seeder

Mirror R4.4. `CurrencyRatesSeeder` writes through `$store->create($payload, IngestSource::SEEDER)`.

### PR R2.5: Lock-down

Final allowlist: `[App\Services\Stores\CurrencyRateStore]` only.

**R2 acceptance:** mirrors R4.

### NOT in pass 2

The `_gbp` derived columns on user-data entity tables (Properties, Investments, etc. per spec §9.1) are NOT part of pass 2. Each user-data entity's pass adds its own `_gbp` columns when that entity is migrated. Pass 2 ships only the admin-managed `currency_rates` store; consumers materialise per pass.

---

## Pass-wide acceptance criteria (spec §16.1 + §16.2)

After all R4 / R3 / R1 / R2 PRs ship and reach main:

- [ ] **Single write path per entity** — Pest arch tests green for all four reference-data entities
- [ ] **Three-ingest parity** — N/A for reference data (only ADMIN + SEEDER ingest sources; no parity to assert)
- [ ] **Audit completeness** — every R1 write produces a `tax_configuration_audits` row with actor + before-state + rationale; R2/R3/R4 emit `ReferenceDataUpdated` events on every write (audit log table covers them)
- [ ] **Derived-column correctness** — N/A for pass 2 (snapshots/derived columns belong to user-data entity passes)
- [ ] **Snapshot policy applied** — N/A; reference data uses the existing `TaxConfigurationAudit` row pattern for R1, no separate snapshot tables for R2/R3/R4
- [ ] **Currency round-trip** — `CurrencyRateStore::convert()` returns the right value; rate changes invalidate the memoised cache
- [ ] **Tier-cap enforcement** — N/A (reference data is admin-only, no per-user count caps)
- [ ] **Browser-tested via Playwright on csjones for every entity** — for each of the four admin panels: add / edit / delete one row + verify DB + verify reload reflects change
- [ ] **B2 closed** — TaxSettings.vue round-trips every editable field per the PR R1.0 audit memo
- [ ] **Each entity has `app/Services/Stores/{Entity}Store.md`** describing the public API, the entity key, allowed read methods, and any per-entity quirks (R1's audit table integration, R2's `convert()` method, etc.)

---

## Self-Review

**Spec coverage check:**

| Spec section | Covered by |
|---|---|
| §3.3 R1 Tax | R1.1 – R1.6 |
| §3.3 R2 Currency | R2.1 – R2.5 |
| §3.3 R3 Actuarial | R3.1 – R3.5 |
| §3.3 R4 Savings rates | R4.1 – R4.5 |
| §4 architectural principles | Inherited from PR 0 `ReferenceDataStore` base + per-entity arch tests |
| §5 store contract | PR 0 + each entity's PR 1 |
| §6 ingest paths | Spec §12.1 narrows reference data to ADMIN + SEEDER — both implemented |
| §7 validation | Normalisers per entity + FormRequests on each admin endpoint |
| §8 audit | R1 uses existing `TaxConfigurationAudit`; R2/R3/R4 emit `ReferenceDataUpdated` events (audit-log infrastructure handles persistence) |
| §9 currency normalisation | R2.1 ships the `currency_rates` table + store; column rollout to user-data entities deferred to passes 3–14 |
| §10 derived columns / snapshots | N/A for ref data (called out as deferred in R2 section) |
| §11 storage events | `ReferenceDataUpdated` event from PR 0 |
| §12 reference-data stores | Pass 2 IS this section |
| §13 tier-aware count caps | N/A (ref data is global, no per-user count) |
| §14 boundary enforcement | Per-entity Pest arch tests, hard-fail from each entity's PR 1 |
| §15 migration strategy | Five-PR cadence per entity (vs spec's 8-PR pattern, justified by spec §12.1 narrowing the ingest surface) |
| §16 acceptance criteria | Pass-wide block above |
| §17 out of scope | Same as spec §17 |
| §20 open questions | All resolved — no new questions raised by this plan |

**Placeholder scan:**
- Search for "TBD", "TODO", "fill in" → none in this plan (verified)
- "Similar to PR X" appears in R3.x and R2.x sections — each occurrence is followed by a concrete file list and the step list of the mirror PR is referenced verbatim. The engineer can read the R4 sections then mechanically apply to R3 / R2. This is acceptable per the writing-plans skill's "DRY" guidance.
- All code blocks include complete code for the step they appear in.

**Type consistency:**
- `IngestSource` enum cases (`FORM`, `FYN_AI`, `UPLOAD`, `SEEDER`, `ADMIN`) used identically across all entity sections
- `ReferenceDataUpdated` event constructor signature (`entityKey`, `entityId`, `changedKeys`, `actorUserId`) consistent across PR 0, R4, R3, R1, R2
- Store base class methods (`create`, `update`, `delete`, `find`, `entityKey`, `persist`, `read`, `delete_`) consistent across PR 0 base + R4 / R3 / R1 / R2 subclasses
- `actorUserId` parameter name consistent (`int $actorUserId` in event, `?int $actorUserId = null` in store methods)

---

## Risks and mitigations

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `TaxConfigService` is consumed in many places — refactoring its internals (R1.4) regresses tax calculations across the app | Medium | High | R1.4 keeps the service's PUBLIC API byte-identical; only internal reads change. Run the full Pest suite after each step. |
| `TaxConfigurationAudit` schema may not match what `TaxConfigStore::writeAudit` writes | Medium | Medium | Read the existing model in PR R1.1 step 1 BEFORE writing the store's audit method; adjust the audit shape to match. |
| B2 may already be partially fixed by post-spec commits, making PR R1.5 mostly a no-op (or expanding scope to find new gaps) | High | Low | PR R1.0 audit memo explicitly sets PR R1.5's scope. R1.5 ships whatever the audit identifies — no more, no less. |
| Admin permissions schema (`permission:admin.tax_config` vs whatever) varies by deployment — the migration in R4.2 / R3.2 / R1.x / R2.x may not match | Medium | Medium | Each permission migration uses `Schema::hasTable('permissions')` guards and is reversible. Check the existing `permission:admin.tax_config` middleware wiring to confirm the schema shape before writing the migration. |
| Currency rates seeder pollutes prod when first deployed without admin review | Low | Low | Seeder defaults to manual-source, conservative GBP↔EUR/USD rates. Admin can override immediately via UI once R2.3 ships. |
| `AdminPanel.vue` tab registration breaks existing admin tabs | Medium | Medium | Browser-test the admin panel after EVERY entity's R*.2 PR (csjones smoke is mandatory). |
| Pest arch tests fail in CI due to forgotten allowlist entries when a PR adds a NEW consumer | Medium | High | Each entity's R*.1 PR ships with an explicit allowlist of every existing consumer. Subsequent PRs shrink the allowlist as they migrate. The test never has a "permissive" mode — every consumer is enumerated. |

---

## Execution

Plan saved to `docs/superpowers/plans/2026-05-22-sub-project-1-pass-2-reference-data.md`.

**Two execution options:**

**1. Subagent-Driven (recommended)** — dispatch a fresh subagent per PR (R4.1, R4.2, …), two-stage review per PR (spec-compliance + code-quality), admin-merge after CSJ approval. Best for the long PR cadence — keeps each PR's context fresh.

**2. Inline Execution** — execute PRs in-session using `superpowers:executing-plans`. Batched checkpoints at each entity's lock-down PR. Faster but heavier on context budget.

**Which approach?**

---

*End of plan.*
