<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns an ownership-scoped canonical liability detail', function (): void {
    $user = User::factory()->create();
    $liability = Liability::factory()->for($user)->create([
        'liability_name' => 'Personal loan',
        'liability_type' => 'loan',
        'current_balance' => 12000,
        'monthly_payment' => 350,
        'interest_rate' => 5.9,
        'ownership_type' => 'individual',
        'maturity_date' => '2029-06-01',
    ]);
    Sanctum::actingAs($user);

    $this->getJson("/api/estate/liabilities/{$liability->id}")
        ->assertOk()
        ->assertJsonPath('data.liability.id', $liability->id)
        ->assertJsonPath('data.liability.liability_name', 'Personal loan')
        ->assertJsonPath('data.liability.current_balance', 12000)
        ->assertJsonPath('data.liability.monthly_payment', 350)
        ->assertJsonPath('data.liability.interest_rate', 5.9)
        ->assertJsonPath('data.liability.ownership_type', 'individual')
        ->assertJsonPath('data.liability.is_primary_owner', true);
});

it('does not disclose another users liability', function (): void {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $liability = Liability::factory()->for($owner)->create();
    Sanctum::actingAs($attacker);

    $this->getJson("/api/estate/liabilities/{$liability->id}")->assertNotFound();
});
