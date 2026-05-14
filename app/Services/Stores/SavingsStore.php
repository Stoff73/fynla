<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Savings\SavingsAccountCreated;
use App\Events\Savings\SavingsAccountDeleted;
use App\Events\Savings\SavingsAccountRestored;
use App\Events\Savings\SavingsAccountUpdated;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SavingsStore
{
    public const ENTITY_KEY = 'savings_account';

    public function __construct(
        private readonly TierGate $tierGate,
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

    // ---------- Writes ----------

    public function create(array $data, User $user, IngestSource $source): SavingsAccount
    {
        $this->validateCanonical($data);

        $count = SavingsAccount::where('user_id', $user->id)->count();
        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }

        $attributes = array_merge($data, ['user_id' => $user->id]);

        return DB::transaction(function () use ($attributes, $user, $source) {
            $account = SavingsAccount::create($attributes);

            event(new SavingsAccountCreated($account, $user, $source));

            return $account;
        });
    }

    /**
     * Update the savings account. Only the primary owner (user_id) may mutate;
     * joint owners have read-only access via find() / forUser(). Matches the
     * pre-store SavingsController::updateAccount contract.
     */
    public function update(int $id, array $data, User $user, IngestSource $source): SavingsAccount
    {
        $account = SavingsAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($data, partial: true);

        return DB::transaction(function () use ($account, $data, $user, $source) {
            // fill before getDirty so the dirty diff is captured correctly
            $account->fill($data);
            $dirty = $account->getDirty();
            $account->save();
            $fresh = $account->fresh();

            event(new SavingsAccountUpdated($fresh, $dirty, $user, $source));

            return $fresh;
        });
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

    private function validateCanonical(array $data, bool $partial = false): void
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
            'ownership_type' => 'sometimes|in:individual,joint,trust',
            'ownership_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            'country' => 'sometimes|nullable|string|max:255',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
