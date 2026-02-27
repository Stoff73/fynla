<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;

class AiModelResolver
{
    private const MODEL_PRO = 'claude-sonnet-4-6-20250514';

    private const MODEL_STANDARD = 'claude-haiku-4-5-20251001';

    public function getModel(User $user): string
    {
        $plan = $this->getUserPlan($user);

        return match ($plan) {
            'pro' => config('services.anthropic.chat_model_pro', self::MODEL_PRO),
            default => config('services.anthropic.chat_model_standard', self::MODEL_STANDARD),
        };
    }

    public function getMaxTokens(User $user): int
    {
        $plan = $this->getUserPlan($user);

        return match ($plan) {
            'pro' => 4096,
            default => 2048,
        };
    }

    private function getUserPlan(User $user): string
    {
        $subscription = $user->relationLoaded('subscription')
            ? $user->subscription
            : $user->subscription()->first();

        return $subscription?->plan ?? 'student';
    }
}
