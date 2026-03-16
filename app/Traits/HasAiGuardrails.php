<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Provides model selection, token budget management, error categorisation,
 * and permission checks for AI chat capabilities.
 */
trait HasAiGuardrails
{
    private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

    private const DAILY_TOKEN_LIMITS = [
        'preview' => 10_000,
        'student' => 50_000,
        'standard' => 200_000,
        'pro' => 500_000,
    ];

    /**
     * Get the appropriate model for this user and query complexity.
     */
    protected function getAiModel(User $user, string $complexity = 'standard'): string
    {
        $configModel = config('services.anthropic.chat_model');
        if ($configModel) {
            return $configModel;
        }

        if ($complexity === 'complex' && $this->getUserPlan($user) === 'pro') {
            return config('services.anthropic.advanced_chat_model', self::DEFAULT_MODEL);
        }

        return self::DEFAULT_MODEL;
    }

    /**
     * Get max output tokens for this user's plan.
     */
    protected function getAiMaxTokens(User $user): int
    {
        $plan = $this->getUserPlan($user);

        return match ($plan) {
            'pro' => 8192,
            default => 4096,
        };
    }

    /**
     * Classify query complexity from message content and conversation depth.
     */
    protected function classifyComplexity(string $message, int $conversationDepth = 0): string
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

        if ($conversationDepth > 6) {
            return 'complex';
        }

        return 'standard';
    }

    /**
     * Check if the user has remaining token budget for today.
     */
    protected function hasTokenBudget(User $user): bool
    {
        $plan = $user->is_preview_user ? 'preview' : $this->getUserPlan($user);
        $limit = self::DAILY_TOKEN_LIMITS[$plan] ?? self::DAILY_TOKEN_LIMITS['student'];

        $todayUsage = $this->getTodayTokenUsage($user);

        return $todayUsage < $limit;
    }

    /**
     * Invalidate the cached daily usage (call after each message).
     */
    protected function invalidateDailyUsageCache(User $user): void
    {
        $cacheKey = "ai_daily_tokens_{$user->id}_".now()->format('Y-m-d');
        Cache::forget($cacheKey);
    }

    /**
     * Categorise an API error into a user-friendly message.
     */
    protected function categoriseApiError(?string $errorMessage, ?int $httpStatus, ?string $errorType): string
    {
        if ($httpStatus === 429) {
            return "You've sent several messages quickly. Please wait a moment before trying again.";
        }

        if ($httpStatus === 529) {
            return 'The service is temporarily busy. Please try again in a moment.';
        }

        if ($httpStatus === 401 || $httpStatus === 403) {
            return 'Configuration issue — please contact support.';
        }

        $errorString = ($errorMessage ?? '').' '.($errorType ?? '');
        $errorLower = strtolower($errorString);

        if (str_contains($errorLower, 'api_key') || str_contains($errorLower, 'authentication') || str_contains($errorLower, 'invalid_api_key')) {
            return 'Configuration issue — please contact support.';
        }

        if (str_contains($errorLower, 'context_length') || str_contains($errorLower, 'token') || str_contains($errorLower, 'too many tokens') || str_contains($errorLower, 'max_tokens')) {
            return 'This conversation has become quite long. Starting a new conversation may help.';
        }

        if (str_contains($errorLower, 'overloaded') || str_contains($errorLower, 'capacity')) {
            return 'The service is temporarily busy. Please try again in a moment.';
        }

        if (str_contains($errorLower, 'rate_limit')) {
            return "You've sent several messages quickly. Please wait a moment before trying again.";
        }

        return 'I apologise, but I encountered an issue processing your request. Please try again.';
    }

    /**
     * Get the user's subscription plan.
     */
    private function getUserPlan(User $user): string
    {
        if ($user->is_preview_user) {
            return 'preview';
        }

        $subscription = $user->relationLoaded('subscription')
            ? $user->subscription
            : $user->subscription()->first();

        return $subscription?->plan ?? 'student';
    }

    /**
     * Get today's token usage for a user (cached for 5 minutes).
     */
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
