<?php

declare(strict_types=1);

namespace App\Services\LifeStage;

use App\Models\User;
use Carbon\Carbon;

class LifeStageService
{
    public const VALID_STAGES = ['university', 'early_career', 'mid_career', 'peak', 'retirement'];

    /**
     * Set the life stage for a user.
     *
     * @throws \InvalidArgumentException
     */
    public function setStage(User $user, string $stage): void
    {
        if (! in_array($stage, self::VALID_STAGES, true)) {
            throw new \InvalidArgumentException("Invalid life stage: {$stage}");
        }

        $user->update(['life_stage' => $stage]);
    }

    /**
     * Get the user's life stage progress (current stage + completed steps).
     */
    public function getProgress(User $user): array
    {
        return [
            'life_stage' => $user->life_stage,
            'completed_steps' => $user->life_stage_completed_steps ?? [],
            'suggested_transition' => $this->suggestTransition($user),
        ];
    }

    /**
     * Mark an onboarding step as completed for the user's current life stage.
     */
    public function completeStep(User $user, string $stepId): void
    {
        $steps = $user->life_stage_completed_steps ?? [];

        if (! in_array($stepId, $steps, true)) {
            $steps[] = $stepId;
        }

        $user->update(['life_stage_completed_steps' => $steps]);
    }

    /**
     * Suggest a life stage transition based on the user's profile data.
     *
     * Implements spec section 13.7 — Stage Suggestion Algorithm.
     *
     * Rules:
     * - Starting Out (university) -> Building Foundations (early_career):
     *   Age > 22 AND (first full-time job OR first property)
     *
     * - Building Foundations (early_career) -> Protecting What Matters (mid_career):
     *   Age > 29 AND (first child OR marriage OR property count > 1)
     *
     * - Protecting What Matters (mid_career) -> Planning Your Future (peak):
     *   Age > 48 AND (children independent OR pension value > 200k)
     *
     * - Planning Your Future (peak) -> Enjoying Your Wealth (retirement):
     *   Age > 63 AND (retirement date set OR stopped working)
     *
     * - Enjoying Your Wealth (retirement): terminal stage, no transition
     */
    public function suggestTransition(User $user): ?string
    {
        $currentStage = $user->life_stage;

        if (! $currentStage || $currentStage === 'retirement') {
            return null;
        }

        $age = $this->calculateAge($user);

        if ($age === null) {
            return null;
        }

        if ($currentStage === 'university' && $age > 22) {
            if ($this->hasFullTimeJob($user) || $this->hasProperty($user)) {
                return 'early_career';
            }
        }

        if ($currentStage === 'early_career' && $age > 29) {
            if ($this->hasChildren($user) || $this->isMarried($user) || $this->hasMultipleProperties($user)) {
                return 'mid_career';
            }
        }

        if ($currentStage === 'mid_career' && $age > 48) {
            if ($this->hasIndependentChildren($user) || $this->hasPensionValueAbove($user, 200000)) {
                return 'peak';
            }
        }

        if ($currentStage === 'peak' && $age > 63) {
            if ($this->hasRetirementDateSet($user) || $this->hasStoppedWorking($user)) {
                return 'retirement';
            }
        }

        return null;
    }

    /**
     * Calculate user's age from date_of_birth.
     */
    private function calculateAge(User $user): ?int
    {
        if (! $user->date_of_birth) {
            return null;
        }

        return Carbon::parse($user->date_of_birth)->age;
    }

    /**
     * Check if the user has a full-time job (employed or self-employed).
     */
    private function hasFullTimeJob(User $user): bool
    {
        return in_array($user->employment_status, ['employed', 'self_employed', 'self-employed'], true);
    }

    /**
     * Check if the user owns at least one property.
     */
    private function hasProperty(User $user): bool
    {
        return $user->properties()->exists();
    }

    /**
     * Check if the user has children (family members with 'child' relationship).
     */
    private function hasChildren(User $user): bool
    {
        return $user->familyMembers()->where('relationship', 'child')->exists();
    }

    /**
     * Check if the user is married.
     */
    private function isMarried(User $user): bool
    {
        return $user->marital_status === 'married';
    }

    /**
     * Check if the user has more than one property.
     */
    private function hasMultipleProperties(User $user): bool
    {
        return $user->properties()->count() > 1;
    }

