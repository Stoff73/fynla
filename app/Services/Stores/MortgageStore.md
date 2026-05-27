# MortgageStore

Canonical write boundary for `App\Models\Mortgage`. Every mutation of the `mortgages` table flows through this store, and every joint-aware read by controllers, agents, services, and document-processing consumers funnels through the store's typed read methods.

**Entity key:** `mortgage` (constant `MortgageStore::ENTITY_KEY`)
**Table:** `mortgages`
**Model:** `App\Models\Mortgage`
**Base:** standalone (user-data store; **does not** extend `ReferenceDataStore`)
**Normaliser:** `App\Services\Stores\Normalisers\MortgageNormaliser`
**Derived calculator:** `App\Services\Stores\Recalc\MortgageDerivedColumnCalculator`
**Snapshot policies:** `App\Services\Stores\Snapshots\SnapshotPolicies` — `mortgageBalance()`, `mortgageRate()` (PR 6)
**Snapshot table:** `mortgage_value_snapshots` (PR 6)

## Boundary

Lock test: `tests/Architecture/StoreBoundary/MortgageStoreBoundaryTest.php` (SP1 Pass 5 PR 8 — LOCKED).

### Allowlist — legitimate exceptions

The three entries below are the ONLY files that may reference `Mortgage::create|update|save|delete|forceDelete|restore` outside the store. Every other direct write is a violation.

| File | Classification | Justification |
|---|---|---|
| `app/Console/Commands/EncryptExistingData.php` | Spec §14.2 console-command | Pre-existing encryption backfill. Uses `Mortgage::chunkById` + `forceFill`/`saveQuietly`. Not a runtime write path. |
| `app/Console/Commands/ResetPreviewData.php` | Spec §14.2 console-command | Admin-only persona data reset. Controlled scheduled command. Not a runtime write path. |
| `database/seeders/PreviewUserSeeder.php` | Spec §14.2 seeder | `deleteUserData()` uses `Mortgage::where('user_id', $userId)->delete()` for bulk pre-seed cleanup. Equivalent treatment to `PropertyStoreBoundaryTest`: PropertyStore allows the same seeder for the same reason ("resetPersonaData() retains a bulk-delete path outside the ingest boundary"). Migrating to per-record `MortgageStore::delete` would generate audit-log noise (one row per mortgage per persona) and requires a new bulk-cleanup store method out of scope for PR 8. Kept permanently allowlisted. |

Adding a new direct-model consumer requires either routing through the store (preferred) or adding to this allowlist with written justification. Removing any entry whose file still references `Mortgage` will turn the suite RED — the entry is load-bearing.

## Allowed ingest sources

| Source | Meaning |
|---|---|
| `IngestSource::FORM` | HTTP form-validated via `StoreMortgageRequest` / `UpdateMortgageRequest` |
| `IngestSource::FYN_AI` | Fyn tool call (`CoordinatingAgent::handleCreateMortgage` / `handleUpdateMortgage`) |
| `IngestSource::UPLOAD` | Document extraction via `DocumentProcessor` + `MortgageMapper` |
| `IngestSource::SEEDER` | Preview / lifecycle persona fixture seeders only (`PreviewUserSeeder`) |
| `IngestSource::ADMIN` | Reserved; not currently used for mortgages (future admin-panel override path) |

The active `IngestSource` is attached to every audit row via `AuditLog::withContext(['ingest_source' => $source->value], …)` (spec §8.1). Locked by `MortgageAuditIngestSourceTest` (PR 8).

## Public API

### Reads (7 methods)

