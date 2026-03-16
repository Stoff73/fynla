<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\ExpenditureProfile;
use App\Models\Investment\RiskProfile;
use App\Models\User;

/**
 * Retirement Data Readiness Service
 *
 * Performs granular data readiness checks before retirement analysis can proceed.
 * Returns blocking issues, warnings, and informational items so the UI can guide
 * the user to complete missing data rather than silently assuming defaults.
 */
class RetirementDataReadinessService
{
    /**
     * Assess the user's data readiness for retirement analysis.
     *
     * @return array{
     *     can_proceed: bool,
     *     blocking: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     warnings: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     info: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     checks: array<int, array{key: string, level: string, passed: bool, message: string, form_link: string}>,
     *     summary: string
     * }
     */
    public function assess(User $user): array
    {
        $user->loadMissing(['retirementProfile', 'dcPensions', 'dbPensions', 'statePension', 'spouse']);

        $checks = [
            $this->checkDateOfBirth($user),
            $this->checkMaritalStatus($user),
            $this->checkIncome($user),
            $this->checkPensionData($user),
            $this->checkTargetRetirementAge($user),
            $this->checkTargetRetirementIncome($user),
            $this->checkExpenditure($user),
            $this->checkEmploymentStatus($user),
            $this->checkRiskProfile($user),
            $this->checkStatePensionForecast($user),
            $this->checkSpousePensionData($user),
        ];

        $blocking = array_values(array_filter($checks, fn (array $check) => $check['level'] === 'blocking' && ! $check['passed']));
        $warnings = array_values(array_filter($checks, fn (array $check) => $check['level'] === 'warning' && ! $check['passed']));
        $info = array_values(array_filter($checks, fn (array $check) => $check['level'] === 'info' && ! $check['passed']));

        $canProceed = count($blocking) === 0;

        return [
            'can_proceed' => $canProceed,
            'blocking' => $blocking,
            'warnings' => $warnings,
            'info' => $info,
            'checks' => $checks,
            'summary' => $this->buildSummary($canProceed, $blocking, $warnings, $info),
        ];
    }

    /**
     * Check whether the user has a date of birth recorded.
     * Required for retirement age calculations and pension projections.
     */
    private function checkDateOfBirth(User $user): array
    {
        $passed = $user->date_of_birth !== null;

        return [
            'key' => 'date_of_birth',
            'level' => 'blocking',
            'passed' => $passed,
            'message' => $passed
                ? 'Date of birth is recorded.'
                : 'Date of birth is required for retirement age calculations.',
            'form_link' => '/profile/personal',
        ];
    }

    /**
     * Check whether the user has a marital status recorded.
     * Affects survivor benefit analysis and joint retirement planning.
     */
    private function checkMaritalStatus(User $user): array
    {
        $passed = ! empty($user->marital_status);

        return [
            'key' => 'marital_status',
            'level' => 'blocking',
            'passed' => $passed,
            'message' => $passed
                ? 'Marital status is recorded.'
                : 'Marital status is required to assess survivor benefits and joint retirement planning.',
            'form_link' => '/profile/personal',
        ];
    }

    /**
     * Check whether the user has a gross annual income greater than zero.
     * Essential for contribution calculations and pension annual allowance checks.
     */
    private function checkIncome(User $user): array
    {
        $grossIncome = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);

        $passed = $grossIncome > 0;

        // Already-retired users may have no employment income — downgrade from blocking to warning
        $profile = $user->retirementProfile;
        $isRetired = $profile
            && $profile->current_age > 0
            && $profile->target_retirement_age > 0
            && $profile->current_age >= $profile->target_retirement_age;

