# SavingsStore

Canonical write boundary for `App\Models\SavingsAccount`. Every mutation of `savings_accounts` flows through this store, and every joint-aware read by Savings, Estate, Retirement, Tax, Investment, AI, Coordination, Goals, NetWorth, Plans, Mobile, and User-profile consumers funnels through `find()` / `forUser()` / `forUsers()` / `findMany()` / `forUserWithJointOwner()`.

**Entity key:** `savings_account` (constant `SavingsStore::ENTITY_KEY`)
**Table:** `savings_accounts`
**Model:** `App\Models\SavingsAccount`
**Base:** standalone (user-data store; **does not** extend `ReferenceDataStore`)
**Normaliser:** `App\Services\Stores\Normalisers\SavingsAccountNormaliser`
**Derived calculator:** `App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator`
**Snapshot policies:** `App\Services\Stores\Snapshots\SnapshotPolicies` (per-column)

## Boundary

Lock test: `tests/Architecture/StoreBoundary/SavingsStoreBoundaryTest.php` (SP1 Pass 1 PR 8 — locked).

Allowed direct consumers of `App\Models\SavingsAccount`:

### Canonical write/read set
- `App\Services\Stores\SavingsStore` — the store itself
- `App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator` — invoked only by `SavingsStore::recalculateDerived()`; conceptually an internal of the store (no queries, no mutations)

### Spec §14.2 permanent allowlist
- `App\Observers\SavingsAccountGoalObserver` — goal-link side effects
- `App\Observers\SavingsAccountRiskObserver` — risk recalculation
- `App\Models\\` — self-references + relationship definitions (`Goal`, `SavingsGoal`, `User`, `SavingsAccountValueSnapshot belongsTo`)
- `Database\Factories\SavingsAccountFactory` — test fixtures
- `App\Http\Resources\SavingsAccountResource` — API resource
- `App\Events\Savings\SavingsAccountCreated` / `SavingsAccountUpdated` / `SavingsAccountRestored` — typed event constructor params
- `App\Console\Commands\ResetPreviewData` / `SendSavingsAlerts` / `EncryptExistingData` — controlled console commands
- `App\Console\Commands\BackfillSavingsDerivedColumns` — one-off migration-style backfill
- `Database\Seeders\PreviewUserSeeder` / `LifecycleTestSeeder` — fixture/persona seeders

### Documented residual NON-QUERY references
Retain a `SavingsAccount` reference that is NOT a statically-resolvable query/mutation (class-name args, type hints, relationship defs, polymorphic dispatch). The canonical contract still holds — none of these issue a `SavingsAccount` query that bypasses the store:

- `App\Agents\CoordinatingAgent` — `SavingsAccount::class` as a duplicate-checker argument and entity type-map value
- `App\Services\Savings\ISATracker` — `SavingsAccount` type hint on `calculateProjectedSubscription(SavingsAccount $account)`; all six prior query sites migrated
- `App\Models\Goal` — `SavingsAccount::class` in `belongsTo` / `belongsToMany` definitions
- `App\Services\Coordination\HouseholdPlanningService` — polymorphic `$modelClass::where(...)` sweep over an asset-type list; two direct sites migrated, sweep is OoS
- `App\Services\Documents\DocumentProcessor` — `SavingsAccount::class` as a field-mapper key
- `App\Services\Goals\LifeEventAllocationService` — `?SavingsAccount` return type + `instanceof` check

### Out-of-sub-project-1-scope read / infra references
Read-only or framework-infra usages kept allowlisted; a future sub-project may route them through the store:

- `App\Providers\EventServiceProvider`
- `App\Models\User`, `App\Models\SavingsGoal`
- `App\Http\Controllers\Api\Plans\PlanController`
- `App\Services\Savings\RateComparator`, `LiquidityAnalyzer`
- `App\Services\UserProfile\PersonalAccountsService`, `UserProfileService`
- `App\Services\Documents\DocumentTypeDetector`, `FieldMappers\SavingsAccountMapper`
- `App\Services\Eval\EvalHttpDriver`
- `App\Services\NetWorth\NetWorthService`
- `App\Services\Risk\AutoRiskCalculator`

