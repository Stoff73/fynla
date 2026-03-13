<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\LifeEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Life Event Service
 *
 * Manages life events - future occurrences that impact financial position.
 * Handles CRUD operations, event type definitions, and projection calculations.
 */
class LifeEventService
{
    /**
     * Get all events for a user, optionally including household (spouse) events.
     */
    public function getEvents(int $userId, bool $includeHousehold = false): Collection
    {
        $query = LifeEvent::forUserOrJoint($userId);

        if ($includeHousehold) {
            $user = User::find($userId);
            if ($user && $user->hasAcceptedSpousePermission()) {
                $spouseId = $user->spouse_user_id;
                if ($spouseId) {
                    $query->orWhere(function ($q) use ($spouseId) {
                        $q->where('user_id', $spouseId)
                            ->where('show_in_household_view', true);
                    });
                }
            }
        }

        return $query->orderBy('expected_date')->get();
    }

    /**
     * Get active events for projections.
     */
    public function getActiveEventsForProjection(int $userId, bool $includeHousehold = false): Collection
    {
        return $this->getEvents($userId, $includeHousehold)
            ->filter(fn (LifeEvent $event) => $event->show_in_projection)
            ->filter(fn (LifeEvent $event) => in_array($event->status, ['expected', 'confirmed']));
    }

