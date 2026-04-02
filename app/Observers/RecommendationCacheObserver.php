<?php

declare(strict_types=1);

namespace App\Observers;

use App\Agents\CoordinatingAgent;
use App\Agents\EstateAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Agents\TaxOptimisationAgent;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates agent analysis caches when financial data changes.
 *
 * Without this, agents return stale cached analysis and recommendations
 * don't update when users add/edit/delete financial records.
 *
 * Each model type maps to the agents whose analysis depends on it.
 * The CoordinatingAgent cache is always cleared since it aggregates all modules.
 */
class RecommendationCacheObserver
{
    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $userId = $model->user_id ?? null;
        if (! $userId) {
            return;
        }

        $agents = $this->getAffectedAgents($model);

        foreach ($agents as $agentClass) {
            app($agentClass)->invalidateUserCache($userId);
        }

        // Always invalidate coordinating agent — it aggregates all modules
        app(CoordinatingAgent::class)->invalidateUserCache($userId);

        // Also invalidate for joint owner if applicable
        $jointOwnerId = $model->joint_owner_id ?? null;
        if ($jointOwnerId) {
            foreach ($agents as $agentClass) {
                app($agentClass)->invalidateUserCache($jointOwnerId);
            }
            app(CoordinatingAgent::class)->invalidateUserCache($jointOwnerId);
        }
    }

    private function getAffectedAgents(Model $model): array
    {
        $class = get_class($model);

        return match (true) {
            // Savings models
            str_contains($class, 'SavingsAccount') => [SavingsAgent::class, TaxOptimisationAgent::class],

            // Investment models
            str_contains($class, 'InvestmentAccount'),
            str_contains($class, 'Holding') => [InvestmentAgent::class, TaxOptimisationAgent::class],

            // Retirement models
            str_contains($class, 'DCPension'),
            str_contains($class, 'DBPension'),
            str_contains($class, 'StatePension'),
            str_contains($class, 'RetirementProfile') => [RetirementAgent::class, TaxOptimisationAgent::class],

            // Protection models
            str_contains($class, 'LifeInsurancePolicy'),
            str_contains($class, 'CriticalIllnessPolicy'),
            str_contains($class, 'IncomeProtectionPolicy'),
            str_contains($class, 'DisabilityPolicy'),
            str_contains($class, 'SicknessIllnessPolicy'),
            str_contains($class, 'ProtectionProfile') => [ProtectionAgent::class],

            // Estate models
            str_contains($class, 'Gift'),
            str_contains($class, 'Trust'),
            str_contains($class, 'Liability'),
            str_contains($class, 'Chattel'),
            str_contains($class, 'BusinessInterest') => [EstateAgent::class, TaxOptimisationAgent::class],

            // Property models
            str_contains($class, 'Property'),
            str_contains($class, 'Mortgage') => [EstateAgent::class, ProtectionAgent::class, TaxOptimisationAgent::class],

            // User/family changes affect multiple agents
            str_contains($class, 'FamilyMember') => [ProtectionAgent::class, EstateAgent::class, TaxOptimisationAgent::class],

            // Goals/events
            str_contains($class, 'Goal'),
            str_contains($class, 'LifeEvent') => [SavingsAgent::class, InvestmentAgent::class],

            // Default: clear all main agents
            default => [SavingsAgent::class, InvestmentAgent::class, RetirementAgent::class, ProtectionAgent::class, EstateAgent::class],
        };
    }
}