Adding a new direct-model consumer requires either routing through the store (preferred) or adding to this allowlist with written justification.

## Allowed ingest sources

`IngestSource::FORM` (HTTP form-validated), `IngestSource::FYN_AI` (Fyn tool call), or `IngestSource::UPLOAD` (document extraction). Reference-data sources (`ADMIN`, `SEEDER`) are not rejected by the store — `PreviewUserSeeder` and `LifecycleTestSeeder` pass `IngestSource::SEEDER` for fixture creation — but the canonical user-write paths are the first three.

The active `IngestSource` is attached to every audit row via `AuditLog::withContext(['ingest_source' => $source->value], …)` (spec §8.1, locked in PR 8).

## Public API

### Writes

| Method | Purpose |
|---|---|
| `create(array $data, User $user, IngestSource $source): SavingsAccount` | Validate canonical shape, check `TierGate::canCreate`, persist, recalculate derived columns, write per-column value snapshots per policy, emit `SavingsAccountCreated`. |
| `update(int $id, array $data, User $user, IngestSource $source): SavingsAccount` | Primary-owner-only mutation. Fill before `getDirty()` so the changes diff is captured, save, recalculate derived columns, write snapshots, emit `SavingsAccountUpdated` with `$changes` diff. |
| `updateOrCreate(array $match, array $data, User $user, IngestSource $source): SavingsAccount` | Find by `$match` for `$user`; route to `update` if found, otherwise to `create` with merged data. |
| `delete(int $id, User $user, string $reason): void` | Primary-owner-only soft delete (model uses `SoftDeletes`). Emits `SavingsAccountDeleted` with `$reason`. |
| `restore(int $id, User $user): SavingsAccount` | Restore a soft-deleted row. Primary-owner-only. Emits `SavingsAccountRestored`. |

### Reads (joint-aware)

| Method | Returns |
|---|---|
| `find(int $id, User $user): ?SavingsAccount` | By id, scoped to `user_id = $user->id OR joint_owner_id = $user->id`. |
| `forUser(User $user): Collection<SavingsAccount>` | All accounts where the user is primary or joint owner. Uses `SavingsAccount::forUserOrJoint($user->id)` scope. |
| `forUserWithJointOwner(User $user): Collection<SavingsAccount>` | Same as `forUser`, with `jointOwner` relation eager-loaded. Used by AI existing-records prompt (no `joint_owner_name` column; spec §8 — `preventLazyLoading` on staging). |
| `forUsers(array $userIds): Collection<SavingsAccount>` | Multi-user joint-aware read for household/multi-user contexts (e.g. `RetirementIncomeService`). |
| `findMany(array $ids, User $user): Collection<SavingsAccount>` | User-scoped joint-aware id-based read. Returns empty `Collection` for unknown ids or ids belonging to other users — prevents cross-user leakage when callers pass externally-supplied id arrays (e.g. allocation payloads from HTTP requests). |

### What the store does NOT expose

Per spec §5.5, the store does **not** expose:
- Raw model classes — every read returns Eloquent collections / models scoped to `$user`; consumers never call `SavingsAccount::query()` directly
- Raw query builders — no `whereSomething()` chain exits the store
- Mutation methods that bypass derived-column recalc or event emission
- Cross-user reads except via the explicit multi-user `forUsers(array)` entry point
- Soft-delete bypass (`forceDelete`, `withTrashed`-as-default) — `restore()` is the only entry point that sees trashed rows

## Per-entity quirks

1. **Joint ownership uses one row, not two.** The spouse's share is derived as `(100 - ownership_percentage)`. There is no second `SavingsAccount` row for the joint owner. Every read funnels through `forUserOrJoint` to surface accounts where the user is either `user_id` or `joint_owner_id`. (CLAUDE.md Rule #7.)

