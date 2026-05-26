# PensionStore

Canonical write boundary for `App\Models\DCPension`, `App\Models\DBPension`, `App\Models\StatePension`, and `App\Models\PensionInputHistory`. Every mutation of the four pension tables flows through this store, and every read by Retirement, Estate, Tax, Coordination, AI, Goals, Risk, NetWorth, Plans, Documents, Onboarding, and User-profile consumers funnels through the store's typed read methods.

**Entity key:** `pension_account` (constant `PensionStore::ENTITY_KEY`)
**Tables:** `dc_pensions`, `db_pensions`, `state_pensions`, `pension_input_history`
**Models:** `DCPension`, `DBPension`, `StatePension`, `PensionInputHistory`
**Base:** standalone (user-data store; **does not** extend `ReferenceDataStore`)
**Normaliser:** `App\Services\Stores\Normalisers\PensionNormaliser`
**Derived calculator:** `App\Services\Stores\Recalc\PensionDerivedColumnCalculator`
**Snapshot policies:** `App\Services\Stores\Snapshots\SnapshotPolicies` (per-column)

## Boundary

Lock test: `tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` (SP1 Pass 3 PR 8 — locked).

Allowed direct consumers of the four pension models:

### Canonical write/read set
- `App\Services\Stores\PensionStore` — the store itself
- `App\Services\Stores\Normalisers\PensionNormaliser` — folds form / Fyn / upload vocabularies onto the canonical shape
- `App\Services\Stores\Recalc\PensionDerivedColumnCalculator` — invoked only by `PensionStore::recalculate{Dc,Db,State}Derived()`; conceptually an internal of the store (no queries, no mutations)
- `App\Console\Commands\BackfillPensionDerivedColumns` — one-off migration-style backfill via `chunkById` + `forceFill`/`saveQuietly`; §14.2 console-command category

### Spec §14.2 permanent allowlist
- `App\Observers\DCPensionRiskObserver` — risk recalculation side effects
- `Database\Factories\{DCPension,DBPension,StatePension}Factory` — test fixtures
- `App\Models\{DC,DB,State}PensionValueSnapshot` — snapshot models, written exclusively by `PensionStore::recalculate*Derived()`
- `App\Models\\` — self-references + relationship definitions in sibling models
- `App\Events\Pension\{DC,DB}Pension{Created,Updated,Restored}` + `StatePensionUpserted` — typed event constructor params; dispatched only from the store
- `App\Providers\EventServiceProvider` — listener registration (event wiring, §14.2)
- `App\Http\Resources\DCPensionResource` — API resource (`@mixin` docblock only; no query)
- `App\Console\Commands\{EncryptExistingData,ResetPreviewData}` — controlled console commands
- `Database\Seeders\PreviewUserSeeder` / `LifecycleTestSeeder` — fixture/persona seeders (both pass `IngestSource::SEEDER` for creates)

### Documented residual NON-QUERY references
Retain a pension-model reference that is NOT a statically-resolvable query/mutation (class-name args, type hints, polymorphic `holdable_type` discriminators, field-mapper registry keys). The canonical contract still holds — none of these issue a query that bypasses the store:

- `App\Http\Controllers\Api\RetirementController` — `DCPension::class` as `holdable_type` for `Holding` rows in the private `seedHoldingsForDcPension` helper
- `App\Http\Controllers\Api\Retirement\DCPensionHoldingsController` — `Holding::where('holdable_type', DCPension::class)` polymorphic queries + a `?DCPension` return-type hint on `pensionForUserOr404`
- `App\Http\Controllers\Api\Retirement\DecumulationController` — DC/DB/State type hints on private projection helpers
- `App\Agents\CoordinatingAgent` — DC/DB/State `::class` as duplicate-checker args and entity-type-map values
- `App\Services\Documents\DocumentProcessor` — DC/DBPension `::class` as field-mapper registry keys + holdings-import type discriminator
- `App\Services\Retirement\PensionProjector`, `SalarySacrificeAnalyzer`, `RetirementActionDefinitionService`, `PensionContributionOptimizer` — type hints on public/private method signatures and closure callbacks
- `App\Services\Plans\RetirementPlanService` — DCPension type hints on `calculateMonthly{Employee,Employer}Contribution`
- `App\Services\UserProfile\ModuleDataRequirementsService` — pension type hints on field-presence helpers
- `App\Services\Documents\{DocumentTypeDetector,FieldMappers\DBPensionMapper,FieldMappers\DCPensionMapper,HoldingsImportService}` — type constant references + mapper-supports-target methods + a `?DCPension` return-type hint on `HoldingsImportService::matchPension`
- `App\Models\User` — pension relationship methods only (`hasMany dcPensions`, `hasMany dbPensions`, `hasOne statePension`)
- `App\Models\Investment\Holding` — polymorphic `holdable belongsTo DCPension`

