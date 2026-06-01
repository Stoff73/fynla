<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;

/** Model/builder archetype — formalises the existing records-summary fetch. */
final class UserFinancialHandler implements FetchHandler
{
    public function __construct(private readonly AdvicePromptBuilder $advice) {}

    public function id(): string
    {
        return 'user_financial';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        $summary = $this->advice->buildExistingRecordsSummary($ctx->user, null);

        // Source version = newest record touch, so provenance reflects data freshness.
        $version = (string) ($ctx->user->updated_at?->toDateString() ?? 'unknown');

        return FetchResult::make($summary, 'user records', $version);
    }
}
