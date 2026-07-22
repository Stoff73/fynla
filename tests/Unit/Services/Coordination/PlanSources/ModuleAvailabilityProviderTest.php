<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\PlanSources\ModuleAvailabilityProvider;

it('returns only the requested module vocabulary', function (): void {
    $keys = array_keys(app(ModuleAvailabilityProvider::class)->forModule('retirement', User::factory()->create()));

    expect($keys)->toEqualCanonicalizing(['dc_pension_exists', 'pension_input_history', 'retirement_age_set']);
});

it('flips retirement and savings availability with real records', function (): void {
    $empty = User::factory()->create(['target_retirement_age' => null]);
    $provider = app(ModuleAvailabilityProvider::class);

    expect($provider->forModule('retirement', $empty)['dc_pension_exists'])->toBeFalse()
        ->and($provider->forModule('savings', $empty)['savings_balances'])->toBeFalse();

    $rich = User::factory()->create(['target_retirement_age' => 67]);
    DCPension::factory()->create(['user_id' => $rich->id]);
    SavingsAccount::factory()->create(['user_id' => $rich->id]);

    expect($provider->forModule('retirement', $rich->fresh())['dc_pension_exists'])->toBeTrue()
        ->and($provider->forModule('retirement', $rich->fresh())['retirement_age_set'])->toBeTrue()
        ->and($provider->forModule('savings', $rich->fresh())['savings_balances'])->toBeTrue();
});

it('flips protection life cover availability with a policy', function (): void {
    $user = User::factory()->create();
    $provider = app(ModuleAvailabilityProvider::class);

    expect($provider->forModule('protection', $user)['life_cover_in_force'])->toBeFalse();

    LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);

    expect($provider->forModule('protection', $user->fresh())['life_cover_in_force'])->toBeTrue();
});

it('returns an empty map for an unknown module', function (): void {
    expect(app(ModuleAvailabilityProvider::class)->forModule('nope', User::factory()->create()))->toBe([]);
});