    /**
     * Get events grouped by age for chart display.
     */
    public function getEventsByAge(int $userId, bool $includeHousehold = false): array
    {
        $user = User::findOrFail($userId);
        if (! $user->date_of_birth) {
            return [];
        }

        $events = $this->getActiveEventsForProjection($userId, $includeHousehold);
        $grouped = [];

        foreach ($events as $event) {
            $age = $event->getAgeAtEvent($user);
            if ($age === null) {
                continue;
            }

            if (! isset($grouped[$age])) {
                $grouped[$age] = [];
            }

            $grouped[$age][] = [
                'id' => $event->id,
                'name' => $event->event_name,
                'type' => $event->event_type,
                'amount' => (float) $event->amount,
                'signed_amount' => $event->signed_amount,
                'impact_type' => $event->impact_type,
                'certainty' => $event->certainty,
                'icon' => $event->icon,
                'expected_date' => $event->expected_date->toDateString(),
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Calculate total impact at a specific age.
     */
    public function calculateTotalImpactAtAge(int $userId, int $age): float
    {
        $user = User::findOrFail($userId);
        if (! $user->date_of_birth) {
            return 0;
        }

        $targetDate = $user->date_of_birth->addYears($age);
        $startOfYear = $targetDate->startOfYear();
        $endOfYear = $targetDate->copy()->endOfYear();

        $events = LifeEvent::where('user_id', $userId)
            ->active()
            ->forProjection()
            ->whereBetween('expected_date', [$startOfYear, $endOfYear])
            ->get();

        return $events->sum('signed_amount');
    }

    /**
     * Get all available event types with metadata.
     */
    public function getEventTypes(): array
    {
        return [
            // Income events (positive)
            [
                'type' => 'inheritance',
                'label' => 'Inheritance',
                'impact_type' => 'income',
                'icon' => 'GiftIcon',
                'color' => '#7C3AED',
                'description' => 'Money or assets inherited from a family member or friend',
            ],
            [
                'type' => 'gift_received',
                'label' => 'Gift Received',
                'impact_type' => 'income',
                'icon' => 'GiftTopIcon',
                'color' => '#EC4899',
                'description' => 'Significant financial gift from someone',
            ],
            [
                'type' => 'bonus',
                'label' => 'Bonus',
                'impact_type' => 'income',
                'icon' => 'BanknotesIcon',
                'color' => '#15803D',
                'description' => 'Expected work bonus or performance payment',
            ],
            [
                'type' => 'redundancy_payment',
                'label' => 'Redundancy Payment',
                'impact_type' => 'income',
                'icon' => 'DocumentTextIcon',
                'color' => '#F59E0B',
                'description' => 'Expected redundancy or severance payment',
            ],
            [
                'type' => 'property_sale',
                'label' => 'Property Sale',
                'impact_type' => 'income',
                'icon' => 'BuildingOfficeIcon',
                'color' => '#1257A0',
                'description' => 'Expected proceeds from selling a property',
            ],
            [
                'type' => 'business_sale',
                'label' => 'Business Sale',
                'impact_type' => 'income',
                'icon' => 'BriefcaseIcon',
                'color' => '#0EA5E9',
                'description' => 'Expected proceeds from selling a business',
            ],
            [
                'type' => 'pension_lump_sum',
                'label' => 'Pension Lump Sum',
                'impact_type' => 'income',
                'icon' => 'CurrencyPoundIcon',
                'color' => '#F59E0B',
                'description' => 'Tax-free cash or other pension lump sum',
            ],
            [
                'type' => 'lottery_windfall',
                'label' => 'Lottery/Windfall',
                'impact_type' => 'income',
                'icon' => 'SparklesIcon',
                'color' => '#EC4899',
                'description' => 'Lottery win or unexpected windfall',
            ],
            [
                'type' => 'custom_income',
                'label' => 'Other Income',
                'impact_type' => 'income',
                'icon' => 'PlusCircleIcon',
                'color' => '#15803D',
                'description' => 'Other expected income not listed above',
            ],

            // Expense events (negative)
            [
                'type' => 'large_purchase',
                'label' => 'Large Purchase',
                'impact_type' => 'expense',
                'icon' => 'ShoppingCartIcon',
                'color' => '#EF4444',
                'description' => 'Major purchase like a car or boat',
            ],
            [
                'type' => 'home_improvement',
                'label' => 'Home Improvement',
                'impact_type' => 'expense',
                'icon' => 'WrenchScrewdriverIcon',
                'color' => '#64748B',
                'description' => 'Renovation, extension, or major home work',
            ],
            [
                'type' => 'wedding',
                'label' => 'Wedding',
                'impact_type' => 'expense',
                'icon' => 'HeartIcon',
                'color' => '#EC4899',
                'description' => 'Wedding expenses for yourself or family',
            ],
            [
                'type' => 'education_fees',
                'label' => 'Education Fees',
                'impact_type' => 'expense',
                'icon' => 'AcademicCapIcon',
                'color' => '#7C3AED',
                'description' => 'School fees, university costs, or training',
            ],
            [
                'type' => 'gift_given',
                'label' => 'Gift Given',
                'impact_type' => 'expense',
                'icon' => 'GiftIcon',
                'color' => '#EC4899',
                'description' => 'Significant financial gift to someone',
            ],
            [
                'type' => 'medical_expense',
                'label' => 'Medical Expense',
                'impact_type' => 'expense',
                'icon' => 'HeartIcon',
                'color' => '#EF4444',
                'description' => 'Expected medical or healthcare costs',
            ],
            [
                'type' => 'custom_expense',
                'label' => 'Other Expense',
                'impact_type' => 'expense',
                'icon' => 'MinusCircleIcon',
                'color' => '#EF4444',
                'description' => 'Other expected expense not listed above',
            ],
        ];
    }

    /**
     * Get event type metadata by type key.
     */
    public function getEventTypeMetadata(string $type): ?array
    {
        $types = $this->getEventTypes();

        foreach ($types as $eventType) {
            if ($eventType['type'] === $type) {
                return $eventType;
            }
        }

        return null;
    }

    /**
     * Get certainty levels with metadata.
     */
    public function getCertaintyLevels(): array
    {
        return [
            [
                'value' => 'confirmed',
                'label' => 'Confirmed',
                'description' => 'This event is definitely happening',
                'weight' => 1.0,
            ],
            [
                'value' => 'likely',
                'label' => 'Likely',
                'description' => 'This event will probably happen',
                'weight' => 0.75,
            ],
            [
                'value' => 'possible',
                'label' => 'Possible',
                'description' => 'This event might happen',
                'weight' => 0.5,
            ],
            [
                'value' => 'speculative',
                'label' => 'Speculative',
                'description' => 'This event is uncertain',
                'weight' => 0.25,
            ],
        ];
    }

    /**
     * Create a new life event.
     */
    public function createEvent(int $userId, array $data): LifeEvent
    {
        // Auto-determine impact_type from event_type if not provided
        if (! isset($data['impact_type'])) {
            $data['impact_type'] = in_array($data['event_type'], LifeEvent::INCOME_EVENT_TYPES)
                ? 'income'
                : 'expense';
        }

        $data['user_id'] = $userId;

        $event = LifeEvent::create($data);

        return $event;
    }

    /**
     * Update an existing life event.
     */
    public function updateEvent(LifeEvent $event, array $data): LifeEvent
    {
        // Update impact_type if event_type changed
        if (isset($data['event_type']) && ! isset($data['impact_type'])) {
            $data['impact_type'] = in_array($data['event_type'], LifeEvent::INCOME_EVENT_TYPES)
                ? 'income'
                : 'expense';
        }

        $event->update($data);

        return $event->fresh();
    }

    /**
     * Delete a life event.
     */
    public function deleteEvent(LifeEvent $event): void
    {
        $event->delete();
    }

    /**
     * Mark an event as completed.
     */
    public function markCompleted(LifeEvent $event, ?Carbon $occurredAt = null): LifeEvent
    {
        $event->update([
            'status' => 'completed',
            'occurred_at' => $occurredAt ?? Carbon::now(),
        ]);

        return $event->fresh();
    }
}