    /**
     * Check if children are independent (all children aged 18+).
     */
    private function hasIndependentChildren(User $user): bool
    {
        $children = $user->familyMembers()->where('relationship', 'child')->get();

        if ($children->isEmpty()) {
            return false;
        }

        return $children->every(function ($child) {
            if (! $child->date_of_birth) {
                return false;
            }

            return Carbon::parse($child->date_of_birth)->age >= 18;
        });
    }

    /**
     * Check if total pension value exceeds a threshold.
     */
    private function hasPensionValueAbove(User $user, float $threshold): bool
    {
        $dcTotal = $user->dcPensions()->sum('current_value');
        $dbTotal = $user->dbPensions()->sum('transfer_value');

        return ($dcTotal + $dbTotal) > $threshold;
    }

    /**
     * Check if the user has a retirement date set.
     */
    private function hasRetirementDateSet(User $user): bool
    {
        return $user->retirement_date !== null;
    }

    /**
     * Check if the user has stopped working (retired or not working).
     */
    private function hasStoppedWorking(User $user): bool
    {
        return in_array($user->employment_status, ['retired', 'not_working', 'not-working'], true);
    }

    /**
     * Get data completeness for each onboarding step.
     *
     * Uses actual DB queries (same as DataReadiness services and PrerequisiteGateService)
     * to determine which steps have data. Returns an array of step IDs that are complete.
     *
     * This is the single source of truth for progress calculation — the frontend
     * should NOT attempt to check Vuex store state for this.
     */
    public function getDataCompleteness(User $user): array
    {
        $hasPersonalInfo = $user->date_of_birth && $user->gender;
        $hasIncome = $this->calculateTotalIncome($user) > 0 || $user->employment_status;
        $hasExpenditure = $user->monthly_expenditure > 0 || $this->hasExpenditureProfile($user);
        $hasSavings = $user->savingsAccounts()->exists();
        $hasInvestments = $user->investmentAccounts()->exists();
        $hasPensions = $user->dcPensions()->exists() || $user->dbPensions()->exists();
        $hasProtection = $user->lifeInsurancePolicies()->exists()
            || $user->criticalIllnessPolicies()->exists()
            || $user->incomeProtectionPolicies()->exists();
        $hasProperty = $this->hasProperty($user);
        $hasGoals = $user->goals()->exists();
        $hasFamily = $this->hasChildren($user) || $this->isMarried($user);
        $hasWill = $user->has_will ?? false;
        $hasEstate = $hasProperty || $hasInvestments || $hasSavings;
        $hasLiabilities = $user->liabilities()->exists();
        $hasStatePension = $user->statePension()->exists();

        // Map every possible step ID to its data check
        $stepChecks = [
            'personal-info' => $hasPersonalInfo,
            'student-loan' => $hasLiabilities,
            'income' => $hasIncome,
            'income-career' => $hasIncome,
            'income-tax' => $hasIncome,
            'expenditure' => $hasExpenditure,
            'savings' => $hasSavings,
            'savings-emergency' => $hasSavings,
            'first-home-lisa' => $hasSavings,
            'investments' => $hasInvestments,
            'investments-isa' => $hasInvestments,
            'goals' => $hasGoals,
            'family' => $hasFamily,
            'property-mortgage' => $hasProperty,
            'property-portfolio' => $hasProperty,
            'protection-insurance' => $hasProtection,
            'pensions' => $hasPensions,
            'pension-auto-enrolment' => $hasPensions,
            'pension-review' => $hasPensions,
            'pension-drawdown' => $hasPensions,
            'state-pension' => $hasStatePension || $hasPensions,
            'will-estate' => $hasWill,
            'estate-iht' => $hasEstate,
            'estate-legacy' => $hasEstate,
        ];

        $completed = [];
        foreach ($stepChecks as $stepId => $hasData) {
            if ($hasData) {
                $completed[] = $stepId;
            }
        }

        return $completed;
    }

    /**
     * Calculate total income from all sources (same as PrerequisiteGateService).
     */
    private function calculateTotalIncome(User $user): float
    {
        return (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);
    }

    /**
     * Check if user has an expenditure profile.
     */
    private function hasExpenditureProfile(User $user): bool
    {
        return \App\Models\ExpenditureProfile::where('user_id', $user->id)->exists();
    }
}
