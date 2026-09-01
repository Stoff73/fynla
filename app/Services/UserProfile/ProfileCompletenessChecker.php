<?php

declare(strict_types=1);

namespace App\Services\UserProfile;

use App\Models\BusinessInterest;
use App\Models\CashAccount;
use App\Models\Chattel;
use App\Models\Estate\Asset;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Shared\DependantsReach;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;

class ProfileCompletenessChecker
{
    public function __construct(
        private readonly PropertyStore $propertyStore,
        // W-0275 — the one home for reaching a household's family (Rule 20).
        private readonly DependantsReach $dependantsReach,
    ) {}

    /**
     * Check profile completeness for a user
     */
    public function checkCompleteness(User $user): array
    {
        $isMarried = in_array($user->marital_status, ['married', 'civil_partnership']);

        $checks = $isMarried
            ? $this->checkMarriedUser($user)
            : $this->checkSingleUser($user);

        $totalChecks = count($checks);
        $passedChecks = count(array_filter($checks, fn ($check) => $check['filled']));
        $completenessScore = $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 0;

        $missingFields = array_filter($checks, fn ($check) => ! $check['filled']);
        $recommendations = $this->generateRecommendations($missingFields, $isMarried);

        return [
            'completeness_score' => $completenessScore,
            'is_complete' => $completenessScore >= 100,
            'missing_fields' => $missingFields,
            'all_checks' => $checks,
            'recommendations' => $recommendations,
            'is_married' => $isMarried,
        ];
    }

    /**
     * Check completeness for married user
     */
    private function checkMarriedUser(User $user): array
    {
        return [
            'spouse_linked' => [
                'required' => true,
                'filled' => ! is_null($user->liveSpouseId()),
                'message' => 'Link your spouse account for accurate joint financial planning',
                'priority' => 'high',
                'link' => '/profile#family',
            ],
            'dependants' => [
                'required' => true,
                'filled' => $this->hasDependants($user),
                'message' => 'Add any children or other dependants for protection planning',
                'priority' => 'medium',
                'link' => '/profile#family',
            ],
            'income' => [
                'required' => true,
                'filled' => $this->hasIncome($user),
                'message' => 'Add your income details for protection needs calculation',
                'priority' => 'high',
                'link' => '/profile#income-occupation',
            ],
            'expenditure' => [
                'required' => true,
                'filled' => $this->hasExpenditure($user),
                'message' => 'Complete expenditure profile for comprehensive planning',
                'priority' => 'medium',
                'link' => '/profile#personal',
            ],
            'assets' => [
                'required' => true,
                'filled' => $this->hasAssets($user),
                'message' => 'Add at least one asset for estate and retirement planning',
                'priority' => 'high',
                'link' => '/net-worth',
            ],
            'protection_plans' => [
                'required' => true,
                'filled' => $this->hasProtectionPlans($user),
                'message' => 'Add protection details (life, critical illness, income protection)',
                'priority' => 'high',
                'link' => '/protection',
            ],
        ];
    }

    /**
     * Check completeness for single user
     */
    private function checkSingleUser(User $user): array
    {
        return [
            'dependants' => [
                'required' => true,
                'filled' => $this->hasDependants($user),
                'message' => 'Add dependants (if any) for protection planning',
                'priority' => 'high',
                'link' => '/profile#family',
            ],
            'income' => [
                'required' => true,
                'filled' => $this->hasIncome($user),
                'message' => 'Add your income details for protection needs calculation',
                'priority' => 'high',
                'link' => '/profile#income-occupation',
            ],
            'expenditure' => [
                'required' => true,
                'filled' => $this->hasExpenditure($user),
                'message' => 'Complete expenditure profile for comprehensive planning',
                'priority' => 'medium',
                'link' => '/profile#personal',
            ],
            'assets' => [
                'required' => true,
                'filled' => $this->hasAssets($user),
                'message' => 'Add at least one asset for estate and retirement planning',
                'priority' => 'high',
                'link' => '/net-worth',
            ],
            'protection_plans' => [
                'required' => true,
                'filled' => $this->hasProtectionPlans($user),
                'message' => 'Add protection details (life, critical illness, income protection)',
                'priority' => 'high',
                'link' => '/protection',
            ],
        ];
    }

