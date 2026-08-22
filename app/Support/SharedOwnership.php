<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one home for the joint-ownership share rules (CLAUDE.md Rule 6, Rule 20).
 *
 * Single-record architecture: ONE row holds the FULL asset value,
 * `ownership_percentage` is the PRIMARY owner's share, and the joint owner
 * (`joint_owner_id`) holds the remainder.
 *
 * Before this class the same rule was implemented eight times — once in each of
 * the four Store normalisers, twice inline in controllers, once as a hard
 * validation reject on savings, and once as a read-side `100 → 50` rewrite in
 * `CalculatesOwnershipShare`. They disagreed, which is how a joint account came
 * to be stored at 100/0 and rendered as £95,000 to both spouses (W-0014/W-0015).
 * Every one of those call sites now reads this class. Do not add a ninth copy.
 *
 * The Fyn tool handlers, `PropertyNormaliser::fromFyn` and `LiabilityStore` were
 * never part of that sweep and carried five more copies between them; W-0040
 * brought them here too. A caller that computes a default share of its own is a
 * copy, however small — and a caller that sends a share it does not mean is the
 * same bug wearing a payload.
 */
final class SharedOwnership
{
    /**
     * The primary owner's share when a shared asset arrives without one.
     *
     * No form in the app exposes a share input for joint ownership, so a
     * shared asset that says nothing about the split means a 50/50.
     */
    public const DEFAULT_PERCENTAGE = 50.0;

    /** The share a solely-owned asset carries. */
    public const INDIVIDUAL_PERCENTAGE = 100.0;

    /** Ownership types whose value is split between two parties. */
    public const SHARED_TYPES = ['joint', 'tenants_in_common'];

    /**
     * Is this ownership type split between a primary and a joint owner?
     */
    public static function isShared(?string $ownershipType): bool
    {
        return in_array($ownershipType, self::SHARED_TYPES, true);
    }

    /**
     * The primary owner's share to STORE for an asset of this ownership type.
     *
     * **Supplied beats inherited (W-0040).** A share the caller stated is a fact
     * they asserted and is kept exactly as given; a share they said nothing
     * about is defaulted. The two used to be indistinguishable: a submitted 100
     * on a shared asset was read as "the individual default a form never
     * cleared" and quietly rewritten to 50, so a caller stating *"I own all of
     * it"* was told 201 and stored as *"I own half of it"* — while a caller
     * stating `0` was refused. Nobody chose that asymmetry; it fell out of the
     * coercion. CSJ's ruling is that a 100/0 split **is** individual ownership,
     * so a stated 100 must never become a joint 50/50 record.
     *
     * A stated share that is not a shared split at all (0 or 100) is refused by
     * the validation layers via {@see isValidSharedSplit()} rather than being
     * rewritten here — nothing on this path may silently alter a stated figure.
     *
     * Callers must therefore send `ownership_percentage` only when they mean
     * it. A form with no share input sends nothing and gets the default; it
     * must not echo an individual default of 100 on a joint payload, which is
     * what made the two cases indistinguishable in the first place.
     */
    public static function primaryOwnerPercentage(?string $ownershipType, mixed $submitted): float
    {
        $given = self::statedShare($submitted);

        if (! self::isShared($ownershipType)) {
            return $given ?? self::INDIVIDUAL_PERCENTAGE;
        }

        return $given ?? self::DEFAULT_PERCENTAGE;
    }

    /**
     * Apply {@see primaryOwnerPercentage()} to a payload keyed
     * `ownership_type` / `ownership_percentage`.
     *
     * `$existing` is the stored record on an update. Where the caller states no
     * share, the one already on record is kept rather than re-defaulted —
     * otherwise every update from a form with no share input would rewrite a
     * stored 70 to 50, which is the same silent overwrite in a new place
     * (the trap `HoldingValuation::reconcile()` closes at the valuation
     * boundary). On a create there is nothing to inherit, so the default
     * applies as before.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyTo(array $data, ?string $ownershipType = null, ?object $existing = null): array
    {
        $type = $ownershipType ?? ($data['ownership_type'] ?? null);
        $type = is_string($type) ? $type : null;

        $stated = self::statedShare($data['ownership_percentage'] ?? null);

        // Only a share that is itself a shared split can be inherited. A stored
        // 100 is what an individually-owned asset carries by definition, so an
        // account being CONVERTED to joint must re-default to 50 rather than
        // carry its individual 100 across — and a legacy shared row stored at
        // 100 (the damage W-0014 did) is a value nobody stated, so defaulting it
        // is not the silent rewrite of a stated figure this item forbids.
        if ($stated === null && $existing !== null && self::isShared($type)) {
            $inherited = $existing->ownership_percentage ?? null;

            if (self::isValidSharedSplit($inherited)) {
                $data['ownership_percentage'] = (float) $inherited;

                return $data;
            }
        }

        $data['ownership_percentage'] = self::primaryOwnerPercentage($type, $stated);

        return $data;
    }

    /**
     * Is this a share the caller actually stated?
     *
     * Absent, null and empty string all mean "said nothing"; anything else is
     * an assertion, including 0 and 100.
     */
    public static function statedShare(mixed $submitted): ?float
    {
        return ($submitted === null || $submitted === '') ? null : (float) $submitted;
    }

    /**
     * Is this a share two parties can actually hold between them?
     *
     * A shared asset splits its value, so the primary owner's share lies
     * strictly between the two ends. `0` means the whole asset is someone
     * else's and `100` means it is entirely the primary owner's — both are
     * individual ownership described with the wrong `ownership_type`, and per
     * CSJ's ruling on W-0040 neither may be stored as a shared record.
     *
     * The validation layers refuse a stated share that fails this. It is never
     * corrected silently: a figure a user typed is either honoured or refused.
     */
    public static function isValidSharedSplit(mixed $share): bool
    {
        $value = self::statedShare($share);

        return $value !== null && $value > 0.0 && $value < self::INDIVIDUAL_PERCENTAGE;
    }

    /**
     * Does this payload name the OTHER party on a shared asset?
     *
     * A shared asset needs someone to share it with. The counterparty is either
     * a linked account (`joint_owner_id`) or, where the table carries the
     * column, a free-text name for someone who is not on the platform
     * (`joint_owner_name`) — the persona's tenants-in-common co-owner is exactly
     * that case.
     *
     * Neither means 50% of the asset belongs to nobody: the primary owner's
     * net worth silently loses half of it, and because every joint read is
     * `WHERE user_id = ? OR joint_owner_id = ?`, no one else ever sees the rest
     * (W-0025).
     *
     * @param  array<string, mixed>  $data
     */
    public static function namesCounterparty(array $data): bool
    {
        if (($data['joint_owner_id'] ?? null) !== null) {
            return true;
        }

        return trim((string) ($data['joint_owner_name'] ?? '')) !== '';
    }

    /**
     * The share the OTHER party holds, given the primary owner's share.
     */
    public static function jointOwnerPercentage(float $primaryOwnerPercentage): float
    {
        return 100.0 - $primaryOwnerPercentage;
    }
}
