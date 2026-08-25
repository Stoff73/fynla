<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Tiers\DbTierGate;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('caps a free user at the free-tier limit for an entity', function () {
    $this->seed(TierConfigurationSeeder::class);
    $gate = app(DbTierGate::class);
    $user = User::factory()->create(['tier' => 'free']);

    // 'savings_account' has the approved finite Free cap of 2.
    $cap = $gate->hardLimit($user, 'savings_account');
    expect($cap)->toBe(2);

    expect($gate->canCreate($user, 'savings_account', $cap - 1))->toBeTrue();
    expect($gate->canCreate($user, 'savings_account', $cap))->toBeFalse();
});
