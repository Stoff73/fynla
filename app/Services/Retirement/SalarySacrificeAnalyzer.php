<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Salary Sacrifice Analyzer
 *
 * Analyses the potential benefits and risks of salary sacrifice pension arrangements.
 * Calculates National Insurance savings for both employee and employer, checks
 * post-sacrifice salary against minimum wage proxy floors, and generates warnings
 * for edge cases (personal allowance, National Insurance Lower Earnings Limit,
 * high sacrifice percentages).
 *
 * Self-employed users are not eligible for salary sacrifice arrangements.
 */
class SalarySacrificeAnalyzer
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Analyse salary sacrifice opportunities for the user.
     *
     * Examines each workplace Defined Contribution pension to determine whether
     * salary sacrifice is beneficial. Self-employed users receive a not-available
     * response immediately.
     *
     * @return array{
     *     is_available: bool,
     *     current_contribution: float,
     *     recommended_sacrifice: float,
     *     employee_ni_saving: float,
     *     employer_ni_saving: float,
     *     total_ni_saving: float,
     *     net_cost_to_employee: float,
     *     warnings: array<int, array{type: string, message: string}>,
     *     post_sacrifice_salary: float,
     *     pensions: array
     * }
     */
    public function analyze(User $user): array
    {
        // Self-employed users cannot use salary sacrifice
        if ($this->isSelfEmployed($user)) {
            return [
                'is_available' => false,
                'current_contribution' => 0.0,
                'recommended_sacrifice' => 0.0,
                'employee_ni_saving' => 0.0,
                'employer_ni_saving' => 0.0,
                'total_ni_saving' => 0.0,
                'net_cost_to_employee' => 0.0,
                'warnings' => [[
                    'type' => 'info',
                    'message' => 'Salary sacrifice is not available for self-employed individuals. It is only available through workplace pension schemes.',
                ]],
                'post_sacrifice_salary' => 0.0,
                'pensions' => [],
            ];
        }

        $salary = (float) ($user->annual_employment_income ?? 0);

        if ($salary <= 0) {
            return [
                'is_available' => false,
                'current_contribution' => 0.0,
                'recommended_sacrifice' => 0.0,
                'employee_ni_saving' => 0.0,
                'employer_ni_saving' => 0.0,
                'total_ni_saving' => 0.0,
                'net_cost_to_employee' => 0.0,
                'warnings' => [[
                    'type' => 'info',
                    'message' => 'No employment income recorded. Salary sacrifice requires an employment income.',
                ]],
                'post_sacrifice_salary' => 0.0,
                'pensions' => [],
            ];
        }

        // Load workplace DC pensions
        $user->loadMissing('dcPensions');
        $workplacePensions = $user->dcPensions->filter(
            fn (DCPension $pension) => $pension->scheme_type === 'workplace'
        );

        if ($workplacePensions->isEmpty()) {
            return [
                'is_available' => false,
                'current_contribution' => 0.0,
                'recommended_sacrifice' => 0.0,
                'employee_ni_saving' => 0.0,
                'employer_ni_saving' => 0.0,
                'total_ni_saving' => 0.0,
                'net_cost_to_employee' => 0.0,
                'warnings' => [[
                    'type' => 'info',
                    'message' => 'No workplace pension schemes found. Salary sacrifice is only available through workplace pension arrangements.',
                ]],
                'post_sacrifice_salary' => 0.0,
                'pensions' => [],
            ];
        }

        return $this->analyzeWorkplacePensions($user, $salary, $workplacePensions);
    }

    /**
     * Analyse salary sacrifice for a specific Defined Contribution pension.
     *
     * @return array{
     *     is_available: bool,
     *     pension_id: int,
     *     scheme_name: string,
     *     current_employee_contribution: float,
     *     employee_ni_saving: float,
     *     employer_ni_saving: float,
     *     total_ni_saving: float,
     *     net_cost_to_employee: float,
     *     post_sacrifice_salary: float,
     *     warnings: array<int, array{type: string, message: string}>
     * }
     */
    public function analyzeForPension(User $user, DCPension $pension): array
    {
        $salary = (float) ($user->annual_employment_income ?? 0);

        if ($salary <= 0 || $pension->scheme_type !== 'workplace') {
            return [
                'is_available' => false,
                'pension_id' => $pension->id,
                'scheme_name' => $pension->scheme_name ?? 'Unknown pension',
                'current_employee_contribution' => 0.0,
                'employee_ni_saving' => 0.0,
                'employer_ni_saving' => 0.0,
                'total_ni_saving' => 0.0,
                'net_cost_to_employee' => 0.0,
                'post_sacrifice_salary' => $salary,
                'warnings' => [],
            ];
        }

        $contribution = $this->calculateAnnualEmployeeContribution($pension, $salary);
        $postSacrificeSalary = $salary - $contribution;
        $niSavings = $this->calculateNISavings($contribution);
        $warnings = $this->generateWarnings($salary, $contribution, $postSacrificeSalary);

        return [
            'is_available' => true,
            'pension_id' => $pension->id,
            'scheme_name' => $pension->scheme_name ?? 'Workplace pension',
            'current_employee_contribution' => round($contribution, 2),
            'employee_ni_saving' => round($niSavings['employee'], 2),
            'employer_ni_saving' => round($niSavings['employer'], 2),
            'total_ni_saving' => round($niSavings['total'], 2),
            'net_cost_to_employee' => round(max(0, $contribution - $niSavings['employee']), 2),
            'post_sacrifice_salary' => round($postSacrificeSalary, 2),
            'warnings' => $warnings,
        ];
    }

    /**
     * Analyse all workplace pensions and aggregate results.
     */
    private function analyzeWorkplacePensions(User $user, float $salary, Collection $workplacePensions): array
    {
        $totalContribution = 0.0;
        $totalEmployeeNISaving = 0.0;
        $totalEmployerNISaving = 0.0;
        $pensionResults = [];
        $allWarnings = [];

        foreach ($workplacePensions as $pension) {
            $contribution = $this->calculateAnnualEmployeeContribution($pension, $salary);
            $niSavings = $this->calculateNISavings($contribution);

            $totalContribution += $contribution;
            $totalEmployeeNISaving += $niSavings['employee'];
            $totalEmployerNISaving += $niSavings['employer'];

            $pensionResults[] = [
                'pension_id' => $pension->id,
                'scheme_name' => $pension->scheme_name ?? 'Workplace pension',
                'employee_contribution' => round($contribution, 2),
                'employee_ni_saving' => round($niSavings['employee'], 2),
                'employer_ni_saving' => round($niSavings['employer'], 2),
            ];
        }

        $postSacrificeSalary = $salary - $totalContribution;
        $allWarnings = $this->generateWarnings($salary, $totalContribution, $postSacrificeSalary);
        $totalNISaving = $totalEmployeeNISaving + $totalEmployerNISaving;

        return [
            'is_available' => true,
            'current_contribution' => round($totalContribution, 2),
            'recommended_sacrifice' => round($totalContribution, 2),
            'employee_ni_saving' => round($totalEmployeeNISaving, 2),
            'employer_ni_saving' => round($totalEmployerNISaving, 2),
            'total_ni_saving' => round($totalNISaving, 2),
            'net_cost_to_employee' => round(max(0, $totalContribution - $totalEmployeeNISaving), 2),
            'warnings' => $allWarnings,
            'post_sacrifice_salary' => round($postSacrificeSalary, 2),
            'pensions' => $pensionResults,
        ];
    }

    /**
     * Calculate the annual employee contribution for a pension.
     */
    private function calculateAnnualEmployeeContribution(DCPension $pension, float $salary): float
    {
        $monthly = (float) ($pension->monthly_contribution_amount ?? 0);

        if ($monthly > 0) {
            return $monthly * 12;
        }

        $pensionSalary = (float) ($pension->annual_salary ?? 0);
        $effectiveSalary = $pensionSalary > 0 ? $pensionSalary : $salary;
        $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);

        if ($effectiveSalary > 0 && $employeePercent > 0) {
            return $effectiveSalary * ($employeePercent / 100);
        }

        return 0.0;
    }

    /**
     * Calculate National Insurance savings from salary sacrifice.
     *
     * Employee saves: NI on sacrificed amount at main rate (8%)
     * Employer saves: NI on sacrificed amount at employer rate (13.8%)
     *
     * @return array{employee: float, employer: float, total: float}
     */
    private function calculateNISavings(float $sacrificeAmount): array
    {
        $employeeMainRate = (float) $this->taxConfig->get(
            'national_insurance.class_1.employee.main_rate',
            0.08
        );
        $employerRate = (float) $this->taxConfig->get(
            'national_insurance.class_1.employer.rate',
            0.138
        );

        $employeeSaving = $sacrificeAmount * $employeeMainRate;
        $employerSaving = $sacrificeAmount * $employerRate;

        return [
            'employee' => $employeeSaving,
            'employer' => $employerSaving,
            'total' => $employeeSaving + $employerSaving,
        ];
    }

    /**
     * Generate warnings based on post-sacrifice salary thresholds.
     *
     * @return array<int, array{type: string, message: string}>
     */
    private function generateWarnings(float $salary, float $contribution, float $postSacrificeSalary): array
    {
        $warnings = [];

        if ($salary <= 0 || $contribution <= 0) {
            return $warnings;
        }

        $sacrificePercent = $contribution / $salary;

        // Warning: sacrifice exceeds 20% of salary
        if ($sacrificePercent > 0.20) {
            $warnings[] = [
                'type' => 'info',
                'message' => sprintf(
                    'You are sacrificing %.1f%% of your salary. Sacrificing more than 20%% may affect mortgage applications, statutory pay entitlements, and death-in-service benefits. Review these impacts before proceeding.',
                    $sacrificePercent * 100
                ),
            ];
        }

        // Warning: below conservative proxy floor (auto-enrolment earnings trigger)
        $proxyFloor = (float) $this->taxConfig->get(
            'pension.salary_sacrifice.conservative_proxy_floor',
            10000
        );

        if ($postSacrificeSalary < $proxyFloor) {
            $warnings[] = [
                'type' => 'warn',
                'message' => sprintf(
                    'Salary sacrifice would reduce your pay to %s, which is below the auto-enrolment earnings trigger of %s. '
                    .'This may breach National Minimum Wage or National Living Wage requirements depending on your contracted hours. '
                    .'Seek advice before proceeding.',
                    '£'.number_format($postSacrificeSalary, 2),
                    '£'.number_format($proxyFloor, 2)
                ),
            ];
        }

        // Warning: below personal allowance (lose income tax personal allowance benefit)
        $personalAllowance = (float) $this->taxConfig->get('income_tax.personal_allowance', 12570);

        if ($postSacrificeSalary < $personalAllowance) {
            $warnings[] = [
                'type' => 'warn',
                'message' => sprintf(
                    'Salary sacrifice would reduce your pay below the personal allowance (%s). '
                    .'You would lose the benefit of tax-free income up to this threshold, '
                    .'which may reduce the overall tax advantage of salary sacrifice.',
                    '£'.number_format($personalAllowance, 0)
                ),
            ];
        }

        // Warning: below National Insurance primary threshold (lose National Insurance credits / State Pension qualifying)
        $niPrimaryThreshold = (float) $this->taxConfig->get(
            'national_insurance.class_1.employee.primary_threshold',
            12570
        );

        if ($postSacrificeSalary < $niPrimaryThreshold) {
            $warnings[] = [
                'type' => 'warn',
                'message' => sprintf(
                    'Salary sacrifice would reduce your pay below the National Insurance primary threshold (%s). '
                    .'This could affect your National Insurance credits and State Pension qualifying years.',
                    '£'.number_format($niPrimaryThreshold, 0)
                ),
            ];
        }

        // Warning: below auto-enrolment earnings trigger (employer may stop auto-enrolment)
        $earningsTrigger = (float) $this->taxConfig->get(
            'pension.auto_enrolment.earnings_trigger',
            10000
        );

        if ($postSacrificeSalary < $earningsTrigger && $postSacrificeSalary >= $proxyFloor) {
            // Only show this if not already warned about proxy floor (which covers the same ground)
            $warnings[] = [
                'type' => 'warn',
                'message' => sprintf(
                    'Salary sacrifice would reduce your pay below the auto-enrolment earnings trigger (%s). '
                    .'Your employer may no longer be required to auto-enrol you into a workplace pension.',
                    '£'.number_format($earningsTrigger, 0)
                ),
            ];
        }

        return $warnings;
    }

    /**
     * Determine whether the user is self-employed.
     */
    private function isSelfEmployed(User $user): bool
    {
        return $user->employment_status === 'self_employed';
    }
}
