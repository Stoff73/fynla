<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
 * T63 (release ac prod walk, 2026-10-13): a property saved through Fyn with no
 * purchase price showed "Purchase Price: £0" on the web Overview and the /m
 * property screen. The column was null; the property summary sent it as 0
 * and its capital growth as the whole value. A price never given is null.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($this->user);
});

it('sends no purchase price or capital growth when no price was given', function () {
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'current_value' => 320000,
        'purchase_price' => null,
    ]);

    $data = $this->getJson("/api/properties/{$property->id}")->assertOk()->json('data.property');

    expect($data['purchase_price'])->toBeNull()
        ->and($data['valuation']['purchase_price'])->toBeNull()
        ->and($data['valuation']['capital_growth'])->toBeNull()
        ->and($data['valuation']['capital_growth_percent'])->toBeNull();
});

it('still sends the purchase price and growth when a price was given', function () {
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'current_value' => 320000,
        'purchase_price' => 250000,
    ]);

    $data = $this->getJson("/api/properties/{$property->id}")->assertOk()->json('data.property');

    expect((float) $data['purchase_price'])->toBe(250000.0)
        ->and((float) $data['valuation']['capital_growth'])->toBe(70000.0)
        ->and((float) $data['valuation']['capital_growth_percent'])->toBe(28.0);
});
