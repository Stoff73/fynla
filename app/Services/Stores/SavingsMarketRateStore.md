# SavingsMarketRateStore

Canonical write boundary for `App\Models\SavingsMarketRate`. Every mutation of `savings_market_rates` flows through this store, and the Savings consumer (`RateComparator`) reads via `forTaxYear()`.

**Entity key:** `savings_market_rate` (emitted on every `ReferenceDataUpdated` event)
**Table:** `savings_market_rates`
**Model:** `App\Models\SavingsMarketRate`
**Base:** extends `ReferenceDataStore`
**Normaliser:** `App\Services\Stores\Normalisers\SavingsMarketRateNormaliser`

## Boundary

Lock test: `tests/Architecture/StoreBoundary/SavingsMarketRateStoreBoundaryTest.php` (SP1 Pass 2 R4.5).

Allowed direct consumers of `App\Models\SavingsMarketRate`:

- `App\Services\Stores\SavingsMarketRateStore` — the store itself
- `Database\Factories\SavingsMarketRateFactory` — test fixtures only (permanent; spec §14.2)

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
| `all(): Collection<SavingsMarketRate>` | All rates ordered by `rate_key` then most-recent `effective_from`. Used by the admin index endpoint. |
| `forTaxYear(string $taxYear): Collection<SavingsMarketRate>` | All rates for a given tax year — the canonical `RateComparator` access pattern. Returns every `rate_key` for the year in one query. |
| `findByKeyAndTaxYear(string $rateKey, string $taxYear): ?SavingsMarketRate` | Find by composite identity. Used by the seeder for upsert lookups and by admin reads that need a single canonical row without scanning the full tax-year set. |
| `findEloquent(int $id): ?SavingsMarketRate` | Return the Eloquent model for the admin controller to build Resources without importing the model. |

## Per-entity quirks

1. **Schema is intentionally lean.** Fillable columns are `rate_key`, `label`, `rate`, `tax_year`, `effective_from` — there is no `effective_to` or `is_active`. The most-recent `effective_from` for a given `(rate_key, tax_year)` is the active rate; older rows are historical.

2. **`rate_key` is a free-form string, not an enum.** Examples: `'easy_access_avg'`, `'fixed_1y_avg'`, `'isa_easy_access'`. The normaliser does not validate against a canonical list — `RateComparator` is the consumer that decides which keys it cares about. If you rename a key, search `app/Services/Savings/RateComparator.php` first.

3. **`tax_year` is the partition key for reads.** `forTaxYear('2026/27')` is the primary read pattern; standalone `latestFor`-style methods deliberately don't exist because rates are always quoted in the context of a tax year (ISA allowances etc. are tax-year scoped).

4. **DATE canonicalisation on read.** `read()` overrides Carbon's ISO-8601 cast with `Y-m-d` so the partial-merge → persist path round-trips against the MySQL DATE column. Without this, `update()` re-emits the ISO string and the column value drifts.

5. **No DB-level uniqueness on `(rate_key, tax_year)`.** The seeder enforces uniqueness behaviourally via `findByKeyAndTaxYear` → create/update. If duplicates appear it is a seeder bug, not a schema gap.

## Events

Every successful write dispatches:

```php
ReferenceDataUpdated::dispatch(
    'savings_market_rate',
    $id,
    $changedKeys,   // array_keys($canonical) on create/update; ['__deleted'] on delete
    $actorUserId,
);
```

## Migration history (R4 track, all merged to `dev`)

- **PR R4.1 (#348)** — introduce `SavingsMarketRateStore` + arch boundary (schema reconciled — `rate_key/label/rate/tax_year/effective_from`, not the plan's aspirational column names).
- **PR R4.2 (#349)** — admin CRUD + Vue panel; `AdminPanel` "Savings Rates" tab; Carbon DATE round-trip fix in `read()`.
- **PR R4.3 (#350)** — `RateComparator` migrated to `$store->forTaxYear()`; consumer removed from allowlist.
- **PR R4.4 (#351)** — `SavingsMarketRatesSeeder` writes via store with `IngestSource::SEEDER`; seeder removed from allowlist.
- **PR R4.5 (#352)** — boundary locked to the 2-entry allowlist above.