2. **Joint owners cannot mutate.** `update()` / `delete()` / `restore()` all filter by `user_id = $user->id` (primary owner only). Joint owners get read-only access via `find()` / `forUser()`. Matches the pre-store `SavingsController` contract — do not relax without spec sign-off.

3. **Joint ISAs are illegal.** `is_isa = true` plus a `joint_owner_id` is rejected upstream. ISAs are individual-only under UK law; the store does not enforce this directly (validation lives in `StoreSavingsAccountRequest` / `UpdateSavingsAccountRequest`), but callers that construct payloads via the normaliser must respect this. (See memory `feedback_joint_isa_illegal.md`.)

4. **Ownership type is `individual|joint|trust` — `tenants_in_common` is property-only.** The `validateCanonical` rule deliberately excludes `tenants_in_common`. Do not "fix" this to match property's ownership set. (See memory `reference_tenants_in_common_is_property_only.md`.)

5. **Derived columns are materialised inside the write transaction.** `balance_gbp`, `annual_interest_projected_gbp`, `isa_allowance_used_pct` are recalculated by `SavingsAccountDerivedColumnCalculator` and persisted alongside `*_calculated_at` timestamps. Null-derived means the metric is not applicable to the row (no `interest_rate`, not an ISA, etc.) — these are NOT snapshotted, otherwise the old-is-null → shouldSnapshot=true short-circuit would fire on every write.

6. **Per-column snapshot policy.** `SnapshotPolicies::savingsAccountBalance()`, `savingsAnnualInterestProjected()`, `savingsIsaAllowanceUsedPct()` decide whether a `SavingsAccountValueSnapshot` row is written. Snapshots carry `trigger_reason` (`'create'` / `'update'`) and `ingest_source`. Policy lives outside the store so it can be swapped without touching write logic.

7. **Tier-cap is enforced at `create()` only.** `TierGate::canCreate($user, 'savings_account', $currentCount)` runs before persistence; on failure throws `TierLimitExceededException` with the hard limit. `update()` / `delete()` / `restore()` do not re-check (a row that exists already cleared the gate). Tier-gate behaviour itself is governed by SP2 (freemium); SP1 only provides the enforcement hook. (Spec §13, PR 7.)

8. **`validateCanonical()` mirrors the form-request rules, not a stricter gate.** Inner-layer validation is a canonical-shape sanity check — it does not tighten what the outer HTTP request layer already enforces (spec §7.2). If you change form-request rules, mirror them here.

9. **No DB-level uniqueness on `(user_id, account_name)`.** Multiple accounts at the same institution with the same name are valid (e.g. two ISA cycles). Deduplication is a behavioural concern of `CoordinatingAgent::checkForDuplicate(SavingsAccount::class, …)`, not a schema constraint.

10. **Soft-delete is the deletion contract.** `delete()` calls Eloquent `delete()` against a model with `SoftDeletes`. `forceDelete` is not exposed. `restore()` is the only way back. The boundary test deliberately does not allow `withTrashed`-default reads — `restore()` opens that window only for the single id it operates on.

## Events

Per spec §11.1, four events are dispatched (each on its own write path):

```php
SavingsAccountCreated(SavingsAccount $entity, User $user, IngestSource $source)
SavingsAccountUpdated(SavingsAccount $entity, array $changes, User $user, IngestSource $source)
SavingsAccountDeleted(int $entityId, User $user, string $reason)
SavingsAccountRestored(SavingsAccount $entity, User $user)
```

Event tests: `tests/Unit/Services/Stores/SavingsStoreEventsTest.php` — one `it()` per event, asserting dispatch via `Event::fake()` + `Event::assertDispatched()`.

Consumers register listeners in `EventServiceProvider`. Per spec §11.3, listeners do **not** call store write methods on the entity that triggered them.

## Validation policy

Two-layer (spec §7):

- **Outer (HTTP):** `StoreSavingsAccountRequest`, `UpdateSavingsAccountRequest` (form-request rules; user-facing error messages)
- **Inner (store):** `SavingsStore::validateCanonical()` (canonical-shape sanity; throws `StoreValidationException` with field-keyed error arrays; never user-facing)

