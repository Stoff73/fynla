<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Trait for calculating user's share of jointly-owned assets.
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL asset value
 * - user_id = primary owner
 * - joint_owner_id = secondary owner (nullable)
 * - ownership_percentage = primary owner's share (default 50 for joint)
 *
 * Usage:
 * - Primary owner (user_id): Gets ownership_percentage of full value
 * - Joint owner (joint_owner_id): Gets (100 - ownership_percentage) of full value
 * - Individual owner: Gets 100% of full value
 */
trait CalculatesOwnershipShare
{
    /**
     * Calculate user's share of an asset value.
     *
     * @param  object  $asset  The asset record (Property, SavingsAccount, InvestmentAccount, Mortgage)
     * @param  int  $userId  The user requesting the calculation
     * @return float The user's share of the asset value
     */
    protected function calculateUserShare(object $asset, int $userId): float
    {
        // Get the full value - supports both current_value (properties/investments) and current_balance (savings)
        $fullValue = (float) ($asset->current_value ?? $asset->current_balance ?? $asset->outstanding_balance ?? 0);

        $ownershipType = $asset->ownership_type ?? 'individual';

        // Individual ownership - full value belongs to owner
        if ($ownershipType === 'individual' || $ownershipType === 'trust') {
            return $asset->user_id === $userId ? $fullValue : 0.0;
        }

        // Joint or tenants_in_common ownership
        $percentage = (float) ($asset->ownership_percentage ?? 50);

        if ($asset->user_id === $userId) {
            // Primary owner gets their ownership_percentage
            return $fullValue * ($percentage / 100);
        }

        if (($asset->joint_owner_id ?? null) === $userId) {
            // Secondary owner gets the complementary share
            return $fullValue * ((100 - $percentage) / 100);
        }

        // User not associated with this asset
        return 0.0;
    }

    /**
     * Calculate user's share of mortgage liability.
     *
     * @param  object  $mortgage  The mortgage record
     * @param  int  $userId  The user requesting the calculation
     * @return float The user's share of the mortgage balance
     */
    protected function calculateUserMortgageShare(object $mortgage, int $userId): float
    {
        $fullBalance = (float) ($mortgage->outstanding_balance ?? 0);

        $ownershipType = $mortgage->ownership_type ?? 'individual';

        // Individual ownership
        if ($ownershipType === 'individual' || $ownershipType === 'trust') {
            return $mortgage->user_id === $userId ? $fullBalance : 0.0;
        }

        // Joint ownership
        $percentage = (float) ($mortgage->ownership_percentage ?? 50);

        if ($mortgage->user_id === $userId) {
            return $fullBalance * ($percentage / 100);
        }

        if (($mortgage->joint_owner_id ?? null) === $userId) {
            return $fullBalance * ((100 - $percentage) / 100);
        }

        return 0.0;
    }

    /**
     * Check if user has any ownership in an asset.
     *
     * @param  object  $asset  The asset record
     * @param  int  $userId  The user to check
     * @return bool True if user owns or co-owns the asset
     */
    protected function userOwnsAsset(object $asset, int $userId): bool
    {
        return $asset->user_id === $userId ||
               ($asset->joint_owner_id ?? null) === $userId;
    }

    /**
     * Check if user is the primary owner of an asset.
     *
     * @param  object  $asset  The asset record
     * @param  int  $userId  The user to check
     * @return bool True if user is the primary owner (user_id)
     */
    protected function isPrimaryOwner(object $asset, int $userId): bool
    {
        return $asset->user_id === $userId;
    }

    /**
     * Check if asset has shared ownership (joint or tenants in common).
     *
     * @param  object  $asset  The asset record
     * @return bool True if asset has shared ownership
     */
    protected function isSharedOwnership(object $asset): bool
    {
        $ownershipType = $asset->ownership_type ?? 'individual';

        return in_array($ownershipType, ['joint', 'tenants_in_common'], true);
    }

    /**
     * Get the full value of an asset (regardless of ownership share).
     *
     * @param  object  $asset  The asset record
     * @return float The full asset value
     */
    protected function getFullValue(object $asset): float
    {
        return (float) ($asset->current_value ?? $asset->current_balance ?? $asset->outstanding_balance ?? 0);
    }
}
