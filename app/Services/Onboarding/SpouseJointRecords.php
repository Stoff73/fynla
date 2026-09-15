<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\LiabilityStore;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
use App\Support\SharedOwnership;

/**
 * The onboarding memory for "this joint record is shared with my spouse".
 *
 * Mid-onboarding a joint account is usually saved before Fyn knows the
 * spouse by name (the funnel only recorded "has a spouse") and long before
 * the two accounts are linked. Nothing invented goes on the record: the
 * record is remembered here, its co-owner name is filled the moment the
 * spouse row carries a first name, and its co-owner id the moment the
 * invitation links the accounts (CSJ 2026-09-15). One home for the rule —
 * the tool executor remembers, the spouse-row writers apply.
 */
final class SpouseJointRecords
{
    public const CONTEXT_KEY = 'spouse_joint_records';

    /**
     * The canonical store for each record type — every read and write of a
     * record goes through its store (the store-boundary architecture tests).
     *
     * @var array<string, class-string>
     */
    private const STORES = [
        'savings_account' => SavingsStore::class,
        'investment_account' => InvestmentAccountStore::class,
        'property' => PropertyStore::class,
        'mortgage' => MortgageStore::class,
        'liability' => LiabilityStore::class,
    ];

    /**
     * Remember a joint record saved without a named co-owner, when the
     * household has a spouse, and fill what is already known.
     */
    public function remember(User $user, string $entityType, int $entityId): void
    {
        if (! isset(self::STORES[$entityType]) || ! $this->householdHasSpouse($user)) {
            return;
        }

        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $entries = $context[self::CONTEXT_KEY] ?? [];
        $entry = ['type' => $entityType, 'id' => $entityId];
        if (! in_array($entry, $entries, true)) {
            $entries[] = $entry;
        }
        $context[self::CONTEXT_KEY] = $entries;
        $user->onboarding_fyn_context = $context;
        $user->save();

        $this->apply($user);
    }

    /**
     * Fill the remembered records with whatever the household now knows:
     * the spouse's first name from their family row, and their account id
     * once the two accounts are linked. A record is forgotten once it
     * carries the id — there is nothing left to fill.
     */
    public function apply(User $user): void
    {
        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $entries = $context[self::CONTEXT_KEY] ?? [];
        if ($entries === []) {
            return;
        }

        $spouseId = $user->spouse_id ? (int) $user->spouse_id : null;
        $spouseName = $this->spouseFirstName($user);
        if ($spouseId === null && $spouseName === null) {
            return;
        }

        $remaining = [];
        foreach ($entries as $entry) {
            $storeClass = self::STORES[$entry['type'] ?? ''] ?? null;
            $store = $storeClass !== null ? app($storeClass) : null;
            $record = $store !== null ? $store->find((int) ($entry['id'] ?? 0), $user) : null;
            if ($record === null) {
                continue; // deleted since — nothing to fill
            }
            $changes = [];
            if ($record->joint_owner_id === null && $spouseId !== null) {
                $changes['joint_owner_id'] = $spouseId;
            }
            if ($spouseName !== null
                && (SharedOwnership::counterpartyName($record->joint_owner_name) === null
                    || SharedOwnership::isRelationshipPlaceholder($record->joint_owner_name))) {
                $changes['joint_owner_name'] = $spouseName;
            }
            if ($changes !== []) {
                $record = $store->update((int) $record->id, $changes, $user, IngestSource::FYN_AI);
            }
            if ($record->joint_owner_id === null) {
                $remaining[] = $entry;
            }
        }

        $context[self::CONTEXT_KEY] = $remaining;
        if ($remaining === []) {
            unset($context[self::CONTEXT_KEY]);
        }
        $user->onboarding_fyn_context = $context === [] ? null : $context;
        $user->save();
    }

    /**
     * Has the household said there is a spouse? The funnel answer, the
     * marital status, an existing spouse row or a linked account all count.
     */
    public function householdHasSpouse(User $user): bool
    {
        if ($user->spouse_id) {
            return true;
        }
        if (in_array($user->marital_status, ['married', 'civil_partnership'], true)) {
            return true;
        }
        $funnel = is_array($user->funnel_answers ?? null) ? $user->funnel_answers : [];
        if (($funnel['spouse'] ?? null) === 'yes') {
            return true;
        }

        return FamilyMember::where('user_id', $user->id)->where('relationship', 'spouse')->exists();
    }

    private function spouseFirstName(User $user): ?string
    {
        if ($user->spouse_id) {
            $linked = User::query()->find($user->spouse_id);
            if ($linked !== null && trim((string) $linked->first_name) !== '') {
                return trim((string) $linked->first_name);
            }
        }
        $row = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'spouse')
            ->latest()
            ->first();
        $name = $row !== null ? trim((string) $row->first_name) : '';

        return $name !== '' && strcasecmp($name, 'Spouse') !== 0 ? $name : null;
    }
}
