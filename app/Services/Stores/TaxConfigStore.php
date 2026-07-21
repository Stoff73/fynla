<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Models\TaxConfiguration;
use App\Models\TaxConfigurationAudit;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Normalisers\TaxConfigNormaliser;
use App\Services\Stores\ReferenceData\ReferenceDataStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaxConfigStore extends ReferenceDataStore
{
    /**
     * Per-instance memoisation of the active row.
     *
     * Kept separate from the base's id-keyed `cache` so it can be invalidated
     * independently when is_active flips on any row (setActive / forgetAll).
     * Tri-state: unset = not loaded; null = loaded, no active row; array = loaded.
     */
    private array $activeMemo = [];

    public function __construct(
        private readonly TaxConfigNormaliser $normaliser = new TaxConfigNormaliser,
    ) {}

    protected function entityKey(): string
    {
        return 'tax_configuration';
    }

    /**
     * Create a new tax-configuration row.
     *
     * Optionally accepts an IP address so the controller can preserve the
     * existing TaxConfigurationAudit ip_address trail without coupling the
     * store to the HTTP request.
     */
    public function create(
        array $input,
        IngestSource $source,
        ?int $actorUserId = null,
        ?string $rationale = null,
        ?string $ipAddress = null,
    ): int {
        $canonical = $this->normaliser->fromAdmin($input);

        return DB::transaction(function () use ($canonical, $source, $actorUserId, $rationale, $ipAddress) {
            $id = parent::create($canonical, $source, $actorUserId);
            $this->writeAudit($id, 'created', null, $actorUserId, $rationale, $ipAddress);

            return $id;
        });
    }

    /**
     * Partial update — caller supplies only the fields to change; the store
     * merges with the existing row and re-normalises.
     *
     * NOTE: this method does NOT activate/deactivate. Use setActive() for that.
     * Passing is_active=true here will persist the flag but will NOT deactivate
     * sibling rows; this is by design (single-responsibility, mirrors spec §10.4).
     */
    public function update(
        int $id,
        array $input,
        IngestSource $source,
        ?int $actorUserId = null,
        ?string $rationale = null,
        ?string $ipAddress = null,
    ): void {
        DB::transaction(function () use ($id, $input, $source, $actorUserId, $rationale, $ipAddress) {
            $existing = $this->find($id);
            if ($existing === []) {
                throw new StoreValidationException(['id' => 'tax configuration not found']);
            }

            $beforeState = $existing['config_data'] ?? null;
            $merged = array_merge($existing, $input);
            $canonical = $this->normaliser->fromAdmin($merged);

            parent::update($id, $canonical, $source, $actorUserId);
            $this->writeAudit($id, 'updated', $beforeState, $actorUserId, $rationale, $ipAddress);
        });
    }

    /**
     * Activate this configuration, deactivating any currently-active sibling.
     * Writes a separate 'deactivated' audit row for the previously-active config
     * (if any), then 'activated' for the new one.
     *
     * Cache::flush() of agent-cached analyses is the controller's responsibility
     * (keeps the store HTTP/cache-layer agnostic).
     */
    public function setActive(
        int $id,
        IngestSource $source,
        ?int $actorUserId = null,
        ?string $rationale = null,
        ?string $ipAddress = null,
    ): void {
        $existing = $this->find($id);
        if ($existing === []) {
            throw new StoreValidationException(['id' => 'tax configuration not found']);
        }

        $this->guardSource($source);

        DB::transaction(function () use ($id, $actorUserId, $rationale, $ipAddress) {
            $currentlyActive = TaxConfiguration::where('is_active', true)
                ->where('id', '!=', $id)
                ->get();

            foreach ($currentlyActive as $row) {
                $row->update(['is_active' => false]);
                $this->writeAudit($row->id, 'deactivated', null, $actorUserId, null, $ipAddress);
            }

            TaxConfiguration::where('id', $id)->update(['is_active' => true]);
            $this->writeAudit($id, 'activated', null, $actorUserId, $rationale, $ipAddress);
        });

        $this->forgetAll();

        ReferenceDataUpdated::dispatch(
            $this->entityKey(),
            $id,
            ['is_active'],
            $actorUserId,
        );
    }

    /**
     * Create a new (inactive) configuration by copying the config_data of an
     * existing one. The new row gets caller-supplied tax_year / effective dates.
     *
     * Writes a TaxConfigurationAudit row with change_type='duplicated' (not
     * 'created') so audit history preserves the semantic action.
     */
    public function duplicate(
        int $sourceId,
        string $newTaxYear,
        string $effectiveFrom,
        string $effectiveTo,
        IngestSource $source,
        ?int $actorUserId = null,
        ?string $ipAddress = null,
    ): int {
        $existing = $this->find($sourceId);
        if ($existing === []) {
            throw new StoreValidationException(['source_id' => 'tax configuration not found']);
        }

        $canonical = $this->normaliser->fromAdmin([
            'tax_year' => $newTaxYear,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'config_data' => $existing['config_data'],
            'is_active' => false,
            'notes' => "Duplicated from {$existing['tax_year']}",
        ]);

        $rationale = "Duplicated from tax year {$existing['tax_year']}";

        return DB::transaction(function () use ($canonical, $source, $actorUserId, $rationale, $ipAddress) {
            $id = parent::create($canonical, $source, $actorUserId);
            $this->writeAudit($id, 'duplicated', null, $actorUserId, $rationale, $ipAddress);

            return $id;
        });
    }

    /**
     * Delete a row. No audit row is written for deletions because the
     * tax_configuration_audits.tax_configuration_id FK cascades on delete —
     * writing one would be cascade-removed immediately. Preserving the audit
     * trail across deletes requires a separate schema change (out of scope
     * for R1; revisit in R1.5 if the B2 audit identifies it as a gap).
     */
    public function delete(
        int $id,
        IngestSource $source,
        ?int $actorUserId = null,
        ?string $rationale = null,
        ?string $ipAddress = null,
    ): void {
        parent::delete($id, $source, $actorUserId);
    }

    /**
     * Returns all rows, newest effective_from first. Used by the admin index endpoint.
     *
     * @return Collection<int, TaxConfiguration>
     */
    public function all(): Collection
    {
        return TaxConfiguration::orderByDesc('effective_from')->get();
    }

    /**
     * Find by tax_year (e.g. "2026/27"). Used by the seeder for idempotency.
     */
    public function findByTaxYear(string $taxYear): ?TaxConfiguration
    {
        return TaxConfiguration::where('tax_year', $taxYear)->first();
    }

    /**
     * Return the active tax-configuration row as a canonical array, or null
     * if no row is active. Memoised per-instance — any write that touches
     * is_active (setActive / forgetAll) drops the memo. Consumers that hold
     * their own cache (e.g. TaxConfigService) call forgetActive() to invalidate
     * after out-of-band DB mutations.
     */
    public function activeConfig(): ?array
    {
        if (! array_key_exists('current', $this->activeMemo)) {
            $row = TaxConfiguration::where('is_active', true)->first();
            $this->activeMemo['current'] = $row ? $this->read($row->id) : null;
        }

        return $this->activeMemo['current'];
    }

    /**
     * Invalidate the activeConfig() memo. Public so external consumers
     * (TaxConfigService::clearCache, integration tests, the admin Cache::flush
     * call site) can force a re-read after out-of-band mutations.
     */
    public function forgetActive(): void
    {
        $this->activeMemo = [];
    }

    /**
     * Return the Eloquent model for a given id, or null. Used by the admin
     * controller to construct response Resources without touching the model
     * directly (boundary enforcement; spec §5.1). Callers needing the active
     * row's model can chain: $store->findEloquent($store->activeConfig()['id']).
     */
    public function findEloquent(int $id): ?TaxConfiguration
    {
        return TaxConfiguration::find($id);
    }

    // ── Abstract base overrides ───────────────────────────────────────────────

    protected function persist(array $canonical, ?int $id = null): int
    {
        if ($id === null) {
            return TaxConfiguration::create($canonical)->id;
        }

        TaxConfiguration::where('id', $id)->update($canonical);

        return $id;
    }

    protected function read(int $id): array
    {
        $row = TaxConfiguration::find($id);

        if (! $row) {
            return [];
        }

        $arr = $row->toArray();
        // Carbon date casts serialise to ISO 8601; canonicalise to Y-m-d so the
        // partial-merge → normaliser path round-trips against MySQL DATE columns.
        if ($row->effective_from !== null) {
            $arr['effective_from'] = $row->effective_from->format('Y-m-d');
        }
        if ($row->effective_to !== null) {
            $arr['effective_to'] = $row->effective_to->format('Y-m-d');
        }

        return $arr;
    }

    protected function delete_(int $id): void
    {
        TaxConfiguration::where('id', $id)->delete();
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function writeAudit(
        int $configId,
        string $changeType,
        ?array $beforeState,
        ?int $actorUserId,
        ?string $rationale,
        ?string $ipAddress,
    ): void {
        $config = TaxConfiguration::find($configId);
        if (! $config) {
            return; // Already deleted (delete path writes audit BEFORE removing the row, so this is the post-delete safety net)
        }

        TaxConfigurationAudit::log(
            $config,
            $changeType,
            $beforeState,
            $actorUserId,
            $rationale,
            $ipAddress,
        );
    }

    /**
     * Visible mirror of the base guard so setActive() can validate the source
     * before opening the transaction (the base's create/update/delete already
     * guard internally, but setActive bypasses those entry points).
     */
    private function guardSource(IngestSource $source): void
    {
        if (! in_array($source, [IngestSource::ADMIN, IngestSource::SEEDER], true)) {
            throw new StoreValidationException(
                ['ingest_source' => "Reference-data writes only permitted from ADMIN or SEEDER (got: {$source->value})"]
            );
        }
    }

    /**
     * Drop the entire per-request memoised cache (both the id-keyed `cache`
     * on the base class and this store's `activeMemo`). Used after setActive()
     * which can flip is_active on any/all rows.
     */
    private function forgetAll(): void
    {
        $reflection = new \ReflectionClass(ReferenceDataStore::class);
        $cacheProp = $reflection->getProperty('cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($this, []);

        $this->activeMemo = [];
    }
}
