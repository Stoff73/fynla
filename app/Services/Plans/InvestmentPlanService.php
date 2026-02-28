<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\InvestmentAgent;
use App\Agents\SavingsAgent;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\SavingsAccount;
use App\Models\User;

class InvestmentPlanService extends BasePlanService
{
    public function __construct(
        private readonly InvestmentAgent $investmentAgent,
        private readonly SavingsAgent $savingsAgent
    ) {}

    public function generatePlan(int $userId, array $options = []): array
    {
        $user = User::findOrFail($userId);
        $completeness = $this->checkDataCompleteness($userId);

        $investmentAnalysis = $this->investmentAgent->analyze($userId);
        $savingsAnalysis = $this->savingsAgent->analyze($userId);

        $investmentAccounts = InvestmentAccount::where('user_id', $userId)
            ->orWhere('joint_owner_id', $userId)
            ->with('holdings')
            ->get();

        $savingsAccounts = SavingsAccount::where('user_id', $userId)
            ->orWhere('joint_owner_id', $userId)
            ->get();

        $currentSituation = $this->buildCurrentSituation(
            $investmentAnalysis,
            $savingsAnalysis,
            $investmentAccounts,
            $savingsAccounts
        );

        $recommendations = $this->getRecommendations($userId);
        $actions = $this->structureActions($recommendations, 'investment');
        $actions = $this->applyActionFilter($actions, $options);
        $enabledActions = collect($actions)->where('enabled', true)->values()->toArray();

        $whatIf = $this->buildWhatIfData($investmentAnalysis, $savingsAnalysis, $currentSituation, $enabledActions);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'investment');

        return [
            'metadata' => $this->buildPlanMetadata($user, 'investment', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $this->buildExecutiveSummary($user, $investmentAnalysis, $savingsAnalysis, $investmentAccounts, $savingsAccounts),
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
        ];
    }

    public function getRecommendations(int $userId): array
    {
        $investmentAnalysis = $this->investmentAgent->analyze($userId);
        $savingsAnalysis = $this->savingsAgent->analyze($userId);

        $investmentResult = $this->investmentAgent->generateRecommendations($investmentAnalysis);
        $investmentRecs = $investmentResult['recommendations'] ?? [];
        $savingsRecs = $this->buildSavingsRecommendations($savingsAnalysis);

        return array_merge($investmentRecs, $savingsRecs);
    }

    public function checkDataCompleteness(int $userId): array
    {
        $missing = [];
        $checks = [
            'investment_accounts' => InvestmentAccount::where('user_id', $userId)->exists(),
            'risk_profile' => RiskProfile::where('user_id', $userId)->exists(),
            'savings_accounts' => SavingsAccount::where('user_id', $userId)->exists(),
            'income' => User::where('id', $userId)
                ->where(function ($q) {
                    $q->whereNotNull('annual_employment_income')
                        ->orWhereNotNull('annual_self_employment_income');
                })->exists(),
        ];

        $hasHoldings = false;
        if ($checks['investment_accounts']) {
            $hasHoldings = InvestmentAccount::where('user_id', $userId)
                ->whereHas('holdings')
                ->exists();
            if (! $hasHoldings) {
                $missing[] = [
                    'field' => 'holdings',
                    'label' => 'Investment holdings',
                    'description' => 'Add holdings to your investment accounts for detailed analysis.',
                    'link' => '/investments',
                ];
            }
        }

        if (! $checks['investment_accounts']) {
            $missing[] = [
                'field' => 'investment_accounts',
                'label' => 'Investment accounts',
                'description' => 'Add your investment accounts to receive portfolio analysis.',
                'link' => '/investments',
            ];
        }

        if (! $checks['risk_profile']) {
            $missing[] = [
                'field' => 'risk_profile',
                'label' => 'Risk profile',
                'description' => 'Complete the risk questionnaire for personalised allocation recommendations.',
                'link' => '/investments',
            ];
        }

        if (! $checks['savings_accounts']) {
            $missing[] = [
                'field' => 'savings_accounts',
                'label' => 'Savings accounts',
                'description' => 'Add your savings accounts for emergency fund analysis.',
                'link' => '/savings',
            ];
        }

        if (! $checks['income']) {
            $missing[] = [
                'field' => 'income',
                'label' => 'Income details',
                'description' => 'Add your income for accurate emergency fund runway calculations.',
                'link' => '/profile',
            ];
        }

        $total = count($checks) + 1; // +1 for holdings
        $present = $total - count($missing);

        return [
            'percentage' => $total > 0 ? round(($present / $total) * 100) : 0,
            'missing' => $missing,
            'complete' => empty($missing),
        ];
    }

