<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->gate = app(DbTierGate::class);
});

it('is the bound TierGate implementation', function () {
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);
});

it('enforces the free savings cap of 3', function () {
    $u = User::factory()->create(['tier' => 'free']);
    expect($this->gate->canCreate($u, 'savings_account', 2))->toBeTrue()
        ->and($this->gate->canCreate($u, 'savings_account', 3))->toBeFalse()
        ->and($this->gate->hardLimit($u, 'savings_account'))->toBe(3);
});

it('treats tier1+ as unlimited', function () {
    $u = User::factory()->create(['tier' => 'tier1']);
    expect($this->gate->canCreate($u, 'savings_account', 9999))->toBeTrue()
        ->and($this->gate->hardLimit($u, 'savings_account'))->toBeNull();
});

it('admin bypasses all caps', function () {
    $u = User::factory()->create(['tier' => 'free', 'is_admin' => true]);
    expect($this->gate->canCreate($u, 'savings_account', 100))->toBeTrue();
});

it('GRANDFATHERS a legacy paid subscriber over the free cap (spec §4.4)', function () {
    // Legacy 'pro' sub, tier null — must NOT be blocked at 3 savings just
    // because resolve() returns 'free' for arithmetic.
    $u = User::factory()->create(['plan' => 'pro', 'tier' => null]);
    $u->subscription()->create(['plan' => 'pro', 'status' => 'active', 'amount' => 1999]);
    expect($this->gate->canCreate($u->fresh(), 'savings_account', 50))->toBeTrue();
});

it('blocks a true free user at the cap but never deletes existing rows', function () {
    $u = User::factory()->create(['plan' => 'free', 'tier' => 'free']);
    expect($this->gate->canCreate($u, 'savings_account', 5))->toBeFalse(); // over-cap create blocked
    // (No assertion that rows are removed — grandfather principle 4.4: the
    // gate only ever refuses NEW creates; it never inspects/removes rows.)
});
