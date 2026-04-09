<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

use App\Constants\TaxDefaults;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use Illuminate\Support\Str;

/**
 * The PRIMARY recommendation engine — 11-step sequential contribution waterfall.
 *
 * Each step consumes surplus up to its wrapper limit, then passes the remainder
 * to the next step. Steps can be skipped based on age, allowance, or life event blocks.
 *
 * Step order:
 *  1. Lifetime ISA (25% bonus, age < 40, first-time buyer)
 *  2. Stocks & Shares ISA (up to remaining ISA allowance)
 *  3. Pension (up to remaining Annual Allowance)
 *  4a. Premium Bonds (up to £50,000 maximum holding)
 *  4b. NS&I Products (10% of remainder)
 *  5. Offshore Bond (higher/additional rate, min £10,000)
 *  6. Onshore Bond (higher rate, min £5,000)
 *  7. Pension Carry Forward (3-year window)
 *  8. VCT/EIS/SEIS (max 10% portfolio, experienced investors)
 *  9. GIA (remaining surplus)
 */
class ContributionWaterfallService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Allocate surplus through the 11-step waterfall.
     *
     * @param  array  $context  User context from UserContextBuilder
     * @param  float  $adjustedSurplus  Surplus after safety checks
     * @param  array  $lifeEventModifiers  From LifeEventAssessmentService
     * @param  array  $goalModifiers  From GoalAssessmentService
     * @param  array  $safetyResult  From SafetyCheckService
     * @return array{
     *     recommendations: array,
     *     total_allocated: float,
     *     remaining_surplus: float,
     *     steps_executed: int,
     *     steps_skipped: int,
     *     decision_path: array
     * }
     */
    public function allocate(
        array $context,
        float $adjustedSurplus,
        array $lifeEventModifiers,
        array $goalModifiers,
        array $safetyResult
    ): array {
        $remaining = $adjustedSurplus;
        $recommendations = [];
        $decisionPath = [];
        $stepsExecuted = 0;
        $stepsSkipped = 0;

        $blockedWrappers = array_merge(
            $lifeEventModifiers['blocked_wrappers'] ?? [],
            $goalModifiers['aggregate_blocked_wrappers'] ?? []
        );

        $waterfallConfig = $this->taxConfig->get('investment.waterfall', []);

        // ── Step 1: Lifetime ISA ──
        $step1 = $this->stepLISA($remaining, $context, $blockedWrappers, $goalModifiers);
        $this->processStep($step1, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 2: Stocks & Shares ISA ──
        $step2 = $this->stepStocksSharesISA($remaining, $context, $blockedWrappers);
        $this->processStep($step2, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 3: Pension current year ──
        $step3 = $this->stepPension($remaining, $context, $blockedWrappers, $safetyResult);
        $this->processStep($step3, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 4a: Premium Bonds ──
        $step4a = $this->stepPremiumBonds($remaining, $context, $blockedWrappers, $waterfallConfig);
        $this->processStep($step4a, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 4b: NS&I Products ──
        $step4b = $this->stepNSI($remaining, $context, $blockedWrappers, $waterfallConfig);
        $this->processStep($step4b, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 5: Offshore Bond ──
        $step5 = $this->stepOffshoreBond($remaining, $context, $blockedWrappers, $waterfallConfig);
        $this->processStep($step5, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 6: Onshore Bond ──
        $step6 = $this->stepOnshoreBond($remaining, $context, $blockedWrappers, $waterfallConfig);
        $this->processStep($step6, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 7: Pension Carry Forward ──
        $step7 = $this->stepPensionCarryForward($remaining, $context, $blockedWrappers);
        $this->processStep($step7, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 8: VCT/EIS/SEIS ──
        $step8 = $this->stepVCTEIS($remaining, $context, $blockedWrappers, $waterfallConfig);
        $this->processStep($step8, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        // ── Step 9: GIA (catch-all) ──
        $step9 = $this->stepGIA($remaining, $context, $blockedWrappers);
        $this->processStep($step9, $remaining, $recommendations, $decisionPath, $stepsExecuted, $stepsSkipped);

        $totalAllocated = $adjustedSurplus - $remaining;

        return [
            'recommendations' => $recommendations,
            'total_allocated' => round($totalAllocated, 2),
            'remaining_surplus' => round(max(0, $remaining), 2),
            'steps_executed' => $stepsExecuted,
            'steps_skipped' => $stepsSkipped,
            'decision_path' => $decisionPath,
        ];
    }

    // ──────────────────────────────────────────────
    // Waterfall steps
    // ──────────────────────────────────────────────

    /**
     * Step 1: Lifetime ISA (age < 40, first-time buyer, £4,000 limit).
     */
    private function stepLISA(float $remaining, array $context, array $blockedWrappers, array $goalModifiers): array
    {
        $stepName = 'lisa';

        if ($remaining <= 0 || in_array('lisa', $blockedWrappers, true)) {
            return $this->skipStep($stepName, 'Blocked by life event or insufficient surplus.');
        }

        $age = $context['personal']['age'] ?? null;
        if ($age === null || $age >= 40) {
            return $this->skipStep($stepName, $age !== null ? 'Age '.$age.' — Lifetime ISA only available to those under 40.' : 'Age unknown — cannot confirm Lifetime ISA eligibility.');
        }

        // Check for first-time buyer goal
        $hasFirstTimeBuyerGoal = $goalModifiers['has_house_purchase_goal'] ?? false;
        if (! $hasFirstTimeBuyerGoal) {
            return $this->skipStep($stepName, 'No first-time buyer goal — Lifetime ISA not prioritised.');
        }

        $lisaAllowances = $this->taxConfig->getISAAllowances()['lifetime_isa'] ?? [];
        $lisaLimit = is_array($lisaAllowances) ? ($lisaAllowances['annual_allowance'] ?? TaxDefaults::LISA_ALLOWANCE) : $lisaAllowances;
        $allocation = min($remaining, $lisaLimit);
        $bonus = $allocation * 0.25;
        $yearsEligible = max(0, 50 - $age);

        $trace = [];

        $trace[] = [
            'question' => 'Is there surplus available and is the Lifetime ISA wrapper not blocked?',
            'data_field' => 'remaining_surplus',
            'data_value' => '£'.number_format($remaining, 0).' surplus entering this step',
            'threshold' => 'More than £0 and not blocked',
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' surplus available. No life event or goal blocks on the Lifetime ISA wrapper.',
        ];

        $trace[] = [
            'question' => 'Is the user under 40 and eligible for a Lifetime ISA?',
            'data_field' => 'personal.age',
            'data_value' => 'Age '.$age,
            'threshold' => 'Under 40',
            'passed' => true,
            'explanation' => 'Age '.$age.' — eligible for Lifetime ISA contributions for '.$yearsEligible.' more years (government bonus paid until age 50).',
        ];

        $trace[] = [
            'question' => 'Is there a first-time buyer goal linked?',
            'data_field' => 'goalModifiers.has_house_purchase_goal',
            'data_value' => 'Yes',
            'threshold' => 'Yes',
            'passed' => true,
            'explanation' => 'First-time buyer goal found — Lifetime ISA prioritised for the 25% government bonus.',
        ];

        $trace[] = [
            'question' => 'How much can be allocated to the Lifetime ISA?',
            'data_field' => 'calculated: min(surplus, lisa_limit)',
            'data_value' => 'min(£'.number_format($remaining, 0).', £'.number_format($lisaLimit, 0).') = £'.number_format($allocation, 0),
            'threshold' => '£'.number_format($lisaLimit, 0).' annual limit',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated. Government bonus: £'.number_format($allocation, 0).' × 25% = £'.number_format($bonus, 0).'. Total invested: £'.number_format($allocation + $bonus, 0).'.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Contribute to Lifetime ISA',
            'explanation' => sprintf(
                'As a first-time buyer under 40, the Lifetime ISA adds a 25%% government bonus on contributions up to £%s per year. On £%s that is a £%s bonus.',
                number_format($lisaLimit, 0, '.', ','),
                number_format($allocation, 0, '.', ','),
                number_format($bonus, 0, '.', ',')
            ),
            'personal_context' => sprintf(
                'Age %d — %d years of Lifetime ISA eligibility remaining.',
                $age,
                $yearsEligible
            ),
            'wrapper' => 'lisa',
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 2: Stocks & Shares ISA (up to remaining ISA allowance).
     */
    private function stepStocksSharesISA(float $remaining, array $context, array $blockedWrappers): array
    {
        $stepName = 'stocks_shares_isa';

        if ($remaining <= 0 || in_array('stocks_shares_isa', $blockedWrappers, true)) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No surplus remaining after prior steps.' : 'Stocks and Shares ISA wrapper blocked.');
        }

        $isaRemaining = $context['allowances']['isa_remaining'] ?? 0;
        $isaAnnual = $context['allowances']['isa_annual'] ?? TaxDefaults::ISA_ALLOWANCE;
        $isaUsed = $context['allowances']['isa_used'] ?? 0;

        // Deduct any LISA allocation already made (LISA counts towards ISA allowance)
        // The LISA step has already been processed, so isa_remaining may need adjustment
        if ($isaRemaining <= 0) {
            return $this->skipStep($stepName, 'ISA allowance fully used this tax year: £'.number_format($isaUsed, 0).' of £'.number_format($isaAnnual, 0).' used.');
        }

        $allocation = min($remaining, $isaRemaining);

        $riskLevel = $context['risk']['risk_level'] ?? 'medium';
        $returnParams = $this->riskPreferenceService->getReturnParameters($riskLevel);
        $expectedReturn = $returnParams['expected_return_typical'];
        $taxBand = $context['financial']['tax_band'] ?? 'basic';

        $trace = [];

        $trace[] = [
            'question' => 'Is the Stocks and Shares ISA wrapper available with surplus to deploy?',
            'data_field' => 'remaining_surplus + blockedWrappers',
            'data_value' => '£'.number_format($remaining, 0).' surplus entering this step, wrapper not blocked',
            'threshold' => 'More than £0 and not blocked',
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' surplus available for ISA consideration.',
        ];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance this tax year?',
            'data_field' => 'allowances.isa_remaining (isa_used: £'.number_format($isaUsed, 0).' of £'.number_format($isaAnnual, 0).')',
            'data_value' => '£'.number_format($isaRemaining, 0).' remaining',
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => '£'.number_format($isaRemaining, 0).' ISA allowance available. Contributions grow free of income tax and Capital Gains Tax.',
        ];

        $trace[] = [
            'question' => 'How much can be allocated to the ISA and what is the expected return?',
            'data_field' => 'calculated: min(surplus, isa_remaining)',
            'data_value' => 'min(£'.number_format($remaining, 0).', £'.number_format($isaRemaining, 0).') = £'.number_format($allocation, 0),
            'threshold' => 'Capped by ISA remaining',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated to Stocks and Shares ISA. At '.$riskLevel.' risk, expected typical return is '.round($expectedReturn, 1).'% per year. As a '.$taxBand.' rate taxpayer, the ISA wrapper shelters all growth from tax.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Maximise Stocks and Shares ISA contributions',
            'explanation' => sprintf(
                'ISA allowance remaining: £%s of £%s. Contributions grow free of income tax and Capital Gains Tax. At %s risk, typical returns are around %.1f%% per year.',
                number_format($isaRemaining, 0, '.', ','),
                number_format($isaAnnual, 0, '.', ','),
                $riskLevel,
                $expectedReturn
            ),
            'personal_context' => sprintf(
                'ISA allowance used: £%s of £%s. Allocating £%s to Stocks and Shares ISA.',
                number_format($isaUsed, 0, '.', ','),
                number_format($isaAnnual, 0, '.', ','),
                number_format($allocation, 0, '.', ',')
            ),
            'wrapper' => 'stocks_shares_isa',
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 3: Pension current year (up to remaining Annual Allowance).
     */
    private function stepPension(float $remaining, array $context, array $blockedWrappers, array $safetyResult): array
    {
        $stepName = 'pension';

        if ($remaining <= 0 || in_array('pension', $blockedWrappers, true)) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No surplus remaining after prior steps.' : 'Pension wrapper blocked.');
        }

        $pensionRemaining = $context['allowances']['pension_remaining'] ?? 0;
        $pensionAnnualAllowance = $context['allowances']['pension_annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
        $pensionContributions = $context['allowances']['pension_contributions_this_year'] ?? 0;
        if ($pensionRemaining <= 0) {
            return $this->skipStep($stepName, 'Pension Annual Allowance fully used: £'.number_format($pensionContributions, 0).' of £'.number_format($pensionAnnualAllowance, 0).' contributed.');
        }

        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $grossIncome = $context['financial']['gross_income'] ?? 0;

        // Tax relief percentage based on marginal rate
        $incomeTaxBands = $this->taxConfig->getIncomeTax();
        $basicRate = (float) ($incomeTaxBands['bands'][0]['rate'] ?? 0.20);
        $higherRate = (float) ($incomeTaxBands['bands'][1]['rate'] ?? 0.40);
        $additionalRate = (float) ($incomeTaxBands['bands'][2]['rate'] ?? 0.45);
        $reliefRate = match ($taxBand) {
            'additional' => $additionalRate,
            'higher' => $higherRate,
            'basic' => $basicRate,
            default => $basicRate,
        };

        // For higher/additional rate — suggest more; for basic rate — suggest less
        $pensionProportion = match ($taxBand) {
            'additional' => 0.60,
            'higher' => 0.50,
            'basic' => 0.30,
            default => 0.20,
        };

        $desiredAllocation = $remaining * $pensionProportion;
        $allocation = min($remaining, $pensionRemaining, $desiredAllocation);

        // Ensure pension contribution doesn't exceed net relevant earnings
        $maxContribution = min($grossIncome, $pensionRemaining);
        $allocation = min($allocation, $maxContribution);

        if ($allocation < 100) {
            return $this->skipStep($stepName, 'Pension allocation of £'.number_format($allocation, 0).' is too small to be meaningful (minimum £100).');
        }

        $taxRelief = $allocation * $reliefRate;
        $netCost = $allocation * (1 - $reliefRate);

        // Note employer match if available
        $employerNote = '';
        $employerMatch = $safetyResult['employer_match'] ?? null;
        if ($employerMatch !== null) {
            $employerNote = sprintf(' Employer matches up to %.1f%% — ensure this is maximised.', $employerMatch['matching_limit'] ?? 0);
        }

        $trace = [];

        $trace[] = [
            'question' => 'Is there remaining pension Annual Allowance?',
            'data_field' => 'allowances.pension_remaining (contributions: £'.number_format($pensionContributions, 0).' of £'.number_format($pensionAnnualAllowance, 0).')',
            'data_value' => '£'.number_format($pensionRemaining, 0).' remaining',
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => '£'.number_format($pensionRemaining, 0).' of the £'.number_format($pensionAnnualAllowance, 0).' Annual Allowance available for further contributions.',
        ];

        $trace[] = [
            'question' => 'What tax relief rate applies and how much of the surplus should go to pension?',
            'data_field' => 'financial.tax_band + financial.gross_income',
            'data_value' => ucfirst($taxBand).' rate taxpayer (£'.number_format($grossIncome, 0).' gross income), '.round($reliefRate * 100).'% relief, '.round($pensionProportion * 100).'% of surplus suggested',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => ucfirst($taxBand).' rate taxpayer — '.round($reliefRate * 100).'% tax relief on pension contributions. At this tax band, '.round($pensionProportion * 100).'% of surplus (£'.number_format($desiredAllocation, 0).') is directed to pension, capped by allowance and income.',
        ];

        $trace[] = [
            'question' => 'How much is allocated to pension and what is the net cost?',
            'data_field' => 'calculated: min(surplus × proportion, pension_remaining, gross_income)',
            'data_value' => 'min(£'.number_format($desiredAllocation, 0).', £'.number_format($pensionRemaining, 0).', £'.number_format($grossIncome, 0).') = £'.number_format($allocation, 0),
            'threshold' => 'At least £100',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' contribution. Tax relief: £'.number_format($allocation, 0).' × '.round($reliefRate * 100).'% = £'.number_format($taxRelief, 0).'. Effective net cost: £'.number_format($netCost, 0).'.'.$employerNote,
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Contribute to pension',
            'explanation' => sprintf(
                'As a %s rate taxpayer (£%s gross income), pension contributions receive %.0f%% tax relief. A contribution of £%s effectively costs £%s after relief.%s',
                $taxBand,
                number_format($grossIncome, 0, '.', ','),
                $reliefRate * 100,
                number_format($allocation, 0, '.', ','),
                number_format($netCost, 0, '.', ','),
                $employerNote
            ),
            'personal_context' => sprintf(
                'Annual Allowance remaining: £%s of £%s. Tax relief at %.0f%% = £%s saved.',
                number_format($pensionRemaining, 0, '.', ','),
                number_format($pensionAnnualAllowance, 0, '.', ','),
                $reliefRate * 100,
                number_format($taxRelief, 0, '.', ',')
            ),
            'wrapper' => 'pension',
            'tax_relief' => round($taxRelief, 2),
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 4a: Premium Bonds (up to £50,000 maximum holding).
     */
    private function stepPremiumBonds(float $remaining, array $context, array $blockedWrappers, array $waterfallConfig): array
    {
        $stepName = 'premium_bonds';

        if ($remaining <= 0 || in_array('premium_bonds', $blockedWrappers, true)) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No surplus remaining after prior steps.' : 'Premium Bonds wrapper blocked.');
        }

        $maxHolding = (float) ($waterfallConfig['premium_bonds_max'] ?? 50000);
        $minAge = (int) ($waterfallConfig['premium_bonds_min_age'] ?? 16);
        $age = $context['personal']['age'] ?? null;

        if ($age !== null && $age < $minAge) {
            return $this->skipStep($stepName, sprintf('Age %d — must be at least %d to hold Premium Bonds.', $age, $minAge));
        }

        // Estimate current Premium Bonds holding (not tracked individually — allocate conservatively)
        $currentHolding = 0; // Would need to be populated from savings accounts if tracked
        $headroom = max(0, $maxHolding - $currentHolding);

        if ($headroom <= 0) {
            return $this->skipStep($stepName, 'Premium Bonds holding at maximum of £'.number_format($maxHolding, 0).'.');
        }

        // Allocate up to 20% of remaining surplus to Premium Bonds
        $desiredAllocation = min($remaining * 0.20, $headroom);
        $allocation = min($remaining, $desiredAllocation);

        if ($allocation < 25) {
            return $this->skipStep($stepName, 'Allocation of £'.number_format($allocation, 0).' is below the Premium Bonds minimum of £25.');
        }

        $trace = [];

        $trace[] = [
            'question' => 'Is the user eligible for Premium Bonds?',
            'data_field' => 'personal.age',
            'data_value' => $age !== null ? 'Age '.$age : 'Age not set',
            'threshold' => 'At least '.$minAge,
            'passed' => true,
            'explanation' => ($age !== null ? 'Age '.$age.' — ' : '').'Eligible for Premium Bonds. Prizes are tax-free regardless of tax band.',
        ];

        $trace[] = [
            'question' => 'Is there headroom within the maximum Premium Bonds holding?',
            'data_field' => 'premium_bonds_max',
            'data_value' => 'Current holding: £'.number_format($currentHolding, 0).', Maximum: £'.number_format($maxHolding, 0),
            'threshold' => 'More than £0 headroom',
            'passed' => true,
            'explanation' => '£'.number_format($headroom, 0).' headroom available. £'.number_format($maxHolding, 0).' - £'.number_format($currentHolding, 0).' = £'.number_format($headroom, 0).'.',
        ];

        $trace[] = [
            'question' => 'How much should be allocated to Premium Bonds?',
            'data_field' => 'calculated: min(20% of surplus, headroom)',
            'data_value' => 'min(£'.number_format($remaining, 0).' × 20% = £'.number_format($remaining * 0.20, 0).', £'.number_format($headroom, 0).') = £'.number_format($allocation, 0),
            'threshold' => 'At least £25 minimum',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated (20% of £'.number_format($remaining, 0).' remaining surplus, capped by headroom). Capital is secure with HM Treasury backing.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Add to Premium Bonds',
            'explanation' => sprintf(
                'Premium Bonds offer tax-free prizes with a current prize fund rate. Maximum holding: £%s. Capital is secure with government backing.',
                number_format($maxHolding, 0, '.', ',')
            ),
            'personal_context' => sprintf(
                'Suggested allocation: £%s. Maximum holding: £%s. Headroom: £%s.',
                number_format($allocation, 0, '.', ','),
                number_format($maxHolding, 0, '.', ','),
                number_format($headroom, 0, '.', ',')
            ),
            'wrapper' => 'premium_bonds',
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 4b: NS&I Products (10% of remainder).
     */
    private function stepNSI(float $remaining, array $context, array $blockedWrappers, array $waterfallConfig): array
    {
        $stepName = 'nsi';

        if ($remaining <= 0 || in_array('nsi', $blockedWrappers, true)) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No surplus remaining after prior steps.' : 'NS&I wrapper blocked.');
        }

        $allocationPercent = (float) ($waterfallConfig['nsi_allocation_percent'] ?? 0.10);
        $minimum = (float) ($waterfallConfig['nsi_minimum'] ?? 25);

        $allocation = $remaining * $allocationPercent;

        if ($allocation < $minimum) {
            return $this->skipStep($stepName, sprintf('Allocation of £%s (%.0f%% of £%s) is below the NS&I minimum of £%s.', number_format($allocation, 0, '.', ','), $allocationPercent * 100, number_format($remaining, 0, '.', ','), number_format($minimum, 0, '.', ',')));
        }

        $allocation = min($remaining, $allocation);

        $trace = [];

        $trace[] = [
            'question' => 'How much should be directed to NS&I products?',
            'data_field' => 'calculated: surplus × '.round($allocationPercent * 100).'%',
            'data_value' => '£'.number_format($remaining, 0).' × '.round($allocationPercent * 100).'% = £'.number_format($allocation, 0),
            'threshold' => 'At least £'.number_format($minimum, 0).' minimum',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated to NS&I products ('.round($allocationPercent * 100).'% of £'.number_format($remaining, 0).' remaining surplus). NS&I products are backed by HM Treasury, providing capital security.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Consider NS&I savings products',
            'explanation' => 'NS&I products are backed by HM Treasury offering capital security. Income Bonds and Direct Saver provide competitive rates with government backing.',
            'personal_context' => sprintf(
                'Suggested allocation of £%s (%.0f%% of remaining surplus) to NS&I products.',
                number_format($allocation, 0, '.', ','),
                $allocationPercent * 100
            ),
            'wrapper' => 'nsi',
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 5: Offshore Bond (higher/additional rate, minimum £10,000).
     */
    private function stepOffshoreBond(float $remaining, array $context, array $blockedWrappers, array $waterfallConfig): array
    {
        $stepName = 'offshore_bond';

        if ($remaining <= 0 || in_array('offshore_bond', $blockedWrappers, true)) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No surplus remaining after prior steps.' : 'Offshore bond wrapper blocked.');
        }

        $minimum = (float) ($waterfallConfig['offshore_bond_minimum'] ?? 10000);
        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $grossIncome = $context['financial']['gross_income'] ?? 0;

        if (! in_array($taxBand, ['higher', 'additional'], true)) {
            return $this->skipStep($stepName, ucfirst($taxBand).' rate taxpayer (£'.number_format($grossIncome, 0).' gross income) — offshore bonds are most beneficial for higher or additional rate taxpayers.');
        }

        if ($remaining < $minimum) {
            return $this->skipStep($stepName, sprintf('Remaining surplus of £%s is below the offshore bond minimum of £%s.', number_format($remaining, 0, '.', ','), number_format($minimum, 0, '.', ',')));
        }

        // Allocate up to 30% of remaining for offshore bond
        $allocation = min($remaining, $remaining * 0.30);
        $allocation = max($minimum, $allocation);
        $allocation = min($remaining, $allocation);
        $annualWithdrawalAllowance = $allocation * 0.05;

        $trace = [];

        $trace[] = [
            'question' => 'Is the user a higher or additional rate taxpayer who benefits from gross roll-up?',
            'data_field' => 'financial.tax_band + financial.gross_income',
            'data_value' => ucfirst($taxBand).' rate taxpayer, £'.number_format($grossIncome, 0).' gross income',
            'threshold' => 'Higher or additional rate',
            'passed' => true,
            'explanation' => ucfirst($taxBand).' rate taxpayer — offshore bond allows investment growth to roll up gross (no internal tax on the fund). This is most beneficial when the investor expects to be in a lower tax band on withdrawal.',
        ];

        $trace[] = [
            'question' => 'Does the remaining surplus meet the minimum for an offshore bond?',
            'data_field' => 'remaining_surplus',
            'data_value' => '£'.number_format($remaining, 0),
            'threshold' => 'At least £'.number_format($minimum, 0),
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' surplus exceeds the £'.number_format($minimum, 0).' minimum investment threshold.',
        ];

        $trace[] = [
            'question' => 'How much should be allocated to the offshore bond?',
            'data_field' => 'calculated: max(minimum, 30% of surplus)',
            'data_value' => '£'.number_format($allocation, 0).' (30% of £'.number_format($remaining, 0).')',
            'threshold' => '30% of remaining surplus',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated. The 5% annual tax-deferred withdrawal allowance permits £'.number_format($annualWithdrawalAllowance, 0).'/year withdrawals without immediate tax.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Consider an offshore investment bond',
            'explanation' => sprintf(
                'As a %s rate taxpayer (£%s gross income), an offshore bond allows investment growth to roll up gross. The 5%% annual tax-deferred withdrawal allowance provides flexible access — £%s per year on this allocation.',
                $taxBand,
                number_format($grossIncome, 0, '.', ','),
                number_format($annualWithdrawalAllowance, 0, '.', ',')
            ),
            'personal_context' => sprintf(
                'Suggested allocation: £%s. Tax band: %s rate. Minimum investment: £%s.',
                number_format($allocation, 0, '.', ','),
                $taxBand,
                number_format($minimum, 0, '.', ',')
            ),
            'wrapper' => 'offshore_bond',
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 6: Onshore Bond (higher rate, minimum £5,000, top-slicing benefit).
     */
    private function stepOnshoreBond(float $remaining, array $context, array $blockedWrappers, array $waterfallConfig): array
    {
        $stepName = 'onshore_bond';

        if ($remaining <= 0 || in_array('onshore_bond', $blockedWrappers, true)) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No surplus remaining after prior steps.' : 'Onshore bond wrapper blocked.');
        }

        $minimum = (float) ($waterfallConfig['onshore_bond_minimum'] ?? 5000);
        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $grossIncome = $context['financial']['gross_income'] ?? 0;

        if ($taxBand !== 'higher') {
            return $this->skipStep($stepName, ucfirst($taxBand).' rate taxpayer (£'.number_format($grossIncome, 0).' gross income) — onshore bonds with top-slicing relief are most beneficial for higher rate taxpayers.');
        }

        if ($remaining < $minimum) {
            return $this->skipStep($stepName, sprintf('Remaining surplus of £%s is below the onshore bond minimum of £%s.', number_format($remaining, 0, '.', ','), number_format($minimum, 0, '.', ',')));
        }

        $allocation = min($remaining, $remaining * 0.25);
        $allocation = max($minimum, $allocation);
        $allocation = min($remaining, $allocation);
        $annualWithdrawalAllowance = $allocation * 0.05;

        $trace = [];

        $trace[] = [
            'question' => 'Is the user a higher rate taxpayer who benefits from top-slicing relief?',
            'data_field' => 'financial.tax_band + financial.gross_income',
            'data_value' => ucfirst($taxBand).' rate taxpayer, £'.number_format($grossIncome, 0).' gross income',
            'threshold' => 'Higher rate',
            'passed' => true,
            'explanation' => 'Higher rate taxpayer — onshore bond with top-slicing relief can reduce the effective tax rate on gains. Gains are spread across the years the bond is held, potentially bringing some into the basic rate band.',
        ];

        $trace[] = [
            'question' => 'Does the remaining surplus meet the minimum for an onshore bond?',
            'data_field' => 'remaining_surplus',
            'data_value' => '£'.number_format($remaining, 0),
            'threshold' => 'At least £'.number_format($minimum, 0),
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' surplus exceeds the £'.number_format($minimum, 0).' minimum.',
        ];

        $trace[] = [
            'question' => 'How much should be allocated to the onshore bond?',
            'data_field' => 'calculated: max(minimum, 25% of surplus)',
            'data_value' => '£'.number_format($allocation, 0).' (25% of £'.number_format($remaining, 0).')',
            'threshold' => '25% of remaining surplus',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated. The 5% tax-deferred withdrawal allowance permits £'.number_format($annualWithdrawalAllowance, 0).'/year. Top-slicing relief is most beneficial if income drops to basic rate in future.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Consider an onshore investment bond',
            'explanation' => sprintf(
                'As a higher rate taxpayer (£%s gross income), onshore bonds benefit from top-slicing relief — gains are spread across the years held, potentially reducing the effective tax rate. 5%% annual tax-deferred withdrawal of £%s applies.',
                number_format($grossIncome, 0, '.', ','),
                number_format($annualWithdrawalAllowance, 0, '.', ',')
            ),
            'personal_context' => sprintf(
                'Suggested allocation: £%s. Top-slicing relief is most beneficial if income drops to basic rate in future.',
                number_format($allocation, 0, '.', ',')
            ),
            'wrapper' => 'onshore_bond',
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 7: Pension Carry Forward (3-year unused allowance window).
     */
    private function stepPensionCarryForward(float $remaining, array $context, array $blockedWrappers): array
    {
        $stepName = 'pension_carry_forward';

        if ($remaining <= 0 || in_array('pension', $blockedWrappers, true)) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No surplus remaining after prior steps.' : 'Pension wrapper blocked.');
        }

        $pensionRemaining = $context['allowances']['pension_remaining'] ?? 0;
        $pensionAnnualAllowance = $context['allowances']['pension_annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
        $grossIncome = $context['financial']['gross_income'] ?? 0;

        // Carry forward is only relevant if current year allowance is used
        // and there is unused allowance from prior years
        if ($pensionRemaining > 0) {
            return $this->skipStep($stepName, 'Current year pension allowance not yet exhausted — £'.number_format($pensionRemaining, 0).' of £'.number_format($pensionAnnualAllowance, 0).' still available. Use current year allowance first.');
        }

        // Estimate carry forward availability (3 years of unused allowance)
        // In practice this requires prior year data — estimate conservatively
        $estimatedCarryForward = $pensionAnnualAllowance * 0.5; // Conservative: assume 50% of one year unused

        if ($estimatedCarryForward <= 0) {
            return $this->skipStep($stepName, 'No estimated carry forward available.');
        }

        // Cannot exceed net relevant earnings
        $maxContribution = min($grossIncome, $estimatedCarryForward);
        $allocation = min($remaining, $maxContribution);

        if ($allocation < 1000) {
            return $this->skipStep($stepName, 'Carry forward allocation of £'.number_format($allocation, 0).' is too small (minimum £1,000 recommended).');
        }

        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $carryIncomeTaxBands = $this->taxConfig->getIncomeTax();
        $carryBasicRate = (float) ($carryIncomeTaxBands['bands'][0]['rate'] ?? 0.20);
        $carryHigherRate = (float) ($carryIncomeTaxBands['bands'][1]['rate'] ?? 0.40);
        $carryAdditionalRate = (float) ($carryIncomeTaxBands['bands'][2]['rate'] ?? 0.45);
        $reliefRate = match ($taxBand) {
            'additional' => $carryAdditionalRate,
            'higher' => $carryHigherRate,
            default => $carryBasicRate,
        };
        $taxRelief = $allocation * $reliefRate;
        $netCost = $allocation * (1 - $reliefRate);

        $trace = [];

        $trace[] = [
            'question' => 'Has the current year pension allowance been exhausted?',
            'data_field' => 'allowances.pension_remaining',
            'data_value' => '£'.number_format($pensionRemaining, 0).' remaining of £'.number_format($pensionAnnualAllowance, 0),
            'threshold' => '£0 (fully used)',
            'passed' => true,
            'explanation' => 'Current year Annual Allowance of £'.number_format($pensionAnnualAllowance, 0).' is fully used — carry forward from prior years may be available.',
        ];

        $trace[] = [
            'question' => 'Is there estimated carry forward from prior tax years?',
            'data_field' => 'estimated: 50% of one year\'s allowance',
            'data_value' => '£'.number_format($estimatedCarryForward, 0).' estimated',
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => 'Estimated £'.number_format($estimatedCarryForward, 0).' carry forward available (conservative estimate of 50% of one year\'s £'.number_format($pensionAnnualAllowance, 0).' allowance). Verification against pension statements required.',
        ];

        $trace[] = [
            'question' => 'How much can be contributed via carry forward?',
            'data_field' => 'calculated: min(surplus, carry_forward, gross_income)',
            'data_value' => 'min(£'.number_format($remaining, 0).', £'.number_format($estimatedCarryForward, 0).', £'.number_format($grossIncome, 0).') = £'.number_format($allocation, 0),
            'threshold' => 'Capped by income (£'.number_format($grossIncome, 0).') and carry forward',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' contribution. Tax relief at '.round($reliefRate * 100).'%: £'.number_format($taxRelief, 0).'. Net cost: £'.number_format($netCost, 0).'. This is a lump sum opportunity requiring verification against pension statements.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Use pension carry forward',
            'explanation' => sprintf(
                'There may be unused pension Annual Allowance from the previous 3 tax years. As a %s rate taxpayer (£%s gross income), a lump sum contribution of £%s would receive %.0f%% tax relief (£%s), costing £%s net.',
                $taxBand,
                number_format($grossIncome, 0, '.', ','),
                number_format($allocation, 0, '.', ','),
                $reliefRate * 100,
                number_format($taxRelief, 0, '.', ','),
                number_format($netCost, 0, '.', ',')
            ),
            'personal_context' => 'Check your pension statements for unused allowance from the last 3 tax years. Carry forward is a lump sum opportunity — review with your pension provider.',
            'wrapper' => 'pension_carry_forward',
            'requires_verification' => true,
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 8: VCT/EIS/SEIS (max 10% of portfolio, experienced investors only).
     */
    private function stepVCTEIS(float $remaining, array $context, array $blockedWrappers, array $waterfallConfig): array
    {
        $stepName = 'vct_eis_seis';

        $blocked = in_array('vct', $blockedWrappers, true)
            || in_array('eis', $blockedWrappers, true)
            || in_array('seis', $blockedWrappers, true);

        if ($remaining <= 0 || $blocked) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No surplus remaining after prior steps.' : 'Venture capital wrappers blocked.');
        }

        $maxPortfolioPercent = (float) ($waterfallConfig['vct_eis_seis_max_portfolio_percent'] ?? 0.10);
        $minAllocation = (float) ($waterfallConfig['vct_eis_seis_min_allocation'] ?? 1000);
        $disposableGate = (float) ($waterfallConfig['vct_eis_seis_disposable_gate'] ?? 0.10);

        $portfolioValue = $context['portfolio']['total_value'] ?? 0;
        $riskLevel = $context['risk']['risk_level'] ?? 'medium';
        $disposableIncome = $context['financial']['disposable_income'] ?? 0;
        $grossIncome = $context['financial']['gross_income'] ?? 0;
        $taxBand = $context['financial']['tax_band'] ?? 'basic';

        // Only for experienced investors with higher risk tolerance
        if (! in_array($riskLevel, ['upper_medium', 'high'], true)) {
            return $this->skipStep($stepName, ucfirst(str_replace('_', '-', $riskLevel)).' risk tolerance — Venture Capital Trust/Enterprise Investment Scheme investments require upper-medium or high risk tolerance due to illiquidity and higher risk.');
        }

        // Cap at 10% of portfolio
        $maxFromPortfolio = $portfolioValue * $maxPortfolioPercent;

        // Also cap at a proportion of disposable income
        $maxFromDisposable = $disposableIncome * $disposableGate;

        $allocation = min($remaining, $maxFromPortfolio, $maxFromDisposable);

        if ($allocation < $minAllocation) {
            return $this->skipStep($stepName, sprintf('Allocation of £%s is below the minimum of £%s for venture capital investments. Portfolio cap (%.0f%% of £%s): £%s. Disposable income cap (%.0f%% of £%s): £%s.', number_format($allocation, 0, '.', ','), number_format($minAllocation, 0, '.', ','), $maxPortfolioPercent * 100, number_format($portfolioValue, 0, '.', ','), number_format($maxFromPortfolio, 0, '.', ','), $disposableGate * 100, number_format($disposableIncome, 0, '.', ','), number_format($maxFromDisposable, 0, '.', ',')));
        }

        $ventureConfig = $this->taxConfig->get('investment.venture_capital', []);
        $eisRelief = (float) ($ventureConfig['eis']['relief'] ?? 0.30);
        $taxRelief = $allocation * $eisRelief;

        $trace = [];

        $trace[] = [
            'question' => 'Does the user have sufficient risk tolerance for venture capital investments?',
            'data_field' => 'risk.risk_level',
            'data_value' => ucfirst(str_replace('_', '-', $riskLevel)).' risk tolerance',
            'threshold' => 'Upper-medium or high',
            'passed' => true,
            'explanation' => ucfirst(str_replace('_', '-', $riskLevel)).' risk tolerance — suitable for venture capital exposure. These investments are illiquid with minimum holding periods.',
        ];

        $trace[] = [
            'question' => 'How much can be allocated within portfolio and income limits?',
            'data_field' => 'calculated: min(surplus, portfolio_cap, disposable_cap)',
            'data_value' => 'min(£'.number_format($remaining, 0).', £'.number_format($maxFromPortfolio, 0).' ('.round($maxPortfolioPercent * 100).'% of £'.number_format($portfolioValue, 0).'), £'.number_format($maxFromDisposable, 0).' ('.round($disposableGate * 100).'% of £'.number_format($disposableIncome, 0).')) = £'.number_format($allocation, 0),
            'threshold' => 'At least £'.number_format($minAllocation, 0).' minimum',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated. Enterprise Investment Scheme income tax relief at '.round($eisRelief * 100).'%: £'.number_format($allocation, 0).' × '.round($eisRelief * 100).'% = £'.number_format($taxRelief, 0).' tax reduction as a '.$taxBand.' rate taxpayer.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Consider Venture Capital Trust or Enterprise Investment Scheme',
            'explanation' => sprintf(
                'With a %s risk profile and portfolio of £%s, a small allocation to Venture Capital Trust or Enterprise Investment Scheme investments provides %.0f%% income tax relief (£%s on £%s). These are illiquid and carry higher risk.',
                str_replace('_', '-', $riskLevel),
                number_format($portfolioValue, 0, '.', ','),
                $eisRelief * 100,
                number_format($taxRelief, 0, '.', ','),
                number_format($allocation, 0, '.', ',')
            ),
            'personal_context' => sprintf(
                'Maximum suggested: £%s (%.0f%% of portfolio value). Minimum holding period applies.',
                number_format($allocation, 0, '.', ','),
                $maxPortfolioPercent * 100
            ),
            'wrapper' => 'vct_eis_seis',
            'requires_specialist_advice' => true,
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    /**
     * Step 9: GIA catch-all (remaining surplus, no limits).
     */
    private function stepGIA(float $remaining, array $context, array $blockedWrappers): array
    {
        $stepName = 'gia';

        if ($remaining <= 0 || in_array('gia', $blockedWrappers, true)) {
            return $this->skipStep($stepName, $remaining <= 0 ? 'No remaining surplus to allocate — all surplus deployed to tax-efficient wrappers.' : 'General Investment Account wrapper blocked.');
        }

        $cgtExempt = $context['allowances']['cgt_annual_exempt'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT;
        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $grossIncome = $context['financial']['gross_income'] ?? 0;

        $cgtRate = match ($taxBand) {
            'higher', 'additional' => TaxDefaults::CGT_HIGHER_RATE,
            default => TaxDefaults::CGT_BASIC_RATE,
        };

        $trace = [];

        $trace[] = [
            'question' => 'Is there remaining surplus after all tax-efficient wrappers?',
            'data_field' => 'remaining_surplus',
            'data_value' => '£'.number_format($remaining, 0).' remaining after ISA, pension, bonds, and other wrappers',
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' remains after maximising all tax-efficient wrappers. This goes into a General Investment Account — the only wrapper with no contribution limits.',
        ];

        $trace[] = [
            'question' => 'What Capital Gains Tax rate applies to General Investment Account gains?',
            'data_field' => 'financial.tax_band + cgt_rates',
            'data_value' => ucfirst($taxBand).' rate taxpayer (£'.number_format($grossIncome, 0).' gross), Capital Gains Tax at '.round($cgtRate * 100).'% above £'.number_format($cgtExempt, 0).' exemption',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => ucfirst($taxBand).' rate taxpayer — gains above the £'.number_format($cgtExempt, 0).' annual exemption are taxed at '.round($cgtRate * 100).'%. Mitigation strategies: use accumulation units (no dividend tax events), index trackers (low turnover), and annual Bed and ISA transfers.',
        ];

        $step = $this->buildStep($stepName, $remaining, [
            'headline' => 'Invest remaining surplus in a General Investment Account',
            'explanation' => sprintf(
                'After maximising tax-efficient wrappers, the remaining £%s can be invested in a General Investment Account. As a %s rate taxpayer, gains above the £%s annual Capital Gains Tax exemption are taxed at %.0f%%.',
                number_format($remaining, 0, '.', ','),
                $taxBand,
                number_format($cgtExempt, 0, '.', ','),
                $cgtRate * 100
            ),
            'personal_context' => sprintf(
                'Consider tax-efficient funds (accumulation units, index trackers) to minimise taxable distributions. Annual Capital Gains Tax exemption: £%s.',
                number_format($cgtExempt, 0, '.', ',')
            ),
            'wrapper' => 'gia',
        ]);
        $step['recommendation']['decision_trace'] = $trace;

        return $step;
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Build a recommendation step result.
     */
    private function buildStep(string $stepName, float $allocation, array $details): array
    {
        return [
            'step' => $stepName,
            'skipped' => false,
            'allocation' => round($allocation, 2),
            'recommendation' => array_merge([
                'id' => (string) Str::uuid(),
                'source' => 'waterfall',
                'step' => $stepName,
                'amount' => round($allocation, 2),
                'priority' => $this->getStepPriority($stepName),
            ], $details),
        ];
    }

    /**
     * Build a skipped step result.
     */
    private function skipStep(string $stepName, string $reason): array
    {
        return [
            'step' => $stepName,
            'skipped' => true,
            'allocation' => 0,
            'skip_reason' => $reason,
        ];
    }

    /**
     * Process a step result — update running totals.
     */
    private function processStep(
        array $step,
        float &$remaining,
        array &$recommendations,
        array &$decisionPath,
        int &$stepsExecuted,
        int &$stepsSkipped
    ): void {
        if ($step['skipped']) {
            $stepsSkipped++;
            $decisionPath[] = [
                'step' => $step['step'],
                'action' => 'skipped',
                'reason' => $step['skip_reason'] ?? 'N/A',
            ];
        } else {
            $allocation = $step['allocation'];
            $remaining = max(0, $remaining - $allocation);
            $recommendations[] = $step['recommendation'];
            $stepsExecuted++;
            $decisionPath[] = [
                'step' => $step['step'],
                'action' => 'allocated',
                'amount' => $allocation,
                'remaining_after' => round($remaining, 2),
            ];
        }
    }

    /**
     * Get the default priority for a waterfall step.
     */
    private function getStepPriority(string $stepName): string
    {
        return match ($stepName) {
            'lisa' => 'high',
            'stocks_shares_isa' => 'high',
            'pension' => 'high',
            'premium_bonds' => 'medium',
            'nsi' => 'low',
            'offshore_bond' => 'medium',
            'onshore_bond' => 'medium',
            'pension_carry_forward' => 'medium',
            'vct_eis_seis' => 'low',
            'gia' => 'low',
            default => 'low',
        };
    }
}
