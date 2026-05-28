<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Constants\ValidationLimits;
use App\Events\Investment\InvestmentAccountCreated;
use App\Events\Investment\InvestmentAccountDeleted;
use App\Events\Investment\InvestmentAccountRestored;
use App\Events\Investment\InvestmentAccountUpdated;
use App\Models\AuditLog;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Canonical store for InvestmentAccount entities. Every read and write of
 * App\Models\Investment\InvestmentAccount MUST go through this class.
 *
 * Joint-ownership semantics: forUser returns user_id = ? OR joint_owner_id = ?
 * (joint-aware). For primary-only reads, use forUserPrimaryOnly.
 *
 * Tier-cap key: 'investment' (free=2 by default, tier1+=null).
 */
class InvestmentAccountStore
{
    public const ENTITY_KEY = 'investment';

    public function __construct(
        private readonly TierGate $tierGate,
    ) {}

    // ─── READ METHODS ──────────────────────────────────────────────────────

    public function find(int $id, User $user): ?InvestmentAccount
    {
        return InvestmentAccount::forUserOrJoint($user->id)->find($id);
    }

    public function forUser(User $user): Collection
    {
        return InvestmentAccount::forUserOrJoint($user->id)->get();
    }

    public function forUserPrimaryOnly(User $user): Collection
    {
        return InvestmentAccount::where('user_id', $user->id)->get();
    }

    public function forUserWithJointOwner(User $user): Collection
    {
        return InvestmentAccount::forUserOrJoint($user->id)->with('jointOwner')->get();
    }

    public function forUserByType(User $user, string $accountType): Collection
    {
        return InvestmentAccount::forUserOrJoint($user->id)
            ->where('account_type', $accountType)
            ->get();
    }

    /**
     * @param  array<int>  $ids
     */
    public function findMany(array $ids, User $user): Collection
    {
        return InvestmentAccount::forUserOrJoint($user->id)->whereIn('id', $ids)->get();
    }

    // ─── WRITE METHODS ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical  Output of InvestmentAccountNormaliser::from*
     */
    public function create(array $canonical, User $user, IngestSource $source): InvestmentAccount
    {
        $this->validateCanonical($canonical, partial: false);
        $this->enforceTierCap($user);

        $account = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical) {
                return InvestmentAccount::create($canonical);
            })
        );

        event(new InvestmentAccountCreated($account, $user, $source));

        return $account;
    }

    /**
     * @param  array<string, mixed>  $canonical  Partial — only changed fields
     */
    public function update(int $id, array $canonical, User $user, IngestSource $source): InvestmentAccount
    {
        $account = InvestmentAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($canonical, partial: true);

        $result = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($account, $canonical) {
                $account->fill($canonical);
                $changes = [];
                foreach ($account->getDirty() as $field => $newValue) {
                    $changes[$field] = [$account->getOriginal($field), $newValue];
                }
                $account->save();

                return ['fresh' => $account->fresh(), 'changes' => $changes];
            })
        );

        event(new InvestmentAccountUpdated($result['fresh'], $result['changes'], $user, $source));

        return $result['fresh'];
    }

    /**
     * Find-or-create by (user_id, account_name, account_type) — idempotent for seeders.
     *
     * @param  array<string, mixed>  $canonical
     */
    public function updateOrCreate(array $canonical, User $user, IngestSource $source): InvestmentAccount
    {
        $this->validateCanonical($canonical, partial: false);

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical, $user, $source) {
                $existing = InvestmentAccount::where('user_id', $user->id)
                    ->where('account_name', $canonical['account_name'])
                    ->where('account_type', $canonical['account_type'])
                    ->first();

                if ($existing) {
                    $existing->fill($canonical);
                    $changes = [];
                    foreach ($existing->getDirty() as $field => $newValue) {
                        $changes[$field] = [$existing->getOriginal($field), $newValue];
                    }
                    $existing->save();
                    $fresh = $existing->fresh();
                    event(new InvestmentAccountUpdated($fresh, $changes, $user, $source));

                    return $fresh;
                }

                $this->enforceTierCap($user);
                $account = InvestmentAccount::create($canonical);
                event(new InvestmentAccountCreated($account, $user, $source));

                return $account;
            })
        );
    }

    public function delete(int $id, User $user, IngestSource $source, bool $force = false): void
    {
        $account = InvestmentAccount::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($account, $force) {
                $force ? $account->forceDelete() : $account->delete();
            })
        );

        event(new InvestmentAccountDeleted($account, $user, $source, $force));
    }

    public function restore(int $id, User $user, IngestSource $source): InvestmentAccount
    {
        $account = InvestmentAccount::withTrashed()->where('user_id', $user->id)->findOrFail($id);
        if (! $account->trashed()) {
            return $account;
        }

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($account) {
                $account->restore();
            })
        );

        $fresh = $account->fresh();
        event(new InvestmentAccountRestored($fresh, $user, $source));

        return $fresh;
    }

    // ─── INTERNAL ──────────────────────────────────────────────────────────

    private function enforceTierCap(User $user): void
    {
        $count = InvestmentAccount::where('user_id', $user->id)->count();

        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }
    }

    // ─── VALIDATION ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical
     */
    private function validateCanonical(array $canonical, bool $partial): void
    {
        $req = $partial ? 'sometimes|' : 'required|';

        $rules = [
            'user_id' => $req.'integer|exists:users,id',
            'account_name' => $req.'string|max:255',
            'account_type' => $req.'in:isa,gia,nsi,onshore_bond,offshore_bond,vct,eis,private_company,crowdfunding,saye,csop,emi,unapproved_options,rsu,other',
            'ownership_type' => $req.'in:individual,joint',
            'ownership_percentage' => ($partial ? 'sometimes|' : 'required|').ValidationLimits::percentageRules(false),
            'current_value' => 'sometimes|nullable|'.ValidationLimits::currencyRules(false),
            'provider' => 'sometimes|nullable|string|max:255',
            'country' => 'sometimes|nullable|string|max:255',
            'isa_type' => 'sometimes|nullable|in:stocks_and_shares,lifetime,innovative_finance',
            'contributions_ytd' => 'sometimes|nullable|'.ValidationLimits::currencyRules(false),
            'monthly_contribution_amount' => 'sometimes|nullable|'.ValidationLimits::currencyRules(false),
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
        ];

        $validator = Validator::make($canonical, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
