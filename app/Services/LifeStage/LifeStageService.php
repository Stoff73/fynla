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

        $user->update([
            'life_stage' => $stage,
        ]);
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
        $dcTotal = $user->dcPensions()->sum('current_fund_value');
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
     * Get data completeness for each onboarding step (binary, for backward compat).
     *
     * Returns an array of step IDs that have any data. Delegates to getStepCompleteness()
     * and returns step IDs where status is 'partial' or 'complete'.
     */
    public function getDataCompleteness(User $user): array
    {
        $stepCompleteness = $this->getStepCompleteness($user);

        $completed = [];
        foreach ($stepCompleteness as $stepId => $info) {
            if ($info['status'] !== 'skipped') {
                $completed[] = $stepId;
            }
        }

        return $completed;
    }

    /**
     * Get field-level completeness for each onboarding step.
     *
     * Journey-aware: only tracks fields visible in the user's current journey.
     * Returns per-step status (skipped / partial / complete) with filled/missing field lists.
     *
     * Used by: onboarding progress bar, dashboard nudges, decision engines, AI.
     */
    public function getStepCompleteness(User $user): array
    {
        $stage = $user->life_stage;
        $stepFields = $this->getStepFieldConfig($stage);

        $result = [];
        foreach ($stepFields as $stepId => $fields) {
            $filled = [];
            $missing = [];

            foreach ($fields as $field) {
                if ($this->isFieldFilled($user, $field)) {
                    $filled[] = $field;
                } else {
                    $missing[] = $field;
                }
            }

            $total = count($fields);
            $filledCount = count($filled);

            $result[$stepId] = [
                'status' => $this->determineStepStatus($filledCount, $total),
                'filled' => $filled,
                'missing' => $missing,
                'filled_count' => $filledCount,
                'total_count' => $total,
                'percentage' => $total > 0 ? (int) round(($filledCount / $total) * 100) : 100,
            ];
        }

        return $result;
    }

    /**
     * Get field-level completeness for ALL fields across ALL steps.
     *
     * NOT journey-filtered. Used by agents and AI to know every missing field
     * and guide users to fill them, regardless of which journey they're on.
     */
    public function getFullFieldCompleteness(User $user): array
    {
        $allFields = $this->getAllFieldConfig();

        $result = [];
        foreach ($allFields as $stepId => $fields) {
            $filled = [];
            $missing = [];

            foreach ($fields as $field) {
                if ($this->isFieldFilled($user, $field)) {
                    $filled[] = $field;
                } else {
                    $missing[] = $field;
                }
            }

            $total = count($fields);
            $filledCount = count($filled);

            $result[$stepId] = [
                'status' => $this->determineStepStatus($filledCount, $total),
                'filled' => $filled,
                'missing' => $missing,
                'filled_count' => $filledCount,
                'total_count' => $total,
                'percentage' => $total > 0 ? (int) round(($filledCount / $total) * 100) : 100,
                'form_link' => $this->getStepFormLink($stepId),
            ];
        }

        return $result;
    }

    /**
     * Determine step status from filled/total counts.
     */
    private function determineStepStatus(int $filled, int $total): string
    {
        if ($total === 0) {
            return 'complete';
        }
        if ($filled === 0) {
            return 'skipped';
        }
        if ($filled >= $total) {
            return 'complete';
        }

        return 'partial';
    }

    /**
     * Check whether a single tracked field has data for the user.
     */
    private function isFieldFilled(User $user, string $field): bool
    {
        return match ($field) {
            // User model columns
            'date_of_birth' => $user->date_of_birth !== null,
            'gender' => ! empty($user->gender),
            'marital_status' => ! empty($user->marital_status),
            'employment_status' => ! empty($user->employment_status),
            'address_line_1' => ! empty($user->address_line_1),
            'city' => ! empty($user->city),
            'postcode' => ! empty($user->postcode),
            'phone' => ! empty($user->phone),
            'health_status' => ! empty($user->health_status),
            'smoking_status' => ! empty($user->smoking_status),
            'occupation' => ! empty($user->occupation),
            'employer' => ! empty($user->employer),
            'target_retirement_age' => $user->target_retirement_age !== null,

            // Income — any source > 0
            'has_income' => $this->calculateTotalIncome($user) > 0,

            // Expenditure
            'has_expenditure' => $user->monthly_expenditure > 0 || $this->hasExpenditureProfile($user),

            // Relationships (existence checks)
            'has_family_members' => $user->familyMembers()->exists(),
            'has_savings' => $user->savingsAccounts()->exists(),
            'has_investments' => $user->investmentAccounts()->exists(),
            'has_dc_pensions' => $user->dcPensions()->exists(),
            'has_db_pensions' => $user->dbPensions()->exists(),
            'has_state_pension' => $user->statePension()->exists(),
            'has_pensions' => $user->dcPensions()->exists() || $user->dbPensions()->exists() || $user->statePension()->exists(),
            'has_property' => $this->hasProperty($user),
            'has_any_assets' => $user->savingsAccounts()->exists()
                || $user->investmentAccounts()->exists()
                || $user->dcPensions()->exists()
                || $user->dbPensions()->exists()
                || $this->hasProperty($user),
            'has_goals' => $user->goals()->exists(),
            'has_liabilities' => $user->liabilities()->exists(),
            'has_will' => \App\Models\Estate\Will::where('user_id', $user->id)->exists(),
            'has_protection' => $user->lifeInsurancePolicies()->exists()
                || $user->criticalIllnessPolicies()->exists()
                || $user->incomeProtectionPolicies()->exists(),

            default => false,
        };
    }

    /**
     * Get tracked fields per step, filtered by the user's journey (life stage).
     *
     * Only includes fields that are VISIBLE during onboarding for that stage.
     * Hidden fields (per onboardingHide in lifeStageConfig) are excluded.
     */
    private function getStepFieldConfig(?string $stage): array
    {
        // Base personal-info fields for all journeys
        $personalInfoBase = ['date_of_birth', 'gender'];

        // Stage-specific additions to personal-info
        $personalInfoExtra = match ($stage) {
            'university' => [],
            'early_career' => ['marital_status'],
            'mid_career' => ['marital_status', 'address_line_1', 'city', 'postcode', 'phone', 'health_status', 'smoking_status'],
            'peak' => ['marital_status', 'address_line_1', 'city', 'postcode', 'phone', 'health_status', 'smoking_status'],
            'retirement' => ['marital_status', 'address_line_1', 'city', 'postcode', 'phone', 'health_status', 'smoking_status'],
            default => [],
        };

        $steps = [];

        // Build step field lists per journey
        match ($stage) {
            'university' => $this->buildUniversitySteps($steps, $personalInfoBase, $personalInfoExtra),
            'early_career' => $this->buildEarlyCareerSteps($steps, $personalInfoBase, $personalInfoExtra),
            'mid_career' => $this->buildMidCareerSteps($steps, $personalInfoBase, $personalInfoExtra),
            'peak' => $this->buildPeakSteps($steps, $personalInfoBase, $personalInfoExtra),
            'retirement' => $this->buildRetirementSteps($steps, $personalInfoBase, $personalInfoExtra),
            default => null,
        };

        return $steps;
    }

    private function buildUniversitySteps(array &$steps, array $base, array $extra): void
    {
        $steps['personal-info'] = array_merge($base, $extra);
        $steps['student-loan'] = ['has_liabilities'];
        $steps['income'] = ['employment_status', 'has_income'];
        $steps['expenditure'] = ['has_expenditure'];
        $steps['assets'] = ['has_savings'];
        $steps['goals'] = ['has_goals'];
    }

    private function buildEarlyCareerSteps(array &$steps, array $base, array $extra): void
    {
        $steps['personal-info'] = array_merge($base, $extra);
        $steps['income-career'] = ['employment_status', 'has_income'];
        $steps['expenditure'] = ['has_expenditure'];
        $steps['assets'] = ['has_any_assets'];
        $steps['goals'] = ['has_goals'];
    }

    private function buildMidCareerSteps(array &$steps, array $base, array $extra): void
    {
        $steps['personal-info'] = array_merge($base, $extra);
        $steps['family'] = ['has_family_members'];
        $steps['income'] = ['employment_status', 'has_income'];
        $steps['expenditure'] = ['has_expenditure'];
        $steps['assets'] = ['has_any_assets'];
        $steps['liabilities'] = ['has_liabilities'];
        $steps['protection-insurance'] = ['has_protection'];
        $steps['will-estate'] = ['has_will'];
        $steps['goals'] = ['has_goals'];
    }

    private function buildPeakSteps(array &$steps, array $base, array $extra): void
    {
        $steps['personal-info'] = array_merge($base, $extra);
        $steps['family'] = ['has_family_members'];
        $steps['income-tax'] = ['employment_status', 'has_income'];
        $steps['expenditure'] = ['has_expenditure'];
        $steps['assets'] = ['has_any_assets'];
        $steps['liabilities'] = ['has_liabilities'];
        $steps['estate-iht'] = ['has_will'];
        $steps['goals'] = ['has_goals'];
    }

    private function buildRetirementSteps(array &$steps, array $base, array $extra): void
    {
        $steps['personal-info'] = array_merge($base, $extra);
        $steps['family'] = ['has_family_members'];
        $steps['income-tax'] = ['employment_status', 'has_income'];
        $steps['expenditure'] = ['has_expenditure'];
        $steps['assets'] = ['has_any_assets'];
        $steps['estate-legacy'] = ['has_will'];
        $steps['goals'] = ['has_goals'];
    }

    /**
     * Get ALL tracked fields across ALL steps (not journey-filtered).
     *
     * Used by agents and AI to identify any missing data regardless of journey.
     */
    private function getAllFieldConfig(): array
    {
        return [
            'personal-info' => [
                'date_of_birth', 'gender', 'marital_status',
                'address_line_1', 'city', 'postcode', 'phone',
                'health_status', 'smoking_status',
            ],
            'family' => ['has_family_members'],
            'income' => ['employment_status', 'has_income'],
            'expenditure' => ['has_expenditure'],
            'assets' => ['has_savings', 'has_investments', 'has_pensions', 'has_property'],
            'protection' => ['has_protection'],
            'will-estate' => ['has_will'],
            'goals' => ['has_goals'],
        ];
    }

    /**
     * Get the form/page link for a given step (for agent guidance).
     */
    private function getStepFormLink(string $stepId): string
    {
        return match ($stepId) {
            'personal-info' => '/profile',
            'family' => '/profile',
            'income' => '/valuable-info?section=income',
            'expenditure' => '/valuable-info?section=expenditure',
            'assets' => '/net-worth/cash',
            'protection' => '/protection',
            'will-estate' => '/estate/will-builder',
            'goals' => '/goals',
            default => '/onboarding',
        };
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
