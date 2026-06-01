<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Agents\CoordinatingAgent;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use Illuminate\Support\Carbon;

/** Engine archetype — live recommendations from the recommendation engine. */
final class RecommendationHandler implements FetchHandler
{
    public function __construct(private readonly CoordinatingAgent $coordinator) {}

    public function id(): string
    {
        return 'recommendations';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        $analysis = $this->coordinator->orchestrateAnalysis($ctx->user->id);
        $recs = $analysis['ranked_recommendations'] ?? [];

        $lines = [];
        foreach ($recs as $r) {
            $title = is_array($r) ? ($r['title'] ?? $r['description'] ?? '') : (string) $r;
            if ($title !== '') {
                $lines[] = '- '.$title;
            }
        }

        $value = $lines === [] ? 'No current recommendations.' : "Current recommendations:\n".implode("\n", $lines);

        return FetchResult::make($value, 'recommendation engine', Carbon::now()->toDateString());
    }
}
