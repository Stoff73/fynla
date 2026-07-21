<?php

declare(strict_types=1);

namespace App\Services\Stores\ReferenceData;

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;

abstract class ReferenceDataStore
{
    /** Per-request memoised cache keyed by entity_id. */
    private array $cache = [];

    /** The string key identifying this entity (e.g. 'tax_configuration', 'currency_rate'). */
    abstract protected function entityKey(): string;

    /**
     * Persist a canonical row. Returns the row's id.
     * If $id is provided, this is an update; otherwise it's a create.
     */
    abstract protected function persist(array $canonical, ?int $id = null): int;

    /** Read a single row by id. Return [] if not found. */
    abstract protected function read(int $id): array;

    /** Delete a row by id. Idempotent — no error if absent. */
    abstract protected function delete_(int $id): void;

    public function create(array $canonical, IngestSource $source, ?int $actorUserId = null): int
    {
        $this->guardSource($source);
        $id = $this->persist($canonical);
        $this->cache[$id] = $canonical;

        ReferenceDataUpdated::dispatch(
            $this->entityKey(),
            $id,
            array_keys($canonical),
            $actorUserId
        );

        return $id;
    }

    public function update(int $id, array $canonical, IngestSource $source, ?int $actorUserId = null): void
    {
        $this->guardSource($source);
        $this->persist($canonical, $id);
        unset($this->cache[$id]);

        ReferenceDataUpdated::dispatch(
            $this->entityKey(),
            $id,
            array_keys($canonical),
            $actorUserId
        );
    }

    public function delete(int $id, IngestSource $source, ?int $actorUserId = null): void
    {
        $this->guardSource($source);
        $this->delete_($id);
        unset($this->cache[$id]);

        ReferenceDataUpdated::dispatch(
            $this->entityKey(),
            $id,
            ['__deleted'],
            $actorUserId
        );
    }

    public function find(int $id): array
    {
        if (! array_key_exists($id, $this->cache)) {
            $this->cache[$id] = $this->read($id);
        }

        return $this->cache[$id];
    }

    private function guardSource(IngestSource $source): void
    {
        if (! in_array($source, [IngestSource::ADMIN, IngestSource::SEEDER], true)) {
            throw new StoreValidationException(
                ['ingest_source' => "Reference-data writes only permitted from ADMIN or SEEDER (got: {$source->value})"]
            );
        }
    }
}