    private function buildExecutiveSummary(
        User $user,
        array $investmentAnalysis,
        array $savingsAnalysis,
        $investmentAccounts,
        $savingsAccounts
    ): array {
        $firstName = $user->first_name ?? explode(' ', $user->name)[0] ?? 'there';
        $totalInvestmentValue = $investmentAccounts->sum('current_value');
        $totalSavingsValue = $savingsAccounts->sum('current_balance');
        $totalWealth = $totalInvestmentValue + $totalSavingsValue;
        $emergencyRunway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $investmentCount = $investmentAccounts->count();
        $savingsCount = $savingsAccounts->count();
        $totalAccounts = $investmentCount + $savingsCount;

        $lines = [];
        $lines[] = "Dear {$firstName},";
        $lines[] = '';
        $lines[] = 'Thank you for using Fynla. Here is your personalised Investment Plan based on the accounts and holdings you have provided.';
        $lines[] = '';

        // Portfolio overview
        if ($investmentCount > 0 && $savingsCount > 0) {
            $lines[] = sprintf(
                'You currently hold %d investment %s valued at %s and %d savings %s totalling %s, giving you a combined portfolio of %s.',
                $investmentCount,
                $investmentCount === 1 ? 'account' : 'accounts',
                $this->formatCurrency($totalInvestmentValue),
                $savingsCount,
                $savingsCount === 1 ? 'account' : 'accounts',
                $this->formatCurrency($totalSavingsValue),
                $this->formatCurrency($totalWealth)
            );
        } elseif ($investmentCount > 0) {
            $lines[] = sprintf(
                'You currently hold %d investment %s valued at %s.',
                $investmentCount,
                $investmentCount === 1 ? 'account' : 'accounts',
                $this->formatCurrency($totalInvestmentValue)
            );
        } elseif ($savingsCount > 0) {
            $lines[] = sprintf(
                'You currently hold %d savings %s totalling %s.',
                $savingsCount,
                $savingsCount === 1 ? 'account' : 'accounts',
                $this->formatCurrency($totalSavingsValue)
            );
        }

        // Name specific account types
        $accountTypes = $investmentAccounts->pluck('account_type')->filter()->unique()->values();
        if ($accountTypes->isNotEmpty()) {
            $typeLabels = $accountTypes->map(fn ($t) => ucfirst(str_replace('_', ' ', $t)))->toArray();
            $lines[] = 'Your investment accounts include '.implode(', ', $typeLabels).'.';
        }

        // Emergency fund
        if ($emergencyRunway >= 6) {
            $lines[] = sprintf(
                'Your emergency fund is in a strong position, covering approximately %.0f months of expenses — well above the recommended 6-month target.',
                $emergencyRunway
            );
        } elseif ($emergencyRunway >= 3) {
            $lines[] = sprintf(
                'Your emergency fund currently covers around %.0f months of expenses. While this provides a reasonable buffer, building it to at least 6 months would strengthen your financial resilience.',
                $emergencyRunway
            );
        } elseif ($emergencyRunway > 0) {
            $lines[] = sprintf(
                'Your emergency fund covers only %.0f month(s) of expenses, which is below the recommended minimum of 3 months. Building this up should be a priority.',
                $emergencyRunway
            );
        } else {
            $lines[] = 'You do not currently have a dedicated emergency fund. Establishing one to cover at least 3 to 6 months of expenses should be a priority.';
        }

        // ISA allowance
        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        $isaUsed = $savingsAnalysis['isa_allowance']['total_used'] ?? 0;
        if ($isaUsed > 0 || $isaRemaining > 0) {
            $lines[] = sprintf(
                'You have used %s of your ISA allowance this tax year, with %s remaining.',
                $this->formatCurrency($isaUsed),
                $this->formatCurrency($isaRemaining)
            );
        }

        // Risk profile
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        if ($riskProfile) {
            $riskLevel = ucfirst($riskProfile->risk_tolerance ?? 'moderate');
            $lines[] = "Based on your risk questionnaire, your risk profile is {$riskLevel}.";
        }

        // Point to detail below
        $lines[] = '';
        $lines[] = 'The sections below provide a detailed breakdown of your current holdings, asset allocation, fees, and specific recommendations to improve your investment position.';

        return [
            'narrative' => implode("\n", $lines),
            'key_metrics' => [],
        ];
    }