| Method | Returns | Joint-aware? |
|---|---|---|
| `find(int $id, User $user): ?Mortgage` | Single mortgage by id, scoped to `user_id = $user->id OR joint_owner_id = $user->id`. Returns `null` if not found or not accessible. | Yes |
| `forUser(User $user): Collection` | All mortgages where the user is primary or joint owner. Uses `Mortgage::forUserOrJoint($user->id)` scope. | Yes |
| `forUserPrimaryOnly(User $user): Collection` | All mortgages where `user_id = $user->id` only. Does NOT include mortgages where the user is `joint_owner_id`. Used by tier-cap enforcement (a user's joint mortgages do not count against their cap). | No |
| `forUserWithJointOwner(User $user): Collection` | Same as `forUser`, with the `jointOwner` relation eager-loaded. Used by views that render joint-owner display names. | Yes |
| `forProperty(int $propertyId, ?User $user): Collection` | All mortgages for a specific property. When `$user` is supplied, additionally scoped to `user_id = $user->id OR joint_owner_id = $user->id`. | Conditional |
| `forUserByProperty(User $user): Collection` | Returns `forUserWithJointOwner` grouped by `property_id`. Used by dashboard aggregators that render mortgages per property. | Yes |
| `findMany(array $ids, User $user): Collection` | Joint-aware id-based batch read. Returns only ids accessible to `$user` — prevents cross-user leakage when callers pass externally-supplied id arrays. Returns empty `Collection` for unknown ids or ids belonging to other users. | Yes |

### Writes (5 methods)

| Method | Purpose |
|---|---|
| `create(array $canonical, User $user, IngestSource $source): Mortgage` | Validate canonical shape via `validateCanonical()` (full), check `TierGate::canCreate('mortgage', …)` against `forUserPrimaryOnly` count, persist inside `DB::transaction`, recalculate derived columns + write value snapshots per policy, emit `MortgageCreated`. Entire operation (including snapshot writes) wrapped in `AuditLog::withContext`. |
| `update(int $id, array $canonical, User $user, IngestSource $source): Mortgage` | Primary-owner-only mutation (`user_id = $user->id`). Partial validate, fill before `getDirty()` so the changes diff is captured, save, recalculate derived columns + snapshots, emit `MortgageUpdated` with `$changes` diff. Wrapped in `AuditLog::withContext`. |
| `updateOrCreate(array $canonical, User $user, IngestSource $source): Mortgage` | Find by `(user_id, property_id, lender_name)` for `$user`; update if found (with diff), create if not. Idempotent for seeders. Tier-cap enforced on the create path only. Full validate. Wrapped in `AuditLog::withContext`. |
| `delete(int $id, User $user, IngestSource $source, bool $force = false): void` | Primary-owner-only soft delete (or `forceDelete` when `$force = true`). Emits `MortgageDeleted`. Wrapped in `AuditLog::withContext`. |
| `restore(int $id, User $user, IngestSource $source): Mortgage` | Restore a soft-deleted row (primary-owner-only). No-op if already live. Emits `MortgageRestored`. Wrapped in `AuditLog::withContext`. |

### What the store does NOT expose

Per spec §5.5, the store does **not** expose:

- Raw model classes — every read returns Eloquent collections or models scoped to `$user` (or `$propertyId`); consumers never call `Mortgage::query()` directly.
- Raw query builders — no `whereSomething()` chain exits the store.
- Mutation methods that bypass derived-column recalc or event emission.
- The internal `forUserPrimaryOnly` is public only because the tier-cap path is conceptually user-facing (a consumer wanting primary-only records should call `forUser` and filter by `user_id`); the tier-cap enforcement itself calls `forUserPrimaryOnly` via the store, not around it.
- Soft-delete bypass (`forceDelete` as default, `withTrashed`-as-default) — `restore()` is the only entry point that sees trashed rows.

## Joint-aware contract

Joint ownership uses a **SINGLE record**. `joint_owner_id` identifies the second owner; `ownership_percentage` is the primary owner's share. The spouse's share is `(100 - ownership_percentage)`. There is no second `Mortgage` row for the joint owner.

| Method | Scope |
|---|---|
| `forUser` | `user_id = ? OR joint_owner_id = ?` — joint-aware; the canonical read for most consumers |
| `forUserWithJointOwner` | Same as `forUser` + `with('jointOwner')` |
| `forProperty` (with user) | `property_id = ? AND (user_id = ? OR joint_owner_id = ?)` |
| `forUserByProperty` | Delegates to `forUserWithJointOwner`, then `groupBy('property_id')` |
| `find` | `forUserOrJoint($user->id)->find($id)` — joint-aware |
| `findMany` | `forUserOrJoint($user->id)->whereIn('id', $ids)` — joint-aware |
| `forUserPrimaryOnly` | `user_id = ?` only — **not** joint-aware; used for tier-cap counting |

(CLAUDE.md Rule #7. Locked by `MortgageReadConsumerParityTest`.)

## Derived columns

Three derived columns are materialised inside every write transaction by `MortgageDerivedColumnCalculator` (PR 6):

| Column | Derivation |
|---|---|
| `outstanding_balance_gbp` | `outstanding_balance` converted to GBP. GBP-only in Pass 5 (no FX yet); equals `outstanding_balance`. |
| `monthly_payment_gbp` | `monthly_payment` converted to GBP. Same: equals `monthly_payment` in Pass 5. |
| `current_ltv_pct` | `(outstanding_balance / property.current_value) * 100` rounded to 2 dp. `null` when `property_id` is null or property value is zero. |

**Recalc lifecycle:** `MortgageDerivedColumnCalculator::recalculate()` is called from the private `recalculateDerived(Mortgage $mortgage, ?Mortgage $previous)` helper on every `create`, `update`, `updateOrCreate`, and `restore`. It runs **inside** `DB::transaction`, so a calculator or snapshot-write failure rolls back the whole operation. The `$previous` snapshot is used to detect threshold-crossing for snapshot policies.

**Cross-store recalc:** after `recalculateDerived` runs, the events emitted by the store (`MortgageCreated`, `MortgageUpdated`, `MortgageDeleted`, `MortgageRestored`) are consumed by `RecalculatePropertyOutstandingMortgage` listener, which recomputes `properties.outstanding_mortgage` as the canonical sum of all mortgage `outstanding_balance` values for the property. See "Cross-store recalc contract" below.

## Snapshot policies

`SnapshotPolicies` wires two mortgage-specific policies (PR 6). Both use a 2555-day (7-year) retention window.

| Policy | Trigger predicate |
|---|---|
| `mortgageBalance()` | Emit a `mortgage_value_snapshots` row when `outstanding_balance` changes by **at least £1,000** OR **at least 0.5 % relative** (whichever fires first). Null-old skips snapshot (first create from null produces no snapshot — avoids the null→any short-circuit). |
| `mortgageRate()` | Emit a `mortgage_value_snapshots` row when `interest_rate` changes by **at least 0.25 percentage points**. Null-old skips snapshot. |

`MortgageValueSnapshot` records carry: `mortgage_id`, `snapshot_type` (`'mortgageBalance'` or `'mortgageRate'`), `value`, `snapshotted_at`.

## Tier-cap

| Attribute | Value |
|---|---|
| Entity key | `'mortgage'` (`MortgageStore::ENTITY_KEY`) |
| Free-tier cap | 10 (seeded by `TierConfigurationSeeder`) |
| tier1+ cap | `null` (unlimited) |
| Counted by | `Mortgage::where('user_id', $user->id)->count()` — primary ownership only; joint mortgages do **not** count against the joint owner's cap |
| Enforced at | `create()` and `updateOrCreate()` (new-record path only); `update()` / `delete()` / `restore()` do not re-check |
| Exception | `TierLimitExceededException($entityKey, $currentCount, $hardLimit)` |

Locked by `MortgageTierCapTest` (PR 7).

## Cross-store recalc contract

Mortgage writes trigger a **one-way** recalculation of `properties.outstanding_mortgage`:

```
MortgageStore::create / update / delete / restore
  → event(MortgageCreated | MortgageUpdated | MortgageDeleted | MortgageRestored)
    → RecalculatePropertyOutstandingMortgage listener
      → PropertyStore::recalculateDerivedForPropertyId($propertyId)
        → properties.outstanding_mortgage = SUM(mortgages.outstanding_balance WHERE property_id = ?)
        → properties.equity_gbp / loan_to_value_pct recalculated
```

**Loop prevention:** `PropertyStore::recalculateDerivedForPropertyId` uses `saveQuietly()` to update the property row — this bypasses Eloquent model events and does not dispatch `PropertyUpdated`. There is no `RecalculateMortgage…` listener registered on Property events, so there is no feedback loop.

The recalc runs **outside** the MortgageStore transaction (in the listener, after the event is dispatched) so a property-recalc failure does not roll back the mortgage write. If the property row is missing or `property_id` is null, the listener is a no-op.

## Events

Per spec §11.1, four events are dispatched (each on its own write path):

```php
MortgageCreated(Mortgage $mortgage, User $user, IngestSource $source)
MortgageUpdated(Mortgage $mortgage, array $changes, User $user, IngestSource $source)
MortgageDeleted(Mortgage $mortgage, User $user, IngestSource $source, bool $force)
MortgageRestored(Mortgage $mortgage, User $user, IngestSource $source)
```

Consumers register listeners in `EventServiceProvider`. Per spec §11.3, listeners do **not** call store write methods on the entity that triggered them.

## Validation policy

Two-layer (spec §7):

- **Outer (HTTP):** `StoreMortgageRequest`, `UpdateMortgageRequest` (form-request rules; user-facing error messages)
- **Inner (store):** `MortgageStore::validateCanonical()` (canonical-shape sanity; throws `StoreValidationException` with field-keyed error arrays; never user-facing)

For the three non-HTTP ingest paths (`FYN_AI`, `UPLOAD`, `SEEDER`), the normaliser is responsible for producing a payload that satisfies the inner rules. The store treats every ingest path identically once the canonical shape is in hand.

**Ownership-type enum (inner rules):** `in:individual,joint`. `tenants_in_common` is explicitly excluded — mortgages do not support it. The normaliser coerces TIC to joint before the store receives the payload; if TIC somehow reached `validateCanonical` directly, the `in:individual,joint` rule would reject it. Locked by `MortgageThreeIngestParityTest` case 2.

## Audit, encryption, security

- **Audit (spec §8.1):** every write happens inside `AuditLog::withContext(['ingest_source' => $source->value], …)` so audit rows capture the originating ingest path. Audit row schema: `user_id`, `model_type`, `model_id`, `action` (`created|updated|deleted|restored`), `old_values`, `new_values`, `metadata['ingest_source']`. Locked by `MortgageAuditIngestSourceTest` (PR 8).
- **Encryption at rest:** `Mortgage` has no encrypted columns currently; encryption is a §8.2 concern.
- **Authorisation:** primary-owner-only writes; joint-aware reads. Preview-user isolation is a route-level concern (`PreviewWriteInterceptor`), not a store responsibility (CLAUDE.md Rule #8).

## Per-entity quirks

1. **Joint ownership uses a SINGLE record.** `joint_owner_id` identifies the second owner; `ownership_percentage` is the primary owner's share. The joint owner's share is `(100 - ownership_percentage)`. There is no second `Mortgage` row for the joint owner. Every joint-aware read funnels through `forUserOrJoint`. (CLAUDE.md Rule #7.)

2. **`tenants_in_common` is property-only.** Mortgages do NOT support `ownership_type = 'tenants_in_common'`. `MortgageNormaliser::normalise()` coerces TIC → `'joint'` at the boundary. `validateCanonical()` enforces `in:individual,joint` as the only valid values. Locked by `MortgageThreeIngestParityTest` case 2. (CLAUDE.md Rule #5.)

3. **`ownership_percentage` defaults to 100 for `individual` and to 50 for `joint`.** Both defaults are applied by `MortgageNormaliser::normalise()` when the field is absent or null. TIC input (coerced to joint) inherits the caller-supplied percentage if present, otherwise defaults to 50.

4. **Tier-cap counts primary ownership only.** `enforceTierCap` calls `Mortgage::where('user_id', $user->id)->count()` — mortgages where the user is `joint_owner_id` do NOT count against their cap. This mirrors the Property tier-cap behaviour.

5. **`outstanding_balance_gbp` and `monthly_payment_gbp` are GBP-only in Pass 5.** The derived calculator sets them equal to their source columns. Multi-currency mortgages and FX-aware derived columns are deferred to a later sub-project pass. Single source of truth for FX rates when this lands is `CurrencyRateStore::latestFor()`.

6. **`current_ltv_pct` requires a linked property.** If `property_id` is null or the related property's `current_value` is zero, `current_ltv_pct` is set to `null`. The derived calculator reads the property's `current_value` via `$mortgage->property->current_value`.

7. **Derived columns are materialised inside the write transaction.** `recalculateDerived` runs inside `DB::transaction` in every write path so a snapshot-write or derived-column failure rolls back the whole create/update.

8. **Cross-store recalc is one-way only.** Mortgage → Property (via events + listener). There is no Property → Mortgage recalculation chain. The listener uses `saveQuietly()` to prevent a feedback loop.

9. **`updateOrCreate` matches on `(user_id, property_id, lender_name)`.** This triple is the idempotency key for seeder use. If the same lender has two separate mortgages on the same property (rare but valid), callers should use `create()` directly for the second one rather than `updateOrCreate()`.

10. **Boundary allowlist has 3 entries (not 2).** The plan §12.1 named 2 entries; `PreviewUserSeeder.php` was already present in PR 4 and is kept permanently allowlisted following the identical precedent set by `PropertyStoreBoundaryTest`. See "Boundary" section above for the full rationale.

11. **`forUserByProperty` returns a `Collection` grouped by `property_id` key.** Each value is itself a `Collection` of `Mortgage` objects. The grouping is done in PHP (not SQL) on the result of `forUserWithJointOwner`. Callers must be aware that the returned object is `Illuminate\Support\Collection<int, Collection<int, Mortgage>>`, not a flat `Collection<int, Mortgage>`.

## Migration history (SP1 Pass 5 — all merged to `dev`)

| PR | Number | Merge SHA | Description |
|---|---|---|---|
| PR 1 | #403 | — | Introduce `MortgageStore` facade + arch boundary + `MortgageNormaliser` + four events + tier-cap enforcement seam |
| PR 2 | #404 | — | Point HTTP form requests at `MortgageStore` (`MortgageController` write paths) |
| PR 3 | #405 | — | Point Fyn AI write tools at `MortgageStore` (`CoordinatingAgent::handleCreateMortgage` / `handleUpdateMortgage`) |
| PR 4 | #406 | — | Point upload + onboarding + seeders at `MortgageStore`; `PreviewUserSeeder` bulk-delete allowlisted |
| PR 5a–5e | #407–411 | — | Read consumer migration (controllers, agents, services, GDPR, protection, rate-alert consumers) |
| PR 6 | #412 | `d258b10` | Canonical derived columns (`outstanding_balance_gbp`, `monthly_payment_gbp`, `current_ltv_pct`) + `mortgage_value_snapshots` table + per-column `SnapshotPolicy` + `MortgageDerivedColumnCalculator` + cross-store Property reconciliation |
| PR 7 | #413 | `479cc6d` | Tier-cap test (`MortgageTierCapTest`) locking the spec §13 contract |
| PR 8 | (this PR) | — | Boundary allowlist LOCKED (transition language dropped; entries recategorised) + `MortgageAuditIngestSourceTest` (5 cases) + `MortgageThreeIngestParityTest` (2 cases) + `MortgageStore.md` |

## Acceptance criteria (spec §16)

| Gate | Met by |
|---|---|
| §16.1.1 Single write path (Pest boundary locked) | PR 8 (`MortgageStoreBoundaryTest` — LOCKED framing, no transition entries) |
| §16.1.2 Three-ingest parity | PR 8 (`MortgageThreeIngestParityTest` — individual parity + TIC coercion invariant) |
| §16.1.3 Audit completeness — `ingest_source` on every write | PR 1 (wraps from the start) + PR 8 (`MortgageAuditIngestSourceTest` — 5 cases) |
| §16.1.4 Derived-column correctness | PR 6 (`MortgageDerivedColumnCalculator` + tests) |
| §16.1.5 Snapshot policy applied | PR 6 (per-column `mortgageBalance()` / `mortgageRate()` policies + tests) |
| §16.1.6 Currency round-trip | n/a — GBP-only in Pass 5; deferred to later pass (mirrors Property + Pension pattern) |
| §16.1.7 Tier-cap enforcement | PR 1 (enforcement seam) + PR 7 (`MortgageTierCapTest` — 5 cases) |
| §16.1.8 Browser-tested via Playwright | csjones smoke after PR 8 merge (CSJ-driven) |
| §16.2.1 All entity stores | 8/19 after Pass 5 (Savings + Pensions + 4 ref-data + Properties + Mortgages) |
| §16.2.2 Boundary tests green | `MortgageStoreBoundaryTest` LOCKED in PR 8 |
| §16.2.5 Per-entity Store.md | PR 8 (this document) |

**Pass 5 is complete. MortgageStore joins Savings, R1–R4 reference data, Pensions, and Properties as a fully shipped entity store. SP1 progress: 8 of 19 entity stores done.**
