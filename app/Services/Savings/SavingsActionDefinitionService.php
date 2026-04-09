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
        $trace = [];

        $user = User::find($userId);
        if (! $user || $user->date_of_birth !== null) {
            return [];
        }

        $userName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: 'Unknown user';

        $trace[] = [
            'question' => 'Who is this assessment for?',
            'data_field' => 'user_profile',
            'data_value' => $userName,
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Assessing savings data readiness for '.$userName.'.',
        ];

        $trace[] = [
            'question' => 'Has your date of birth been provided?',
            'data_field' => 'date_of_birth',
            'data_value' => 'Not set',
            'threshold' => 'Must be provided',
            'passed' => true,
            'explanation' => $userName.'\'s date of birth is not recorded. This is needed to personalise savings recommendations based on age and life stage — for example, determining risk tolerance and time horizon for savings goals.',
        ];

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Missing income: triggers when user has no income data set.
     */
    private function evaluateMissingIncome(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $trace = [];

        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $grossIncome = $this->resolveGrossAnnualIncome($user);
        if ($grossIncome > 0) {
            return [];
        }

        $userName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: 'Unknown user';
        $employmentStatus = $user->employment_status ?: 'not set';

        $trace[] = [
            'question' => 'Who is this assessment for?',
            'data_field' => 'user_profile',
            'data_value' => $userName.', employment status: '.$employmentStatus,
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Assessing income data readiness for '.$userName.' (employment status: '.$employmentStatus.').',
        ];

        $trace[] = [
            'question' => 'Has your income been provided?',
            'data_field' => 'gross_annual_income',
            'data_value' => '£'.number_format($grossIncome, 0),
            'threshold' => 'Greater than £0',
            'passed' => true,
            'explanation' => $userName.' has no income data recorded (annual_employment_income: £0). Income is needed to determine Personal Savings Allowance band (basic rate: £1,000, higher rate: £500, additional rate: £0) and to calculate tax-efficient savings strategies.',
        ];

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Missing expenditure: triggers when user has no expenditure data.
     */
    private function evaluateMissingExpenditure(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $trace = [];

        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $resolved = $this->resolveMonthlyExpenditure($user);
        if ($resolved['amount'] > 0) {
            return [];
        }

        $userName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: 'Unknown user';
        $employmentStatus = $user->employment_status ?: 'not set';
        $targetMonths = $this->getTargetEmergencyMonths($user);

        $trace[] = [
            'question' => 'Who is this assessment for?',
            'data_field' => 'user_profile',
            'data_value' => $userName.', employment status: '.$employmentStatus,
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Assessing expenditure data readiness for '.$userName.' (employment status: '.$employmentStatus.').',
        ];

        $trace[] = [
            'question' => 'Has your monthly expenditure been provided?',
            'data_field' => 'monthly_expenditure',
            'data_value' => '£'.number_format($resolved['amount'], 0).' (source: '.$resolved['source'].')',
            'threshold' => 'Greater than £0',
            'passed' => true,
            'explanation' => $userName.' has no expenditure data recorded. Monthly expenditure is needed to calculate the emergency fund target ('.$targetMonths.' months based on '.$employmentStatus.' status) and assess overall savings adequacy.',
        ];

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Missing employment status: triggers when employment_status is not set.
     */
    private function evaluateMissingEmployment(
        SavingsActionDefinition $definition,
        int $userId,
        int $priority
    ): array {
        $trace = [];

        $user = User::find($userId);
        if (! $user || ! empty($user->employment_status)) {
            return [];
        }

        $userName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: 'Unknown user';

        $trace[] = [
            'question' => 'Who is this assessment for?',
            'data_field' => 'user_profile',
            'data_value' => $userName,
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Assessing employment data readiness for '.$userName.'.',
        ];

        $trace[] = [
            'question' => 'Has your employment status been provided?',
            'data_field' => 'employment_status',
            'data_value' => 'Not set',
            'threshold' => 'Must be provided',
            'passed' => true,
            'explanation' => $userName.'\'s employment status is not recorded. This determines the recommended emergency fund target: employed = 6 months, self-employed/contractor = 9 months, retired = 6 months. Without it, a default of 6 months is used.',
        ];

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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
        $userName = $user ? $this->getUserName($user) : 'Unknown user';
        $targetMonths = $this->getTargetEmergencyMonths($user);

        // 1. User profile
        if ($user) {
            $trace[] = $this->buildUserProfileTrace($user);
        }

        // 2. Employment-based target
        $trace[] = $this->buildEmploymentTargetTrace($user, $targetMonths);

        // 3. Emergency fund accounts listing
        $trace[] = $this->buildEmergencyFundAccountsTrace($savingsAccounts);

        // 4. Runway calculation
        $currentBalance = $savingsAnalysis['emergency_fund']['current_balance'] ?? $savingsAccounts->where('is_emergency_fund', true)->sum('current_balance');
        $trace[] = [
            'question' => 'What is the current emergency fund runway?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => number_format($threshold, 1).' months (critical)',
            'passed' => true,
            'explanation' => 'Total emergency savings £'.number_format((float) $currentBalance, 0).' ÷ £'.number_format($monthlyExpenditure, 0).' monthly expenditure = '.number_format($runway, 1).' months runway. This is below the critical threshold of '.number_format($threshold, 1).' months.',
        ];

        // 5. Shortfall calculation
        $shortfallMonths = max(0, $targetMonths - $runway);
        $shortfallAmount = $shortfallMonths * $monthlyExpenditure;
        $targetAmount = $targetMonths * $monthlyExpenditure;

        $trace[] = [
            'question' => 'How large is the emergency fund shortfall?',
            'data_field' => 'shortfall_amount',
            'data_value' => '£'.number_format($shortfallAmount, 0),
            'threshold' => $targetMonths.' months target = £'.number_format($targetAmount, 0),
            'passed' => true,
            'explanation' => $userName.' needs £'.number_format($targetAmount, 0).' ('.$targetMonths.' months × £'.number_format($monthlyExpenditure, 0).'/month) but has £'.number_format((float) $currentBalance, 0).'. Shortfall: £'.number_format($targetAmount, 0).' − £'.number_format((float) $currentBalance, 0).' = £'.number_format($shortfallAmount, 0).'.',
        ];

        $vars = [
            'runway_months' => number_format($runway, 1),
            'target_months' => (string) $targetMonths,
            'shortfall' => $this->formatCurrency($shortfallAmount),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($shortfallAmount, 2);
        $rec['decision_trace'] = $trace;

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
        $trace = [];

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

        // 1. User profile
        if ($user) {
            $trace[] = $this->buildUserProfileTrace($user);
        }

        // 2. Employment-based target
        $trace[] = $this->buildEmploymentTargetTrace($user, $targetMonths);

        // 3. Emergency fund accounts listing
        $trace[] = $this->buildEmergencyFundAccountsTrace($savingsAccounts);

        // 4. Runway assessment
        $currentBalance = $savingsAnalysis['emergency_fund']['current_balance'] ?? $savingsAccounts->where('is_emergency_fund', true)->sum('current_balance');
        $trace[] = [
            'question' => 'What is the current emergency fund runway?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => 'Between '.number_format($low, 1).' and '.number_format($high, 1).' months (low)',
            'passed' => true,
            'explanation' => 'Total emergency savings £'.number_format((float) $currentBalance, 0).' ÷ £'.number_format($monthlyExpenditure, 0).' monthly expenditure = '.number_format($runway, 1).' months. This is below the '.number_format((float) $targetMonths, 0).'-month target but above the critical threshold.',
        ];

        // 5. Monthly top-up calculation
        $shortfallMonths = max(0, $targetMonths - $runway);
        $shortfallAmount = $shortfallMonths * $monthlyExpenditure;
        $monthlyTopUp = $this->emergencyFundCalculator->calculateMonthlyTopUp(
            $shortfallAmount,
            12
        );

        $trace[] = [
            'question' => 'How much needs to be saved each month to reach the target within a year?',
            'data_field' => 'monthly_top_up',
            'data_value' => '£'.number_format($monthlyTopUp, 0).'/month',
            'threshold' => $targetMonths.'-month target = £'.number_format($targetMonths * $monthlyExpenditure, 0),
            'passed' => true,
            'explanation' => 'Shortfall of £'.number_format($shortfallAmount, 0).' ('.number_format($shortfallMonths, 1).' months × £'.number_format($monthlyExpenditure, 0).'/month). Saving £'.number_format($monthlyTopUp, 0).' per month would close the gap within 12 months.',
        ];

        $vars = [
            'runway_months' => number_format($runway, 1),
            'target_months' => (string) $targetMonths,
            'monthly_top_up' => $this->formatCurrency($monthlyTopUp),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $low = (float) ($config['low'] ?? 3);
        $high = (float) ($config['high'] ?? 6);

        if ($runway < $low || $runway >= $high) {
            return [];
        }

        $adequacy = $savingsAnalysis['emergency_fund']['adequacy']['adequacy_score'] ?? 0;
        $currentBalance = $savingsAnalysis['emergency_fund']['current_balance'] ?? 0;
        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $targetAmount = $high * $monthlyExpenditure;

        $trace[] = [
            'question' => 'What is the current emergency fund position?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => 'Between '.number_format($low, 1).' and '.number_format($high, 1).' months',
            'passed' => true,
            'explanation' => 'Emergency fund balance of £'.number_format((float) $currentBalance, 0).' provides '.number_format($runway, 1).' months of runway against £'.number_format($monthlyExpenditure, 0).' monthly expenditure. This is progressing towards the '.number_format($high, 0).'-month target of £'.number_format($targetAmount, 0).'.',
        ];

        $remainingToTarget = max(0, $targetAmount - (float) $currentBalance);
        $trace[] = [
            'question' => 'How much remains to reach the full target?',
            'data_field' => 'adequacy_percent',
            'data_value' => number_format($adequacy, 0).'% adequacy',
            'threshold' => '100% = £'.number_format($targetAmount, 0),
            'passed' => true,
            'explanation' => 'At '.number_format($adequacy, 0).'% adequacy, £'.number_format($remainingToTarget, 0).' more is needed to reach the full '.number_format($high, 0).'-month target. Keep building to achieve full emergency cover.',
        ];

        $vars = [
            'runway_months' => number_format($runway, 1),
            'adequacy_percent' => number_format($adequacy, 0),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * No designated emergency fund: triggers when no account is flagged as emergency fund.
     */
    private function evaluateEmergencyFundNoDesignated(
        SavingsActionDefinition $definition,
        Collection $savingsAccounts,
        int $priority
    ): array {
        $trace = [];

        if ($savingsAccounts->isEmpty()) {
            return [];
        }

        $hasDesignated = $savingsAccounts->contains('is_emergency_fund', true);
        if ($hasDesignated) {
            return [];
        }

        // List all accounts so user can identify a suitable candidate
        $accountListings = $savingsAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');
        $totalBalance = $savingsAccounts->sum('current_balance');
        $easyAccessAccounts = $savingsAccounts->where('access_type', 'easy_access');

        $trace[] = [
            'question' => 'Do you have a savings account designated as your emergency fund?',
            'data_field' => 'is_emergency_fund',
            'data_value' => 'No designated account across '.$savingsAccounts->count().' account(s)',
            'threshold' => 'At least one account flagged as is_emergency_fund = true',
            'passed' => true,
            'explanation' => 'None of your '.$savingsAccounts->count().' savings account(s) is designated as an emergency fund. Accounts: '.$accountListings.'.',
        ];

        $trace[] = [
            'question' => 'Are there suitable easy-access accounts that could serve as an emergency fund?',
            'data_field' => 'easy_access_accounts',
            'data_value' => $easyAccessAccounts->count().' easy-access account(s)',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'You have '.$easyAccessAccounts->count().' easy-access account(s) out of '.$savingsAccounts->count().' total, holding £'.number_format((float) $totalBalance, 0).' in total. Designating an easy-access account as your emergency fund helps track your safety net separately from other savings goals.',
        ];

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 12);

        if ($runway < $threshold) {
            return [];
        }

        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        if ($monthlyExpenditure <= 0) {
            return [];
        }

        $currentBalance = $savingsAnalysis['emergency_fund']['current_balance'] ?? 0;
        $targetMonths = 6;
        $targetAmount = $targetMonths * $monthlyExpenditure;
        $excessMonths = $runway - $targetMonths;
        $excessAmount = $excessMonths * $monthlyExpenditure;

        $trace[] = [
            'question' => 'What is the current emergency fund runway?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => number_format($threshold, 1).' months (excessive trigger)',
            'passed' => true,
            'explanation' => 'Emergency fund balance of £'.number_format((float) $currentBalance, 0).' ÷ £'.number_format($monthlyExpenditure, 0).' monthly expenditure = '.number_format($runway, 1).' months runway. This exceeds the '.number_format($threshold, 1).'-month excessive threshold.',
        ];

        $trace[] = [
            'question' => 'How much is held above the recommended 6-month target?',
            'data_field' => 'excess_amount',
            'data_value' => '£'.number_format($excessAmount, 0),
            'threshold' => '6-month target = £'.number_format($targetAmount, 0),
            'passed' => true,
            'explanation' => '£'.number_format((float) $currentBalance, 0).' total − £'.number_format($targetAmount, 0).' target (6 months × £'.number_format($monthlyExpenditure, 0).') = £'.number_format($excessAmount, 0).' excess ('.number_format($excessMonths, 1).' months). This surplus could be deployed into higher-growth assets such as investments or pensions.',
        ];

        $vars = [
            'runway_months' => number_format($runway, 1),
            'excess_amount' => $this->formatCurrency($excessAmount),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($excessAmount, 2);
        $rec['decision_trace'] = $trace;

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
        $trace = [];

        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $psaPosition = $this->psaCalculator->assessPSAPosition($user);
        if (! $psaPosition['is_breached']) {
            return [];
        }

        // 1. User profile
        $trace[] = $this->buildUserProfileTrace($user);

        // 2. Tax band determination
        $userName = $this->getUserName($user);
        $grossIncome = $this->resolveGrossAnnualIncome($user);
        $trace[] = [
            'question' => 'What is the user\'s tax band and Personal Savings Allowance?',
            'data_field' => 'tax_band',
            'data_value' => $psaPosition['tax_band'].' rate taxpayer, Personal Savings Allowance: £'.number_format($psaPosition['psa_amount'], 0),
            'threshold' => 'Based on gross income of £'.number_format($grossIncome, 0),
            'passed' => true,
            'explanation' => $userName.' is a '.$psaPosition['tax_band'].' rate taxpayer (gross income £'.number_format($grossIncome, 0).'), giving a Personal Savings Allowance of £'.number_format($psaPosition['psa_amount'], 0).'. Basic rate = £1,000, higher rate = £500, additional rate = £0.',
        ];

        // 3. Interest vs allowance
        $nonIsaAccounts = $user->savingsAccounts()->where('is_isa', false)->get();
        $accountInterestDetails = $nonIsaAccounts->map(function ($account) {
            $balance = (float) ($account->current_balance ?? 0);
            $rate = (float) ($account->interest_rate ?? 0);
            $interest = $balance * $rate;

            return ($account->account_name ?? 'Unnamed').' at '.($account->institution ?? 'unknown').' — £'.number_format($balance, 0).' × '.number_format($rate * 100, 2).'% = £'.number_format($interest, 0);
        })->implode('; ');

        $trace[] = [
            'question' => 'How much taxable interest is generated across non-ISA accounts?',
            'data_field' => 'annual_interest',
            'data_value' => '£'.number_format($psaPosition['annual_interest'], 0).' total',
            'threshold' => '£'.number_format($psaPosition['psa_amount'], 0).' Personal Savings Allowance',
            'passed' => true,
            'explanation' => 'Non-ISA account interest: '.$accountInterestDetails.'. Total taxable interest: £'.number_format($psaPosition['annual_interest'], 0).' exceeds Personal Savings Allowance of £'.number_format($psaPosition['psa_amount'], 0).' by £'.number_format($psaPosition['breach_amount'], 0).'.',
        ];

        // 4. Tax payable calculation
        $incomeTaxBands = $this->taxConfig->getIncomeTax();
        $basicRate = (float) ($incomeTaxBands['bands'][0]['rate'] ?? 0.20);
        $higherRate = (float) ($incomeTaxBands['bands'][1]['rate'] ?? 0.40);
        $additionalRate = (float) ($incomeTaxBands['additional_rate'] ?? 0.45);
        $taxRate = match ($psaPosition['tax_band']) {
            'additional' => $additionalRate,
            'higher' => $higherRate,
            default => $basicRate,
        };
        $taxPayable = $psaPosition['breach_amount'] * $taxRate;

        $trace[] = [
            'question' => 'How much tax is payable on the excess interest?',
            'data_field' => 'tax_payable',
            'data_value' => '£'.number_format($taxPayable, 0),
            'threshold' => round($taxRate * 100, 0).'% tax rate',
            'passed' => true,
            'explanation' => 'Excess interest £'.number_format($psaPosition['breach_amount'], 0).' × '.round($taxRate * 100, 0).'% = £'.number_format($taxPayable, 0).' tax payable. Moving funds into a Cash ISA would shelter this interest from tax.',
        ];

        $vars = [
            'breach_amount' => $this->formatCurrency($psaPosition['breach_amount']),
            'psa_amount' => $this->formatCurrency($psaPosition['psa_amount']),
            'annual_interest' => $this->formatCurrency($psaPosition['annual_interest']),
            'tax_band' => $psaPosition['tax_band'],
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($taxPayable, 2);
        $rec['decision_trace'] = $trace;

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
        $trace = [];

        $user = User::find($userId);
        if (! $user) {
            return [];
        }

        $psaPosition = $this->psaCalculator->assessPSAPosition($user);
        if (! $psaPosition['is_approaching']) {
            return [];
        }

        // 1. User profile
        $trace[] = $this->buildUserProfileTrace($user);

        // 2. Tax band and PSA
        $userName = $this->getUserName($user);
        $grossIncome = $this->resolveGrossAnnualIncome($user);
        $trace[] = [
            'question' => 'What is the user\'s tax band and Personal Savings Allowance?',
            'data_field' => 'tax_band',
            'data_value' => $psaPosition['tax_band'].' rate, Personal Savings Allowance: £'.number_format($psaPosition['psa_amount'], 0),
            'threshold' => 'Based on gross income of £'.number_format($grossIncome, 0),
            'passed' => true,
            'explanation' => $userName.' is a '.$psaPosition['tax_band'].' rate taxpayer with a £'.number_format($psaPosition['psa_amount'], 0).' Personal Savings Allowance.',
        ];

        // 3. Utilisation detail
        $trace[] = [
            'question' => 'How much of the Personal Savings Allowance has been used?',
            'data_field' => 'utilisation_percent',
            'data_value' => number_format($psaPosition['utilisation_percent'], 0).'%',
            'threshold' => '£'.number_format($psaPosition['psa_amount'], 0).' allowance',
            'passed' => true,
            'explanation' => $userName.' has used '.number_format($psaPosition['utilisation_percent'], 0).'% of the £'.number_format($psaPosition['psa_amount'], 0).' Personal Savings Allowance. Annual taxable interest: £'.number_format($psaPosition['annual_interest'], 0).'. Remaining headroom: £'.number_format($psaPosition['headroom'], 0).'. Consider moving additional deposits into an ISA before the allowance is fully consumed.',
        ];

        $vars = [
            'utilisation_percent' => number_format($psaPosition['utilisation_percent'], 0),
            'headroom' => $this->formatCurrency($psaPosition['headroom']),
            'psa_amount' => $this->formatCurrency($psaPosition['psa_amount']),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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

        // 1. User profile
        $trace[] = $this->buildUserProfileTrace($user);

        // 2. PSA headroom
        $userName = $this->getUserName($user);
        $trace[] = [
            'question' => 'How much Personal Savings Allowance headroom is available?',
            'data_field' => 'utilisation_percent',
            'data_value' => number_format($psaPosition['utilisation_percent'], 0).'% used',
            'threshold' => 'Below 50% utilisation',
            'passed' => true,
            'explanation' => $userName.' is using only '.number_format($psaPosition['utilisation_percent'], 0).'% of the £'.number_format($psaPosition['psa_amount'], 0).' Personal Savings Allowance ('.$psaPosition['tax_band'].' rate). Annual interest: £'.number_format($psaPosition['annual_interest'], 0).'. Headroom remaining: £'.number_format($psaPosition['headroom'], 0).'.',
        ];

        // 3. Non-ISA accounts listing
        $accountDetails = $accounts->map(function ($account) {
            $balance = (float) ($account->current_balance ?? 0);
            $rate = (float) ($account->interest_rate ?? 0);
            $interest = $balance * $rate;

            return ($account->account_name ?? 'Unnamed').' at '.($account->institution ?? 'unknown').' — £'.number_format($balance, 0).' at '.number_format($rate * 100, 2).'% (£'.number_format($interest, 0).'/year interest)';
        })->implode('; ');

        $totalNonIsaBalance = $accounts->sum('current_balance');

        $trace[] = [
            'question' => 'Which non-ISA accounts could earn more interest tax-free?',
            'data_field' => 'non_isa_accounts',
            'data_value' => $accounts->count().' account(s), total £'.number_format((float) $totalNonIsaBalance, 0),
            'threshold' => 'At least 1 account',
            'passed' => true,
            'explanation' => 'Non-ISA accounts: '.$accountDetails.'. With £'.number_format($psaPosition['headroom'], 0).' of tax-free interest headroom, higher-rate non-ISA accounts could earn additional interest without triggering a tax charge.',
        ];

        $vars = [
            'headroom' => $this->formatCurrency($psaPosition['headroom']),
            'psa_amount' => $this->formatCurrency($psaPosition['psa_amount']),
            'utilisation_percent' => number_format($psaPosition['utilisation_percent'], 0),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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

        // 1. User profile
        $trace[] = $this->buildUserProfileTrace($user);

        // 2. PSA pressure
        $userName = $this->getUserName($user);
        $psaStatus = $psaPosition['is_breached'] ? 'breached' : 'approaching the limit';
        $trace[] = [
            'question' => 'Is the Personal Savings Allowance under pressure?',
            'data_field' => 'psa_status',
            'data_value' => ucfirst($psaStatus).' — '.number_format($psaPosition['utilisation_percent'], 0).'% used',
            'threshold' => 'Breached or approaching',
            'passed' => true,
            'explanation' => $userName.'\'s Personal Savings Allowance (£'.number_format($psaPosition['psa_amount'], 0).', '.$psaPosition['tax_band'].' rate) is '.$psaStatus.'. Annual taxable interest: £'.number_format($psaPosition['annual_interest'], 0).'. '.($psaPosition['is_breached'] ? 'Excess of £'.number_format($psaPosition['breach_amount'], 0).' is taxable.' : 'Only £'.number_format($psaPosition['headroom'], 0).' headroom remains.'),
        ];

        // 3. No Cash ISA held
        $nonIsaAccounts = $user->savingsAccounts()->where('is_isa', false)->get();
        $nonIsaDetails = $nonIsaAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');

        $trace[] = [
            'question' => 'Does the user hold a Cash ISA?',
            'data_field' => 'has_cash_isa',
            'data_value' => 'No',
            'threshold' => 'No existing Cash ISA',
            'passed' => true,
            'explanation' => $userName.' does not hold a Cash ISA. Non-ISA accounts: '.$nonIsaDetails.'. Opening a Cash ISA would shelter interest from tax and reduce Personal Savings Allowance pressure.',
        ];

        // 4. ISA allowance available
        $isaAllowances = $this->taxConfig->getISAAllowances();
        $isaAllowance = $isaAllowances['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;
        $isaUsed = $savingsAnalysis['isa_allowance']['used'] ?? 0;
        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? ((float) $isaAllowance - (float) $isaUsed);

        $trace[] = [
            'question' => 'How much ISA allowance is available this tax year?',
            'data_field' => 'isa_allowance',
            'data_value' => '£'.number_format((float) $isaAllowance, 0).' total, £'.number_format((float) $isaRemaining, 0).' remaining',
            'threshold' => $this->taxConfig->getTaxYear().' tax year',
            'passed' => true,
            'explanation' => 'The '.$this->taxConfig->getTaxYear().' ISA allowance is £'.number_format((float) $isaAllowance, 0).'. '.$userName.' has £'.number_format((float) $isaRemaining, 0).' remaining to shelter savings interest from tax.',
        ];

        $vars = [
            'isa_allowance' => $this->formatCurrency((float) $isaAllowance),
            'annual_interest' => $this->formatCurrency($psaPosition['annual_interest']),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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
        $cashIsaAccounts = $user->savingsAccounts()
            ->where('is_isa', true)
            ->where('isa_type', 'cash')
            ->get();

        if ($cashIsaAccounts->isEmpty()) {
            return [];
        }

        // 1. User profile
        $trace[] = $this->buildUserProfileTrace($user);

        // 2. Low PSA utilisation
        $userName = $this->getUserName($user);
        $trace[] = [
            'question' => 'Is the Personal Savings Allowance utilisation very low?',
            'data_field' => 'utilisation_percent',
            'data_value' => number_format($psaPosition['utilisation_percent'], 0).'%',
            'threshold' => 'Below 25%',
            'passed' => true,
            'explanation' => $userName.' is using only '.number_format($psaPosition['utilisation_percent'], 0).'% of the £'.number_format($psaPosition['psa_amount'], 0).' Personal Savings Allowance ('.$psaPosition['tax_band'].' rate). Annual interest: £'.number_format($psaPosition['annual_interest'], 0).'. Headroom: £'.number_format($psaPosition['headroom'], 0).'.',
        ];

        // 3. Cash ISA details
        $isaDetails = $cashIsaAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');
        $totalIsaBalance = $cashIsaAccounts->sum('current_balance');
        $avgIsaRate = $cashIsaAccounts->avg('interest_rate');

        $trace[] = [
            'question' => 'Which Cash ISA accounts are held?',
            'data_field' => 'cash_isa_accounts',
            'data_value' => $cashIsaAccounts->count().' Cash ISA(s), total £'.number_format((float) $totalIsaBalance, 0),
            'threshold' => 'Has existing Cash ISA',
            'passed' => true,
            'explanation' => 'Cash ISA accounts: '.$isaDetails.'. With low Personal Savings Allowance utilisation, non-ISA savings accounts often offer better rates than Cash ISAs. Average ISA rate: '.number_format((float) $avgIsaRate * 100, 2).'%.',
        ];

        $vars = [
            'utilisation_percent' => number_format($psaPosition['utilisation_percent'], 0),
            'psa_amount' => $this->formatCurrency($psaPosition['psa_amount']),
            'headroom' => $this->formatCurrency($psaPosition['headroom']),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        if ($isaRemaining <= 0) {
            return [];
        }

        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $runwayThreshold = (float) ($config['threshold'] ?? 6);

        if ($runway < $runwayThreshold) {
            return [];
        }

        // 1. ISA allowance position
        $taxYear = $this->taxConfig->getTaxYear();
        $isaUsed = $savingsAnalysis['isa_allowance']['used'] ?? 0;
        $isaTotal = $savingsAnalysis['isa_allowance']['allowance'] ?? ($isaUsed + $isaRemaining);

        $trace[] = [
            'question' => 'How much ISA allowance remains this tax year?',
            'data_field' => 'isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0).' remaining of £'.number_format((float) $isaTotal, 0),
            'threshold' => 'Greater than £0',
            'passed' => true,
            'explanation' => 'For the '.$taxYear.' tax year, £'.number_format((float) $isaUsed, 0).' of the £'.number_format((float) $isaTotal, 0).' ISA allowance has been used. Remaining: £'.number_format($isaRemaining, 0).'. ISA interest is tax-free regardless of income level.',
        ];

        // 2. Emergency fund adequacy check
        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $trace[] = [
            'question' => 'Is the emergency fund adequate enough to consider ISA contributions?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => number_format($runwayThreshold, 1).' months minimum',
            'passed' => true,
            'explanation' => 'Emergency fund runway of '.number_format($runway, 1).' months exceeds the '.number_format($runwayThreshold, 1).'-month threshold'.($monthlyExpenditure > 0 ? ' (based on £'.number_format($monthlyExpenditure, 0).' monthly expenditure)' : '').'. Surplus savings can be directed into ISA wrappers for tax-free growth.',
        ];

        $vars = [
            'isa_remaining' => $this->formatCurrency($isaRemaining),
            'tax_year' => $taxYear,
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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

            $trace = [];
            $balance = (float) ($account->current_balance ?? 0);
            $currentRate = ((float) $account->interest_rate) * 100;
            $marketRate = ($comparison['comparison']['market_rate'] ?? 0) * 100;
            $rateGap = $marketRate - $currentRate;
            $currentInterest = $balance * ((float) $account->interest_rate);
            $marketInterest = $balance * (($comparison['comparison']['market_rate'] ?? 0));
            $accountName = $account->account_name ?? 'Unnamed account';
            $institution = $account->institution ?? 'unknown provider';
            $accessType = $account->access_type ?? 'unknown';
            $isIsa = $account->is_isa ? ' (ISA — '.($account->isa_type ?? 'unspecified').')' : '';

            // 1. Account details
            $trace[] = [
                'question' => 'Which account has a below-market rate?',
                'data_field' => 'account_details',
                'data_value' => $accountName.' at '.$institution,
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => $accountName.' at '.$institution.' — £'.number_format($balance, 0).' balance, '.number_format($currentRate, 2).'% interest rate, '.$accessType.' access'.$isIsa.'.',
            ];

            // 2. Rate comparison
            $trace[] = [
                'question' => 'How does the rate compare to the market?',
                'data_field' => 'interest_rate',
                'data_value' => number_format($currentRate, 2).'% (rated Fair)',
                'threshold' => number_format($marketRate, 2).'% best available market rate',
                'passed' => true,
                'explanation' => 'Current rate '.number_format($currentRate, 2).'% is '.number_format($rateGap, 2).' percentage points below the best available market rate of '.number_format($marketRate, 2).'% for a comparable '.$accessType.' account.',
            ];

            // 3. Impact calculation
            $trace[] = [
                'question' => 'What is the financial impact of switching?',
                'data_field' => 'potential_gain',
                'data_value' => '£'.number_format($potentialGain, 0).'/year',
                'threshold' => '£50 minimum to trigger',
                'passed' => true,
                'explanation' => 'Current interest: £'.number_format($balance, 0).' × '.number_format($currentRate, 2).'% = £'.number_format($currentInterest, 0).'/year. At market rate: £'.number_format($balance, 0).' × '.number_format($marketRate, 2).'% = £'.number_format($marketInterest, 0).'/year. Additional interest from switching: £'.number_format($potentialGain, 0).'/year.',
            ];

            $vars = [
                'account_name' => $accountName,
                'current_rate' => number_format($currentRate, 2),
                'market_rate' => number_format($marketRate, 2),
                'potential_gain' => $this->formatCurrency($potentialGain),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['estimated_impact'] = round($potentialGain, 2);
            $rec['decision_trace'] = $trace;
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
            $balance = (float) ($account->current_balance ?? 0);
            $currentRate = ((float) $account->interest_rate) * 100;
            $marketRate = ($comparison['comparison']['market_rate'] ?? 0) * 100;
            $rateGap = $marketRate - $currentRate;
            $currentInterest = $balance * ((float) $account->interest_rate);
            $accountName = $account->account_name ?? 'Unnamed account';
            $institution = $account->institution ?? 'unknown provider';
            $accessType = $account->access_type ?? 'unknown';
            $isIsa = $account->is_isa ? ' (ISA — '.($account->isa_type ?? 'unspecified').')' : '';

            $trace = [];

            // 1. Account details
            $trace[] = [
                'question' => 'Which account has a significantly below-market rate?',
                'data_field' => 'account_details',
                'data_value' => $accountName.' at '.$institution,
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => $accountName.' at '.$institution.' — £'.number_format($balance, 0).' balance, '.number_format($currentRate, 2).'% interest rate, '.$accessType.' access'.$isIsa.'.',
            ];

            // 2. Rate gap
            $trace[] = [
                'question' => 'How far below market is the current rate?',
                'data_field' => 'interest_rate',
                'data_value' => number_format($currentRate, 2).'% (rated Poor)',
                'threshold' => number_format($marketRate, 2).'% best available market rate',
                'passed' => true,
                'explanation' => 'Current rate '.number_format($currentRate, 2).'% is '.number_format($rateGap, 2).' percentage points below the best available market rate of '.number_format($marketRate, 2).'% for a comparable '.$accessType.' account. This is a significant gap rated as Poor.',
            ];

            // 3. Financial impact
            $marketInterest = $balance * (($comparison['comparison']['market_rate'] ?? 0));
            $trace[] = [
                'question' => 'How much interest is being lost each year?',
                'data_field' => 'potential_gain',
                'data_value' => '£'.number_format($potentialGain, 0).'/year',
                'threshold' => 'Any amount',
                'passed' => true,
                'explanation' => 'Current interest: £'.number_format($balance, 0).' × '.number_format($currentRate, 2).'% = £'.number_format($currentInterest, 0).'/year. At market rate: £'.number_format($balance, 0).' × '.number_format($marketRate, 2).'% = £'.number_format($marketInterest, 0).'/year. Switching could earn an additional £'.number_format($potentialGain, 0).'/year.',
            ];

            $vars = [
                'account_name' => $accountName,
                'institution' => $institution,
                'current_rate' => number_format($currentRate, 2),
                'market_rate' => number_format($marketRate, 2),
                'potential_gain' => $this->formatCurrency($potentialGain),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['estimated_impact'] = round($potentialGain, 2);
            $rec['decision_trace'] = $trace;
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

            $balance = (float) ($account->current_balance ?? 0);
            $currentRate = ((float) ($account->interest_rate ?? 0)) * 100;
            $accountName = $account->account_name ?? 'Unnamed account';
            $institution = $account->institution ?? 'unknown provider';
            $isIsa = $account->is_isa ? ' (ISA — '.($account->isa_type ?? 'unspecified').')' : '';
            $annualInterest = $balance * ((float) ($account->interest_rate ?? 0));

            $trace = [];

            // 1. Account details
            $trace[] = [
                'question' => 'Which fixed-rate account is approaching maturity?',
                'data_field' => 'account_details',
                'data_value' => $accountName.' at '.$institution,
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => $accountName.' at '.$institution.' — £'.number_format($balance, 0).' balance, '.number_format($currentRate, 2).'% fixed rate'.$isIsa.'. Earning £'.number_format($annualInterest, 0).'/year interest (£'.number_format($balance, 0).' × '.number_format($currentRate, 2).'%).',
            ];

            // 2. Maturity date and timeline
            $weeksToMaturity = round($daysToMaturity / 7, 1);
            $trace[] = [
                'question' => 'When does the fixed rate mature?',
                'data_field' => 'maturity_date',
                'data_value' => $account->maturity_date->format('d M Y').' ('.$daysToMaturity.' days / '.number_format($weeksToMaturity, 1).' weeks)',
                'threshold' => 'Within '.$windowDays.' days',
                'passed' => true,
                'explanation' => 'Maturity date: '.$account->maturity_date->format('d M Y').' — '.$daysToMaturity.' days away. After maturity, the account will likely revert to a lower variable rate. Plan ahead to secure a competitive new rate for the £'.number_format($balance, 0).' balance.',
            ];

            $vars = [
                'account_name' => $accountName,
                'institution' => $institution,
                'maturity_date' => $account->maturity_date->format('d M Y'),
                'days_remaining' => (string) $daysToMaturity,
                'balance' => $this->formatCurrency($balance),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['decision_trace'] = $trace;
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

            $balance = (float) ($account->current_balance ?? 0);
            $currentRate = ((float) ($account->interest_rate ?? 0)) * 100;
            $accountName = $account->account_name ?? 'Unnamed account';
            $institution = $account->institution ?? 'unknown provider';
            $isIsa = $account->is_isa ? ' (ISA — '.($account->isa_type ?? 'unspecified').')' : '';
            $annualInterest = $balance * ((float) ($account->interest_rate ?? 0));

            $trace = [];

            // 1. Account details
            $trace[] = [
                'question' => 'Which account has an expiring promotional rate?',
                'data_field' => 'account_details',
                'data_value' => $accountName.' at '.$institution,
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => $accountName.' at '.$institution.' — £'.number_format($balance, 0).' balance, current promotional rate '.number_format($currentRate, 2).'%'.$isIsa.'. Currently earning £'.number_format($annualInterest, 0).'/year (£'.number_format($balance, 0).' × '.number_format($currentRate, 2).'%).',
            ];

            // 2. Expiry timeline
            $weeksToExpiry = round($daysToExpiry / 7, 1);
            $trace[] = [
                'question' => 'When does the promotional rate expire?',
                'data_field' => 'promo_rate_end_date',
                'data_value' => $account->promo_rate_end_date->format('d M Y').' ('.$daysToExpiry.' days / '.number_format($weeksToExpiry, 1).' weeks)',
                'threshold' => 'Within '.$windowDays.' days',
                'passed' => true,
                'explanation' => 'Promotional rate expires on '.$account->promo_rate_end_date->format('d M Y').' — '.$daysToExpiry.' days away. After expiry, the rate will likely drop significantly. Review alternative accounts or negotiate a new rate before the £'.number_format($balance, 0).' balance starts earning less.',
            ];

            $vars = [
                'account_name' => $accountName,
                'institution' => $institution,
                'expiry_date' => $account->promo_rate_end_date->format('d M Y'),
                'days_remaining' => (string) $daysToExpiry,
                'balance' => $this->formatCurrency($balance),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['decision_trace'] = $trace;
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
        $trace = [];

        $rateComparisons = $savingsAnalysis['rate_comparisons'] ?? [];
        $uncompetitiveComparisons = collect($rateComparisons)
            ->where('comparison.is_competitive', false);

        $totalGain = $uncompetitiveComparisons->sum('potential_gain');

        if ($totalGain < 100) {
            return [];
        }

        $uncompetitiveCount = $uncompetitiveComparisons->count();

        // 1. Per-account breakdown of uncompetitive rates
        $accountBreakdown = $uncompetitiveComparisons->map(function ($comparison) use ($savingsAccounts) {
            $account = $savingsAccounts->firstWhere('id', $comparison['account_id']);
            if (! $account) {
                return null;
            }
            $balance = (float) ($account->current_balance ?? 0);
            $currentRate = ((float) ($account->interest_rate ?? 0)) * 100;
            $marketRate = (($comparison['comparison']['market_rate'] ?? 0)) * 100;
            $gain = (float) ($comparison['potential_gain'] ?? 0);
            $category = $comparison['comparison']['category'] ?? 'Unknown';

            return ($account->account_name ?? 'Unnamed').' at '.($account->institution ?? 'unknown').' — £'.number_format($balance, 0).' at '.number_format($currentRate, 2).'% (market: '.number_format($marketRate, 2).'%, '.$category.', +£'.number_format($gain, 0).'/year)';
        })->filter()->implode('; ');

        $trace[] = [
            'question' => 'Which accounts have uncompetitive rates?',
            'data_field' => 'uncompetitive_accounts',
            'data_value' => $uncompetitiveCount.' account(s)',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Uncompetitive accounts: '.$accountBreakdown.'.',
        ];

        // 2. Total potential gain
        $trace[] = [
            'question' => 'What is the total potential gain from switching all uncompetitive accounts?',
            'data_field' => 'total_potential_gain',
            'data_value' => '£'.number_format($totalGain, 0).'/year',
            'threshold' => '£100 minimum total gain',
            'passed' => true,
            'explanation' => 'Across '.$uncompetitiveCount.' account(s) with uncompetitive rates, switching to market-rate alternatives could earn an additional £'.number_format($totalGain, 0).' per year in aggregate.',
        ];

        $vars = [
            'total_potential_gain' => $this->formatCurrency($totalGain),
            'account_count' => (string) $uncompetitiveCount,
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($totalGain, 2);
        $rec['decision_trace'] = $trace;

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

            $accountName = $account->account_name ?? 'Unnamed account';
            $institution = $account->institution ?? 'unknown provider';
            $accessType = $account->access_type ?? 'unknown';
            $isIsa = $account->is_isa ? ' (ISA — '.($account->isa_type ?? 'unspecified').')' : '';
            $isEmergency = $account->is_emergency_fund ? ', designated as emergency fund' : '';

            $trace = [];

            // 1. Account details
            $trace[] = [
                'question' => 'Which account is earning 0% interest?',
                'data_field' => 'account_details',
                'data_value' => $accountName.' at '.$institution,
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => $accountName.' at '.$institution.' — £'.number_format($balance, 0).' balance, 0.00% interest rate, '.$accessType.' access'.$isIsa.$isEmergency.'.',
            ];

            // 2. Opportunity cost estimate (assume a modest 4% easy-access rate)
            $illustrativeRate = 0.04;
            $potentialInterest = $balance * $illustrativeRate;
            $trace[] = [
                'question' => 'How much interest is being foregone?',
                'data_field' => 'interest_rate',
                'data_value' => '0.00%',
                'threshold' => 'Greater than 0%',
                'passed' => true,
                'explanation' => '£'.number_format($balance, 0).' is earning no interest. At an illustrative '.number_format($illustrativeRate * 100, 1).'% easy-access rate, this balance could earn approximately £'.number_format($potentialInterest, 0).'/year (£'.number_format($balance, 0).' × '.number_format($illustrativeRate * 100, 1).'%). Moving to a competitive account would generate meaningful returns.',
            ];

            $vars = [
                'account_name' => $accountName,
                'institution' => $institution,
                'balance' => $this->formatCurrency($balance),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $account->id;
            $rec['account_name'] = $account->account_name;
            $rec['decision_trace'] = $trace;
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

            $trace = [];

            // 1. List accounts at this institution
            $institutionAccounts = $savingsAccounts->filter(fn ($a) => ($a->institution ?? '') === $group['institution_group']);
            $accountDetails = $institutionAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');

            $trace[] = [
                'question' => 'Which accounts are held at this institution?',
                'data_field' => 'institution_accounts',
                'data_value' => $institutionAccounts->count().' account(s) at '.$group['institution_group'],
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'Accounts at '.$group['institution_group'].': '.$accountDetails.'.',
            ];

            // 2. FSCS limit breach
            $trace[] = [
                'question' => 'Do total deposits exceed the Financial Services Compensation Scheme limit?',
                'data_field' => 'total_balance',
                'data_value' => '£'.number_format($group['total_balance'], 0),
                'threshold' => '£'.number_format($group['fscs_limit'], 0).' Financial Services Compensation Scheme limit',
                'passed' => true,
                'explanation' => 'Combined deposits of £'.number_format($group['total_balance'], 0).' across '.$institutionAccounts->count().' account(s) at '.$group['institution_group'].' exceed the £'.number_format($group['fscs_limit'], 0).' protection limit by £'.number_format($group['excess'], 0).' (£'.number_format($group['total_balance'], 0).' − £'.number_format($group['fscs_limit'], 0).' = £'.number_format($group['excess'], 0).'). This excess is unprotected if the institution fails.',
            ];

            $vars = [
                'institution' => $group['institution_group'],
                'total_balance' => $this->formatCurrency($group['total_balance']),
                'fscs_limit' => $this->formatCurrency($group['fscs_limit']),
                'excess' => $this->formatCurrency($group['excess']),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['estimated_impact'] = round($group['excess'], 2);
            $rec['decision_trace'] = $trace;
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

            $trace = [];

            // 1. List accounts at this institution
            $institutionAccounts = $savingsAccounts->filter(fn ($a) => ($a->institution ?? '') === $group['institution_group']);
            $accountDetails = $institutionAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');

            $trace[] = [
                'question' => 'Which accounts are held at this institution?',
                'data_field' => 'institution_accounts',
                'data_value' => $institutionAccounts->count().' account(s) at '.$group['institution_group'],
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'Accounts at '.$group['institution_group'].': '.$accountDetails.'.',
            ];

            // 2. Approaching limit
            $utilisationPercent = $group['fscs_limit'] > 0 ? ($group['total_balance'] / $group['fscs_limit']) * 100 : 0;
            $trace[] = [
                'question' => 'Are deposits approaching the Financial Services Compensation Scheme limit?',
                'data_field' => 'total_balance',
                'data_value' => '£'.number_format($group['total_balance'], 0).' ('.number_format($utilisationPercent, 0).'% of limit)',
                'threshold' => '£'.number_format($group['fscs_limit'], 0).' Financial Services Compensation Scheme limit',
                'passed' => true,
                'explanation' => 'Combined deposits of £'.number_format($group['total_balance'], 0).' at '.$group['institution_group'].' are '.number_format($utilisationPercent, 0).'% of the £'.number_format($group['fscs_limit'], 0).' protection limit. Only £'.number_format($group['headroom'], 0).' headroom remains before exceeding the limit. Consider diversifying new deposits across different institutions.',
            ];

            $vars = [
                'institution' => $group['institution_group'],
                'total_balance' => $this->formatCurrency($group['total_balance']),
                'fscs_limit' => $this->formatCurrency($group['fscs_limit']),
                'headroom' => $this->formatCurrency($group['headroom']),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
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
        $trace = [];

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

        // 1. User profile
        $trace[] = $this->buildUserProfileTrace($user);

        // 2. Mortgage details
        $userName = $this->getUserName($user);
        $mortgageRate = (float) $highRateMortgage->interest_rate;
        $mortgageBalance = (float) ($highRateMortgage->outstanding_balance ?? $highRateMortgage->current_balance ?? 0);
        $annualMortgageCost = $mortgageBalance * $mortgageRate;

        $trace[] = [
            'question' => 'Which mortgage has a rate exceeding the best savings rate?',
            'data_field' => 'mortgage_details',
            'data_value' => ($highRateMortgage->lender_name ?? 'Unknown lender').' at '.number_format($mortgageRate * 100, 2).'%',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => $userName.'\'s mortgage with '.($highRateMortgage->lender_name ?? 'unknown lender').' — £'.number_format($mortgageBalance, 0).' outstanding at '.number_format($mortgageRate * 100, 2).'%. Annual interest cost: approximately £'.number_format($annualMortgageCost, 0).' (£'.number_format($mortgageBalance, 0).' × '.number_format($mortgageRate * 100, 2).'%).',
        ];

        // 3. Best savings rate comparison
        $bestSavingsAccount = $savingsAccounts->sortByDesc('interest_rate')->first();
        $bestAccountName = $bestSavingsAccount ? (($bestSavingsAccount->account_name ?? 'Unnamed').' at '.($bestSavingsAccount->institution ?? 'unknown')) : 'unknown';
        $rateDifference = $mortgageRate - $bestSavingsRate;

        $trace[] = [
            'question' => 'How does the mortgage rate compare to the best savings rate?',
            'data_field' => 'rate_comparison',
            'data_value' => 'Mortgage '.number_format($mortgageRate * 100, 2).'% vs savings '.number_format($bestSavingsRate * 100, 2).'%',
            'threshold' => 'Mortgage rate must exceed best savings rate',
            'passed' => true,
            'explanation' => 'Best savings rate: '.number_format($bestSavingsRate * 100, 2).'% ('.$bestAccountName.'). Mortgage rate: '.number_format($mortgageRate * 100, 2).'%. Gap: '.number_format($rateDifference * 100, 2).' percentage points. Every pound used to overpay the mortgage effectively "earns" '.number_format($mortgageRate * 100, 2).'% by reducing interest charges, compared to '.number_format($bestSavingsRate * 100, 2).'% in savings.',
        ];

        $vars = [
            'mortgage_rate' => number_format($mortgageRate * 100, 2),
            'savings_rate' => number_format($bestSavingsRate * 100, 2),
            'rate_difference' => number_format($rateDifference * 100, 2),
            'lender' => $highRateMortgage->lender_name ?? 'your mortgage lender',
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

        $user = User::with('mortgages')->find($userId);
        if (! $user || $user->mortgages->isEmpty()) {
            return [];
        }

        // Only trigger if user has surplus above emergency fund levels
        $nonEmergencyAccounts = $savingsAccounts->where('is_emergency_fund', false);
        $nonEmergencyBalance = $nonEmergencyAccounts->sum('current_balance');

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

        // 1. User profile
        $trace[] = $this->buildUserProfileTrace($user);

        // 2. Non-emergency savings listing
        $userName = $this->getUserName($user);
        $nonEmergencyDetails = $nonEmergencyAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');

        $trace[] = [
            'question' => 'What non-emergency savings are available?',
            'data_field' => 'non_emergency_balance',
            'data_value' => '£'.number_format($nonEmergencyBalance, 0).' across '.$nonEmergencyAccounts->count().' account(s)',
            'threshold' => 'Greater than £0',
            'passed' => true,
            'explanation' => 'Non-emergency savings accounts: '.$nonEmergencyDetails.'. Total: £'.number_format($nonEmergencyBalance, 0).'.',
        ];

        // 3. Mortgage vs savings rate comparison
        $highestMortgage = $user->mortgages->sortByDesc('interest_rate')->first();
        $mortgageLender = $highestMortgage->lender_name ?? 'unknown lender';
        $mortgageBalance = (float) ($highestMortgage->outstanding_balance ?? $highestMortgage->current_balance ?? 0);
        $rateDiff = $highestMortgageRate - $averageSavingsRate;
        $effectiveSaving = $nonEmergencyBalance * $rateDiff;

        $trace[] = [
            'question' => 'Does the mortgage rate meaningfully exceed the average savings rate?',
            'data_field' => 'rate_comparison',
            'data_value' => 'Mortgage '.number_format($highestMortgageRate * 100, 2).'% vs savings avg '.number_format($averageSavingsRate * 100, 2).'%',
            'threshold' => 'Mortgage rate must exceed average savings rate by > 0.5%',
            'passed' => true,
            'explanation' => 'Highest mortgage: '.$mortgageLender.' at '.number_format($highestMortgageRate * 100, 2).'% (£'.number_format($mortgageBalance, 0).' outstanding). Average savings rate: '.number_format($averageSavingsRate * 100, 2).'%. Rate gap: '.number_format($rateDiff * 100, 2).' percentage points. Overpaying with the £'.number_format($nonEmergencyBalance, 0).' non-emergency savings could save approximately £'.number_format($effectiveSaving, 0).'/year in net interest.',
        ];

        $vars = [
            'mortgage_rate' => number_format($highestMortgageRate * 100, 2),
            'average_savings_rate' => number_format($averageSavingsRate * 100, 2),
            'non_emergency_balance' => $this->formatCurrency($nonEmergencyBalance),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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

        // 1. Emergency fund position
        $trace[] = [
            'question' => 'Does the emergency fund exceed the recommended target?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => number_format($targetMonths, 1).' months target',
            'passed' => true,
            'explanation' => 'Total savings: £'.number_format($totalSavings, 0).'. Monthly expenditure: £'.number_format($monthlyExpenditure, 0).'. Runway: £'.number_format($totalSavings, 0).' ÷ £'.number_format($monthlyExpenditure, 0).' = '.number_format($runway, 1).' months, which exceeds the '.number_format($targetMonths, 1).'-month target.',
        ];

        // 2. Surplus calculation
        $excessMonths = $runway - $targetMonths;
        $trace[] = [
            'question' => 'Is the surplus large enough to consider investing?',
            'data_field' => 'surplus',
            'data_value' => '£'.number_format($surplus, 0).' ('.number_format($excessMonths, 1).' months excess)',
            'threshold' => '£'.number_format($surplusThreshold, 0).' minimum surplus',
            'passed' => true,
            'explanation' => '£'.number_format($totalSavings, 0).' total savings − £'.number_format($targetAmount, 0).' target ('.number_format($targetMonths, 0).' months × £'.number_format($monthlyExpenditure, 0).') = £'.number_format($surplus, 0).' surplus. This exceeds the £'.number_format($surplusThreshold, 0).' threshold and could potentially be invested for higher long-term returns.',
        ];

        $vars = [
            'surplus_amount' => $this->formatCurrency($surplus),
            'target_amount' => $this->formatCurrency($targetAmount),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($surplus, 2);
        $rec['decision_trace'] = $trace;

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
        $trace = [];

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

        // 1. Cash savings total
        $accountCount = $savingsAnalysis['summary']['account_count'] ?? 0;
        $avgRate = $savingsAnalysis['summary']['average_rate'] ?? 0;
        $trace[] = [
            'question' => 'How much is held in cash savings?',
            'data_field' => 'total_savings',
            'data_value' => '£'.number_format($totalSavings, 0).' across '.$accountCount.' account(s)',
            'threshold' => '£'.number_format($threshold, 0).' minimum',
            'passed' => true,
            'explanation' => 'Total cash savings of £'.number_format($totalSavings, 0).' across '.$accountCount.' account(s) at an average rate of '.number_format((float) $avgRate * 100, 2).'%. This exceeds the £'.number_format($threshold, 0).' cash drag threshold.',
        ];

        // 2. Surplus vs emergency fund
        $investmentValue = $investmentAnalysis['portfolio_summary']['total_value'] ?? 0;
        $cashToInvestmentRatio = $investmentValue > 0 ? ($totalSavings / $investmentValue) * 100 : 0;

        $trace[] = [
            'question' => 'How much surplus cash is held above the emergency fund target?',
            'data_field' => 'surplus',
            'data_value' => '£'.number_format($surplus, 0),
            'threshold' => '£'.number_format($threshold * 0.5, 0).' minimum surplus',
            'passed' => true,
            'explanation' => '£'.number_format($totalSavings, 0).' total cash − £'.number_format($emergencyTarget, 0).' emergency target (6 months × £'.number_format($monthlyExpenditure, 0).') = £'.number_format($surplus, 0).' surplus. Investment portfolio: £'.number_format((float) $investmentValue, 0).' across '.$investmentCount.' account(s). Cash-to-investment ratio: '.number_format($cashToInvestmentRatio, 0).'%. Holding excessive cash may create drag on overall returns compared to investing.',
        ];

        $vars = [
            'surplus_amount' => $this->formatCurrency($surplus),
            'total_savings' => $this->formatCurrency($totalSavings),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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

        // 1. User profile
        $user = User::find($userId);
        if ($user) {
            $trace[] = $this->buildUserProfileTrace($user);
        }

        // 2. ISA allowance remaining
        $taxYear = $this->taxConfig->getTaxYear();
        $isaUsed = $savingsAnalysis['isa_allowance']['used'] ?? 0;
        $isaTotal = $savingsAnalysis['isa_allowance']['allowance'] ?? ($isaUsed + $isaRemaining);

        $trace[] = [
            'question' => 'How much ISA allowance remains this tax year?',
            'data_field' => 'isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0).' remaining of £'.number_format((float) $isaTotal, 0),
            'threshold' => 'Greater than £0',
            'passed' => true,
            'explanation' => 'For the '.$taxYear.' tax year, £'.number_format((float) $isaUsed, 0).' of the £'.number_format((float) $isaTotal, 0).' ISA allowance has been used in Cash ISAs. £'.number_format($isaRemaining, 0).' remains available for a Stocks and Shares ISA.',
        ];

        // 3. Emergency fund adequate
        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $trace[] = [
            'question' => 'Is the emergency fund adequate to consider longer-term investing?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => '6 months minimum',
            'passed' => true,
            'explanation' => 'Emergency fund runway of '.number_format($runway, 1).' months (£'.number_format($totalSavings, 0).' total savings ÷ £'.number_format($monthlyExpenditure, 0).' monthly expenditure) exceeds the 6-month threshold.',
        ];

        // 4. No existing Stocks & Shares ISA
        $trace[] = [
            'question' => 'Is a Stocks and Shares ISA already held?',
            'data_field' => 'has_stocks_shares_isa',
            'data_value' => 'No',
            'threshold' => 'No existing Stocks and Shares ISA',
            'passed' => true,
            'explanation' => 'No Stocks and Shares ISA is currently held. Opening one with some of the £'.number_format($isaRemaining, 0).' remaining allowance could provide tax-efficient growth potential for surplus savings over the medium to long term.',
        ];

        $vars = [
            'isa_remaining' => $this->formatCurrency($isaRemaining),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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
        $emergencyTarget = $monthlyExpenditure * 6;
        $surplus = $totalSavings - $emergencyTarget;

        if ($surplus < 5000) {
            return [];
        }

        // 1. User profile
        $trace[] = $this->buildUserProfileTrace($user);

        // 2. Surplus calculation
        $userName = $this->getUserName($user);
        $trace[] = [
            'question' => 'How much surplus savings exist above the emergency fund?',
            'data_field' => 'surplus',
            'data_value' => '£'.number_format($surplus, 0),
            'threshold' => '£5,000 minimum',
            'passed' => true,
            'explanation' => '£'.number_format($totalSavings, 0).' total savings − £'.number_format($emergencyTarget, 0).' emergency fund target (6 months × £'.number_format($monthlyExpenditure, 0).') = £'.number_format($surplus, 0).' surplus available for pension contributions.',
        ];

        // 3. Pension contribution and tax relief
        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = (float) ($pensionAllowances['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE);
        $pensionAmount = min($surplus, $annualAllowance);

        // Determine marginal tax rate based on income
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? TaxDefaults::PERSONAL_ALLOWANCE);
        $basicRateLimit = $personalAllowance + (float) ($incomeTax['bands'][0]['max'] ?? 37700);
        $additionalRateThreshold = (float) ($incomeTax['additional_rate_threshold'] ?? 125140);

        $basicRate = (float) ($incomeTax['bands'][0]['rate'] ?? 0.20);
        $higherRate = (float) ($incomeTax['bands'][1]['rate'] ?? 0.40);
        $additionalRate = (float) ($incomeTax['additional_rate'] ?? 0.45);
        $marginalRate = $basicRate;
        if ($grossIncome > $additionalRateThreshold) {
            $marginalRate = $additionalRate;
        } elseif ($grossIncome > $basicRateLimit) {
            $marginalRate = $higherRate;
        }
        $taxRelief = $pensionAmount * $marginalRate;
        $rateLabel = $marginalRate === $additionalRate ? 'additional' : ($marginalRate === $higherRate ? 'higher' : 'basic');

        $trace[] = [
            'question' => 'What pension contribution and tax relief is available?',
            'data_field' => 'pension_amount',
            'data_value' => '£'.number_format($pensionAmount, 0).' contribution',
            'threshold' => '£'.number_format($annualAllowance, 0).' Annual Allowance',
            'passed' => true,
            'explanation' => $userName.' earns £'.number_format($grossIncome, 0).'/year ('.$rateLabel.' rate taxpayer). Contributing £'.number_format($pensionAmount, 0).' to a pension (capped by the £'.number_format($annualAllowance, 0).' Annual Allowance) could provide approximately £'.number_format($taxRelief, 0).' in '.$rateLabel.' rate tax relief (£'.number_format($pensionAmount, 0).' × '.number_format($marginalRate * 100, 0).'%).',
        ];

        $vars = [
            'pension_amount' => $this->formatCurrency($pensionAmount),
            'annual_allowance' => $this->formatCurrency($annualAllowance),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($taxRelief, 2);
        $rec['decision_trace'] = $trace;

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

            $goalName = $goal->goal_name ?? 'Unnamed goal';
            $targetAmount = (float) ($goal->target_amount ?? 0);
            $currentAmount = (float) ($goal->current_amount ?? 0);
            $progress = $goal->progress_percentage ?? 0;
            $targetDate = $goal->target_date ? Carbon::parse($goal->target_date)->format('d M Y') : 'no target date';

            $trace = [];
            $trace[] = [
                'question' => 'Which goal is missing a linked savings account?',
                'data_field' => 'goal_details',
                'data_value' => $goalName.' ('.$goal->goal_type.')',
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'Goal: "'.$goalName.'" (type: '.($goal->goal_type ?? 'unspecified').'). Target: £'.number_format($targetAmount, 0).'. Current: £'.number_format($currentAmount, 0).' ('.number_format($progress, 0).'% progress). Target date: '.$targetDate.'.',
            ];

            $trace[] = [
                'question' => 'Is this goal linked to a savings account?',
                'data_field' => 'linked_account',
                'data_value' => 'No linked account',
                'threshold' => 'At least one linked account',
                'passed' => true,
                'explanation' => '"'.$goalName.'" is not linked to any savings account. Without a linked account, progress tracking relies on manual updates rather than automatic balance monitoring.',
            ];

            $vars = [
                'goal_name' => $goalName,
                'target_amount' => $this->formatCurrency($targetAmount),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $rec['decision_trace'] = $trace;
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
            $goalName = $goal->goal_name ?? 'Unnamed goal';
            $targetDate = $goal->target_date ? Carbon::parse($goal->target_date)->format('d M Y') : 'no target date';
            $monthlyContribution = (float) ($goal->monthly_contribution ?? 0);

            $trace = [];

            // 1. Goal details
            $linkedAccounts = $goal->savingsAccounts;
            $linkedDetails = $linkedAccounts->isNotEmpty()
                ? $linkedAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ')
                : 'No linked accounts';

            $trace[] = [
                'question' => 'Which goal is significantly underfunded?',
                'data_field' => 'goal_details',
                'data_value' => $goalName.' — £'.number_format($currentAmount, 0).' of £'.number_format($targetAmount, 0),
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'Goal: "'.$goalName.'" (type: '.($goal->goal_type ?? 'unspecified').'). Target: £'.number_format($targetAmount, 0).'. Current: £'.number_format($currentAmount, 0).'. Target date: '.$targetDate.'. Monthly contribution: £'.number_format($monthlyContribution, 0).'. Linked accounts: '.$linkedDetails.'.',
            ];

            // 2. Progress assessment
            $trace[] = [
                'question' => 'Is the goal significantly underfunded?',
                'data_field' => 'progress_percentage',
                'data_value' => number_format($progress, 0).'%',
                'threshold' => 'Below 50%',
                'passed' => true,
                'explanation' => '"'.$goalName.'" is at '.number_format($progress, 0).'% progress (£'.number_format($currentAmount, 0).' of £'.number_format($targetAmount, 0).'). Shortfall: £'.number_format($targetAmount, 0).' − £'.number_format($currentAmount, 0).' = £'.number_format($shortfall, 0).'.',
            ];

            $vars = [
                'goal_name' => $goalName,
                'progress' => number_format($progress, 0),
                'shortfall' => $this->formatCurrency($shortfall),
                'target_amount' => $this->formatCurrency($targetAmount),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $rec['estimated_impact'] = round($shortfall, 2);
            $rec['decision_trace'] = $trace;
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
            $goalName = $goal->goal_name ?? 'Unnamed goal';
            $targetAmount = (float) ($goal->target_amount ?? 0);
            $currentAmount = (float) ($goal->current_amount ?? 0);
            $targetDate = $goal->target_date ? Carbon::parse($goal->target_date)->format('d M Y') : 'no target date';
            $monthsRemaining = $goal->months_remaining ?? 0;

            $trace = [];

            // 1. Goal details
            $trace[] = [
                'question' => 'Which goal is off track?',
                'data_field' => 'goal_details',
                'data_value' => $goalName.' — '.number_format($goal->progress_percentage, 0).'% progress',
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'Goal: "'.$goalName.'" (type: '.($goal->goal_type ?? 'unspecified').'). Target: £'.number_format($targetAmount, 0).'. Current: £'.number_format($currentAmount, 0).' ('.number_format($goal->progress_percentage, 0).'%). Target date: '.$targetDate.' ('.$monthsRemaining.' months remaining).',
            ];

            // 2. Contribution shortfall
            $trace[] = [
                'question' => 'How much more needs to be contributed each month?',
                'data_field' => 'monthly_shortfall',
                'data_value' => '£'.number_format($shortfall, 0).'/month shortfall',
                'threshold' => '£'.number_format($required, 0).' required monthly to stay on track',
                'passed' => true,
                'explanation' => 'Current contribution: £'.number_format($monthlyContribution, 0).'/month. Required: £'.number_format($required, 0).'/month. Shortfall: £'.number_format($required, 0).' − £'.number_format($monthlyContribution, 0).' = £'.number_format($shortfall, 0).'/month. Increasing contributions by £'.number_format($shortfall, 0).' would bring the goal back on track.',
            ];

            $vars = [
                'goal_name' => $goalName,
                'progress' => number_format($goal->progress_percentage, 0),
                'shortfall' => $this->formatCurrency($shortfall),
                'required_monthly' => $this->formatCurrency($required),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $rec['decision_trace'] = $trace;
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

            $goalName = $goal->goal_name ?? 'Unnamed goal';
            $targetAmount = (float) ($goal->target_amount ?? 0);
            $currentAmount = (float) ($goal->current_amount ?? 0);
            $targetDate = $goal->target_date ? Carbon::parse($goal->target_date)->format('d M Y') : 'no target date';
            $progress = $goal->progress_percentage ?? 0;
            $shortfall = max(0, $targetAmount - $currentAmount);

            $trace = [];

            // 1. Goal details
            $trace[] = [
                'question' => 'Which goal has no monthly contribution?',
                'data_field' => 'goal_details',
                'data_value' => $goalName.' — £'.number_format($currentAmount, 0).' of £'.number_format($targetAmount, 0).' ('.number_format($progress, 0).'%)',
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'Goal: "'.$goalName.'" (type: '.($goal->goal_type ?? 'unspecified').'). Target: £'.number_format($targetAmount, 0).'. Current: £'.number_format($currentAmount, 0).' ('.number_format($progress, 0).'% progress). Shortfall: £'.number_format($shortfall, 0).'. Target date: '.$targetDate.'.',
            ];

            // 2. Contribution gap
            $trace[] = [
                'question' => 'Is a monthly contribution set for this goal?',
                'data_field' => 'monthly_contribution',
                'data_value' => '£0/month',
                'threshold' => '£'.number_format($required, 0).'/month required',
                'passed' => true,
                'explanation' => 'No monthly contribution is set for "'.$goalName.'". To reach the £'.number_format($targetAmount, 0).' target by '.$targetDate.', a contribution of £'.number_format($required, 0).' per month is needed.',
            ];

            $vars = [
                'goal_name' => $goalName,
                'required_monthly' => $this->formatCurrency($required),
                'target_amount' => $this->formatCurrency($targetAmount),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $rec['decision_trace'] = $trace;
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

            $goalName = $goal->goal_name ?? 'Unnamed goal';
            $targetAmount = (float) ($goal->target_amount ?? 0);
            $currentAmount = (float) ($goal->current_amount ?? 0);
            $shortfall = max(0, $targetAmount - $currentAmount);
            $targetDate = $goal->target_date ? Carbon::parse($goal->target_date)->format('d M Y') : 'no target date';
            $monthlyContribution = (float) ($goal->monthly_contribution ?? 0);
            $required = $goal->required_monthly_contribution ?? 0;

            $trace = [];

            // 1. Goal details and deadline
            $trace[] = [
                'question' => 'Which goal has an approaching deadline with insufficient progress?',
                'data_field' => 'goal_details',
                'data_value' => $goalName.' — deadline '.$targetDate.' ('.$monthsRemaining.' months)',
                'threshold' => 'Within '.$monthsThreshold.' months',
                'passed' => true,
                'explanation' => 'Goal: "'.$goalName.'" (type: '.($goal->goal_type ?? 'unspecified').'). Target: £'.number_format($targetAmount, 0).'. Current: £'.number_format($currentAmount, 0).'. Shortfall: £'.number_format($shortfall, 0).'. Deadline: '.$targetDate.' ('.$monthsRemaining.' months away). Monthly contribution: £'.number_format($monthlyContribution, 0).'.',
            ];

            // 2. Progress vs expected
            $trace[] = [
                'question' => 'Is progress below the expected level for this deadline?',
                'data_field' => 'progress_percentage',
                'data_value' => number_format($progress, 0).'%',
                'threshold' => number_format($progressThreshold, 0).'% expected at this point',
                'passed' => true,
                'explanation' => 'At '.number_format($progress, 0).'% progress (£'.number_format($currentAmount, 0).' of £'.number_format($targetAmount, 0).') with only '.$monthsRemaining.' months remaining, progress is behind the '.number_format($progressThreshold, 0).'% expected level.'.($required > 0 ? ' Required monthly contribution: £'.number_format($required, 0).'.' : ''),
            ];

            $vars = [
                'goal_name' => $goalName,
                'months_remaining' => (string) $monthsRemaining,
                'progress' => number_format($progress, 0),
                'target_amount' => $this->formatCurrency($targetAmount),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'goal';
            $rec['goal_id'] = $goal->id;
            $rec['decision_trace'] = $trace;
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

            $childName = $child->first_name ?? $child->name ?? 'your child';
            $childAge = $child->date_of_birth ? (int) $child->date_of_birth->diffInYears(Carbon::now()) : null;
            $childAgeStr = $childAge !== null ? 'age '.$childAge : 'age unknown';
            $yearsToAdult = $childAge !== null ? max(0, 18 - $childAge) : null;

            $trace = [];

            // 1. Child details
            $trace[] = [
                'question' => 'Which child has no savings account?',
                'data_field' => 'child_details',
                'data_value' => $childName.', '.$childAgeStr,
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => $childName.' ('.$childAgeStr.', date of birth: '.$child->date_of_birth->format('d M Y').').'.($yearsToAdult !== null ? ' '.$yearsToAdult.' years until adulthood.' : ''),
            ];

            // 2. No account linked
            $trace[] = [
                'question' => 'Does this child have a savings account?',
                'data_field' => 'has_savings_account',
                'data_value' => 'No',
                'threshold' => 'At least one account',
                'passed' => true,
                'explanation' => $childName.' does not have a savings account linked to your plan. '.($yearsToAdult !== null && $yearsToAdult > 5 ? 'With '.$yearsToAdult.' years until adulthood, starting savings now benefits significantly from compound growth.' : 'Starting savings early benefits from compound growth.'),
            ];

            $vars = [
                'child_name' => $childName,
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['decision_trace'] = $trace;
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
            $childName = $child->first_name ?? $child->name ?? 'your child';
            $childAge = $child->date_of_birth ? (int) $child->date_of_birth->diffInYears(Carbon::now()) : null;
            $childAgeStr = $childAge !== null ? 'age '.$childAge : 'age unknown';
            $yearsToAdult = $childAge !== null ? max(0, 18 - $childAge) : null;

            // Check if child has any other savings
            $childAccounts = $savingsAccounts->where('beneficiary_id', $child->id);
            $childSavingsTotal = $childAccounts->sum('current_balance');

            $trace = [];

            // 1. Child details
            $trace[] = [
                'question' => 'Which child does not have a Junior ISA?',
                'data_field' => 'child_details',
                'data_value' => $childName.', '.$childAgeStr,
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => $childName.' ('.$childAgeStr.', date of birth: '.$child->date_of_birth->format('d M Y').').'.($yearsToAdult !== null ? ' '.$yearsToAdult.' years until the Junior ISA converts to an adult ISA at 18.' : '').' Existing savings accounts: '.($childAccounts->isNotEmpty() ? $childAccounts->count().' account(s) totalling £'.number_format((float) $childSavingsTotal, 0) : 'none').'.',
            ];

            // 2. No Junior ISA
            $trace[] = [
                'question' => 'Does this child have a Junior ISA?',
                'data_field' => 'has_junior_isa',
                'data_value' => 'No',
                'threshold' => 'At least one Junior ISA',
                'passed' => true,
                'explanation' => $childName.' does not have a Junior ISA. The annual Junior ISA allowance is £'.number_format($jisaAllowance, 0).' for the '.$this->taxConfig->getTaxYear().' tax year. All interest and growth within a Junior ISA is completely tax-free.',
            ];

            $vars = [
                'child_name' => $childName,
                'jisa_allowance' => $this->formatCurrency($jisaAllowance),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
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

            $childName = $child->first_name ?? $child->name ?? 'your child';
            $childAge = $child->date_of_birth ? (int) $child->date_of_birth->diffInYears(Carbon::now()) : null;
            $childAgeStr = $childAge !== null ? 'age '.$childAge : 'age unknown';
            $totalJisaBalance = $jisaAccounts->sum('current_balance');

            $trace = [];

            // 1. Child and JISA details
            $jisaDetails = $jisaAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');
            $trace[] = [
                'question' => 'Which child has unused Junior ISA allowance?',
                'data_field' => 'child_jisa_details',
                'data_value' => $childName.', '.$childAgeStr,
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => $childName.' ('.$childAgeStr.'). Junior ISA account(s): '.$jisaDetails.'. Total Junior ISA balance: £'.number_format((float) $totalJisaBalance, 0).'.',
            ];

            // 2. Allowance remaining
            $utilisationPercent = $jisaAllowance > 0 ? (((float) $subscriptionUsed / $jisaAllowance) * 100) : 0;
            $trace[] = [
                'question' => 'How much Junior ISA allowance remains for this tax year?',
                'data_field' => 'jisa_remaining',
                'data_value' => '£'.number_format($remaining, 0).' remaining ('.number_format($utilisationPercent, 0).'% used)',
                'threshold' => '£'.number_format($jisaAllowance, 0).' annual allowance ('.$taxYear.')',
                'passed' => true,
                'explanation' => 'For the '.$taxYear.' tax year, £'.number_format((float) $subscriptionUsed, 0).' of the £'.number_format($jisaAllowance, 0).' Junior ISA allowance has been subscribed ('.number_format($utilisationPercent, 0).'%). £'.number_format($remaining, 0).' remains to be used tax-free before the tax year ends.',
            ];

            $vars = [
                'child_name' => $childName,
                'jisa_remaining' => $this->formatCurrency($remaining),
                'jisa_allowance' => $this->formatCurrency($jisaAllowance),
                'tax_year' => $taxYear,
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
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

            $childName = $child->first_name ?? $child->name ?? 'your child';
            $turning18Date = $child->date_of_birth->addYears(18)->format('d M Y');

            // Check for children's savings accounts
            $user = User::find($userId);
            $childAccounts = $user ? $user->savingsAccounts()->where('beneficiary_id', $child->id)->get() : collect();
            $childSavingsTotal = $childAccounts->sum('current_balance');
            $hasJisa = $childAccounts->where('isa_type', 'junior')->isNotEmpty();
            $jisaBalance = $childAccounts->where('isa_type', 'junior')->sum('current_balance');

            $trace = [];

            // 1. Child details
            $trace[] = [
                'question' => 'Which child is approaching their 18th birthday?',
                'data_field' => 'child_details',
                'data_value' => $childName.', age '.$age.', turning 18 on '.$turning18Date,
                'threshold' => 'Within 12 months of 18th birthday',
                'passed' => true,
                'explanation' => $childName.' (date of birth: '.$child->date_of_birth->format('d M Y').', currently age '.$age.') turns 18 on '.$turning18Date.' — '.$monthsRemaining.' months away.',
            ];

            // 2. Savings position
            $accountDetails = $childAccounts->isNotEmpty()
                ? $childAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ')
                : 'No accounts';

            $trace[] = [
                'question' => 'What savings arrangements are in place for this child?',
                'data_field' => 'child_savings',
                'data_value' => $childAccounts->count().' account(s), total £'.number_format((float) $childSavingsTotal, 0),
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'Savings accounts for '.$childName.': '.$accountDetails.'.'.($hasJisa ? ' Junior ISA balance: £'.number_format((float) $jisaBalance, 0).' — this will automatically convert to an adult ISA at 18.' : ' No Junior ISA held.').' Review all children\'s savings arrangements before the transition to adult accounts.',
            ];

            $vars = [
                'child_name' => $childName,
                'months_to_18' => (string) $monthsRemaining,
                'turning_18_date' => $turning18Date,
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
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
        $trace = [];

        $children = $this->getMinorChildren($userId);
        if ($children->isEmpty()) {
            return [];
        }

        $childAccounts = $savingsAccounts->whereNotNull('beneficiary_id');
        if ($childAccounts->isEmpty()) {
            return [];
        }

        $totalChildSavings = $childAccounts->sum('current_balance');

        // 1. Children overview
        $childrenSummary = $children->map(function ($child) use ($savingsAccounts) {
            $name = $child->first_name ?? $child->name ?? 'Unknown';
            $age = $child->date_of_birth ? (int) $child->date_of_birth->diffInYears(Carbon::now()) : null;
            $ageStr = $age !== null ? 'age '.$age : 'age unknown';
            $childAccountCount = $savingsAccounts->where('beneficiary_id', $child->id)->count();
            $childBalance = $savingsAccounts->where('beneficiary_id', $child->id)->sum('current_balance');

            return $name.' ('.$ageStr.', '.$childAccountCount.' account(s), £'.number_format((float) $childBalance, 0).')';
        })->implode('; ');

        $trace[] = [
            'question' => 'Which children have savings accounts?',
            'data_field' => 'children_overview',
            'data_value' => $children->count().' child(ren), '.$childAccounts->count().' account(s)',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Children with savings: '.$childrenSummary.'.',
        ];

        // 2. Accounts detail and review recommendation
        $accountDetails = $childAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');
        $avgRate = $childAccounts->avg('interest_rate');

        $trace[] = [
            'question' => 'Would a periodic review of children\'s savings be beneficial?',
            'data_field' => 'child_accounts',
            'data_value' => $childAccounts->count().' account(s), total £'.number_format((float) $totalChildSavings, 0).', avg rate '.number_format((float) $avgRate * 100, 2).'%',
            'threshold' => 'At least 1 account',
            'passed' => true,
            'explanation' => 'Children\'s savings accounts: '.$accountDetails.'. Total: £'.number_format((float) $totalChildSavings, 0).' at an average rate of '.number_format((float) $avgRate * 100, 2).'%. A periodic review ensures rates remain competitive, Junior ISA allowances are used, and accounts are structured appropriately as children grow.',
        ];

        $vars = [
            'child_account_count' => (string) $childAccounts->count(),
            'total_child_savings' => $this->formatCurrency((float) $totalChildSavings),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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

        // 1. User PSA position
        $userName = $this->getUserName($user);
        $spouseName = $this->getUserName($spouse);
        $userIncome = $this->resolveGrossAnnualIncome($user);
        $spouseIncome = $this->resolveGrossAnnualIncome($spouse);

        $trace[] = [
            'question' => 'What is the primary user\'s Personal Savings Allowance position?',
            'data_field' => 'user_psa',
            'data_value' => $userName.' — '.number_format($userPsa['utilisation_percent'], 0).'% used',
            'threshold' => 'Breached or approaching',
            'passed' => true,
            'explanation' => $userName.' (gross income £'.number_format($userIncome, 0).', '.$userPsa['tax_band'].' rate taxpayer): Personal Savings Allowance £'.number_format($userPsa['psa_amount'], 0).', utilisation '.number_format($userPsa['utilisation_percent'], 0).'%, annual interest £'.number_format($userPsa['annual_interest'], 0).'.'.($userPsa['is_breached'] ? ' Breached by £'.number_format($userPsa['breach_amount'], 0).'.' : ' Headroom: £'.number_format($userPsa['headroom'], 0).'.'),
        ];

        // 2. Spouse PSA headroom
        $trace[] = [
            'question' => 'Does the spouse have Personal Savings Allowance headroom?',
            'data_field' => 'spouse_psa',
            'data_value' => $spouseName.' — '.number_format($spousePsa['utilisation_percent'], 0).'% used',
            'threshold' => 'Below 50%',
            'passed' => true,
            'explanation' => $spouseName.' (gross income £'.number_format($spouseIncome, 0).', '.$spousePsa['tax_band'].' rate taxpayer): Personal Savings Allowance £'.number_format($spousePsa['psa_amount'], 0).', utilisation '.number_format($spousePsa['utilisation_percent'], 0).'%, headroom £'.number_format($spousePsa['headroom'], 0).'. Holding savings in '.$spouseName.'\'s name could shelter up to £'.number_format($spousePsa['headroom'], 0).' additional interest from tax.',
        ];

        $vars = [
            'user_utilisation' => number_format($userPsa['utilisation_percent'], 0),
            'spouse_headroom' => $this->formatCurrency($spousePsa['headroom']),
            'spouse_psa' => $this->formatCurrency($spousePsa['psa_amount']),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

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
        $taxYear = $this->taxConfig->getTaxYear();

        // Estimate spouse ISA usage from their savings accounts
        $spouseIsaUsed = (float) \App\Models\SavingsAccount::where('user_id', $user->spouse_id)
            ->where('is_isa', true)
            ->where('isa_subscription_year', $taxYear)
            ->sum('isa_subscription_amount');

        $spouseIsaRemaining = max(0, $totalAllowance - $spouseIsaUsed);
        $combinedRemaining = $userIsaRemaining + $spouseIsaRemaining;

        // Only trigger if combined remaining is meaningful
        if ($combinedRemaining < 5000) {
            return [];
        }

        $spouse = User::find($user->spouse_id);
        $userName = $this->getUserName($user);
        $spouseName = $spouse ? $this->getUserName($spouse) : 'Spouse';
        $userIsaUsed = $savingsAnalysis['isa_allowance']['used'] ?? 0;

        // 1. User ISA position
        $trace[] = [
            'question' => 'What is the primary user\'s ISA position?',
            'data_field' => 'user_isa',
            'data_value' => $userName.' — £'.number_format($userIsaRemaining, 0).' remaining',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => $userName.': £'.number_format((float) $userIsaUsed, 0).' of £'.number_format($totalAllowance, 0).' ISA allowance used this '.$taxYear.' tax year. Remaining: £'.number_format($userIsaRemaining, 0).'.',
        ];

        // 2. Spouse ISA position
        $trace[] = [
            'question' => 'What is the spouse\'s ISA position?',
            'data_field' => 'spouse_isa',
            'data_value' => $spouseName.' — £'.number_format($spouseIsaRemaining, 0).' remaining',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => $spouseName.': £'.number_format($spouseIsaUsed, 0).' of £'.number_format($totalAllowance, 0).' ISA allowance used this '.$taxYear.' tax year. Remaining: £'.number_format($spouseIsaRemaining, 0).'.',
        ];

        // 3. Combined household position
        $combinedAllowance = $totalAllowance * 2;
        $combinedUsed = (float) $userIsaUsed + $spouseIsaUsed;
        $utilisationPercent = $combinedAllowance > 0 ? ($combinedUsed / $combinedAllowance) * 100 : 0;

        $trace[] = [
            'question' => 'What is the combined household ISA allowance position?',
            'data_field' => 'combined_remaining',
            'data_value' => '£'.number_format($combinedRemaining, 0).' combined remaining ('.number_format($utilisationPercent, 0).'% used)',
            'threshold' => '£5,000 combined minimum',
            'passed' => true,
            'explanation' => 'Household ISA allowance: £'.number_format($combinedAllowance, 0).' (£'.number_format($totalAllowance, 0).' each). Used: £'.number_format($combinedUsed, 0).' ('.number_format($utilisationPercent, 0).'%). Remaining: £'.number_format($combinedRemaining, 0).'. Coordinating ISA contributions between '.$userName.' and '.$spouseName.' can maximise the household\'s tax-free savings.',
        ];

        $vars = [
            'user_isa_remaining' => $this->formatCurrency($userIsaRemaining),
            'spouse_isa_remaining' => $this->formatCurrency($spouseIsaRemaining),
            'combined_remaining' => $this->formatCurrency($combinedRemaining),
            'tax_year' => $taxYear,
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
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
        $trace = [];

        $monthlyContribution = $goal['monthly_contribution'] ?? 0;
        $required = $goal['required_monthly_contribution'] ?? 0;

        if ($monthlyContribution > 0 || $required <= 0) {
            return null;
        }

        $goalName = $goal['name'] ?? 'Unnamed goal';
        $targetAmount = (float) ($goal['target_amount'] ?? 0);
        $currentAmount = (float) ($goal['current_amount'] ?? 0);
        $progress = (float) ($goal['progress_percentage'] ?? 0);
        $shortfall = max(0, $targetAmount - $currentAmount);

        $trace[] = [
            'question' => 'Which linked goal has no monthly contribution?',
            'data_field' => 'goal_details',
            'data_value' => $goalName.' — £'.number_format($currentAmount, 0).' of £'.number_format($targetAmount, 0).' ('.number_format($progress, 0).'%)',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Goal: "'.$goalName.'". Target: £'.number_format($targetAmount, 0).'. Current: £'.number_format($currentAmount, 0).' ('.number_format($progress, 0).'% progress). Shortfall: £'.number_format($shortfall, 0).'.',
        ];

        $trace[] = [
            'question' => 'Is a monthly contribution set?',
            'data_field' => 'monthly_contribution',
            'data_value' => '£0/month',
            'threshold' => '£'.number_format((float) $required, 0).'/month required',
            'passed' => true,
            'explanation' => '"'.$goalName.'" has no monthly contribution set but needs £'.number_format((float) $required, 0).'/month to reach its £'.number_format($targetAmount, 0).' target.',
        ];

        $vars = [
            'goal_name' => $goalName,
            'required_monthly' => $this->formatCurrency((float) $required),
            'target_amount' => $this->formatCurrency($targetAmount),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal off track: triggers when goal is_on_track is false and has contributions.
     */
    private function evaluateLinkedGoalOffTrack(SavingsActionDefinition $definition, array $goal): ?array
    {
        $trace = [];

        $monthlyContribution = $goal['monthly_contribution'] ?? 0;

        // Skip if no contribution (caught by no-contribution check)
        if ($monthlyContribution <= 0) {
            return null;
        }

        if ($goal['is_on_track'] ?? true) {
            return null;
        }

        $goalName = $goal['name'] ?? 'Unnamed goal';
        $required = $goal['required_monthly_contribution'] ?? 0;
        $shortfall = max(0, $required - $monthlyContribution);
        $progress = $goal['progress_percentage'] ?? 0;
        $targetAmount = (float) ($goal['target_amount'] ?? 0);
        $currentAmount = (float) ($goal['current_amount'] ?? 0);
        $monthsRemaining = $goal['months_remaining'] ?? 0;

        // 1. Goal details
        $trace[] = [
            'question' => 'Which linked goal is off track?',
            'data_field' => 'goal_details',
            'data_value' => $goalName.' — '.number_format((float) $progress, 0).'% progress',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Goal: "'.$goalName.'". Target: £'.number_format($targetAmount, 0).'. Current: £'.number_format($currentAmount, 0).' ('.number_format((float) $progress, 0).'%).'.($monthsRemaining > 0 ? ' '.$monthsRemaining.' months remaining.' : ''),
        ];

        // 2. Contribution shortfall
        $trace[] = [
            'question' => 'What is the monthly contribution shortfall?',
            'data_field' => 'contribution_shortfall',
            'data_value' => '£'.number_format((float) $monthlyContribution, 0).'/month current vs £'.number_format((float) $required, 0).'/month required',
            'threshold' => 'On track',
            'passed' => true,
            'explanation' => 'Contributing £'.number_format((float) $monthlyContribution, 0).'/month but £'.number_format((float) $required, 0).'/month is needed. Shortfall: £'.number_format((float) $required, 0).' − £'.number_format((float) $monthlyContribution, 0).' = £'.number_format((float) $shortfall, 0).'/month.',
        ];

        $vars = [
            'goal_name' => $goalName,
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
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal deadline approaching: triggers when months remaining and progress below thresholds.
     */
    private function evaluateLinkedGoalDeadline(SavingsActionDefinition $definition, array $goal, array $config): ?array
    {
        $trace = [];

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

        $goalName = $goal['name'] ?? 'Unnamed goal';
        $targetAmount = (float) ($goal['target_amount'] ?? 0);
        $currentAmount = (float) ($goal['current_amount'] ?? 0);
        $shortfall = max(0, $targetAmount - $currentAmount);
        $monthlyContribution = (float) ($goal['monthly_contribution'] ?? 0);

        // 1. Goal details and deadline
        $trace[] = [
            'question' => 'Which goal has an approaching deadline?',
            'data_field' => 'goal_details',
            'data_value' => $goalName.' — '.$monthsRemaining.' months remaining, '.number_format((float) $progress, 0).'% progress',
            'threshold' => 'Within '.$monthsThreshold.' months and below '.number_format($progressThreshold, 0).'% progress',
            'passed' => true,
            'explanation' => 'Goal: "'.$goalName.'". Target: £'.number_format($targetAmount, 0).'. Current: £'.number_format($currentAmount, 0).' ('.number_format((float) $progress, 0).'%). Shortfall: £'.number_format($shortfall, 0).'. Time remaining: '.$monthsRemaining.' months. Monthly contribution: £'.number_format($monthlyContribution, 0).'.',
        ];

        $vars = [
            'goal_name' => $goalName,
            'progress' => number_format((float) $progress, 0),
            'months_remaining' => (string) $monthsRemaining,
            'target_amount' => $this->formatCurrency($targetAmount),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal underfunded: triggers when progress is below 25% and target date is set.
     */
    private function evaluateLinkedGoalUnderfunded(SavingsActionDefinition $definition, array $goal): ?array
    {
        $trace = [];

        $progress = $goal['progress_percentage'] ?? 0;
        $targetAmount = $goal['target_amount'] ?? 0;

        if ($progress >= 25 || $targetAmount <= 0) {
            return null;
        }

        $goalName = $goal['name'] ?? 'Unnamed goal';
        $currentAmount = $goal['current_amount'] ?? 0;
        $shortfall = max(0, $targetAmount - $currentAmount);
        $monthlyContribution = (float) ($goal['monthly_contribution'] ?? 0);
        $monthsRemaining = $goal['months_remaining'] ?? 0;

        // 1. Goal details
        $trace[] = [
            'question' => 'Which linked goal is significantly underfunded?',
            'data_field' => 'goal_details',
            'data_value' => $goalName.' — £'.number_format((float) $currentAmount, 0).' of £'.number_format((float) $targetAmount, 0),
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Goal: "'.$goalName.'". Target: £'.number_format((float) $targetAmount, 0).'. Current: £'.number_format((float) $currentAmount, 0).' ('.number_format((float) $progress, 0).'%). Monthly contribution: £'.number_format($monthlyContribution, 0).'.'.($monthsRemaining > 0 ? ' '.$monthsRemaining.' months remaining.' : ''),
        ];

        // 2. Underfunding assessment
        $trace[] = [
            'question' => 'How significantly underfunded is this goal?',
            'data_field' => 'progress_percentage',
            'data_value' => number_format((float) $progress, 0).'%',
            'threshold' => 'Below 25%',
            'passed' => true,
            'explanation' => '"'.$goalName.'" is at '.number_format((float) $progress, 0).'% progress. Shortfall: £'.number_format((float) $targetAmount, 0).' − £'.number_format((float) $currentAmount, 0).' = £'.number_format((float) $shortfall, 0).'. This is well below the 25% threshold, indicating significant underfunding.',
        ];

        $vars = [
            'goal_name' => $goalName,
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
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal nearly complete: triggers when goal progress is above threshold.
     * Provides encouragement and suggests next steps.
     */
    private function evaluateLinkedGoalNearlyComplete(SavingsActionDefinition $definition, array $goal, array $config): ?array
    {
        $trace = [];

        $progress = $goal['progress_percentage'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 90);

        if ($progress < $threshold || $progress >= 100) {
            return null;
        }

        $goalName = $goal['name'] ?? 'Unnamed goal';
        $targetAmount = $goal['target_amount'] ?? 0;
        $currentAmount = $goal['current_amount'] ?? 0;
        $remaining = max(0, $targetAmount - $currentAmount);
        $monthlyContribution = (float) ($goal['monthly_contribution'] ?? 0);
        $monthsToComplete = $monthlyContribution > 0 ? ceil($remaining / $monthlyContribution) : 0;

        // 1. Goal details
        $trace[] = [
            'question' => 'Which goal is nearly complete?',
            'data_field' => 'goal_details',
            'data_value' => $goalName.' — '.number_format((float) $progress, 0).'% progress',
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Goal: "'.$goalName.'". Target: £'.number_format((float) $targetAmount, 0).'. Current: £'.number_format((float) $currentAmount, 0).' ('.number_format((float) $progress, 0).'%). Remaining: £'.number_format((float) $remaining, 0).'. Monthly contribution: £'.number_format($monthlyContribution, 0).'.',
        ];

        // 2. Completion estimate
        $trace[] = [
            'question' => 'How close is this goal to completion?',
            'data_field' => 'progress_percentage',
            'data_value' => number_format((float) $progress, 0).'%',
            'threshold' => number_format($threshold, 0).'% or above',
            'passed' => true,
            'explanation' => '"'.$goalName.'" is at '.number_format((float) $progress, 0).'% progress with just £'.number_format((float) $remaining, 0).' remaining to reach the £'.number_format((float) $targetAmount, 0).' target.'.($monthsToComplete > 0 ? ' At the current rate of £'.number_format($monthlyContribution, 0).'/month, completion is estimated in approximately '.$monthsToComplete.' month(s).' : ''),
        ];

        $vars = [
            'goal_name' => $goalName,
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
            'decision_trace' => $trace,
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

    /**
     * Build a user profile trace entry with name, age, employment, and income context.
     */
    private function buildUserProfileTrace(User $user): array
    {
        $userName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: 'Unknown user';
        $age = $user->date_of_birth ? (int) $user->date_of_birth->diffInYears(Carbon::now()) : null;
        $ageStr = $age !== null ? 'age '.$age : 'age unknown';
        $employmentStatus = $user->employment_status ?: 'not set';
        $grossIncome = $this->resolveGrossAnnualIncome($user);
        $resolved = $this->resolveMonthlyExpenditure($user);
        $monthlyExpenditure = $resolved['amount'];

        $profileParts = [$userName, $ageStr, $employmentStatus];
        if ($grossIncome > 0) {
            $profileParts[] = 'gross income £'.number_format($grossIncome, 0).'/year';
        }
        if ($monthlyExpenditure > 0) {
            $profileParts[] = 'monthly expenditure £'.number_format($monthlyExpenditure, 0);
        }

        return [
            'question' => 'Who is this assessment for?',
            'data_field' => 'user_profile',
            'data_value' => implode(', ', $profileParts),
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Assessing savings for '.$userName.', '.$ageStr.', '.$employmentStatus.($grossIncome > 0 ? ', earning £'.number_format($grossIncome, 0).' per year gross' : ', no income recorded').($monthlyExpenditure > 0 ? ', spending £'.number_format($monthlyExpenditure, 0).' per month' : ', no expenditure recorded').'.',
        ];
    }

    /**
     * Build a formatted string describing a single savings account.
     */
    private function formatAccountDescription($account): string
    {
        $name = $account->account_name ?? 'Unnamed account';
        $institution = $account->institution ?? 'unknown provider';
        $balance = (float) ($account->current_balance ?? 0);
        $rate = ((float) ($account->interest_rate ?? 0)) * 100;
        $type = $account->account_type ?? 'savings';
        $isIsa = $account->is_isa ? ' (ISA'.($account->isa_type ? ' — '.$account->isa_type : '').')' : '';
        $access = $account->access_type ? ', '.$account->access_type.' access' : '';
        $emergency = $account->is_emergency_fund ? ', emergency fund' : '';
        $joint = $account->joint_owner_id ? ', joint ('.($account->ownership_percentage ?? 50).'% share)' : '';

        return $name.' at '.$institution.' — £'.number_format($balance, 0).', '.number_format($rate, 2).'%'.$isIsa.$access.$emergency.$joint;
    }

    /**
     * Build a trace entry listing emergency fund accounts.
     */
    private function buildEmergencyFundAccountsTrace(Collection $savingsAccounts): array
    {
        $emergencyAccounts = $savingsAccounts->where('is_emergency_fund', true);
        if ($emergencyAccounts->isEmpty()) {
            return [
                'question' => 'Which accounts are designated as emergency fund?',
                'data_field' => 'emergency_fund_accounts',
                'data_value' => 'None designated',
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'No savings accounts are currently designated as emergency fund. Total savings across all '.$savingsAccounts->count().' account(s) may be used as a proxy.',
            ];
        }

        $accountDescriptions = $emergencyAccounts->map(fn ($a) => $this->formatAccountDescription($a))->implode('; ');
        $totalBalance = $emergencyAccounts->sum('current_balance');

        return [
            'question' => 'Which accounts are designated as emergency fund?',
            'data_field' => 'emergency_fund_accounts',
            'data_value' => $emergencyAccounts->count().' account(s), total £'.number_format((float) $totalBalance, 0),
            'threshold' => 'N/A',
            'passed' => true,
            'explanation' => 'Emergency fund accounts: '.$accountDescriptions.'. Combined balance: £'.number_format((float) $totalBalance, 0).'.',
        ];
    }

    /**
     * Get formatted user name from a User model.
     */
    private function getUserName(User $user): string
    {
        return trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: 'Unknown user';
    }

    /**
     * Build a trace entry explaining the employment-based emergency fund target.
     */
    private function buildEmploymentTargetTrace(?User $user, int $targetMonths): array
    {
        $employmentStatus = ($user && $user->employment_status) ? $user->employment_status : 'not set';

        return [
            'question' => 'What is the recommended emergency fund target based on employment status?',
            'data_field' => 'employment_status',
            'data_value' => $employmentStatus.' → '.$targetMonths.' months',
            'threshold' => 'Employed = 6 months, self-employed/contractor = 9 months, retired = 6 months',
            'passed' => true,
            'explanation' => 'Employment status "'.$employmentStatus.'" maps to a '.$targetMonths.'-month emergency fund target. Self-employed and contractors need 9 months due to income volatility; employed and retired need 6 months.',
        ];
    }
}