### Out-of-sub-project-1-scope read / infra references
Read-only cache observers, async risk-recalc job — never in the Pass 3 read-consumer migration scope:

- `App\Observers\NetWorthCacheObserver`
- `App\Observers\RecommendationCacheObserver`
- `App\Jobs\RecalculateRiskProfileJob`

Adding a new direct-model consumer requires either routing through the store (preferred) or adding to this allowlist with written justification.

## Allowed ingest sources

`IngestSource::FORM` (HTTP form-validated via `Store{DC,DB}PensionRequest` / `UpdateStatePensionRequest`), `IngestSource::FYN_AI` (Fyn tool call), or `IngestSource::UPLOAD` (document extraction). `IngestSource::SEEDER` is accepted by the store but only legitimate from `PreviewUserSeeder` / `LifecycleTestSeeder`.

The active `IngestSource` is attached to every audit row via `AuditLog::withContext(['ingest_source' => $source->value], …)` (spec §8.1, locked in Pass 3 PR 8).

## Public API

### Writes — DC pension

| Method | Purpose |
|---|---|
| `createDc(array $data, User $user, IngestSource $source): DCPension` | Validate canonical shape, check `TierGate::canCreate('pension_account', …)` against combined (DC + DB) count, persist, recalculate derived columns + write value snapshots per policy, emit `DCPensionCreated`. |
| `updateDc(int $id, array $data, User $user, IngestSource $source): DCPension` | Primary-owner-only mutation. Fill before `getDirty()` so the changes diff is captured, save, recalculate derived columns + snapshots, emit `DCPensionUpdated` with `$changes` diff. |
| `updateOrCreateDc(array $match, array $data, User $user, IngestSource $source): DCPension` | Find by `$match` for `$user`; route to `updateDc` if found, otherwise to `createDc` with merged data. |
| `deleteDc(int $id, User $user, string $reason): void` | Primary-owner-only soft delete (`SoftDeletes`). Emits `DCPensionDeleted` with `$reason`. |
| `restoreDc(int $id, User $user): DCPension` | Restore a soft-deleted row. Primary-owner-only. Emits `DCPensionRestored`. |

### Writes — DB pension

| Method | Purpose |
|---|---|
| `createDb(array $data, User $user, IngestSource $source): DBPension` | Validate canonical shape, check tier-cap, persist, recalculate derived columns + snapshot, emit `DBPensionCreated`. |
| `updateDb(int $id, array $data, User $user, IngestSource $source): DBPension` | Primary-owner-only mutation with `$changes` diff. Emits `DBPensionUpdated`. |
| `deleteDb(int $id, User $user, string $reason): void` | Primary-owner-only soft delete. Emits `DBPensionDeleted`. |
| `restoreDb(int $id, User $user): DBPension` | Restore. Emits `DBPensionRestored`. |

### Writes — State pension (one per user)

| Method | Purpose |
|---|---|
| `upsertState(array $data, User $user, IngestSource $source): StatePension` | `updateOrCreate(['user_id' => $user->id], $data)`. State pension is semantically one-per-user, so this is the only write entry. Recalculates derived columns + snapshot. Emits `StatePensionUpserted` with `wasRecentlyCreated` boolean. Tier-cap is NOT enforced — the one-per-user nature is the natural cap. |

### Writes — Pension Input History (one row per user per tax year)

