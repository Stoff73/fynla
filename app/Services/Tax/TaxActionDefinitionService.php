<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Constants\TaxDefaults;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;
use App\Traits\StructuredLogging;

/**
 * Evaluates tax action definitions against user data
 * to produce configurable, database-driven tax optimisation recommendations.
 *
 * Mirrors InvestmentActionDefinitionService — each trigger condition
 * maps to one private evaluator method that checks the condition
 * and returns zero or more recommendations.
 */
class TaxActionDefinitionService
{
    use FormatsCurrency;
    use StructuredLogging;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly AnnualAllowanceChecker $allowanceChecker
    ) {}

    /**
     * Evaluate all enabled tax action definitions against a user's data.
     *
     * @return array{recommendations: array, total_count: int, high_priority_count: int}
     */
    public function evaluateActions(User $user): array
    {
        $definitions = TaxActionDefinition::getEnabledBySource('agent');
        $recommendations = [];
        $priority = 1;

        foreach ($definitions as $definition) {
            $results = $this->evaluateTrigger($definition, $user, $priority);

            foreach ($results as $rec) {
                $recommendations[] = $rec;
                $priority++;
            }
        }

        return [
            'recommendations' => $recommendations,
            'total_count' => count($recommendations),
            'high_priority_count' => count(array_filter($recommendations, fn ($r) => in_array($r['impact'] ?? '', ['Critical', 'High'], true))),
        ];
    }

    // =========================================================================
    // Trigger dispatch
    // =========================================================================

    /**
     * Dispatch a single trigger to the appropriate evaluator.
     *
     * @return array List of recommendations (may be empty)
     */
    private function evaluateTrigger(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'isa_not_maxed' => $this->evaluateIsaNotMaxed($definition, $user, $priority),
            'pension_carry_forward_available' => $this->evaluatePensionCarryForward($definition, $user, $priority),
            'spousal_transfer_beneficial' => $this->evaluateSpousalTransfer($definition, $user, $priority),
            'cgt_allowance_unused' => $this->evaluateCgtAllowanceUnused($definition, $user, $priority),
            'high_dividend_in_gia' => $this->evaluateHighDividendInGia($definition, $user, $priority),
            default => [],
        };
    }

    // =========================================================================
    // Evaluators (5)
    // =========================================================================

    /**
     * ISA not maxed: triggers when total ISA subscriptions are below the annual allowance.
     */
    private function evaluateIsaNotMaxed(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $isaConfig = $this->taxConfig->getISAAllowances();
        $isaAllowance = (float) ($isaConfig['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);

        // Investment ISAs
        $investmentISASubscribed = (float) InvestmentAccount::where('user_id', $user->id)
            ->whereIn('account_type', ['isa', 'stocks_shares_isa'])
            ->sum('isa_subscription_current_year');

        // Cash ISAs from savings
        $cashISASubscribed = (float) SavingsAccount::where('user_id', $user->id)
            ->where('account_type', 'isa')
            ->sum('isa_subscription_amount');

        $totalUsed = $investmentISASubscribed + $cashISASubscribed;
        $remaining = $isaAllowance - $totalUsed;

        if ($remaining <= 0) {
            return [];
        }

        $vars = [
            'isa_used' => '£'.number_format($totalUsed, 0),
            'isa_allowance' => '£'.number_format($isaAllowance, 0),
            'isa_remaining' => '£'.number_format($remaining, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Pension carry forward: triggers when unused Annual Allowance is available from prior years.
     */
    private function evaluatePensionCarryForward(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $taxYear = $this->taxConfig->getTaxYear();
        $allowanceResult = $this->allowanceChecker->checkAnnualAllowance($user->id, $taxYear);

        $carryForward = $allowanceResult['carry_forward_available'] ?? 0;

        if ($carryForward <= 0) {
            return [];
        }

        $grossIncome = $user->annual_employment_income ?? 0;
        $taxRate = $this->determineMarginalRate($grossIncome);

        $vars = [
            'carry_forward' => '£'.number_format($carryForward, 0),
            'tax_rate' => (string) $taxRate,
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($carryForward * ($taxRate / 100), 2);

        return [$rec];
    }

    /**
     * Spousal transfer: triggers when married user and spouse are in different tax bands.
     */
    private function evaluateSpousalTransfer(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        if ($user->marital_status !== 'married' || ! $user->spouse_id) {
            return [];
        }

        $spouse = User::find($user->spouse_id);
        if (! $spouse) {
            return [];
        }

        $userIncome = (float) ($user->annual_employment_income ?? 0);
        $spouseIncome = (float) ($spouse->annual_employment_income ?? 0);

        $userBand = $this->determineTaxBand($userIncome);
        $spouseBand = $this->determineTaxBand($spouseIncome);

        if ($userBand === $spouseBand) {
            return [];
        }

        // Estimate potential saving from income shifting
        $higherRate = $this->determineMarginalRate(max($userIncome, $spouseIncome));
        $lowerRate = $this->determineMarginalRate(min($userIncome, $spouseIncome));
        $rateDifference = ($higherRate - $lowerRate) / 100;

        // Conservative estimate: shift 10% of investment income
        $giaValue = (float) InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'gia')
            ->sum('current_value');
        $estimatedInvestmentIncome = $giaValue * 0.04;
        $potentialSaving = round($estimatedInvestmentIncome * $rateDifference, 2);

        if ($potentialSaving <= 0) {
            $potentialSaving = 200.0; // Conservative fallback
        }

        $vars = [
            'user_band' => $userIncome >= $spouseIncome ? $userBand : $spouseBand,
            'spouse_band' => $userIncome >= $spouseIncome ? $spouseBand : $userBand,
            'potential_saving' => '£'.number_format($potentialSaving, 0),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = $potentialSaving;

        return [$rec];
    }

    /**
     * CGT allowance unused: triggers when user has GIA holdings with unrealised gains
     * and has not used the CGT annual exemption.
     */
    private function evaluateCgtAllowanceUnused(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $annualExempt = (float) ($cgtConfig['annual_exempt_amount'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT);

        $giaAccounts = InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'gia')
            ->with('holdings')
            ->get();

        if ($giaAccounts->isEmpty()) {
            return [];
        }

        $unrealisedGains = 0.0;
        $totalGiaValue = 0.0;

        foreach ($giaAccounts as $account) {
            $totalGiaValue += (float) $account->current_value;
            foreach ($account->holdings as $holding) {
                if ($holding->cost_basis && $holding->current_value) {
                    $gainLoss = (float) $holding->current_value - (float) $holding->cost_basis;
                    if ($gainLoss > 0) {
                        $unrealisedGains += $gainLoss;
                    }
                }
            }
        }

        if ($unrealisedGains <= 0) {
            return [];
        }

        $vars = [
            'gia_value' => '£'.number_format($totalGiaValue, 0),
            'cgt_exemption' => '£'.number_format($annualExempt, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * High dividend in GIA: triggers when GIA holdings exceed threshold
     * and could benefit from ISA sheltering for dividends.
     */
    private function evaluateHighDividendInGia(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $minGiaValue = (float) ($config['min_gia_value'] ?? 10000);

        $giaValue = (float) InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'gia')
            ->sum('current_value');

        if ($giaValue < $minGiaValue) {
            return [];
        }

        // Check if user has ISA remaining capacity
        $isaConfig = $this->taxConfig->getISAAllowances();
        $isaAllowance = (float) ($isaConfig['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);

        $investmentISASubscribed = (float) InvestmentAccount::where('user_id', $user->id)
            ->whereIn('account_type', ['isa', 'stocks_shares_isa'])
            ->sum('isa_subscription_current_year');
        $cashISASubscribed = (float) SavingsAccount::where('user_id', $user->id)
            ->where('account_type', 'isa')
            ->sum('isa_subscription_amount');

        $isaRemaining = $isaAllowance - ($investmentISASubscribed + $cashISASubscribed);
        if ($isaRemaining <= 0) {
            return [];
        }

        $dividendTaxConfig = $this->taxConfig->getDividendTax();
        $dividendAllowance = (float) ($dividendTaxConfig['allowance'] ?? 500);

        $vars = [
            'gia_value' => '£'.number_format($giaValue, 0),
            'dividend_allowance' => '£'.number_format($dividendAllowance, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a standard recommendation array from a definition and template variables.
     */
    private function buildRecommendation(
        TaxActionDefinition $definition,
        array $vars,
        int $priority
    ): array {
        return [
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'definition_key' => $definition->key,
        ];
    }

    /**
     * Determine the marginal tax rate for a given gross income.
     */
    private function determineMarginalRate(float $grossIncome): int
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $basicRateLimit = $personalAllowance + (float) ($incomeTax['bands'][0]['max'] ?? 37700);
        $additionalThreshold = (float) ($incomeTax['additional_rate_threshold'] ?? 125140);

        if ($grossIncome <= $personalAllowance) {
            return 0;
        }

        if ($grossIncome <= $basicRateLimit) {
            return 20;
        }

        if ($grossIncome <= $additionalThreshold) {
            return 40;
        }

        return 45;
    }

    /**
     * Determine the tax band name for a given gross income.
     */
    private function determineTaxBand(float $grossIncome): string
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $basicRateLimit = $personalAllowance + (float) ($incomeTax['bands'][0]['max'] ?? 37700);
        $additionalThreshold = (float) ($incomeTax['additional_rate_threshold'] ?? 125140);

        if ($grossIncome <= $personalAllowance) {
            return 'non-taxpayer';
        }

        if ($grossIncome <= $basicRateLimit) {
            return 'basic rate';
        }

        if ($grossIncome <= $additionalThreshold) {
            return 'higher rate';
        }

        return 'additional rate';
    }
}
