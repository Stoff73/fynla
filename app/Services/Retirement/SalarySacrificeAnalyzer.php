<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Constants\TaxDefaults;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
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
        private readonly TaxConfigService $taxConfig,
        private readonly UKTaxCalculator $calculator,
        private readonly IncomeDefinitionsService $definitions,
    ) {}

    /**
     * Employee Class 1 National Insurance saved by sacrificing £sacrifice out of
     * £pay: the calculator's charge on the pay less its charge on what is left.
     * The one pricing of that fact for the analyser, SalarySacrificeNiStrategy
     * and the threshold line (Rule 20).
     */
    public function employeeNiSaving(float $pay, float $sacrifice): float
    {
        return $this->classOne($pay) - $this->classOne($pay - $sacrifice);
    }

    /** Employer Class 1 National Insurance saved on £sacrifice: a flat rate above one threshold. */
    public function employerNiSaving(float $sacrifice): float
    {
        return $sacrifice * (float) $this->taxConfig->get('national_insurance.class_1.employer.rate', 0.138);
    }

    /**
     * Pay before any salary sacrifice. The recorded employment income is the
     * pre-sacrifice figure unless the user said they recorded it net of
     * sacrifice (employment_income_basis = post_sacrifice), in which case the
     * sacrificed pay goes back on. IncomeDefinitionsService owns that reading.
     */
    public function payBeforeSacrifice(User $user): float
    {
        $definitions = $this->definitions->calculate((int) $user->id);
        $income = (float) ($user->annual_employment_income ?? 0);

        return ($definitions['employment_income_basis'] ?? null) === 'post_sacrifice'
            ? $income + (float) ($definitions['deductions']['salary_sacrificed'] ?? 0)
            : $income;
    }

    /** Pay after the sacrifice already in place: what National Insurance is charged on today. */
    public function payAfterSacrifice(User $user): float
    {
        $definitions = $this->definitions->calculate((int) $user->id);

        return max(0.0, $this->payBeforeSacrifice($user) - (float) ($definitions['deductions']['salary_sacrificed'] ?? 0));
    }

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

        $salary = $this->payBeforeSacrifice($user);

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
        $salary = $this->payBeforeSacrifice($user);

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
        $niSavings = $this->calculateNISavings($contribution, $salary);
        $warnings = $this->generateWarnings($salary, $contribution, $postSacrificeSalary);

        // Add NIC exemption cap warning if sacrifice exceeds £2,000
        if ($niSavings['exceeds_nic_cap']) {
            $currentSaving = $niSavings['employee'];
            $postCapSaving = $niSavings['post_cap_employee'];
            $reduction = $currentSaving - $postCapSaving;
            $warnings[] = [
                'type' => 'info',
                'message' => sprintf(
                    'From April %d, only the first £%s of employee salary sacrifice will be exempt from National Insurance. '
                    .'Your sacrifice of £%s exceeds this cap. Current National Insurance saving: £%s per year. '
                    .'Post-%d National Insurance saving: £%s per year (a reduction of £%s). '
                    .'Employer contributions remain fully exempt. Income Tax relief is unaffected.',
                    $niSavings['nic_cap_effective_year'],
                    number_format($niSavings['nic_exemption_cap'], 0),
                    number_format($contribution, 0),
                    number_format($currentSaving, 0),
                    $niSavings['nic_cap_effective_year'],
                    number_format($postCapSaving, 0),
                    number_format($reduction, 0)
                ),
            ];
        }

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
            'post_cap_employee_ni_saving' => round($niSavings['post_cap_employee'], 2),
            'post_cap_total_ni_saving' => round($niSavings['post_cap_total'], 2),
            'exceeds_nic_cap' => $niSavings['exceeds_nic_cap'],
            'nic_cap_effective_year' => $niSavings['nic_cap_effective_year'],
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
            $niSavings = $this->calculateNISavings($contribution, $salary);

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

        // Calculate aggregate post-cap NI savings (cap applies to total sacrifice, not per pension)
        $aggregateNI = $this->calculateNISavings($totalContribution, $salary);

        // Add NIC exemption cap warning if total sacrifice exceeds £2,000
        if ($aggregateNI['exceeds_nic_cap']) {
            $reduction = $totalEmployeeNISaving - $aggregateNI['post_cap_employee'];
            $allWarnings[] = [
                'type' => 'info',
                'message' => sprintf(
                    'From April %d, only the first £%s of employee salary sacrifice will be exempt from National Insurance. '
                    .'Your total sacrifice of £%s exceeds this cap. Current National Insurance saving: £%s per year. '
                    .'Post-%d National Insurance saving: £%s per year (a reduction of £%s). '
                    .'Employer contributions remain fully exempt. Income Tax relief is unaffected.',
                    $aggregateNI['nic_cap_effective_year'],
                    number_format($aggregateNI['nic_exemption_cap'], 0),
                    number_format($totalContribution, 0),
                    number_format($totalEmployeeNISaving, 0),
                    $aggregateNI['nic_cap_effective_year'],
                    number_format($aggregateNI['post_cap_employee'], 0),
                    number_format($reduction, 0)
                ),
            ];
        }

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
            'post_cap_employee_ni_saving' => round($aggregateNI['post_cap_employee'], 2),
            'post_cap_total_ni_saving' => round($aggregateNI['post_cap_total'], 2),
            'exceeds_nic_cap' => $aggregateNI['exceeds_nic_cap'],
            'nic_cap_effective_year' => $aggregateNI['nic_cap_effective_year'],
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
     * Current rules: Full NIC exemption on the entire sacrificed amount.
     * From the cap's effective date, in config: Only the first £2,000 of
     * employee salary sacrifice is exempt from NICs. Amounts above £2,000
     * are subject to NICs. Employer contributions remain fully NIC-exempt
     * regardless.
     *
     * @return array{employee: float, employer: float, total: float, post_cap_employee: float, post_cap_employer: float, post_cap_total: float, nic_exemption_cap: float, exceeds_nic_cap: bool, nic_cap_effective_year: int}
     */
    private function calculateNISavings(float $sacrificeAmount, float $preSacrificePay): array
    {
        $nicExemptionCap = (float) $this->taxConfig->get(
            'pension.salary_sacrifice.nic_exemption_cap',
            2000
        );
        $effectiveYear = (int) substr(
            (string) $this->taxConfig->get('pension.salary_sacrifice.nic_exemption_cap_effective_date', '2027-04-06'),
            0,
            4,
        );

        // Employee National Insurance comes from the calculator, never from a rate
        // (Rule 20 — `SalarySacrificeNiCapLine` prices the same thing the same way).
        // A flat main rate is wrong for anyone whose sacrifice sits above the upper
        // earnings limit, where the employee rate is 2%: a £145,000 earner sacrificing
        // £30,000 was told £2,240 here and £560 on the threshold strip, for one fact.
        // Employer National Insurance is a flat rate above one threshold, so it stays.
        $employeeSaving = $this->employeeNiSaving($preSacrificePay, $sacrificeAmount);
        $employerSaving = $this->employerNiSaving($sacrificeAmount);

        // Post-cap rules: only first £2,000 exempt from employee NICs
        $exemptAmount = min($sacrificeAmount, $nicExemptionCap);
        $postCapEmployeeSaving = $this->employeeNiSaving($preSacrificePay, $exemptAmount);
        // Employer NI savings unaffected — all employer contributions remain NIC-exempt
        $postCapEmployerSaving = $employerSaving;

        return [
            'employee' => $employeeSaving,
            'employer' => $employerSaving,
            'total' => $employeeSaving + $employerSaving,
            'post_cap_employee' => $postCapEmployeeSaving,
            'post_cap_employer' => $postCapEmployerSaving,
            'post_cap_total' => $postCapEmployeeSaving + $postCapEmployerSaving,
            'nic_exemption_cap' => $nicExemptionCap,
            'exceeds_nic_cap' => $sacrificeAmount > $nicExemptionCap,
            'nic_cap_effective_year' => $effectiveYear,
        ];
    }

    /** Employee Class 1 National Insurance on a year's pay, from the one calculator. */
    private function classOne(float $pay): float
    {
        return (float) ($this->calculator->calculateNetIncome(max(0.0, $pay))['breakdown']['class_1_ni'] ?? 0);
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
        $personalAllowance = (float) $this->taxConfig->get('income_tax.personal_allowance', TaxDefaults::PERSONAL_ALLOWANCE);

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
            TaxDefaults::NI_PRIMARY_THRESHOLD
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
