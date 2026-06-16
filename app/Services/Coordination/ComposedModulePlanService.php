<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\User;
use App\Services\Coordination\PlanSources\ModuleStrategySource;

/**
 * Module-agnostic composed plan: takes any ModuleStrategySource, joins its
 * recommendations with its catalogue metadata, derives the locked list
 * (enabled strategy rows whose required_data are not all available and which
 * produced no recommendation), and runs the pure StrategyPlanComposer.
 * Generalised verbatim from ComposedTaxPlanService::forUser — tax is now one
 * source among many. Locked strategies are surfaced as unlock prompts, never
 * silently skipped.
 */
final class ComposedModulePlanService
{
    public function __construct(private readonly StrategyPlanComposer $composer) {}

    /**
     * @return array{items: list<array<string,mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}
     */
    public function forSource(ModuleStrategySource $source, User $user): array
    {
        $recommendations = $source->recommendations($user);
        $rows = $source->metadataRows();

        $metadata = [];
        foreach ($rows as $row) {
            $metadata[$row->strategy_type] = [
                'claim_tier' => (string) $row->claim_tier,
                'sequencing' => $row->sequencing ?? ['do_before' => [], 'conflicts_with' => []],
            ];
        }

        $availability = $source->availability($user);
        $firedTypes = array_map(fn (StrategyRecommendation $r): string => $r->type, $recommendations);

        $locked = [];
        foreach ($rows as $row) {
            $missing = array_values(array_filter(
                (array) ($row->required_data ?? []),
                fn (string $key): bool => ($availability[$key] ?? false) === false
            ));
            if ($missing !== [] && ! in_array($row->strategy_type, $firedTypes, true)) {
                $locked[] = ['strategy_type' => (string) $row->strategy_type, 'missing' => $missing];
            }
        }

        return $this->composer->compose($recommendations, $metadata, $locked);
    }

    /**
     * Module-agnostic strategy-id derivation (the canonical home — the tax
     * facade delegates here). Surfaced item types + locked strategy types,
     * consumed by both Fyn provenance paths so they can never drift apart.
     *
     * @param  array{items: list<array<string,mixed>>, locked: list<array{strategy_type: string, missing: list<string>}>}  $plan
     * @return array{surfaced: list<string>, locked: list<string>}
     */
    public static function extractStrategyIds(array $plan): array
    {
        $surfaced = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['type'] ?? ''),
            $plan['items'],
        ), static fn (string $id): bool => $id !== ''));

        $locked = array_values(array_map(
            static fn (array $l): string => (string) $l['strategy_type'],
            $plan['locked'],
        ));

        return ['surfaced' => $surfaced, 'locked' => $locked];
    }

    /**
     * The harmonised plan digest — the same encoding RecommendationHandler::fetch
     * passes to FetchResult::make, so the same plan yields the same digest on
     * the skill and tool paths. The 'composed_tax_plan' key is the digest
     * namespace for EVERY module (not a tax label) and is byte-stable — pinned
     * by RecommendationHandlerParityTest. Do not rename it.
     *
     * @param  array<string, mixed>  $plan
     */
    public static function planDigest(array $plan): string
    {
        return substr(hash('sha256', (string) json_encode(['composed_tax_plan' => $plan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 16);
    }
}
