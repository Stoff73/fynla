<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\LifeEvent;

/**
 * Life Event Integration Service
 *
 * Maps life events to relevant financial planning modules and provides
 * contextual annotations explaining each event's impact on that module.
 */
class LifeEventIntegrationService
{
    /**
     * Event type to module mapping.
     * Each event maps to a primary module and optional secondary modules.
     *
     * @var array<string, array{primary: string, secondary: string[]}>
     */
    private const EVENT_MODULE_MAP = [
        // Income events
        'inheritance' => ['primary' => 'estate', 'secondary' => ['savings', 'investment']],
        'gift_received' => ['primary' => 'estate', 'secondary' => ['savings']],
        'bonus' => ['primary' => 'savings', 'secondary' => ['retirement']],
        'redundancy_payment' => ['primary' => 'protection', 'secondary' => ['savings']],
        'property_sale' => ['primary' => 'estate', 'secondary' => ['savings', 'investment']],
        'business_sale' => ['primary' => 'estate', 'secondary' => ['investment']],
        'pension_lump_sum' => ['primary' => 'retirement', 'secondary' => ['savings']],
        'lottery_windfall' => ['primary' => 'savings', 'secondary' => ['investment', 'estate']],
        'custom_income' => ['primary' => 'savings', 'secondary' => []],

        // Life change events
        'divorce' => ['primary' => 'estate', 'secondary' => ['protection', 'retirement', 'savings']],
        'marriage' => ['primary' => 'protection', 'secondary' => ['estate', 'savings']],
        'new_child' => ['primary' => 'protection', 'secondary' => ['savings', 'estate']],
        'job_loss' => ['primary' => 'protection', 'secondary' => ['savings', 'retirement']],
        'income_change' => ['primary' => 'savings', 'secondary' => ['retirement', 'investment']],

        // Expense events
        'large_purchase' => ['primary' => 'savings', 'secondary' => []],
        'home_improvement' => ['primary' => 'savings', 'secondary' => ['estate']],
        'wedding' => ['primary' => 'savings', 'secondary' => []],
        'education_fees' => ['primary' => 'savings', 'secondary' => []],
        'gift_given' => ['primary' => 'estate', 'secondary' => ['savings']],
        'medical_expense' => ['primary' => 'protection', 'secondary' => ['savings']],
        'custom_expense' => ['primary' => 'savings', 'secondary' => []],
    ];

    /**
     * Module context messages explaining why an event is relevant.
     *
     * @var array<string, array<string, string>>
     */
    private const MODULE_CONTEXT = [
        'savings' => [
            'inheritance' => 'Incoming funds that will increase your cash position',
            'gift_received' => 'Incoming gift that will increase your cash savings',
            'bonus' => 'Additional income available for savings or goal contributions',
            'redundancy_payment' => 'Payment that will need to be managed in your cash reserves',
            'property_sale' => 'Sale proceeds that will flow into your cash holdings',
            'lottery_windfall' => 'Windfall that will significantly increase your cash position',
            'large_purchase' => 'Planned expense that will reduce your cash reserves',
            'home_improvement' => 'Planned expense that will reduce your cash savings',
            'wedding' => 'Planned expense that will reduce your cash reserves',
            'education_fees' => 'Ongoing fees that will reduce your disposable income',
            'gift_given' => 'Planned gift that will reduce your cash reserves',
            'medical_expense' => 'Planned expense that will reduce your cash reserves',
            'custom_income' => 'Expected income that will increase your cash position',
            'custom_expense' => 'Expected expense that will reduce your cash reserves',
            'pension_lump_sum' => 'Lump sum that will increase your cash position',
            'business_sale' => 'Sale proceeds available for savings or reinvestment',
            'divorce' => 'Divorce may require liquidating savings to settle finances. Review your emergency fund position.',
            'new_child' => 'A new child increases household costs. Review your savings goals and emergency fund.',
            'income_change' => 'An income change affects your savings capacity. Review your contribution levels and goals.',
            'job_loss' => 'Job loss will deplete savings. Prioritise your emergency fund and reduce non-essential contributions.',
        ],
        'investment' => [
            'inheritance' => 'Incoming funds that could be invested for long-term growth',
            'property_sale' => 'Sale proceeds available for reinvestment',
            'business_sale' => 'Proceeds available for portfolio diversification',
            'lottery_windfall' => 'Significant funds available for investment',
            'income_change' => 'An income change may affect your investment risk capacity. Review your portfolio allocation and contribution levels.',
        ],
        'retirement' => [
            'bonus' => 'Opportunity to make additional pension contributions',
            'pension_lump_sum' => 'Tax-free cash from your pension',
            'divorce' => 'Divorce may split pension assets. Review your retirement projections and contribution strategy.',
            'job_loss' => 'Job loss interrupts pension contributions. Review your Annual Allowance position and consider bridging strategies.',
            'income_change' => 'An income change affects your pension contribution capacity. Review salary sacrifice and Annual Allowance position.',
        ],
        'protection' => [
            'redundancy_payment' => 'Review your income protection and critical illness cover',
            'medical_expense' => 'Check if this is covered by your health or protection policies',
            'marriage' => 'Marriage changes your protection needs. Review life cover and consider whether your existing policies still provide adequate cover.',
            'new_child' => 'A new child increases your family\'s protection needs. Review life cover and income protection to ensure your family is fully covered.',
            'job_loss' => 'Job loss makes income protection critical. Review existing cover and consider how long your savings could sustain your family.',
        ],
        'estate' => [
            'inheritance' => 'Will increase your taxable estate and may affect Inheritance Tax liability',
            'gift_received' => 'May be a Potentially Exempt Transfer with Inheritance Tax implications',
            'property_sale' => 'Capital Gains Tax may apply; changes your estate composition',
            'business_sale' => 'May lose Business Relief; Capital Gains Tax implications',
            'lottery_windfall' => 'Will increase your taxable estate',
            'home_improvement' => 'May increase the value of your property within the estate',
            'gift_given' => 'Potentially Exempt Transfer that could reduce your taxable estate',
            'divorce' => 'Divorce will significantly restructure your estate. Assets may need to be divided and your Inheritance Tax position recalculated.',
        ],
    ];

