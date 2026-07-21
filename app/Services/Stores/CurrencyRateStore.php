<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\CurrencyRate;
use App\Services\Stores\Normalisers\CurrencyRateNormaliser;
use App\Services\Stores\ReferenceData\ReferenceDataStore;
use DateTimeInterface;
use Illuminate\Support\Collection;

class CurrencyRateStore extends ReferenceDataStore
{
    public function __construct(
        private readonly CurrencyRateNormaliser $normaliser = new CurrencyRateNormaliser,
    ) {}

    protected function entityKey(): string
    {
        return 'currency_rate';
    }

    /**
     * Create a new currency-rate row after normalising the admin payload.
     * Only ADMIN and SEEDER sources are accepted (enforced by base).
     */
    public function create(array $input, IngestSource $source, ?int $actorUserId = null): int
    {
        $canonical = $this->normaliser->fromAdmin($input);

        return parent::create($canonical, $source, $actorUserId);
    }

    /**
     * Partially update a currency-rate row.
     */
    public function update(int $id, array $input, IngestSource $source, ?int $actorUserId = null): void
    {
        $existing = $this->find($id);
        $canonical = $this->normaliser->fromAdmin(array_merge($existing, $input));

        parent::update($id, $canonical, $source, $actorUserId);
    }

    /**
     * Returns all rates, ordered by currency pair then most-recent effective_at first.
     * Used by the admin index endpoint.
     *
     * @return Collection<int, CurrencyRate>
     */
    public function all(): Collection
    {
        return CurrencyRate::orderBy('from_ccy')
            ->orderBy('to_ccy')
            ->orderByDesc('effective_at')
            ->get();
    }

    /**
     * Returns the most-recent rate for a (from_ccy, to_ccy) pair, or null if none.
     * Returns 1.0 when from_ccy === to_ccy (identity conversion).
     */
    public function latestFor(string $fromCcy, string $toCcy): ?float
    {
        $fromCcy = strtoupper($fromCcy);
        $toCcy = strtoupper($toCcy);

        if ($fromCcy === $toCcy) {
            return 1.0;
        }

        $row = CurrencyRate::where('from_ccy', $fromCcy)
            ->where('to_ccy', $toCcy)
            ->orderByDesc('effective_at')
            ->first();

        return $row ? (float) $row->rate : null;
    }

    /**
     * Convert an amount from one currency to another using the latest rate.
     * Returns null if no rate is available for the pair.
     */
    public function convert(float $amount, string $fromCcy, string $toCcy): ?float
    {
        $rate = $this->latestFor($fromCcy, $toCcy);

        return $rate !== null ? $amount * $rate : null;
    }

    /**
     * Returns the rate that was effective on or before the given datetime.
     * Used for historical conversions (e.g. valuing an asset on a specific date).
     */
    public function historical(string $fromCcy, string $toCcy, DateTimeInterface $at): ?float
    {
        $fromCcy = strtoupper($fromCcy);
        $toCcy = strtoupper($toCcy);

        if ($fromCcy === $toCcy) {
            return 1.0;
        }

        $row = CurrencyRate::where('from_ccy', $fromCcy)
            ->where('to_ccy', $toCcy)
            ->where('effective_at', '<=', $at)
            ->orderByDesc('effective_at')
            ->first();

        return $row ? (float) $row->rate : null;
    }

    /**
     * Find a rate by its composite identity. Used by the seeder for upserts.
     */
    public function findByPairAndEffectiveAt(string $fromCcy, string $toCcy, string $effectiveAt): ?CurrencyRate
    {
        return CurrencyRate::where('from_ccy', strtoupper($fromCcy))
            ->where('to_ccy', strtoupper($toCcy))
            ->where('effective_at', $effectiveAt)
            ->first();
    }

    /**
     * Return the Eloquent model for a given id, or null. Used by the admin
     * controller (PR R2.3) to construct response Resources without touching
     * the model directly (boundary enforcement; spec §5.1).
     */
    public function findEloquent(int $id): ?CurrencyRate
    {
        return CurrencyRate::find($id);
    }

    // ── Abstract base overrides ───────────────────────────────────────────────

    protected function persist(array $canonical, ?int $id = null): int
    {
        if ($id === null) {
            return CurrencyRate::create($canonical)->id;
        }

        CurrencyRate::where('id', $id)->update($canonical);

        return $id;
    }

    protected function read(int $id): array
    {
        $row = CurrencyRate::find($id);

        if (! $row) {
            return [];
        }

        $arr = $row->toArray();
        // Carbon datetime casts serialise to ISO 8601; canonicalise to the
        // mysql DATETIME string so the partial-merge → persist path round-trips.
        if ($row->effective_at !== null) {
            $arr['effective_at'] = $row->effective_at->format('Y-m-d H:i:s');
        }

        return $arr;
    }

    protected function delete_(int $id): void
    {
        CurrencyRate::where('id', $id)->delete();
    }
}
