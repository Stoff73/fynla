<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DBPensionValueSnapshot;
use App\Models\DCPension;
use App\Models\DCPensionValueSnapshot;
use App\Models\PensionInputHistory;
use App\Models\StatePension;
use App\Models\StatePensionValueSnapshot;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('PensionStore::createDc persists a DC pension through the canonical write path', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc([
        'scheme_name' => 'Aviva Workplace',
        'pension_type' => 'occupational',
        'provider' => 'Aviva',
        'current_fund_value' => 45000,
        'retirement_age' => 65,
    ], $user, IngestSource::FORM);

    expect($pension)->toBeInstanceOf(DCPension::class);
    expect($pension->user_id)->toBe($user->id);
    expect($pension->scheme_name)->toBe('Aviva Workplace');
    expect((float) $pension->current_fund_value)->toBe(45000.00);
    expect(DCPension::count())->toBe(1);
});

it('PensionStore::createDc rejects writes with invalid canonical shape', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    // pension_type must be one of the allowed enum values. Per spec §7.2
    // the inner validation layer mirrors the form (nullable scheme_name)
    // but still enforces canonical-shape correctness (enum, ranges).
    expect(fn () => $store->createDc(['pension_type' => 'made_up_type'], $user, IngestSource::FORM))
        ->toThrow(StoreValidationException::class);

    expect(DCPension::count())->toBe(0);
});

it('PensionStore::updateDc mutates a DC pension through the canonical write path', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 10000,
    ]);

    $updated = $store->updateDc($pension->id, ['current_fund_value' => 12500], $user, IngestSource::FORM);

    expect((float) $updated->current_fund_value)->toBe(12500.00);
});

it('PensionStore::deleteDc soft-deletes a DC pension', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = DCPension::factory()->create(['user_id' => $user->id]);

    $store->deleteDc($pension->id, $user, 'user_requested');

    expect(DCPension::find($pension->id))->toBeNull();
    expect(DCPension::withTrashed()->find($pension->id))->not->toBeNull();
});

it('PensionStore::createDb persists a DB pension', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDb([
        'scheme_name' => 'NHS 2015',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 12000,
        'normal_retirement_age' => 67,
    ], $user, IngestSource::FORM);

    expect($pension)->toBeInstanceOf(DBPension::class);
    expect($pension->user_id)->toBe($user->id);
    expect($pension->scheme_type)->toBe('career_average');
    expect(DBPension::count())->toBe(1);
});

it('PensionStore::upsertState inserts when no row exists', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $state = $store->upsertState([
        'ni_years_completed' => 28,
        'ni_years_required' => 35,
        'state_pension_forecast_annual' => 9000,
        'state_pension_age' => 67,
    ], $user, IngestSource::FORM);

    expect($state)->toBeInstanceOf(StatePension::class);
    expect(StatePension::where('user_id', $user->id)->count())->toBe(1);
});

it('PensionStore::upsertState updates when row exists (one-per-user invariant)', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $store->upsertState(['ni_years_completed' => 20, 'ni_years_required' => 35], $user, IngestSource::FORM);
    $store->upsertState(['ni_years_completed' => 25], $user, IngestSource::FORM);

    expect(StatePension::where('user_id', $user->id)->count())->toBe(1);
    expect((int) StatePension::where('user_id', $user->id)->first()->ni_years_completed)->toBe(25);
});

it('PensionStore::captureInputHistory writes one row per tax_year', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $written = $store->captureInputHistory([
        ['tax_year' => '2024-25', 'pension_input_amount' => 9000],
        ['tax_year' => '2025-26', 'pension_input_amount' => 12000],
    ], $user, IngestSource::FYN_AI);

    expect($written)->toBe(['2024-25' => 9000.0, '2025-26' => 12000.0]);
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(2);
});

it('PensionStore::captureInputHistory updates an existing tax_year row idempotently', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $store->captureInputHistory([['tax_year' => '2024-25', 'pension_input_amount' => 5000]], $user, IngestSource::FYN_AI);
    $store->captureInputHistory([['tax_year' => '2024-25', 'pension_input_amount' => 9000]], $user, IngestSource::FYN_AI);

    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(1);
    $row = PensionInputHistory::where('user_id', $user->id)->first();
    expect((float) $row->pension_input_amount)->toBe(9000.00);
});

it('PensionStore::find returns the right pension by type', function () {
    $user = User::factory()->create();
    $dc = DCPension::factory()->create(['user_id' => $user->id]);
    $db = DBPension::factory()->create(['user_id' => $user->id]);

    $store = app(PensionStore::class);

    expect($store->find($dc->id, 'dc', $user)->id)->toBe($dc->id);
    expect($store->find($db->id, 'db', $user)->id)->toBe($db->id);
    expect($store->find(999, 'dc', $user))->toBeNull();
});

