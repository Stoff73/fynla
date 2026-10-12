<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AiDailyUsage;
use App\Models\User;
use App\Services\AI\AiProvider;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Provides model selection, token budget management, error categorisation,
 * and permission checks for AI chat capabilities.
 */
trait HasAiGuardrails
{
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
     * Callers that span an entire chat loop (HasAiChat::chat) MUST capture
     * the result ONCE at the top and reuse the local snapshot for every
     * iteration — otherwise a mid-stream admin toggle can swap the
     * provider mid-loop and cause Anthropic prompt-cache markers to leak
     * into an OpenAI-format provider (or vice versa), producing a 400 from
     * the wrong endpoint (S0.11.4 / INV-2.9.4). The lookup order lives in
     * {@see AiProvider::active()}.
     */
    protected static function getAiProviderForLoop(): string
    {
        return AiProvider::active();
    }

    // ─── Tier-store helpers (PR 6) ────────────────────────────────────────

    private function tierStore(): TierConfigurationStore
    {
        return app(TierConfigurationStore::class);
    }

    private function userTier(User $user): string
    {
        return app(TierResolver::class)->resolve($user);
    }

    /**
     * Sum of tokens used in the rolling 7-day window (today + 6 prior days).
     */
    private function weeklyTokenUsage(User $user): int
    {
        return (int) AiDailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', '>=', now()->subDays(6)->toDateString())
            ->sum('tokens_used');
    }

    /**
     * The tier's one Fyn limit: tokens per rolling week (T48, CSJ 2026-10-12).
     */
    private function weeklyTokenLimit(User $user): int
    {
        return $this->tierStore()->forTier($this->userTier($user))->fyn_weekly_token_budget;
    }

    /**
     * True when the user's rolling weekly token total meets or exceeds the
     * tier's weekly limit. This is the only Fyn limit: the turn stops.
     */
    protected function isWeeklyBudgetExceeded(User $user): bool
    {
        // Preview personas are seeded demo users — never metered, and carry
        // no implicit tier-store dependency.
        if ($user->is_preview_user) {
            return false;
        }

        return $this->weeklyTokenUsage($user) >= $this->weeklyTokenLimit($user);
    }

    /**
     * Get the appropriate model for this user and query complexity, on the
     * given provider (a chat loop's snapshot) or the active one ({@see AiProvider}).
     */
    protected function getAiModel(User $user, string $complexity = 'standard', ?string $provider = null): string
    {
        $provider ??= static::getAiProvider();

        if ($complexity === 'complex' && $this->getUserPlan($user) === 'premium') {
            return AiProvider::advancedChatModel($provider);
        }

        return AiProvider::chatModel($provider);
    }

    /**
     * Get max output tokens for this user's plan.
     */
    protected function getAiMaxTokens(User $user): int
    {
        $plan = $this->getUserPlan($user);

        return match ($plan) {
            'premium' => 8192,
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
     * Check if the user has Fyn tokens left this rolling week.
     * Preview users are never limited (isWeeklyBudgetExceeded short-circuits).
     */
    protected function hasTokenBudget(User $user): bool
    {
        return ! $this->isWeeklyBudgetExceeded($user);
    }

    /**
     * Get the rolling week's token usage against the weekly limit, and when
     * Fyn opens again once the limit is reached.
     *
     * Preview personas are never metered; a sentinel limit of PHP_INT_MAX
     * is returned so callers receive a well-formed shape without a tier-store
     * lookup (preview users have no tier row).
     */
    public function getTokenUsageDetails(User $user): array
    {
        $limit = $user->is_preview_user ? PHP_INT_MAX : $this->weeklyTokenLimit($user);
        $used = $this->weeklyTokenUsage($user);

        $resetAt = $this->weeklyResetAt($user, $limit);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'percent_used' => $limit > 0 ? round(($used / $limit) * 100) : 0,
            'has_budget' => $user->is_preview_user || $used < $limit,
            'reset_at' => $resetAt->toIso8601String(),
            'seconds_until_reset' => (int) now()->diffInSeconds($resetAt),
        ];
    }

    /**
     * The first midnight at which the rolling week's total falls under the
     * limit, counting only usage already recorded. Each midnight drops the
     * oldest day from the window; by the seventh, today has left it too.
     */
    private function weeklyResetAt(User $user, int $limit): CarbonInterface
    {
        $byDay = AiDailyUsage::query()
            ->where('user_id', $user->id)
            ->where('usage_date', '>=', now()->subDays(6)->toDateString())
            ->pluck('tokens_used', 'usage_date')
            ->mapWithKeys(fn ($tokens, $date) => [substr((string) $date, 0, 10) => (int) $tokens]);

        for ($days = 1; $days <= 7; $days++) {
            $midnight = now()->addDays($days)->startOfDay();
            $windowStart = $midnight->copy()->subDays(6)->toDateString();
            $total = $byDay->filter(fn (int $tokens, string $date) => $date >= $windowStart)->sum();

            if ($total < $limit) {
                return $midnight;
            }
        }

        return now()->addDays(7)->startOfDay();
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

        if (str_contains($errorLower, 'context_length') || str_contains($errorLower, 'token') || str_contains($errorLower, 'too many tokens') || str_contains($errorLower, 'max_tokens') || str_contains($errorLower, 'max_completion_tokens')) {
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
     * Get the user's canonical entitlement tier.
     */
    private function getUserPlan(User $user): string
    {
        if ($user->is_preview_user) {
            return 'preview';
        }

        return $this->userTier($user);
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
