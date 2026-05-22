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

        return $row ? $row->toArray() : [];
    }

    protected function delete_(int $id): void
    {
        SavingsMarketRate::where('id', $id)->delete();
    }
}
