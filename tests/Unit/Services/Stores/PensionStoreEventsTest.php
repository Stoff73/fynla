<?php

declare(strict_types=1);

use App\Events\Pension\DBPensionCreated;
use App\Events\Pension\DCPensionCreated;
use App\Events\Pension\DCPensionDeleted;
use App\Events\Pension\DCPensionUpdated;
use App\Events\Pension\PensionInputHistoryCaptured;
use App\Events\Pension\StatePensionUpserted;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PensionStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('createDc emits DCPensionCreated with source', function () {
    Event::fake();
    $user = User::factory()->create();

    app(PensionStore::class)->createDc(
        ['scheme_name' => 'Aviva', 'current_fund_value' => 1000],
        $user,
        IngestSource::FORM
    );

    Event::assertDispatched(DCPensionCreated::class, function ($e) use ($user) {
        return $e->user->id === $user->id && $e->source === IngestSource::FORM;
    });
});

it('updateDc emits DCPensionUpdated with changes diff', function () {
    Event::fake();
    $user = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 1000]);

    app(PensionStore::class)->updateDc($pension->id, ['current_fund_value' => 2500], $user, IngestSource::FORM);

    Event::assertDispatched(DCPensionUpdated::class, function ($e) {
        return array_key_exists('current_fund_value', $e->changes);
    });
});

it('deleteDc emits DCPensionDeleted with reason', function () {
    Event::fake();
    $user = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $user->id]);

    app(PensionStore::class)->deleteDc($pension->id, $user, 'user_requested');

    Event::assertDispatched(DCPensionDeleted::class, function ($e) {
        return $e->reason === 'user_requested';
    });
});

it('createDb emits DBPensionCreated', function () {
    Event::fake();
    $user = User::factory()->create();

    app(PensionStore::class)->createDb(
        ['scheme_name' => 'NHS', 'scheme_type' => 'career_average', 'accrued_annual_pension' => 5000],
        $user,
        IngestSource::FYN_AI
    );

    Event::assertDispatched(DBPensionCreated::class);
});

it('upsertState emits StatePensionUpserted with wasRecentlyCreated boolean', function () {
    Event::fake();
    $user = User::factory()->create();

    app(PensionStore::class)->upsertState(['ni_years_completed' => 10], $user, IngestSource::FORM);

    Event::assertDispatched(StatePensionUpserted::class, function ($e) {
        return $e->wasRecentlyCreated === true;
    });
});

it('captureInputHistory emits PensionInputHistoryCaptured with the written map', function () {
    Event::fake();
    $user = User::factory()->create();

    app(PensionStore::class)->captureInputHistory(
        [['tax_year' => '2024-25', 'pension_input_amount' => 7500]],
        $user,
        IngestSource::FYN_AI
    );

    Event::assertDispatched(PensionInputHistoryCaptured::class, function ($e) {
        return $e->written === ['2024-25' => 7500.0] && $e->source === IngestSource::FYN_AI;
    });
});
