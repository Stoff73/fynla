# TaxConfigStore

Canonical write boundary for `App\Models\TaxConfiguration`. Every mutation of the `tax_configurations` table — and every internal read consumed by `TaxConfigService` — flows through this store.

**Entity key:** `tax_configuration` (emitted on every `ReferenceDataUpdated` event)
**Table:** `tax_configurations`
**Model:** `App\Models\TaxConfiguration`
**Base:** extends `ReferenceDataStore`
**Normaliser:** `App\Services\Stores\Normalisers\TaxConfigNormaliser`

## Boundary

Lock test: `tests/Architecture/StoreBoundary/TaxConfigStoreBoundaryTest.php` (SP1 Pass 2 R1.6).

Allowed direct consumers of `App\Models\TaxConfiguration`:

- `App\Services\Stores\TaxConfigStore` — the store itself
- `App\Models\TaxConfigurationAudit` — `belongsTo` relation (permanent; spec §14.2 model-on-model is OK)
- `Database\Factories\TaxConfigurationFactory` — test fixtures only (permanent; spec §14.2)

Adding a new direct-model consumer requires either routing through the store (preferred) or adding to this allowlist with written justification.

## Allowed ingest sources

`IngestSource::ADMIN` or `IngestSource::SEEDER`. Any other source throws `StoreValidationException` from `ReferenceDataStore::guardSource` (or `TaxConfigStore::guardSource` on the `setActive` path, which bypasses the base entry points).

## Public API

### Writes

| Method | Purpose |
|---|---|
| `create(array $input, IngestSource $source, ?int $actorUserId = null, ?string $rationale = null, ?string $ipAddress = null): int` | Create a new (inactive) tax-config row. Normalises the admin payload, persists, writes a `TaxConfigurationAudit` row with `change_type='created'`. |
| `update(int $id, array $input, IngestSource $source, ?int $actorUserId = null, ?string $rationale = null, ?string $ipAddress = null): void` | Partial update — caller supplies only the fields to change; the store merges with the existing row and re-normalises. Does **not** activate/deactivate; use `setActive` for that. Writes a `TaxConfigurationAudit` row with `change_type='updated'` and the pre-update `config_data` as `beforeState`. |
| `setActive(int $id, IngestSource $source, ?int $actorUserId = null, ?string $rationale = null, ?string $ipAddress = null): void` | Activate this row, deactivating any currently-active sibling. Writes a `deactivated` audit row for the previously-active config (if any), then `activated` for the new one. Drops the in-memory `activeMemo` and dispatches `ReferenceDataUpdated`. |
| `duplicate(int $sourceId, string $newTaxYear, string $effectiveFrom, string $effectiveTo, IngestSource $source, ?int $actorUserId = null, ?string $ipAddress = null): int` | Create a new (inactive) configuration by copying `config_data` from an existing row. Writes a `duplicated` audit row (not `created`) so the audit history preserves the semantic action. |
| `delete(int $id, IngestSource $source, ?int $actorUserId = null, ?string $rationale = null, ?string $ipAddress = null): void` | Delete the row. **No audit row is written for deletions** — `tax_configuration_audits.tax_configuration_id` cascades on delete, so any audit row would be wiped immediately. Preserving audit history across deletes requires a separate schema change. |

### Reads

| Method | Returns |
|---|---|
| `find(int $id): array` | Read-by-id from the per-request memoised cache. Returns `[]` if not found. Inherited from base. |
| `all(): Collection<TaxConfiguration>` | All rows, newest `effective_from` first. Used by the admin index endpoint. |
| `findByTaxYear(string $taxYear): ?TaxConfiguration` | Find by tax_year string (e.g. `"2026/27"`). Used by the seeder for idempotency. |
| `activeConfig(): ?array` | The active row as a canonical array, or `null` if no row is active. **Memoised per-instance.** Drops on `setActive` / `forgetAll` / explicit `forgetActive`. |
| `forgetActive(): void` | Drop the `activeConfig` memo. Public so `TaxConfigService::clearCache`, integration tests, and the admin `Cache::flush` call site can force a re-read after out-of-band mutations. |
| `findEloquent(int $id): ?TaxConfiguration` | Return the Eloquent model for the admin controller to build Resources without importing the model. |

## Per-entity quirks

1. **`activeConfig()` returns `?array`, not a model.** The `read()` method canonicalises Carbon date casts to `Y-m-d` so the partial-merge → normaliser → persist path round-trips against the MySQL DATE columns. Callers needing the Eloquent model chain through `findEloquent($store->activeConfig()['id'])`.

2. **`config_data` is a nested key inside the array** returned by `activeConfig()` / `find()`. Use `$store->activeConfig()['config_data']`, never `?->config_data` — there is no model wrapper here.

3. **Tri-state `activeMemo`.** Not loaded vs. loaded-and-null vs. loaded-and-array. Kept separate from the base's id-keyed `cache` so `setActive` can invalidate it independently when `is_active` flips on any row.

4. **`update()` does not flip `is_active`.** Passing `is_active = true` in the input array will persist the flag on this row but will not deactivate siblings. Use `setActive` instead (single-responsibility; mirrors spec §10.4).

5. **Cache::flush of agent-cached analyses is the controller's responsibility.** The store stays HTTP/cache-layer agnostic — `TaxSettingsController` calls `flushAgentCaches()` after `setActive` returns.

6. **Audit trail.** Every write (except `delete` — see above) emits a `TaxConfigurationAudit` row via `TaxConfigurationAudit::log($config, $changeType, $beforeState, $actorUserId, $rationale, $ipAddress)`. Change types: `created`, `updated`, `duplicated`, `activated`, `deactivated`. The IP-address parameter exists so the HTTP controller can preserve the request trail without coupling the store to `Request`.

## Events

Every successful write dispatches:

```php
ReferenceDataUpdated::dispatch(
    'tax_configuration',
    $id,
    $changedKeys,   // array_keys($canonical) on create/update; ['is_active'] on setActive; ['__deleted'] on delete
    $actorUserId,
);
```

## Migration history (R1 track, all merged to `dev`)

- **PR R1.1 (#364)** — introduce `TaxConfigStore` + arch boundary + normaliser + 28 new tests.
- **PR R1.2 (#365)** — `TaxSettingsController` writes routed via store; removed from allowlist.
- **PR R1.3 (#366)** — `TaxConfigurationSeeder` writes via `$store->create/update` with `IngestSource::SEEDER` plus `setActive` for the target year; removed from allowlist.
- **PR R1.4 (#367)** — `TaxConfigService` internal reads via `$store->activeConfig()`; dead `getModel()` dropped; removed from allowlist.
- **PR R1.5 (#372)** — B2 admin-edit gap closed (5 v-model'd sections previously dropped on save); `TaxSettingsController::getCalculations()` rewritten to read live config via `$store->activeConfig()['config_data']` instead of ~125 lines of hardcoded `'£12,570'` literals (Rule #3).
- **PR R1.6 (#368)** — boundary locked to the 3-entry allowlist above.
