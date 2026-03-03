<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for models that support joint ownership.
 *
 * Provides query scopes for filtering records where the user is either
 * the primary owner (user_id) or joint owner (joint_owner_id).
 */
trait HasJointOwnership
{
    /**
     * Scope to get records where user is owner or joint owner.
     */
    public function scopeForUserOrJoint(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('joint_owner_id', $userId);
        });
    }

    /**
     * Scope to get records where user is the primary owner only.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get records where user is the joint owner only.
     */
    public function scopeForJointOwner(Builder $query, int $userId): Builder
    {
        return $query->where('joint_owner_id', $userId);
    }

    /**
     * Check if the given user has any ownership in this record.
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId || $this->joint_owner_id === $userId;
    }

    /**
     * Check if this record has joint ownership.
     */
    public function hasJointOwner(): bool
    {
        return $this->joint_owner_id !== null;
    }
}
