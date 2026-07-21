<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Stores\PensionStore;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Annual Allowance Checker Service
 *
 * Checks pension annual allowance, tapering for high earners, carry forward, and MPAA.
 * Uses active tax year rates from TaxConfigService.
 */
class AnnualAllowanceChecker
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly IncomeDefinitionsService $incomeDefinitions
    ) {}

    /**
     * Get standard annual allowance from tax config
     */
    private function getStandardAnnualAllowance(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['annual_allowance'];
    }

    /**
     * Get minimum tapered allowance from tax config
     */
    private function getMinimumTaperedAllowance(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['tapered_annual_allowance']['minimum_allowance'];
    }

    /**
     * Get threshold income from tax config
     */
    private function getThresholdIncome(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['tapered_annual_allowance']['threshold_income'];
    }

    /**
     * Get adjusted income threshold from tax config
     */
    private function getAdjustedIncomeThreshold(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['tapered_annual_allowance']['adjusted_income_threshold'];
    }

    /**
     * Get Money Purchase Annual Allowance from tax config
     */
    private function getMPAA(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['mpaa'];
    }

    /**
     * Get the calendar-based tax year (April 6 - April 5).
     * Used to decide whether ongoing monthly contributions should be
     * attributed to the requested tax year.
     */
    private function getCalendarTaxYear(): string
    {
        $now = Carbon::now();
        $taxYearStart = Carbon::create($now->year, 4, 6);
        $startYear = $now->lt($taxYearStart) ? $now->year - 1 : $now->year;

        return $startYear.'/'.substr((string) ($startYear + 1), -2);
    }

    /**
     * Check annual allowance for a user in a given tax year.
     *
     * @param  string  $taxYear  Tax year (e.g., '2024/25')
     */
    public function checkAnnualAllowance(int $userId, string $taxYear): array
    {
        // DC pension contributions are stored as monthly recurring amounts
        // with no per-year history. The projected annual figure only applies
        // to the calendar year we're physically in — switching to a past or
        // future year should show zero used (tax year hasn't started yet, or
        // we have no record of what was contributed then).
        $isCalendarYear = $taxYear === $this->getCalendarTaxYear();

        if ($isCalendarYear) {
            $dcPensions = app(PensionStore::class)->forUserByType(User::findOrFail($userId), 'dc');
            $totalContributions = $this->calculateTotalAnnualContributions($dcPensions);
        } else {
            $totalContributions = 0.0;
        }

        // Get user's income definitions (threshold and adjusted income)
        $definitions = $this->incomeDefinitions->calculate($userId);
        $thresholdIncome = $definitions['threshold_income'];
        $adjustedIncome = $definitions['adjusted_income'];

        // Check if tapering applies
        $standardAllowance = $this->getStandardAnnualAllowance();
        $availableAllowance = $standardAllowance;
        $isTapered = false;
        $taperingDetails = null;

        if ($thresholdIncome > $this->getThresholdIncome() && $adjustedIncome > $this->getAdjustedIncomeThreshold()) {
            $isTapered = true;
            $availableAllowance = $this->calculateTapering($thresholdIncome, $adjustedIncome);
            $taperingDetails = [
                'threshold_income' => $thresholdIncome,
                'adjusted_income' => $adjustedIncome,
                'reduction' => $standardAllowance - $availableAllowance,
            ];
        }

        // Carry-forward from the previous 3 years. For a currently-tapered user
        // we cap each prior year's assumed unused allowance at the current
        // tapered allowance instead of the full standard AA — per-year income
        // history isn't captured, so this is a conservative simplification
        // (assumes a similar taper applied then) that avoids over-crediting.
        $carryForward = $this->getCarryForward(
            $userId,
            $taxYear,
            $isTapered ? $availableAllowance : null
        );

        // Money Purchase Annual Allowance (FA 2004 s227ZA): once the user has
        // flexibly accessed a DC pension, money-purchase contributions are
        // capped at the MPAA and NO carry-forward is available against it.
        $mpaaApplies = app(PensionStore::class)
            ->hasFlexiblyAccessedDcPension(User::findOrFail($userId));
        if ($mpaaApplies) {
            $availableAllowance = min($availableAllowance, $this->getMPAA());
            $carryForward = 0.0;
        }

        // Calculate remaining allowance
        $allowanceUsed = min($totalContributions, $availableAllowance + $carryForward);
        $remainingAllowance = max(0, $availableAllowance - $totalContributions);
        $excessContributions = max(0, $totalContributions - ($availableAllowance + $carryForward));

        return [
            'tax_year' => $taxYear,
            'standard_allowance' => $standardAllowance,
            'available_allowance' => $availableAllowance,
            'is_tapered' => $isTapered,
            'tapering_details' => $taperingDetails,
            'total_contributions' => round($totalContributions, 2),
            'carry_forward_available' => round($carryForward, 2),
            'allowance_used' => round($allowanceUsed, 2),
            'remaining_allowance' => round($remainingAllowance, 2),
            'excess_contributions' => round($excessContributions, 2),
            'has_excess' => $excessContributions > 0,
            'mpaa_applies' => $mpaaApplies,
            'mpaa_amount' => $mpaaApplies ? $this->getMPAA() : null,
        ];
    }

    /**
     * Calculate tapered annual allowance for high earners.
     *
     * Reduction: £1 for every £2 over adjusted income threshold.
     * Minimum allowance: £10,000
     *
     * @return float Tapered allowance
     */
    public function calculateTapering(float $thresholdIncome, float $adjustedIncome): float
    {
        if ($thresholdIncome <= $this->getThresholdIncome() || $adjustedIncome <= $this->getAdjustedIncomeThreshold()) {
            return $this->getStandardAnnualAllowance();
        }

        // Calculate reduction
        $excessIncome = $adjustedIncome - $this->getAdjustedIncomeThreshold();
        $reduction = $excessIncome / 2;

        // Apply reduction but ensure minimum allowance
        $taperedAllowance = $this->getStandardAnnualAllowance() - $reduction;

        return max($this->getMinimumTaperedAllowance(), $taperedAllowance);
    }

    /**
     * Get carry forward allowance from previous 3 tax years.
     *
     * Primary path: reads captured PensionInputHistory rows (written by the
     * savetax onboarding flow via CoordinatingAgent → PensionStore::captureInputHistory).
     * Per-year unused = max(0, annual_allowance − pension_input_amount) so an
     * over-contributed year contributes zero, never a negative.
     *
     * Fallback: manually-entered RetirementProfile.prior_year_unused_allowance
     * JSON field (existing behaviour, unchanged). Returns 0 when neither source
     * is present (conservative default).
     *
     * Note: prior-year allowances are valued at the current standard annual
     * allowance from TaxConfigService. TaxConfigService only holds the active
     * tax year's figure; a per-historical-year lookup is not available.
     *
     * Only rows inside the exact HMRC 3-prior-years window count — stale
     * rows from older captures are ignored, never summed as eligible.
     *
     * @param  float|null  $perYearAllowanceCap  When the caller knows the user is
     *                                           currently tapered, the tapered allowance to value each prior year at
     *                                           instead of the full standard AA (conservative — see checkAnnualAllowance).
     *                                           Null keeps the standard-AA valuation. Applies to the history path only;
     *                                           the manual fallback is the user's own entered unused figure.
     * @return float Total carry forward available
     */
    public function getCarryForward(int $userId, string $taxYear, ?float $perYearAllowanceCap = null): float
    {
        $priorYears = $this->getPrevious3TaxYears($taxYear);

        // Store-mediated read (store-boundary architecture rule): the pensions
        // canonical set owns PensionInputHistory access.
        $user = User::find($userId);
        $history = $user === null
            ? collect()
            : collect(app(PensionStore::class)->pensionInputHistory($user))
                ->whereIn('tax_year', $priorYears)
                ->values();

        if ($history->isNotEmpty()) {
            $standard = (float) ($this->taxConfig->getPensionAllowances()['annual_allowance'] ?? 60000);
            $perYearAllowance = $perYearAllowanceCap ?? $standard;

            return (float) $history->sum(
                fn ($row) => max(0.0, $perYearAllowance - (float) $row->pension_input_amount)
            );
        }

        // Fallback: manually-entered RetirementProfile.prior_year_unused_allowance.
        $profile = RetirementProfile::where('user_id', $userId)->first();

        if (! $profile || ! $profile->prior_year_unused_allowance) {
            return 0.0;
        }

        $manualUnused = $profile->prior_year_unused_allowance;
        $carryForward = 0.0;

        foreach ($priorYears as $year) {
            $carryForward += (float) ($manualUnused[$year] ?? 0);
        }

        return $carryForward;
    }

    /**
     * Get the previous 3 tax year strings for carry forward lookback.
     *
     * Public so PensionAACarryForwardStrategy shares the exact same HMRC
     * window arithmetic — one source of truth for which years are eligible.
     *
     * @return array e.g. ['2022/23', '2023/24', '2024/25'] for current year '2025/26'
     */
    public function getPrevious3TaxYears(string $currentTaxYear): array
    {
        $startYear = (int) substr($currentTaxYear, 0, 4);

        return [
            ($startYear - 3).'/'.substr((string) ($startYear - 2), -2),
            ($startYear - 2).'/'.substr((string) ($startYear - 1), -2),
            ($startYear - 1).'/'.substr((string) $startYear, -2),
        ];
    }

    /**
     * Check if user has triggered Money Purchase Annual Allowance (MPAA).
     *
     * MPAA is triggered when user has flexibly accessed any DC pension
     * (e.g., flexi-access drawdown, UFPLS, or cashing in a small pot).
     */
    public function checkMPAA(int $userId): array
    {
        $flexiblyAccessed = app(PensionStore::class)
            ->forUserByType(User::findOrFail($userId), 'dc')
            ->where('has_flexibly_accessed', true);

        $isTriggered = $flexiblyAccessed->isNotEmpty();

        $mpaaAmount = $this->getMPAA();

        $triggerDate = $isTriggered
            ? $flexiblyAccessed->min('flexible_access_date')
            : null;

        return [
            'is_triggered' => $isTriggered,
            'mpaa_amount' => $mpaaAmount,
            'trigger_date' => $triggerDate,
            'message' => $isTriggered
                ? 'Money Purchase Annual Allowance triggered - your annual allowance for money purchase contributions is reduced to £'.number_format($mpaaAmount).' per year.'
                : 'Money Purchase Annual Allowance not triggered - standard annual allowance applies.',
        ];
    }

    /**
     * Calculate total annual pension contributions from all DC pensions.
     * Includes both employee and employer contributions as both count towards annual allowance.
     *
     * @param  Collection  $dcPensions
     */
    private function calculateTotalAnnualContributions($dcPensions): float
    {
        $total = 0.0;

        foreach ($dcPensions as $pension) {
            // First try monthly_contribution_amount if set
            if ($pension->monthly_contribution_amount > 0) {
                $total += (float) $pension->monthly_contribution_amount * 12;
            } else {
                // Otherwise calculate from percentages
                $annualSalary = (float) ($pension->annual_salary ?? 0);
                $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);
                $employerPercent = (float) ($pension->employer_contribution_percent ?? 0);

                // Both employee and employer contributions count towards annual allowance
                $employeeContrib = $annualSalary * ($employeePercent / 100);
                $employerContrib = $annualSalary * ($employerPercent / 100);

                $total += $employeeContrib + $employerContrib;
            }
        }

        return $total;
    }
}
