<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Life Event Cash Flow Service
 *
 * Transforms life events into year-indexed cash flow maps suitable for injection
 * into Monte Carlo simulations and drawdown projections. All amounts are raw
 * (no certainty weighting) - users confirm or remove events as appropriate.
 */
class LifeEventCashFlowService
{
    private const MODULE_RETIREMENT = 'retirement';

    private const MODULE_INVESTMENT = 'investment';

    /**
     * Event types relevant to investment projections (subset of all types).
     */
    private const INVESTMENT_EVENT_TYPES = [
        'inheritance',
        'property_sale',
        'business_sale',
        'lottery_windfall',
        'gift_received',
        'custom_income',
        'large_purchase',
        'gift_given',
        'custom_expense',
    ];

    public function __construct(
        private readonly LifeEventService $lifeEventService
    ) {}

    /**
     * Build a year-indexed cash flow map for Monte Carlo injection.
     *
     * Returns [yearNumber => signedAmount] where yearNumber is 1-based
     * (year 1 = first year of simulation). Positive = inflow, negative = outflow.
     *
     * @param  int  $userId  User ID
     * @param  string  $module  Module context ('retirement', 'investment', 'estate')
     * @param  int  $years  Simulation horizon in years
     * @return array<int, float> Year-indexed cash flows
     */
    public function buildCashFlowMap(int $userId, string $module, int $years): array
    {
        $events = $this->lifeEventService->getActiveEventsForProjection($userId);
        $relevantTypes = $this->getRelevantEventTypes($module);

        $filtered = $relevantTypes === null
            ? $events
            : $events->filter(fn ($event) => in_array($event->event_type, $relevantTypes));

        return $this->mapEventsToYears($filtered, $years);
    }

    /**
     * Build cash flow map for retirement drawdown (age-indexed).
     *
     * Returns [age => signedAmount] for events within the drawdown period.
     *
     * @param  int  $userId  User ID
     * @param  int  $retirementAge  Age at retirement
     * @param  int  $endAge  End age for projections (default 100)
     * @return array<int, float> Age-indexed cash flows
     */
    public function buildDrawdownCashFlowMap(int $userId, int $retirementAge, int $endAge = 100): array
    {
        $user = User::findOrFail($userId);
        if (! $user->date_of_birth) {
            return [];
        }

        $events = $this->lifeEventService->getActiveEventsForProjection($userId);

        $cashFlows = [];

        foreach ($events as $event) {
            $ageAtEvent = $event->getAgeAtEvent($user);
            if ($ageAtEvent === null || $ageAtEvent < $retirementAge || $ageAtEvent > $endAge) {
                continue;
            }

            $amount = (float) $event->amount;
            if ($event->impact_type === 'expense') {
                $amount = -$amount;
            }

            $cashFlows[$ageAtEvent] = ($cashFlows[$ageAtEvent] ?? 0) + $amount;
        }

        return $cashFlows;
    }

    /**
     * Get the list of life events that were applied (for API response metadata).
     *
     * @return array List of applied events with name, type, year, and amount
     */
    public function getAppliedEvents(int $userId, string $module, int $years): array
    {
        $events = $this->lifeEventService->getActiveEventsForProjection($userId);
        $relevantTypes = $this->getRelevantEventTypes($module);

        $applied = [];

        foreach ($events as $event) {
            if ($relevantTypes !== null && ! in_array($event->event_type, $relevantTypes)) {
                continue;
            }

            $yearsUntil = $event->years_until_event;
            if ($yearsUntil === null || $yearsUntil < 1 || $yearsUntil > $years) {
                continue;
            }

            $applied[] = [
                'id' => $event->id,
                'event_name' => $event->event_name,
                'event_type' => $event->event_type,
                'impact_type' => $event->impact_type,
                'amount' => (float) $event->amount,
                'signed_amount' => $event->signed_amount,
                'expected_date' => $event->expected_date->toDateString(),
                'year_number' => $yearsUntil,
                'certainty' => $event->certainty,
            ];
        }

        return $applied;
    }

    /**
     * Generate a hash of active life events for cache key differentiation.
     */
    public function getEventHash(int $userId, string $module): string
    {
        $events = $this->lifeEventService->getActiveEventsForProjection($userId);
        $relevantTypes = $this->getRelevantEventTypes($module);

        $filtered = $relevantTypes === null
            ? $events
            : $events->filter(fn ($event) => in_array($event->event_type, $relevantTypes));

        if ($filtered->isEmpty()) {
            return 'noevents';
        }

        $hashData = $filtered->map(fn ($e) => $e->id.':'.$e->amount.':'.$e->impact_type.':'.$e->event_type.':'.$e->expected_date->format('Y'))
            ->sort()
            ->implode('|');

        return substr(md5($hashData), 0, 8);
    }

    /**
     * Map events to simulation year numbers.
     *
     * @return array<int, float>
     */
    private function mapEventsToYears(Collection $events, int $years): array
    {
        $cashFlows = [];

        foreach ($events as $event) {
            $yearsUntil = $event->years_until_event;

            if ($yearsUntil === null || $yearsUntil < 1 || $yearsUntil > $years) {
                continue;
            }

            $amount = (float) $event->amount;
            if ($event->impact_type === 'expense') {
                $amount = -$amount;
            }

            $cashFlows[$yearsUntil] = ($cashFlows[$yearsUntil] ?? 0) + $amount;
        }

        return $cashFlows;
    }

    /**
     * Get event types relevant to a given module.
     *
     * @return string[]
     */
    private function getRelevantEventTypes(string $module): ?array
    {
        return match ($module) {
            self::MODULE_INVESTMENT => self::INVESTMENT_EVENT_TYPES,
            default => null, // null = all event types (no filtering)
        };
    }
}