For the three non-HTTP ingest paths (`FYN_AI`, `UPLOAD`, `SEEDER`), the normaliser is responsible for producing a payload that satisfies the inner rules — there is no outer HTTP layer to lean on. The store treats every ingest path identically once the canonical shape is in hand.

## Audit, encryption, security

- **Audit (spec §8.1):** every write happens inside `AuditLog::withContext(['ingest_source' => $source->value], …)` so audit rows capture the originating ingest path. Audit row schema: `user_id`, `entity_type='savings_account'`, `entity_id`, `action` (`create|update|delete|restore`), `payload`, `ingest_source`.
- **Encryption at rest:** `SavingsAccount` has no encrypted columns currently; encryption is a §8.2 concern that applies to other entities (e.g. policies, beneficiaries).
- **Authorisation:** primary-owner-only writes; joint-aware reads; cross-user reads only via the explicit `forUsers(array)` entry point. Preview-user isolation is a route-level concern (`PreviewWriteInterceptor`), not a store responsibility (CLAUDE.md Rule #8).

## Currency normalisation

Per spec §9, balances are stored in their source currency on the row and a derived `balance_gbp` column carries the GBP-converted value (FX rate sourced from `CurrencyRateStore` at write time). The store does **not** convert at read time — consumers that need a display-currency view do their own conversion via `CurrencyDisplayService`. Single source of truth for rates is `CurrencyRateStore::latestFor()`.

## Migration history (SP1 Pass 1 — all merged to `dev`)

- **PR 1 (#305)** — introduce `SavingsStore` facade + arch boundary + `SavingsAccountNormaliser` + four events.
- **PR 2 (#306)** — point HTTP form requests at `SavingsStore` (`SavingsController` removed from allowlist).
- **PR 3 (#307)** — point Fyn AI write tools at `SavingsStore` (`CoordinatingAgent::handleCreateSavingsAccount` / `handleUpdateSavingsAccount` routed through the store; only the non-query `class` references remain).
- **PR 4 (#308)** — point upload + seeders at `SavingsStore` (`DocumentProcessor` write paths + persona seeders).
- **PR 5a (#309)** — net-worth + mobile dashboard reads via `SavingsStore`.
- **PR 5b (#310)** — Estate / IHT consumers via `SavingsStore`.
- **PR 5c-1 (#312)** — Plans cluster.
- **PR 5c-2 (#313)** — Retirement cluster (closes `findManyById` data-leak path in review fixes #311).
- **PR 5d (#314)** — Tax strategies cluster.
- **PR 5e (#315)** — Investment ISA consumers.
- **PR 5f (#316)** — Coordination + Goals cluster.
- **PR 5g (#318)** — AI prompt + profile cluster.
- **PR 5h (#319)** — Agents + savings-internal cluster.
- **PR 6 (#321)** — materialise canonical derived columns (`balance_gbp`, `annual_interest_projected_gbp`, `isa_allowance_used_pct`) + snapshot table + per-column `SnapshotPolicy`.
- **PR 7 (#322)** — tier-cap enforcement point + `StaticTierGate` (enforcement deferred to SP2 freemium).
- **PR 8 (#323)** — boundary allowlist locked + audit captures `ingest_source` via `AuditLog::withContext`.

## Acceptance criteria (spec §16.2)

Per-entity acceptance (§16.2.1) — all met:
- One store, four events, normaliser, boundary test: **shipped (PR 1, PR 8)**
- Three ingest paths converge: **shipped (PR 2 / PR 3 / PR 4)**
- Derived columns materialised + snapshot policy: **shipped (PR 6)**
- Tier-cap enforcement hook: **shipped (PR 7)**
- Boundary locked (no transition entries): **shipped (PR 8)**
- Three-ingest canonical parity test: **shipped (PR #324)**

Sub-project-wide acceptance (§16.2.5) — **this doc closes the per-entity documentation requirement.**
