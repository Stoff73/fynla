<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Property\PropertyCreated;
use App\Events\Property\PropertyDeleted;
use App\Events\Property\PropertyRestored;
use App\Events\Property\PropertyUpdated;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\PropertyValueSnapshot;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use App\Services\Stores\Recalc\PropertyDerivedColumnCalculator;
use App\Services\Stores\Snapshots\SnapshotPolicies;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PropertyStore
{
    public const ENTITY_KEY = 'property';

    public function __construct(
        private readonly PropertyNormaliser $normaliser,
        private readonly TierGate $tierGate,
        private readonly PropertyDerivedColumnCalculator $derivedCalc,
        private readonly SnapshotPolicies $snapshotPolicies,
    ) {}

    // ---------- Reads ----------

    public function find(int $id, User $user): ?Property
    {
        return Property::query()
            ->where('id', $id)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->first();
    }

    public function forUser(User $user): Collection
    {
        return Property::forUserOrJoint($user->id)->get();
    }

    public function forUserWithJointOwner(User $user): Collection
    {
        return Property::forUserOrJoint($user->id)->with('jointOwner')->get();
    }

    public function forUsers(array $userIds): Collection
    {
        if ($userIds === []) {
            return new Collection;
        }

        return Property::query()
            ->where(function ($q) use ($userIds) {
                $q->whereIn('user_id', $userIds)->orWhereIn('joint_owner_id', $userIds);
            })
            ->get();
    }

    public function findMany(array $ids, User $user): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return Property::query()
            ->whereIn('id', $ids)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->get();
    }

    public function forUserByType(User $user, string $propertyType): Collection
    {
        return Property::forUserOrJoint($user->id)
            ->where('property_type', $propertyType)
            ->get();
    }

    /**
     * Properties held in a specific trust.
     *
     * Trust-scoped read for TrustAssetAggregatorService and any future trust-context consumer.
     * Distinct from forUser/forUserWithJointOwner — those scope by user; this scopes by the
     * trust_id FK on properties. Used during trust reporting and IHT trust-asset aggregation.
     */
    public function forTrust(int $trustId): Collection
    {
        return Property::query()
            ->where('trust_id', $trustId)
            ->get();
    }

    // ---------- Writes ----------

    public function create(array $data, User $user, IngestSource $source): Property
    {
        $this->validateCanonical($data);
        $this->enforceTierCap($user);

        $attributes = array_merge($data, ['user_id' => $user->id]);

        $property = AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($attributes, $user, $source) {
            $property = Property::create($attributes);
            $this->recalculateDerived($property, $user, $source, 'create');

            return $property;
        }));

        event(new PropertyCreated($property, $user, $source));

        return $property;
    }

    public function update(int $id, array $data, User $user, IngestSource $source): Property
    {
        $property = Property::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($data);

        $result = AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($property, $data, $user, $source) {
            $property->fill($data);
            $dirty = $property->getDirty();
            $property->save();
            $fresh = $property->fresh();
            $this->recalculateDerived($fresh, $user, $source, 'update');

            return ['fresh' => $fresh, 'dirty' => $dirty];
        }));

        event(new PropertyUpdated($result['fresh'], $result['dirty'], $user, $source));

        return $result['fresh'];
    }

    public function updateOrCreate(array $match, array $data, User $user, IngestSource $source): Property
    {
        $existing = Property::where('user_id', $user->id)->where($match)->first();

        if ($existing) {
            return $this->update($existing->id, $data, $user, $source);
        }

        return $this->create(array_merge($match, $data), $user, $source);
    }

    public function delete(int $id, User $user, string $reason): void
    {
        $property = Property::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $property->delete();

        event(new PropertyDeleted($id, $user, $reason));
    }

    public function restore(int $id, User $user): Property
    {
        $property = Property::withTrashed()->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $property->restore();

        event(new PropertyRestored($property, $user));

        return $property;
    }

    // ---------- Derived columns + snapshots ----------

    private function recalculateDerived(Property $property, User $user, IngestSource $source, string $reason): void
    {
        $derived = $this->derivedCalc->calculate($property, $user);
        $now = now();

        $oldValues = [
            'current_value_gbp' => $property->current_value_gbp !== null ? (float) $property->current_value_gbp : null,
            'equity_gbp' => $property->equity_gbp !== null ? (float) $property->equity_gbp : null,
        ];

        $property->fill([
            'current_value_gbp' => $derived['current_value_gbp'],
            'current_value_gbp_calculated_at' => $now,
            'equity_gbp' => $derived['equity_gbp'],
            'equity_gbp_calculated_at' => $now,
            'loan_to_value_pct' => $derived['loan_to_value_pct'],
            'loan_to_value_pct_calculated_at' => $now,
        ])->save();

        $policies = [
            'current_value_gbp' => $this->snapshotPolicies->propertyValue(),
            'equity_gbp' => $this->snapshotPolicies->propertyEquity(),
        ];

        foreach ($policies as $column => $policy) {
            // A null derived value means the metric is not applicable — recording
            // a snapshot would spuriously fire on every write (null short-circuits
            // shouldSnapshot to true). Skip until the metric materialises.
            if ($derived[$column] === null) {
                continue;
            }

            if (! $policy->shouldSnapshot($oldValues[$column], $derived[$column])) {
                continue;
            }

            PropertyValueSnapshot::create([
                'property_id' => $property->id,
                'column_name' => $column,
                'value' => $derived[$column],
                'currency' => 'GBP',
                'value_gbp' => $derived[$column],
                'taken_at' => $now,
                'trigger_reason' => $reason,
                'ingest_source' => $source->value,
            ]);
        }
    }

    // ---------- Internal ----------

    private function enforceTierCap(User $user): void
    {
        $count = Property::where('user_id', $user->id)->count();

        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }
    }

    private function validateCanonical(array $data): void
    {
        $rules = [
            'property_type' => 'sometimes|nullable|in:main_residence,secondary_residence,buy_to_let',
            // Property is the ONLY entity that allows tenants_in_common as ownership_type.
            'ownership_type' => 'sometimes|nullable|in:individual,joint,tenants_in_common,trust',
            'joint_ownership_type' => 'sometimes|nullable|in:joint_tenancy,tenants_in_common',
            'joint_owner_id' => 'sometimes|nullable|integer|exists:users,id',
            'joint_owner_name' => 'sometimes|nullable|string|max:255',
            'household_id' => 'sometimes|nullable|integer|exists:households,id',
            'trust_id' => 'sometimes|nullable|integer|exists:trusts,id',
            'trust_name' => 'sometimes|nullable|string|max:255',
            'ownership_percentage' => 'sometimes|numeric|min:0|max:100',

            'tenure_type' => 'sometimes|nullable|in:freehold,leasehold',
            'lease_remaining_years' => 'sometimes|nullable|integer|min:0|max:999',
            'lease_expiry_date' => 'sometimes|nullable|date',
            'lease_start_date' => 'sometimes|nullable|date',
            'lease_end_date' => 'sometimes|nullable|date',

            'address_line_1' => 'sometimes|nullable|string|max:255',
            'address_line_2' => 'sometimes|nullable|string|max:255',
            'city' => 'sometimes|nullable|string|max:255',
            'county' => 'sometimes|nullable|string|max:255',
            'postcode' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|nullable|string|max:255',

            'purchase_date' => 'sometimes|nullable|date',
            'purchase_price' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'current_value' => 'sometimes|numeric|min:0|max:999999999.99',
            'valuation_date' => 'sometimes|nullable|date',
            'sdlt_paid' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'monthly_rental_income' => 'sometimes|nullable|numeric|min:0',
            'outstanding_mortgage' => 'sometimes|nullable|numeric|min:0|max:999999999.99',

            'tenant_name' => 'sometimes|nullable|string|max:255',
            'tenant_email' => 'sometimes|nullable|email|max:255',
            'managing_agent_name' => 'sometimes|nullable|string|max:255',
            'managing_agent_company' => 'sometimes|nullable|string|max:255',
            'managing_agent_email' => 'sometimes|nullable|email|max:255',
            'managing_agent_phone' => 'sometimes|nullable|string|max:255',
            'managing_agent_fee' => 'sometimes|nullable|numeric|min:0',

            'monthly_council_tax' => 'sometimes|nullable|numeric|min:0',
            'monthly_gas' => 'sometimes|nullable|numeric|min:0',
            'monthly_electricity' => 'sometimes|nullable|numeric|min:0',
            'monthly_water' => 'sometimes|nullable|numeric|min:0',
            'monthly_building_insurance' => 'sometimes|nullable|numeric|min:0',
            'monthly_contents_insurance' => 'sometimes|nullable|numeric|min:0',
            'monthly_service_charge' => 'sometimes|nullable|numeric|min:0',
            'monthly_maintenance_reserve' => 'sometimes|nullable|numeric|min:0',
            'other_monthly_costs' => 'sometimes|nullable|numeric|min:0',

            'notes' => 'sometimes|nullable|string|max:1000',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
