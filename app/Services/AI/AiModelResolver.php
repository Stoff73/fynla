<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;

class AiModelResolver
{
    private const MODEL_PRO = 'gpt-5-mini-2025-08-07';

    private const MODEL_STANDARD = 'gpt-5-mini-2025-08-07';

    public function getModel(User $user): string
    {
        $plan = $this->getUserPlan($user);

        return match ($plan) {
            'pro' => config('services.openai.chat_model_pro', self::MODEL_PRO),
            default => config('services.openai.chat_model_standard', self::MODEL_STANDARD),
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
