<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\Coordination\ComposedTaxPlanService;
use Illuminate\Support\Carbon;

/** Engine archetype — the live composed tax plan from the recommendation engine. */
final class RecommendationHandler implements FetchHandler
{
    public function __construct(private readonly ComposedTaxPlanService $plans) {}

    public function id(): string
    {
        return 'recommendations';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        // §6b — the skill returns the same composed plan as get_recommendations,
        // so skill and tool cannot disagree (shape-parity pinned in tests).
        $plan = $this->plans->forUser($ctx->user);

        $surfaced = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['type'] ?? ''),
            $plan['items'],
        ), static fn (string $id): bool => $id !== ''));

        $locked = array_values(array_map(
            static fn (array $l): string => $l['strategy_type'],
            $plan['locked'],
        ));

        return FetchResult::make(
            (string) json_encode(['composed_tax_plan' => $plan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'recommendation engine',
            Carbon::now()->toDateString(),
            [
                'strategy_ids' => implode(',', $surfaced),
                'locked_strategy_ids' => implode(',', $locked),
            ],
        );
    }
}
