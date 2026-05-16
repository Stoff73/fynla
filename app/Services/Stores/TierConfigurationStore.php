<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\AuditLog;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Stores\Exceptions\TierConfigValidationException;
use Illuminate\Support\Facades\DB;

class TierConfigurationStore
{
    public const TIERS = ['free', 'tier1', 'tier2', 'tier3'];

    /** Per-request memoisation: tier => TierConfiguration */
    private array $cache = [];

    public function forTier(string $tier): TierConfiguration
    {
        if (! in_array($tier, self::TIERS, true)) {
            throw new TierConfigValidationException(['tier' => "Unknown tier: {$tier}"]);
        }

        return $this->cache[$tier] ??= TierConfiguration::where('tier', $tier)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Count cap for an entity at a tier. null = unlimited / not count-gated.
     *
     * Intentionally conflates "key absent (entity not count-gated)" and
     * "key present but null (explicitly unlimited)" — both return null by
     * design, since callers treat either as "no cap to enforce".
     */
    public function capFor(string $tier, string $entityKey): ?int
    {
        $caps = $this->forTier($tier)->count_caps ?? [];

        return $caps[$entityKey] ?? null;
    }

    /** Capability verb (full|none|limited|teaser) for an entity at a tier. */
    public function capabilityFor(string $tier, string $entityKey): string
    {
        $matrix = $this->forTier($tier)->capability_matrix ?? [];

        return $matrix[$entityKey] ?? 'none';
    }

    /**
     * Admin/seeder-only write. Audited and cache-invalidating. Mirrors the
     * SavingsStore audited-transaction shape; the only legitimate ingest
     * sources are ADMIN and SEEDER (spec §6.1, §12.1).
     */
    public function updateTier(string $tier, array $data, User $actor, IngestSource $source): TierConfiguration
    {
        if (! in_array($tier, self::TIERS, true)) {
            throw new TierConfigValidationException(['tier' => "Unknown tier: {$tier}"]);
        }
        if (! in_array($source, [IngestSource::ADMIN, IngestSource::SEEDER], true)) {
            throw new TierConfigValidationException(['source' => 'tier_configurations is admin/seeder-write only']);
        }

        $allowed = array_intersect_key($data, array_flip([
            'display_name', 'price_monthly_pence', 'price_annual_pence',
            'revolut_plan_variation_id', 'capability_matrix', 'count_caps',
            'document_upload_allowance', 'document_storage_gb',
            'fyn_weekly_token_budget', 'fyn_daily_hard_backstop',
            'currency_display_mode', 'snapshot_surfacing_window_days',
            'open_api_affordance', 'is_active',
        ]));

        return AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($tier, $allowed, $actor, $source) {
            $row = TierConfiguration::where('tier', $tier)->firstOrFail();
            $before = $row->only(array_keys($allowed));
            $row->fill(array_merge($allowed, ['updated_by' => $actor->id]))->save();

            AuditLog::create([
                'user_id' => $actor->id,
                'event_type' => AuditLog::EVENT_ADMIN,
                'action' => AuditLog::ACTION_UPDATED,
                'model_type' => 'tier_configuration',
                'model_id' => $row->id,
                'old_values' => $before,
                'new_values' => $row->only(array_keys($allowed)),
                'metadata' => ['ingest_source' => $source->value],
            ]);

            unset($this->cache[$tier]);

            return $row->fresh();
        }));
    }
}