        return [
            'key' => 'income',
            'level' => ($passed || $isRetired) ? ($passed ? 'blocking' : 'warning') : 'blocking',
            'passed' => $passed,
            'message' => $passed
                ? 'Gross annual income is recorded.'
                : ($isRetired
                    ? 'No employment income recorded. This is expected for retired users — pension income will be used for analysis.'
                    : 'Gross annual income is required for contribution and annual allowance calculations.'),
            'form_link' => '/profile/employment',
        ];
    }

    /**
     * Check whether the user has at least one pension recorded (Defined Contribution or Defined Benefit).
     * Without pension data, projections will be severely limited.
     */
    private function checkPensionData(User $user): array
    {
        $hasDC = $user->dcPensions->isNotEmpty();
        $hasDB = $user->dbPensions->isNotEmpty();
        $passed = $hasDC || $hasDB;

        return [
            'key' => 'pension_data',
            'level' => 'warning',
            'passed' => $passed,
            'message' => $passed
                ? 'At least one pension scheme is recorded.'
                : 'No pension schemes recorded. Add your Defined Contribution or Defined Benefit pensions for accurate projections.',
            'form_link' => '/retirement/pensions',
        ];
    }

    /**
     * Check whether the user has set a target retirement age.
     * Without this, projections must assume the State Pension age.
     */
    private function checkTargetRetirementAge(User $user): array
    {
        $retirementProfile = $user->retirementProfile;
        $passed = $retirementProfile !== null
            && $retirementProfile->target_retirement_age !== null
            && $retirementProfile->target_retirement_age > 0;

        return [
            'key' => 'target_retirement_age',
            'level' => 'warning',
            'passed' => $passed,
            'message' => $passed
                ? 'Target retirement age is set.'
                : 'Target retirement age is not set. Projections will use the State Pension age as a default.',
            'form_link' => '/retirement/settings',
        ];
    }

    /**
     * Check whether the user has set a desired retirement income.
     * Without this, gap analysis cannot determine whether projected income is sufficient.
     */
    private function checkTargetRetirementIncome(User $user): array
    {
        $retirementProfile = $user->retirementProfile;
        $passed = $retirementProfile !== null
            && $retirementProfile->target_retirement_income !== null
            && (float) $retirementProfile->target_retirement_income > 0;

        return [
            'key' => 'target_retirement_income',
            'level' => 'warning',
            'passed' => $passed,
            'message' => $passed
                ? 'Target retirement income is set.'
                : 'Desired retirement income is not set. Income gap analysis will be limited.',
            'form_link' => '/retirement/settings',
        ];
    }

    /**
     * Check whether the user has monthly expenditure data available.
     * Used to estimate essential spending in retirement and validate income targets.
     */
    private function checkExpenditure(User $user): array
    {
        $hasExpenditure = false;

        // Priority 1: ExpenditureProfile
        $expenditureProfile = ExpenditureProfile::where('user_id', $user->id)->first();
        if ($expenditureProfile && $expenditureProfile->total_monthly_expenditure > 0) {
            $hasExpenditure = true;
        }

        // Priority 2: User monthly expenditure
        if (! $hasExpenditure && $user->monthly_expenditure > 0) {
            $hasExpenditure = true;
        }

        // Priority 3: User annual expenditure
        if (! $hasExpenditure && $user->annual_expenditure > 0) {
            $hasExpenditure = true;
        }

        return [
            'key' => 'expenditure',
            'level' => 'warning',
            'passed' => $hasExpenditure,
            'message' => $hasExpenditure
                ? 'Monthly expenditure data is available.'
                : 'No expenditure data found. This helps estimate essential spending in retirement.',
            'form_link' => '/profile/expenditure',
        ];
    }

    /**
     * Check whether the user has an employment status recorded.
     * Relevant for auto-enrolment eligibility and contribution source analysis.
     */
    private function checkEmploymentStatus(User $user): array
    {
        $passed = ! empty($user->employment_status);

        return [
            'key' => 'employment_status',
            'level' => 'info',
            'passed' => $passed,
            'message' => $passed
                ? 'Employment status is recorded.'
                : 'Employment status is not set. This helps assess auto-enrolment eligibility and contribution sources.',
            'form_link' => '/profile/employment',
        ];
    }

    /**
     * Check whether the user has completed a risk profile.
     * Used to determine appropriate growth rate assumptions for pension projections.
     */
    private function checkRiskProfile(User $user): array
    {
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        $passed = $riskProfile !== null && ! empty($riskProfile->risk_level);

        return [
            'key' => 'risk_profile',
            'level' => 'info',
            'passed' => $passed,
            'message' => $passed
                ? 'Risk profile is completed.'
                : 'Risk profile is not completed. Growth rate assumptions will use conservative defaults.',
            'form_link' => '/investment/risk-profile',
        ];
    }

    /**
     * Check whether the user has entered a State Pension forecast.
     * Enhances income projections with guaranteed retirement income.
     */
    private function checkStatePensionForecast(User $user): array
    {
        $statePension = $user->statePension;
        $passed = $statePension !== null
            && $statePension->state_pension_forecast_annual !== null
            && (float) $statePension->state_pension_forecast_annual > 0;

        return [
            'key' => 'state_pension_forecast',
            'level' => 'info',
            'passed' => $passed,
            'message' => $passed
                ? 'State Pension forecast is recorded.'
                : 'State Pension forecast is not entered. Adding this improves retirement income projections.',
            'form_link' => '/retirement/state-pension',
        ];
    }

    /**
     * Check whether the user's partner has pension data recorded.
     * Only relevant when the user is married or in a civil partnership with a linked spouse.
     */
    private function checkSpousePensionData(User $user): array
    {
        // Not applicable if no spouse linked
        if (! $user->spouse_id) {
            return [
                'key' => 'spouse_pension_data',
                'level' => 'info',
                'passed' => true,
                'message' => 'No linked partner — spouse pension check not applicable.',
                'form_link' => '/retirement/pensions',
            ];
        }

        $spouse = $user->spouse;

        if (! $spouse) {
            return [
                'key' => 'spouse_pension_data',
                'level' => 'info',
                'passed' => true,
                'message' => 'No linked partner — spouse pension check not applicable.',
                'form_link' => '/retirement/pensions',
            ];
        }

        $spouse->loadMissing(['dcPensions', 'dbPensions']);

        $hasSpousePensions = $spouse->dcPensions->isNotEmpty() || $spouse->dbPensions->isNotEmpty();

        return [
            'key' => 'spouse_pension_data',
            'level' => 'info',
            'passed' => $hasSpousePensions,
            'message' => $hasSpousePensions
                ? 'Partner\'s pension details are recorded.'
                : 'Partner\'s pension details are not recorded. Adding these enables joint retirement planning.',
            'form_link' => '/retirement/pensions',
        ];
    }

    /**
     * Build a human-readable summary of the readiness assessment.
     */
    private function buildSummary(bool $canProceed, array $blocking, array $warnings, array $info): string
    {
        if ($canProceed && count($warnings) === 0 && count($info) === 0) {
            return 'All retirement data checks passed. Full analysis is available.';
        }

        if (! $canProceed) {
            $count = count($blocking);

            return sprintf(
                '%d blocking %s must be resolved before retirement analysis can proceed.',
                $count,
                $count === 1 ? 'issue' : 'issues'
            );
        }

        $parts = [];
        if (count($warnings) > 0) {
            $parts[] = sprintf('%d %s', count($warnings), count($warnings) === 1 ? 'warning' : 'warnings');
        }
        if (count($info) > 0) {
            $parts[] = sprintf('%d %s', count($info), count($info) === 1 ? 'enhancement' : 'enhancements');
        }

        return sprintf(
            'Retirement analysis can proceed with %s that would improve accuracy.',
            implode(' and ', $parts)
        );
    }
}
