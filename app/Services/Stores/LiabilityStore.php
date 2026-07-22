<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Constants\ValidationLimits;
use App\Models\AuditLog;
use App\Models\Estate\Liability;
use App\Models\LiabilityValueSnapshot;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Snapshots\SnapshotPolicies;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LiabilityStore
{
    public function __construct(
        private readonly SnapshotPolicies $snapshotPolicies,
    ) {}

    public function find(int $id, User $user): ?Liability
    {
        return Liability::forUserOrJoint($user->id)->find($id);
    }

    public function forUser(User $user): Collection
    {
        return Liability::forUserOrJoint($user->id)->get();
    }

    public function forUserPrimaryOnly(User $user): Collection
    {
        return Liability::query()->where('user_id', $user->id)->get();
    }

    public function create(array $canonical, User $user, IngestSource $source): Liability
    {
        $canonical['user_id'] = $user->id;
        $canonical['ownership_type'] ??= 'individual';
        $canonical['ownership_percentage'] ??= $canonical['ownership_type'] === 'joint' ? 50 : 100;
        $this->validateCanonical($canonical, partial: false);

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($canonical, $source) {
                $liability = Liability::query()->create($canonical);
                $this->writeBalanceSnapshot($liability, null, $source, 'create');

                return $liability;
            }),
        );
    }

    public function update(int $id, array $canonical, User $user, IngestSource $source): Liability
    {
        $liability = Liability::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->validateCanonical($canonical, partial: true);

        return AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(function () use ($liability, $canonical, $source) {
                $oldBalance = $liability->current_balance === null ? null : (float) $liability->current_balance;
                $liability->fill($canonical);
                $balanceChanged = $liability->isDirty('current_balance');
                $liability->save();

                if ($balanceChanged) {
                    $this->writeBalanceSnapshot($liability, $oldBalance, $source, 'update');
                }

                return $liability->fresh();
            }),
        );
    }

    public function delete(int $id, User $user, IngestSource $source, bool $force = false): void
    {
        $liability = Liability::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        AuditLog::withContext(
            ['ingest_source' => $source->value],
            fn () => DB::transaction(
                fn () => $force ? $liability->forceDelete() : $liability->delete()
            ),
        );
    }

    private function writeBalanceSnapshot(
        Liability $liability,
        ?float $oldBalance,
        IngestSource $source,
        string $reason,
    ): void {
        $newBalance = $liability->current_balance === null ? null : (float) $liability->current_balance;
        if ($newBalance === null
            || ! $this->snapshotPolicies->liabilityBalance()->shouldSnapshot($oldBalance, $newBalance)) {
            return;
        }

        LiabilityValueSnapshot::query()->create([
            'liability_id' => $liability->id,
            'column_name' => 'current_balance',
            'value' => $newBalance,
            'currency' => 'GBP',
            'value_gbp' => $newBalance,
            'taken_at' => now(),
            'trigger_reason' => $reason,
            'ingest_source' => $source->value,
        ]);
    }

    private function validateCanonical(array $canonical, bool $partial): void
    {
        $required = $partial ? 'sometimes|' : 'required|';
        $rules = [
            'user_id' => $required.'integer|exists:users,id',
            'liability_type' => $required.'in:mortgage,secured_loan,personal_loan,credit_card,overdraft,hire_purchase,student_loan,business_loan,other',
            'liability_name' => 'sometimes|nullable|string|max:255',
            'current_balance' => ($partial ? 'sometimes|' : '').ValidationLimits::currencyRules(true),
            'monthly_payment' => 'sometimes|nullable|'.ValidationLimits::currencyRules(false),
            'interest_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'ownership_type' => 'sometimes|in:individual,joint,tenants_in_common,trust',
            'ownership_percentage' => 'sometimes|'.ValidationLimits::percentageRules(false),
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            'trust_id' => 'sometimes|nullable|integer|exists:trusts,id',
            'maturity_date' => 'sometimes|nullable|date',
            'fixed_until' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
        ];

        $validator = Validator::make($canonical, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
