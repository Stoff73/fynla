<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\Coordination\CompositePlanService;
use Illuminate\Support\Carbon;

/**
 * The cross-module composite plan as a live pointer fetch — every module's
 * composed plan, ranked by affordability against the household's effective
 * monthly surplus (after goal commitments), with nothing dropped. Tool-mode
 * only (explicit ask), so it never fattens a prefetch turn. Read-side only:
 * composing a plan never writes, so it is safe on the read-only advice surface.
 */
final class CrossModulePlanHandler implements FetchHandler
{
    public function __construct(private readonly CompositePlanService $composite) {}

    public function id(): string
    {
        return 'cross-module-plan';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        $plan = $this->composite->compose($ctx->user);

        return FetchResult::make(
            (string) json_encode(['composite_plan' => $plan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'cross-module plan engine',
            Carbon::now()->toDateString(),
            [
                'item_count' => (string) count($plan['items'] ?? []),
                'locked_count' => (string) count($plan['locked'] ?? []),
            ],
        );
    }
}
