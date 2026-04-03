<?php

declare(strict_types=1);

namespace App\Agents;

use App\Constants\TaxDefaults;
use App\Traits\FormatsCurrency;
use Illuminate\Support\Facades\Cache;

abstract class BaseAgent
{
    use FormatsCurrency;

    protected const CACHE_VERSION = 'v1';

    /**
     * Cache time-to-live in seconds.
     * Uses TaxDefaults::CACHE_TTL_STANDARD for consistency across agents.
     */
    protected int $cacheTtl = TaxDefaults::CACHE_TTL_STANDARD;

    /**
     * Analyze user data and generate insights.
     */
    abstract public function analyze(int $userId): array;

    /**
     * Generate personalized recommendations based on analysis.
     */
    abstract public function generateRecommendations(array $analysisData): array;

    /**
     * Build what-if scenarios for user planning.
     */
    abstract public function buildScenarios(int $userId, array $parameters): array;

    /**
     * Get cached data or execute callback and cache result.
     *
     * @param  string  $key  Cache key
     * @param  callable  $callback  Callback to execute if cache miss
     * @param  int|null  $ttl  Time to live in seconds (null uses default)
     */
    protected function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $ttl = $ttl ?? $this->cacheTtl;

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Get a standardized cache key for user-specific data.
     *
     * @param  int  $userId  User ID
     * @param  string  $suffix  Cache key suffix (e.g., 'analysis', 'recommendations')
     */
    protected function getUserCacheKey(int $userId, string $suffix): string
    {
        $agentName = strtolower(class_basename(static::class));

        return static::CACHE_VERSION."_{$agentName}_{$userId}_{$suffix}";
    }

    /**
     * Clear all cached data for a specific user.
     *
     * @param  int  $userId  User ID
     * @param  array  $suffixes  Cache key suffixes to clear (default: common suffixes)
     */
    public function clearUserCache(int $userId, array $suffixes = ['analysis', 'recommendations', 'scenarios']): void
    {
        foreach ($suffixes as $suffix) {
            Cache::forget($this->getUserCacheKey($userId, $suffix));
        }
    }

    /**
     * Invalidate all cache entries for a user.
     *
     * This is the standardised method for cache invalidation across all agents.
     *
     * @param  int  $userId  User ID
     * @param  array  $additionalKeys  Additional specific cache keys to clear
     */
    public function invalidateUserCache(int $userId, array $additionalKeys = []): void
    {
        $agentName = strtolower(class_basename(static::class));

        // Clear specific known keys
        $defaultSuffixes = ['analysis', 'recommendations', 'scenarios', 'summary', 'projection'];
        foreach ($defaultSuffixes as $suffix) {
            Cache::forget($this->getUserCacheKey($userId, $suffix));
        }

        // Clear any agent-specific cache key pattern
        Cache::forget(static::CACHE_VERSION."_{$agentName}_analysis_{$userId}");

        // Clear additional specified keys
        foreach ($additionalKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear cache for multiple users (useful for joint accounts).
     *
     * @param  array<int>  $userIds  Array of user IDs
     * @param  array  $additionalKeys  Additional keys to clear per user
     */
    public function invalidateCacheForUsers(array $userIds, array $additionalKeys = []): void
    {
        foreach ($userIds as $userId) {
            if ($userId !== null) {
                $this->invalidateUserCache($userId, $additionalKeys);
            }
        }
    }

    /**
     * Remember user-specific data with standardized cache key.
     *
     * @param  int  $userId  User ID
     * @param  string  $suffix  Cache key suffix
     * @param  callable  $callback  Callback to execute on cache miss
     * @param  int|null  $ttl  Time to live in seconds
     */
    protected function rememberForUser(int $userId, string $suffix, callable $callback, ?int $ttl = null): mixed
    {
        $key = $this->getUserCacheKey($userId, $suffix);

        return $this->remember($key, $callback, $ttl);
    }

    /**
     * Generate a standardized response format.
     */
    protected function response(bool $success, string $message, array $data = []): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Round to nearest penny (2 decimal places).
     */
    protected function roundToPenny(float $value): float
    {
        return round($value, 2);
    }
}
