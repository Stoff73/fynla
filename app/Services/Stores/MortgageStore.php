<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Constants\ValidationLimits;
use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Models\AuditLog;
use App\Models\Mortgage;
use App\Models\MortgageValueSnapshot;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Recalc\MortgageDerivedColumnCalculator;
use App\Services\Stores\Snapshots\SnapshotPolicies;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Canonical store for Mortgage entities. Every read and write of
 * App\Models\Mortgage MUST go through this class.
 *
 * Joint-ownership semantics: forUser returns user_id = ? OR joint_owner_id = ?
 * (joint-aware). For primary-only reads, use forUserPrimaryOnly. Pattern locked
 * by MortgageReadConsumerParityTest (PR 5a).
 *
 * Tier-cap key: 'mortgage' (free=10 by default, tier1+=null).
 *
 * Cross-store recalc (PR 6): writes fire events consumed by
 * RecalculatePropertyOutstandingMortgage listener which recomputes
 * properties.outstanding_mortgage from the canonical mortgages sum.
 */
class MortgageStore
{
    public const ENTITY_KEY = 'mortgage';

    public function __construct(
        private readonly TierGate $tierGate,
        private readonly MortgageDerivedColumnCalculator $calculator,
        private readonly SnapshotPolicies $snapshotPolicies,
    ) {}

    // ─── READ METHODS ──────────────────────────────────────────────────────

    public function find(int $id, User $user): ?Mortgage
    {
        return Mortgage::forUserOrJoint($user->id)->find($id);
    }

    public function forUser(User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->get();
    }

    public function forUserPrimaryOnly(User $user): Collection
    {
        return Mortgage::where('user_id', $user->id)->get();
    }

    public function forUserWithJointOwner(User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->with('jointOwner')->get();
    }

    public function forProperty(int $propertyId, ?User $user = null): Collection
    {
        $query = Mortgage::where('property_id', $propertyId);
        if ($user !== null) {
            $query->forUserOrJoint($user->id);
        }

        return $query->get();
    }

    public function forUserByProperty(User $user): Collection
    {
        return $this->forUserWithJointOwner($user)->groupBy('property_id');
    }

    /**
     * @param  array<int>  $ids
     */
    public function findMany(array $ids, User $user): Collection
    {
        return Mortgage::forUserOrJoint($user->id)->whereIn('id', $ids)->get();
    }

