<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\AuditLog;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\Stores\Exceptions\TierConfigValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TierConfigurationStore
{
    public const TIERS = ['free', 'premium'];

    public const RETIRED_TIERS = ['tier1', 'tier2', 'tier3'];

    /**
     * @return list<string>
     */
    public static function paidTiers(): array
    {
        return array_values(array_diff(self::TIERS, ['free']));
    }

    /** Per-request memoisation: tier => TierConfiguration */
    private array $cache = [];

    public static function canonicalPlanForEntitlement(string $plan): string
    {
        return in_array($plan, self::RETIRED_TIERS, true) ? 'premium' : $plan;
    }

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

    public function priceForCycle(string $tier, string $billingCycle): int
    {
        $configuration = $this->forTier($tier);

        return $billingCycle === 'monthly'
            ? $configuration->price_monthly_pence
            : $configuration->price_annual_pence;
    }

    /**
     * All active tier configurations ordered Free, then Premium.
     * Read-only consumer entry point so HTTP controllers never touch the
     * TierConfiguration model directly (SP1 §12 single-source-of-truth moat).
     *
     * @return Collection<int, TierConfiguration>
     */
    public function allActiveOrdered(): Collection
    {
        return TierConfiguration::where('is_active', true)
            ->whereIn('tier', self::TIERS)
            ->orderByRaw('FIELD(tier, ?, ?)', self::TIERS)
            ->get();
    }

    /**
     * Every tier configuration (active and inactive) ordered Free, then
     * Premium. Admin-screen read entry point so the admin controller
     * never touches the TierConfiguration model directly (SP1 §12 moat).
     *
     * @return Collection<int, TierConfiguration>
     */
    public function allOrdered(): Collection
    {
        return TierConfiguration::whereIn('tier', self::TIERS)
            ->orderByRaw('FIELD(tier, ?, ?)', self::TIERS)
            ->get();
    }

    /**
     * Return the lowest active tier whose capability for $capabilityKey equals $verb,
     * or null if no such tier exists. Tiers are evaluated in canonical ascending order
     * (Free, then Premium). Used to derive accurate CTA targets from the
     * store rather than hardcoding (SP2 PR7 plan §7.3).
     *
     * @return array{tier: string, display_name: string}|null
     */
    public function lowestTierWithCapability(string $capabilityKey, string $verb): ?array
    {
        foreach (self::TIERS as $candidate) {
            try {
                $config = $this->forTier($candidate);
            } catch (ModelNotFoundException) {
                continue;
            }

            $matrix = $config->capability_matrix ?? [];
            if (($matrix[$capabilityKey] ?? null) === $verb) {
                return ['tier' => $config->tier, 'display_name' => $config->display_name];
            }
        }

        return null;
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