    private function buildCurrentSituation(
        array $investmentAnalysis,
        array $savingsAnalysis,
        $investmentAccounts,
        $savingsAccounts
    ): array {
        $accounts = $investmentAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->account_name,
                'type' => $account->account_type,
                'provider' => $account->provider,
                'value' => $this->roundToPenny((float) $account->current_value),
                'holdings_count' => $account->holdings->count(),
            ];
        })->toArray();

        $savings = $savingsAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'institution' => $account->institution,
                'type' => $account->account_type,
                'balance' => $this->roundToPenny((float) $account->current_balance),
                'interest_rate' => (float) ($account->interest_rate ?? 0),
            ];
        })->toArray();

        return [
            'investment_accounts' => $accounts,
            'savings_accounts' => $savings,
            'asset_allocation' => $investmentAnalysis['asset_allocation'] ?? [],
            'fee_analysis' => $investmentAnalysis['fee_analysis'] ?? [],
            'diversification_score' => $investmentAnalysis['diversification_score'] ?? null,
            'tax_wrappers' => $investmentAnalysis['tax_wrappers'] ?? [],
            'emergency_fund' => [
                'runway_months' => $savingsAnalysis['emergency_fund']['runway_months'] ?? 0,
                'category' => $savingsAnalysis['emergency_fund']['category'] ?? 'Unknown',
                'total_savings' => $savingsAnalysis['summary']['total_savings'] ?? 0,
            ],
            'isa_allowance' => [
                'used' => $savingsAnalysis['isa_allowance']['total_used'] ?? 0,
                'remaining' => $savingsAnalysis['isa_allowance']['remaining'] ?? 0,
            ],
            'total_investment_value' => $this->roundToPenny($investmentAccounts->sum('current_value')),
            'total_savings_value' => $this->roundToPenny($savingsAccounts->sum('current_balance')),
        ];
    }

    private function buildSavingsRecommendations(array $savingsAnalysis): array
    {
        $recommendations = [];

        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        if ($runway < 3) {
            $recommendations[] = [
                'category' => 'Emergency Fund',
                'priority' => 'critical',
                'title' => 'Build Emergency Fund to 3 Months',
                'description' => 'Your emergency fund is critically low. Prioritise building savings to cover at least 3 months of expenses.',
                'action' => 'Set up a monthly standing order to your savings account.',
            ];
        } elseif ($runway < 6) {
            $recommendations[] = [
                'category' => 'Emergency Fund',
                'priority' => 'high',
                'title' => 'Grow Emergency Fund to 6 Months',
                'description' => sprintf('Your emergency fund covers %.0f months. The recommended target is 6 months of expenses.', $runway),
                'action' => 'Continue building your emergency fund alongside other goals.',
            ];
        }

        $rateComparisons = $savingsAnalysis['rate_comparisons'] ?? [];
        $lowRateAccounts = collect($rateComparisons)->filter(function ($comp) {
            return ($comp['comparison']['rating'] ?? '') === 'Poor';
        });

        if ($lowRateAccounts->isNotEmpty()) {
            $totalGain = $lowRateAccounts->sum('potential_gain');
            $recommendations[] = [
                'category' => 'Interest Rate',
                'priority' => 'medium',
                'title' => 'Switch to Higher-Rate Savings Accounts',
                'description' => sprintf('You could earn an additional %s per year by moving to better-rate accounts.', $this->formatCurrency($totalGain)),
                'action' => 'Compare savings rates and switch accounts with below-market rates.',
                'estimated_impact' => $this->roundToPenny($totalGain),
            ];
        }

        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        if ($isaRemaining > 0 && $runway >= 6) {
            $recommendations[] = [
                'category' => 'ISA Allowance',
                'priority' => 'medium',
                'title' => 'Use Remaining ISA Allowance',
                'description' => sprintf('You have %s of ISA allowance remaining this tax year. Use it for tax-free growth.', $this->formatCurrency($isaRemaining)),
                'action' => 'Transfer savings into a Cash ISA or Stocks & Shares ISA.',
            ];
        }

        return $recommendations;
    }

    private function buildWhatIfData(
        array $investmentAnalysis,
        array $savingsAnalysis,
        array $currentSituation,
        array $enabledActions
    ): array {
        $totalInvestment = $currentSituation['total_investment_value'];
        $totalSavings = $currentSituation['total_savings_value'];
        $emergencyMonths = $currentSituation['emergency_fund']['runway_months'] ?? 0;

        $feeReduction = 0;
        $additionalSavings = 0;

        foreach ($enabledActions as $action) {
            $category = strtolower($action['category'] ?? '');
            if (str_contains($category, 'fee')) {
                $feeReduction += ($action['estimated_impact'] ?? 200);
            }
            if (str_contains($category, 'emergency') || str_contains($category, 'saving')) {
                $additionalSavings += 500;
            }
        }

        return [
            'current_scenario' => [
                'total_wealth' => $this->roundToPenny($totalInvestment + $totalSavings),
                'annual_fees' => $this->roundToPenny($investmentAnalysis['fee_analysis']['total_annual_fees'] ?? 0),
                'emergency_fund_months' => round($emergencyMonths, 1),
                'projected_5yr_value' => $this->roundToPenny($this->projectValue($totalInvestment, 0.05, 5, 0)),
            ],
            'projected_scenario' => [
                'total_wealth' => $this->roundToPenny($totalInvestment + $totalSavings + ($additionalSavings * 12)),
                'annual_fees' => $this->roundToPenny(max(0, ($investmentAnalysis['fee_analysis']['total_annual_fees'] ?? 0) - $feeReduction)),
                'emergency_fund_months' => round($emergencyMonths + ($additionalSavings > 0 ? 2 : 0), 1),
                'projected_5yr_value' => $this->roundToPenny($this->projectValue($totalInvestment, 0.05, 5, $additionalSavings)),
            ],
            'is_approximate' => true,
            'frontend_calc_params' => [
                'current_value' => $totalInvestment,
                'growth_rate' => 0.05,
                'years' => 5,
            ],
        ];
    }

    /**
     * Simple future value projection: FV = PV*(1+r)^n + PMT*((1+r)^n - 1)/r
     */
    private function projectValue(float $presentValue, float $rate, int $years, float $monthlyContribution): float
    {
        if ($rate <= 0) {
            return $presentValue + ($monthlyContribution * 12 * $years);
        }

        $monthlyRate = $rate / 12;
        $months = $years * 12;
        $fvLumpSum = $presentValue * pow(1 + $monthlyRate, $months);
        $fvContributions = $monthlyContribution * ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);

        return $fvLumpSum + $fvContributions;
    }
}