| Method | Purpose |
|---|---|
| `captureInputHistory(array $entries, User $user, IngestSource $source): array<string,float>` | `updateOrCreate(['user_id' => $user->id, 'tax_year' => …], ['pension_input_amount' => …])` per entry. Accepts either a flat `[['tax_year' => …, 'pension_input_amount' => …], …]` array or a `['entries' => […]]` envelope. Throws `StoreValidationException` if no valid entries provided. Emits `PensionInputHistoryCaptured` with the written `tax_year => amount` map. |

### Reads

| Method | Returns |
|---|---|
| `find(int $id, string $type, User $user): DCPension\|DBPension\|StatePension\|null` | By id + type (`'dc'\|'db'\|'state'`), scoped to `user_id = $user->id`. |
| `forUser(User $user): array{dc: Collection, db: Collection, state: ?StatePension, input_history: Collection}` | Every pension the user owns, grouped by type. DC has `holdings` eager-loaded. |
| `forUserByType(User $user, string $type): Collection` | Single-type read (e.g. all DCPension rows). |
| `statePension(User $user): ?StatePension` | The user's one State pension row, or null. |
| `pensionInputHistory(User $user, ?string $taxYear = null): Collection\|PensionInputHistory\|null` | All history rows ordered by `tax_year`, or one row by tax year if `$taxYear` provided. |

### What the store does NOT expose

Per spec §5.5, the store does **not** expose:
- Raw model classes — every read returns Eloquent collections / models scoped to `$user`; consumers never call `DCPension::query()` / `DBPension::query()` / `StatePension::query()` directly
- Raw query builders — no `whereSomething()` chain exits the store
- Mutation methods that bypass derived-column recalc or event emission
- Cross-user reads — there is no `forUsers(array)` entry point; pensions are individually owned (no `joint_owner_id` on any of the four tables)
- Soft-delete bypass (`forceDelete`, `withTrashed`-as-default) — `restoreDc` / `restoreDb` are the only entry points that see trashed rows; State and InputHistory don't use soft deletes

## Per-entity quirks

1. **Pensions are individually owned. There is no joint ownership.** Unlike `SavingsAccount` / `Property` / `Investment`, the four pension tables have NO `joint_owner_id` column. UK pensions are registered to one person; benefits to a spouse flow through the scheme (DB spouse pension percentage) or beneficiary nomination (DC), not via dual ownership at the data layer. All reads filter on `user_id = $user->id` only.

2. **Three pension types, one entity key, one tier cap.** `pension_account` covers DC + DB combined (spec §13). Free tier = 5 pension_account; tier1+ unlimited. State pension is NOT counted against the cap — `upsertState` skips `enforceTierCap` entirely because the one-per-user natural cap subsumes the tier-cap purpose. (Per `tests/Feature/Stores/PensionTierCapTest`.)

3. **State pension uses `updateOrCreate`, not `create`+`update`.** `upsertState(array $data, User $user, IngestSource $source)` is the only State write entry point. `wasRecentlyCreated` on the emitted `StatePensionUpserted` event tells listeners whether this was an insert or update.

4. **DC pension recalc uses `user.date_of_birth`.** `years_to_drawdown` = `retirement_age - currentAge`. If `date_of_birth` is null on the user, `years_to_drawdown` falls through to null (calculator is null-safe). Same for State pension's `years_to_state_pension_age`.

5. **DC pension `annual_contribution_gbp` has a two-stage fallback.** Calculator first tries `monthly_contribution_amount × 12`. If that is null/zero, falls back to `(employee% + employer%) × annual_salary`. If both are absent, contribution is null.

6. **`annual_allowance_used_gbp` is null-safe to TaxConfig failure.** `TaxConfigService::getPensionAllowances()` is wrapped in `try/catch` with `TaxDefaults::PENSION_ANNUAL_ALLOWANCE` fallback (mirrors the Savings ISA-allowance pattern).

7. **Derived columns are materialised inside the write transaction.** `recalculate{Dc,Db,State}Derived` run inside `DB::transaction(...)` in every write path so a snapshot-write failure rolls back the whole create — per the Savings precedent.