    // ─── WRITE METHODS ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $canonical  Output of MortgageNormaliser::from*
     */
    public function create(array $canonical, User $user, IngestSource $source): Mortgage
    {
        $this->validateCanonical($canonical, partial: false);
        $this->enforceTierCap($user);

        $mortgage = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical) {
                $mortgage = Mortgage::create($canonical);
                $this->recalculateDerived($mortgage, null);

                return $mortgage;
            })
        );

        event(new MortgageCreated($mortgage, $user, $source));

        return $mortgage;
    }

    /**
     * @param  array<string, mixed>  $canonical  Partial — only changed fields
     */
    public function update(int $id, array $canonical, User $user, IngestSource $source): Mortgage
    {
        $mortgage = Mortgage::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($canonical, partial: true);

        $result = AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage, $canonical) {
                $previous = $mortgage->replicate();
                $previous->setRawAttributes($mortgage->getOriginal(), true);

                $mortgage->fill($canonical);
                $changes = [];
                foreach ($mortgage->getDirty() as $field => $newValue) {
                    $changes[$field] = [$mortgage->getOriginal($field), $newValue];
                }
                $mortgage->save();
                $fresh = $mortgage->fresh();
                $this->recalculateDerived($fresh, $previous);

                return ['fresh' => $fresh, 'changes' => $changes];
            })
        );

        event(new MortgageUpdated($result['fresh'], $result['changes'], $user, $source));

        return $result['fresh'];
    }

    /**
     * Find-or-create by (user_id, property_id, lender_name) — idempotent for seeders.
     *
     * @param  array<string, mixed>  $canonical
     */
    public function updateOrCreate(array $canonical, User $user, IngestSource $source): Mortgage
    {
        $this->validateCanonical($canonical, partial: false);

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical, $user, $source) {
                $existing = Mortgage::where('user_id', $user->id)
                    ->where('property_id', $canonical['property_id'])
                    ->where('lender_name', $canonical['lender_name'])
                    ->first();

                if ($existing) {
                    $previous = $existing->replicate();
                    $previous->setRawAttributes($existing->getOriginal(), true);

                    $existing->fill($canonical);
                    $changes = [];
                    foreach ($existing->getDirty() as $field => $newValue) {
                        $changes[$field] = [$existing->getOriginal($field), $newValue];
                    }
                    $existing->save();
                    $fresh = $existing->fresh();
                    $this->recalculateDerived($fresh, $previous);
                    event(new MortgageUpdated($fresh, $changes, $user, $source));

                    return $fresh;
                }

                $this->enforceTierCap($user);
                $mortgage = Mortgage::create($canonical);
                $this->recalculateDerived($mortgage, null);
                event(new MortgageCreated($mortgage, $user, $source));

                return $mortgage;
            })
        );
    }

    public function delete(int $id, User $user, IngestSource $source, bool $force = false): void
    {
        $mortgage = Mortgage::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage, $force) {
                $force ? $mortgage->forceDelete() : $mortgage->delete();
            })
        );

        event(new MortgageDeleted($mortgage, $user, $source, $force));
    }

    public function restore(int $id, User $user, IngestSource $source): Mortgage
    {
        $mortgage = Mortgage::withTrashed()->where('user_id', $user->id)->findOrFail($id);
        if (! $mortgage->trashed()) {
            return $mortgage;
        }

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($mortgage) {
                $mortgage->restore();
            })
        );

        $fresh = $mortgage->fresh();
        event(new MortgageRestored($fresh, $user, $source));

        return $fresh;
    }

    // ─── DERIVED COLUMNS + SNAPSHOTS ───────────────────────────────────────

    /**
     * Recompute canonical derived columns and emit snapshots when the
     * configured policies trigger.
     *
     * Cross-store recalc invariant: this method does not dispatch Property
     * events. The Property reconciliation is driven by the Mortgage events
     * emitted by create/update/delete/restore and handled by
     * RecalculatePropertyOutstandingMortgage.
     */
    private function recalculateDerived(Mortgage $mortgage, ?Mortgage $previous): void
    {
        $oldBalance = $previous !== null && $previous->outstanding_balance !== null
            ? (float) $previous->outstanding_balance
            : null;
        $oldRate = $previous !== null && $previous->interest_rate !== null
            ? (float) $previous->interest_rate
            : null;

        $this->calculator->recalculate($mortgage);

        $now = now();

        $balancePolicy = $this->snapshotPolicies->mortgageBalance();
        $newBalance = (float) ($mortgage->outstanding_balance ?? 0);
        if ($balancePolicy->shouldSnapshot($oldBalance, $newBalance)) {
            MortgageValueSnapshot::create([
                'mortgage_id' => $mortgage->id,
                'snapshot_type' => 'mortgageBalance',
                'value' => $newBalance,
                'snapshotted_at' => $now,
            ]);
        }

        $ratePolicy = $this->snapshotPolicies->mortgageRate();
        $newRate = $mortgage->interest_rate !== null ? (float) $mortgage->interest_rate : null;
        if ($newRate !== null && $ratePolicy->shouldSnapshot($oldRate, $newRate)) {
            MortgageValueSnapshot::create([
                'mortgage_id' => $mortgage->id,
                'snapshot_type' => 'mortgageRate',
                'value' => $newRate,
                'snapshotted_at' => $now,
            ]);
        }
    }

    // ─── INTERNAL ──────────────────────────────────────────────────────────

    private function enforceTierCap(User $user): void
    {
        $count = Mortgage::where('user_id', $user->id)->count();

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
        $rules = [
            'property_id' => ($partial ? 'sometimes|' : 'required|').'integer|exists:properties,id',
            'user_id' => ($partial ? 'sometimes|' : 'required|').'integer|exists:users,id',
            'lender_name' => ($partial ? 'sometimes|' : 'required|').'string|max:255',
            'mortgage_type' => ($partial ? 'sometimes|' : 'required|').'in:repayment,interest_only,mixed',
            'outstanding_balance' => ($partial ? 'sometimes|' : 'required|').ValidationLimits::currencyRules(false),
            'monthly_payment' => ($partial ? 'sometimes|' : 'required|').ValidationLimits::currencyRules(false),
            'ownership_type' => ($partial ? 'sometimes|' : 'required|').'in:individual,joint',
            'ownership_percentage' => ($partial ? 'sometimes|' : 'required|').ValidationLimits::percentageRules(false),
            'interest_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'rate_type' => 'sometimes|nullable|in:fixed,variable,tracker,discount,capped,offset',
            'original_loan_amount' => 'sometimes|nullable|'.ValidationLimits::currencyRules(false),
            'remaining_term_months' => 'sometimes|nullable|integer|min:0|max:600',
            'start_date' => 'sometimes|nullable|date',
            'maturity_date' => 'sometimes|nullable|date',
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            'joint_owner_name' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string|max:1000',
        ];

        $validator = Validator::make($canonical, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
