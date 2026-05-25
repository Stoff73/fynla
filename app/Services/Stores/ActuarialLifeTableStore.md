# ActuarialLifeTableStore

Canonical write boundary for `App\Models\ActuarialLifeTable`. Every mutation of the `actuarial_life_tables` table flows through this store, and every read by the Estate consumers (`TrustService`, `FutureValueCalculator`, `ComprehensiveEstatePlanService`) goes through `forCohort()`.

**Entity key:** `actuarial_life_table` (emitted on every `ReferenceDataUpdated` event)
**Table:** `actuarial_life_tables`
**Model:** `App\Models\ActuarialLifeTable`
**Base:** extends `ReferenceDataStore`
**Normaliser:** `App\Services\Stores\Normalisers\ActuarialLifeTableNormaliser`

## Boundary

Lock test: `tests/Architecture/StoreBoundary/ActuarialLifeTableStoreBoundaryTest.php` (SP1 Pass 2 R3.5).

Allowed direct consumers of `App\Models\ActuarialLifeTable`:

- `App\Services\Stores\ActuarialLifeTableStore` — the store itself
- `Database\Factories\ActuarialLifeTableFactory` — test fixtures only (permanent; spec §14.2)

Adding a new direct-model consumer requires either routing through the store (preferred) or adding to this allowlist with written justification.

## Allowed ingest sources

`IngestSource::ADMIN` or `IngestSource::SEEDER`. Any other source throws `StoreValidationException` from `ReferenceDataStore::guardSource`.

## Public API

### Writes

| Method | Purpose |
|---|---|
| `create(array $input, IngestSource $source, ?int $actorUserId = null): int` | Create a new row after normalising the admin payload. |
| `update(int $id, array $input, IngestSource $source, ?int $actorUserId = null): void` | Partial update — caller supplies only the fields to change; the store merges with the existing row and re-normalises. |
| `delete(int $id, IngestSource $source, ?int $actorUserId = null): void` | Delete the row. Inherited from base. Idempotent. |

### Reads

| Method | Returns |
|---|---|
| `find(int $id): array` | Read-by-id from the per-request memoised cache. Returns `[]` if not found. Inherited from base. |
| `all(): Collection<ActuarialLifeTable>` | All rows ordered by `table_year` desc → gender → age. Used by the admin index endpoint. |
| `forCohort(string $gender, string $tableYear): Collection<ActuarialLifeTable>` | The full cohort (every age row) for a given gender / table_year, ordered by age. **The canonical Estate read path.** |
| `findByCohortAndAge(int $age, string $gender, string $tableYear, string $tableSource): ?ActuarialLifeTable` | Find by composite identity. Used by the seeder for upsert lookups. |
| `findEloquent(int $id): ?ActuarialLifeTable` | Return the Eloquent model for the admin controller to build Resources without importing the model. |

## Per-entity quirks

1. **Cohort-then-in-memory access pattern.** Estate consumers (`TrustService`, `FutureValueCalculator`, `ComprehensiveEstatePlanService`) fetch the full cohort once via `forCohort()` and then run age-comparison logic in memory — exact match, nearest-lower, nearest-upper. The data set is small (~22 ages per cohort), so an in-memory pass is cheaper than three separate WHERE clauses.

2. **DB unique key vs. seeder lookup mismatch.** The `actuarial_life_tables` table has `UNIQUE (age, gender, table_year)` — `table_source` is **not** part of the unique index. The seeder upsert via `findByCohortAndAge` DOES include `table_source` for safety, but two rows for the same `(age, gender, table_year)` with different `table_source` values will violate the unique constraint at insert time. If you ever introduce a second source for the same cohort, the unique index needs widening first.

3. **Default `table_source`.** The MySQL DEFAULT is `'UK ONS National Life Tables'`. The seeder writes this string explicitly via the normaliser — do not rely on the DB default when writing through the store.

4. **`gender` is a MySQL `ENUM('male','female')`** — the normaliser lowercases input. Inserting anything else throws a DB-level error before the store guard sees it.

## Events

Every successful write dispatches:

```php
ReferenceDataUpdated::dispatch(
    'actuarial_life_table',
    $id,
    $changedKeys,   // array_keys($canonical) on create/update; ['__deleted'] on delete
    $actorUserId,
);
```

## Migration history (R3 track, all merged to `dev`)

- **PR R3.1 (#354)** — introduce `ActuarialLifeTableStore` + arch boundary + `forCohort()` read API.
- **PR R3.2 (#355)** — admin CRUD + Vue panel + factory; new "Life Tables" tab in `AdminPanel`.
- **PR R3.3 (#356)** — `TrustService`, `FutureValueCalculator`, `ComprehensiveEstatePlanService` all migrated to `$store->forCohort()`; Estate consumers no longer import the model.
- **PR R3.4 (#357)** — `ActuarialLifeTablesSeeder` writes via store with `IngestSource::SEEDER`.
- **PR R3.5 (#358)** — boundary locked to the 2-entry allowlist above.
