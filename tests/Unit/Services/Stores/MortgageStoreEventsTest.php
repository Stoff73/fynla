<?php

declare(strict_types=1);

use App\Events\Mortgage\MortgageCreated;
use App\Events\Mortgage\MortgageDeleted;
use App\Events\Mortgage\MortgageRestored;
use App\Events\Mortgage\MortgageUpdated;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->property = Property::factory()->create(['user_id' => $this->user->id]);
    $this->store = app(MortgageStore::class);
    Event::fake([MortgageCreated::class, MortgageUpdated::class, MortgageDeleted::class, MortgageRestored::class]);
});

it('dispatches MortgageCreated on create', function () {
    $canonical = [
        'property_id' => $this->property->id,
        'user_id' => $this->user->id,
        'lender_name' => 'Halifax',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 200000.00,
        'monthly_payment' => 1200.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $this->store->create($canonical, $this->user, IngestSource::FORM);

    Event::assertDispatched(MortgageCreated::class, function ($event) {
        return $event->entity instanceof Mortgage
            && $event->user->is($this->user)
            && $event->source === IngestSource::FORM;
    });
});

it('dispatches MortgageUpdated on update with populated changes payload', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'outstanding_balance' => 200000,
    ]);

    $this->store->update($mortgage->id, ['outstanding_balance' => 195000], $this->user, IngestSource::FORM);

    Event::assertDispatched(MortgageUpdated::class, function ($event) {
        return isset($event->changes['outstanding_balance'])
            && $event->changes['outstanding_balance'][1] == 195000;
    });
});

it('dispatches MortgageDeleted on delete', function () {
    $mortgage = Mortgage::factory()->create(['user_id' => $this->user->id, 'property_id' => $this->property->id]);

    $this->store->delete($mortgage->id, $this->user, IngestSource::FORM);

    Event::assertDispatched(MortgageDeleted::class, function ($event) {
        return $event->entity instanceof Mortgage
            && $event->user->is($this->user)
            && $event->force === false;
    });
});

it('dispatches MortgageUpdated with populated changes from updateOrCreate on an existing record', function () {
    $existing = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Halifax',
        'outstanding_balance' => 200000,
        'monthly_payment' => 1200,
        'mortgage_type' => 'repayment',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $canonical = [
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'lender_name' => 'Halifax',
        'outstanding_balance' => 150000,
        'monthly_payment' => 1200,
        'mortgage_type' => 'repayment',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $this->store->updateOrCreate($canonical, $this->user, IngestSource::SEEDER);

    Event::assertDispatched(MortgageUpdated::class, function ($event) {
        return isset($event->changes['outstanding_balance']);
    });
});
