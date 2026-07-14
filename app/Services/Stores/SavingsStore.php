<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Savings\SavingsAccountCreated;
use App\Events\Savings\SavingsAccountDeleted;
use App\Events\Savings\SavingsAccountRestored;
use App\Events\Savings\SavingsAccountUpdated;
use App\Models\AuditLog;
use App\Models\Estate\Trust;
use App\Models\SavingsAccount;
use App\Models\SavingsAccountValueSnapshot;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Recalc\SavingsAccountDerivedColumnCalculator;
use App\Services\Stores\Snapshots\SnapshotPolicies;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SavingsStore
{
    public const ENTITY_KEY = 'savings_account';

    public function __construct(
        private readonly TierGate $tierGate,
        private readonly SavingsAccountDerivedColumnCalculator $derivedCalc,
        private readonly SnapshotPolicies $snapshotPolicies,
    ) {}

    // ---------- Reads ----------

    public function find(int $id, User $user): ?SavingsAccount
    {
        return SavingsAccount::query()
            ->where('id', $id)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->first();
    }

    public function forUser(User $user): Collection
    {
        return SavingsAccount::forUserOrJoint($user->id)->get();
    }

    public function existsForUser(User $user): bool
    {
        return SavingsAccount::forUserOrJoint($user->id)->exists();
    }

    /**
     * Joint-aware read with the `jointOwner` relation eager-loaded.
     *
     * For consumers that render the co-owner's name (e.g. the AI
     * existing-records prompt) and must not trip
     * Model::preventLazyLoading on staging — savings_accounts has no
     * joint_owner_name column, so the co-owner can only resolve via the
     * relation.
     */
    public function forUserWithJointOwner(User $user): Collection
    {
        return SavingsAccount::forUserOrJoint($user->id)->with('jointOwner')->get();
    }

    /**
     * Multi-user, joint-aware read. Returns every SavingsAccount where any
     * supplied user ID appears as primary owner or joint owner.
     * Used by household / multi-user contexts (e.g. RetirementIncomeService).
     */
    public function forUsers(array $userIds): Collection
    {
        if ($userIds === []) {
            return new Collection;
        }

        return SavingsAccount::query()
            ->where(function ($q) use ($userIds) {
                $q->whereIn('user_id', $userIds)
                    ->orWhereIn('joint_owner_id', $userIds);
            })
            ->get();
    }

    /**
     * User-scoped, joint-aware id-based read. Returns only accounts in $ids
     * the user owns or is the joint owner of. Returns an empty Collection for
     * unknown ids or ids belonging to other users — preventing cross-user leakage
     * even when callers pass externally-supplied id arrays (e.g. allocation
     * payloads from HTTP requests).
     */
    public function findMany(array $ids, User $user): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return SavingsAccount::query()
            ->whereIn('id', $ids)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->get();
    }

    /**
     * Gate-accurate count of the user's savings accounts: primary-owner rows
     * only, matching what canCreate enforces (joint-owned accounts don't count
     * toward the cap). Single source for both create() and the free-tier cap
     * surfaced on the savings screen (/m freemium 5.1), so the displayed
     * "X of Y" can never diverge from the gate.
     */
    public function countForUser(User $user): int
    {
        return SavingsAccount::where('user_id', $user->id)->count();
    }

    // ---------- Writes ----------

    public function create(array $data, User $user, IngestSource $source): SavingsAccount
    {
        $this->validateCanonical($data);
        $this->validateOwnershipLinks($data, $user);

        $count = $this->countForUser($user);
        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }

        $attributes = array_merge($data, ['user_id' => $user->id]);

        return AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($attributes, $user, $source) {
            $account = SavingsAccount::create($attributes);

            $this->recalculateDerived($account, $source, 'create');

            event(new SavingsAccountCreated($account, $user, $source));

            return $account;
        }));
    }

    /**
     * Update the savings account. Only the primary owner (user_id) may mutate;
     * joint owners have read-only access via find() / forUser(). Matches the
     * pre-store SavingsController::updateAccount contract.
     */
    public function update(int $id, array $data, User $user, IngestSource $source): SavingsAccount
    {
        $account = SavingsAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($data);
        $this->validateOwnershipLinks(array_merge(
            $account->only(['account_type', 'is_isa', 'ownership_type', 'ownership_percentage', 'joint_owner_id', 'trust_id']),
            $data
        ), $user);

        return AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($account, $data, $user, $source) {
            // fill before getDirty so the dirty diff is captured correctly
            $account->fill($data);
            $dirty = $account->getDirty();
            $account->save();
            $fresh = $account->fresh();

            $this->recalculateDerived($fresh, $source, 'update');

            event(new SavingsAccountUpdated($fresh, $dirty, $user, $source));

            return $fresh;
        }));
    }

    public function updateOrCreate(array $match, array $data, User $user, IngestSource $source): SavingsAccount
    {
        $existing = SavingsAccount::where('user_id', $user->id)
            ->where($match)
            ->first();

        if ($existing) {
            return $this->update($existing->id, $data, $user, $source);
        }

        return $this->create(array_merge($match, $data), $user, $source);
    }

    // Primary owner only — joint owners cannot delete. Matches pre-store contract.
    public function delete(int $id, User $user, string $reason): void
    {
        $account = SavingsAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        DB::transaction(function () use ($account, $id, $user, $reason) {
            $account->delete();

            event(new SavingsAccountDeleted($id, $user, $reason));
        });
    }

    public function restore(int $id, User $user): SavingsAccount
    {
        $account = SavingsAccount::withTrashed()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return DB::transaction(function () use ($account, $user) {
            $account->restore();

            event(new SavingsAccountRestored($account, $user));

            return $account;
        });
    }

    // ---------- Internal ----------

    /**
     * Materialise the canonical derived columns onto the row and write
     * value snapshots per the per-column SnapshotPolicy. Called inside the
     * create()/update() DB transaction, after the row is persisted.
     */
    private function recalculateDerived(SavingsAccount $account, IngestSource $source, string $reason): void
    {
        $derived = $this->derivedCalc->calculate($account);
        $now = now();

        $oldValues = [
            'balance_gbp' => $account->balance_gbp !== null ? (float) $account->balance_gbp : null,
            'annual_interest_projected_gbp' => $account->annual_interest_projected_gbp !== null ? (float) $account->annual_interest_projected_gbp : null,
            'isa_allowance_used_pct' => $account->isa_allowance_used_pct !== null ? (float) $account->isa_allowance_used_pct : null,
        ];

        $account->fill([
            'balance_gbp' => $derived['balance_gbp'],
            'balance_gbp_calculated_at' => $now,
            'annual_interest_projected_gbp' => $derived['annual_interest_projected_gbp'],
            'annual_interest_projected_gbp_calculated_at' => $now,
            'isa_allowance_used_pct' => $derived['isa_allowance_used_pct'],
            'isa_allowance_used_pct_calculated_at' => $now,
        ])->save();

        $policies = [
            'balance_gbp' => $this->snapshotPolicies->savingsAccountBalance(),
            'annual_interest_projected_gbp' => $this->snapshotPolicies->savingsAnnualInterestProjected(),
            'isa_allowance_used_pct' => $this->snapshotPolicies->savingsIsaAllowanceUsedPct(),
        ];

        foreach ($policies as $column => $policy) {
            // A null derived value means the metric is not applicable to this
            // account (e.g. no interest_rate, or not an ISA). Recording a
            // snapshot for an absent metric would spuriously fire on every
            // write (old===null short-circuits shouldSnapshot to true), so
            // skip — only materialised values are snapshotted.
            if ($derived[$column] === null) {
                continue;
            }

            if (! $policy->shouldSnapshot($oldValues[$column], $derived[$column])) {
                continue;
            }

            SavingsAccountValueSnapshot::create([
                'savings_account_id' => $account->id,
                'column_name' => $column,
                'value' => $derived[$column] ?? 0,
                'currency' => 'GBP',
                'value_gbp' => $derived[$column],
                'taken_at' => $now,
                'trigger_reason' => $reason,
                'ingest_source' => $source->value,
            ]);
        }
    }

    private function validateCanonical(array $data): void
    {
        // Mirrors StoreSavingsAccountRequest / UpdateSavingsAccountRequest — the store does
        // not tighten the outer contract. Canonical-shape sanity check, not a stricter gate.
        $rules = [
            'account_name' => 'sometimes|string|max:255',
            'current_balance' => 'nullable|numeric|min:0',
            'account_type' => 'sometimes|string|max:255',
            'institution' => 'sometimes|string|max:255',
            'interest_rate' => 'sometimes|nullable|numeric|min:0|max:20',
            'is_isa' => 'sometimes|boolean',
            'is_emergency_fund' => 'sometimes|boolean',
            'ownership_type' => 'sometimes|in:individual,joint,tenants_in_common,trust',
            'ownership_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'trust_id' => 'sometimes|nullable|integer|exists:trusts,id',
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            'country' => 'sometimes|nullable|string|max:255',
            'include_in_retirement' => 'sometimes|boolean',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }

    private function validateOwnershipLinks(array $data, User $user): void
    {
        $ownershipType = $data['ownership_type'] ?? 'individual';
        $jointOwnerId = isset($data['joint_owner_id']) && is_numeric($data['joint_owner_id'])
            ? (int) $data['joint_owner_id']
            : null;
        $trustId = isset($data['trust_id']) && is_numeric($data['trust_id'])
            ? (int) $data['trust_id']
            : null;
        $isIsa = ! empty($data['is_isa'])
            || in_array($data['account_type'] ?? null, ['cash_isa', 'stocks_shares_isa', 'lifetime_isa', 'innovative_finance_isa', 'junior_isa'], true);

        if ($isIsa && ($ownershipType !== 'individual' || $jointOwnerId !== null || $trustId !== null)) {
            throw new StoreValidationException([
                'ownership_type' => ['ISAs must be held in one individual name.'],
            ]);
        }

        if (in_array($ownershipType, ['joint', 'tenants_in_common'], true)) {
            $ownershipPercentage = $data['ownership_percentage'] ?? null;
            if (! is_numeric($ownershipPercentage)
                || (float) $ownershipPercentage <= 0
                || (float) $ownershipPercentage >= 100) {
                throw new StoreValidationException([
                    'ownership_percentage' => ['An explicit ownership share between 0% and 100% is required for a shared account.'],
                ]);
            }

            $isReciprocalSpouse = $jointOwnerId !== null
                && (int) $user->spouse_id === $jointOwnerId
                && User::query()->whereKey($jointOwnerId)->where('spouse_id', $user->id)->exists();
            if (! $isReciprocalSpouse) {
                throw new StoreValidationException([
                    'joint_owner_id' => ['The joint owner must be securely linked to your household.'],
                ]);
            }
        } elseif ($jointOwnerId !== null) {
            throw new StoreValidationException([
                'joint_owner_id' => ['A joint owner can only be set for a shared account.'],
            ]);
        }

        if ($ownershipType === 'trust') {
            $isHouseholdTrust = $trustId !== null
                && Trust::query()
                    ->whereKey($trustId)
                    ->where(function ($query) use ($user): void {
                        $query->where('user_id', $user->id);
                        if ($user->household_id !== null) {
                            $query->orWhere('household_id', $user->household_id);
                        }
                    })
                    ->exists();
            if (! $isHouseholdTrust) {
                throw new StoreValidationException([
                    'trust_id' => ['The trust must be securely linked to your household.'],
                ]);
            }
        } elseif ($trustId !== null) {
            throw new StoreValidationException([
                'trust_id' => ['A trust can only be set for a trust-owned account.'],
            ]);
        }
    }
}
