<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\AuditLog;
use App\Models\LifeEvent;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LifeEventStore
{
    public const ENTITY_KEY = 'life_event';

    public function __construct(
        private readonly TierGate $tierGate,
    ) {}

    public function countForUser(User $user): int
    {
        return LifeEvent::forUserOrJoint($user->id)->count();
    }

    public function create(array $canonical, User $user, IngestSource $source): LifeEvent
    {
        $currentCount = $this->countForUser($user);
        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $currentCount)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $currentCount,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY),
            );
        }

        $attributes = array_merge($canonical, ['user_id' => $user->id]);

        $this->validateCanonical($attributes);

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(fn () => LifeEvent::create($attributes)),
        );
    }

    /**
     * Canonical-shape sanity check on the accepted-value columns (W-0501).
     *
     * Same footing as GoalStore::validateCanonical — the enum columns only, as a
     * sanity check rather than a stricter gate. See that docblock for why.
     *
     * **event_type composes from the model constants rather than retyping the
     * list**, per Rule 20. The vocabulary already had a home in
     * LifeEvent::INCOME_EVENT_TYPES and ::EXPENSE_EVENT_TYPES, which
     * LifeEventService::createEvent reads to derive impact_type. A fourth copy
     * here — after StoreLifeEventRequest:26 and
     * CoordinatingAgent::handleCreateLifeEvent, which each retype it — would be
     * one more place to miss when the list changes.
     *
     * @param  array<string, mixed>  $canonical
     */
    private function validateCanonical(array $canonical): void
    {
        $rules = [
            'user_id' => 'sometimes|integer|exists:users,id',
            // Kept on one line, with the constants named inline rather than via a
            // local, because StoreEnumRulesMatchColumnsTest resolves a composed
            // list by reading the constant references out of the source. Behind a
            // variable it parses as an in: rule with no values, which the guard
            // silently skipped — a rule invisible to its own drift guard.
            'event_type' => 'sometimes|in:'.implode(',', array_merge(LifeEvent::INCOME_EVENT_TYPES, LifeEvent::EXPENSE_EVENT_TYPES)),
            'impact_type' => 'sometimes|nullable|in:income,expense',
            'certainty' => 'sometimes|nullable|in:confirmed,likely,possible,speculative',
            'ownership_type' => 'sometimes|nullable|in:individual,joint',
            'status' => 'sometimes|nullable|in:expected,confirmed,completed,cancelled',
        ];

        $validator = Validator::make($canonical, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
