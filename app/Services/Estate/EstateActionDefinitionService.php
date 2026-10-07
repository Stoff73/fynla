<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Agents\EstateAgent;
use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\EstateActionDefinition;
use App\Models\User;
use App\Services\Stores\PensionStore;
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
 *
 * Item 9 (CSJ 2026-10-07, D1 to D6): one Inheritance Tax position card read
 * from the step engine the Estate plan page uses; one Lasting Power of Attorney
 * card that counts only a registered one; gifts counted by the one gift engine;
 * beneficiaries only where a pension has none; trusts on their ten-year
 * anniversary. The life policy trust card is Protection's alone.
 */
class EstateActionDefinitionService
{
    use FormatsCurrency;
    use StructuredLogging;

    public function __construct(
        private readonly EstateAgent $estateAgent,
        private readonly FailedGiftTaxCalculator $giftTax,
        private readonly TrustService $trustService,
        private readonly TaxConfigService $taxConfig,
        private readonly PensionStore $pensionStore,
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
            'iht_position' => $this->evaluateIhtPosition($definition, $user, $priority),
            'lpa_not_registered' => $this->evaluateLpaNotRegistered($definition, $user, $priority),
            'gifts_pet_window' => $this->evaluateGiftsPetWindow($definition, $user, $priority),
            'trust_anniversary_due' => $this->evaluateTrustAnniversaryDue($definition, $user, $priority),
            'pension_no_beneficiary' => $this->evaluatePensionNoBeneficiary($definition, $user, $priority),
            default => [],
        };
    }

    // =========================================================================
    // Evaluators
    // =========================================================================

    /**
     * No will: triggers when the user has no will recorded, or recorded none.
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
     * The Inheritance Tax position: today's tax and the steps that reduce or
     * pay it, read from the one step engine the Estate plan page shows
     * (`EstateAgent::generateRecommendations`, item 9 D1). The tax figure is the
     * Inheritance Tax engine's, the one on the Estate page (W-0501).
     */
    private function evaluateIhtPosition(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $analysis = $this->estateAgent->analyze($user->id);
        $summary = $analysis['data']['summary'] ?? [];
        $ihtLiability = (float) ($summary['iht_liability'] ?? 0);

        if ($ihtLiability <= 0.0) {
            return [];
        }

        $iht = $analysis['data']['iht_calculation'] ?? [];
        $steps = [];
        foreach ($this->estateAgent->generateRecommendations($analysis)['data']['recommendations'] ?? [] as $step) {
            $steps[$step['category'] ?? ''] = $step;
        }

        $charity = $steps['charitable_bequest'] ?? null;
        $charityStep = $charity !== null && ($charity['potential_saving'] ?? 0) > 0;
        $payment = $steps['liquidity'] ?? null;
        $coverInTrust = (float) ($steps['life_cover']['usable_cover'] ?? 0);
        $annual = $steps['annual_gifting'] ?? null;
        $newCover = (float) ($steps['new_life_cover']['cover_amount'] ?? 0);
        $larger = $steps['pet_gifting'] ?? null;

        $vars = [
            'iht_liability' => $this->money($ihtLiability),
            // The engine's own estate, the one its tax is worked on: a couple's
            // pooled estate where the partner's is counted (W-0501).
            'estate_text' => ($couple = ((float) ($iht['spouse_net_estate'] ?? 0)) > 0) ? 'Your household\'s estate' : 'Your estate',
            // A couple's figure is the tax on the second death, as the Estate page
            // words it ("If both die today", IHTPlanning.vue).
            'when_text' => $couple ? 'if you both died today' : 'if you died today',
            'when_start' => $couple ? 'If you both died today' : 'If you died today',
            'net_estate' => $this->money((float) ($iht['total_net_estate'] ?? $summary['net_estate'] ?? 0)),
            'allowances' => $this->money((float) ($iht['total_allowances'] ?? 0)),
            'rate_percent' => (string) (int) round(((float) ($iht['iht_rate'] ?? $this->taxConfig->getInheritanceTax()['standard_rate'])) * 100),
            'has_charity_step' => $charityStep,
            'charity_gift' => $charityStep ? $this->money((float) ($charity['shortfall'] ?? 0)) : null,
            'charity_saving' => $charityStep ? $this->money((float) $charity['potential_saving']) : null,
            'has_payment_gap' => $payment !== null,
            'payment_gap' => $payment !== null ? $this->money((float) $payment['shortfall']) : null,
            'has_cover_in_trust' => $coverInTrust > 0,
            'cover_in_trust' => $this->money($coverInTrust),
            'annual_exemption' => $annual !== null ? $this->money((float) $annual['annual_exemption']) : null,
            'annual_saving' => $annual !== null ? $this->money((float) $annual['annual_saving']) : null,
            'has_cover_gap' => $newCover > 0,
            'cover_needed' => $this->money($newCover),
            'has_gift_band' => $larger !== null,
            'gift_band' => $larger !== null ? $this->money((float) $larger['band_left']) : null,
            'gift_band_saving' => $larger !== null ? $this->money((float) $larger['potential_saving']) : null,
            'has_trust_step' => isset($steps['clt_trust']),
            // The rates the steps speak of, from the configuration (Rule 2).
            'reduced_rate_percent' => (string) (int) round($this->taxConfig->getCharitableReducedRate() * 100),
            'charity_threshold_percent' => (string) (int) round($this->taxConfig->getCharitableThresholdPercent() * 100),
            'clt_rate_percent' => (string) (int) round($this->taxConfig->getCLTLifetimeRate() * 100),
            'periodic_max_percent' => (string) (int) round(((float) $this->taxConfig->getTrustCharges()['periodic']['max_rate']) * 100),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        // No `estimated_impact`: the list shows it as "You could save £X", and the
        // tax due is not a saving (the old card claimed the whole tax as one).
        $rec['figures'] = array_filter($vars, static fn ($v): bool => is_scalar($v));

        return [$rec];
    }

    /**
     * Lasting Powers of Attorney: one card naming each kind not registered.
     * An LPA is not created until the instrument is registered (Mental
     * Capacity Act 2005 s9(2)(b)), so a draft, completed or uploaded one
     * counts only once it is recorded as registered (item 9 D3).
     */
    private function evaluateLpaNotRegistered(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $lpas = LastingPowerOfAttorney::where('user_id', $user->id)->get();
        $registered = fn (string $type): bool => $lpas->contains(
            fn (LastingPowerOfAttorney $lpa): bool => $lpa->lpa_type === $type
                && ($lpa->status === 'registered' || $lpa->is_registered_with_opg)
        );

        $missingFinancial = ! $registered('property_financial');
        $missingHealth = ! $registered('health_welfare');

        if (! $missingFinancial && ! $missingHealth) {
            return [];
        }

        $missing = array_values(array_filter([
            $missingFinancial ? 'property and financial affairs' : null,
            $missingHealth ? 'health and welfare' : null,
        ]));

        $vars = [
            'missing_text' => implode(' or ', $missing),
            'missing_financial' => $missingFinancial,
            'missing_health' => $missingHealth,
            'has_unregistered' => $lpas->contains(fn (LastingPowerOfAttorney $lpa): bool => $lpa->status !== 'registered' && ! $lpa->is_registered_with_opg),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['figures'] = array_filter($vars, static fn ($v): bool => is_scalar($v));

        return [$rec];
    }

    /**
     * Gifts still inside the seven years: Potentially Exempt Transfers and
     * chargeable lifetime transfers only (exempt gifts never count, IHTA 1984
     * s19, s20, s22), from the one gift engine. A gift carries tax of its own
     * only on the part above the nil rate band (HMRC IHTM14512; CSJ 2026-10-07);
     * within it, it uses band the estate would otherwise get (IHTM14503).
     */
    private function evaluateGiftsPetWindow(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $window = (int) $this->taxConfig->getPETRules()['years_to_exemption'];

        $gifts = Gift::where('user_id', $user->id)
            ->whereIn('gift_type', ['pet', 'clt'])
            ->where('gift_date', '>', Carbon::today()->subYears($window))
            ->orderBy('gift_date')
            ->get();

        if ($gifts->isEmpty()) {
            return [];
        }

        $nrb = (float) $this->taxConfig->getInheritanceTax()['nil_rate_band'];
        $totals = $this->giftTax->forMember($user, $nrb);
        $giftTax = (float) $totals['failed_gift_tax'];

        $vars = [
            'gift_count' => (string) $gifts->count(),
            'gifts_text' => $gifts->count() === 1 ? '1 gift' : $gifts->count().' gifts',
            'gift_total' => $this->money((float) $gifts->sum('gift_value')),
            'band_used' => $this->money((float) $totals['total_nrb_used']),
            'gift_tax' => $this->money($giftTax),
            'has_gift_tax' => $giftTax > 0,
            'has_trust_gift' => $gifts->contains(fn (Gift $gift): bool => $gift->gift_type === 'clt'),
            'next_clear_date' => Carbon::parse($gifts->first()->gift_date)->addYears($window)->format('j F Y'),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['figures'] = array_filter($vars, static fn ($v): bool => is_scalar($v));

        return [$rec];
    }

    /**
     * A relevant property trust within the configured lead time of its ten-year
     * anniversary charge (IHTA 1984 s64(1)), dated by the one trust engine
     * (`TrustService::calculateNextPeriodicChargeDate`). Replaces a 12-month
     * "review" with no source (item 9 D5).
     */
    private function evaluateTrustAnniversaryDue(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $yearsBefore = (int) ($definition->trigger_config['years_before'] ?? 2);
        $horizon = Carbon::today()->addYears($yearsBefore);
        $maxRate = (float) $this->taxConfig->getTrustCharges()['periodic']['max_rate'];

        $results = [];
        foreach (Trust::where('user_id', $user->id)->get() as $trust) {
            $anniversary = $this->trustService->calculateNextPeriodicChargeDate($trust);

            if ($anniversary === null || $anniversary->lt(Carbon::today()) || $anniversary->gt($horizon)) {
                continue;
            }

            $vars = [
                'trust_name' => $trust->trust_name ?: 'Your trust',
                'anniversary_date' => $anniversary->format('j F Y'),
                'max_rate_percent' => (string) (int) round($maxRate * 100),
            ];
            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['figures'] = $vars;
            $results[] = $rec;
            $priority++;
        }

        return $results;
    }

    /**
     * A defined contribution pension with no beneficiary recorded, one card
     * per pension (item 9 D4). From 6 April 2027 most unused pension funds
     * come into the estate for Inheritance Tax (GOV.UK, Inheritance Tax on
     * unused pension funds and death benefits), and a nomination tells the
     * scheme whom you want the money to go to.
     */
    private function evaluatePensionNoBeneficiary(
        EstateActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $results = [];
        foreach ($this->pensionStore->forUserByType($user, 'dc') as $pension) {
            if ($pension->beneficiary_id !== null || trim((string) $pension->beneficiary_name) !== '') {
                continue;
            }

            $vars = ['pension_name' => $pension->scheme_name ?: ($pension->provider ?: 'your pension')];
            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['account_id'] = $pension->id;
            $rec['figures'] = $vars;
            $results[] = $rec;
            $priority++;
        }

        return $results;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function money(float $amount): string
    {
        return '£'.number_format($amount, 0);
    }

    /**
     * Build a standard recommendation array from a definition and template variables.
     */
    private function buildRecommendation(
        EstateActionDefinition $definition,
        array $vars,
        int $priority
    ): array {
        $vars = array_map(static fn ($v) => is_bool($v) || $v === null ? '' : $v, $vars);

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
