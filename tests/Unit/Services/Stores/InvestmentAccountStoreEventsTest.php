<?php

declare(strict_types=1);

use App\Events\Investment\InvestmentAccountCreated;
use App\Events\Investment\InvestmentAccountDeleted;
use App\Events\Investment\InvestmentAccountRestored;
use App\Events\Investment\InvestmentAccountUpdated;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->store = app(InvestmentAccountStore::class);
    Event::fake([InvestmentAccountCreated::class, InvestmentAccountUpdated::class, InvestmentAccountDeleted::class, InvestmentAccountRestored::class]);
});

function makeEventCanonical(int $userId): array
{
    return [
        'user_id' => $userId,
        'account_name' => 'Events ISA',
        'account_type' => 'isa',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'current_value' => 10000.00,
        'provider' => 'Vanguard',
        'country' => 'United Kingdom',
    ];
}

it('dispatches InvestmentAccountCreated on create', function () {
    $canonical = makeEventCanonical($this->user->id);

    $this->store->create($canonical, $this->user, IngestSource::FORM);

    Event::assertDispatched(InvestmentAccountCreated::class, function ($event) {
        return $event->entity instanceof InvestmentAccount
            && $event->user->is($this->user)
            && $event->source === IngestSource::FORM;
    });
});

it('dispatches InvestmentAccountUpdated on update with populated changes payload', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 10000,
    ]);

    $this->store->update($account->id, ['current_value' => 15000], $this->user, IngestSource::FORM);

    Event::assertDispatched(InvestmentAccountUpdated::class, function ($event) {
        return isset($event->changes['current_value'])
            && $event->changes['current_value'][1] == 15000;
    });
});

it('dispatches InvestmentAccountDeleted on delete', function () {
    $account = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

    $this->store->delete($account->id, $this->user, IngestSource::FORM);

    Event::assertDispatched(InvestmentAccountDeleted::class, function ($event) {
        return $event->entity instanceof InvestmentAccount
            && $event->user->is($this->user)
            && $event->force === false;
    });
});

it('dispatches InvestmentAccountRestored on restore', function () {
    $account = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);
    $account->delete();

    $this->store->restore($account->id, $this->user, IngestSource::FORM);

    Event::assertDispatched(InvestmentAccountRestored::class, function ($event) {
        return $event->entity instanceof InvestmentAccount
            && $event->user->is($this->user)
            && $event->source === IngestSource::FORM;
    });
});

it('dispatches InvestmentAccountUpdated from updateOrCreate on an existing record', function () {
    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'Vanguard',
        'account_name' => 'Events ISA',
        'account_type' => 'isa',
        'current_value' => 10000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $this->store->updateOrCreate(
        match: ['provider' => 'Vanguard', 'account_type' => 'isa'],
        data: ['current_value' => 20000],
        user: $this->user,
        source: IngestSource::SEEDER,
    );

    Event::assertDispatched(InvestmentAccountUpdated::class, function ($event) {
        return isset($event->changes['current_value']);
    });
});

it('dispatches InvestmentAccountCreated from updateOrCreate on a new record', function () {
    $this->store->updateOrCreate(
        match: ['provider' => 'Vanguard', 'account_type' => 'gia'],
        data: [
            'account_name' => 'Brand New GIA',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
            'current_value' => 10000.00,
            'country' => 'United Kingdom',
        ],
        user: $this->user,
        source: IngestSource::SEEDER,
    );

    Event::assertDispatched(InvestmentAccountCreated::class);
});
