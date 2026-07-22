<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\AuditLog;
use App\Models\Goal;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use Illuminate\Support\Facades\DB;

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

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(fn () => Goal::create($attributes)),
        );
    }
}
