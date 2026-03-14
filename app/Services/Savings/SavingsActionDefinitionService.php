<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Constants\TaxDefaults;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\Mortgage;
use App\Models\SavingsActionDefinition;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Evaluates savings action definitions against user data
 * to produce configurable, database-driven recommendations.
 *
 * Mirrors InvestmentActionDefinitionService — each trigger condition
 * maps to one private evaluator method that checks the condition
 * and returns zero or more recommendations.
 */
class SavingsActionDefinitionService
{
    use FormatsCurrency;
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly PSACalculator $psaCalculator,
        private readonly FSCSAssessor $fscsAssessor,
        private readonly EmergencyFundCalculator $emergencyFundCalculator
    ) {}

    /**
     * Evaluate all enabled agent-sourced action definitions against analysis data.
     *
     * @return array{recommendations: array, total_count: int, high_priority_count: int}
     */
    public function evaluateAgentActions(
        array $savingsAnalysis,
        array $investmentAnalysis,
        Collection $savingsAccounts,
        Collection $investmentAccounts,
        int $userId
    ): array {
        $definitions = SavingsActionDefinition::getEnabledBySource('agent');
        $recommendations = [];
        $priority = 1;

        foreach ($definitions as $definition) {
            $results = $this->evaluateAgentTrigger(
                $definition,
                $savingsAnalysis,
                $investmentAnalysis,
                $savingsAccounts,
                $investmentAccounts,
                $userId,
                $priority
            );

            foreach ($results as $rec) {
                $recommendations[] = $rec;
                $priority++;
            }
        }

        $recommendations = $this->resolveConflicts($recommendations);

        return [
            'recommendations' => $recommendations,
            'total_count' => count($recommendations),
            'high_priority_count' => count(array_filter($recommendations, fn ($r) => ($r['priority'] ?? 999) <= 2)),
        ];
    }

    /**
     * Evaluate all enabled goal-sourced action definitions against linked goals.
     *
     * @return array Recommendations in standard format consumed by structureActions()
     */
    public function evaluateGoalActions(Collection $linkedGoals): array
    {
        $definitions = SavingsActionDefinition::getEnabledBySource('goal');
        $recommendations = [];

        foreach ($linkedGoals as $goal) {
            $progress = $goal['progress_percentage'] ?? 0;
            if ($progress >= 100) {
                continue;
            }

            foreach ($definitions as $definition) {
                $rec = $this->evaluateGoalTrigger($definition, $goal);
                if ($rec !== null) {
                    $recommendations[] = $rec;
                }
            }
        }

        return $recommendations;
    }

    /**
     * Look up the what_if_impact_type for a given action category.
     */
    public function getWhatIfImpactType(string $category): string
    {
        $definition = SavingsActionDefinition::where('category', $category)->first();

        return $definition?->what_if_impact_type ?? 'default';
    }

    // =========================================================================
    // Agent trigger dispatch
    // =========================================================================

    /**
     * Dispatch a single agent-sourced trigger to the appropriate evaluator.
     *
     * @return array List of recommendations (may be empty or contain multiple for per-account triggers)
     */
    private function evaluateAgentTrigger(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        array $investmentAnalysis,
        Collection $savingsAccounts,
        Collection $investmentAccounts,
        int $userId,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            // Data Readiness
            'missing_date_of_birth' => $this->evaluateMissingDOB($definition, $userId, $priority),
            'missing_income' => $this->evaluateMissingIncome($definition, $userId, $priority),
            'missing_expenditure' => $this->evaluateMissingExpenditure($definition, $userId, $priority),
            'missing_employment_status' => $this->evaluateMissingEmployment($definition, $userId, $priority),

            // Emergency Fund
            'emergency_fund_critical' => $this->evaluateEmergencyFundCritical($definition, $savingsAnalysis, $savingsAccounts, $userId, $config, $priority),
            'emergency_fund_low' => $this->evaluateEmergencyFundLow($definition, $savingsAnalysis, $savingsAccounts, $userId, $config, $priority),
            'emergency_fund_building' => $this->evaluateEmergencyFundBuilding($definition, $savingsAnalysis, $config, $priority),
            'emergency_fund_no_designated' => $this->evaluateEmergencyFundNoDesignated($definition, $savingsAccounts, $priority),
            'emergency_fund_excessive' => $this->evaluateEmergencyFundExcessive($definition, $savingsAnalysis, $config, $priority),

            // Tax Efficiency (PSA)
            'psa_breached' => $this->evaluatePSABreached($definition, $userId, $priority),
            'psa_approaching' => $this->evaluatePSAApproaching($definition, $userId, $priority),
            'psa_headroom_available' => $this->evaluatePSAHeadroomAvailable($definition, $userId, $priority),
            'cash_isa_recommended' => $this->evaluateCashISARecommended($definition, $savingsAnalysis, $userId, $priority),
            'cash_isa_not_needed' => $this->evaluateCashISANotNeeded($definition, $savingsAnalysis, $userId, $priority),
            'isa_allowance_remaining' => $this->evaluateISAAllowanceRemaining($definition, $savingsAnalysis, $config, $priority),

            // Rate Optimisation
            'rate_below_market' => $this->evaluateRateBelowMarket($definition, $savingsAnalysis, $savingsAccounts, $priority),
            'rate_significantly_below' => $this->evaluateRateSignificantlyBelow($definition, $savingsAnalysis, $savingsAccounts, $priority),
            'fixed_rate_maturing' => $this->evaluateFixedRateMaturing($definition, $savingsAccounts, $config, $priority),
            'promo_rate_expiring' => $this->evaluatePromoRateExpiring($definition, $savingsAccounts, $config, $priority),
            'rate_improvement_available' => $this->evaluateRateImprovementAvailable($definition, $savingsAnalysis, $savingsAccounts, $priority),
            'zero_rate_account' => $this->evaluateZeroRateAccount($definition, $savingsAccounts, $priority),

            // FSCS Protection
            'fscs_breach' => $this->evaluateFSCSBreach($definition, $savingsAccounts, $priority),
            'fscs_approaching' => $this->evaluateFSCSApproaching($definition, $savingsAccounts, $priority),

            // Debt vs Savings
            'debt_rate_exceeds_savings' => $this->evaluateDebtRateExceedsSavings($definition, $userId, $savingsAccounts, $priority),
            'mortgage_rate_comparison' => $this->evaluateMortgageRateComparison($definition, $userId, $savingsAccounts, $priority),

            // Cash vs Investment
            'surplus_above_emergency_fund' => $this->evaluateSurplusAboveEmergencyFund($definition, $savingsAnalysis, $config, $priority),
            'cash_drag_risk' => $this->evaluateCashDragRisk($definition, $savingsAnalysis, $investmentAnalysis, $config, $priority),
            'consider_stocks_shares_isa' => $this->evaluateConsiderStocksSharesISA($definition, $savingsAnalysis, $investmentAnalysis, $userId, $priority),
            'consider_pension_contribution' => $this->evaluateConsiderPensionContribution($definition, $savingsAnalysis, $userId, $priority),

            // Goal-Linked
            'goal_no_linked_account' => $this->evaluateGoalNoLinkedAccount($definition, $userId, $priority),
            'goal_underfunded' => $this->evaluateGoalUnderfunded($definition, $userId, $priority),
            'goal_off_track' => $this->evaluateGoalOffTrack($definition, $userId, $priority),
            'goal_no_contribution' => $this->evaluateGoalNoContribution($definition, $userId, $priority),
            'goal_deadline_approaching' => $this->evaluateGoalDeadlineApproaching($definition, $userId, $config, $priority),

            // Children's Savings
            'child_no_savings' => $this->evaluateChildNoSavings($definition, $userId, $savingsAccounts, $priority),
            'junior_isa_not_open' => $this->evaluateJuniorISANotOpen($definition, $userId, $savingsAccounts, $priority),
            'junior_isa_allowance_remaining' => $this->evaluateJuniorISAAllowanceRemaining($definition, $userId, $savingsAccounts, $priority),
            'child_approaching_18' => $this->evaluateChildApproaching18($definition, $userId, $priority),
            'child_savings_review' => $this->evaluateChildSavingsReview($definition, $userId, $savingsAccounts, $priority),

            // Spouse Coordination
            'spouse_psa_optimisation' => $this->evaluateSpousePSAOptimisation($definition, $userId, $priority),
            'spouse_isa_coordination' => $this->evaluateSpouseISACoordination($definition, $userId, $savingsAnalysis, $priority),

            default => [],
        };
    }

    // =========================================================================
    // Data Readiness evaluators (4)
    // =========================================================================

    /**
     * Missing date of birth: triggers when user has no DOB set.
     */
    private function evaluateMissingDOB(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user || $user->date_of_birth !== null) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    /**
     * Missing income: triggers when user has no income data set.
     */
    private function evaluateMissingIncome(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $grossIncome = $this->resolveGrossAnnualIncome($user);
        if ($grossIncome > 0) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    /**
     * Missing expenditure: triggers when user has no expenditure data.
     */
    private function evaluateMissingExpenditure(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $resolved = $this->resolveMonthlyExpenditure($user);
        if ($resolved['amount'] > 0) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    /**
     * Missing employment status: triggers when employment_status is not set.
     */
    private function evaluateMissingEmployment(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user || ! empty($user->employment_status)) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    // =========================================================================
    // Emergency Fund evaluators (5)
    // =========================================================================

    /**
     * Emergency fund critical: triggers when runway is below critical threshold.
     * Adjusts target based on employment status.
     */
    private function evaluateEmergencyFundCritical(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        Collection $savingsAccounts,
        int $userId,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 1);

        if ($runway >= $threshold) {
            return [];
        }

        // Cannot evaluate without expenditure data
        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        if ($monthlyExpenditure <= 0) {
            return [];
        }

        $user = User::find($userId);
        $targetMonths = $this->getTargetEmergencyMonths($user);
        $shortfallMonths = max(0, $targetMonths - $runway);
        $shortfallAmount = $shortfallMonths * $monthlyExpenditure;

        $vars = [
            'runway_months' => number_format($runway, 1),
            'target_months' => (string) $targetMonths,
            'shortfall' => $this->formatCurrency($shortfallAmount),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($shortfallAmount, 2);

        return [$rec];
    }

    /**
     * Emergency fund low: triggers when runway is below target but not critical.
     */
    private function evaluateEmergencyFundLow(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        Collection $savingsAccounts,
        int $userId,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $low = (float) ($config['low'] ?? 1);
        $high = (float) ($config['high'] ?? 3);

        if ($runway < $low || $runway >= $high) {
            return [];
        }

        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        if ($monthlyExpenditure <= 0) {
            return [];
        }

        $user = User::find($userId);
        $targetMonths = $this->getTargetEmergencyMonths($user);
        $shortfallMonths = max(0, $targetMonths - $runway);
        $monthlyTopUp = $this->emergencyFundCalculator->calculateMonthlyTopUp(
            $shortfallMonths * $monthlyExpenditure,
            12
        );

        $vars = [
            'runway_months' => number_format($runway, 1),
            'target_months' => (string) $targetMonths,
            'monthly_top_up' => $this->formatCurrency($monthlyTopUp),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Emergency fund building: triggers when runway is between low and target thresholds.
     * Provides encouragement and progress tracking.
     */
    private function evaluateEmergencyFundBuilding(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $low = (float) ($config['low'] ?? 3);
        $high = (float) ($config['high'] ?? 6);

        if ($runway < $low || $runway >= $high) {
            return [];
        }

        $adequacy = $savingsAnalysis['emergency_fund']['adequacy']['adequacy_score'] ?? 0;

        $vars = [
            'runway_months' => number_format($runway, 1),
            'adequacy_percent' => number_format($adequacy, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * No designated emergency fund: triggers when no account is flagged as emergency fund.
     */
    private function evaluateEmergencyFundNoDesignated(
        SavingsActionDefinition $definition,
        Collection $savingsAccounts,
        int $priority
    ): array {
        if ($savingsAccounts->isEmpty()) {
            return [];
        }

        $hasDesignated = $savingsAccounts->contains('is_emergency_fund', true);
        if ($hasDesignated) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    /**
     * Emergency fund excessive: triggers when runway significantly exceeds target.
     * Suggests deploying excess into more productive assets.
     */
    private function evaluateEmergencyFundExcessive(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 12);

        if ($runway < $threshold) {
            return [];
        }

        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        if ($monthlyExpenditure <= 0) {
            return [];
        }

        $targetMonths = 6;
        $excessMonths = $runway - $targetMonths;
        $excessAmount = $excessMonths * $monthlyExpenditure;

        $vars = [
            'runway_months' => number_format($runway, 1),
            'excess_amount' => $this->formatCurrency($excessAmount),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($excessAmount, 2);

        return [$rec];
    }

    // =========================================================================
    // Tax Efficiency (PSA) evaluators (6)
    // =========================================================================

    /**
     * PSA breached: triggers when annual interest exceeds Personal Savings Allowance.
     */
    private function evaluatePSABreached(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $psaPosition = $this->psaCalculator->assessPSAPosition($user);
        if (! $psaPosition['is_breached']) {
            return [];
        }

        $vars = [
            'breach_amount' => $this->formatCurrency($psaPosition['breach_amount']),
            'psa_amount' => $this->formatCurrency($psaPosition['psa_amount']),
            'annual_interest' => $this->formatCurrency($psaPosition['annual_interest']),
            'tax_band' => $psaPosition['tax_band'],
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        // Estimated tax payable on the breach
        $taxRate = $psaPosition['tax_band'] === 'higher' ? 0.40 : 0.20;
        $rec['estimated_impact'] = round($psaPosition['breach_amount'] * $taxRate, 2);

        return [$rec];
    }

    /**
     * PSA approaching: triggers when utilisation is high but not yet breached.
     */
    private function evaluatePSAApproaching(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $psaPosition = $this->psaCalculator->assessPSAPosition($user);
        if (! $psaPosition['is_approaching']) {
            return [];
        }

        $vars = [
            'utilisation_percent' => number_format($psaPosition['utilisation_percent'], 0),
            'headroom' => $this->formatCurrency($psaPosition['headroom']),
            'psa_amount' => $this->formatCurrency($psaPosition['psa_amount']),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * PSA headroom available: triggers when significant PSA headroom exists.
     * Suggests user could earn more interest without tax consequences.
     */
    private function evaluatePSAHeadroomAvailable(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $psaPosition = $this->psaCalculator->assessPSAPosition($user);

        // Only relevant if user has a meaningful PSA and significant headroom
        if ($psaPosition['psa_amount'] <= 0 || $psaPosition['utilisation_percent'] > 50) {
            return [];
        }

        // Must have some savings to make this useful
        $accounts = $user->savingsAccounts()->where('is_isa', false)->get();
        if ($accounts->isEmpty()) {
            return [];
        }

        $vars = [
            'headroom' => $this->formatCurrency($psaPosition['headroom']),
            'psa_amount' => $this->formatCurrency($psaPosition['psa_amount']),
            'utilisation_percent' => number_format($psaPosition['utilisation_percent'], 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Cash ISA recommended: triggers when PSA is breached or approaching,
     * and user does not already hold a Cash ISA.
     */
    private function evaluateCashISARecommended(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $psaPosition = $this->psaCalculator->assessPSAPosition($user);
        if (! $psaPosition['is_breached'] && ! $psaPosition['is_approaching']) {
            return [];
        }

        // Check if user already has a Cash ISA
        $hasCashIsa = $user->savingsAccounts()
            ->where('is_isa', true)
            ->where('isa_type', 'cash')
            ->exists();

        if ($hasCashIsa) {
            return [];
        }

        $isaAllowances = $this->taxConfig->getISAAllowances();
        $isaAllowance = $isaAllowances['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;

        $vars = [
            'isa_allowance' => $this->formatCurrency((float) $isaAllowance),
            'annual_interest' => $this->formatCurrency($psaPosition['annual_interest']),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Cash ISA not needed: triggers when PSA headroom is substantial
     * and user might benefit from non-ISA rates (which are often higher).
     */
    private function evaluateCashISANotNeeded(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $psaPosition = $this->psaCalculator->assessPSAPosition($user);

        // Only suggest ISA not needed when utilisation is very low
        if ($psaPosition['utilisation_percent'] > 25) {
            return [];
        }

        // Only relevant if user actually has a Cash ISA
        $hasCashIsa = $user->savingsAccounts()
            ->where('is_isa', true)
            ->where('isa_type', 'cash')
            ->exists();

        if (! $hasCashIsa) {
            return [];
        }

        $vars = [
            'utilisation_percent' => number_format($psaPosition['utilisation_percent'], 0),
            'psa_amount' => $this->formatCurrency($psaPosition['psa_amount']),
            'headroom' => $this->formatCurrency($psaPosition['headroom']),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * ISA allowance remaining: triggers when ISA allowance has not been fully used
     * and emergency fund is adequate.
     */
    private function evaluateISAAllowanceRemaining(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        array $config,
        int $priority
    ): array {
        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        if ($isaRemaining <= 0) {
            return [];
        }

        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $runwayThreshold = (float) ($config['threshold'] ?? 6);

        if ($runway < $runwayThreshold) {
            return [];
        }

        $vars = [
            'isa_remaining' => $this->formatCurrency($isaRemaining),
            'tax_year' => $this->taxConfig->getTaxYear(),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    // =========================================================================
    // Rate Optimisation evaluators (6)
    // =========================================================================

    /**
     * Rate below market: triggers per-account when rate is categorized as Fair or Poor.
     */
    private function evaluateRateBelowMarket(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $rateComparisons = $savingsAnalysis['rate_comparisons'] ?? [];
        $results = [];

        foreach ($rateComparisons as $comparison) {
            $rating = $comparison['comparison']['category'] ?? '';
            if ($rating !== 'Fair') {
                continue;
            }

            $account = $savingsAccounts->firstWhere('id', $comparison['account_id']);
            if (! $account) {
                continue;
            }

            $potentialGain = (float) ($comparison['potential_gain'] ?? 0);
            if ($potentialGain < 50) {
                continue;
            }

            $vars = [
                'account_name' => $account->account_name ?? 'Unknown Account',
                'current_rate' => number_format(((float) $account->interest_rate) * 100, 2),
                'market_rate' => number_format(($comparison['comparison']['market_rate'] ?? 0) * 100, 2),
                'potential_gain' => $this->formatCurrency($potentialGain),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['estimated_impact'] = round($potentialGain, 2);
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Rate significantly below market: triggers per-account when rate is categorized as Poor.
     */
    private function evaluateRateSignificantlyBelow(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $rateComparisons = $savingsAnalysis['rate_comparisons'] ?? [];
        $results = [];

        foreach ($rateComparisons as $comparison) {
            $rating = $comparison['comparison']['category'] ?? '';
            if ($rating !== 'Poor') {
                continue;
            }

            $account = $savingsAccounts->firstWhere('id', $comparison['account_id']);
            if (! $account) {
                continue;
            }

            $potentialGain = (float) ($comparison['potential_gain'] ?? 0);

            $vars = [
                'account_name' => $account->account_name ?? 'Unknown Account',
                'institution' => $account->institution ?? 'Unknown',
                'current_rate' => number_format(((float) $account->interest_rate) * 100, 2),
                'market_rate' => number_format(($comparison['comparison']['market_rate'] ?? 0) * 100, 2),
                'potential_gain' => $this->formatCurrency($potentialGain),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['estimated_impact'] = round($potentialGain, 2);
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Fixed rate maturing: triggers per-account when a fixed-rate account
     * is approaching maturity within the configured window.
     */
    private function evaluateFixedRateMaturing(
        SavingsActionDefinition $definition,
        Collection $savingsAccounts,
        array $config,
        int $priority
    ): array {
        $windowDays = (int) ($config['window_days'] ?? 90);
        $now = Carbon::now();
        $results = [];

        foreach ($savingsAccounts as $account) {
            if ($account->access_type !== 'fixed' || ! $account->maturity_date) {
                continue;
            }

            $daysToMaturity = (int) $now->diffInDays($account->maturity_date, false);
            if ($daysToMaturity < 0 || $daysToMaturity > $windowDays) {
                continue;
            }

            $vars = [
                'account_name' => $account->account_name ?? 'Unknown Account',
                'institution' => $account->institution ?? 'Unknown',
                'maturity_date' => $account->maturity_date->format('d M Y'),
                'days_remaining' => (string) $daysToMaturity,
                'balance' => $this->formatCurrency((float) $account->current_balance),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Promo rate expiring: triggers per-account when a promotional rate end date
     * is approaching within the configured window.
     */
    private function evaluatePromoRateExpiring(
        SavingsActionDefinition $definition,
        Collection $savingsAccounts,
        array $config,
        int $priority
    ): array {
        $windowDays = (int) ($config['window_days'] ?? 60);
        $now = Carbon::now();
        $results = [];

        foreach ($savingsAccounts as $account) {
            if (! $account->promo_rate_end_date) {
                continue;
            }

            $daysToExpiry = (int) $now->diffInDays($account->promo_rate_end_date, false);
            if ($daysToExpiry < 0 || $daysToExpiry > $windowDays) {
                continue;
            }

            $vars = [
                'account_name' => $account->account_name ?? 'Unknown Account',
                'institution' => $account->institution ?? 'Unknown',
                'expiry_date' => $account->promo_rate_end_date->format('d M Y'),
                'days_remaining' => (string) $daysToExpiry,
                'balance' => $this->formatCurrency((float) $account->current_balance),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Rate improvement available: triggers when total potential gain
     * across all accounts exceeds a meaningful threshold.
     */
    private function evaluateRateImprovementAvailable(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $rateComparisons = $savingsAnalysis['rate_comparisons'] ?? [];

        $totalGain = collect($rateComparisons)
            ->where('comparison.is_competitive', false)
            ->sum('potential_gain');

        if ($totalGain < 100) {
            return [];
        }

        $uncompetitiveCount = collect($rateComparisons)
            ->where('comparison.is_competitive', false)
            ->count();

        $vars = [
            'total_potential_gain' => $this->formatCurrency($totalGain),
            'account_count' => (string) $uncompetitiveCount,
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($totalGain, 2);

        return [$rec];
    }

    /**
     * Zero rate account: triggers per-account when interest rate is 0%.
     */
    private function evaluateZeroRateAccount(
        SavingsActionDefinition $definition,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $results = [];

        foreach ($savingsAccounts as $account) {
            $rate = (float) ($account->interest_rate ?? 0);
            $balance = (float) ($account->current_balance ?? 0);

            if ($rate > 0 || $balance <= 0) {
                continue;
            }

            $vars = [
                'account_name' => $account->account_name ?? 'Unknown Account',
                'institution' => $account->institution ?? 'Unknown',
                'balance' => $this->formatCurrency($balance),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $results[] = $rec;
        }

        return $results;
    }

    // =========================================================================
    // FSCS Protection evaluators (2)
    // =========================================================================

    /**
     * FSCS breach: triggers per-institution when deposits exceed FSCS protection limit.
     */
    private function evaluateFSCSBreach(
        SavingsActionDefinition $definition,
        Collection $savingsAccounts,
        int $priority
    ): array {
        if ($savingsAccounts->isEmpty()) {
            return [];
        }

        $exposure = $this->fscsAssessor->assessExposure($savingsAccounts);
        if (! $exposure['has_breach']) {
            return [];
        }

        $results = [];
        foreach ($exposure['institution_groups'] as $group) {
            if (! $group['is_breach']) {
                continue;
            }

            $vars = [
                'institution' => $group['institution_group'],
                'total_balance' => $this->formatCurrency($group['total_balance']),
                'fscs_limit' => $this->formatCurrency($group['fscs_limit']),
                'excess' => $this->formatCurrency($group['excess']),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['estimated_impact'] = round($group['excess'], 2);
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * FSCS approaching: triggers per-institution when deposits are nearing the FSCS limit.
     */
    private function evaluateFSCSApproaching(
        SavingsActionDefinition $definition,
        Collection $savingsAccounts,
        int $priority
    ): array {
        if ($savingsAccounts->isEmpty()) {
            return [];
        }

        $exposure = $this->fscsAssessor->assessExposure($savingsAccounts);
        if (! $exposure['has_approaching']) {
            return [];
        }

        $results = [];
        foreach ($exposure['institution_groups'] as $group) {
            if (! $group['is_approaching']) {
                continue;
            }

            $vars = [
                'institution' => $group['institution_group'],
                'total_balance' => $this->formatCurrency($group['total_balance']),
                'fscs_limit' => $this->formatCurrency($group['fscs_limit']),
                'headroom' => $this->formatCurrency($group['headroom']),
            ];

            $results[] = $this->buildRecommendation($definition, $vars, $priority);
        }

        return $results;
    }

    // =========================================================================
    // Debt vs Savings evaluators (2)
    // =========================================================================

    /**
     * Debt rate exceeds savings: triggers when user has high-interest debts
     * that cost more than savings accounts earn.
     */
    private function evaluateDebtRateExceedsSavings(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        int $priority
    ): array {
        if ($savingsAccounts->isEmpty()) {
            return [];
        }

        $user = User::with('mortgages')->find($userId);
        if (! $user) {
            return [];
        }

        // Check mortgages for high-rate debt comparison
        $mortgages = $user->mortgages;
        if ($mortgages->isEmpty()) {
            return [];
        }

        $bestSavingsRate = $savingsAccounts->max('interest_rate');
        $bestSavingsRate = (float) ($bestSavingsRate ?? 0);

        // Find any mortgage where overpayment could save more than savings interest
        $highRateMortgage = $mortgages->first(function ($mortgage) use ($bestSavingsRate) {
            $mortgageRate = (float) ($mortgage->interest_rate ?? 0);

            return $mortgageRate > $bestSavingsRate && $mortgageRate > 0;
        });

        if (! $highRateMortgage) {
            return [];
        }

        $mortgageRate = (float) $highRateMortgage->interest_rate;
        $rateDifference = $mortgageRate - $bestSavingsRate;

        $vars = [
            'mortgage_rate' => number_format($mortgageRate * 100, 2),
            'savings_rate' => number_format($bestSavingsRate * 100, 2),
            'rate_difference' => number_format($rateDifference * 100, 2),
            'lender' => $highRateMortgage->lender_name ?? 'your mortgage lender',
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Mortgage rate comparison: triggers when savings above emergency fund
     * could be better used for mortgage overpayments.
     */
    private function evaluateMortgageRateComparison(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $user = User::with('mortgages')->find($userId);
        if (! $user || $user->mortgages->isEmpty()) {
            return [];
        }

        // Only trigger if user has surplus above emergency fund levels
        $emergencyFundAccounts = $savingsAccounts->where('is_emergency_fund', true);
        $nonEmergencyBalance = $savingsAccounts->where('is_emergency_fund', false)->sum('current_balance');

        if ($nonEmergencyBalance <= 0) {
            return [];
        }

        $highestMortgageRate = $user->mortgages->max('interest_rate');
        $highestMortgageRate = (float) ($highestMortgageRate ?? 0);
        $averageSavingsRate = $savingsAccounts->avg('interest_rate');
        $averageSavingsRate = (float) ($averageSavingsRate ?? 0);

        // Only trigger if mortgage rate meaningfully exceeds savings rate
        if ($highestMortgageRate <= $averageSavingsRate + 0.005) {
            return [];
        }

        $vars = [
            'mortgage_rate' => number_format($highestMortgageRate * 100, 2),
            'average_savings_rate' => number_format($averageSavingsRate * 100, 2),
            'non_emergency_balance' => $this->formatCurrency($nonEmergencyBalance),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    // =========================================================================
    // Cash vs Investment evaluators (4)
    // =========================================================================

    /**
     * Surplus above emergency fund: triggers when savings significantly
     * exceed emergency fund target, suggesting investment consideration.
     */
    private function evaluateSurplusAboveEmergencyFund(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $targetMonths = (float) ($config['target_months'] ?? 6);
        $surplusThreshold = (float) ($config['surplus_threshold'] ?? 5000);

        if ($runway <= $targetMonths) {
            return [];
        }

        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $targetAmount = $monthlyExpenditure * $targetMonths;
        $surplus = $totalSavings - $targetAmount;

        if ($surplus < $surplusThreshold) {
            return [];
        }

        $vars = [
            'surplus_amount' => $this->formatCurrency($surplus),
            'target_amount' => $this->formatCurrency($targetAmount),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($surplus, 2);

        return [$rec];
    }

    /**
     * Cash drag risk: triggers when significant savings are held in cash
     * while investment accounts exist but could benefit from more funding.
     */
    private function evaluateCashDragRisk(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        array $investmentAnalysis,
        array $config,
        int $priority
    ): array {
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 50000);

        if ($totalSavings < $threshold) {
            return [];
        }

        // Only trigger if user has investment accounts
        $investmentCount = $investmentAnalysis['portfolio_summary']['accounts_count'] ?? 0;
        if ($investmentCount === 0) {
            return [];
        }

        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $emergencyTarget = $monthlyExpenditure * 6;
        $surplus = $totalSavings - $emergencyTarget;

        if ($surplus < $threshold * 0.5) {
            return [];
        }

        $vars = [
            'surplus_amount' => $this->formatCurrency($surplus),
            'total_savings' => $this->formatCurrency($totalSavings),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Consider Stocks & Shares ISA: triggers when user has ISA allowance remaining
     * and surplus cash, but no Stocks & Shares ISA.
     */
    private function evaluateConsiderStocksSharesISA(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        array $investmentAnalysis,
        int $userId,
        int $priority
    ): array {
        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        if ($isaRemaining <= 0) {
            return [];
        }

        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        if ($runway < 6) {
            return [];
        }

        // Check if user already has a Stocks & Shares ISA
        $hasStocksSharesIsa = $investmentAnalysis['tax_wrappers']['has_isa'] ?? false;
        if ($hasStocksSharesIsa) {
            return [];
        }

        $vars = [
            'isa_remaining' => $this->formatCurrency($isaRemaining),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Consider pension contribution: triggers when surplus exists and pension
     * allowance is available, offering tax relief benefit.
     */
    private function evaluateConsiderPensionContribution(
        SavingsActionDefinition $definition,
        array $savingsAnalysis,
        int $userId,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        if ($runway < 6) {
            return [];
        }

        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $grossIncome = $this->resolveGrossAnnualIncome($user);
        if ($grossIncome <= 0) {
            return [];
        }

        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $surplus = $totalSavings - ($monthlyExpenditure * 6);

        if ($surplus < 5000) {
            return [];
        }

        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = (float) ($pensionAllowances['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE);
        $pensionAmount = min($surplus, $annualAllowance);

        $vars = [
            'pension_amount' => $this->formatCurrency($pensionAmount),
            'annual_allowance' => $this->formatCurrency($annualAllowance),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        // Estimate basic rate tax relief
        $rec['estimated_impact'] = round($pensionAmount * 0.20, 2);

        return [$rec];
    }

    // =========================================================================
    // Goal-Linked evaluators (5)
    // =========================================================================

    /**
     * Goal has no linked savings account: triggers when active savings goals
     * exist but are not linked to any savings account.
     */
    private function evaluateGoalNoLinkedAccount(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $goals = Goal::where('user_id', $userId)
            ->where('assigned_module', 'savings')
            ->where('status', 'active')
            ->get();

        $results = [];
        foreach ($goals as $goal) {
            // Check both the legacy FK and the new pivot table
            $hasLinkedAccount = $goal->linked_savings_account_id !== null
                || $goal->savingsAccounts()->exists();

            if ($hasLinkedAccount) {
                continue;
            }

            $vars = [
                'goal_name' => $goal->goal_name ?? 'Unnamed goal',
                'target_amount' => $this->formatCurrency((float) ($goal->target_amount ?? 0)),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Goal underfunded: triggers when linked account balance is significantly
     * below the goal's allocated amount.
     */
    private function evaluateGoalUnderfunded(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $goals = Goal::where('user_id', $userId)
            ->where('assigned_module', 'savings')
            ->where('status', 'active')
            ->with('savingsAccounts')
            ->get();

        $results = [];
        foreach ($goals as $goal) {
            $targetAmount = (float) ($goal->target_amount ?? 0);
            $currentAmount = (float) ($goal->current_amount ?? 0);

            if ($targetAmount <= 0 || $currentAmount >= $targetAmount * 0.5) {
                continue;
            }

            $shortfall = $targetAmount - $currentAmount;
            $progress = $goal->progress_percentage;

            $vars = [
                'goal_name' => $goal->goal_name ?? 'Unnamed goal',
                'progress' => number_format($progress, 0),
                'shortfall' => $this->formatCurrency($shortfall),
                'target_amount' => $this->formatCurrency($targetAmount),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $rec['estimated_impact'] = round($shortfall, 2);
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Goal off track: triggers when a savings goal is not on track
     * to meet its target date.
     */
    private function evaluateGoalOffTrack(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $goals = Goal::where('user_id', $userId)
            ->where('assigned_module', 'savings')
            ->where('status', 'active')
            ->get();

        $results = [];
        foreach ($goals as $goal) {
            if ($goal->is_on_track || $goal->progress_percentage >= 100) {
                continue;
            }

            // Skip goals with no contribution (caught by goal_no_contribution)
            $monthlyContribution = (float) ($goal->monthly_contribution ?? 0);
            if ($monthlyContribution <= 0) {
                continue;
            }

            $required = $goal->required_monthly_contribution;
            $shortfall = max(0, $required - $monthlyContribution);

            $vars = [
                'goal_name' => $goal->goal_name ?? 'Unnamed goal',
                'progress' => number_format($goal->progress_percentage, 0),
                'shortfall' => $this->formatCurrency($shortfall),
                'required_monthly' => $this->formatCurrency($required),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Goal no contribution: triggers when an active savings goal has
     * no monthly contribution set.
     */
    private function evaluateGoalNoContribution(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $goals = Goal::where('user_id', $userId)
            ->where('assigned_module', 'savings')
            ->where('status', 'active')
            ->get();

        $results = [];
        foreach ($goals as $goal) {
            $monthlyContribution = (float) ($goal->monthly_contribution ?? 0);
            $required = $goal->required_monthly_contribution;

            if ($monthlyContribution > 0 || $required <= 0) {
                continue;
            }

            if ($goal->progress_percentage >= 100) {
                continue;
            }

            $vars = [
                'goal_name' => $goal->goal_name ?? 'Unnamed goal',
                'required_monthly' => $this->formatCurrency($required),
                'target_amount' => $this->formatCurrency((float) ($goal->target_amount ?? 0)),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Goal deadline approaching: triggers when target date is near
     * and progress is below expected level.
     */
    private function evaluateGoalDeadlineApproaching(
        SavingsActionDefinition $definition,
        int $userId,
        array $config,
        int $priority
    ): array {
        $monthsThreshold = (int) ($config['months_threshold'] ?? 6);
        $progressThreshold = (float) ($config['progress_threshold'] ?? 75);

        $goals = Goal::where('user_id', $userId)
            ->where('assigned_module', 'savings')
            ->where('status', 'active')
            ->get();

        $results = [];
        foreach ($goals as $goal) {
            $monthsRemaining = $goal->months_remaining;
            $progress = $goal->progress_percentage;

            if ($monthsRemaining > $monthsThreshold || $progress >= $progressThreshold) {
                continue;
            }

            if ($progress >= 100) {
                continue;
            }

            $vars = [
                'goal_name' => $goal->goal_name ?? 'Unnamed goal',
                'months_remaining' => (string) $monthsRemaining,
                'progress' => number_format($progress, 0),
                'target_amount' => $this->formatCurrency((float) ($goal->target_amount ?? 0)),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $results[] = $rec;
        }

        return $results;
    }

    // =========================================================================
    // Children's Savings evaluators (5)
    // =========================================================================

    /**
     * Child has no savings: triggers when user has dependent children
     * but no savings accounts linked to them.
     */
    private function evaluateChildNoSavings(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $children = $this->getMinorChildren($userId);
        if ($children->isEmpty()) {
            return [];
        }

        $results = [];
        foreach ($children as $child) {
            $hasAccount = $savingsAccounts->contains('beneficiary_id', $child->id);
            if ($hasAccount) {
                continue;
            }

            $vars = [
                'child_name' => $child->first_name ?? $child->name ?? 'your child',
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Junior ISA not open: triggers when user has children under 18
     * but no Junior ISA accounts.
     */
    private function evaluateJuniorISANotOpen(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $children = $this->getMinorChildren($userId);
        if ($children->isEmpty()) {
            return [];
        }

        $results = [];
        foreach ($children as $child) {
            $hasJISA = $savingsAccounts
                ->where('beneficiary_id', $child->id)
                ->where('isa_type', 'junior')
                ->isNotEmpty();

            if ($hasJISA) {
                continue;
            }

            $isaAllowances = $this->taxConfig->getISAAllowances();
            $jisaAllowance = (float) ($isaAllowances['junior_isa']['annual_allowance'] ?? TaxDefaults::JISA_ALLOWANCE);

            $vars = [
                'child_name' => $child->first_name ?? $child->name ?? 'your child',
                'jisa_allowance' => $this->formatCurrency($jisaAllowance),
            ];

            $results[] = $this->buildRecommendation($definition, $vars, $priority);
        }

        return $results;
    }

    /**
     * Junior ISA allowance remaining: triggers per-child when JISA subscription
     * has not been fully utilised for the current tax year.
     */
    private function evaluateJuniorISAAllowanceRemaining(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $children = $this->getMinorChildren($userId);
        if ($children->isEmpty()) {
            return [];
        }

        $isaAllowances = $this->taxConfig->getISAAllowances();
        $jisaAllowance = (float) ($isaAllowances['junior_isa']['annual_allowance'] ?? TaxDefaults::JISA_ALLOWANCE);
        $taxYear = $this->taxConfig->getTaxYear();

        $results = [];
        foreach ($children as $child) {
            $jisaAccounts = $savingsAccounts
                ->where('beneficiary_id', $child->id)
                ->where('isa_type', 'junior');

            if ($jisaAccounts->isEmpty()) {
                continue;
            }

            $subscriptionUsed = $jisaAccounts
                ->where('isa_subscription_year', $taxYear)
                ->sum('isa_subscription_amount');

            $remaining = $jisaAllowance - (float) $subscriptionUsed;
            if ($remaining <= 0) {
                continue;
            }

            $vars = [
                'child_name' => $child->first_name ?? $child->name ?? 'your child',
                'jisa_remaining' => $this->formatCurrency($remaining),
                'jisa_allowance' => $this->formatCurrency($jisaAllowance),
                'tax_year' => $taxYear,
            ];

            $results[] = $this->buildRecommendation($definition, $vars, $priority);
        }

        return $results;
    }

    /**
     * Child approaching 18: triggers when a child is within 12 months
     * of turning 18 and has a Junior ISA.
     */
    private function evaluateChildApproaching18(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $children = FamilyMember::where('user_id', $userId)
            ->where('relationship', 'child')
            ->where('is_dependent', true)
            ->whereNotNull('date_of_birth')
            ->get();

        if ($children->isEmpty()) {
            return [];
        }

        $now = Carbon::now();
        $results = [];

        foreach ($children as $child) {
            $age = $child->date_of_birth->diffInYears($now);
            $monthsTo18 = (int) $child->date_of_birth->addYears(18)->diffInMonths($now, false);

            // Only trigger when within 12 months of turning 18
            if ($age >= 18 || $monthsTo18 > 0) {
                continue;
            }

            $monthsRemaining = abs($monthsTo18);
            if ($monthsRemaining > 12) {
                continue;
            }

            $vars = [
                'child_name' => $child->first_name ?? $child->name ?? 'your child',
                'months_to_18' => (string) $monthsRemaining,
                'turning_18_date' => $child->date_of_birth->addYears(18)->format('d M Y'),
            ];

            $results[] = $this->buildRecommendation($definition, $vars, $priority);
        }

        return $results;
    }

    /**
     * Children's savings review: triggers annually when user has children
     * with savings accounts to encourage a periodic review.
     */
    private function evaluateChildSavingsReview(
        SavingsActionDefinition $definition,
        int $userId,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $children = $this->getMinorChildren($userId);
        if ($children->isEmpty()) {
            return [];
        }

        $childAccounts = $savingsAccounts->whereNotNull('beneficiary_id');
        if ($childAccounts->isEmpty()) {
            return [];
        }

        $totalChildSavings = $childAccounts->sum('current_balance');

        $vars = [
            'child_account_count' => (string) $childAccounts->count(),
            'total_child_savings' => $this->formatCurrency((float) $totalChildSavings),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    // =========================================================================
    // Spouse Coordination evaluators (2)
    // =========================================================================

    /**
     * Spouse PSA optimisation: triggers when the primary user has breached or
     * is approaching their PSA, and the spouse has headroom.
     */
    private function evaluateSpousePSAOptimisation(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user || ! $user->spouse_id) {
            return [];
        }

        $spouse = User::find($user->spouse_id);
        if (! $spouse) {
            return [];
        }

        $userPsa = $this->psaCalculator->assessPSAPosition($user);
        $spousePsa = $this->psaCalculator->assessPSAPosition($spouse);

        // Only trigger if user has PSA pressure and spouse has headroom
        if (! $userPsa['is_breached'] && ! $userPsa['is_approaching']) {
            return [];
        }

        if ($spousePsa['utilisation_percent'] > 50) {
            return [];
        }

        $vars = [
            'user_utilisation' => number_format($userPsa['utilisation_percent'], 0),
            'spouse_headroom' => $this->formatCurrency($spousePsa['headroom']),
            'spouse_psa' => $this->formatCurrency($spousePsa['psa_amount']),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Spouse ISA coordination: triggers when ISA allowance across the household
     * could be better utilised by coordinating between spouses.
     */
    private function evaluateSpouseISACoordination(
        SavingsActionDefinition $definition,
        int $userId,
        array $savingsAnalysis,
        int $priority
    ): array {
        $user = User::find($userId);
        if (! $user || ! $user->spouse_id) {
            return [];
        }

        $userIsaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        if ($userIsaRemaining <= 0) {
            return [];
        }

        // Check if spouse also has remaining ISA allowance
        $isaAllowances = $this->taxConfig->getISAAllowances();
        $totalAllowance = (float) ($isaAllowances['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);

        // Estimate spouse ISA usage from their savings accounts
        $spouseIsaUsed = (float) \App\Models\SavingsAccount::where('user_id', $user->spouse_id)
            ->where('is_isa', true)
            ->where('isa_subscription_year', $this->taxConfig->getTaxYear())
            ->sum('isa_subscription_amount');

        $spouseIsaRemaining = max(0, $totalAllowance - $spouseIsaUsed);
        $combinedRemaining = $userIsaRemaining + $spouseIsaRemaining;

        // Only trigger if combined remaining is meaningful
        if ($combinedRemaining < 5000) {
            return [];
        }

        $vars = [
            'user_isa_remaining' => $this->formatCurrency($userIsaRemaining),
            'spouse_isa_remaining' => $this->formatCurrency($spouseIsaRemaining),
            'combined_remaining' => $this->formatCurrency($combinedRemaining),
            'tax_year' => $this->taxConfig->getTaxYear(),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    // =========================================================================
    // Goal trigger dispatch (goal-sourced definitions)
    // =========================================================================

    /**
     * Dispatch a single goal-sourced trigger to the appropriate evaluator.
     */
    private function evaluateGoalTrigger(SavingsActionDefinition $definition, array $goal): ?array
    {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'linked_goal_no_monthly_contribution' => $this->evaluateLinkedGoalNoContribution($definition, $goal),
            'linked_goal_off_track' => $this->evaluateLinkedGoalOffTrack($definition, $goal),
            'goal_months_remaining_below_and_progress_below' => $this->evaluateLinkedGoalDeadline($definition, $goal, $config),
            'linked_goal_underfunded' => $this->evaluateLinkedGoalUnderfunded($definition, $goal),
            'linked_goal_nearly_complete' => $this->evaluateLinkedGoalNearlyComplete($definition, $goal, $config),
            default => null,
        };
    }

    /**
     * Goal no contribution: triggers when monthly contribution is zero but required > 0.
     */
    private function evaluateLinkedGoalNoContribution(SavingsActionDefinition $definition, array $goal): ?array
    {
        $monthlyContribution = $goal['monthly_contribution'] ?? 0;
        $required = $goal['required_monthly_contribution'] ?? 0;

        if ($monthlyContribution > 0 || $required <= 0) {
            return null;
        }

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
            'required_monthly' => $this->formatCurrency((float) $required),
            'target_amount' => $this->formatCurrency((float) ($goal['target_amount'] ?? 0)),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
        ];
    }

    /**
     * Goal off track: triggers when goal is_on_track is false and has contributions.
     */
    private function evaluateLinkedGoalOffTrack(SavingsActionDefinition $definition, array $goal): ?array
    {
        $monthlyContribution = $goal['monthly_contribution'] ?? 0;

        // Skip if no contribution (caught by no-contribution check)
        if ($monthlyContribution <= 0) {
            return null;
        }

        if ($goal['is_on_track'] ?? true) {
            return null;
        }

        $required = $goal['required_monthly_contribution'] ?? 0;
        $shortfall = max(0, $required - $monthlyContribution);
        $progress = $goal['progress_percentage'] ?? 0;

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
            'progress' => number_format((float) $progress, 0),
            'shortfall' => $this->formatCurrency((float) $shortfall),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
        ];
    }

    /**
     * Goal deadline approaching: triggers when months remaining and progress below thresholds.
     */
    private function evaluateLinkedGoalDeadline(SavingsActionDefinition $definition, array $goal, array $config): ?array
    {
        // Only triggers for goals that are otherwise on-track
        if (! ($goal['is_on_track'] ?? true)) {
            return null;
        }

        $monthsRemaining = $goal['months_remaining'] ?? 0;
        $progress = $goal['progress_percentage'] ?? 0;
        $monthsThreshold = (int) ($config['months_threshold'] ?? 6);
        $progressThreshold = (float) ($config['progress_threshold'] ?? 75);

        if ($monthsRemaining > $monthsThreshold || $progress >= $progressThreshold) {
            return null;
        }

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
            'progress' => number_format((float) $progress, 0),
            'months_remaining' => (string) $monthsRemaining,
            'target_amount' => $this->formatCurrency((float) ($goal['target_amount'] ?? 0)),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
        ];
    }

    /**
     * Goal underfunded: triggers when progress is below 25% and target date is set.
     */
    private function evaluateLinkedGoalUnderfunded(SavingsActionDefinition $definition, array $goal): ?array
    {
        $progress = $goal['progress_percentage'] ?? 0;
        $targetAmount = $goal['target_amount'] ?? 0;

        if ($progress >= 25 || $targetAmount <= 0) {
            return null;
        }

        $currentAmount = $goal['current_amount'] ?? 0;
        $shortfall = max(0, $targetAmount - $currentAmount);

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
            'progress' => number_format((float) $progress, 0),
            'shortfall' => $this->formatCurrency((float) $shortfall),
            'target_amount' => $this->formatCurrency((float) $targetAmount),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
        ];
    }

    /**
     * Goal nearly complete: triggers when goal progress is above threshold.
     * Provides encouragement and suggests next steps.
     */
    private function evaluateLinkedGoalNearlyComplete(SavingsActionDefinition $definition, array $goal, array $config): ?array
    {
        $progress = $goal['progress_percentage'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 90);

        if ($progress < $threshold || $progress >= 100) {
            return null;
        }

        $targetAmount = $goal['target_amount'] ?? 0;
        $currentAmount = $goal['current_amount'] ?? 0;
        $remaining = max(0, $targetAmount - $currentAmount);

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
            'progress' => number_format((float) $progress, 0),
            'remaining' => $this->formatCurrency((float) $remaining),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
        ];
    }

    // =========================================================================
    // Conflict resolution
    // =========================================================================

    /**
     * Resolve conflicts between mutually exclusive recommendations.
     *
     * - Remove emergency_fund_building if emergency_fund_critical or emergency_fund_low fires
     * - Remove cash_isa_not_needed if cash_isa_recommended fires
     * - Remove psa_approaching if psa_breached fires
     * - Remove fscs_approaching if fscs_breach fires for same institution
     */
    private function resolveConflicts(array $recommendations): array
    {
        $keys = array_column($recommendations, 'definition_key');

        // Emergency fund: critical/low supersedes building
        $hasCritical = in_array('emergency_fund_critical', $keys, true);
        $hasLow = in_array('emergency_fund_low', $keys, true);
        if ($hasCritical || $hasLow) {
            $recommendations = array_values(array_filter(
                $recommendations,
                fn ($r) => ($r['definition_key'] ?? '') !== 'emergency_fund_building'
            ));
        }

        // Cash ISA: recommended supersedes not_needed
        if (in_array('cash_isa_recommended', $keys, true)) {
            $recommendations = array_values(array_filter(
                $recommendations,
                fn ($r) => ($r['definition_key'] ?? '') !== 'cash_isa_not_needed'
            ));
        }

        // PSA: breached supersedes approaching
        if (in_array('psa_breached', $keys, true)) {
            $recommendations = array_values(array_filter(
                $recommendations,
                fn ($r) => ($r['definition_key'] ?? '') !== 'psa_approaching'
            ));
        }

        // FSCS: breach supersedes approaching for same institution
        $breachInstitutions = collect($recommendations)
            ->where('definition_key', 'fscs_breach')
            ->pluck('account_name')
            ->filter()
            ->toArray();

        if (! empty($breachInstitutions)) {
            $recommendations = array_values(array_filter(
                $recommendations,
                function ($r) use ($breachInstitutions) {
                    if (($r['definition_key'] ?? '') !== 'fscs_approaching') {
                        return true;
                    }

                    // Only remove if same institution has a breach
                    return ! in_array($r['account_name'] ?? '', $breachInstitutions, true);
                }
            ));
        }

        return $recommendations;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a standard recommendation array from a definition and template variables.
     */
    private function buildRecommendation(
        SavingsActionDefinition $definition,
        array $vars,
        int $priority
    ): array {
        return [
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'Review your savings strategy',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'definition_key' => $definition->key,
        ];
    }

    /**
     * Get target emergency fund months based on employment status.
     *
     * Self-employed and contractors should hold more months of reserves.
     *
     * NOTE: Savings uses employment-specific targets here (mirroring
     * EmergencyFundCalculator::getTargetMonths()). Investment uses a
     * 6-month universal baseline via PlanConfigService::getEmergencyFundTargetMonths().
     * This divergence is intentional — savings recommendations personalise the
     * emergency fund target based on employment stability, while investment
     * surplus calculations use a conservative universal floor.
     */
    private function getTargetEmergencyMonths(?User $user): int
    {
        if (! $user || empty($user->employment_status)) {
            return 6;
        }

        return match ($user->employment_status) {
            'self_employed', 'self-employed' => 9,
            'contractor', 'freelance' => 9,
            'unemployed', 'seeking_employment' => 6,
            'retired' => 6,
            default => 6,
        };
    }

    /**
     * Get minor children (under 18) for a user.
     */
    private function getMinorChildren(int $userId): Collection
    {
        $now = Carbon::now();

        return FamilyMember::where('user_id', $userId)
            ->where('relationship', 'child')
            ->where('is_dependent', true)
            ->whereNotNull('date_of_birth')
            ->get()
            ->filter(fn ($child) => $child->date_of_birth->diffInYears($now) < 18);
    }
}
