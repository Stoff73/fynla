<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\FinancialCalculationException;
use App\Models\Mortgage;
use App\Support\SecuringPropertyResolver;
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
            // W-0425 — same refusal as `userShareFraction`. This reads the ownership
            // columns ON the record via `calculateUserShare`, and a mortgage's share
            // follows the property securing it, so a mortgage here returns the
            // pre-W-0228 answer with nothing to indicate it.
            $this->refuseRecordWhoseShareFollowsAnother($asset);

            $view = clone $asset;
            $view->{$this->userShareColumn($asset)} = $this->calculateUserShare($asset, $userId);

            return $view;
        });
    }

    /**
     * Refuse a record whose share is NOT a function of its own ownership columns.
     *
     * One home for the rule, called by `atUserShare` and `userShareFraction`
     * (Rule 20). Both answer from the columns on the record handed to them, and
     * CSJ's W-0228 ruling makes a mortgage's share follow the PROPERTY securing
     * it — so both are wrong about a mortgage, in the same way, for the same
     * reason. The guard lived in only one of them until W-0425.
     *
     * It throws rather than falling through because a silent wrong share is the
     * failure mode this whole family of defects is made of: on the household
     * W-0425 was found against, a mortgage row saying joint 50% against a property
     * held tenants-in-common 40% returns £60,000 where £48,000 is correct, and
     * nothing about the number says so.
     *
     * @throws FinancialCalculationException when asked about a record whose share
     *                                       depends on a related record
     */
    private function refuseRecordWhoseShareFollowsAnother(object $asset): void
    {
        // W-0483 — a mortgage carrying an explicitly declared liability share is no
        // longer a record whose share follows another. It still refuses the accidental
        // case, which is the one the guard exists for: a caller reaching for
        // `ownership_percentage` on a mortgage row nobody reviewed.
        if (($asset->declared_liability_percentage ?? null) !== null) {
            return;
        }

        if (isset($asset->property_id) || $asset instanceof Mortgage) {
            throw FinancialCalculationException::invalidInput(
                'asset',
                $asset::class,
                'A mortgage share follows the property securing it (W-0228), not the '
                .'ownership columns on the mortgage row. Use calculateUserMortgageShare, '
                .'which resolves the property.'
            );
        }
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
     *
     * **The probe copies the ownership pair and nothing else, so this method is
     * only sound while a share is a pure function of the columns ON the record.**
     * A mortgage is not: CSJ's ruling makes its share follow the property
     * securing it (W-0228), and a probe built here would have discarded
     * `property_id` — the one field that rule needs — and returned a confidently
     * wrong fraction with nothing to indicate it. That is why the guard below
     * throws rather than falling through: a silent wrong share is the failure
     * mode this whole family of defects is made of.
     *
     * @throws FinancialCalculationException when asked about a record whose share
     *                                       depends on a related record
     */
    protected function userShareFraction(object $asset, int $userId): float
    {
        $this->refuseRecordWhoseShareFollowsAnother($asset);

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
     */
    protected function calculateUserMortgageMonthlyPaymentShare(object $mortgage, int $userId): float
    {
        $fullPayment = (float) ($mortgage->monthly_payment ?? 0);

        return $this->calculateUserMortgageAmountShare($mortgage, $userId, $fullPayment);
    }

    /**
     * **A debt is shared exactly as the asset securing it is shared** — CSJ's
     * ruling, 2026-08-22, recorded in full on W-0228. Not open to
     * re-litigation.
     *
     * The docblock that used to sit here said the opposite: *"mortgage liability
     * follows the mortgage borrower(s), not the ownership percentage recorded on
     * the linked property."* It was wrong, and it was the load-bearing part —
     * the code below matched it exactly, so a reviewer checking one against the
     * other passed it. Deleted rather than corrected, because the rule now lives
     * in the code as `propertyOwnershipFor()`, not in prose beside it.
     *
     * What the mismatch cost, live on one household at once: the property detail
     * read "Your Mortgage Share (40%) £48,000" while the Mortgage tab read
     * "Your mortgage liability £60,000" — two figures for one debt, four inches
     * apart, because the property said tenants-in-common 40% and the mortgage row
     * said joint 50%.
     *
     * **The property is authoritative.** Where a mortgage has no property to
     * resolve against, the mortgage's own columns are the only information that
     * exists and are used — stated here so the fallback is not mistaken for the
     * rule.
     *
     * **Amended by CSJ, 2026-08-30 (W-0483):** *"W-0228 can allow mortgage share
     * that is not the same as ownership share."* The limitation this docblock used
     * to record — that a mortgage in one spouse's sole name against a jointly-owned
     * property is inexpressible — is lifted, and the shape of the lift matters:
     *
     * - It is a **declared** share, `mortgages.declared_liability_percentage`, and
     *   nullable. Null means nobody has said anything and the property is
     *   authoritative, which is every row that existed before the column did.
     * - It is **not** `mortgages.ownership_percentage`. That column is populated
     *   everywhere, was never reviewed, and is precisely the unread value W-0228
     *   stopped trusting — the persona carries `joint 50%` on a mortgage secured on
     *   a `tenants_in_common 40%` property. Believing it would move a verified
     *   household figure by £12,000 and reintroduce the two-mechanism disagreement
     *   the ruling closed.
     *
     * So the ruling still holds by default and yields only to someone saying
     * otherwise, which is the "supplied beats inherited" shape W-0040 established.
     */
    private function calculateUserMortgageAmountShare(object $mortgage, int $userId, float $fullAmount): float
    {
        $declared = $this->declaredLiabilityShare($mortgage, $userId);

        if ($declared !== null) {
            return $fullAmount * $declared;
        }

        $securing = $this->propertyOwnershipFor($mortgage);

        $ownershipType = $securing->ownership_type ?? 'individual';

        // Individual ownership
        if ($ownershipType === 'individual' || $ownershipType === 'trust') {
            return $securing->user_id === $userId ? $fullAmount : 0.0;
        }

        // Joint ownership
        $percentage = (float) ($securing->ownership_percentage ?? 50);

        if ($securing->user_id === $userId) {
            return $fullAmount * ($percentage / 100);
        }

        if (($securing->joint_owner_id ?? null) === $userId) {
            return $fullAmount * ((100 - $percentage) / 100);
        }

        return 0.0;
    }

    /**
     * The share of a mortgage this user has been DECLARED to carry, or null where
     * nobody has declared one (W-0483).
     *
     * Returned as a 0..1 multiplier so the caller cannot forget to divide. The
     * declared figure is the share belonging to the mortgage's own `user_id`, the
     * same convention `ownership_percentage` uses everywhere else in the
     * application — the counterparty gets the remainder — so there is one reading
     * of "percentage" to learn, not two.
     *
     * A user who is neither the borrower nor the counterparty carries none of it.
     */
    private function declaredLiabilityShare(object $mortgage, int $userId): ?float
    {
        $declared = $mortgage->declared_liability_percentage ?? null;

        if ($declared === null) {
            return null;
        }

        $percentage = (float) $declared;
        $securing = $this->propertyOwnershipFor($mortgage);

        if (($mortgage->user_id ?? null) === $userId) {
            return $percentage / 100;
        }

        // The counterparty is whoever else owns the property securing it. A sole
        // borrower declaring 100% leaves them zero, which is the case this exists for.
        $counterpartyId = ($securing->user_id ?? null) === ($mortgage->user_id ?? null)
            ? ($securing->joint_owner_id ?? null)
            : ($securing->user_id ?? null);

        if ($counterpartyId !== null && $counterpartyId === $userId) {
            return (100 - $percentage) / 100;
        }

        return 0.0;
    }

    /**
     * The ownership a mortgage inherits from the property securing it — the ONE
     * reader for that question (Rule 20).
     *
     * Four mechanisms answered it before, and they did not agree:
     *
     * | Mechanism | Behaviour |
     * |---|---|
     * | `calculateUserMortgageAmountShare` | read the pair off the **mortgage row** — the bug |
     * | `EstateController::index` | copied the property's pair onto the mortgage — right, and a second implementation |
     * | `PropertyService::calculateTaxPosition` | its own inline `$ownershipMultiplier` over the property — right, and a third |
     * | `PropertyService::calculateUserEquity` | its own inline copy again — right, and a fourth |
     *
     * All four now compose from this. Nothing here re-derives a share: it
     * resolves WHICH record the share is read from, and `calculateUserShare`
     * still answers what the share is.
     *
     * The resolution itself lives in `SecuringPropertyResolver`, a container
     * singleton, because it has to memoise and **a `static` declared in a trait
     * is per using class** — a dozen services use this trait, so a static here
     * would be a dozen caches with no single way to clear any of them.
     *
     * @return object carrying user_id, joint_owner_id, ownership_type and
     *                ownership_percentage — the property's where one secures the
     *                mortgage, the mortgage's own where none does
     */
    private function propertyOwnershipFor(object $mortgage): object
    {
        return app(SecuringPropertyResolver::class)->for($mortgage);
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
