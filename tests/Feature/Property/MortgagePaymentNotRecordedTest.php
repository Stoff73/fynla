<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * T61 (release ac prod walk, 2026-10-13): a buy-to-let mortgage saved with no
 * monthly payment showed "Full Monthly Payment: Not recorded" (T25) and, on the
 * same card, "Full Annual Payment: £0". The property endpoint turned the
 * missing payment into 0 × 12. A payment never given is null on every line.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->property = Property::factory()->create([
        'user_id' => $this->user->id,
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'current_value' => 320000,
        'monthly_rental_income' => 1450,
    ]);
});

it('sends no annual or monthly share for a payment that was never given', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'ownership_type' => 'individual',
        'outstanding_balance' => 180000,
        'monthly_payment' => null,
    ]);
    Sanctum::actingAs($this->user);

    $share = $this->getJson("/api/properties/{$this->property->id}")->assertOk()
        ->json("data.property.mortgage_shares.{$mortgage->id}");

    expect($share['annual_payment'])->toBeNull()
        ->and($share['user_monthly_payment'])->toBeNull()
        ->and((float) $share['user_share'])->toBe(180000.0);
});

it('still sends the annual payment when the monthly payment was given', function () {
    $mortgage = Mortgage::factory()->create([
        'user_id' => $this->user->id,
        'property_id' => $this->property->id,
        'ownership_type' => 'individual',
        'outstanding_balance' => 180000,
        'monthly_payment' => 780,
    ]);
    Sanctum::actingAs($this->user);

    $share = $this->getJson("/api/properties/{$this->property->id}")->assertOk()
        ->json("data.property.mortgage_shares.{$mortgage->id}");

    expect((float) $share['annual_payment'])->toBe(9360.0)
        ->and((float) $share['user_monthly_payment'])->toBe(780.0);
});
