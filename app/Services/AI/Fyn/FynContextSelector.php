<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Services\AI\AdviceFyn;

/**
 * Maps a FynTurnContext onto the 4 context buckets (spec §6).
 * Reuses AdviceFyn::engineCallLevelFor as the factual signal — does
 * not redefine the classification taxonomy.
 */
final class FynContextSelector
{
    /** @return list<ContextBucket> */
    public function buckets(FynTurnContext $ctx): array
    {
        if ($ctx->isOnboarding()) {
            return [ContextBucket::IDENTITY, ContextBucket::CAPTURE];
        }

        $primary = $ctx->classification['primary'] ?? null;
        $isFactual = $primary !== null
            && AdviceFyn::engineCallLevelFor($primary) === 'factual';

        if ($isFactual) {
            return [ContextBucket::IDENTITY];
        }

        return [
            ContextBucket::IDENTITY,
            ContextBucket::POSITION,
            ContextBucket::READINESS,
        ];
    }
}
