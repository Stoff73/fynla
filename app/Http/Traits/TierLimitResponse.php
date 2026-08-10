<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Services\Stores\Exceptions\TierLimitExceededException;
use Illuminate\Http\JsonResponse;

trait TierLimitResponse
{
    protected function tierLimitResponse(
        TierLimitExceededException $exception,
        string $message,
        string $fallback = 'dashboard',
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => 'tier_limit_reached',
            'error_type' => 'tier_limit_reached',
            'entity_key' => $exception->entityKey,
            'current_count' => $exception->currentCount,
            'hard_limit' => $exception->hardLimit,
            'required_tier' => 'premium',
            'message' => $message,
            'action' => 'subscription_options',
            'destination' => [
                'screen' => 'subscription',
                'params' => [],
                'fallback' => $fallback,
            ],
        ], 403);
    }
}
