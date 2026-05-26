<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Property\PropertyCreated;
use App\Events\Property\PropertyDeleted;
use App\Events\Property\PropertyRestored;
use App\Events\Property\PropertyUpdated;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PropertyStore
{
    public const ENTITY_KEY = 'property';

    public function __construct(
        private readonly PropertyNormaliser $normaliser,
        private readonly TierGate $tierGate,
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

    // ---------- Writes ----------

    public function create(array $data, User $user, IngestSource $source): Property
    {
        $this->validateCanonical($data);
        $this->enforceTierCap($user);

        $attributes = array_merge($data, ['user_id' => $user->id]);

        $property = AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($attributes) {
            return Property::create($attributes);
        }));

        event(new PropertyCreated($property, $user, $source));

        return $property;
    }

    public function update(int $id, array $data, User $user, IngestSource $source): Property
    {
        $property = Property::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateCanonical($data);

        $result = AuditLog::withContext(['ingest_source' => $source->value], fn () => DB::transaction(function () use ($property, $data) {
            $property->fill($data);
            $dirty = $property->getDirty();
            $property->save();

            return ['fresh' => $property->fresh(), 'dirty' => $dirty];
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