8. **Per-column snapshot policy.** `SnapshotPolicies::dcPensionFundValue()`, `dcPensionProjectedValue()`, `dbPensionAnnualValue()`, `statePensionForecast()` decide whether a `{DC,DB,State}PensionValueSnapshot` row is written. Null-derived means the metric is not applicable to the row (no `retirement_age`, no `expected_return_percent`, no `accrued_annual_pension`, etc.) — these are NOT snapshotted, otherwise the old-is-null → shouldSnapshot=true short-circuit would fire on every write.

9. **Pension fund values are stored in GBP today.** Pass 3 stores `current_fund_value` and `current_fund_value_gbp` as the same number — currency conversion for pensions is deferred to a later sub-project pass. The calculator comment documents this explicitly: `$currentGbp = (float) $pension->current_fund_value;`.

10. **Pension models pluralise wrong by default.** `DCPension` → `d_c_pensions` (Laravel treats consecutive caps as separate words). Every pension model sets `protected $table = '…'` explicitly. Snapshot models inherit the same pitfall — also set explicitly.

11. **Per-column MySQL index names are explicit short names.** Composite indexes on the three snapshot tables would otherwise blow the MySQL 64-char identifier limit (`dc_pension_value_snapshots_dc_pension_id_column_name_taken_at_index` is 65 chars). Migrations use short names like `dcpvs_id_column_taken_idx`.

12. **`captureInputHistory` returns the written map.** Earlier shape used `use (..., &$written)` to extract the count from inside a `DB::transaction` closure; wrapping with `AuditLog::withContext(fn () => …)` in PR 8 broke the by-reference capture, so the closure now returns `$written` and the caller assigns it. The same fix applied to `updateDc` / `updateDb` for the `$dirty` diff.

## Events

Per spec §11.1, twelve events are dispatched across the four entities (each on its own write path):

```php
// DC pension
DCPensionCreated(DCPension $pension, User $user, IngestSource $source)
DCPensionUpdated(DCPension $pension, array $changes, User $user, IngestSource $source)
DCPensionDeleted(int $pensionId, User $user, string $reason)
DCPensionRestored(DCPension $pension, User $user)

// DB pension
DBPensionCreated(DBPension $pension, User $user, IngestSource $source)
DBPensionUpdated(DBPension $pension, array $changes, User $user, IngestSource $source)
DBPensionDeleted(int $pensionId, User $user, string $reason)
DBPensionRestored(DBPension $pension, User $user)

// State pension (one-per-user; upsert merges create + update)
StatePensionUpserted(StatePension $state, User $user, IngestSource $source, bool $wasRecentlyCreated)

// Pension Input History (per-tax-year)
PensionInputHistoryCaptured(User $user, array $written, IngestSource $source)
```

Event tests: `tests/Unit/Services/Stores/PensionStoreEventsTest.php` — one `it()` per write path, asserting dispatch via `Event::fake()` + `Event::assertDispatched()`.

Consumers register listeners in `EventServiceProvider`. Per spec §11.3, listeners do **not** call store write methods on the entity that triggered them.

## Validation policy

Two-layer (spec §7):

- **Outer (HTTP):** `StoreDCPensionRequest`, `UpdateDCPensionRequest`, `StoreDBPensionRequest`, `UpdateDBPensionRequest`, `UpdateStatePensionRequest` (form-request rules; user-facing error messages)
- **Inner (store):** `PensionStore::validate{Dc,Db,State}Canonical()` (canonical-shape sanity; throws `StoreValidationException` with field-keyed error arrays; never user-facing)

For the three non-HTTP ingest paths (`FYN_AI`, `UPLOAD`, `SEEDER`), the normaliser is responsible for producing a payload that satisfies the inner rules — there is no outer HTTP layer to lean on. The store treats every ingest path identically once the canonical shape is in hand.

## Audit, encryption, security

