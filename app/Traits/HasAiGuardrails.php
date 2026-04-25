<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AiConversation;
use App\Models\AiDailyUsage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Provides model selection, token budget management, error categorisation,
 * and permission checks for AI chat capabilities.
 */
trait HasAiGuardrails
{
    private const DEFAULT_MODEL_ANTHROPIC = 'claude-haiku-4-5-20251001';

    private const DEFAULT_MODEL_XAI = 'grok-4-1-fast-reasoning';

    /**
     * Get the active AI provider, checking admin toggle (cache) first, then .env.
     *
     * Delegates to {@see getAiProviderForLoop()} so every reader goes
     * through the same versioned-cache path. Kept as a thin wrapper for
     * backward compatibility with existing call sites (`HasAiChat::chat`,
     * `AdviceFyn::buildToolList`, etc.).
     */
    protected static function getAiProvider(): string
    {
        return static::getAiProviderForLoop();
    }

    /**
     * Resolve the active AI provider for an in-flight chat() call.
     *
     * Reads the versioned cache key written by AdminController::setAiProvider.
     * Callers that span an entire chat loop (HasAiChat::chat) MUST capture
     * the result ONCE at the top and reuse the local snapshot for every
     * iteration — otherwise a mid-stream admin toggle can swap the
     * provider mid-loop and cause Anthropic prompt-cache markers to leak
     * into xAI (or vice versa), producing a 400 from the wrong endpoint
     * (S0.11.4 / INV-2.9.4).
     *
     * Lookup order (first match wins):
     *  1. Versioned key `ai_provider:v{N}` where N is `ai_provider_version`
     *     (the canonical post-S0.11.4 path — admin toggle bumps the version
     *     atomically so any in-flight reader never sees a torn write).
     *  2. Legacy unversioned `ai_provider` key (kept for backward
     *     compatibility with existing tests / fixtures that wrote the old
     *     key directly via `Cache::forever('ai_provider', ...)`).
     *  3. The `services.ai_provider` config default ('anthropic').
     */
    protected static function getAiProviderForLoop(): string
    {
        $version = (int) \Illuminate\Support\Facades\Cache::get('ai_provider_version', 0);

        if ($version > 0) {
            return \Illuminate\Support\Facades\Cache::get(
                "ai_provider:v{$version}",
                config('services.ai_provider', 'anthropic')
            );
        }

        return \Illuminate\Support\Facades\Cache::get(
            'ai_provider',
            config('services.ai_provider', 'anthropic')
        );
    }

    private const DAILY_TOKEN_LIMITS = [
        'preview' => 100_000,
        'trial' => 1_000_000,
        'student' => 300_000,
        'standard' => 1_000_000,
        'family' => 1_500_000,
        'pro' => 2_000_000,
    ];

    /**
     * Get the appropriate model for this user and query complexity.
     * Supports both Anthropic and xAI providers via AI_PROVIDER config.
     */
    protected function getAiModel(User $user, string $complexity = 'standard'): string
    {
        $provider = static::getAiProvider();
        $configKey = $provider === 'xai' ? 'services.xai' : 'services.anthropic';
        $defaultModel = $provider === 'xai' ? self::DEFAULT_MODEL_XAI : self::DEFAULT_MODEL_ANTHROPIC;

        $configModel = config("{$configKey}.chat_model");
        if ($configModel) {
            return $configModel;
        }

        if ($complexity === 'complex' && $this->getUserPlan($user) === 'pro') {
            return config("{$configKey}.advanced_chat_model", $defaultModel);
        }

        return $defaultModel;
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
     * Get token usage details including reset time.
     * Resets daily at midnight (00:00 UTC).
     */
    public function getTokenUsageDetails(User $user): array
    {
        $plan = $user->is_preview_user ? 'preview' : $this->getUserPlan($user);
        $limit = self::DAILY_TOKEN_LIMITS[$plan] ?? self::DAILY_TOKEN_LIMITS['student'];
        $used = $this->getTodayTokenUsage($user);
        $remaining = max(0, $limit - $used);

        // Reset is at midnight tomorrow
        $resetAt = now()->copy()->addDay()->startOfDay();
        $secondsUntilReset = now()->diffInSeconds($resetAt);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percent_used' => $limit > 0 ? round(($used / $limit) * 100) : 0,
            'has_budget' => $used < $limit,
            'reset_at' => $resetAt->toIso8601String(),
            'seconds_until_reset' => $secondsUntilReset,
        ];
    }

    /**
     * Invalidate the cached daily usage (call after each message).
     *
     * No-op since S0.11.1 — daily usage is now read directly from
     * `ai_daily_usage` with no cache layer between, so there is nothing
     * to invalidate. Kept for backward compatibility with any external
     * caller that may still invoke it.
     */
    protected function invalidateDailyUsageCache(User $user): void
    {
        // S0.11.1: deliberately a no-op. Also clear the legacy cache
        // key in case any pre-deploy fixture / running process wrote
        // it; harmless if absent.
        Cache::forget("ai_daily_tokens_{$user->id}_".now()->format('Y-m-d'));
    }

    /**
     * Atomically record token usage for the current day.
     *
     * Wrapped in a DB::transaction with a `SELECT ... FOR UPDATE` row
     * lock so concurrent calls for the same user serialise on the row.
     * The (user_id, usage_date) unique key on the ai_daily_usage table
     * makes firstOrCreate atomic against the race where two parallel
     * processes simultaneously discover the row is missing.
     *
     * Closes INV-2.9.1: the pre-S0.11.1 path used a 5-minute Cache
     * remember layer, so two parallel chat() calls could both pass
     * hasTokenBudget on stale data and silently exceed the daily limit.
     */
    protected function recordTokenUsage(User $user, int $tokens): void
    {
        if ($tokens <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $tokens) {
            $today = now()->toDateString();

            // The unique (user_id, usage_date) key makes firstOrCreate
            // atomic against the create-create race; the followup
            // lockForUpdate query holds the row exclusively for the rest
            // of the transaction so the increment cannot be lost.
            AiDailyUsage::firstOrCreate(
                ['user_id' => $user->id, 'usage_date' => $today],
                ['tokens_used' => 0]
            );

            AiDailyUsage::query()
                ->where('user_id', $user->id)
                ->where('usage_date', $today)
                ->lockForUpdate()
                ->first()
                ?->increment('tokens_used', $tokens);
        });
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

        if (! $subscription) {
            return 'student';
        }

        // Trialing users get the trial tier regardless of plan slug
        if ($subscription->isTrialing()) {
            return 'trial';
        }

        return $subscription->plan ?? 'student';
    }

    /**
     * Get today's token usage for a user — read directly from
     * ai_daily_usage with no cache layer (S0.11.1).
     *
     * Pre-S0.11.1 this method cached the result for 5 minutes and read
     * by summing ai_conversations.total_input_tokens +
     * total_output_tokens for rows updated today. The cache window
     * created the race that allowed parallel chats to silently exceed
     * the daily limit. The new path reads the canonical row from
     * ai_daily_usage which is written transactionally by
     * recordTokenUsage on every chat completion.
     */
    private function getTodayTokenUsage(User $user): int
    {
        $row = AiDailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', now()->toDateString())
            ->first(['tokens_used']);

        return $row !== null ? (int) $row->tokens_used : 0;
    }
}
