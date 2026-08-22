<?php

declare(strict_types=1);

namespace App\Traits;

use App\Support\SharedOwnership;
use Illuminate\Support\Collection;

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
        // Get the full value - supports current_value (properties/investments/chattels), current_balance (savings),
        // current_valuation (business interests), and outstanding_balance (mortgages/liabilities)
        $fullValue = (float) ($asset->current_value ?? $asset->current_balance ?? $asset->current_valuation ?? $asset->outstanding_balance ?? 0);

        $ownershipType = $asset->ownership_type ?? 'individual';
        $percentage = (float) ($asset->ownership_percentage ?? 100);

        // Business interests: ownership_percentage always applies (represents shareholding)
        // Detect business interest by checking for current_valuation field AND business_name
        $isBusinessInterest = isset($asset->current_valuation) && isset($asset->business_name);

        if ($isBusinessInterest) {
            // Trust ownership - trustee/business controlled by trust
            if ($ownershipType === 'trust') {
                return $asset->user_id === $userId ? $fullValue : 0.0;
            }

            // Individual ownership - use ownership_percentage for shareholding
            // (e.g., owning 60% of a company individually)
            if ($ownershipType === 'individual') {
                return $asset->user_id === $userId ? $fullValue * ($percentage / 100) : 0.0;
            }

            // Joint ownership - split between user and joint_owner based on percentage
            if ($asset->user_id === $userId) {
                return $fullValue * ($percentage / 100);
            }

            if (($asset->joint_owner_id ?? null) === $userId) {
                return $fullValue * ((100 - $percentage) / 100);
            }

            return 0.0;
        }

        // Non-business assets: individual/trust means 100% ownership
        if ($ownershipType === 'individual' || $ownershipType === 'trust') {
            return $asset->user_id === $userId ? $fullValue : 0.0;
        }

        // Joint or tenants_in_common ownership - the stored ownership_percentage IS
        // the primary owner's share. This used to silently rewrite a stored 100 to 50,
        // which masked the write-side bug that stored joint assets at 100/0 (W-0014)
        // and made every non-trait consumer disagree with every trait consumer
        // (W-0015). SharedOwnership normalises the share on the way IN instead.
        if ($asset->user_id === $userId) {
            // Primary owner gets their ownership_percentage
            return $fullValue * ($percentage / 100);
        }

        if (($asset->joint_owner_id ?? null) === $userId) {
            // Secondary owner gets the complementary share
            return $fullValue * (SharedOwnership::jointOwnerPercentage($percentage) / 100);
        }

        // User not associated with this asset
        return 0.0;
    }

    /**
     * The user's own view of a set of records: the same records, each carrying
     * THIS user's share in place of the full value.
     *
     * A module analysis derives dozens of figures from one collection — a
     * liquidity ladder, a rate comparison, a deposit-protection exposure. Every
     * one of them sums the value column, so handing them the raw records charges
     * the recording owner with the whole of a jointly-held account and shows the
     * co-owner nothing (W-0238). Applying the share once, here, keeps every
     * derived figure at the user's fraction without each analyzer learning about
     * ownership.
     *
     * **The returned models are read-only presentation copies.** They are clones
     * carrying a value the database does not hold, so saving one would write a
     * half-balance over a whole one. Every consumer of this method must be a pure
     * reader; nothing here may be persisted.
     *
     * @param  iterable<int, object>  $assets
     * @return Collection<int, object>
     */
    protected function atUserShare(iterable $assets, int $userId): Collection
    {
        // Keep the caller's collection class. An Eloquent collection of models
        // maps to an Eloquent collection, which is what the analyzers type-hint;
        // a bare collect() would hand them a base collection and TypeError.
        $collection = $assets instanceof Collection ? $assets : collect($assets);

        return $collection->map(function (object $asset) use ($userId): object {
            $view = clone $asset;
            $view->{$this->userShareColumn($asset)} = $this->calculateUserShare($asset, $userId);

            return $view;
        });
    }

    /**
     * What proportion of a record belongs to this user, as a multiplier in 0..1.
     *
     * For figures that hang off a record without carrying ownership columns of
     * their own — a holding belongs to an investment account, and the account is
     * what is jointly held. Asking `calculateUserShare` for the record's value
     * and dividing would break on a record valued at zero, so this asks the same
     * question of a unit-valued probe instead. One home, one set of rules; only
     * the value it is asked about differs.
     */
    protected function userShareFraction(object $asset, int $userId): float
    {
        $probe = (object) [
            'user_id' => $asset->user_id ?? null,
            'joint_owner_id' => $asset->joint_owner_id ?? null,
            'ownership_type' => $asset->ownership_type ?? 'individual',
            'ownership_percentage' => $asset->ownership_percentage ?? 100,
        ];

        // A business interest's percentage is a shareholding and applies even
        // when individually held, which calculateUserShare detects from these two
        // fields being present together. The probe has to look like one or the
        // rule it is asking about would not fire.
        if (isset($asset->current_valuation, $asset->business_name)) {
            $probe->current_valuation = 1.0;
            $probe->business_name = $asset->business_name;
        } else {
            $probe->current_value = 1.0;
        }

        return $this->calculateUserShare($probe, $userId);
    }

    /**
     * Which attribute holds the value that a share applies to. Mirrors the
     * fallback chain in calculateUserShare/getFullValue so the two cannot drift.
     */
    private function userShareColumn(object $asset): string
    {
        return match (true) {
            isset($asset->current_value) => 'current_value',
            isset($asset->current_balance) => 'current_balance',
            isset($asset->current_valuation) => 'current_valuation',
            isset($asset->outstanding_balance) => 'outstanding_balance',
            default => 'current_value',
        };
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

        return $this->calculateUserMortgageAmountShare($mortgage, $userId, $fullBalance);
    }

    /**
     * Calculate the user's share of a mortgage monthly payment.
     *
     * Mortgage liability follows the mortgage borrower(s), not the ownership
     * percentage recorded on the linked property.
     */
    protected function calculateUserMortgageMonthlyPaymentShare(object $mortgage, int $userId): float
    {
        $fullPayment = (float) ($mortgage->monthly_payment ?? 0);

        return $this->calculateUserMortgageAmountShare($mortgage, $userId, $fullPayment);
    }

    /**
     * Calculate the user's share of a mortgage amount using the mortgage's
     * borrower configuration.
     */
    private function calculateUserMortgageAmountShare(object $mortgage, int $userId, float $fullAmount): float
    {
        $ownershipType = $mortgage->ownership_type ?? 'individual';

        // Individual ownership
        if ($ownershipType === 'individual' || $ownershipType === 'trust') {
            return $mortgage->user_id === $userId ? $fullAmount : 0.0;
        }

        // Joint ownership
        $percentage = (float) ($mortgage->ownership_percentage ?? 50);

        if ($mortgage->user_id === $userId) {
            return $fullAmount * ($percentage / 100);
        }

        if (($mortgage->joint_owner_id ?? null) === $userId) {
            return $fullAmount * ((100 - $percentage) / 100);
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
        return SharedOwnership::isShared($asset->ownership_type ?? 'individual');
    }

    /**
     * Get the full value of an asset (regardless of ownership share).
     *
     * @param  object  $asset  The asset record
     * @return float The full asset value
     */
    protected function getFullValue(object $asset): float
    {
        return (float) ($asset->current_value ?? $asset->current_balance ?? $asset->current_valuation ?? $asset->outstanding_balance ?? 0);
    }
}