- **Audit (spec §8.1):** every write happens inside `AuditLog::withContext(['ingest_source' => $source->value], …)` so audit rows capture the originating ingest path. Audit row schema: `user_id`, `model_type`, `model_id`, `action` (`created|updated|deleted|restored`), `old_values`, `new_values`, `metadata['ingest_source']`.
- **Encryption at rest:** the four pension tables have no encrypted columns currently; encryption is a §8.2 concern that applies to other entities (e.g. policies, beneficiaries).
- **Authorisation:** primary-owner-only — every read filters `user_id = $user->id`, every write requires the row's `user_id` match. There are no joint-aware paths (see quirk #1). Preview-user isolation is a route-level concern (`PreviewWriteInterceptor`), not a store responsibility (CLAUDE.md Rule #8).

## Currency normalisation

Per spec §9, Pass 3 stores pension fund values in GBP only — `current_fund_value` and `current_fund_value_gbp` carry the same number. Multi-currency pensions and FX-aware derived columns are deferred to a later sub-project pass. Single source of truth for FX rates when this lands is `CurrencyRateStore::latestFor()` — the same hook `SavingsStore` already uses.

## Migration history (SP1 Pass 3 — all merged to `dev`)

- **PR 1 (#377)** — introduce `PensionStore` facade + arch boundary + `PensionNormaliser` + ten events + `IngestSource` enum reuse.
- **PR 2 (#378)** — point HTTP form requests at `PensionStore` (`RetirementController` + form requests removed from allowlist).
- **PR 3 (#379)** — point Fyn AI write tools at `PensionStore` (`CoordinatingAgent::handle{Create,Update}{Dc,Db}Pension` + State + InputHistory routed through the store; only the non-query `class` references remain).
- **PR 4 (#381)** — point upload + seeders at `PensionStore` (`DocumentProcessor` DC write path + persona seeders).
- **PR 5a (PR #382 sub)** — Retirement domain reads via `PensionStore`.
- **PR 5b (PR #382 sub)** — Plans + Coordination reads.
- **PR 5c (PR #382 sub)** — Estate + NetWorth reads.
- **PR 5d (PR #382 sub)** — Tax strategies reads.
- **PR 5e (PR #382 sub)** — AI + UserProfile + Risk reads.
- **PR 5f (PR #382 sub)** — Documents + Onboarding + Eval reads.
- **PR 5g (PR #382 sub)** — `CoordinatingAgent` residual read consumers.
- **PR 6 (#383)** — materialise canonical derived columns (`current_fund_value_gbp`, `projected_value_at_retirement_gbp`, `annual_contribution_gbp`, `years_to_drawdown`, `annual_allowance_used_gbp` for DC; `projected_annual_pension_at_nra_gbp`, `spouse_pension_projected_gbp` for DB; `state_pension_forecast_annual_gbp`, `ni_completion_pct`, `years_to_state_pension_age` for State) + three snapshot tables + per-column `SnapshotPolicy` + `BackfillPensionDerivedColumns` console command.
- **PR 7 (#384)** — tier-cap test (`PensionTierCapTest`) locking the spec §13 contract. Enforcement seam itself shipped in PR 1.
- **PR 8 (#384)** — boundary allowlist locked (transition language dropped; entries recategorised into canonical / §14.2 permanent / documented residual / out-of-scope) + audit captures `ingest_source` via `AuditLog::withContext` on every write path + `PensionAuditIngestSourceTest`.

## Acceptance criteria (spec §16)

Per-entity acceptance (§16.1) — all met:
- One store, ten events, normaliser, boundary test: **shipped (PR 1, PR 8)**
- Three ingest paths converge: **shipped (PR 2 / PR 3 / PR 4)**
- Three-ingest canonical parity test: **shipped (`PensionThreeIngestParityTest` — DC parity + DB parity via form / fyn / upload)**
- Audit completeness — `ingest_source` on every write: **shipped (PR 8 + `PensionAuditIngestSourceTest`)**
- Derived columns materialised + snapshot policy: **shipped (PR 6)**
- Tier-cap enforcement: **shipped (PR 1 seam + PR 7 test + `TierConfigurationSeeder` cap value)**
- Boundary locked (no transition entries): **shipped (PR 8)**

Sub-project-wide acceptance (§16.2.5) — **this doc closes the per-entity documentation requirement for Pensions.**
