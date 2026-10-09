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
     *
     * Both owners of a joint record own it (CSJ 2026-10-08: "for joint
     * accounts, both parties have ownership"), so the Stores find a record to
     * change or remove with this scope, then carry on as its primary owner
     * (`user_id`): the change is the record's, and everything derived from it
     * (ownership links, the dividend total, events) moves exactly as it does
     * when the primary owner makes it.
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
