# CurrencyRateStore

Canonical write boundary for `App\Models\CurrencyRate`. Every mutation of `currency_rates` flows through this store, and every read — including `convert()` and `historical()` — is exposed here so callers never touch the model directly.

**Entity key:** `currency_rate` (emitted on every `ReferenceDataUpdated` event)
**Table:** `currency_rates`
**Model:** `App\Models\CurrencyRate`
**Base:** extends `ReferenceDataStore`
**Normaliser:** `App\Services\Stores\Normalisers\CurrencyRateNormaliser`

## Boundary

Lock test: `tests/Architecture/StoreBoundary/CurrencyRateStoreBoundaryTest.php` (SP1 Pass 2 R2.5).

Allowed direct consumers of `App\Models\CurrencyRate`:

- `App\Services\Stores\CurrencyRateStore` — the store itself
- `Database\Factories\CurrencyRateFactory` — test fixtures only (permanent; spec §14.2)

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
| `all(): Collection<CurrencyRate>` | All rates, ordered by `from_ccy` → `to_ccy` → most-recent `effective_at` first. Used by the admin index endpoint. |
| `latestFor(string $fromCcy, string $toCcy): ?float` | The most-recent rate for a `(from_ccy, to_ccy)` pair, or `null` if none. Returns `1.0` when `from_ccy === to_ccy` (identity conversion). |
| `convert(float $amount, string $fromCcy, string $toCcy): ?float` | Convert an amount using the latest rate. Returns `null` if no rate is available for the pair. |
| `historical(string $fromCcy, string $toCcy, DateTimeInterface $at): ?float` | The rate effective on or before the given datetime — for valuing assets on a specific date. |
| `findByPairAndEffectiveAt(string $fromCcy, string $toCcy, string $effectiveAt): ?CurrencyRate` | Find by composite identity. Used by the seeder for upserts. |
| `findEloquent(int $id): ?CurrencyRate` | Return the Eloquent model for the admin controller to build Resources without importing the model. |

## Per-entity quirks

1. **Currency codes are normalised to upper-case** in `latestFor` and `historical` before the WHERE clause. The normaliser also uppercases on write, so `'gbp'` and `'GBP'` are interchangeable from the caller's perspective.

2. **Identity conversion shortcut.** `latestFor('GBP', 'GBP')` returns `1.0` without touching the DB. `historical(..., $at)` does the same regardless of `$at`. Callers can rely on `convert($amount, 'GBP', 'GBP')` returning `$amount` even if no GBP→GBP row exists.

3. **`null` means "no rate"**, not "rate is zero." `latestFor` and `historical` return `null` when no row matches the pair; `convert` propagates this as `null`. Callers must null-check rather than treating an absent rate as a free conversion.

4. **DATETIME canonicalisation on read.** `read()` overrides Carbon's ISO-8601 serialisation with `Y-m-d H:i:s` so the partial-merge → persist path round-trips against the MySQL DATETIME column. Without this, `update()` re-emits the ISO string and the column value drifts.

5. **`historical()` does not interpolate.** It returns the most-recent row with `effective_at <= $at`. If you need a rate exactly at `$at` and one doesn't exist, you get the previous available rate, not an interpolated value. This is intentional — currency rates are step functions in this system, not continuous time series.

## Events

Every successful write dispatches:

```php
ReferenceDataUpdated::dispatch(
    'currency_rate',
    $id,
    $changedKeys,   // array_keys($canonical) on create/update; ['__deleted'] on delete
    $actorUserId,
);
```

## Migration history (R2 track, all merged to `dev`)

- **PR R2.1 (#359)** — `currency_rates` migration + model + factory + seeder (direct-write per plan; migrated to store in R2.4).
- **PR R2.2 (#360)** — introduce `CurrencyRateStore` + arch boundary; read API: `latestFor()` / `convert()` / `historical()` / `findByPairAndEffectiveAt()`.
- **PR R2.3 (#361)** — admin CRUD + Vue panel; `AdminPanel` "Currency Rates" tab; DATETIME canonicalisation in `read()` (Carbon ISO-8601 round-trip fix).
- **PR R2.4 (#362)** — `CurrencyRatesSeeder` writes via store with `IngestSource::SEEDER`.
- **PR R2.5 (#363)** — boundary locked to the 2-entry allowlist above.