it('PensionStore::forUser returns all pensions across all types', function () {
    $user = User::factory()->create();
    DCPension::factory(2)->create(['user_id' => $user->id]);
    DBPension::factory(1)->create(['user_id' => $user->id]);
    StatePension::factory()->create(['user_id' => $user->id]);

    $all = app(PensionStore::class)->forUser($user);

    expect($all['dc'])->toHaveCount(2);
    expect($all['db'])->toHaveCount(1);
    expect($all['state'])->not->toBeNull();
});

it('PensionStore::statePension returns null for a user without one', function () {
    $user = User::factory()->create();
    expect(app(PensionStore::class)->statePension($user))->toBeNull();
});

it('PensionStore::updateDc refuses to mutate a pension owned by another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $owner->id]);

    expect(fn () => app(PensionStore::class)->updateDc($pension->id, ['current_fund_value' => 1], $intruder, IngestSource::FORM))
        ->toThrow(ModelNotFoundException::class);
});

// ---------- SP1 Pass 3 / PR 6 — derived columns + snapshots ----------

it('createDc materialises current_fund_value_gbp + writes initial snapshot', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc([
        'scheme_name' => 'Aviva',
        'current_fund_value' => 50000,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
        'monthly_contribution_amount' => 0,
    ], $user, IngestSource::FORM);

    expect((float) $pension->current_fund_value_gbp)->toBe(50000.00)
        ->and($pension->current_fund_value_gbp_calculated_at)->not->toBeNull();

    // Initial creation (old === null) short-circuits shouldSnapshot to true
    // for any non-null derived column, so at least one snapshot row exists.
    expect(DCPensionValueSnapshot::where('dc_pension_id', $pension->id)->count())
        ->toBeGreaterThanOrEqual(1);
});

it('updateDc fires snapshot only when policy threshold exceeded', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDc([
        'scheme_name' => 'Aviva',
        'current_fund_value' => 50000,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
    ], $user, IngestSource::FORM);

    $initial = DCPensionValueSnapshot::where('dc_pension_id', $pension->id)
        ->where('column_name', 'current_fund_value_gbp')
        ->count();

    // Sub-threshold change — abs delta < 500 and % delta < 2% — no new snapshot.
    $store->updateDc($pension->id, ['current_fund_value' => 50300], $user, IngestSource::FORM);
    expect(DCPensionValueSnapshot::where('dc_pension_id', $pension->id)
        ->where('column_name', 'current_fund_value_gbp')
        ->count())->toBe($initial);

    // Super-threshold change — snapshot fires.
    $store->updateDc($pension->id, ['current_fund_value' => 70000], $user, IngestSource::FORM);
    expect(DCPensionValueSnapshot::where('dc_pension_id', $pension->id)
        ->where('column_name', 'current_fund_value_gbp')
        ->count())->toBeGreaterThan($initial);
});

it('createDb materialises projected_annual_pension_at_nra_gbp + writes initial snapshot', function () {
    $user = User::factory()->create();
    $store = app(PensionStore::class);

    $pension = $store->createDb([
        'scheme_name' => 'Civil Service Pension',
        'scheme_type' => 'final_salary',
        'accrued_annual_pension' => 12000,
        'spouse_pension_percent' => 50,
    ], $user, IngestSource::FORM);

    expect((float) $pension->projected_annual_pension_at_nra_gbp)->toBe(12000.00)
        ->and((float) $pension->spouse_pension_projected_gbp)->toBe(6000.00);

    expect(DBPensionValueSnapshot::where('db_pension_id', $pension->id)->count())
        ->toBeGreaterThanOrEqual(1);
});

it('upsertState materialises forecast + writes initial snapshot', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(55)]);
    $store = app(PensionStore::class);

    $state = $store->upsertState([
        'ni_years_completed' => 30,
        'ni_years_required' => 35,
        'state_pension_forecast_annual' => 11000,
        'state_pension_age' => 67,
    ], $user, IngestSource::FORM);

    expect((float) $state->state_pension_forecast_annual_gbp)->toBe(11000.00)
        ->and((float) $state->ni_completion_pct)->toBeGreaterThan(85.0)
        ->and((float) $state->ni_completion_pct)->toBeLessThan(86.0);

    expect(StatePensionValueSnapshot::where('state_pension_id', $state->id)->count())
        ->toBeGreaterThanOrEqual(1);
});
