<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\Investment\RiskProfile;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;

class SavingsDataReadinessService
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Assess data readiness for the Savings module.
     *
     * Returns a structured report of blocking, warning, and info checks
     * that determine whether savings analysis can proceed.
     *
     * @return array{can_proceed: bool, blocking: array, warnings: array, info: array, total_checks: int, passed_checks: int}
     */
    public function assess(User $user): array
    {
        $checks = [];

        // Blocking checks — analysis cannot proceed without these
        $checks[] = $this->checkDateOfBirth($user);
        $checks[] = $this->checkIncome($user);
        $checks[] = $this->checkExpenditure($user);

        // Warning checks — analysis will be limited
        $checks[] = $this->checkEmploymentStatus($user);
        $checks[] = $this->checkSavingsAccounts($user);
        $checks[] = $this->checkMaritalStatus($user);

        // Info checks — nice to have for enhanced analysis
        $checks[] = $this->checkRiskProfile($user);
        $checks[] = $this->checkGoals($user);

        $collection = collect($checks);
        $blocking = $collection->where('level', 'blocking');
        $warnings = $collection->where('level', 'warning');
        $info = $collection->where('level', 'info');

        return [
            'can_proceed' => $blocking->where('passed', false)->isEmpty(),
            'blocking' => array_values($blocking->toArray()),
            'warnings' => array_values($warnings->toArray()),
            'info' => array_values($info->toArray()),
            'total_checks' => count($checks),
            'passed_checks' => $collection->where('passed', true)->count(),
        ];
    }

    /**
     * Check that the user has a date of birth set.
     */
    private function checkDateOfBirth(User $user): array
    {
        return [
            'key' => 'date_of_birth',
            'level' => 'blocking',
            'passed' => $user->date_of_birth !== null,
            'message' => $user->date_of_birth !== null
                ? 'Date of birth is set.'
                : 'Date of birth is required for age-based savings analysis.',
            'form_link' => '/profile',
        ];
    }

    /**
     * Check that the user has gross annual income greater than zero.
     */
    private function checkIncome(User $user): array
    {
        $grossIncome = $this->resolveGrossAnnualIncome($user);
        $passed = $grossIncome > 0;

        return [
            'key' => 'income',
            'level' => 'blocking',
            'passed' => $passed,
            'message' => $passed
                ? 'Income information is available.'
                : 'At least one source of income is required for savings analysis.',
            'form_link' => '/profile',
        ];
    }

    /**
     * Check that the user has monthly expenditure set and greater than zero.
     */
    private function checkExpenditure(User $user): array
    {
        $expenditure = $this->resolveMonthlyExpenditure($user);
        $passed = $expenditure['amount'] > 0;

        return [
            'key' => 'expenditure',
            'level' => 'blocking',
            'passed' => $passed,
            'message' => $passed
                ? 'Monthly expenditure is available.'
                : 'Monthly expenditure is required to calculate savings capacity.',
            'form_link' => '/profile',
        ];
    }

    /**
     * Check that the user has an employment status set.
     */
    private function checkEmploymentStatus(User $user): array
    {
        $passed = ! empty($user->employment_status);

        return [
            'key' => 'employment_status',
            'level' => 'warning',
            'passed' => $passed,
            'message' => $passed
                ? 'Employment status is set.'
                : 'Employment status helps tailor savings recommendations to your circumstances.',
            'form_link' => '/profile',
        ];
    }

    /**
     * Check that the user has at least one savings account.
     */
    private function checkSavingsAccounts(User $user): array
    {
        $count = $user->savingsAccounts()->count();
        $passed = $count > 0;

        return [
            'key' => 'savings_accounts',
            'level' => 'warning',
            'passed' => $passed,
            'message' => $passed
                ? "You have {$count} savings ".($count === 1 ? 'account' : 'accounts').' on record.'
                : 'Adding your savings accounts enables detailed analysis and recommendations.',
            'form_link' => '/savings',
        ];
    }

    /**
     * Check that the user has a marital status set (for spouse optimisation).
     */
    private function checkMaritalStatus(User $user): array
    {
        $passed = ! empty($user->marital_status);

        return [
            'key' => 'marital_status',
            'level' => 'warning',
            'passed' => $passed,
            'message' => $passed
                ? 'Marital status is set.'
                : 'Knowing your marital status enables spouse-based savings optimisation.',
            'form_link' => '/profile',
        ];
    }

    /**
     * Check that the user has completed a risk profile.
     */
    private function checkRiskProfile(User $user): array
    {
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        $passed = $riskProfile !== null;

        return [
            'key' => 'risk_profile',
            'level' => 'info',
            'passed' => $passed,
            'message' => $passed
                ? 'Risk profile is completed.'
                : 'A completed risk profile enhances savings product recommendations.',
            'form_link' => '/profile',
        ];
    }

    /**
     * Check that the user has at least one active goal.
     */
    private function checkGoals(User $user): array
    {
        $count = $user->goals()->where('status', 'active')->count();
        $passed = $count > 0;

        return [
            'key' => 'goals',
            'level' => 'info',
            'passed' => $passed,
            'message' => $passed
                ? "You have {$count} active ".($count === 1 ? 'goal' : 'goals').' linked to savings analysis.'
                : 'Setting financial goals helps prioritise and track your savings progress.',
            'form_link' => '/goals',
        ];
    }
}