    public function __construct(
        private readonly LifeEventService $lifeEventService
    ) {}

    /**
     * Get life events relevant to a specific module.
     *
     * Returns active life events mapped to the given module (primary and secondary),
     * sorted by expected date, with contextual annotations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getEventsForModule(int $userId, string $module, bool $includeHousehold = false): array
    {
        $events = $this->lifeEventService->getActiveEventsForProjection($userId, $includeHousehold);

        return $events
            ->filter(fn (LifeEvent $event) => $this->isRelevantToModule($event->event_type, $module))
            ->map(fn (LifeEvent $event) => $this->formatEventForModule($event, $module, $userId))
            ->values()
            ->toArray();
    }

    /**
     * Get aggregate impact summary for a module.
     *
     * @return array{upcoming_income: float, upcoming_expense: float, net_impact: float, event_count: int, next_event: array|null}
     */
    public function getModuleImpactSummary(int $userId, string $module, bool $includeHousehold = false): array
    {
        $events = $this->getEventsForModule($userId, $module, $includeHousehold);

        $upcomingIncome = 0.0;
        $upcomingExpense = 0.0;
        $nextEvent = null;

        foreach ($events as $event) {
            if ($event['impact_type'] === 'income') {
                $upcomingIncome += $event['amount'];
            } else {
                $upcomingExpense += $event['amount'];
            }

            if ($nextEvent === null || $event['expected_date'] < $nextEvent['expected_date']) {
                $nextEvent = $event;
            }
        }

        return [
            'upcoming_income' => round($upcomingIncome, 2),
            'upcoming_expense' => round($upcomingExpense, 2),
            'net_impact' => round($upcomingIncome - $upcomingExpense, 2),
            'event_count' => count($events),
            'next_event' => $nextEvent,
        ];
    }

    /**
     * Get the list of modules an event is relevant to.
     *
     * @return string[]
     */
    public function getEventModules(LifeEvent $event): array
    {
        $mapping = self::EVENT_MODULE_MAP[$event->event_type] ?? null;

        if ($mapping === null) {
            return ['savings']; // Default fallback
        }

        return array_merge([$mapping['primary']], $mapping['secondary']);
    }

    /**
     * Check if an event type is relevant to a given module.
     */
    private function isRelevantToModule(string $eventType, string $module): bool
    {
        $mapping = self::EVENT_MODULE_MAP[$eventType] ?? null;

        if ($mapping === null) {
            return $module === 'savings';
        }

        return $mapping['primary'] === $module || in_array($module, $mapping['secondary'], true);
    }

    /**
     * Format a life event with module-specific context.
     *
     * @return array<string, mixed>
     */
    private function formatEventForModule(LifeEvent $event, string $module, int $userId): array
    {
        $mapping = self::EVENT_MODULE_MAP[$event->event_type] ?? null;
        $isPrimary = $mapping !== null && $mapping['primary'] === $module;

        return [
            'id' => $event->id,
            'event_name' => $event->event_name,
            'event_type' => $event->event_type,
            'display_event_type' => $event->display_event_type,
            'amount' => (float) $event->amount,
            'impact_type' => $event->impact_type,
            'expected_date' => $event->expected_date->toDateString(),
            'certainty' => $event->certainty,
            'icon' => $event->icon,
            'status' => $event->status,
            'is_primary_module' => $isPrimary,
            'module_context' => $this->getModuleContext($event->event_type, $module),
            'years_until_event' => $event->years_until_event,
            'ownership_type' => $event->ownership_type,
            'user_share' => $event->getAmountForUser($userId),
        ];
    }

    /**
     * Get the context message for an event type within a module.
     */
    private function getModuleContext(string $eventType, string $module): string
    {
        return self::MODULE_CONTEXT[$module][$eventType]
            ?? ($module === 'savings'
                ? 'This event will affect your cash position'
                : 'This event may impact your financial planning');
    }
}
