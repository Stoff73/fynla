<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One module's contribution to a composed plan: the three module-specific
 * inputs the generic ComposedModulePlanService needs. Implementations adapt
 * each module's bespoke recommendation engine + catalogue + data-availability
 * into the common currency. The composition itself (sort, sequencing, conflict
 * resolution, locked derivation) is module-agnostic and lives in
 * ComposedModulePlanService + the pure StrategyPlanComposer.
 */
interface ModuleStrategySource
{
    /** Stable module key: 'tax', 'retirement', 'savings', 'investment', 'protection', 'estate'. */
    public function moduleKey(): string;

    /**
     * The eligible recommendations for this module, as the common DTO.
     *
     * @return list<StrategyRecommendation>
     */
    public function recommendations(User $user): array;

    /**
     * The enabled source='strategy' catalogue rows for this module, each exposing
     * strategy_type, claim_tier, sequencing (array{do_before:list,conflicts_with:list}),
     * and required_data (list<string>). One row per strategy_type.
     *
     * @return Collection<int, Model>
     */
    public function metadataRows(): Collection;

    /**
     * Data-availability map keyed by the required_data vocabulary.
     *
     * @return array<string, bool>
     */
    public function availability(User $user): array;
}
