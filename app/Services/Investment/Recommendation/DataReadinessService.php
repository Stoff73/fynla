<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\LifeEvent;
use App\Models\User;

/**
 * Data Readiness Gate for the Investment Module
 *
 * Assesses whether a user has provided sufficient data for meaningful
 * investment analysis. Returns blocking issues (analysis cannot proceed),
 * warnings (analysis will be limited), and info items (would enhance analysis).
 */
class DataReadinessService
{
    /**
     * Assess data readiness for investment analysis.
     *
     * @return array{
     *     can_proceed: bool,
     *     blocking: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     warnings: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     info: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     total_checks: int,
     *     passed_checks: int,
     *     completion_percent: float
     * }
     */
    public function assess(User $user): array
    {
        $checks = $this->runAllChecks($user);

        $blocking = array_values(array_filter($checks, fn (array $check): bool => $check['level'] === 'blocking' && ! $check['passed']));
        $warnings = array_values(array_filter($checks, fn (array $check): bool => $check['level'] === 'warning' && ! $check['passed']));
        $info = array_values(array_filter($checks, fn (array $check): bool => $check['level'] === 'info' && ! $check['passed']));

        $totalChecks = count($checks);
        $passedChecks = count(array_filter($checks, fn (array $check): bool => $check['passed']));

        return [
            'can_proceed' => count($blocking) === 0,
            'blocking' => $blocking,
            'warnings' => $warnings,
            'info' => $info,
            'total_checks' => $totalChecks,
            'passed_checks' => $passedChecks,
            'completion_percent' => $totalChecks > 0
                ? round(($passedChecks / $totalChecks) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Run all readiness checks against the user.
     *
     * @return array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>
     */
    private function runAllChecks(User $user): array
    {
        return [
            // Blocking checks
            $this->checkDateOfBirth($user),
            $this->checkIncome($user),
            $this->checkRiskProfile($user),
            $this->checkExpenditure($user),

            // Warning checks
            $this->checkEmploymentStatus($user),
            $this->checkProtectionProfile($user),
            $this->checkPensionData($user),
            $this->checkInvestmentAccounts($user),

            // Info checks
            $this->checkSpouseData($user),
            $this->checkLifeEvents($user),
            $this->checkSavingsData($user),
            $this->checkGoals($user),
        ];
    }

    // ──────────────────────────────────────────────
    // Blocking checks
    // ──────────────────────────────────────────────

    /**
     * Check that the user has a date of birth recorded.
     */
    private function checkDateOfBirth(User $user): array
    {
        return [
            'key' => 'date_of_birth',
            'level' => 'blocking',
            'passed' => $user->date_of_birth !== null,
            'message' => $user->date_of_birth !== null
                ? 'Date of birth is recorded.'
                : 'Your date of birth is required to calculate time horizons and age-appropriate investment strategies.',
            'form_link' => '/profile/personal',
        ];
    }

    /**
     * Check that the user has a gross annual income greater than zero.
     */
    private function checkIncome(User $user): array
    {
        $grossIncome = $this->calculateGrossIncome($user);

        return [
            'key' => 'income',
            'level' => 'blocking',
            'passed' => $grossIncome > 0,
            'message' => $grossIncome > 0
                ? 'Income details are recorded.'
                : 'Your gross annual income is needed to assess contribution capacity and tax-efficient wrapper selection.',
            'form_link' => '/profile/employment',
        ];
    }

    /**
     * Check that the user has completed a risk profile assessment.
     */
    private function checkRiskProfile(User $user): array
    {
        $hasRiskProfile = RiskProfile::where('user_id', $user->id)->exists();

        return [
            'key' => 'risk_profile',
            'level' => 'blocking',
            'passed' => $hasRiskProfile,
            'message' => $hasRiskProfile
                ? 'Risk profile assessment is complete.'
                : 'A completed risk profile is essential to determine suitable asset allocation and investment recommendations.',
            'form_link' => '/investment/risk-profile',
        ];
    }

    /**
     * Check that the user has monthly expenditure set.
     */
    private function checkExpenditure(User $user): array
    {
        $hasExpenditure = $this->hasExpenditureData($user);

        return [
            'key' => 'expenditure',
            'level' => 'blocking',
            'passed' => $hasExpenditure,
            'message' => $hasExpenditure
                ? 'Monthly expenditure is recorded.'
                : 'Your monthly expenditure is needed to calculate disposable income and affordable contribution levels.',
            'form_link' => '/profile/expenditure',
        ];
    }

    // ──────────────────────────────────────────────
    // Warning checks
    // ──────────────────────────────────────────────

    /**
     * Check that the user's employment status is known.
     */
    private function checkEmploymentStatus(User $user): array
    {
        $hasStatus = ! empty($user->employment_status);

        return [
            'key' => 'employment_status',
            'level' => 'warning',
            'passed' => $hasStatus,
            'message' => $hasStatus
                ? 'Employment status is recorded.'
                : 'Knowing your employment status helps tailor pension contribution and tax relief recommendations.',
            'form_link' => '/profile/employment',
        ];
    }

    /**
     * Check that the user has protection data available.
     */
    private function checkProtectionProfile(User $user): array
    {
        $hasProfile = $user->protectionProfile !== null;

        return [
            'key' => 'protection_profile',
            'level' => 'warning',
            'passed' => $hasProfile,
            'message' => $hasProfile
                ? 'Protection profile is available.'
                : 'Adding your protection details allows investment recommendations to account for your existing safety net.',
            'form_link' => '/protection',
        ];
    }

    /**
     * Check that the user has at least one pension recorded.
     */
    private function checkPensionData(User $user): array
    {
        $hasPension = $user->dcPensions()->exists()
            || $user->dbPensions()->exists()
            || ($user->statePension !== null);

        return [
            'key' => 'pension_data',
            'level' => 'warning',
            'passed' => $hasPension,
            'message' => $hasPension
                ? 'Pension data is available.'
                : 'Recording your pensions enables coordination between retirement savings and other investment strategies.',
            'form_link' => '/retirement/pensions',
        ];
    }

    /**
     * Check that the user has at least one investment account.
     */
    private function checkInvestmentAccounts(User $user): array
    {
        $hasAccounts = InvestmentAccount::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->exists();

        return [
            'key' => 'investment_accounts',
            'level' => 'warning',
            'passed' => $hasAccounts,
            'message' => $hasAccounts
                ? 'Investment account data is available.'
                : 'Adding your investment accounts allows analysis of your current portfolio and identification of optimisation opportunities.',
            'form_link' => '/investment/accounts',
        ];
    }

    // ──────────────────────────────────────────────
    // Info checks
    // ──────────────────────────────────────────────

    /**
     * Check that partner details are available for joint optimisation.
     */
    private function checkSpouseData(User $user): array
    {
        $hasSpouse = $user->spouse_id !== null;

        return [
            'key' => 'spouse_data',
            'level' => 'info',
            'passed' => $hasSpouse,
            'message' => $hasSpouse
                ? 'Partner details are available for joint optimisation.'
                : 'Adding partner details enables joint tax planning and coordinated investment strategies.',
            'form_link' => '/profile/personal',
        ];
    }

    /**
     * Check that the user has life events recorded.
     */
    private function checkLifeEvents(User $user): array
    {
        $hasEvents = LifeEvent::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('joint_owner_id', $user->id);
        })->exists();

        return [
            'key' => 'life_events',
            'level' => 'info',
            'passed' => $hasEvents,
            'message' => $hasEvents
                ? 'Life events are recorded.'
                : 'Recording expected life events helps align investment timelines with future financial needs.',
            'form_link' => '/goals/life-events',
        ];
    }

    /**
     * Check that savings accounts are available for Individual Savings Account coordination.
     */
    private function checkSavingsData(User $user): array
    {
        $hasSavings = $user->savingsAccounts()->exists();

        return [
            'key' => 'savings_data',
            'level' => 'info',
            'passed' => $hasSavings,
            'message' => $hasSavings
                ? 'Savings account data is available.'
                : 'Adding savings accounts enables coordination of Individual Savings Account allowances across cash and investment wrappers.',
            'form_link' => '/savings',
        ];
    }

    /**
     * Check that investment-linked goals are set.
     */
    private function checkGoals(User $user): array
    {
        $hasGoals = $user->goals()->exists();

        return [
            'key' => 'goals',
            'level' => 'info',
            'passed' => $hasGoals,
            'message' => $hasGoals
                ? 'Investment-linked goals are recorded.'
                : 'Setting financial goals allows investment recommendations to be tailored to your specific objectives and timelines.',
            'form_link' => '/goals',
        ];
    }

    // ──────────────────────────────────────────────
    // Helper methods
    // ──────────────────────────────────────────────

    /**
     * Calculate the user's total gross annual income from all income fields.
     */
    private function calculateGrossIncome(User $user): float
    {
        return (float) $user->annual_employment_income
            + (float) $user->annual_self_employment_income
            + (float) $user->annual_rental_income
            + (float) $user->annual_dividend_income
            + (float) $user->annual_interest_income
            + (float) $user->annual_other_income
            + (float) $user->annual_trust_income;
    }

    /**
     * Check whether the user has expenditure data from any available source.
     *
     * Checks the ExpenditureProfile relationship first, then falls back
     * to the legacy monthly/annual expenditure fields on the User model.
     */
    private function hasExpenditureData(User $user): bool
    {
        // Check expenditure profile (preferred source)
        $profile = $user->expenditureProfile;
        if ($profile !== null && ($profile->total_monthly_expenditure ?? 0) > 0) {
            return true;
        }

        // Fall back to legacy fields on user model
        if (($user->monthly_expenditure ?? 0) > 0 || ($user->annual_expenditure ?? 0) > 0) {
            return true;
        }

        return false;
    }
}
