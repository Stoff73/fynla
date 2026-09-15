<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Estate\Liability;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Support\SharedOwnership;
use Illuminate\Database\Eloquent\Model;

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

    /** @var array<string, class-string<Model>> */
    private const MODELS = [
        'savings_account' => SavingsAccount::class,
        'investment_account' => InvestmentAccount::class,
        'property' => Property::class,
        'mortgage' => Mortgage::class,
        'liability' => Liability::class,
    ];

    /**
     * Remember a joint record saved without a named co-owner, when the
     * household has a spouse, and fill what is already known.
     */
    public function remember(User $user, string $entityType, int $entityId): void
    {
        if (! isset(self::MODELS[$entityType]) || ! $this->householdHasSpouse($user)) {
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
            $model = self::MODELS[$entry['type'] ?? ''] ?? null;
            $record = $model !== null ? $model::query()->find($entry['id'] ?? 0) : null;
            if ($record === null) {
                continue; // deleted since — nothing to fill
            }
            if ($record->joint_owner_id === null && $spouseId !== null) {
                $record->joint_owner_id = $spouseId;
            }
            if ($spouseName !== null
                && (SharedOwnership::counterpartyName($record->joint_owner_name) === null
                    || SharedOwnership::isRelationshipPlaceholder($record->joint_owner_name))) {
                $record->joint_owner_name = $spouseName;
            }
            if ($record->isDirty()) {
                $record->save();
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
