<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\EstateActionDefinition;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
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
        private readonly TaxConfigService $taxConfig,
        private readonly PropertyStore $propertyStore,
        private readonly MortgageStore $mortgageStore,
        private readonly IHTCalculationService $ihtCalculator,
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
                'policy_value' => '£'.number_format((float) ($policy->sum_assured ?? 0), 0),
            ];
            $results[] = $this->buildRecommendation($definition, $vars, $priority);
            $priority++;
        }

        return $results;
    }

    /**
     * IHT exceeds the nil-rate band — asked of the Inheritance Tax engine.
     *
     * **W-0501. This used to estimate the estate by hand and got it wrong in both
     * directions.** `estimateEstateValue()` summed each asset's FULL value with no
     * `ownership_percentage`, then scoped on `user_id` — which drops every asset
     * where the user is the `joint_owner_id` rather than the primary owner. On a
     * £295,000 property held 40/60 it reported £295,000 to the primary owner whose
     * share is £118,000, and **£0** to the joint owner whose share is £177,000.
     *
     * The zero is the half that mattered: this evaluator gates on the figure, so a
     * user whose exposure sits in a co-owned asset they do not hold as primary
     * owner was told **nothing at all** about a liability they have. A suppressed
     * warning, not a conservative estimate.
     *
     * It also granted the residence band unconditionally — no qualifying residence,
     * no direct descendants, no £2,000,000 taper — which inflated the band and made
     * the warning less likely to fire. Same suppressing direction.
     *
     * Reading `IHTCalculationService` fixes all of it at once, because that engine
     * already applies ownership shares, the undivided-share discount (W-0368), the
     * residence-band conditions and the taper. It is also the figure the Estate
     * module shows this same user, so the recommendation can no longer contradict
     * the page it sits beside — which the hand-rolled sum did by construction.
     */
    private function evaluateIhtExceedsNrb(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        // The spouse is passed only where their estate is actually pooled; the
        // engine owns that rule, and asking it the same way the Estate module does
        // is the point of the change.
        // Derived exactly as IHTController, TrustController and
        // ComprehensiveEstatePlanService derive it — a live reciprocal link AND an
        // accepted permission. There is no `data_sharing_enabled` column; inventing
        // one here would have been a fifth answer to a question already settled.
        $spouse = $user->liveSpouse();
        $dataSharingEnabled = $spouse !== null && $user->hasAcceptedSpousePermission();

        $iht = $this->ihtCalculator->calculate($user, $spouse, $dataSharingEnabled);

        $ihtLiability = (float) ($iht['iht_liability'] ?? 0.0);

        // Nothing chargeable means nothing to warn about. Gating on the liability
        // rather than on a re-derived comparison keeps this evaluator from forming
        // a second opinion about the same estate.
        if ($ihtLiability <= 0.0) {
            return [];
        }

        $netEstate = (float) ($iht['total_net_estate'] ?? 0.0);
        $allowances = (float) ($iht['total_allowances'] ?? 0.0);

        $vars = [
            'estate_value' => '£'.number_format($netEstate, 0),
            'nrb' => '£'.number_format($allowances, 0),
            'excess_amount' => '£'.number_format(max(0.0, $netEstate - $allowances), 0),
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
        $store = app(PensionStore::class);
        $hasPensions = $store->forUserByType($user, 'dc')->isNotEmpty()
            || $store->forUserByType($user, 'db')->isNotEmpty();

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
}
