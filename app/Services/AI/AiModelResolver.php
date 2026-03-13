<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;

class AiModelResolver
{
    private const DEFAULT_MODEL = 'llama3.1-8b';

    public function getModel(User $user): string
    {
        return config('services.cerebras.chat_model', self::DEFAULT_MODEL);
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
