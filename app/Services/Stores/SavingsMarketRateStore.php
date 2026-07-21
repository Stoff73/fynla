<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\SavingsMarketRate;
use App\Services\Stores\Normalisers\SavingsMarketRateNormaliser;
use App\Services\Stores\ReferenceData\ReferenceDataStore;
use Illuminate\Support\Collection;

class SavingsMarketRateStore extends ReferenceDataStore
{
    public function __construct(
        private readonly SavingsMarketRateNormaliser $normaliser = new SavingsMarketRateNormaliser,
    ) {}

    protected function entityKey(): string
    {
        return 'savings_market_rate';
    }

    /**
     * Create a new market-rate row after normalising the admin payload.
     * Only ADMIN and SEEDER sources are accepted (enforced by base).
     */
    public function create(array $input, IngestSource $source, ?int $actorUserId = null): int
    {
        $canonical = $this->normaliser->fromAdmin($input);

        return parent::create($canonical, $source, $actorUserId);
    }

    /**
     * Partially update a market-rate row.
     * Merges the supplied fields with the existing row then re-normalises,
     * so callers may supply only the fields they want to change.
     */
    public function update(int $id, array $input, IngestSource $source, ?int $actorUserId = null): void
    {
        $existing = $this->find($id);
        $canonical = $this->normaliser->fromAdmin(array_merge($existing, $input));

        parent::update($id, $canonical, $source, $actorUserId);
    }

    /**
     * Returns all market rates, ordered by rate_key then most-recent effective_from.
     * Used by the admin index endpoint.
     *
     * @return Collection<int, SavingsMarketRate>
     */
    public function all(): Collection
    {
        return SavingsMarketRate::orderBy('rate_key')
            ->orderByDesc('effective_from')
            ->get();
    }

    /**
     * Returns all market rates for a given tax year.
     * This is the access pattern used by RateComparator — it queries by
     * tax_year and works across all rate_key values for that year.
     *
     * @return Collection<int, SavingsMarketRate>
     */
    public function forTaxYear(string $taxYear): Collection
    {
        return SavingsMarketRate::where('tax_year', $taxYear)->get();
    }

    /**
     * Find a rate by its composite identity (rate_key, tax_year).
     * Used by the seeder for upsert lookups and by admin reads that need
     * the canonical row without scanning a full tax_year set.
     */
    public function findByKeyAndTaxYear(string $rateKey, string $taxYear): ?SavingsMarketRate
    {
        return SavingsMarketRate::where('rate_key', $rateKey)
            ->where('tax_year', $taxYear)
            ->first();
    }

    /**
     * Return the Eloquent model for a given id, or null. Used by the admin
     * controller to construct response Resources without touching the model
     * directly (boundary enforcement; spec §5.1 — reads return Eloquent
     * instances, but the entry point is the store).
     */
    public function findEloquent(int $id): ?SavingsMarketRate
    {
        return SavingsMarketRate::find($id);
    }

    // ── Abstract base overrides ───────────────────────────────────────────────

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

        if (! $row) {
            return [];
        }

        $arr = $row->toArray();
        // Carbon date casts serialise to ISO 8601 ('YYYY-MM-DDTHH:MM:SS.NNNZ');
        // that's incompatible with MySQL date columns on round-trip update.
        // Re-emit as Y-m-d so the partial-merge → persist path works.
        if ($row->effective_from !== null) {
            $arr['effective_from'] = $row->effective_from->toDateString();
        }

        return $arr;
    }

    protected function delete_(int $id): void
    {
        SavingsMarketRate::where('id', $id)->delete();
    }
}
