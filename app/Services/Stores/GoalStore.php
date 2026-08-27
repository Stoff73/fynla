<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\AuditLog;
use App\Models\Goal;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GoalStore
{
    public const ENTITY_KEY = 'goal';

    public function __construct(
        private readonly TierGate $tierGate,
    ) {}

    public function countForUser(User $user): int
    {
        return Goal::forUserOrJoint($user->id)->count();
    }

    public function create(array $canonical, User $user, IngestSource $source): Goal
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
            fn () => DB::transaction(fn () => Goal::create($attributes)),
        );
    }

    /**
     * Canonical-shape sanity check on the accepted-value columns (W-0501).
     *
     * Deliberately a sanity check and NOT a stricter gate, following the
     * philosophy already stated at PensionStore::validateDcCanonical: each list
     * is the column's own enum, so this can refuse nothing the table would have
     * stored. What it changes is WHERE an impossible value is caught — a
     * StoreValidationException naming the field, rather than a raw SQLSTATE
     * 22001 from MySQL with nothing to attribute it to.
     *
     * Only the enum columns are listed. Types, bounds and requiredness stay with
     * StoreGoalRequest and with Fyn's own tool-input rules, because widening this
     * into a full mirror of the request would make the Store a stricter gate than
     * the two callers were written against.
     *
     * **Neither caller can currently reach this**, and that is the point rather
     * than an argument against it: GoalsController validates through
     * StoreGoalRequest, and CoordinatingAgent::handleCreateGoal validates
     * goal_type and priority through validateToolInput before building its
     * payload. The guarantee therefore rests entirely on every caller
     * remembering — this Store is create-only and has no other line of defence.
     *
     * @param  array<string, mixed>  $canonical
     */
    private function validateCanonical(array $canonical): void
    {
        $rules = [
            'user_id' => 'sometimes|integer|exists:users,id',
            'goal_type' => 'sometimes|nullable|in:emergency_fund,property_purchase,home_deposit,education,retirement,wealth_accumulation,wedding,holiday,car_purchase,debt_repayment,custom',
            'assigned_module' => 'sometimes|nullable|in:savings,investment,property,retirement',
            'priority' => 'sometimes|nullable|in:critical,high,medium,low',
            'status' => 'sometimes|nullable|in:active,paused,completed,abandoned',
            'contribution_frequency' => 'sometimes|nullable|in:weekly,monthly,quarterly,annually',
            'ownership_type' => 'sometimes|nullable|in:individual,joint',
            'property_type' => 'sometimes|nullable|in:house,flat,bungalow,terraced,semi_detached,detached,other',
        ];

        $validator = Validator::make($canonical, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
