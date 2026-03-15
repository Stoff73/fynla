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
            return $this->skipStep($stepName, 'Lifetime ISA only available to those under 40.');
        }

        // Check for first-time buyer goal
        $hasFirstTimeBuyerGoal = $goalModifiers['has_house_purchase_goal'] ?? false;
        if (! $hasFirstTimeBuyerGoal) {
            return $this->skipStep($stepName, 'No first-time buyer goal — Lifetime ISA not prioritised.');
        }

        $lisaAllowances = $this->taxConfig->getISAAllowances()['lifetime_isa'] ?? [];
        $lisaLimit = is_array($lisaAllowances) ? ($lisaAllowances['annual_allowance'] ?? TaxDefaults::LISA_ALLOWANCE) : $lisaAllowances;
        $allocation = min($remaining, $lisaLimit);

        $trace = [];

        $trace[] = [
            'question' => 'Is there surplus available and is the Lifetime ISA wrapper not blocked?',
            'data_field' => 'remaining',
            'data_value' => '£'.number_format($remaining, 0),
            'threshold' => 'More than £0, not blocked',
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' surplus available for Lifetime ISA.',
        ];

        $trace[] = [
            'question' => 'Is the user under 40 and eligible for a Lifetime ISA?',
            'data_field' => 'age',
            'data_value' => (string) $age,
            'threshold' => 'Under 40',
            'passed' => true,
            'explanation' => 'Age '.$age.' — eligible for '.max(0, 50 - $age).' more years of Lifetime ISA bonus.',
        ];

        $trace[] = [
            'question' => 'Is there a first-time buyer goal linked?',
            'data_field' => 'has_house_purchase_goal',
            'data_value' => 'Yes',
            'threshold' => 'Yes',
            'passed' => true,
            'explanation' => 'First-time buyer goal found — Lifetime ISA prioritised for 25% government bonus.',
        ];

        $trace[] = [
            'question' => 'How much can be allocated to the Lifetime ISA?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '£'.number_format($lisaLimit, 0).' annual limit',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated, earning a £'.number_format($allocation * 0.25, 0).' government bonus.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Contribute to Lifetime ISA',
            'explanation' => sprintf(
                'As a first-time buyer under 40, the Lifetime ISA adds a 25%% government bonus on contributions up to %s per year. On %s that is a %s bonus.',
                number_format($lisaLimit, 0, '.', ','),
                number_format($allocation, 0, '.', ','),
                number_format($allocation * 0.25, 0, '.', ',')
            ),
            'personal_context' => sprintf(
                'You are %d — you have %d years of Lifetime ISA eligibility remaining.',
                $age,
                max(0, 50 - $age) // LISA bonus paid until 50
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
            return $this->skipStep($stepName, 'Blocked or insufficient surplus.');
        }

        $isaRemaining = $context['allowances']['isa_remaining'] ?? 0;

        // Deduct any LISA allocation already made (LISA counts towards ISA allowance)
        // The LISA step has already been processed, so isa_remaining may need adjustment
        if ($isaRemaining <= 0) {
            return $this->skipStep($stepName, 'ISA allowance fully used this tax year.');
        }

        $allocation = min($remaining, $isaRemaining);

        $riskLevel = $context['risk']['risk_level'] ?? 'medium';
        $returnParams = $this->riskPreferenceService->getReturnParameters($riskLevel);
        $expectedReturn = $returnParams['expected_return_typical'];

        $trace = [];

        $trace[] = [
            'question' => 'Is the Stocks and Shares ISA wrapper available?',
            'data_field' => 'remaining',
            'data_value' => '£'.number_format($remaining, 0),
            'threshold' => 'More than £0, not blocked',
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' surplus available for ISA.',
        ];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance this tax year?',
            'data_field' => 'isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0),
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => '£'.number_format($isaRemaining, 0).' ISA allowance available for tax-free growth.',
        ];

        $trace[] = [
            'question' => 'How much can be allocated to the ISA?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '£'.number_format($isaRemaining, 0).' remaining',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated. Expected typical return at '.$riskLevel.' risk: '.round($expectedReturn, 1).'% per year.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Maximise Stocks and Shares ISA contributions',
            'explanation' => sprintf(
                'You have %s of ISA allowance remaining this tax year. Contributions grow free of income tax and Capital Gains Tax. At your risk level, typical returns are around %.1f%% per year.',
                number_format($isaRemaining, 0, '.', ','),
                $expectedReturn
            ),
            'personal_context' => sprintf(
                'ISA allowance used: %s of %s. Allocating %s to Stocks and Shares ISA.',
                number_format($context['allowances']['isa_used'] ?? 0, 0, '.', ','),
                number_format($context['allowances']['isa_annual'] ?? 0, 0, '.', ','),
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
            return $this->skipStep($stepName, 'Blocked or insufficient surplus.');
        }

        $pensionRemaining = $context['allowances']['pension_remaining'] ?? 0;
        if ($pensionRemaining <= 0) {
            return $this->skipStep($stepName, 'Pension Annual Allowance fully used.');
        }

        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $grossIncome = $context['financial']['gross_income'] ?? 0;

        // Tax relief percentage based on marginal rate
        $reliefRate = match ($taxBand) {
            'additional' => 0.45,
            'higher' => 0.40,
            'basic' => 0.20,
            default => 0.20,
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
            return $this->skipStep($stepName, 'Pension allocation too small to be meaningful.');
        }

        $taxRelief = $allocation * $reliefRate;

        // Note employer match if available
        $employerNote = '';
        $employerMatch = $safetyResult['employer_match'] ?? null;
        if ($employerMatch !== null) {
            $employerNote = sprintf(' Your employer matches up to %.1f%% — ensure you are maximising this.', $employerMatch['matching_limit'] ?? 0);
        }

        $trace = [];

        $trace[] = [
            'question' => 'Is there remaining pension Annual Allowance?',
            'data_field' => 'pension_remaining',
            'data_value' => '£'.number_format($pensionRemaining, 0),
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => '£'.number_format($pensionRemaining, 0).' of Annual Allowance available.',
        ];

        $trace[] = [
            'question' => 'What tax relief rate applies?',
            'data_field' => 'tax_band',
            'data_value' => $taxBand,
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => ucfirst($taxBand).' rate taxpayer — '.round($reliefRate * 100).'% tax relief on pension contributions.',
        ];

        $trace[] = [
            'question' => 'How much should be allocated to pension at this step?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '£'.number_format($maxContribution, 0).' (capped by income and allowance)',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated ('.round($pensionProportion * 100).'% of surplus for '.$taxBand.' rate). Tax relief: £'.number_format($taxRelief, 0).'.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Contribute to pension',
            'explanation' => sprintf(
                'As a %s rate taxpayer, pension contributions receive %.0f%% tax relief. A contribution of %s effectively costs you %s after relief.%s',
                $taxBand,
                $reliefRate * 100,
                number_format($allocation, 0, '.', ','),
                number_format($allocation * (1 - $reliefRate), 0, '.', ','),
                $employerNote
            ),
            'personal_context' => sprintf(
                'Annual Allowance remaining: %s. Tax relief at %.0f%%.',
                number_format($pensionRemaining, 0, '.', ','),
                $reliefRate * 100
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
            return $this->skipStep($stepName, 'Blocked or insufficient surplus.');
        }

        $maxHolding = (float) ($waterfallConfig['premium_bonds_max'] ?? 50000);
        $minAge = (int) ($waterfallConfig['premium_bonds_min_age'] ?? 16);
        $age = $context['personal']['age'] ?? null;

        if ($age !== null && $age < $minAge) {
            return $this->skipStep($stepName, sprintf('Must be at least %d to hold Premium Bonds.', $minAge));
        }

        // Estimate current Premium Bonds holding (not tracked individually — allocate conservatively)
        $currentHolding = 0; // Would need to be populated from savings accounts if tracked
        $headroom = max(0, $maxHolding - $currentHolding);

        if ($headroom <= 0) {
            return $this->skipStep($stepName, 'Premium Bonds holding at maximum.');
        }

        // Allocate up to 20% of remaining surplus to Premium Bonds
        $desiredAllocation = min($remaining * 0.20, $headroom);
        $allocation = min($remaining, $desiredAllocation);

        if ($allocation < 25) {
            return $this->skipStep($stepName, 'Allocation too small for Premium Bonds (minimum £25).');
        }

        $trace = [];

        $trace[] = [
            'question' => 'Is the user eligible for Premium Bonds?',
            'data_field' => 'age',
            'data_value' => $age !== null ? (string) $age : 'Not set',
            'threshold' => 'At least '.$minAge,
            'passed' => true,
            'explanation' => 'Age requirement met for Premium Bonds.',
        ];

        $trace[] = [
            'question' => 'Is there headroom within the maximum Premium Bonds holding?',
            'data_field' => 'headroom',
            'data_value' => '£'.number_format($headroom, 0),
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => '£'.number_format($headroom, 0).' headroom available (maximum: £'.number_format($maxHolding, 0).').',
        ];

        $trace[] = [
            'question' => 'How much should be allocated to Premium Bonds?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '20% of remaining or headroom',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated (20% of remaining surplus, capped by headroom).',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Add to Premium Bonds',
            'explanation' => sprintf(
                'Premium Bonds offer tax-free prizes with a current prize fund rate. You can hold up to %s. They provide capital security with potential upside.',
                number_format($maxHolding, 0, '.', ',')
            ),
            'personal_context' => sprintf(
                'Suggested allocation: %s. Maximum holding: %s.',
                number_format($allocation, 0, '.', ','),
                number_format($maxHolding, 0, '.', ',')
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
            return $this->skipStep($stepName, 'Blocked or insufficient surplus.');
        }

        $allocationPercent = (float) ($waterfallConfig['nsi_allocation_percent'] ?? 0.10);
        $minimum = (float) ($waterfallConfig['nsi_minimum'] ?? 25);

        $allocation = $remaining * $allocationPercent;

        if ($allocation < $minimum) {
            return $this->skipStep($stepName, sprintf('Allocation below NS&I minimum of %s.', number_format($minimum, 0, '.', ',')));
        }

        $allocation = min($remaining, $allocation);

        $trace = [];

        $trace[] = [
            'question' => 'How much should be directed to NS&I products?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '£'.number_format($minimum, 0).' minimum, '.round($allocationPercent * 100).'% of surplus',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated ('.round($allocationPercent * 100).'% of £'.number_format($remaining, 0).' remaining). Government-backed capital security.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Consider NS&I savings products',
            'explanation' => 'NS&I products are backed by HM Treasury offering capital security. Income Bonds and Direct Saver provide competitive rates with government backing.',
            'personal_context' => sprintf(
                'Suggested allocation of %s (%.0f%% of remaining surplus) to NS&I products.',
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
            return $this->skipStep($stepName, 'Blocked or insufficient surplus.');
        }

        $minimum = (float) ($waterfallConfig['offshore_bond_minimum'] ?? 10000);
        $taxBand = $context['financial']['tax_band'] ?? 'basic';

        if (! in_array($taxBand, ['higher', 'additional'], true)) {
            return $this->skipStep($stepName, 'Offshore bonds most beneficial for higher or additional rate taxpayers.');
        }

        if ($remaining < $minimum) {
            return $this->skipStep($stepName, sprintf('Remaining surplus below offshore bond minimum of %s.', number_format($minimum, 0, '.', ',')));
        }

        // Allocate up to 30% of remaining for offshore bond
        $allocation = min($remaining, $remaining * 0.30);
        $allocation = max($minimum, $allocation);
        $allocation = min($remaining, $allocation);

        $trace = [];

        $trace[] = [
            'question' => 'Is the user a higher or additional rate taxpayer?',
            'data_field' => 'tax_band',
            'data_value' => $taxBand,
            'threshold' => 'Higher or additional',
            'passed' => true,
            'explanation' => ucfirst($taxBand).' rate taxpayer — offshore bond allows gross roll-up of investment growth.',
        ];

        $trace[] = [
            'question' => 'Does the remaining surplus meet the minimum for an offshore bond?',
            'data_field' => 'remaining',
            'data_value' => '£'.number_format($remaining, 0),
            'threshold' => '£'.number_format($minimum, 0),
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' surplus exceeds the £'.number_format($minimum, 0).' minimum.',
        ];

        $trace[] = [
            'question' => 'How much should be allocated to the offshore bond?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '30% of remaining surplus',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated with 5% annual tax-deferred withdrawal allowance.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Consider an offshore investment bond',
            'explanation' => sprintf(
                'As a %s rate taxpayer, an offshore bond allows investment growth to roll up gross (no internal tax). The 5%% annual tax-deferred withdrawal allowance provides flexible access.',
                $taxBand
            ),
            'personal_context' => sprintf(
                'Suggested allocation: %s. Tax band: %s rate. Minimum investment: %s.',
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
            return $this->skipStep($stepName, 'Blocked or insufficient surplus.');
        }

        $minimum = (float) ($waterfallConfig['onshore_bond_minimum'] ?? 5000);
        $taxBand = $context['financial']['tax_band'] ?? 'basic';

        if ($taxBand !== 'higher') {
            return $this->skipStep($stepName, 'Onshore bonds most beneficial for higher rate taxpayers with top-slicing relief.');
        }

        if ($remaining < $minimum) {
            return $this->skipStep($stepName, sprintf('Remaining surplus below onshore bond minimum of %s.', number_format($minimum, 0, '.', ',')));
        }

        $allocation = min($remaining, $remaining * 0.25);
        $allocation = max($minimum, $allocation);
        $allocation = min($remaining, $allocation);

        $trace = [];

        $trace[] = [
            'question' => 'Is the user a higher rate taxpayer who benefits from top-slicing relief?',
            'data_field' => 'tax_band',
            'data_value' => $taxBand,
            'threshold' => 'Higher',
            'passed' => true,
            'explanation' => 'Higher rate taxpayer — onshore bond with top-slicing relief can reduce effective tax rate.',
        ];

        $trace[] = [
            'question' => 'Does the remaining surplus meet the minimum for an onshore bond?',
            'data_field' => 'remaining',
            'data_value' => '£'.number_format($remaining, 0),
            'threshold' => '£'.number_format($minimum, 0),
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' surplus exceeds the £'.number_format($minimum, 0).' minimum.',
        ];

        $trace[] = [
            'question' => 'How much should be allocated to the onshore bond?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '25% of remaining surplus',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated with 5% annual tax-deferred withdrawal allowance and top-slicing relief.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Consider an onshore investment bond',
            'explanation' => 'Onshore bonds benefit from top-slicing relief — gains are spread across the years the bond is held, potentially reducing the effective tax rate. The 5% annual tax-deferred withdrawal applies.',
            'personal_context' => sprintf(
                'Suggested allocation: %s. Top-slicing relief is most beneficial if you expect to be a basic rate taxpayer in future.',
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
            return $this->skipStep($stepName, 'Blocked or insufficient surplus.');
        }

        $pensionRemaining = $context['allowances']['pension_remaining'] ?? 0;
        $grossIncome = $context['financial']['gross_income'] ?? 0;

        // Carry forward is only relevant if current year allowance is used
        // and there is unused allowance from prior years
        if ($pensionRemaining > 0) {
            return $this->skipStep($stepName, 'Current year pension allowance not yet exhausted — carry forward not needed.');
        }

        // Estimate carry forward availability (3 years of unused allowance)
        // In practice this requires prior year data — estimate conservatively
        $annualAllowance = $context['allowances']['pension_annual_allowance']
            ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
        $estimatedCarryForward = $annualAllowance * 0.5; // Conservative: assume 50% of one year unused

        if ($estimatedCarryForward <= 0) {
            return $this->skipStep($stepName, 'No estimated carry forward available.');
        }

        // Cannot exceed net relevant earnings
        $maxContribution = min($grossIncome, $estimatedCarryForward);
        $allocation = min($remaining, $maxContribution);

        if ($allocation < 1000) {
            return $this->skipStep($stepName, 'Carry forward allocation too small.');
        }

        $taxBand = $context['financial']['tax_band'] ?? 'basic';
        $reliefRate = match ($taxBand) {
            'additional' => 0.45,
            'higher' => 0.40,
            default => 0.20,
        };

        $trace = [];

        $trace[] = [
            'question' => 'Has the current year pension allowance been exhausted?',
            'data_field' => 'pension_remaining',
            'data_value' => '£'.number_format($pensionRemaining, 0),
            'threshold' => '£0',
            'passed' => true,
            'explanation' => 'Current year allowance is fully used — carry forward from prior years may be available.',
        ];

        $trace[] = [
            'question' => 'Is there estimated carry forward from prior tax years?',
            'data_field' => 'estimated_carry_forward',
            'data_value' => '£'.number_format($estimatedCarryForward, 0),
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => 'Estimated £'.number_format($estimatedCarryForward, 0).' carry forward available (conservative estimate of 50% of one year).',
        ];

        $trace[] = [
            'question' => 'How much can be contributed via carry forward?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '£'.number_format($maxContribution, 0).' (capped by income)',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' contribution with '.round($reliefRate * 100).'% tax relief. Requires verification against pension statements.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Use pension carry forward',
            'explanation' => sprintf(
                'You may have unused pension Annual Allowance from the previous 3 tax years. A lump sum contribution of %s would receive %.0f%% tax relief.',
                number_format($allocation, 0, '.', ','),
                $reliefRate * 100
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
            return $this->skipStep($stepName, 'Blocked or insufficient surplus.');
        }

        $maxPortfolioPercent = (float) ($waterfallConfig['vct_eis_seis_max_portfolio_percent'] ?? 0.10);
        $minAllocation = (float) ($waterfallConfig['vct_eis_seis_min_allocation'] ?? 1000);
        $disposableGate = (float) ($waterfallConfig['vct_eis_seis_disposable_gate'] ?? 0.10);

        $portfolioValue = $context['portfolio']['total_value'] ?? 0;
        $riskLevel = $context['risk']['risk_level'] ?? 'medium';

        // Only for experienced investors with higher risk tolerance
        if (! in_array($riskLevel, ['upper_medium', 'high'], true)) {
            return $this->skipStep($stepName, 'VCT/EIS/SEIS suitable for investors with upper-medium or high risk tolerance.');
        }

        // Cap at 10% of portfolio
        $maxFromPortfolio = $portfolioValue * $maxPortfolioPercent;

        // Also cap at a proportion of disposable income
        $disposableIncome = $context['financial']['disposable_income'] ?? 0;
        $maxFromDisposable = $disposableIncome * $disposableGate;

        $allocation = min($remaining, $maxFromPortfolio, $maxFromDisposable);

        if ($allocation < $minAllocation) {
            return $this->skipStep($stepName, sprintf('Allocation below minimum of %s for venture capital investments.', number_format($minAllocation, 0, '.', ',')));
        }

        $ventureConfig = $this->taxConfig->get('investment.venture_capital', []);
        $eisRelief = (float) ($ventureConfig['eis']['relief'] ?? 0.30);

        $trace = [];

        $trace[] = [
            'question' => 'Does the user have sufficient risk tolerance for venture capital investments?',
            'data_field' => 'risk_level',
            'data_value' => $riskLevel,
            'threshold' => 'Upper-medium or high',
            'passed' => true,
            'explanation' => ucfirst(str_replace('_', '-', $riskLevel)).' risk tolerance — suitable for venture capital exposure.',
        ];

        $trace[] = [
            'question' => 'How much can be allocated within portfolio and income limits?',
            'data_field' => 'allocation',
            'data_value' => '£'.number_format($allocation, 0),
            'threshold' => '£'.number_format($minAllocation, 0).' minimum, '.round($maxPortfolioPercent * 100).'% of portfolio cap',
            'passed' => true,
            'explanation' => '£'.number_format($allocation, 0).' allocated (capped at '.round($maxPortfolioPercent * 100).'% of £'.number_format($portfolioValue, 0).' portfolio). '.round($eisRelief * 100).'% income tax relief applies.',
        ];

        $step = $this->buildStep($stepName, $allocation, [
            'headline' => 'Consider Venture Capital Trust or Enterprise Investment Scheme',
            'explanation' => sprintf(
                'With a %s risk profile and portfolio of %s, a small allocation to Venture Capital Trust or Enterprise Investment Scheme investments provides %.0f%% income tax relief. These are illiquid and carry higher risk.',
                str_replace('_', '-', $riskLevel),
                number_format($portfolioValue, 0, '.', ','),
                $eisRelief * 100
            ),
            'personal_context' => sprintf(
                'Maximum suggested: %s (%.0f%% of portfolio value). Minimum holding period applies.',
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
            return $this->skipStep($stepName, 'No remaining surplus to allocate.');
        }

        $cgtExempt = $context['allowances']['cgt_annual_exempt'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT;
        $taxBand = $context['financial']['tax_band'] ?? 'basic';

        $cgtRate = match ($taxBand) {
            'higher', 'additional' => TaxDefaults::CGT_HIGHER_RATE,
            default => TaxDefaults::CGT_BASIC_RATE,
        };

        $trace = [];

        $trace[] = [
            'question' => 'Is there remaining surplus after all tax-efficient wrappers?',
            'data_field' => 'remaining',
            'data_value' => '£'.number_format($remaining, 0),
            'threshold' => 'More than £0',
            'passed' => true,
            'explanation' => '£'.number_format($remaining, 0).' remains after maximising ISA, pension, and other tax-efficient wrappers.',
        ];

        $trace[] = [
            'question' => 'What Capital Gains Tax rate applies to General Investment Account gains?',
            'data_field' => 'cgt_rate',
            'data_value' => round($cgtRate * 100).'%',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => ucfirst($taxBand).' rate taxpayer — gains above £'.number_format($cgtExempt, 0).' exemption taxed at '.round($cgtRate * 100).'%. Use accumulation units and index trackers to minimise distributions.',
        ];

        $step = $this->buildStep($stepName, $remaining, [
            'headline' => 'Invest remaining surplus in a General Investment Account',
            'explanation' => sprintf(
                'After maximising tax-efficient wrappers, the remaining %s can be invested in a General Investment Account. You have a %s annual Capital Gains Tax exemption and gains above this are taxed at %.0f%%.',
                number_format($remaining, 0, '.', ','),
                number_format($cgtExempt, 0, '.', ','),
                $cgtRate * 100
            ),
            'personal_context' => sprintf(
                'Consider tax-efficient funds (accumulation units, index trackers) to minimise taxable distributions. Annual Capital Gains Tax exemption: %s.',
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
