<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiModelResolver
{
    private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

    private const ADVANCED_MODEL = 'claude-sonnet-4-5-20241022';

    private const DAILY_TOKEN_LIMITS = [
        'student' => 50_000,
        'standard' => 200_000,
        'pro' => 500_000,
    ];

    public function getModel(User $user, string $complexity = 'standard'): string
    {
        $configModel = config('services.anthropic.chat_model');
        if ($configModel) {
            return $configModel;
        }

        // Pro users get the advanced model for complex queries
        if ($complexity === 'complex' && $this->getUserPlan($user) === 'pro') {
            return config('services.anthropic.advanced_chat_model', self::ADVANCED_MODEL);
        }

        return self::DEFAULT_MODEL;
    }

    public function getMaxTokens(User $user): int
    {
        $plan = $this->getUserPlan($user);

        return match ($plan) {
            'pro' => 8192,
            default => 4096,
        };
    }

    public function classifyComplexity(string $message, int $conversationDepth = 0): string
    {
        $lower = strtolower($message);

        $complexPatterns = [
            'financial plan', 'holistic plan', 'comprehensive',
            'what if', 'scenario', 'compare',
            'inheritance tax', 'estate planning',
            'pension transfer', 'retirement projection',
            'tax efficiency', 'capital gains',
        ];

        foreach ($complexPatterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return 'complex';
            }
        }

        // Long conversations tend to be more complex
        if ($conversationDepth > 6) {
            return 'complex';
        }

        return 'standard';
    }

    public function hasTokenBudget(User $user): bool
    {
        $plan = $this->getUserPlan($user);
        $limit = self::DAILY_TOKEN_LIMITS[$plan] ?? self::DAILY_TOKEN_LIMITS['student'];

        $todayUsage = $this->getTodayTokenUsage($user);

        return $todayUsage < $limit;
    }

    public function getTokenBudgetStatus(User $user): array
    {
        $plan = $this->getUserPlan($user);
        $limit = self::DAILY_TOKEN_LIMITS[$plan] ?? self::DAILY_TOKEN_LIMITS['student'];
        $used = $this->getTodayTokenUsage($user);

        return [
            'plan' => $plan,
            'daily_limit' => $limit,
            'used_today' => $used,
            'remaining' => max(0, $limit - $used),
            'percentage_used' => $limit > 0 ? round(($used / $limit) * 100, 1) : 100,
        ];
    }

    /**
     * Invalidate the cached daily usage (call after each message).
     */
    public function invalidateDailyUsageCache(User $user): void
    {
        $cacheKey = "ai_daily_tokens_{$user->id}_".now()->format('Y-m-d');
        Cache::forget($cacheKey);
    }

    private function getUserPlan(User $user): string
    {
        $subscription = $user->relationLoaded('subscription')
            ? $user->subscription
            : $user->subscription()->first();

        return $subscription?->plan ?? 'student';
    }

    private function getTodayTokenUsage(User $user): int
    {
        $cacheKey = "ai_daily_tokens_{$user->id}_".now()->format('Y-m-d');

        return Cache::remember($cacheKey, 300, function () use ($user) {
            return (int) AiConversation::where('user_id', $user->id)
                ->whereDate('updated_at', now()->toDateString())
                ->sum(DB::raw('total_input_tokens + total_output_tokens'));
        });
    }
}
