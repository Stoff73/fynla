<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\ActuarialLifeTable;
use App\Services\Stores\Normalisers\ActuarialLifeTableNormaliser;
use App\Services\Stores\ReferenceData\ReferenceDataStore;
use Illuminate\Support\Collection;

class ActuarialLifeTableStore extends ReferenceDataStore
{
    public function __construct(
        private readonly ActuarialLifeTableNormaliser $normaliser = new ActuarialLifeTableNormaliser,
    ) {}

    protected function entityKey(): string
    {
        return 'actuarial_life_table';
    }

    /**
     * Create a new actuarial-life-table row after normalising the admin payload.
     * Only ADMIN and SEEDER sources are accepted (enforced by base).
     */
    public function create(array $input, IngestSource $source, ?int $actorUserId = null): int
    {
        $canonical = $this->normaliser->fromAdmin($input);

        return parent::create($canonical, $source, $actorUserId);
    }

    /**
     * Partially update an actuarial-life-table row.
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
     * Returns all rows, ordered by table_year (desc), gender, then age.
     * Used by the admin index endpoint.
     *
     * @return Collection<int, ActuarialLifeTable>
     */
    public function all(): Collection
    {
        return ActuarialLifeTable::orderByDesc('table_year')
            ->orderBy('gender')
            ->orderBy('age')
            ->get();
    }

    /**
     * Returns the full cohort (every age row) for a given gender / table_year.
     * This is the access pattern the Estate consumers use — they fetch the
     * cohort once and run age-comparison logic in memory (exact / nearest-
     * lower / nearest-upper). Data set is small (~22 ages per cohort), so
     * an in-memory pass is cheaper than three separate WHERE clauses.
     *
     * @return Collection<int, ActuarialLifeTable>
     */
    public function forCohort(string $gender, string $tableYear): Collection
    {
        return ActuarialLifeTable::where('gender', $gender)
            ->where('table_year', $tableYear)
            ->orderBy('age')
            ->get();
    }

    /**
     * Find a row by its composite identity (age, gender, table_year, table_source).
     * Used by the seeder for upsert lookups in PR R3.4.
     */
    public function findByCohortAndAge(int $age, string $gender, string $tableYear, string $tableSource): ?ActuarialLifeTable
    {
        return ActuarialLifeTable::where('age', $age)
            ->where('gender', $gender)
            ->where('table_year', $tableYear)
            ->where('table_source', $tableSource)
            ->first();
    }

    /**
     * Return the Eloquent model for a given id, or null. Used by the admin
     * controller (PR R3.2) to construct response Resources without touching
     * the model directly (boundary enforcement; spec §5.1 — reads return
     * Eloquent instances, but the entry point is the store).
     */
    public function findEloquent(int $id): ?ActuarialLifeTable
    {
        return ActuarialLifeTable::find($id);
    }

    // ── Abstract base overrides ───────────────────────────────────────────────

    protected function persist(array $canonical, ?int $id = null): int
    {
        if ($id === null) {
            return ActuarialLifeTable::create($canonical)->id;
        }

        ActuarialLifeTable::where('id', $id)->update($canonical);

        return $id;
    }

    protected function read(int $id): array
    {
        $row = ActuarialLifeTable::find($id);

        if (! $row) {
            return [];
        }

        return $row->toArray();
    }

    protected function delete_(int $id): void
    {
        ActuarialLifeTable::where('id', $id)->delete();
    }
}
