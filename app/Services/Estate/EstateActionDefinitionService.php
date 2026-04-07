<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Constants\TaxDefaults;
use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\EstateActionDefinition;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;
use App\Traits\StructuredLogging;
use Carbon\Carbon;

/**
 * Evaluates estate action definitions against user data
 * to produce configurable, database-driven estate planning recommendations.
 *
 * Mirrors TaxActionDefinitionService — each trigger condition
 * maps to one private evaluator method that checks the condition
 * and returns zero or more recommendations.
 */
class EstateActionDefinitionService
{
    use FormatsCurrency;
    use StructuredLogging;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Evaluate all enabled estate action definitions against a user's data.
     *
     * @return array{recommendations: array, total_count: int, high_priority_count: int}
     */
    public function evaluateActions(User $user): array
    {
        $definitions = EstateActionDefinition::getEnabledBySource('agent');
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
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'no_will' => $this->evaluateNoWill($definition, $user, $priority),
            'policy_not_in_trust' => $this->evaluatePolicyNotInTrust($definition, $user, $priority),
            'iht_exceeds_nrb' => $this->evaluateIhtExceedsNrb($definition, $user, $priority),
            'no_lpa' => $this->evaluateNoLpa($definition, $user, $priority),
            'no_lpa_health' => $this->evaluateNoLpaHealth($definition, $user, $priority),
            'gifts_pet_window' => $this->evaluateGiftsPetWindow($definition, $user, $priority),
            'trust_review_due' => $this->evaluateTrustReviewDue($definition, $user, $priority),
            'beneficiary_review' => $this->evaluateBeneficiaryReview($definition, $user, $priority),
            default => [],
        };
    }

    // =========================================================================
    // Evaluators (8)
    // =========================================================================

    /**
     * No will: triggers when user has no will record.
     */
    private function evaluateNoWill(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $will = Will::where('user_id', $user->id)->first();

        if ($will && $will->has_will) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    /**
     * Policy not in trust: checks life policies not in trust.
     */
    private function evaluatePolicyNotInTrust(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $policies = LifeInsurancePolicy::where('user_id', $user->id)
            ->where('in_trust', false)
            ->get();

        if ($policies->isEmpty()) {
            return [];
        }

        $results = [];
        foreach ($policies as $policy) {
            $vars = [
                'policy_value' => '£'.number_format((float) ($policy->cover_amount ?? 0), 0),
            ];
            $results[] = $this->buildRecommendation($definition, $vars, $priority);
            $priority++;
        }

        return $results;
    }

    /**
     * IHT exceeds NRB: checks total estate value vs nil-rate band.
     */
    private function evaluateIhtExceedsNrb(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = (float) ($ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB);
        $rnrb = (float) ($ihtConfig['residence_nil_rate_band'] ?? TaxDefaults::RNRB);

        // Estimate total estate value from properties, assets, savings, investments
        $estateValue = $this->estimateEstateValue($user);
        $availableBand = $nrb + $rnrb;

        if ($estateValue <= $availableBand) {
            return [];
        }

        $excess = $estateValue - $availableBand;
        $ihtLiability = $excess * (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);

        $vars = [
            'estate_value' => '£'.number_format($estateValue, 0),
            'nrb' => '£'.number_format($availableBand, 0),
            'excess_amount' => '£'.number_format($excess, 0),
            'iht_liability' => '£'.number_format($ihtLiability, 0),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($ihtLiability, 2);

        return [$rec];
    }

    /**
     * No financial LPA: checks for financial LPA record.
     */
    private function evaluateNoLpa(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $financialLpa = LastingPowerOfAttorney::where('user_id', $user->id)
            ->where('lpa_type', 'property_financial')
            ->first();

        if ($financialLpa) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    /**
     * No health/welfare LPA: checks for health LPA record.
     */
    private function evaluateNoLpaHealth(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $healthLpa = LastingPowerOfAttorney::where('user_id', $user->id)
            ->where('lpa_type', 'health_welfare')
            ->first();

        if ($healthLpa) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    /**
     * Gifts PET window: checks gifts within 7-year PET window.
     */
    private function evaluateGiftsPetWindow(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $sevenYearsAgo = Carbon::now()->subYears(7);

        $gifts = Gift::where('user_id', $user->id)
            ->where('gift_date', '>=', $sevenYearsAgo)
            ->get();

        if ($gifts->isEmpty()) {
            return [];
        }

        $giftTotal = $gifts->sum('gift_value');

        $vars = [
            'gift_count' => (string) $gifts->count(),
            'gift_total' => '£'.number_format((float) $giftTotal, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Trust review due: checks trust last review date > 12 months.
     */
    private function evaluateTrustReviewDue(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $monthsThreshold = (int) ($config['months_threshold'] ?? 12);

        $trusts = Trust::where('user_id', $user->id)->get();

        if ($trusts->isEmpty()) {
            return [];
        }

        $results = [];
        $threshold = Carbon::now()->subMonths($monthsThreshold);

        foreach ($trusts as $trust) {
            $lastReview = $trust->last_valuation_date ? Carbon::parse($trust->last_valuation_date) : null;

            if (! $lastReview || $lastReview->lt($threshold)) {
                $vars = [
                    'trust_name' => $trust->trust_name ?? 'Unnamed trust',
                    'last_review_date' => $lastReview ? $lastReview->format('d/m/Y') : 'never',
                ];
                $results[] = $this->buildRecommendation($definition, $vars, $priority);
                $priority++;
            }
        }

        return $results;
    }

    /**
     * Beneficiary review: periodic reminder to review beneficiary designations.
     */
    private function evaluateBeneficiaryReview(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        // This is a periodic reminder that triggers for any user
        // who has pensions or life insurance policies
        $hasPolicies = LifeInsurancePolicy::where('user_id', $user->id)->exists();
        $hasPensions = \App\Models\DCPension::where('user_id', $user->id)->exists()
            || \App\Models\DBPension::where('user_id', $user->id)->exists();

        if (! $hasPolicies && ! $hasPensions) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a standard recommendation array from a definition and template variables.
     */
    private function buildRecommendation(
        EstateActionDefinition $definition,
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
     * Estimate total estate value from all user assets.
     */
    private function estimateEstateValue(User $user): float
    {
        $total = 0.0;

        // Properties
        $total += (float) \App\Models\Property::where('user_id', $user->id)->sum('current_value');

        // Investment accounts
        $total += (float) \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)->sum('current_value');

        // Savings accounts
        $total += (float) \App\Models\SavingsAccount::where('user_id', $user->id)->sum('current_balance');

        // Cash accounts
        $total += (float) \App\Models\CashAccount::where('user_id', $user->id)->sum('current_balance');

        // Estate assets
        $total += (float) \App\Models\Estate\Asset::where('user_id', $user->id)->sum('current_value');

        // DC Pensions (death benefit)
        $total += (float) \App\Models\DCPension::where('user_id', $user->id)->sum('current_fund_value');

        // Life insurance (death benefit adds to estate if not in trust)
        $total += (float) LifeInsurancePolicy::where('user_id', $user->id)
            ->where('in_trust', false)
            ->sum('cover_amount');

        // Subtract liabilities
        $total -= (float) \App\Models\Mortgage::where('user_id', $user->id)->sum('current_balance');
        $total -= (float) \App\Models\Estate\Liability::where('user_id', $user->id)->sum('amount');

        return max(0.0, $total);
    }
}
