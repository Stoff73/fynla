<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Events\Pension\DBPensionCreated;
use App\Events\Pension\DBPensionDeleted;
use App\Events\Pension\DBPensionRestored;
use App\Events\Pension\DBPensionUpdated;
use App\Events\Pension\DCPensionCreated;
use App\Events\Pension\DCPensionDeleted;
use App\Events\Pension\DCPensionRestored;
use App\Events\Pension\DCPensionUpdated;
use App\Events\Pension\PensionInputHistoryCaptured;
use App\Events\Pension\StatePensionUpserted;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\PensionInputHistory;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\Normalisers\PensionNormaliser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PensionStore
{
    public const ENTITY_KEY = 'pension_account';

    public function __construct(
        private readonly PensionNormaliser $normaliser,
        private readonly TierGate $tierGate,
    ) {}

    // ---------- Reads ----------

    public function find(int $id, string $type, User $user): DCPension|DBPension|StatePension|null
    {
        $model = $this->modelClassForType($type);

        return $model::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Return every pension the user owns, grouped by type.
     *
     * @return array{dc: Collection, db: Collection, state: ?StatePension, input_history: Collection}
     */
    public function forUser(User $user): array
    {
        return [
            'dc' => DCPension::where('user_id', $user->id)->with('holdings')->get(),
            'db' => DBPension::where('user_id', $user->id)->get(),
            'state' => StatePension::where('user_id', $user->id)->first(),
            'input_history' => PensionInputHistory::where('user_id', $user->id)->orderBy('tax_year')->get(),
        ];
    }

    public function forUserByType(User $user, string $type): Collection
    {
        $model = $this->modelClassForType($type);

        return $model::query()->where('user_id', $user->id)->get();
    }

    public function statePension(User $user): ?StatePension
    {
        return StatePension::where('user_id', $user->id)->first();
    }

    public function pensionInputHistory(User $user, ?string $taxYear = null): Collection|PensionInputHistory|null
    {
        $query = PensionInputHistory::where('user_id', $user->id);
        if ($taxYear !== null) {
            return $query->where('tax_year', $taxYear)->first();
        }

        return $query->orderBy('tax_year')->get();
    }

    // ---------- Writes (DC pension) ----------

    public function createDc(array $data, User $user, IngestSource $source): DCPension
    {
        $this->validateDcCanonical($data);
        $this->enforceTierCap($user);

        $payload = array_merge($data, ['user_id' => $user->id]);
        unset($payload['type']);

        $pension = DB::transaction(fn () => DCPension::create($payload));

        event(new DCPensionCreated($pension, $user, $source));

        return $pension;
    }

    public function updateDc(int $id, array $data, User $user, IngestSource $source): DCPension
    {
        $pension = DCPension::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateDcCanonical($data, partial: true);

        $payload = $data;
        unset($payload['type']);

        $dirty = [];
        $updated = DB::transaction(function () use ($pension, $payload, &$dirty) {
            $pension->fill($payload);
            $dirty = $pension->getDirty();
            $pension->save();

            return $pension->fresh();
        });

        event(new DCPensionUpdated($updated, $dirty, $user, $source));

        return $updated;
    }

    public function updateOrCreateDc(array $match, array $data, User $user, IngestSource $source): DCPension
    {
        $existing = DCPension::where('user_id', $user->id)->where($match)->first();

        if ($existing) {
            return $this->updateDc($existing->id, $data, $user, $source);
        }

        return $this->createDc(array_merge($match, $data), $user, $source);
    }

    public function deleteDc(int $id, User $user, string $reason): void
    {
        $pension = DCPension::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $pension->delete();

        event(new DCPensionDeleted($id, $user, $reason));
    }

    public function restoreDc(int $id, User $user): DCPension
    {
        $pension = DCPension::withTrashed()->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $pension->restore();

        event(new DCPensionRestored($pension, $user));

        return $pension;
    }

    // ---------- Writes (DB pension) ----------

    public function createDb(array $data, User $user, IngestSource $source): DBPension
    {
        $this->validateDbCanonical($data);
        $this->enforceTierCap($user);

        $payload = array_merge($data, ['user_id' => $user->id]);
        unset($payload['type']);

        $pension = DB::transaction(fn () => DBPension::create($payload));

        event(new DBPensionCreated($pension, $user, $source));

        return $pension;
    }

    public function updateDb(int $id, array $data, User $user, IngestSource $source): DBPension
    {
        $pension = DBPension::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $this->validateDbCanonical($data, partial: true);

        $payload = $data;
        unset($payload['type']);

        $dirty = [];
        $updated = DB::transaction(function () use ($pension, $payload, &$dirty) {
            $pension->fill($payload);
            $dirty = $pension->getDirty();
            $pension->save();

            return $pension->fresh();
        });

        event(new DBPensionUpdated($updated, $dirty, $user, $source));

        return $updated;
    }

    public function deleteDb(int $id, User $user, string $reason): void
    {
        $pension = DBPension::where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $pension->delete();

        event(new DBPensionDeleted($id, $user, $reason));
    }

    public function restoreDb(int $id, User $user): DBPension
    {
        $pension = DBPension::withTrashed()->where('id', $id)->where('user_id', $user->id)->firstOrFail();
        $pension->restore();

        event(new DBPensionRestored($pension, $user));

        return $pension;
    }

    // ---------- Writes (State pension — one per user) ----------

    public function upsertState(array $data, User $user, IngestSource $source): StatePension
    {
        $this->validateStateCanonical($data);

        $payload = $data;
        unset($payload['type']);

        $state = DB::transaction(fn () => StatePension::updateOrCreate(
            ['user_id' => $user->id],
            $payload
        ));

        event(new StatePensionUpserted($state, $user, $source, wasRecentlyCreated: $state->wasRecentlyCreated));

        return $state;
    }

    // ---------- Writes (Pension Input History — one per user per tax year) ----------

    /**
     * @return array<string, float> tax_year => pension_input_amount of successfully written rows
     */
    public function captureInputHistory(array $entries, User $user, IngestSource $source): array
    {
        // Entries may already be a normaliser output envelope; unwrap if so.
        if (isset($entries['entries']) && is_array($entries['entries'])) {
            $entries = $entries['entries'];
        }

        $written = [];
        DB::transaction(function () use ($entries, $user, &$written) {
            foreach ($entries as $entry) {
                if (! isset($entry['tax_year'], $entry['pension_input_amount'])) {
                    continue;
                }
                if ((float) $entry['pension_input_amount'] < 0 || (string) $entry['tax_year'] === '') {
                    continue;
                }

                PensionInputHistory::updateOrCreate(
                    ['user_id' => $user->id, 'tax_year' => (string) $entry['tax_year']],
                    ['pension_input_amount' => (float) $entry['pension_input_amount']]
                );
                $written[(string) $entry['tax_year']] = (float) $entry['pension_input_amount'];
            }
        });

        if ($written === []) {
            throw new StoreValidationException(['history' => ['No valid history entries provided.']]);
        }

        event(new PensionInputHistoryCaptured($user, $written, $source));

        return $written;
    }

    // ---------- Internal ----------

    private function modelClassForType(string $type): string
    {
        return match ($type) {
            'dc' => DCPension::class,
            'db' => DBPension::class,
            'state' => StatePension::class,
            default => throw new \InvalidArgumentException("Unknown pension type '{$type}'."),
        };
    }

    private function enforceTierCap(User $user): void
    {
        $count = DCPension::where('user_id', $user->id)->count()
            + DBPension::where('user_id', $user->id)->count();

        if (! $this->tierGate->canCreate($user, self::ENTITY_KEY, $count)) {
            throw new TierLimitExceededException(
                self::ENTITY_KEY,
                $count,
                $this->tierGate->hardLimit($user, self::ENTITY_KEY)
            );
        }
    }

    private function validateDcCanonical(array $data, bool $partial = false): void
    {
        $rules = [
            // Mirrors StoreDCPensionRequest (nullable). Spec §7.2 — the
            // inner layer is a canonical-shape sanity check, NOT a stricter
            // gate. DC pensions are allowed to start with minimal data.
            'scheme_name' => 'sometimes|nullable|string|max:255',
            'pension_type' => 'sometimes|nullable|in:occupational,sipp,personal,stakeholder',
            'provider' => 'sometimes|nullable|string|max:255',
            'current_fund_value' => 'sometimes|numeric|min:0|max:999999999.99',
            'annual_salary' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'employee_contribution_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'employer_contribution_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'employer_matching_limit' => 'sometimes|nullable|numeric|min:0|max:100',
            'monthly_contribution_amount' => 'sometimes|nullable|numeric|min:0',
            'lump_sum_contribution' => 'sometimes|nullable|numeric|min:0',
            'platform_fee_percent' => 'sometimes|nullable|numeric|min:0|max:10',
            'platform_fee_amount' => 'sometimes|nullable|numeric|min:0',
            'advisor_fee_percent' => 'sometimes|nullable|numeric|min:0|max:10',
            'retirement_age' => 'sometimes|nullable|integer|min:50|max:75',
            'expected_return_percent' => 'sometimes|nullable|numeric|min:0|max:20',
            'has_flexibly_accessed' => 'sometimes|boolean',
            'flexible_access_date' => 'sometimes|nullable|date|before_or_equal:today',
            'salary_sacrifice' => 'sometimes|boolean',
            'employer_ni_rebate_pct' => 'sometimes|nullable|numeric|min:0|max:1',
            'beneficiary_id' => 'sometimes|nullable|integer|exists:users,id',
            'beneficiary_name' => 'sometimes|nullable|string|max:255',
            'investment_strategy' => 'sometimes|nullable|string|max:255',
            'member_number' => 'sometimes|nullable|string|max:255',
            'risk_preference' => 'sometimes|nullable|string|max:64',
            'has_custom_risk' => 'sometimes|boolean',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }

    private function validateDbCanonical(array $data, bool $partial = false): void
    {
        $rules = [
            'scheme_name' => ($partial ? 'sometimes|' : 'required|').'string|max:255',
            'scheme_type' => ($partial ? 'sometimes|' : 'required|').'in:final_salary,career_average,public_sector',
            'accrued_annual_pension' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'pensionable_service_years' => 'sometimes|nullable|numeric|min:0|max:99',
            'pensionable_salary' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'normal_retirement_age' => 'sometimes|nullable|integer|min:50|max:75',
            'revaluation_method' => 'sometimes|nullable|string|max:64',
            'spouse_pension_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'lump_sum_entitlement' => 'sometimes|nullable|numeric|min:0',
            'inflation_protection' => 'sometimes|nullable|string|max:64',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }

    private function validateStateCanonical(array $data): void
    {
        $rules = [
            'ni_years_completed' => 'sometimes|nullable|integer|min:0|max:50',
            'ni_years_required' => 'sometimes|nullable|integer|min:0|max:50',
            'state_pension_forecast_annual' => 'sometimes|nullable|numeric|min:0|max:99999.99',
            'state_pension_age' => 'sometimes|nullable|integer|min:55|max:75',
            'already_receiving' => 'sometimes|boolean',
            'ni_gaps' => 'sometimes|nullable|array',
            'gap_fill_cost' => 'sometimes|nullable|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new StoreValidationException($validator->errors()->toArray());
        }
    }
}