    /**
     * Check if user has dependants or spouse for protection planning
     *
     * For protection purposes, a linked spouse counts as someone to protect
     * even if they're not marked as a financial dependant (e.g., they work).
     * This is separate from the spouse_linked check - that verifies accounts
     * are linked, this verifies there's someone to protect.
     */
    private function hasDependants(User $user): bool
    {
        // For married users, having a linked spouse counts - they're someone to protect
        // even if they earn their own income (not marked is_dependent)
        $hasSpouse = ! is_null($user->liveSpouseId());

        // W-0275 acceptance 2. This was the second hand-rolled spouse traversal, and
        // it reached the spouse on raw `liveSpouseId()` rather than reciprocally — so a
        // one-sided link let one account read the other's rows while the other could
        // not read back (the W-0350 axis `DependantsReach` already answers).
        //
        // It also had its own idea of which relationships count: the user's own side
        // took everything except a spouse, the spouse's side took only children and
        // step-children. Two rules for one question.
        $hasChildren = $this->dependantsReach->dependantsOf($user)->isNotEmpty();

        return $hasSpouse || $hasChildren;
    }

    /**
     * Check if user has income
     */
    private function hasIncome(User $user): bool
    {
        return ($user->annual_employment_income ?? 0) > 0
            || ($user->annual_self_employment_income ?? 0) > 0
            || ($user->annual_rental_income ?? 0) > 0
            || ($user->annual_dividend_income ?? 0) > 0
            || ($user->annual_other_income ?? 0) > 0;
    }

    /**
     * Check if user has expenditure profile
     */
    private function hasExpenditure(User $user): bool
    {
        return ($user->monthly_expenditure ?? 0) > 0
            || ($user->annual_expenditure ?? 0) > 0;
    }

    /**
     * Check if user has any assets
     */
    private function hasAssets(User $user): bool
    {
        // Check various asset types
        $hasProperty = $this->propertyStore->forUser($user)->where('user_id', $user->id)->isNotEmpty();
        $hasSavings = app(SavingsStore::class)->forUser($user)->where('user_id', $user->id)->isNotEmpty();
        $hasInvestments = InvestmentAccount::where('user_id', $user->id)->exists();
        $hasPensions = app(PensionStore::class)->forUserByType($user, 'dc')->isNotEmpty();
        $hasBusiness = BusinessInterest::where('user_id', $user->id)->exists();
        $hasChattels = Chattel::where('user_id', $user->id)->exists();
        $hasCash = CashAccount::where('user_id', $user->id)->exists();
        $hasEstateAssets = Asset::where('user_id', $user->id)->exists();

        return $hasProperty || $hasSavings || $hasInvestments || $hasPensions
            || $hasBusiness || $hasChattels || $hasCash || $hasEstateAssets;
    }

    /**
     * Check if user has protection plans
     */
    private function hasProtectionPlans(User $user): bool
    {
        // Check if user has a protection profile
        $profile = $user->protectionProfile;
        if (! $profile) {
            return false;
        }

        // If user has marked that they have no policies, consider profile complete
        if ($profile->has_no_policies) {
            return true;
        }

        // Otherwise, check if user has at least one policy
        $hasLifePolicy = $user->lifeInsurancePolicies()->exists();
        $hasCIPolicy = $user->criticalIllnessPolicies()->exists();
        $hasIPPolicy = $user->incomeProtectionPolicies()->exists();

        return $hasLifePolicy || $hasCIPolicy || $hasIPPolicy;
    }

    /**
     * Generate actionable recommendations based on missing fields
     */
    private function generateRecommendations(array $missingFields, bool $isMarried): array
    {
        $recommendations = [];

        // Sort by priority
        $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
        uasort($missingFields, function ($a, $b) use ($priorityOrder) {
            return $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']];
        });

        foreach ($missingFields as $key => $field) {
            if ($field['required']) {
                $recommendations[] = $field['message'];
            }
        }

        return $recommendations;
    }
}
