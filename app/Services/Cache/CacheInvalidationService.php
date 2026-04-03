<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Centralised cache invalidation for all user-specific caches.
 *
 * Called from controllers and services whenever user financial or
 * personal data changes, ensuring all cached views, dashboards,
 * recommendations, and plans reflect the latest data.
 */
class CacheInvalidationService
{
    /**
     * Agent names used in BaseAgent cache key format: v1_{agent}_{userId}_{suffix}
     */
    private const AGENTS = [
        'protectionagent',
        'savingsagent',
        'investmentagent',
        'retirementagent',
        'estateagent',
        'goalsagent',
        'coordinatingagent',
    ];

    /**
     * Standard agent cache key suffixes.
     */
    private const AGENT_SUFFIXES = [
        'analysis',
        'recommendations',
        'scenarios',
        'summary',
        'projection',
    ];

    /**
     * Plan types that are cached per user.
     */
    private const PLAN_TYPES = [
        'protection',
        'savings',
        'investment',
        'retirement',
        'estate',
        'goals',
    ];

    /**
     * Module names used for mobile summary caches.
     */
    private const MODULES = [
        'protection',
        'savings',
        'investment',
        'retirement',
        'estate',
        'goals',
    ];

    /**
     * Invalidate all user-specific caches.
     *
     * This clears every cache key tied to a user ID, forcing fresh
     * recalculation on the next request. The new result is then
     * cached for 24 hours (or until the next data change).
     */
    public function invalidateForUser(int $userId): void
    {
        // Dashboard & alerts
        Cache::forget("dashboard_{$userId}");
        Cache::forget("alerts_{$userId}");
        Cache::forget("mobile_dashboard_{$userId}");

        // Module analysis caches
        Cache::forget("protection_analysis_{$userId}");
        Cache::forget("savings_analysis_{$userId}");
        Cache::forget("estate_analysis_{$userId}");
        Cache::forget("retirement_analysis_{$userId}");
        Cache::forget("retirement_projection_{$userId}");
        Cache::forget("retirement_income_{$userId}");
        Cache::forget("dc_pensions_portfolio_{$userId}");

        // Agent caches (v1_{agent}_{userId}_{suffix})
        foreach (self::AGENTS as $agent) {
            foreach (self::AGENT_SUFFIXES as $suffix) {
                Cache::forget("v1_{$agent}_{$userId}_{$suffix}");
            }
        }

        // Holistic planning
        Cache::forget("holistic_plan_{$userId}");
        Cache::forget("holistic_analysis_{$userId}");

        // Module plans
        foreach (self::PLAN_TYPES as $type) {
            Cache::forget("plan_{$type}_{$userId}");
        }

        // Net worth
        Cache::forget("net_worth_overview_{$userId}");
        Cache::forget("net_worth_breakdown_{$userId}");

        // Goals projections
        Cache::forget("goals_projection_{$userId}_individual");
        Cache::forget("goals_projection_{$userId}_household");

        // Risk profile
        Cache::forget("user_risk_level_{$userId}");
        Cache::forget("risk_profile_{$userId}");

        // Profile completeness
        Cache::forget("profile_completeness_{$userId}");

        // Investment analytics
        Cache::forget("fee_analysis_{$userId}");

        // AI context (cleared so Fyn sees fresh data on next message)
        Cache::forget("v1_coordinating_{$userId}_analysis");
        Cache::forget("ai_financial_context_{$userId}");
        Cache::forget("ai_existing_records_{$userId}");
        Cache::forget("ai_income_defs_{$userId}");

        // Mobile module summaries
        foreach (self::MODULES as $module) {
            Cache::forget("module_summary_{$module}_{$userId}");
        }
    }

    /**
     * Invalidate caches for a user and their spouse.
     *
     * Joint assets, protection profiles, and estate plans depend on
     * both partners' data, so both must be invalidated together.
     */
    public function invalidateForUserAndSpouse(int $userId, ?int $spouseId): void
    {
        $this->invalidateForUser($userId);

        if ($spouseId !== null) {
            $this->invalidateForUser($spouseId);
        }
    }
}
