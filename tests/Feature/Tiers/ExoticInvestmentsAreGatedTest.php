<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0499. `investments_exotic` was `none` on free and `full` on premium, was sold in
 * the upgrade copy as "Advanced investment types" and in the pricing comparison as
 * "Alternative investments", and **nothing in the application read it**. A feature
 * named in a paid tier's differentiators and usable without paying is a commercial
 * problem, not only an untidy one.
 *
 * Gated in the Store rather than on a route, because it is a property of the record
 * and web, `/m` and Fyn all write through the Store and through nothing else.
 */
beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->store = app(InvestmentAccountStore::class);
});

function investmentPayload(array $attributes = []): array
{
    return array_merge([
        'account_name' => 'Test account',
        'account_type' => 'gia',
        'provider' => 'Test provider',
        'current_value' => 10_000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $attributes);
}

it('refuses an exotic account type to a free user', function () {
    $free = User::factory()->create(['tier' => 'free', 'is_preview_user' => false]);

    expect(fn () => $this->store->create(investmentPayload(['account_type' => 'vct']), $free, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);
});

it('refuses an exotic tax relief claim attached to an ordinary account type', function () {
    // The relief is the thing being claimed, and it can be attached to an account of
    // any type — gating only `account_type` would leave the door open.
    $free = User::factory()->create(['tier' => 'free', 'is_preview_user' => false]);

    expect(fn () => $this->store->create(
        investmentPayload(['account_type' => 'gia', 'tax_relief_type' => 'seis']),
        $free,
        IngestSource::FORM
    ))->toThrow(TierLimitExceededException::class);
});

it('lets a premium user create one', function () {
    $premium = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

    $account = $this->store->create(investmentPayload(['account_type' => 'eis']), $premium, IngestSource::FORM);

    expect($account->account_type)->toBe('eis');
});

it('leaves ordinary account types alone for a free user', function () {
    $free = User::factory()->create(['tier' => 'free', 'is_preview_user' => false]);

    $account = $this->store->create(investmentPayload(['account_type' => 'isa']), $free, IngestSource::FORM);

    expect($account->account_type)->toBe('isa');
});

it('gates Fyn on the same rule, because Fyn writes through the same Store', function () {
    $free = User::factory()->create(['tier' => 'free', 'is_preview_user' => false]);

    expect(fn () => $this->store->create(investmentPayload(['account_type' => 'vct']), $free, IngestSource::FYN_AI))
        ->toThrow(TierLimitExceededException::class);
});

it('names the capability, so the client can offer the upgrade', function () {
    $free = User::factory()->create(['tier' => 'free', 'is_preview_user' => false]);

    try {
        $this->store->create(investmentPayload(['account_type' => 'vct']), $free, IngestSource::FORM);
        $this->fail('Expected the exotic capability to be refused.');
    } catch (TierLimitExceededException $e) {
        expect($e->entityKey)->toBe('investments_exotic');
    }
});
