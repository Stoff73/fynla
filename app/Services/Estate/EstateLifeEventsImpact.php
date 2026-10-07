<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\User;
use App\Services\Goals\LifeEventIntegrationService;
use App\Services\TaxConfigService;

/**
 * Upcoming life events and what each does to the Inheritance Tax, for the
 * Inheritance Tax screen (IHTController).
 *
 * Moved out of ComprehensiveEstatePlanService when that unrouted plan was
 * deleted (item 9, CSJ 2026-10-07). Its figures had no source and are gone: a
 * review fired only above £50,000 incoming and was "high" only above £10,000 of
 * tax; the annual exemption fell back to a typed £3,000; and every planned gift
 * above it was called a Potentially Exempt Transfer.
 */
final class EstateLifeEventsImpact
{
    public function __construct(
        private readonly LifeEventIntegrationService $lifeEventIntegration,
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * @param  array<string, mixed>  $ihtAnalysis  IHTCalculationService::calculate()
     * @return array<string, mixed>
     */
    public function forUser(User $user, float $currentIHTLiability, array $ihtAnalysis): array
    {
        $events = $this->lifeEventIntegration->getEventsForModule($user->id, 'estate');
        $impactSummary = $this->lifeEventIntegration->getModuleImpactSummary($user->id, 'estate');

        // The estate's own rate (36% when the charity test is met), as the
        // calculation found it.
        $ihtRate = (float) ($ihtAnalysis['iht_rate'] ?? $this->taxConfig->getInheritanceTax()['standard_rate']);
        $annualExemption = (float) $this->taxConfig->getGiftingExemptions()['annual_exemption'];
        $totalAllowances = (float) ($ihtAnalysis['total_allowances'] ?? 0);
        $currentNetEstate = (float) ($ihtAnalysis['total_net_estate'] ?? 0);

        $eventImpacts = [];
        $reviewTriggers = [];

        foreach ($events as $event) {
            $amount = (float) $event['amount'];
            $isIncome = $event['impact_type'] === 'income';

            $estateAfterEvent = $isIncome ? $currentNetEstate + $amount : $currentNetEstate - $amount;
            $ihtAfterEvent = max(0.0, $estateAfterEvent - $totalAllowances) * $ihtRate;
            $ihtChange = $ihtAfterEvent - $currentIHTLiability;

            $eventImpacts[] = [
                'event_name' => $event['event_name'],
                'event_type' => $event['event_type'],
                'amount' => $amount,
                'impact_type' => $event['impact_type'],
                'expected_date' => $event['expected_date'],
                'certainty' => $event['certainty'],
                'module_context' => $event['module_context'],
                'projected_iht_change' => round($ihtChange, 2),
                'projected_iht_after_event' => round($ihtAfterEvent, 2),
            ];

            // Money coming in that raises the tax is worth a review, whatever
            // its size; money that does not raise it is not.
            if ($isIncome && $ihtChange > 0) {
                $reviewTriggers[] = [
                    'event_name' => $event['event_name'],
                    'reason' => '£'.number_format($amount).' coming in would add £'.number_format($ihtChange).' of Inheritance Tax',
                    'recommendation' => 'See the gifts on the Estate plan page that reduce it',
                    'priority' => 'high',
                ];
            } elseif (! $isIncome && $event['event_type'] === 'gift_given' && $amount > $annualExemption) {
                // Above the annual exemption, the rest of a gift counts towards
                // Inheritance Tax for seven years unless another exemption
                // covers it (IHTA 1984 s19, s3A).
                $reviewTriggers[] = [
                    'event_name' => $event['event_name'],
                    'reason' => 'This gift of £'.number_format($amount).' is more than the £'.number_format($annualExemption).' annual exemption',
                    'recommendation' => 'Record the gift in Fynla once you make it: the part above the exemption counts towards Inheritance Tax for seven years',
                    'priority' => 'medium',
                ];
            }
        }

        return [
            'has_events' => $eventImpacts !== [],
            'event_count' => count($eventImpacts),
            'events' => $eventImpacts,
            'summary' => [
                'total_incoming' => $impactSummary['upcoming_income'],
                'total_outgoing' => $impactSummary['upcoming_expense'],
                'net_estate_impact' => $impactSummary['net_impact'],
            ],
            'review_triggers' => $reviewTriggers,
            'next_event' => $impactSummary['next_event'],
        ];
    }
}
